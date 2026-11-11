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
            (new Fornecedor($pdo, $bid))->update($id, compact('nome', 'contacto', 'telefone', 'email', 'nif'));
            $msg = 'Fornecedor atualizado.';
        } else {
            (new Fornecedor($pdo, $bid))->create(compact('nome', 'contacto', 'telefone', 'email', 'nif'));
            $msg = 'Fornecedor adicionado.';
        }
    }

if ($erro) flash('erro', $erro);
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('fornecedores', 'listar'));
