<?php
/**
 * config/marcacoes.php — lógica partilhada de marcações (site público + gestão)
 * Usar com: require_once __DIR__ . '/../config/marcacoes.php';
 */
declare(strict_types=1);

date_default_timezone_set('Europe/Lisbon');

// Importa as funções de envio de email (PHPMailer / Brevo)
require_once __DIR__ . '/mailer.php';

/* ---------- Definições de negócio ---------- */
const VERIFICAR_MARCACOES         = true;   // true = o cliente confirma por código enviado por email
const ENVIAR_EMAIL                = true;   // usa mail() do PHP; se o servidor não tiver SMTP, o envio falha
const MOSTRAR_CODIGO_NO_ECRA      = false;  // ⚠ SÓ PARA DEMONSTRAÇÃO/DESENVOLVIMENTO. Em produção tem de ser false.
const CODIGO_VALIDADE_MIN         = 15;     // minutos que o horário fica reservado à espera do código
const MAX_TENTATIVAS_CODIGO       = 5;
const MAX_REENVIOS                = 2;
const ANTECEDENCIA_MIN            = 30;     // não se marca para daqui a menos de X minutos
const MAX_DIAS                    = 30;     // até quantos dias à frente se pode marcar
const MAX_MARCACOES_ATIVAS_CLIENTE = 3;     // limite anti-abuso por telemóvel

const DIAS_SEMANA = ['Domingo','Segunda-feira','Terça-feira','Quarta-feira','Quinta-feira','Sexta-feira','Sábado'];
const DIAS_ISO    = [1 => 'Segunda', 2 => 'Terça', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sábado', 7 => 'Domingo'];
const MESES       = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];

/* ---------- Utilitários ---------- */
if (!function_exists('e')) {
    function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('corHex')) {
    function corHex($v, string $padrao): string {
        return preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', (string)$v) ? (string)$v : $padrao;
    }
}
function dataExtenso(DateTime $d): string {
    return DIAS_SEMANA[(int)$d->format('w')] . ', ' . $d->format('j') . ' de ' . MESES[(int)$d->format('n') - 1];
}
/** "HH:MM" ou "HH:MM:SS" → minutos desde as 00:00 (ou $padrao se inválido) */
function paraMinutos(string $hhmm, int $padrao): int {
    return preg_match('/^(\d{1,2}):(\d{2})/', $hhmm, $m) ? ((int)$m[1] * 60 + (int)$m[2]) : $padrao;
}
/** Data/hora atual no formato da BD. Usamos sempre a hora do PHP (e não NOW()) para não haver desvios de fuso. */
function agoraSql(): string { return date('Y-m-d H:i:s'); }

/* ---------- Configuração da barbearia ---------- */
function configBarbearia(PDO $pdo, int $bid): array {
    $st = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE barbearia_id = ?");
    $st->execute([$bid]);
    $cfg = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $cfg[$r['chave']] = $r['valor'];
    return $cfg;
}
/** Dias de funcionamento (ISO: 1=Seg ... 7=Dom), a partir de configuracoes.dias_funcionamento */
function diasFuncionamento(array $cfg): array {
    $dias = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string)($cfg['dias_funcionamento'] ?? '1,2,3,4,5,6'))),
        fn($d) => $d >= 1 && $d <= 7
    )));
    sort($dias);
    return $dias ?: [1, 2, 3, 4, 5, 6];
}
/** [1,2,3,4,5,6] → "Segunda a Sábado"; [1,3,5] → "Segunda, Quarta e Sexta" */
function rotuloDias(array $dias): string {
    sort($dias);
    $n = count($dias);
    if ($n === 0) return '';
    if ($n === 1) return DIAS_ISO[$dias[0]];
    if ($n >= 3 && $dias[$n - 1] - $dias[0] === $n - 1) return DIAS_ISO[$dias[0]] . ' a ' . DIAS_ISO[$dias[$n - 1]];
    $nomes = array_map(fn($d) => DIAS_ISO[$d], $dias);
    $ultimo = array_pop($nomes);
    return implode(', ', $nomes) . ' e ' . $ultimo;
}
function intervaloMinutos(array $cfg): int {
    $v = (int)($cfg['intervalo_marcacoes'] ?? 15);
    return max(5, min(60, $v ?: 15));
}

/* ---------- Barbeiros que se podem reservar ---------- */
/** Barbeiros ativos; se a barbearia não tiver nenhum, usa os gestores ativos (caso de barbearias de 1 pessoa). */
function barbeirosReservaveis(PDO $pdo, int $bid): array {
    foreach (['barbeiro', 'gestor'] as $tipo) {
        $st = $pdo->prepare("SELECT id, nome FROM utilizadores WHERE barbearia_id = ? AND ativo = 1 AND tipo = ? ORDER BY nome");
        $st->execute([$bid, $tipo]);
        $lista = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($lista) return $lista;
    }
    return [];
}

/* ---------- Disponibilidade ---------- */
/**
 * Janelas de trabalho (em minutos) de um barbeiro num dia da semana ISO.
 * Se o barbeiro tiver horário próprio em horarios_barbeiro, usa-o (limitado ao horário da barbearia);
 * se não tiver nenhum registo, usa o horário geral da barbearia.
 */
function janelasDoDia(PDO $pdo, int $barbeiroId, int $diaIso, int $abre, int $fecha): array {
    $st = $pdo->prepare("SELECT dia_semana, hora_inicio, hora_fim FROM horarios_barbeiro WHERE barbeiro_id = ? AND ativo = 1");
    $st->execute([$barbeiroId]);
    $linhas = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$linhas) return [[$abre, $fecha]];

    $janelas = [];
    foreach ($linhas as $l) {
        if ((int)$l['dia_semana'] !== $diaIso) continue;
        $ini = max($abre,  paraMinutos((string)$l['hora_inicio'], $abre));
        $fim = min($fecha, paraMinutos((string)$l['hora_fim'], $fecha));
        if ($fim > $ini) $janelas[] = [$ini, $fim];
    }
    return $janelas;
}

/**
 * Inícios livres ("HH:MM") de um barbeiro num dia, para um serviço de $duracao minutos.
 * Ocupam horário: marcações confirmadas/concluídas e as pendentes de verificação cujo prazo ainda não terminou.
 * Com $bloquear = true usa FOR UPDATE (verificação final dentro da transação).
 */
function slotsLivres(PDO $pdo, int $bid, int $barbeiroId, DateTime $dia, int $duracao, array $cfg, bool $bloquear = false): array {
    if (!in_array((int)$dia->format('N'), diasFuncionamento($cfg), true)) return [];

    $abre  = paraMinutos((string)($cfg['hora_abertura'] ?? ''), 9 * 60);
    $fecha = paraMinutos((string)($cfg['hora_fecho'] ?? ''), 19 * 60);
    $passo = intervaloMinutos($cfg);
    $janelas = janelasDoDia($pdo, $barbeiroId, (int)$dia->format('N'), $abre, $fecha);
    if (!$janelas) return [];

    $ini = (clone $dia)->setTime(0, 0);
    $fim = (clone $ini)->modify('+1 day');

    $sql = "SELECT inicio, fim FROM marcacoes
            WHERE barbearia_id = ? AND barbeiro_id = ?
              AND inicio < ? AND fim > ?
              AND (estado IN ('confirmada','concluida')
                   OR (estado = 'pendente_verificacao' AND (codigo_expira_em IS NULL OR codigo_expira_em > ?)))"
         . ($bloquear ? ' FOR UPDATE' : '');
    $st = $pdo->prepare($sql);
    $st->execute([$bid, $barbeiroId, $fim->format('Y-m-d H:i:s'), $ini->format('Y-m-d H:i:s'), agoraSql()]);
    $ocupados = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ocupados[] = [strtotime($r['inicio']), strtotime($r['fim'])];
    }

    $minimo = time() + ANTECEDENCIA_MIN * 60;
    $livres = [];
    foreach ($janelas as [$ja, $jf]) {
        for ($m = $ja; $m + $duracao <= $jf; $m += $passo) {
            $inicioSlot = (clone $ini)->setTime(intdiv($m, 60), $m % 60);
            $fimSlot    = (clone $inicioSlot)->modify("+{$duracao} minutes");
            $ts = $inicioSlot->getTimestamp();
            $te = $fimSlot->getTimestamp();
            if ($ts < $minimo) continue;
            $livre = true;
            foreach ($ocupados as [$os, $oe]) {
                if ($ts < $oe && $te > $os) { $livre = false; break; }
            }
            if ($livre) $livres[$inicioSlot->format('H:i')] = true;
        }
    }
    $horas = array_keys($livres);
    sort($horas);
    return $horas;
}

/* Verificação por email */
function gerarCodigo(): string { return (string)random_int(100000, 999999); }

/** Marca como expiradas as marcações por verificar cujo prazo terminou (só da barbearia indicada). */
function expirarPendentes(PDO $pdo, int $bid): void {
    $pdo->prepare("UPDATE marcacoes SET estado = 'expirada'
                   WHERE barbearia_id = ? AND estado = 'pendente_verificacao'
                     AND codigo_expira_em IS NOT NULL AND codigo_expira_em < ?")
        ->execute([$bid, agoraSql()]);
}