<?php
declare(strict_types=1);
// Módulo: marcar / formulario (GET passos + POST criar; incluído pelo index.php público)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
require_once __DIR__ . '/../../config/reservas.php'; // normalizarParaNome()


/* ---------- Barbearia (pelo slug) ---------- */
$slug = (string)($_GET['slug'] ?? $_POST['slug'] ?? '');
$slug = preg_match('/^[A-Za-z0-9\-_]{1,60}$/', $slug) ? $slug : '';

$barbearia = null;
if ($slug !== '') {
    $st = $pdo->prepare("SELECT id, nome, slug, morada FROM barbearias WHERE slug = ? AND ativa = 1");
    $st->execute([$slug]);
    $barbearia = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$barbearia) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pt"><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Barbearia não encontrada</title>'
       . '<body style="font-family:system-ui;background:#0a0a0a;color:#f4f1ec;display:grid;place-items:center;min-height:100vh;text-align:center;padding:2rem">'
       . '<div><h1>Barbearia não encontrada</h1><p style="color:#a1a1a6;margin-top:.5rem">Confirma o endereço que recebeste.</p></div></body></html>';
    exit;
}
$bid = (int)$barbearia['id'];

/* ---------- Configurações, serviços e barbeiros ---------- */
$cfg      = configBarbearia($pdo, $bid);
$corPri   = corHex($cfg['tema_cor']   ?? '', '#cda47c');
$corFundo = corHex($cfg['tema_fundo'] ?? '', '#0a0a0a');
$diasAbertos = diasFuncionamento($cfg);
$horarioTxt  = rotuloDias($diasAbertos) . ' · ' . ($cfg['hora_abertura'] ?? '09:00') . ' — ' . ($cfg['hora_fecho'] ?? '19:00');
$telefone    = (string)($cfg['telemovel_contacto'] ?? '');
$emailBarb   = (string)($cfg['email_contacto'] ?? '');

$st = $pdo->prepare("SELECT id, nome, duracao_minutos, preco FROM servicos WHERE barbearia_id = ? AND ativo = 1 ORDER BY nome");
$st->execute([$bid]);
$servicos = $st->fetchAll(PDO::FETCH_ASSOC);

$barbeiros      = barbeirosReservaveis($pdo, $bid);
$servicosPorId  = array_column($servicos, null, 'id');
$barbeirosPorId = array_column($barbeiros, null, 'id');

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

/** Valida serviço, barbeiro e data. Devolve [servico, barbeiro, dia, erro]. */
function validarEscolha(array $servicosPorId, array $barbeirosPorId, array $diasAbertos, $sv, $bb, $dt): array {
    $sv = (int)$sv; $bb = (int)$bb;
    if (!isset($servicosPorId[$sv]) || !isset($barbeirosPorId[$bb])) return [null, null, null, null];
    $s = $servicosPorId[$sv]; $b = $barbeirosPorId[$bb];

    $dia = DateTime::createFromFormat('!Y-m-d', (string)$dt);
    if (!$dia || $dia->format('Y-m-d') !== (string)$dt) return [$s, $b, null, 'Escolhe uma data válida.'];
    $hoje = new DateTime('today');
    $max  = (clone $hoje)->modify('+' . MAX_DIAS . ' days');
    if ($dia < $hoje || $dia > $max) return [$s, $b, null, 'Só é possível marcar nos próximos ' . MAX_DIAS . ' dias.'];
    if (!in_array((int)$dia->format('N'), $diasAbertos, true)) return [$s, $b, null, 'A barbearia está encerrada nesse dia.'];
    return [$s, $b, $dia, null];
}

$erro = '';
$sucesso = null;

/* ---------- POST: criar marcação ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        $erro = 'A sessão expirou. Recarrega a página e tenta de novo.';
    } elseif (!empty($_POST['website'])) {
        // campo isco: só os bots o preenchem
        header('Location: ' . urlModulo('marcar', 'formulario', ['slug' => $slug]));
        exit;
    } else {
        [$sv, $bb, $dia, $erroEscolha] = validarEscolha($servicosPorId, $barbeirosPorId, $diasAbertos, $_POST['servico'] ?? 0, $_POST['barbeiro'] ?? 0, $_POST['data'] ?? '');
        $hora    = (string)($_POST['hora'] ?? '');
        $nomeCli = trim((string)($_POST['nome'] ?? ''));
        $paraNome = normalizarParaNome((string)($_POST['para_nome'] ?? ''), $nomeCli); // null = para o próprio
        $email   = trim((string)($_POST['email'] ?? ''));
        $notas   = trim((string)($_POST['notas'] ?? ''));

        // Telemóvel português: aceita espaços, +351 e 00351
        $telDigitos = preg_replace('/\D/', '', (string)($_POST['telemovel'] ?? ''));
        if (strlen($telDigitos) === 12 && str_starts_with($telDigitos, '351'))   $telDigitos = substr($telDigitos, 3);
        if (strlen($telDigitos) === 14 && str_starts_with($telDigitos, '00351')) $telDigitos = substr($telDigitos, 5);

        if (!$sv || !$bb)                                         $erro = 'Escolhe o serviço e o barbeiro.';
        elseif ($erroEscolha)                                     $erro = $erroEscolha;
        elseif (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) $erro = 'Escolhe um horário.';
        elseif (mb_strlen($nomeCli) < 2 || mb_strlen($nomeCli) > 120) $erro = 'Indica o teu nome (2 a 120 caracteres).';
        elseif ($paraNome !== null && (mb_strlen($paraNome) < 2 || mb_strlen($paraNome) > 120)) $erro = 'Indica o nome da pessoa (2 a 120 caracteres).';
        elseif (!preg_match('/^[239]\d{8}$/', $telDigitos))       $erro = 'Indica um telemóvel português válido (9 dígitos).';
        elseif (VERIFICAR_MARCACOES && !filter_var($email, FILTER_VALIDATE_EMAIL)) $erro = 'Indica um email válido: enviamos-te aí o código de confirmação.';
        elseif ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) $erro = 'O email indicado não é válido.';
        elseif (mb_strlen($notas) > 300)                          $erro = 'As notas podem ter no máximo 300 caracteres.';
        else {
            $duracao   = (int)$sv['duracao_minutos'];
            $verificar = VERIFICAR_MARCACOES;
            $codigo    = $verificar ? gerarCodigo() : null;
            $marcacaoId = 0;
            $verifId    = 0;
            try {
                $pdo->beginTransaction();

                // Serializa as marcações do mesmo barbeiro (evita duas pessoas na mesma hora)
                $lock = $pdo->prepare("SELECT id FROM utilizadores WHERE id = ? AND barbearia_id = ? FOR UPDATE");
                $lock->execute([(int)$bb['id'], $bid]);
                $lock->fetch();

                $livres = slotsLivres($pdo, $bid, (int)$bb['id'], $dia, $duracao, $cfg, true);
                if (!in_array($hora, $livres, true)) throw new RuntimeException('slot');

                // Cliente: a chave única (barbearia_id, telemovel) garante um registo por telemóvel
                $st = $pdo->prepare(
                    "INSERT INTO clientes (barbearia_id, nome, telemovel, email) VALUES (?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), email = COALESCE(email, VALUES(email))"
                );
                $st->execute([$bid, $nomeCli, $telDigitos, $email !== '' ? $email : null]);
                $clienteId = (int)$pdo->lastInsertId();
                $agora = agoraSql();

                // No máximo uma marcação ativa por cliente e por dia (mesma regra da API, config/reservas.php)
                $diaIni = (clone $dia)->setTime(0, 0);
                $diaFim = (clone $diaIni)->modify('+1 day');
                $st = $pdo->prepare(
                    "SELECT COUNT(*) FROM marcacoes
                     WHERE barbearia_id = ? AND cliente_id = ? AND COALESCE(para_nome, '') = ? AND inicio >= ? AND inicio < ?
                       AND (estado IN ('confirmada','concluida')
                            OR (estado = 'pendente_verificacao' AND (codigo_expira_em IS NULL OR codigo_expira_em > ?)))"
                );
                $st->execute([$bid, $clienteId, $paraNome ?? '', $diaIni->format('Y-m-d H:i:s'), $diaFim->format('Y-m-d H:i:s'), $agora]);
                if ((int)$st->fetchColumn() > 0) throw new RuntimeException('dia');

                // Limite anti-abuso
                $st = $pdo->prepare(
                    "SELECT COUNT(*) FROM marcacoes
                     WHERE barbearia_id = ? AND cliente_id = ? AND COALESCE(para_nome, '') = ? AND inicio > ?
                       AND (estado = 'confirmada' OR (estado = 'pendente_verificacao' AND (codigo_expira_em IS NULL OR codigo_expira_em > ?)))"
                );
                $st->execute([$bid, $clienteId, $paraNome ?? '', $agora, $agora]);
                if ((int)$st->fetchColumn() >= MAX_MARCACOES_ATIVAS_CLIENTE) throw new RuntimeException('limite');

                [$h, $mi] = array_map('intval', explode(':', $hora));
                $inicio = (clone $dia)->setTime($h, $mi);
                $fim    = (clone $inicio)->modify("+{$duracao} minutes");
                $expira = $verificar ? (new DateTime())->modify('+' . CODIGO_VALIDADE_MIN . ' minutes')->format('Y-m-d H:i:s') : null;

                $st = $pdo->prepare(
                    "INSERT INTO marcacoes (barbearia_id, cliente_id, barbeiro_id, servico_id, inicio, fim, estado, notas, para_nome, canal_verificacao, codigo_expira_em, verificado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $st->execute([
                    $bid, $clienteId, (int)$bb['id'], (int)$sv['id'],
                    $inicio->format('Y-m-d H:i:s'), $fim->format('Y-m-d H:i:s'),
                    $verificar ? 'pendente_verificacao' : 'confirmada',
                    $notas !== '' ? $notas : null,
                    $paraNome,
                    $verificar ? 'email' : null,
                    $expira,
                    $verificar ? null : $agora,
                ]);
                $marcacaoId = (int)$pdo->lastInsertId();

                if ($verificar) {
                    // Guardamos apenas o HASH do código
                    $st = $pdo->prepare("INSERT INTO verificacoes (marcacao_id, canal, destino, codigo_hash, sucesso) VALUES (?, 'email', ?, ?, 0)");
                    $st->execute([$marcacaoId, $email, password_hash($codigo, PASSWORD_DEFAULT)]);
                    $verifId = (int)$pdo->lastInsertId();
                }
                $pdo->commit();
            } catch (RuntimeException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $erro = match ($ex->getMessage()) {
                    'limite' => 'Já tens várias marcações ativas. Aguarda ou contacta a barbearia para alterar.',
                    'dia'    => $paraNome === null
                        ? 'Já tens uma marcação para este dia. Escolhe outro dia ou contacta a barbearia.'
                        : "Já existe uma marcação para {$paraNome} neste dia. Escolhe outro dia ou contacta a barbearia.",
                    default  => 'Esse horário acabou de ser ocupado. Escolhe outro, por favor.',
                };
            } catch (Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[BarberFlow] Erro ao criar marcação: ' . $ex->getMessage());
                $erro = 'Não foi possível concluir a marcação. Tenta de novo ou contacta a barbearia.';
            }

            if ($erro === '' && $marcacaoId > 0) {
                if ($verificar) {
                    // Envio fora da transação (o horário já está reservado durante CODIGO_VALIDADE_MIN)
                    $enviado = enviarCodigoEmail($email, $barbearia['nome'], (string)$codigo, $emailBarb);
                    if ($enviado) {
                        $pdo->prepare("UPDATE verificacoes SET sucesso = 1 WHERE id = ?")->execute([$verifId]);
                    }
                    session_regenerate_id(true);
                    $_SESSION['csrf'] = bin2hex(random_bytes(32));
                    $_SESSION['verificar'] = ['id' => $marcacaoId, 'slug' => $barbearia['slug'], 'email_enviado' => $enviado];
                    if (MOSTRAR_CODIGO_NO_ECRA) $_SESSION['codigo_dev'] = $codigo;
                    header('Location: ' . urlModulo('verificar', 'formulario', ['slug' => $barbearia['slug']]));
                    exit;
                }
                $_SESSION['marcacao_ok'] = [
                    'servico'  => $sv['nome'],
                    'barbeiro' => $bb['nome'],
                    'quando'   => dataExtenso($inicio) . ' às ' . $inicio->format('H:i'),
                ];
                header('Location: ' . urlModulo('marcar', 'formulario', ['slug' => $slug, 'ok' => 1]));
                exit;
            }
        }
    }
}

/* ---------- Confirmação direta (só quando VERIFICAR_MARCACOES = false) ---------- */
if (isset($_GET['ok']) && !empty($_SESSION['marcacao_ok'])) {
    $sucesso = $_SESSION['marcacao_ok'];
    unset($_SESSION['marcacao_ok']);
}

/* ---------- Estado do formulário (passo 1 via GET, ou repetição após erro) ---------- */
$svId  = (int)($_GET['servico']  ?? $_POST['servico']  ?? 0);
$bbId  = (int)($_GET['barbeiro'] ?? $_POST['barbeiro'] ?? 0);
$dtSel = (string)($_GET['data']  ?? $_POST['data']     ?? '');
if (!$bbId && count($barbeiros) === 1) $bbId = (int)$barbeiros[0]['id'];   // só há um profissional

$slots = [];
$svSel = $bbSel = $diaSel = null;
$erroPasso1 = '';
if (!$sucesso && $svId && $bbId && $dtSel !== '') {
    [$svSel, $bbSel, $diaSel, $erroPasso1] = validarEscolha($servicosPorId, $barbeirosPorId, $diasAbertos, $svId, $bbId, $dtSel);
    if ($svSel && $bbSel && $diaSel) {
        $slots = slotsLivres($pdo, $bid, (int)$bbSel['id'], $diaSel, (int)$svSel['duracao_minutos'], $cfg);
    }
}

$hoje    = new DateTime('today');
$maxData = (clone $hoje)->modify('+' . MAX_DIAS . ' days');
$linkSite = 'index.php?slug=' . rawurlencode($barbearia['slug']);
?>
<?php ob_start(); /* conteúdo do <head> desta página: o index.php coloca-o na estrutura */ ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcar — <?= e($barbearia['nome']) ?></title>
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="<?= e($corFundo) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --bg: <?= e($corFundo) ?>; --primary: <?= e($corPri) ?>; --text: #f4f1ec; --muted: #a1a1a6; --card: rgba(255,255,255,0.04); --border: rgba(255,255,255,0.1); }
        body { font-family: 'Outfit', system-ui, sans-serif; background: var(--bg); color: var(--text); line-height: 1.6; min-height: 100vh; -webkit-font-smoothing: antialiased; }
        :focus-visible { outline: 2px solid var(--primary); outline-offset: 3px; }
        .topo { padding: 1.3rem 5%; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); }
        .topo a { text-decoration: none; }
        .logo { font-family: 'Cinzel', serif; font-size: 1.3rem; font-weight: 700; color: var(--primary); letter-spacing: 0.06em; }
        .voltar { color: var(--muted); font-size: 0.9rem; }
        .voltar:hover { color: var(--primary); }
        main { max-width: 640px; margin: 0 auto; padding: 3rem 5% 5rem; }
        h1 { font-family: 'Cinzel', serif; font-size: clamp(1.7rem, 5vw, 2.3rem); margin-bottom: 0.4rem; }
        .sub { color: var(--muted); margin-bottom: 2.2rem; }
        .passo { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 1.6rem; margin-bottom: 1.4rem; }
        .passo h2 { font-size: 1.05rem; font-weight: 500; margin-bottom: 1.1rem; }
        .passo h2 span { color: var(--primary); margin-right: 0.4rem; }
        label.campo { display: block; font-size: 0.88rem; color: var(--muted); margin-bottom: 0.9rem; }
        label.campo select, label.campo input, label.campo textarea { display: block; width: 100%; margin-top: 0.35rem; background: rgba(0,0,0,0.35); color: var(--text); border: 1px solid var(--border); border-radius: 8px; padding: 0.8rem 0.9rem; font: inherit; font-size: 1rem; }
        label.campo textarea { resize: vertical; min-height: 70px; }
        label.campo select:focus, label.campo input:focus, label.campo textarea:focus { border-color: var(--primary); outline: none; }
        .btn { display: inline-block; width: 100%; background: var(--primary); color: var(--bg); border: 2px solid var(--primary); border-radius: 50px; padding: 0.95rem 2rem; font: inherit; font-weight: 600; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.06em; cursor: pointer; text-decoration: none; text-align: center; transition: 0.3s; }
        .btn:hover { background: transparent; color: var(--primary); }
        .btn-sec { background: transparent; color: var(--primary); }
        .btn-sec:hover { background: var(--primary); color: var(--bg); }
        .slots { display: grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap: 0.6rem; }
        .slot { position: relative; }
        .slot input { position: absolute; opacity: 0; pointer-events: none; }
        .slot span { display: block; text-align: center; padding: 0.7rem 0; border: 1px solid var(--border); border-radius: 8px; cursor: pointer; transition: 0.2s; }
        .slot span:hover { border-color: var(--primary); }
        .slot input:checked + span { background: var(--primary); color: var(--bg); border-color: var(--primary); font-weight: 600; }
        .slot input:focus-visible + span { outline: 2px solid var(--primary); outline-offset: 2px; }
        .aviso { border-radius: 10px; padding: 0.9rem 1.1rem; margin-bottom: 1.4rem; font-size: 0.95rem; }
        .aviso.erro { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.4); color: #fca5a5; }
        .aviso.info { background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--muted); }
        .resumo-escolha { color: var(--muted); font-size: 0.92rem; margin-bottom: 1.1rem; }
        .resumo-escolha strong { color: var(--text); font-weight: 500; }
        .ok { text-align: center; padding: 2rem 0; }
        .ok .tick { width: 64px; height: 64px; border-radius: 50%; border: 2px solid var(--primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.2rem; }
        .ok .tick svg { width: 30px; height: 30px; stroke: var(--primary); fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
        .ok dl { margin: 1.6rem 0 2rem; text-align: left; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.2rem 1.4rem; }
        .ok dt { color: var(--muted); font-size: 0.82rem; }
        .ok dd { margin-bottom: 0.8rem; }
        .ok dd:last-child { margin-bottom: 0; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
<?php $CABECALHO = (string)ob_get_clean(); ?>
    <div class="topo">
        <a href="<?= e($linkSite) ?>" class="logo"><?= e($barbearia['nome']) ?></a>
        <a href="<?= e($linkSite) ?>" class="voltar">← Voltar ao site</a>
    </div>

    <main>
    <?php if ($sucesso): ?>
        <div class="ok">
            <div class="tick"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
            <h1>Marcação confirmada</h1>
            <p class="sub">Esperamos por ti. Guarda estes dados.</p>
            <dl>
                <dt>Serviço</dt><dd><?= e($sucesso['servico']) ?></dd>
                <dt>Barbeiro</dt><dd><?= e($sucesso['barbeiro']) ?></dd>
                <dt>Quando</dt><dd><?= e($sucesso['quando']) ?></dd>
                <dt>Onde</dt><dd><?= e($barbearia['morada']) ?></dd>
            </dl>
            <a class="btn btn-sec" href="<?= e(urlModulo('marcar', 'formulario', ['slug' => $barbearia['slug']])) ?>">Fazer outra marcação</a>
        </div>

    <?php elseif (!$servicos || !$barbeiros): ?>
        <h1>Marcação indisponível</h1>
        <p class="sub">Esta barbearia ainda não tem serviços ou profissionais disponíveis para marcação online.<?= $telefone ? ' Contacta ' . e($telefone) . '.' : '' ?></p>

    <?php else: ?>
        <h1>Marcar na <?= e($barbearia['nome']) ?></h1>
        <p class="sub">Escolhe o serviço, o profissional e o dia. Mostramos só os horários livres.<br><?= e($horarioTxt) ?></p>

        <?php if ($erro): ?><div class="aviso erro" role="alert"><?= e($erro) ?></div><?php endif; ?>

        <!-- Passo 1 -->
        <form method="get" action="index.php" class="passo">
            <input type="hidden" name="m" value="marcar">
            <input type="hidden" name="slug" value="<?= e($barbearia['slug']) ?>">
            <h2><span>1.</span>O que queres fazer?</h2>
            <label class="campo">Serviço
                <select name="servico" required>
                    <option value="">Escolher…</option>
                    <?php foreach ($servicos as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $svId === (int)$s['id'] ? 'selected' : '' ?>>
                            <?= e($s['nome']) ?> — <?= (int)$s['duracao_minutos'] ?> min — <?= number_format((float)$s['preco'], 2, ',', '') ?> €
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="campo">Profissional
                <select name="barbeiro" required>
                    <?php if (count($barbeiros) > 1): ?><option value="">Escolher…</option><?php endif; ?>
                    <?php foreach ($barbeiros as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= $bbId === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="campo">Dia
                <input type="date" name="data" required min="<?= $hoje->format('Y-m-d') ?>" max="<?= $maxData->format('Y-m-d') ?>" value="<?= e($dtSel) ?>">
            </label>
            <button class="btn btn-sec" type="submit">Ver horários livres</button>
        </form>

        <?php if ($erroPasso1): ?><div class="aviso erro" role="alert"><?= e($erroPasso1) ?></div><?php endif; ?>

        <!-- Passo 2 e 3 -->
        <?php if ($svSel && $bbSel && $diaSel): ?>
            <?php if (!$slots): ?>
                <div class="aviso info">Não há horários livres com <?= e($bbSel['nome']) ?> em <?= e(dataExtenso($diaSel)) ?>. Experimenta outro dia<?= count($barbeiros) > 1 ? ' ou outro profissional' : '' ?>.</div>
            <?php else: ?>
            <form method="post" action="<?= e(urlModulo('marcar', 'formulario', ['slug' => $barbearia['slug']])) ?>" class="passo" novalidate>
                <input type="hidden" name="slug" value="<?= e($barbearia['slug']) ?>">
                <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                <input type="hidden" name="servico" value="<?= (int)$svSel['id'] ?>">
                <input type="hidden" name="barbeiro" value="<?= (int)$bbSel['id'] ?>">
                <input type="hidden" name="data" value="<?= e($diaSel->format('Y-m-d')) ?>">
                <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                <h2><span>2.</span>Escolhe a hora</h2>
                <p class="resumo-escolha">
                    <strong><?= e($svSel['nome']) ?></strong> com <strong><?= e($bbSel['nome']) ?></strong><br>
                    <?= e(dataExtenso($diaSel)) ?>
                </p>
                <div class="slots" role="radiogroup" aria-label="Horários disponíveis">
                    <?php foreach ($slots as $h): ?>
                        <label class="slot"><input type="radio" name="hora" value="<?= e($h) ?>" required <?= (($_POST['hora'] ?? '') === $h) ? 'checked' : '' ?>><span><?= e($h) ?></span></label>
                    <?php endforeach; ?>
                </div>

                <h2 style="margin-top:1.8rem"><span>3.</span>Os teus dados</h2>
                <label class="campo">Nome
                    <input type="text" name="nome" required maxlength="120" autocomplete="name" value="<?= e($_POST['nome'] ?? '') ?>">
                </label>
                <label class="campo">Para quem é a marcação? (opcional, se não for para ti)
                    <input type="text" name="para_nome" maxlength="120" placeholder="Ex.: Tiago (filho)" value="<?= e($_POST['para_nome'] ?? '') ?>">
                </label>
                <label class="campo">Telemóvel
                    <input type="tel" name="telemovel" required inputmode="tel" autocomplete="tel" placeholder="912 345 678" value="<?= e($_POST['telemovel'] ?? '') ?>">
                </label>
                <label class="campo">Email<?= VERIFICAR_MARCACOES ? ' (enviamos-te um código para confirmar)' : ' (opcional)' ?>
                    <input type="email" name="email" <?= VERIFICAR_MARCACOES ? 'required' : '' ?> maxlength="150" autocomplete="email" value="<?= e($_POST['email'] ?? '') ?>">
                </label>
                <label class="campo">Notas (opcional)
                    <textarea name="notas" maxlength="300" placeholder="Ex.: prefiro degradê baixo"><?= e($_POST['notas'] ?? '') ?></textarea>
                </label>
                <p class="resumo-escolha" style="font-size:.82rem">Usamos estes dados apenas para gerir a tua marcação.</p>
                <button class="btn" type="submit"><?= VERIFICAR_MARCACOES ? 'Continuar' : 'Confirmar marcação' ?></button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    </main>
