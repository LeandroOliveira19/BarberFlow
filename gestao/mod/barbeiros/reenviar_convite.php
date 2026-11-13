<?php
declare(strict_types=1);
// Módulo: barbeiros / reenviar_convite (POST, só gestor): link novo; o anterior deixa de valer.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('barbeiros', 'listar'));

require_once __DIR__ . '/_convite.php';

$st = $pdo->prepare("SELECT id, nome, email FROM utilizadores
                     WHERE id = ? AND barbearia_id = ? AND tipo = 'barbeiro' AND convite_token_hash IS NOT NULL");
$st->execute([(int)($_POST['id'] ?? 0), $bid]);
$u = $st->fetch(PDO::FETCH_ASSOC);

if (!$u) {
    flash('erro', 'Este barbeiro já ativou a conta ou não existe.');
} else {
    enviarConviteEAvisar($pdo, $bid, (string)$u['nome'], (string)$u['email'], criarConvite($pdo, (int)$u['id']));
}
redirecionar(urlModulo('barbeiros', 'listar'));
