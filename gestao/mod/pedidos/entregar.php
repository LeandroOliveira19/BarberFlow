<?php
declare(strict_types=1);
// Módulo: pedidos / entregar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('pedidos', 'listar'));

require_once __DIR__ . '/_comum.php';
$msg = '';
$erro = '';
$acao = 'entregar';
require __DIR__ . '/_processar.php';

if ($erro) flash('erro', $erro);
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('pedidos', 'listar'));
