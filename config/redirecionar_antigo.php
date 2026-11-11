<?php
declare(strict_types=1);

/**
 * Compatibilidade: as páginas antigas (gestao/agenda.php, publico/marcar.php, ...) passaram a ser módulos.
 * Cada ficheiro antigo chama esta função para enviar o visitante para o URL novo, mantendo os parâmetros.
 */
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

function redirecionarParaModulo(string $index, string $modulo, string $acao): never {
    $extra = $_GET;
    unset($extra['m'], $extra['a']);
    header('Location: ' . $index . '?' . http_build_query(['m' => $modulo, 'a' => $acao] + $extra), true, 302);
    exit;
}
