<?php
declare(strict_types=1);

/**
 * Regras das contas da gestão (gestor/barbeiro): limite de tentativas de login, início de sessão,
 * códigos de segurança por email (mudar/recuperar senha) e convites de barbeiros.
 * Usado por gestao/login.php, gestao/recuperar.php, gestao/ativar.php e pelos módulos perfil/barbeiros.
 */

const LOGIN_MAX_POR_EMAIL = 5;    // falhas por email em LOGIN_JANELA_MIN minutos
const LOGIN_MAX_POR_IP    = 20;   // falhas por IP na mesma janela
const LOGIN_JANELA_MIN    = 15;

/** Minutos que faltam se o email ou o IP excederam o limite de falhas; null se pode tentar. */
function tentativasExcedidas(PDO $pdo, string $email, string $ip): ?int {
    $janela = "criado_em > (NOW() - INTERVAL " . LOGIN_JANELA_MIN . " MINUTE)";
    foreach ([['email', $email, LOGIN_MAX_POR_EMAIL], ['ip', $ip, LOGIN_MAX_POR_IP]] as [$campo, $valor, $max]) {
        $st = $pdo->prepare("SELECT COUNT(*), MIN(criado_em) FROM tentativas_login WHERE $campo = ? AND $janela");
        $st->execute([$valor]);
        [$n, $primeira] = $st->fetch(PDO::FETCH_NUM);
        if ((int)$n >= $max) {
            $st = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, NOW(), ? + INTERVAL " . LOGIN_JANELA_MIN . " MINUTE)");
            $st->execute([$primeira]);
            return max(1, (int)ceil(((int)$st->fetchColumn()) / 60));
        }
    }
    return null;
}

function registarTentativaFalhada(PDO $pdo, string $email, string $ip): void {
    $pdo->prepare("INSERT INTO tentativas_login (email, ip) VALUES (?, ?)")->execute([mb_substr($email, 0, 150), mb_substr($ip, 0, 45)]);
    $pdo->exec("DELETE FROM tentativas_login WHERE criado_em < (NOW() - INTERVAL 1 DAY)");
}

function limparTentativas(PDO $pdo, string $email): void {
    $pdo->prepare("DELETE FROM tentativas_login WHERE email = ?")->execute([$email]);
}

/** Conta criada por convite e ainda não ativada (o barbeiro ainda não escolheu a senha). */
function contaPorAtivar(array $u): bool {
    return !empty($u['convite_token_hash']);
}

/** Login concluído: novo id de sessão (contra fixação de sessão) e os dados que a gestão usa. */
function iniciarSessaoUtilizador(array $u): void {
    session_regenerate_id(true);
    unset($_SESSION['login_2fa']);
    $_SESSION['utilizador_id']   = (int)$u['id'];
    $_SESSION['utilizador_nome'] = $u['nome'];
    $_SESSION['barbearia_id']    = (int)$u['barbearia_id'];
    $_SESSION['tipo']            = $u['tipo'];
    $_SESSION['precisa_alterar_password'] = (int)($u['precisa_alterar_password'] ?? 0);
}

/** Cookie "Lembrar o meu email": só o email, nunca a senha. */
function guardarCookieEmail(?string $email): void {
    $opcoes = ['path' => '/', 'httponly' => true, 'samesite' => 'Lax',
               'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')];
    if ($email === null) {
        if (isset($_COOKIE['bf_email'])) setcookie('bf_email', '', ['expires' => time() - 3600] + $opcoes);
        return;
    }
    setcookie('bf_email', $email, ['expires' => time() + 30 * 86400] + $opcoes);
}
