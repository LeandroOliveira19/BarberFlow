<?php
declare(strict_types=1);
// Módulo: bancadas / editar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('bancadas', 'listar'));

$id = (int)($_POST['id'] ?? 0);
$nome = trim($_POST['nome'] ?? '');
$barbeiro_id = !empty($_POST['barbeiro_id']) ? (int)$_POST['barbeiro_id'] : null;

(new Bancada($pdo, $bid))->update($id, $nome, $barbeiro_id);   // objects/Bancada.php

flash('sucesso', 'Bancada atualizada com sucesso!');
redirecionar(urlModulo('bancadas', 'listar'));
