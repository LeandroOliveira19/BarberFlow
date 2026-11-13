<?php
/**
 * Ligação à base de dados (exemplo).
 *
 * INSTALAÇÃO: copia este ficheiro para "db.php" (na mesma pasta). Não precisas de o editar:
 * os dados da ligação vêm de "credenciais.php" (ver credenciais.example.php).
 *  - Servidor da UA: usa o anfitrião mysql-sa.mgmt.ua.pt com 'user'/'pass'.
 *  - Local (XAMPP): define 'db_host', 'db_user' e 'db_pass' no credenciais.php.
 */

declare(strict_types=1);

$credenciais = require __DIR__ . '/credenciais.php';

$dbHost = $credenciais['db_host'] ?? 'mysql-sa.mgmt.ua.pt';
$dbUser = $credenciais['db_user'] ?? $credenciais['user'];
$dbPass = $credenciais['db_pass'] ?? $credenciais['pass'];
const DB_NAME    = 'esan-dsg09';
const DB_CHARSET = 'utf8mb4';

try {
    $pdo = new PDO('mysql:host=' . $dbHost . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // erros de SQL viram exceções
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,                    // prepared statements reais
    ]);
} catch (PDOException $e) {
    // O detalhe vai para o log do servidor; o visitante não vê nomes de anfitriões nem utilizadores
    error_log('[BarberFlow] Ligação à BD falhou: ' . $e->getMessage());
    http_response_code(503);
    die('<h3>O serviço está temporariamente indisponível.</h3><p>Tenta de novo dentro de alguns minutos.</p>');
}
