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
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/efeitos.css">
    <link rel="stylesheet" href="../assets/css/entrada-gestao.css">
</head>
<body>
    <div class="gradiente-animado" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="caixa">
        <h1 data-stagger>Ativar conta</h1>
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
    <script src="../assets/js/efeitos.js" defer></script>
</body>
</html>
