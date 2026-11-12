<?php
declare(strict_types=1);

/**
 * Lógica de reservas partilhável (API v1; o web pode passar a usá-la quando for retomado).
 * Extraída de publico/marcar.php e publico/verificar.php — mesmas regras e mensagens.
 * Sem I/O HTTP: os erros de negócio são lançados como ReservaErro.
 */

require_once __DIR__ . '/marcacoes.php';
require_once __DIR__ . '/mailer_codigo.php';

final class ReservaErro extends RuntimeException {
    public function __construct(
        public readonly int $status,
        public readonly string $codigo,
        string $mensagem,
        public readonly array $extra = []
    ) {
        parent::__construct($mensagem);
    }
}

/** Valida "Y-m-d" dentro de [hoje, hoje + MAX_DIAS] (regra de validarEscolha em publico/marcar.php). */
function validarDataMarcacao(string $dt): DateTime {
    $dia = DateTime::createFromFormat('!Y-m-d', $dt);
    if (!$dia || $dia->format('Y-m-d') !== $dt) throw new ReservaErro(422, 'data_invalida', 'Escolhe uma data válida.');
    $hoje = new DateTime('today');
    $max  = (clone $hoje)->modify('+' . MAX_DIAS . ' days');
    if ($dia < $hoje || $dia > $max) {
        throw new ReservaErro(422, 'data_invalida', 'Só é possível marcar nos próximos ' . MAX_DIAS . ' dias.');
    }
    return $dia;
}

/** Serviço ativo da barbearia ou ReservaErro 404. */
function servicoDaBarbearia(PDO $pdo, int $bid, int $servicoId): array {
    $st = $pdo->prepare("SELECT id, nome, duracao_minutos, preco FROM servicos WHERE id = ? AND barbearia_id = ? AND ativo = 1");
    $st->execute([$servicoId, $bid]);
    $s = $st->fetch(PDO::FETCH_ASSOC);
    if (!$s) throw new ReservaErro(404, 'servico_nao_encontrado', 'Serviço não encontrado.');
    return $s;
}

/** Telemóvel português: aceita espaços, +351 e 00351 (regra de publico/marcar.php). */
function normalizarTelemovel(string $bruto): ?string {
    $d = preg_replace('/\D/', '', $bruto);
    if (strlen($d) === 12 && str_starts_with($d, '351'))   $d = substr($d, 3);
    if (strlen($d) === 14 && str_starts_with($d, '00351')) $d = substr($d, 5);
    return preg_match('/^[239]\d{8}$/', $d) ? $d : null;
}

/**
 * Valida o pedido de marcação (mesmas regras e mensagens de publico/marcar.php).
 * @return array{servico:array, barbeiro:array, dia:DateTime, hora:string, inicio:DateTime, fim:DateTime, nome:string, telemovel:string, email:string, notas:string}
 */
function validarDadosMarcacao(PDO $pdo, int $bid, array $d): array {
    $invalido = fn(string $msg) => new ReservaErro(422, 'dados_invalidos', $msg);
    $sv = $d['servico_id'] ?? null; $bb = $d['barbeiro_id'] ?? null;
    if (!is_int($sv) && !(is_string($sv) && ctype_digit($sv))) throw $invalido('Escolhe o serviço e o barbeiro.');
    if (!is_int($bb) && !(is_string($bb) && ctype_digit($bb))) throw $invalido('Escolhe o serviço e o barbeiro.');

    $servico  = servicoDaBarbearia($pdo, $bid, (int)$sv);
    $barbeiro = barbeiroDaBarbearia($pdo, $bid, (int)$bb);
    $dia      = validarDataMarcacao((string)($d['data'] ?? ''));
    if (!in_array((int)$dia->format('N'), diasFuncionamento(configBarbearia($pdo, $bid)), true)) {
        throw new ReservaErro(422, 'data_invalida', 'A barbearia está encerrada nesse dia.');
    }

    $hora  = (string)($d['hora'] ?? '');
    $nome  = trim((string)($d['nome'] ?? ''));
    $email = trim((string)($d['email'] ?? ''));
    $notas = trim((string)($d['notas'] ?? ''));
    $tel   = normalizarTelemovel((string)($d['telemovel'] ?? ''));

    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora))             throw $invalido('Escolhe um horário.');
    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120)                throw $invalido('Indica o teu nome (2 a 120 caracteres).');
    if ($tel === null)                                                 throw $invalido('Indica um telemóvel português válido (9 dígitos).');
    if (VERIFICAR_MARCACOES && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw $invalido('Indica um email válido: enviamos-te aí o código de confirmação.');
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) throw $invalido('O email indicado não é válido.');
    if (mb_strlen($notas) > 300)                                       throw $invalido('As notas podem ter no máximo 300 caracteres.');
    if (isset($d['para_nome']) && !is_string($d['para_nome']))         throw $invalido('Indica o nome da pessoa (2 a 120 caracteres).');
    $paraNome = normalizarParaNome((string)($d['para_nome'] ?? ''), $nome);
    if ($paraNome !== null && (mb_strlen($paraNome) < 2 || mb_strlen($paraNome) > 120)) {
        throw $invalido('Indica o nome da pessoa (2 a 120 caracteres).');
    }

    [$h, $mi] = array_map('intval', explode(':', $hora));
    $inicio = (clone $dia)->setTime($h, $mi);
    $fim    = (clone $inicio)->modify('+' . (int)$servico['duracao_minutos'] . ' minutes');

    return compact('servico', 'barbeiro', 'dia', 'hora', 'inicio', 'fim', 'nome', 'email', 'notas') + ['telemovel' => $tel, 'para_nome' => $paraNome];
}

/**
 * "Para quem é a marcação": espaços normalizados; vazio ou igual ao nome de quem marca → null (é para o próprio).
 */
function normalizarParaNome(string $paraNome, string $nomeCliente): ?string {
    $p = trim((string)preg_replace('/\s+/u', ' ', $paraNome));
    if ($p === '' || mb_strtolower($p) === mb_strtolower(trim($nomeCliente))) return null;
    return $p;
}

/**
 * Cria a marcação (transação idêntica a publico/marcar.php) e envia o código por email depois do commit.
 * @param array{id:int, nome:string} $barbearia
 * @return array{marcacao_id:int, cliente_id:int, estado:string, codigo_expira_em:?string, codigo:?string, email_enviado:bool}
 */
function criarMarcacao(PDO $pdo, array $barbearia, array $dados): array {
    $bid = (int)$barbearia['id'];
    $v   = validarDadosMarcacao($pdo, $bid, $dados);
    $cfg = configBarbearia($pdo, $bid);
    $verificar = VERIFICAR_MARCACOES;
    $codigo    = $verificar ? gerarCodigo() : null;
    $agora     = agoraSql();
    $expira    = $verificar ? (new DateTime())->modify('+' . CODIGO_VALIDADE_MIN . ' minutes')->format('Y-m-d H:i:s') : null;

    $pdo->beginTransaction();
    try {
        // Serializa as marcações do mesmo barbeiro (evita duas pessoas na mesma hora)
        $lock = $pdo->prepare("SELECT id FROM utilizadores WHERE id = ? AND barbearia_id = ? FOR UPDATE");
        $lock->execute([(int)$v['barbeiro']['id'], $bid]);
        $lock->fetch();

        $livres = slotsLivres($pdo, $bid, (int)$v['barbeiro']['id'], $v['dia'], (int)$v['servico']['duracao_minutos'], $cfg, true);
        if (!in_array($v['hora'], $livres, true)) {
            throw new ReservaErro(409, 'slot_ocupado', 'Esse horário acabou de ser ocupado. Escolhe outro, por favor.');
        }

        $st = $pdo->prepare(
            "INSERT INTO clientes (barbearia_id, nome, telemovel, email) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), email = COALESCE(email, VALUES(email))"
        );
        $st->execute([$bid, $v['nome'], $v['telemovel'], $v['email'] !== '' ? $v['email'] : null]);
        $clienteId = (int)$pdo->lastInsertId();

        // As regras contam por PESSOA: o cliente (telemóvel) + para quem é a marcação ('' = o próprio).
        // A collation da BD (utf8mb4_unicode_ci) já ignora maiúsculas e espaços finais: "Tiago" = "tiago ".
        $pessoa = $v['para_nome'] ?? '';

        // Regra definida pelo grupo: no máximo uma marcação ativa por pessoa e por dia.
        // Contam as confirmadas/concluídas e as pendentes cujo código ainda não expirou.
        $diaIni = (clone $v['dia'])->setTime(0, 0);
        $diaFim = (clone $diaIni)->modify('+1 day');
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM marcacoes
             WHERE barbearia_id = ? AND cliente_id = ? AND COALESCE(para_nome, '') = ? AND inicio >= ? AND inicio < ?
               AND (estado IN ('confirmada','concluida')
                    OR (estado = 'pendente_verificacao' AND (codigo_expira_em IS NULL OR codigo_expira_em > ?)))"
        );
        $st->execute([$bid, $clienteId, $pessoa, $diaIni->format('Y-m-d H:i:s'), $diaFim->format('Y-m-d H:i:s'), $agora]);
        if ((int)$st->fetchColumn() > 0) {
            throw new ReservaErro(409, 'marcacao_no_dia', $pessoa === ''
                ? 'Já tens uma marcação para este dia. Escolhe outro dia ou contacta a barbearia.'
                : "Já existe uma marcação para {$pessoa} neste dia. Escolhe outro dia ou contacta a barbearia.");
        }

        // Limite anti-abuso (MAX_MARCACOES_ATIVAS_CLIENTE), também por pessoa
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM marcacoes
             WHERE barbearia_id = ? AND cliente_id = ? AND COALESCE(para_nome, '') = ? AND inicio > ?
               AND (estado = 'confirmada' OR (estado = 'pendente_verificacao' AND (codigo_expira_em IS NULL OR codigo_expira_em > ?)))"
        );
        $st->execute([$bid, $clienteId, $pessoa, $agora, $agora]);
        if ((int)$st->fetchColumn() >= MAX_MARCACOES_ATIVAS_CLIENTE) {
            throw new ReservaErro(409, 'limite_marcacoes', 'Já tens várias marcações ativas. Aguarda ou contacta a barbearia para alterar.');
        }

        $st = $pdo->prepare(
            "INSERT INTO marcacoes (barbearia_id, cliente_id, barbeiro_id, servico_id, inicio, fim, estado, notas, para_nome, canal_verificacao, codigo_expira_em, verificado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $st->execute([
            $bid, $clienteId, (int)$v['barbeiro']['id'], (int)$v['servico']['id'],
            $v['inicio']->format('Y-m-d H:i:s'), $v['fim']->format('Y-m-d H:i:s'),
            $verificar ? 'pendente_verificacao' : 'confirmada',
            $v['notas'] !== '' ? $v['notas'] : null,
            $v['para_nome'],
            $verificar ? 'email' : null,
            $expira,
            $verificar ? null : $agora,
        ]);
        $marcacaoId = (int)$pdo->lastInsertId();

        $verifId = 0;
        if ($verificar) {
            $st = $pdo->prepare("INSERT INTO verificacoes (marcacao_id, canal, destino, codigo_hash, sucesso) VALUES (?, 'email', ?, ?, 0)");
            $st->execute([$marcacaoId, $v['email'], password_hash((string)$codigo, PASSWORD_DEFAULT)]);
            $verifId = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    $enviado = false;
    if ($verificar) {
        $emailBarb = (string)($cfg['email_contacto'] ?? '');
        $enviado = enviarCodigoEmail($v['email'], (string)$barbearia['nome'], (string)$codigo, $emailBarb);
        if ($enviado) $pdo->prepare("UPDATE verificacoes SET sucesso = 1 WHERE id = ?")->execute([$verifId]);
    }

    return [
        'marcacao_id'      => $marcacaoId,
        'cliente_id'       => $clienteId,
        'estado'           => $verificar ? 'pendente_verificacao' : 'confirmada',
        'codigo_expira_em' => $expira,
        'codigo'           => $codigo,
        'email_enviado'    => $enviado,
    ];
}

/** Marcação com dados de apresentação; só de barbearias ativas. */
function carregarMarcacaoPorId(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare(
        "SELECT m.id, m.barbearia_id, b.nome AS barbearia_nome, b.slug AS barbearia_slug, m.cliente_id, c.email,
                m.servico_id, m.barbeiro_id,
                m.estado, m.inicio, m.fim, m.codigo_expira_em, m.tentativas_validacao,
                s.nome AS servico_nome, u.nome AS barbeiro_nome, m.para_nome
         FROM marcacoes m
         JOIN barbearias   b ON b.id = m.barbearia_id AND b.ativa = 1
         JOIN clientes     c ON c.id = m.cliente_id  AND c.barbearia_id = m.barbearia_id
         JOIN servicos     s ON s.id = m.servico_id  AND s.barbearia_id = m.barbearia_id
         JOIN utilizadores u ON u.id = m.barbeiro_id AND u.barbearia_id = m.barbearia_id
         WHERE m.id = ?"
    );
    $st->execute([$id]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    return $m ?: null;
}

/** Aplica expiração e limite de tentativas (normalizarEstado de publico/verificar.php). */
function normalizarEstadoMarcacao(PDO $pdo, array $m): array {
    if ($m['estado'] === 'pendente_verificacao') {
        $expirou = $m['codigo_expira_em'] !== null && strtotime((string)$m['codigo_expira_em']) < time();
        if ($expirou || (int)$m['tentativas_validacao'] >= MAX_TENTATIVAS_CODIGO) {
            $pdo->prepare("UPDATE marcacoes SET estado = 'expirada' WHERE id = ? AND barbearia_id = ? AND estado = 'pendente_verificacao'")
                ->execute([(int)$m['id'], (int)$m['barbearia_id']]);
            $m['estado'] = 'expirada';
        }
    }
    return $m;
}

/** Só se verifica/reenvia uma marcação pendente; os outros estados dão erro explícito. */
function exigirPendente(array $m): void {
    if ($m['estado'] === 'pendente_verificacao') return;
    if ($m['estado'] === 'confirmada') throw new ReservaErro(409, 'ja_confirmada', 'Esta marcação já está confirmada.');
    throw new ReservaErro(410, 'expirada', 'O prazo para confirmar esta marcação terminou. Faz uma nova marcação.');
}

/** Valida o código; em sucesso confirma a marcação e devolve-a atualizada. */
function verificarCodigo(PDO $pdo, array $m, string $codigo): array {
    $m = normalizarEstadoMarcacao($pdo, $m);
    exigirPendente($m);
    $id  = (int)$m['id'];
    $bid = (int)$m['barbearia_id'];

    $codigo = preg_replace('/\D/', '', $codigo);
    if (strlen($codigo) !== 6) throw new ReservaErro(422, 'codigo_invalido', 'O código tem 6 dígitos.');

    $st = $pdo->prepare("SELECT codigo_hash FROM verificacoes WHERE marcacao_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$id]);
    $hash = (string)$st->fetchColumn();

    if ($hash === '' || !password_verify($codigo, $hash)) {
        $pdo->prepare("UPDATE marcacoes SET tentativas_validacao = tentativas_validacao + 1 WHERE id = ? AND barbearia_id = ?")
            ->execute([$id, $bid]);
        $m = normalizarEstadoMarcacao($pdo, carregarMarcacaoPorId($pdo, $id) ?? $m);
        if ($m['estado'] === 'expirada') {
            throw new ReservaErro(410, 'expirada', 'Demasiadas tentativas. A marcação foi cancelada; podes fazer uma nova.');
        }
        throw new ReservaErro(422, 'codigo_errado', 'Código incorreto.', [
            'tentativas_restantes' => max(0, MAX_TENTATIVAS_CODIGO - (int)$m['tentativas_validacao']),
        ]);
    }

    $pdo->beginTransaction();
    try {
        $up = $pdo->prepare(
            "UPDATE marcacoes SET estado = 'confirmada', verificado_em = ?, codigo_expira_em = NULL
             WHERE id = ? AND barbearia_id = ? AND estado = 'pendente_verificacao'"
        );
        $up->execute([agoraSql(), $id, $bid]);
        if ($up->rowCount() !== 1) throw new ReservaErro(409, 'ja_confirmada', 'Esta marcação já está confirmada.');
        $pdo->prepare("UPDATE clientes SET email_verificado = 1, ultima_verificacao = ? WHERE id = ? AND barbearia_id = ?")
            ->execute([agoraSql(), (int)$m['cliente_id'], $bid]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    return carregarMarcacaoPorId($pdo, $id) ?? $m;
}

/**
 * Gera e envia um novo código (regras de publico/verificar.php: MAX_REENVIOS, repõe tentativas e prazo).
 * @return array{codigo_expira_em:string, codigo:string, email_enviado:bool}
 */
function reenviarCodigo(PDO $pdo, array $m): array {
    $m = normalizarEstadoMarcacao($pdo, $m);
    exigirPendente($m);
    $id  = (int)$m['id'];
    $bid = (int)$m['barbearia_id'];

    $st = $pdo->prepare("SELECT COUNT(*) FROM verificacoes WHERE marcacao_id = ?");
    $st->execute([$id]);
    if ((int)$st->fetchColumn() > MAX_REENVIOS) {
        throw new ReservaErro(429, 'limite_reenvios', 'Já pediste o número máximo de reenvios. Contacta a barbearia.');
    }
    if (!filter_var((string)$m['email'], FILTER_VALIDATE_EMAIL)) {
        throw new ReservaErro(422, 'sem_email', 'Não temos um email válido para esta marcação.');
    }

    $novo   = gerarCodigo();
    $expira = (new DateTime())->modify('+' . CODIGO_VALIDADE_MIN . ' minutes')->format('Y-m-d H:i:s');
    $st = $pdo->prepare("INSERT INTO verificacoes (marcacao_id, canal, destino, codigo_hash, sucesso) VALUES (?, 'email', ?, ?, 0)");
    $st->execute([$id, (string)$m['email'], password_hash($novo, PASSWORD_DEFAULT)]);
    $verifId = (int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE marcacoes SET codigo_expira_em = ?, tentativas_validacao = 0 WHERE id = ? AND barbearia_id = ? AND estado = 'pendente_verificacao'")
        ->execute([$expira, $id, $bid]);

    $emailBarb = (string)(configBarbearia($pdo, $bid)['email_contacto'] ?? '');
    $enviado = enviarCodigoEmail((string)$m['email'], (string)$m['barbearia_nome'], $novo, $emailBarb);
    if ($enviado) $pdo->prepare("UPDATE verificacoes SET sucesso = 1 WHERE id = ?")->execute([$verifId]);

    return ['codigo_expira_em' => $expira, 'codigo' => $novo, 'email_enviado' => $enviado];
}

/** Garante que o barbeiro é reservável nesta barbearia (mesma lista que o web mostra). */
function barbeiroDaBarbearia(PDO $pdo, int $bid, int $barbeiroId): array {
    foreach (barbeirosReservaveis($pdo, $bid) as $b) {
        if ((int)$b['id'] === $barbeiroId) return $b;
    }
    throw new ReservaErro(404, 'barbeiro_nao_encontrado', 'Barbeiro não encontrado.');
}

/* ===================== Cancelar e remarcar pelo cliente (app) ===================== */

/** Horas de antecedência para o cliente cancelar/remarcar (Definições da gestão); 24 por omissão. */
function antecedenciaAlteracaoHoras(array $cfg): int {
    $v = trim((string)($cfg['antecedencia_alteracao_horas'] ?? ''));
    return (ctype_digit($v) && (int)$v <= 168) ? (int)$v : 24;
}

/** Só uma marcação confirmada e com início daqui a pelo menos N horas pode ser alterada pelo cliente. */
function exigirAlteravel(array $m, array $cfg): void {
    if ($m['estado'] !== 'confirmada') {
        throw new ReservaErro(409, 'estado_invalido', 'Esta marcação já não pode ser alterada.');
    }
    $horas = antecedenciaAlteracaoHoras($cfg);
    if (strtotime((string)$m['inicio']) - time() < $horas * 3600) {
        throw new ReservaErro(409, 'fora_do_prazo', "Já não é possível alterar online (menos de {$horas} h). Contacta a barbearia.");
    }
}

/** Relê a marcação com FOR UPDATE (dentro de uma transação) para decidir sobre o estado atual. */
function marcacaoParaAlterar(PDO $pdo, int $id, int $bid): array {
    $st = $pdo->prepare("SELECT id, cliente_id, barbeiro_id, servico_id, inicio, estado, para_nome, notas
                         FROM marcacoes WHERE id = ? AND barbearia_id = ? FOR UPDATE");
    $st->execute([$id, $bid]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m) throw new ReservaErro(404, 'marcacao_nao_encontrada', 'Marcação não encontrada.');
    return $m;
}

function notaComRasto(?string $notas, string $linha): string {
    $notas = trim((string)$notas);
    return $notas === '' ? $linha : $notas . "\n" . $linha;
}

function cancelarMarcacaoCliente(PDO $pdo, array $m): void {
    $bid = (int)$m['barbearia_id'];
    $cfg = configBarbearia($pdo, $bid);
    $pdo->beginTransaction();
    try {
        $atual = marcacaoParaAlterar($pdo, (int)$m['id'], $bid);
        exigirAlteravel($atual, $cfg);
        $nota = notaComRasto($atual['notas'], 'Cancelada pelo cliente na app em ' . date('d/m H:i'));
        $pdo->prepare("UPDATE marcacoes SET estado = 'cancelada', notas = ? WHERE id = ? AND barbearia_id = ?")
            ->execute([$nota, (int)$m['id'], $bid]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/** Muda dia/hora/barbeiro da mesma marcação (continua confirmada). $d = [barbeiro_id, data, hora]. */
function remarcarMarcacaoCliente(PDO $pdo, array $m, array $d): void {
    $bid = (int)$m['barbearia_id'];
    $cfg = configBarbearia($pdo, $bid);
    $bb  = $d['barbeiro_id'] ?? null;
    if (!is_int($bb) && !(is_string($bb) && ctype_digit($bb))) throw new ReservaErro(422, 'dados_invalidos', 'Escolhe o barbeiro.');
    $barbeiro = barbeiroDaBarbearia($pdo, $bid, (int)$bb);
    $dia = validarDataMarcacao((string)($d['data'] ?? ''));
    if (!in_array((int)$dia->format('N'), diasFuncionamento($cfg), true)) {
        throw new ReservaErro(422, 'data_invalida', 'A barbearia está encerrada nesse dia.');
    }
    $hora = (string)($d['hora'] ?? '');
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) throw new ReservaErro(422, 'dados_invalidos', 'Escolhe um horário.');

    // Um deadlock (o MariaDB escolhe esta transação como vítima) repete-se uma vez em vez de dar erro 500
    for ($tentativa = 1; ; $tentativa++) {
        try {
            remarcarNaTransacao($pdo, $m, $bid, $cfg, $barbeiro, $dia, $hora);
            return;
        } catch (PDOException $e) {
            $deadlock = in_array((string)$e->getCode(), ['40001', '1213'], true) || str_contains($e->getMessage(), '1213');
            if (!$deadlock || $tentativa >= 2) throw $e;
        }
    }
}

/**
 * Ordem dos bloqueios igual à do criarMarcacao (primeiro os barbeiros, depois as marcações), para não haver
 * deadlock com quem está a criar: barbeiro antigo e novo por ordem de id → a marcação → horários livres.
 */
function remarcarNaTransacao(PDO $pdo, array $m, int $bid, array $cfg, array $barbeiro, DateTime $dia, string $hora): void {
    $pdo->beginTransaction();
    try {
        $barbeiros = array_unique([(int)$m['barbeiro_id'], (int)$barbeiro['id']]);
        sort($barbeiros);
        $lock = $pdo->prepare("SELECT id FROM utilizadores WHERE id = ? AND barbearia_id = ? FOR UPDATE");
        foreach ($barbeiros as $idBarbeiro) { $lock->execute([$idBarbeiro, $bid]); $lock->fetch(); }

        $atual = marcacaoParaAlterar($pdo, (int)$m['id'], $bid);
        if ((int)$atual['barbeiro_id'] !== (int)$m['barbeiro_id']) {
            // Mudou de barbeiro entretanto (outro pedido): não temos o bloqueio certo — o cliente tenta de novo
            throw new ReservaErro(409, 'slot_ocupado', 'A marcação acabou de ser alterada. Atualiza a lista e tenta de novo.');
        }
        exigirAlteravel($atual, $cfg);
        $servico = servicoDaBarbearia($pdo, $bid, (int)$atual['servico_id']);

        $livres = slotsLivres($pdo, $bid, (int)$barbeiro['id'], $dia, (int)$servico['duracao_minutos'], $cfg, true, (int)$atual['id']);
        if (!in_array($hora, $livres, true)) {
            throw new ReservaErro(409, 'slot_ocupado', 'Esse horário não está disponível. Escolhe outro, por favor.');
        }

        // Uma marcação por pessoa e por dia, sem contar a própria
        $pessoa = (string)($atual['para_nome'] ?? '');
        $diaIni = (clone $dia)->setTime(0, 0);
        $diaFim = (clone $diaIni)->modify('+1 day');
        $st = $pdo->prepare(
            "SELECT COUNT(*) FROM marcacoes
             WHERE barbearia_id = ? AND cliente_id = ? AND COALESCE(para_nome, '') = ? AND inicio >= ? AND inicio < ? AND id <> ?
               AND (estado IN ('confirmada','concluida')
                    OR (estado = 'pendente_verificacao' AND (codigo_expira_em IS NULL OR codigo_expira_em > ?)))"
        );
        $st->execute([$bid, (int)$atual['cliente_id'], $pessoa, $diaIni->format('Y-m-d H:i:s'), $diaFim->format('Y-m-d H:i:s'), (int)$atual['id'], agoraSql()]);
        if ((int)$st->fetchColumn() > 0) {
            throw new ReservaErro(409, 'marcacao_no_dia', $pessoa === ''
                ? 'Já tens uma marcação para esse dia. Escolhe outro dia ou contacta a barbearia.'
                : "Já existe uma marcação para {$pessoa} nesse dia. Escolhe outro dia ou contacta a barbearia.");
        }

        [$h, $mi] = array_map('intval', explode(':', $hora));
        $inicio = (clone $dia)->setTime($h, $mi);
        $fim    = (clone $inicio)->modify('+' . (int)$servico['duracao_minutos'] . ' minutes');
        $antes  = date('d/m H:i', strtotime((string)$atual['inicio'])) . ', ' . (string)($m['barbeiro_nome'] ?? '');
        $nota   = notaComRasto($atual['notas'], 'Remarcada pelo cliente na app em ' . date('d/m H:i') . " (antes: {$antes})");
        $pdo->prepare("UPDATE marcacoes SET barbeiro_id = ?, inicio = ?, fim = ?, notas = ? WHERE id = ? AND barbearia_id = ?")
            ->execute([(int)$barbeiro['id'], $inicio->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s'), $nota, (int)$atual['id'], $bid]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
