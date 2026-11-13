<?php
declare(strict_types=1);

/**
 * Início de sessão PHP com as proteções certas, usado por TODAS as páginas que abrem sessão
 * (index.php público, gestao/index.php, login, ativar, recuperar, logout):
 *  - HttpOnly: o JavaScript da página não consegue ler o cookie (protege a sessão se houver XSS);
 *  - SameSite=Lax: o browser não envia o cookie em pedidos POST vindos de outros sites (reforça o CSRF);
 *  - Secure em HTTPS: o cookie nunca viaja em HTTP simples;
 *  - use_strict_mode: um id de sessão inventado por um atacante é recusado (contra fixação de sessão).
 */
function iniciarSessaoSegura(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off',
    ]);
    session_start();
}
