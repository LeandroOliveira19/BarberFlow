<?php
declare(strict_types=1);
// Módulo: barbeiros / ativar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('barbeiros', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';
    $id = (int)($_POST['id'] ?? 0);

        $pdo->prepare("UPDATE utilizadores SET ativo = 1 WHERE id = ? AND barbearia_id = ?")->execute([$id, $bid]);
        $msg = 'Barbeiro reativado.';

if ($erro) flash('erro', $erro);
if ($nova_pass_info) flash('sucesso', strip_tags($nova_pass_info));
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('barbeiros', 'listar'));
