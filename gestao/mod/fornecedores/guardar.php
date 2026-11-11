<?php
declare(strict_types=1);
// Módulo: fornecedores / guardar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('fornecedores', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';

    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $contacto = trim($_POST['contacto'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nif = trim($_POST['nif'] ?? '');

    if ($nome) {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE fornecedores SET nome=?, contacto=?, telefone=?, email=?, nif=? WHERE id=? AND barbearia_id=?");
            $stmt->execute([$nome, $contacto, $telefone, $email, $nif, $id, $bid]);
            $msg = 'Fornecedor atualizado.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO fornecedores (barbearia_id, nome, contacto, telefone, email, nif) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bid, $nome, $contacto, $telefone, $email, $nif]);
            $msg = 'Fornecedor adicionado.';
        }
    }

if ($erro) flash('erro', $erro);
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('fornecedores', 'listar'));
