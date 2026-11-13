<?php
declare(strict_types=1);
// Módulo: perfil / alterar_password (POST). Exige o código enviado por email (perfil / enviar_codigo).
// O CSRF e a sessão já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('perfil', 'editar'));

require_once __DIR__ . '/../../../config/contas.php';

$uid       = (int)$_SESSION['utilizador_id'];
$forcado   = (($_SESSION['precisa_alterar_password'] ?? 0) == 1);
$codigo    = preg_replace('/\D/', '', (string)($_POST['codigo'] ?? ''));
$nova      = (string)($_POST['nova_password'] ?? '');
$confirmar = (string)($_POST['confirmar_password'] ?? '');

// A senha é validada antes do código, para uma senha fraca não gastar o código
if (!senhaValida($nova)) {
    $erro = SENHA_REGRA;
} elseif ($nova !== $confirmar) {
    $erro = 'As duas senhas não coincidem.';
} elseif (!validarCodigoSeguranca($pdo, $uid, 'alterar_password', $codigo)) {
    $erro = 'Código errado ou expirado. Pede um código novo.';
} else {
    definirSenha($pdo, $uid, $nova);
    $_SESSION['precisa_alterar_password'] = 0;
    flash('sucesso', 'Password alterada com sucesso!');
    redirecionar(urlModulo('perfil', 'editar'));
}

flash('erro', $erro);
redirecionar(urlModulo('perfil', 'editar', $forcado ? ['force' => 1] : []));
