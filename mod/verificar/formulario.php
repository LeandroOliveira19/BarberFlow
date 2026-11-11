<?php
declare(strict_types=1);
// Módulo: verificar / formulario (GET + POST verificar/reenviar; incluído pelo index.php público)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

/* O contexto vem da sessão (criado pelo módulo marcar); o ID da marcação nunca anda no URL. */
$ctx = $_SESSION['verificar'] ?? null;
if (!is_array($ctx) || empty($ctx['id']) || empty($ctx['slug'])) {
    header('Location: ' . (isset($_GET['slug']) && preg_match('/^[A-Za-z0-9\-_]{1,60}$/', (string)$_GET['slug']) ? 'index.php?slug=' . rawurlencode((string)$_GET['slug']) : 'index.php'));
    exit;
}
$marcacaoId = (int)$ctx['id'];

$st = $pdo->prepare("SELECT id, nome, slug, morada FROM barbearias WHERE slug = ? AND ativa = 1");
$st->execute([(string)$ctx['slug']]);
$barbearia = $st->fetch(PDO::FETCH_ASSOC);
if (!$barbearia) { unset($_SESSION['verificar']); header('Location: index.php'); exit; }
$bid = (int)$barbearia['id'];

$cfg      = configBarbearia($pdo, $bid);
$corPri   = corHex($cfg['tema_cor']   ?? '', '#cda47c');
$corFundo = corHex($cfg['tema_fundo'] ?? '', '#0a0a0a');
$emailBarb = (string)($cfg['email_contacto'] ?? '');
$telefone  = (string)($cfg['telemovel_contacto'] ?? '');
$linkSite  = 'index.php?slug=' . rawurlencode($barbearia['slug']);

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

function carregarMarcacao(PDO $pdo, int $id, int $bid): ?array {
    $st = $pdo->prepare(
        "SELECT m.id, m.estado, m.inicio, m.codigo_expira_em, m.tentativas_validacao, m.cliente_id,
                c.email, s.nome AS servico_nome, u.nome AS barbeiro_nome
         FROM marcacoes m
         JOIN clientes     c ON c.id = m.cliente_id  AND c.barbearia_id = m.barbearia_id
         JOIN servicos     s ON s.id = m.servico_id  AND s.barbearia_id = m.barbearia_id
         JOIN utilizadores u ON u.id = m.barbeiro_id AND u.barbearia_id = m.barbearia_id
         WHERE m.id = ? AND m.barbearia_id = ?"
    );
    $st->execute([$id, $bid]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

$m = carregarMarcacao($pdo, $marcacaoId, $bid);
if (!$m) { unset($_SESSION['verificar']); header('Location: ' . $linkSite); exit; }

$erro = '';
$info = '';

/** Estado efetivo: aplica expiração e limite de tentativas */
function normalizarEstado(PDO $pdo, array $m, int $bid): array {
    if ($m['estado'] === 'pendente_verificacao') {
        $expirou = $m['codigo_expira_em'] !== null && strtotime((string)$m['codigo_expira_em']) < time();
        if ($expirou || (int)$m['tentativas_validacao'] >= MAX_TENTATIVAS_CODIGO) {
            $pdo->prepare("UPDATE marcacoes SET estado = 'expirada' WHERE id = ? AND barbearia_id = ? AND estado = 'pendente_verificacao'")
                ->execute([(int)$m['id'], $bid]);
            $m['estado'] = 'expirada';
        }
    }
    return $m;
}
$m = normalizarEstado($pdo, $m, $bid);

/* ---------- POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $m['estado'] === 'pendente_verificacao') {
    if (!$csrfValido) {   // validado no index.php
        $erro = 'A sessão expirou. Recarrega a página.';
    } else {
        $acao = (string)($_POST['acao'] ?? '');

        if ($acao === 'verificar') {
            $codigo = preg_replace('/\D/', '', (string)($_POST['codigo'] ?? ''));
            if (strlen($codigo) !== 6) {
                $erro = 'O código tem 6 dígitos.';
            } else {
                $st = $pdo->prepare("SELECT codigo_hash FROM verificacoes WHERE marcacao_id = ? ORDER BY id DESC LIMIT 1");
                $st->execute([$marcacaoId]);
                $hash = (string)$st->fetchColumn();

                if ($hash !== '' && password_verify($codigo, $hash)) {
                    $pdo->beginTransaction();
                    try {
                        $up = $pdo->prepare(
                            "UPDATE marcacoes SET estado = 'confirmada', verificado_em = ?, codigo_expira_em = NULL
                             WHERE id = ? AND barbearia_id = ? AND estado = 'pendente_verificacao'"
                        );
                        $up->execute([agoraSql(), $marcacaoId, $bid]);
                        if ($up->rowCount() !== 1) throw new RuntimeException('estado');
                        $pdo->prepare("UPDATE clientes SET email_verificado = 1, ultima_verificacao = ? WHERE id = ? AND barbearia_id = ?")
                            ->execute([agoraSql(), (int)$m['cliente_id'], $bid]);
                        $pdo->commit();
                        unset($_SESSION['codigo_dev']);
                    } catch (Throwable $ex) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        error_log('[BarberFlow] Erro ao confirmar marcação: ' . $ex->getMessage());
                        $erro = 'Não foi possível confirmar. Tenta de novo.';
                    }
                } else {
                    $pdo->prepare("UPDATE marcacoes SET tentativas_validacao = tentativas_validacao + 1 WHERE id = ? AND barbearia_id = ?")
                        ->execute([$marcacaoId, $bid]);
                    $erro = 'Código incorreto.';
                }
            }
        } elseif ($acao === 'reenviar') {
            $st = $pdo->prepare("SELECT COUNT(*) FROM verificacoes WHERE marcacao_id = ?");
            $st->execute([$marcacaoId]);
            if ((int)$st->fetchColumn() > MAX_REENVIOS) {
                $erro = 'Já pediste o número máximo de reenvios. Contacta a barbearia.';
            } elseif (!filter_var((string)$m['email'], FILTER_VALIDATE_EMAIL)) {
                $erro = 'Não temos um email válido para esta marcação.';
            } else {
                $novo   = gerarCodigo();
                $expira = (new DateTime())->modify('+' . CODIGO_VALIDADE_MIN . ' minutes')->format('Y-m-d H:i:s');
                $st = $pdo->prepare("INSERT INTO verificacoes (marcacao_id, canal, destino, codigo_hash, sucesso) VALUES (?, 'email', ?, ?, 0)");
                $st->execute([$marcacaoId, (string)$m['email'], password_hash($novo, PASSWORD_DEFAULT)]);
                $verifId = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE marcacoes SET codigo_expira_em = ?, tentativas_validacao = 0 WHERE id = ? AND barbearia_id = ? AND estado = 'pendente_verificacao'")
                    ->execute([$expira, $marcacaoId, $bid]);
                $enviado = enviarCodigoEmail((string)$m['email'], $barbearia['nome'], $novo, $emailBarb);
                if ($enviado) $pdo->prepare("UPDATE verificacoes SET sucesso = 1 WHERE id = ?")->execute([$verifId]);
                if (MOSTRAR_CODIGO_NO_ECRA) $_SESSION['codigo_dev'] = $novo;
                $_SESSION['verificar']['email_enviado'] = $enviado;
                $info = $enviado ? 'Enviámos um novo código para o teu email.' : 'Não conseguimos enviar o email. Contacta a barbearia para confirmar a marcação.';
            }
        }
        $m = carregarMarcacao($pdo, $marcacaoId, $bid) ?? $m;
        $m = normalizarEstado($pdo, $m, $bid);
        if ($m['estado'] === 'expirada' && $erro === 'Código incorreto.') {
            $erro = 'Demasiadas tentativas. A marcação foi cancelada; podes fazer uma nova.';
        }
    }
}

$confirmada = ($m['estado'] === 'confirmada');
$expirada   = in_array($m['estado'], ['expirada', 'cancelada'], true);
if ($confirmada) { unset($_SESSION['verificar'], $_SESSION['codigo_dev']); }

$inicio = new DateTime((string)$m['inicio']);
$quando = dataExtenso($inicio) . ' às ' . $inicio->format('H:i');
$emailMascarado = '';
if (filter_var((string)$m['email'], FILTER_VALIDATE_EMAIL)) {
    [$u, $d] = explode('@', (string)$m['email'], 2);
    $emailMascarado = mb_substr($u, 0, 2) . '***@' . $d;
}
$emailEnviado = $_SESSION['verificar']['email_enviado'] ?? true;
$restoMin = $m['codigo_expira_em'] ? max(0, (int)ceil((strtotime((string)$m['codigo_expira_em']) - time()) / 60)) : 0;
?>
<?php ob_start(); /* conteúdo do <head> desta página: o index.php coloca-o na estrutura */ ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar marcação — <?= e($barbearia['nome']) ?></title>
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
        main { max-width: 560px; margin: 0 auto; padding: 3rem 5% 5rem; }
        h1 { font-family: 'Cinzel', serif; font-size: clamp(1.6rem, 5vw, 2.1rem); margin-bottom: 0.5rem; }
        .sub { color: var(--muted); margin-bottom: 1.8rem; }
        .caixa { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 1.5rem; margin-bottom: 1.2rem; }
        dl { margin: 0 0 1.6rem; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.2rem 1.4rem; }
        dt { color: var(--muted); font-size: 0.82rem; }
        dd { margin-bottom: 0.8rem; }
        dd:last-child { margin-bottom: 0; }
        input.codigo { width: 100%; text-align: center; letter-spacing: 0.5em; font-size: 1.8rem; padding: 0.8rem; background: rgba(0,0,0,0.35); color: var(--text); border: 1px solid var(--border); border-radius: 10px; font-family: inherit; }
        input.codigo:focus { border-color: var(--primary); outline: none; }
        .btn { display: block; width: 100%; background: var(--primary); color: var(--bg); border: 2px solid var(--primary); border-radius: 50px; padding: 0.95rem 2rem; font: inherit; font-weight: 600; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.06em; cursor: pointer; text-decoration: none; text-align: center; transition: 0.3s; margin-top: 1rem; }
        .btn:hover { background: transparent; color: var(--primary); }
        .btn-sec { background: transparent; color: var(--primary); }
        .btn-sec:hover { background: var(--primary); color: var(--bg); }
        .link { background: none; border: 0; color: var(--muted); text-decoration: underline; cursor: pointer; font: inherit; font-size: 0.9rem; margin-top: 1rem; }
        .link:hover { color: var(--primary); }
        .aviso { border-radius: 10px; padding: 0.9rem 1.1rem; margin-bottom: 1.2rem; font-size: 0.95rem; }
        .aviso.erro { background: rgba(248,113,113,0.1); border: 1px solid rgba(248,113,113,0.4); color: #fca5a5; }
        .aviso.info { background: rgba(255,255,255,0.04); border: 1px solid var(--border); color: var(--muted); }
        .aviso.dev { background: rgba(251,191,36,0.1); border: 1px dashed rgba(251,191,36,0.5); color: #fcd34d; }
        .centro { text-align: center; }
        .tick { width: 64px; height: 64px; border-radius: 50%; border: 2px solid var(--primary); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.2rem; }
        .tick svg { width: 30px; height: 30px; stroke: var(--primary); fill: none; stroke-width: 3; stroke-linecap: round; stroke-linejoin: round; }
    </style>
<?php $CABECALHO = (string)ob_get_clean(); ?>
    <div class="topo">
        <a href="<?= e($linkSite) ?>" class="logo"><?= e($barbearia['nome']) ?></a>
        <a href="<?= e($linkSite) ?>" class="voltar">← Voltar ao site</a>
    </div>

    <main>
    <?php if ($confirmada): ?>
        <div class="centro">
            <div class="tick"><svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div>
            <h1>Marcação confirmada</h1>
            <p class="sub">Esperamos por ti. Guarda estes dados.</p>
        </div>
        <dl style="text-align:left">
            <dt>Serviço</dt><dd><?= e($m['servico_nome']) ?></dd>
            <dt>Profissional</dt><dd><?= e($m['barbeiro_nome']) ?></dd>
            <dt>Quando</dt><dd><?= e($quando) ?></dd>
            <dt>Onde</dt><dd><?= e($barbearia['morada']) ?></dd>
        </dl>
        <?php if ($telefone): ?><p class="sub centro" style="margin-bottom:1.2rem">Precisas de alterar? Contacta <strong><?= e($telefone) ?></strong>.</p><?php endif; ?>
        <a class="btn btn-sec" href="<?= e($linkSite) ?>">Voltar ao site</a>

    <?php elseif ($expirada): ?>
        <h1>Esta marcação já não está ativa</h1>
        <p class="sub">O prazo para confirmar terminou ou o limite de tentativas foi atingido. O horário voltou a ficar livre.</p>
        <?php if ($erro): ?><div class="aviso erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
        <a class="btn" href="<?= e(urlModulo('marcar', 'formulario', ['slug' => $barbearia['slug']])) ?>">Fazer nova marcação</a>

    <?php else: ?>
        <h1>Confirma a tua marcação</h1>
        <p class="sub">
            <?php if ($emailEnviado && $emailMascarado): ?>
                Enviámos um código de 6 dígitos para <strong><?= e($emailMascarado) ?></strong>.
            <?php else: ?>
                Não foi possível enviar o email com o código. Tenta reenviar ou contacta a barbearia<?= $telefone ? ' (' . e($telefone) . ')' : '' ?>.
            <?php endif; ?>
            O horário fica reservado durante mais <?= (int)$restoMin ?> min.
        </p>

        <dl>
            <dt>Serviço</dt><dd><?= e($m['servico_nome']) ?> com <?= e($m['barbeiro_nome']) ?></dd>
            <dt>Quando</dt><dd><?= e($quando) ?></dd>
        </dl>

        <?php if (MOSTRAR_CODIGO_NO_ECRA && !empty($_SESSION['codigo_dev'])): ?>
            <div class="aviso dev">Modo de demonstração: o código é <strong><?= e($_SESSION['codigo_dev']) ?></strong>. (Desativar MOSTRAR_CODIGO_NO_ECRA em produção.)</div>
        <?php endif; ?>
        <?php if ($erro): ?><div class="aviso erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
        <?php if ($info): ?><div class="aviso info" role="status"><?= e($info) ?></div><?php endif; ?>

        <form method="post" action="<?= e(urlModulo('verificar', 'formulario', ['slug' => $barbearia['slug']])) ?>" class="caixa">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="acao" value="verificar">
            <label for="codigo" style="display:block;color:var(--muted);font-size:.88rem;margin-bottom:.5rem">Código de confirmação</label>
            <input class="codigo" id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus>
            <button class="btn" type="submit">Confirmar marcação</button>
        </form>

        <form method="post" action="<?= e(urlModulo('verificar', 'formulario', ['slug' => $barbearia['slug']])) ?>" class="centro">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="acao" value="reenviar">
            <button class="link" type="submit">Não recebi o código — reenviar</button>
        </form>
    <?php endif; ?>
    </main>
