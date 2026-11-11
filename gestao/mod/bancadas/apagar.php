<?php
declare(strict_types=1);
// Módulo: bancadas / apagar — (POST) desativa a bancada. O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('bancadas', 'listar'));

(new Bancada($pdo, $bid))->setAtiva((int)($_POST['id'] ?? 0), false);   // objects/Bancada.php

flash('sucesso', 'Bancada desativada.');
redirecionar(urlModulo('bancadas', 'listar'));
