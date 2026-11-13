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

/* ---------- Senhas e códigos de segurança por email ---------- */

const CODIGO_SEG_VALIDADE_MIN = 15;
const CODIGO_SEG_MAX_TENTATIVAS = 5;

/** Mínimo 8 caracteres, com pelo menos uma letra e um algarismo. */
function senhaValida(string $s): bool {
    return mb_strlen($s) >= 8 && preg_match('/\p{L}/u', $s) && preg_match('/\d/', $s);
}

const SENHA_REGRA = 'A senha tem de ter pelo menos 8 caracteres, com letras e números.';

/** Cria um código de 6 dígitos (só o hash fica na BD) e invalida os anteriores da mesma finalidade. */
function criarCodigoSeguranca(PDO $pdo, int $uid, string $finalidade): string {
    $pdo->prepare("UPDATE codigos_seguranca SET usado = 1 WHERE utilizador_id = ? AND finalidade = ? AND usado = 0")
        ->execute([$uid, $finalidade]);
    $codigo = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO codigos_seguranca (utilizador_id, finalidade, codigo_hash, expira_em)
                   VALUES (?, ?, ?, NOW() + INTERVAL " . CODIGO_SEG_VALIDADE_MIN . " MINUTE)")
        ->execute([$uid, $finalidade, password_hash($codigo, PASSWORD_DEFAULT)]);
    return $codigo;
}

/** Valida e gasta o código (uso único). Cada erro conta; ao 5.º erro o código deixa de servir. */
function validarCodigoSeguranca(PDO $pdo, int $uid, string $finalidade, string $codigo): bool {
    $st = $pdo->prepare("SELECT id, codigo_hash, tentativas FROM codigos_seguranca
                         WHERE utilizador_id = ? AND finalidade = ? AND usado = 0 AND expira_em > NOW()
                         ORDER BY id DESC LIMIT 1");
    $st->execute([$uid, $finalidade]);
    $c = $st->fetch(PDO::FETCH_ASSOC);
    if (!$c || (int)$c['tentativas'] >= CODIGO_SEG_MAX_TENTATIVAS) return false;
    if (preg_match('/^\d{6}$/', $codigo) && password_verify($codigo, $c['codigo_hash'])) {
        $pdo->prepare("UPDATE codigos_seguranca SET usado = 1 WHERE id = ?")->execute([(int)$c['id']]);
        return true;
    }
    $pdo->prepare("UPDATE codigos_seguranca SET tentativas = tentativas + 1 WHERE id = ?")->execute([(int)$c['id']]);
    return false;
}

/** Grava a senha nova (hash) e tira a obrigação de a mudar. */
function definirSenha(PDO $pdo, int $uid, string $senha): void {
    $pdo->prepare("UPDATE utilizadores SET password_hash = ?, precisa_alterar_password = 0 WHERE id = ?")
        ->execute([password_hash($senha, PASSWORD_DEFAULT), $uid]);
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
