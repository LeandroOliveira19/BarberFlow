<?php
declare(strict_types=1);
// Módulo: bancadas / ativar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('bancadas', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $barbeiro_id = !empty($_POST['barbeiro_id']) ? (int)$_POST['barbeiro_id'] : null;

        $stmt = $pdo->prepare("UPDATE bancadas SET ativo = 1 WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $bid]);
        $msg = 'Bancada reativada.';

if ($erro) flash('erro', $erro);
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('bancadas', 'listar'));
