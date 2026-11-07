<?php
/**
 * Ficheiro de Exemplo de Ligação à Base de Dados
 * 
 * INSTRUÇÕES PARA OS PROFESSORES / AVALIADORES:
 * 1. Faz uma cópia deste ficheiro e renomeia-a para "db.php".
 * 2. O ficheiro "credenciais.php" deve estar configurado na mesma pasta.
 */

declare(strict_types=1);

// Carrega as credenciais do ficheiro separado (que não vai para o Git)
$credenciais = require __DIR__ . '/credenciais.php';

// Deteção de ambiente (Servidor da ESAN ou XAMPP local)
$isServer = !in_array($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ['127.0.0.1', '::1', 'localhost'], true)
         && str_contains($_SERVER['HTTP_HOST'] ?? '', 'esan-tesp-ds-paw');

$dbHost = $isServer ? 'mysql-sa.mgmt.ua.pt' : 'localhost'; 
const DB_NAME = 'esan-dsg09';
const DB_CHARSET = 'utf8mb4';

try {
    $dsn = 'mysql:host=' . $dbHost . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, $credenciais['user'], $credenciais['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die('Erro de ligação à base de dados: ' . htmlspecialchars($e->getMessage()));
}