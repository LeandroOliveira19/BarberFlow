<?php
declare(strict_types=1);
// Módulo: perfil / alterar_password (POST). O CSRF já foi validado pelo index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('perfil', 'editar'));

$uid  = (int)$_SESSION['utilizador_id'];
$msg  = '';
$erro = '';
$forcar_alteracao = (($_SESSION['precisa_alterar_password'] ?? 0) == 1);

        $nova = $_POST['nova_password'] ?? '';
        $confirmar = $_POST['confirmar_password'] ?? '';
        if (strlen($nova) >= 6 && $nova === $confirmar) {
            $hash = password_hash($nova, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE utilizadores SET password_hash = ?, precisa_alterar_password = 0 WHERE id = ?")->execute([$hash, $uid]);
            $_SESSION['precisa_alterar_password'] = 0;
            $forcar_alteracao = false;
            $msg = 'Password alterada com sucesso!';
        } else {
            $erro = 'As passwords não coincidem ou têm menos de 6 caracteres.';
        }

if ($erro) {
    flash('erro', $erro);
    redirecionar(urlModulo('perfil', 'editar', $forcar_alteracao ? ['force' => 1] : []));
}
flash('sucesso', $msg);
redirecionar(urlModulo('perfil', 'editar'));
