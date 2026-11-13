<?php
declare(strict_types=1);
/**
 * Ativação da conta de um barbeiro convidado (página pública, como o login.php).
 * O link (gestao/ativar.php?t=<token>) chega por email e vale 48 h e uma só vez;
 * na BD está só o sha256 do token (config/contas.php).
 */
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/modular.php';
require_once __DIR__ . '/../config/contas.php';
ini_set('display_errors', '0');
require_once __DIR__ . '/../config/sessao.php';
iniciarSessaoSegura();
tokenCsrf();

$token = is_string($_GET['t'] ?? null) ? $_GET['t'] : '';
$u     = utilizadorPorConvite($pdo, $token);
$erro  = '';

if ($u && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova      = (string)($_POST['nova_password'] ?? '');
    $confirmar = (string)($_POST['confirmar_password'] ?? '');
    if (!csrfValido()) {
        $erro = 'O formulário expirou. Tenta de novo.';
    } elseif (!senhaValida($nova)) {
        $erro = SENHA_REGRA;
    } elseif ($nova !== $confirmar) {
        $erro = 'As duas senhas não coincidem.';
    } else {
        ativarConta($pdo, (int)$u['id'], $nova);
        terminarSessaoAtual();   // quem ativou tem de entrar com a SUA conta, nunca com a sessão que estava aberta
        header('Location: login.php?ativada=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativar conta — BarberFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Outfit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: #0a0a0a; color: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .caixa { background: #121212; padding: 3rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08); width: 100%; max-width: 420px; }
        h1 { font-family: 'Cinzel', serif; color: #cda47c; text-align: center; margin-bottom: 1rem; font-size: 1.6rem; }
        p.ajuda { color: #9ca3af; font-size: 0.95rem; margin-bottom: 1.5rem; text-align: center; }
        .input-group { margin-bottom: 1.2rem; }
        .input-group label { display: block; font-size: 0.9rem; color: #9ca3af; margin-bottom: 0.5rem; }
        .input-group input { width: 100%; padding: 0.8rem 1rem; background: #1a1a1a; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; color: #fff; font-family: inherit; outline: none; }
        .input-group input:focus { border-color: #cda47c; }
        .btn { width: 100%; padding: 1rem; background: #cda47c; color: #0a0a0a; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; }
        .erro { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; background: rgba(248, 113, 113, 0.1); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.2); }
        a { display: block; text-align: center; margin-top: 1.2rem; color: #9ca3af; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="caixa">
        <h1>Ativar conta</h1>
        <?php if (!$u): ?>
            <div class="erro">Este link de ativação é inválido ou já expirou.</div>
            <p class="ajuda">Pede ao gestor da barbearia que te envie um novo convite.</p>
        <?php else: ?>
            <p class="ajuda">Olá <strong><?= htmlspecialchars($u['nome']) ?></strong>, escolhe a senha para entrares na gestão da barbearia.</p>
            <?php if ($erro): ?><div class="erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
            <form method="POST" action="ativar.php?t=<?= htmlspecialchars(rawurlencode($token)) ?>">
                <?= campoCsrf() ?>
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" value="<?= htmlspecialchars($u['email']) ?>" disabled>
                </div>
                <div class="input-group">
                    <label for="nova">Senha</label>
                    <input id="nova" type="password" name="nova_password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres, com letras e números">
                </div>
                <div class="input-group">
                    <label for="confirmar">Confirmar a senha</label>
                    <input id="confirmar" type="password" name="confirmar_password" required minlength="8" autocomplete="new-password">
                </div>
                <button type="submit" class="btn">Ativar conta</button>
            </form>
        <?php endif; ?>
        <a href="login.php">← Ir para o login</a>
    </div>
</body>
</html>
