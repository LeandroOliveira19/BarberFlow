<?php
declare(strict_types=1);
// Módulo: barbeiros / apagar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('barbeiros', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';
    $id = (int)($_POST['id'] ?? 0);

        $pdo->beginTransaction();
        try {
            // Desativar utilizador
            $pdo->prepare("UPDATE utilizadores SET ativo = 0 WHERE id = ? AND barbearia_id = ?")->execute([$id, $bid]);
            // Libertar bancada
            $pdo->prepare("UPDATE bancadas SET barbeiro_id = NULL WHERE barbeiro_id = ? AND barbearia_id = ?")->execute([$id, $bid]);
            $pdo->commit();
            $msg = 'Barbeiro desativado e bancada libertada.';
        } catch (Exception $e) {
            $pdo->rollBack();
        }

if ($erro) flash('erro', $erro);
if ($nova_pass_info) flash('sucesso', strip_tags($nova_pass_info));
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('barbeiros', 'listar'));
