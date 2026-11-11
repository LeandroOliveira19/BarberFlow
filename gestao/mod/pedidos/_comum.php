<?php
declare(strict_types=1);
// pedidos: preparação partilhada pela leitura e pelas ações (não é uma ação)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

$bid = (int)$_SESSION['barbearia_id'];
$msg  = '';
$erro = '';

$ESTADOS = [
    'pendente'  => 'Pendente',
    'aprovado'  => 'Aprovado',
    'entregue'  => 'Entregue',
    'rejeitado' => 'Rejeitado',
];
