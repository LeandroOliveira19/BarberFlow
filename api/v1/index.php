<?php
declare(strict_types=1);

/**
 * API JSON v1 do BarberFlow (app mobile).
 * Router único: index.php?r=<rota>  (sem URL rewrite — igual em XAMPP e IIS).
 */

require __DIR__ . '/_lib.php';
instalarProtecaoJson();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') responder(null, 204);

require __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/marcacoes.php';

ini_set('display_errors', '0');
set_exception_handler(function (Throwable $e): void {
    error_log('[api/v1] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    erro(500, 'erro_interno', 'Ocorreu um erro. Tenta de novo.');
});

/* ===================== Handlers ===================== */

function h_barbearia(PDO $pdo, array $p): never {
    $b   = resolverBarbearia($pdo, $p[0]);
    $cfg = configBarbearia($pdo, $b['id']);
    $hora = function (string $chave) use ($cfg): ?string {
        $v = (string)($cfg[$chave] ?? '');
        return preg_match('/^(\d{1,2}):(\d{2})/', $v, $m) ? sprintf('%02d:%s', (int)$m[1], $m[2]) : null;
    };
    $txt = fn(string $chave): ?string => (($v = trim((string)($cfg[$chave] ?? ''))) === '') ? null : $v;
    responder([
        'id'                  => $b['id'],
        'nome'                => $b['nome'],
        'slug'                => $b['slug'],
        'morada'              => $b['morada'],
        'email_contacto'      => $txt('email_contacto'),
        'telemovel_contacto'  => $txt('telemovel_contacto'),
        'tema_cor'            => corHex($cfg['tema_cor'] ?? '', '#cda47c'),
        'tema_fundo'          => corHex($cfg['tema_fundo'] ?? '', '#0a0a0a'),
        'dias_funcionamento'  => diasFuncionamento($cfg),
        'hora_abertura'       => $hora('hora_abertura'),
        'hora_fecho'          => $hora('hora_fecho'),
        'max_dias'            => MAX_DIAS,
        'codigo_validade_min' => CODIGO_VALIDADE_MIN,
    ]);
}

/* ===================== Router ===================== */

$rotas = [
    ['GET', '#^barbearias/([a-z0-9-]+)$#', 'h_barbearia'],
];

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rota   = trim((string)($_GET['r'] ?? ''), '/');
$metodoErrado = false;

foreach ($rotas as [$m, $regex, $handler]) {
    if (!preg_match($regex, $rota, $match)) continue;
    if ($m !== $metodo) { $metodoErrado = true; continue; }
    $handler($pdo, array_slice($match, 1));
}
if ($metodoErrado) erro(405, 'metodo_nao_permitido', 'Método não permitido.');
erro(404, 'rota_nao_encontrada', 'Recurso não encontrado.');
