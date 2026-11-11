<?php
declare(strict_types=1);
// Módulo: bancadas / criar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('bancadas', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $barbeiro_id = !empty($_POST['barbeiro_id']) ? (int)$_POST['barbeiro_id'] : null;

        if ($nome === '') {
            $stmt = $pdo->prepare("SELECT MAX(id) FROM bancadas WHERE barbearia_id = ?");
            $stmt->execute([$bid]);
            $prox = ((int)$stmt->fetchColumn()) + 1;
            $nome = 'Bancada ' . $prox;
        }
        $stmt = $pdo->prepare("INSERT INTO bancadas (barbearia_id, nome, barbeiro_id) VALUES (?, ?, ?)");
        $stmt->execute([$bid, $nome, $barbeiro_id]);
        $msg = 'Bancada criada com sucesso!';

if ($erro) flash('erro', $erro);
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('bancadas', 'listar'));
