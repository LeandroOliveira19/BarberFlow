<?php
declare(strict_types=1);
// stock: preparação partilhada pela leitura e pelas ações (não é uma ação)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

require_once __DIR__ . '/../../../config/movimentos.php';

$uid     = (int)$_SESSION['utilizador_id'];
$bid     = (int)$_SESSION['barbearia_id'];
$tipo    = (string)$_SESSION['tipo'];
$eGestor = ($tipo === 'gestor');

$msg  = '';
$erro = '';

// A bancada do barbeiro (calculada cedo porque é usada no POST e na query)
$bancada_id = null;
if (!$eGestor) {
    $stmt = $pdo->prepare("SELECT id FROM bancadas WHERE barbeiro_id = ? AND barbearia_id = ? AND ativo = 1");
    $stmt->execute([$uid, $bid]);
    $col = $stmt->fetchColumn();
    $bancada_id = ($col !== false) ? (int)$col : null;
}
