<?php
declare(strict_types=1);
/**
 * Login da gestão (fora do index.php, como a atividade 06: é a única página que não exige sessão).
 *  - CSRF no formulário; limite de tentativas por email e por IP (config/contas.php);
 *  - session_regenerate_id no fim do login;
 *  - 2.º fator (app autenticadora, TOTP) quando a conta o tem ativo: a sessão fica em "login_2fa"
 *    sem utilizador_id, por isso nenhuma página da gestão abre antes do código certo;
 *  - cookie "Lembrar o meu email".
 */
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/modular.php';
require_once __DIR__ . '/../config/contas.php';
require_once __DIR__ . '/../config/totp.php';
ini_set('display_errors', '0');
require_once __DIR__ . '/../config/sessao.php';
iniciarSessaoSegura();
tokenCsrf();

if (isset($_SESSION['utilizador_id'])) {
    header('Location: index.php');
    exit;
}

// Passo do 2.º fator: válido 5 minutos depois da senha certa
if (isset($_GET['cancelar']) || (isset($_SESSION['login_2fa']) && $_SESSION['login_2fa']['expira'] < time())) {
    unset($_SESSION['login_2fa']);
}
$passo2fa = isset($_SESSION['login_2fa']);

$erro  = '';
$aviso = isset($_GET['ativada']) ? 'Conta ativada. Já podes entrar.' : (isset($_GET['senha']) ? 'Senha alterada. Já podes entrar.' : '');
$emailPreenchido = is_string($_COOKIE['bf_email'] ?? null) ? $_COOKIE['bf_email'] : '';
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

function carregarUtilizador(PDO $pdo, string $campo, string|int $valor): ?array {
    $st = $pdo->prepare("SELECT * FROM utilizadores WHERE $campo = ? AND ativo = 1");
    $st->execute([$valor]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValido()) {
        $erro = 'O formulário expirou. Tenta de novo.';
    } elseif ($passo2fa && isset($_POST['codigo_2fa'])) {
        $u = carregarUtilizador($pdo, 'id', (int)$_SESSION['login_2fa']['id']);
        $codigo = preg_replace('/\D/', '', (string)$_POST['codigo_2fa']);
        // Revisão final I2: os códigos errados contam no mesmo limite da conta (email/IP) — repetir a senha não reinicia nada
        if ($u && ($espera = tentativasExcedidas($pdo, (string)$u['email'], $ip))) {
            unset($_SESSION['login_2fa']);
            $passo2fa = false;
            $erro = "Demasiadas tentativas. Tenta daqui a {$espera} minuto" . ($espera > 1 ? 's' : '') . '.';
        } elseif ($u && !empty($u['totp_ativo']) && totpVerificar((string)$u['totp_segredo'], $codigo, time())) {
            limparTentativas($pdo, $u['email']);
            iniciarSessaoUtilizador($u);
            header('Location: index.php');
            exit;
        } else {
            if ($u) registarTentativaFalhada($pdo, (string)$u['email'], $ip);
            $_SESSION['login_2fa']['falhas']++;
        }
        if ($passo2fa && $_SESSION['login_2fa']['falhas'] >= 5) {
            unset($_SESSION['login_2fa']);
            $passo2fa = false;
            $erro = 'Demasiados códigos errados. Entra outra vez com a tua senha.';
        } elseif ($passo2fa) {
            $erro = 'Código errado. Confirma a hora do telemóvel e tenta de novo.';
        }
    } else {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $emailPreenchido = $email;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $erro = 'Preenche todos os campos.';
        } elseif ($espera = tentativasExcedidas($pdo, $email, $ip)) {
            $erro = "Demasiadas tentativas. Tenta daqui a {$espera} minuto" . ($espera > 1 ? 's' : '') . '.';
        } else {
            $u = carregarUtilizador($pdo, 'email', $email);
            // Revisão final I4: sem conta, faz-se o mesmo trabalho (bcrypt) para o tempo de resposta não revelar que emails existem
            if (!$u) password_verify($password, LOGIN_HASH_FICTICIO);
            if ($u && contaPorAtivar($u)) {
                $erro = 'Ativa a conta pelo link que recebeste por email (ou pede ao gestor um novo convite).';
            } elseif ($u && password_verify($password, $u['password_hash'])) {
                guardarCookieEmail(!empty($_POST['lembrar_email']) ? $email : null);
                if (!empty($u['totp_ativo'])) {
                    $_SESSION['login_2fa'] = ['id' => (int)$u['id'], 'expira' => time() + 300, 'falhas' => 0];
                    $passo2fa = true;
                } else {
                    limparTentativas($pdo, $email);
                    iniciarSessaoUtilizador($u);
                    header('Location: index.php');
                    exit;
                }
            } else {
                registarTentativaFalhada($pdo, $email, $ip);
                $erro = 'Email ou password incorretos.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — BarberFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/efeitos.css">
    <link rel="stylesheet" href="../assets/css/entrada-gestao.css">
</head>
<body>
    <div class="gradiente-animado" aria-hidden="true"><span></span><span></span><span></span></div>
    <div class="login-box">
        <h1 data-stagger>Área Restrita</h1>

        <?php if ($aviso): ?><div class="ok-msg"><?= htmlspecialchars($aviso) ?></div><?php endif; ?>
        <?php if ($erro): ?><div class="error-msg"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

        <?php if ($passo2fa): ?>
            <p class="ajuda">Abre a app autenticadora e escreve o código de 6 dígitos do BarberFlow.</p>
            <form method="POST" action="login.php">
                <?= campoCsrf() ?>
                <div class="input-group">
                    <label for="codigo_2fa">Código da app</label>
                    <input id="codigo_2fa" class="input-codigo" type="text" name="codigo_2fa" inputmode="numeric" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
                </div>
                <button type="submit" class="btn-submit">Confirmar</button>
            </form>
            <a href="login.php?cancelar=1" class="back-link">← Entrar com outra conta</a>
        <?php else: ?>
            <form method="POST" action="login.php">
                <?= campoCsrf() ?>
                <div class="input-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" required placeholder="gestor@barberflow.pt" value="<?= htmlspecialchars($emailPreenchido) ?>">
                </div>
                <div class="input-group">
                    <label for="password">Password</label>
                    <input id="password" type="password" name="password" required placeholder="••••••••">
                </div>
                <label class="lembrar">
                    <input type="checkbox" name="lembrar_email" value="1" <?= isset($_COOKIE['bf_email']) ? 'checked' : '' ?>>
                    Lembrar o meu email neste computador
                </label>
                <button type="submit" class="btn-submit">Entrar</button>
            </form>
            <a href="recuperar.php" class="back-link">Esqueci-me da senha</a>
            <a href="../index.php?slug=rlbarber" class="back-link">← Voltar ao site</a>
        <?php endif; ?>
    </div>
    <script src="../assets/js/efeitos.js" defer></script>
</body>
</html>
