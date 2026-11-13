<?php
declare(strict_types=1);
// Módulo: bancadas / criar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('bancadas', 'listar'));

$nome = trim($_POST['nome'] ?? '');
$barbeiro_id = !empty($_POST['barbeiro_id']) ? (int)$_POST['barbeiro_id'] : null;

try {
    (new Bancada($pdo, $bid))->create($nome, $barbeiro_id);   // objects/Bancada.php (valida o barbeiro)
    flash('sucesso', 'Bancada criada com sucesso!');
} catch (InvalidArgumentException $e) {
    flash('erro', 'Escolhe um barbeiro desta barbearia.');
}
redirecionar(urlModulo('bancadas', 'listar'));
