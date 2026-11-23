<?php
declare(strict_types=1);
/**
 * "Esqueci-me da senha" (página pública, como o login.php).
 *  Passo 1: email → se a conta existir, envia um código. A resposta é SEMPRE a mesma (não revela que emails existem).
 *  Passo 2: email + código + senha nova.
 */
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/modular.php';
require_once __DIR__ . '/../config/contas.php';
require_once __DIR__ . '/../config/mailer_contas.php';
require_once __DIR__ . '/../config/politica_codigo.php';
ini_set('display_errors', '0');
require_once __DIR__ . '/../config/sessao.php';
iniciarSessaoSegura();
tokenCsrf();

$passo = ($_GET['passo'] ?? '') === '2' ? 2 : 1;
$erro  = '';

function contaParaRecuperar(PDO $pdo, string $email): ?array {
    $st = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ? AND ativo = 1");
    $st->execute([$email]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    return ($u && !contaPorAtivar($u)) ? $u : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        $erro = 'O formulário expirou. Tenta de novo.';
    } elseif ($passo === 1) {
        $email = trim((string)($_POST['email'] ?? ''));
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Indica um email válido.';
        } elseif ($espera = tentativasExcedidas($pdo, 'recuperar:' . $email, $ip)) {
            // Limite igual para emails que existem e que não existem (não revela contas; não enche a caixa da vítima)
            $erro = "Demasiados pedidos. Tenta daqui a {$espera} minuto" . ($espera > 1 ? 's' : '') . '.';
        } else {
            registarTentativaFalhada($pdo, 'recuperar:' . $email, $ip);   // cada pedido conta para o limite
            unset($_SESSION['recuperar_codigo_ecra']);
            $u = contaParaRecuperar($pdo, $email);
            if ($u) {
                $codigo  = criarCodigoSeguranca($pdo, (int)$u['id'], 'recuperar_password');
                $enviado = enviarCodigoSegurancaEmail($email, (string)$u['nome'], $codigo, 'recuperar_password');
                // Só contas de teste (example.com…): numa conta real, mostrar o código entregava-a a quem soubesse o email
                if (emailDeTeste($email) && mostrarCodigoNoEcra($enviado)) $_SESSION['recuperar_codigo_ecra'] = $codigo;
            } else {
                password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);   // trabalho equivalente ao criar o código
            }
            $_SESSION['recuperar_email'] = $email;
            header('Location: recuperar.php?passo=2');   // igual com ou sem conta
            exit;
        }
    } else {
        $email     = trim((string)($_POST['email'] ?? ''));
        $codigo    = preg_replace('/\D/', '', (string)($_POST['codigo'] ?? ''));
        $nova      = (string)($_POST['nova_password'] ?? '');
        $confirmar = (string)($_POST['confirmar_password'] ?? '');
        $u = filter_var($email, FILTER_VALIDATE_EMAIL) ? contaParaRecuperar($pdo, $email) : null;
        if (!senhaValida($nova)) {
            $erro = SENHA_REGRA;
        } elseif ($nova !== $confirmar) {
            $erro = 'As duas senhas não coincidem.';
        } elseif (!$u || !validarCodigoSeguranca($pdo, (int)$u['id'], 'recuperar_password', $codigo)) {
            $erro = 'Código errado ou expirado.';
        } else {
            definirSenha($pdo, (int)$u['id'], $nova);
            limparTentativas($pdo, $email);
            terminarSessaoAtual();   // a sessão que estivesse aberta neste browser não pode continuar
            header('Location: login.php?senha=1');
            exit;
        }
    }
}
$emailPasso2 = (string)($_SESSION['recuperar_email'] ?? '');
$codigoEcra  = (string)($_SESSION['recuperar_codigo_ecra'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar senha — BarberFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/efeitos.css">
    <link rel="stylesheet" href="../assets/css/entrada-gestao.css">
</head>
<body>
    <div class="gradiente-animado" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="caixa">
        <h1 data-stagger>Recuperar senha</h1>
        <?php if ($erro): ?><div class="erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

        <?php if ($passo === 1): ?>
            <p class="ajuda">Escreve o email da tua conta. Se existir, enviamos-te um código para escolheres uma senha nova.</p>
            <form method="POST" action="recuperar.php">
                <?= campoCsrf() ?>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" required autofocus>
                </div>
                <button type="submit" class="btn">Enviar código</button>
            </form>
        <?php else: ?>
            <div class="info">Se o email existir, enviámos um código (válido 15 minutos).
                <?php if ($codigoEcra !== ''): ?><br><strong>Código: <?= htmlspecialchars($codigoEcra) ?></strong> (o email não pôde ser enviado neste servidor)<?php endif; ?>
            </div>
            <form method="POST" action="recuperar.php?passo=2">
                <?= campoCsrf() ?>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" required value="<?= htmlspecialchars($emailPasso2) ?>">
                </div>
                <div class="input-group">
                    <label for="codigo">Código</label>
                    <input id="codigo" type="text" name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code">
                </div>
                <div class="input-group">
                    <label for="nova">Nova senha</label>
                    <input id="nova" type="password" name="nova_password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres, com letras e números">
                </div>
                <div class="input-group">
                    <label for="confirmar">Confirmar a nova senha</label>
                    <input id="confirmar" type="password" name="confirmar_password" required minlength="8" autocomplete="new-password">
                </div>
                <button type="submit" class="btn">Guardar senha nova</button>
            </form>
            <a href="recuperar.php">Pedir outro código</a>
        <?php endif; ?>
        <a href="login.php">← Voltar ao login</a>
    </div>
    <script src="../assets/js/efeitos.js" defer></script>
</body>
</html>
