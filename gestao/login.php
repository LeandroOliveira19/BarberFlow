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
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Outfit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: #0a0a0a; color: #f8f9fa; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; background-image: linear-gradient(rgba(10, 10, 10, 0.85), rgba(10, 10, 10, 0.95)), url('https://images.unsplash.com/photo-1585747860715-2ba37e788b70?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; }
        .login-box { background: #121212; padding: 3rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08); width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .login-box h1 { font-family: 'Cinzel', serif; color: #cda47c; text-align: center; margin-bottom: 2rem; font-size: 1.8rem; }
        .login-box p.ajuda { color: #9ca3af; font-size: 0.9rem; margin-bottom: 1.5rem; text-align: center; }
        .input-group { margin-bottom: 1.5rem; }
        .input-group label { display: block; font-size: 0.9rem; color: #9ca3af; margin-bottom: 0.5rem; }
        .input-group input { width: 100%; padding: 0.8rem 1rem; background: #1a1a1a; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; color: #fff; font-family: 'Outfit', sans-serif; outline: none; transition: 0.3s; }
        .input-group input:focus { border-color: #cda47c; }
        .input-codigo { letter-spacing: 0.4em; text-align: center; font-size: 1.4rem; }
        .lembrar { display: flex; align-items: center; gap: 0.5rem; color: #9ca3af; font-size: 0.9rem; margin: -0.5rem 0 1.5rem; }
        .btn-submit { width: 100%; padding: 1rem; background: #cda47c; color: #0a0a0a; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #e0be9a; }
        .error-msg, .ok-msg { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; }
        .error-msg { background: rgba(248, 113, 113, 0.1); color: #f87171; border: 1px solid rgba(248, 113, 113, 0.2); }
        .ok-msg { background: rgba(74, 222, 128, 0.1); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.2); }
        .back-link { display: block; text-align: center; margin-top: 1.2rem; color: #9ca3af; text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .back-link:hover { color: #cda47c; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Área Restrita</h1>

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
</body>
</html>
