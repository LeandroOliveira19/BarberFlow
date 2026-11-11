<?php
declare(strict_types=1);
// Módulo: servicos / apagar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('servicos', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';

        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE servicos SET ativo = 0 WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $bid]);
        $msg = 'Serviço desativado com sucesso.';

if ($erro) flash('erro', $erro);
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('servicos', 'listar'));
