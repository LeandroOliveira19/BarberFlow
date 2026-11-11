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

function h_servicos(PDO $pdo, array $p): never {
    $b  = resolverBarbearia($pdo, $p[0]);
    $st = $pdo->prepare("SELECT id, nome, descricao, duracao_minutos, preco FROM servicos
                         WHERE barbearia_id = ? AND ativo = 1 ORDER BY nome");
    $st->execute([$b['id']]);
    $lista = array_map(fn(array $s) => [
        'id'              => (int)$s['id'],
        'nome'            => $s['nome'],
        'descricao'       => ($s['descricao'] ?? '') !== '' ? $s['descricao'] : null,
        'duracao_minutos' => (int)$s['duracao_minutos'],
        'preco'           => (float)$s['preco'],
    ], $st->fetchAll(PDO::FETCH_ASSOC));
    responder($lista);
}

function h_equipa(PDO $pdo, array $p): never {
    $b   = resolverBarbearia($pdo, $p[0]);
    $ids = array_map(fn($x) => (int)$x['id'], barbeirosReservaveis($pdo, $b['id']));
    if (!$ids) responder([]);
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT id, nome, foto_url, bio, especialidades, instagram FROM utilizadores
                         WHERE barbearia_id = ? AND id IN ($marcas) ORDER BY nome");
    $st->execute(array_merge([$b['id']], $ids));
    $nulo = fn($v) => ($v === null || trim((string)$v) === '') ? null : trim((string)$v);
    responder(array_map(fn(array $u) => [
        'id'             => (int)$u['id'],
        'nome'           => $u['nome'],
        'foto_url'       => urlAbsoluta($u['foto_url']),
        'bio'            => $nulo($u['bio']),
        'especialidades' => $nulo($u['especialidades']),
        'instagram'      => $nulo($u['instagram']),
    ], $st->fetchAll(PDO::FETCH_ASSOC)));
}

function h_reviews(PDO $pdo, array $p): never {
    require_once __DIR__ . '/../../config/reviews.php';
    $b       = resolverBarbearia($pdo, $p[0]);
    $placeId = trim((string)(configBarbearia($pdo, $b['id'])['google_place_id'] ?? ''));
    $token   = (string)(credenciais()['apify_token'] ?? '');
    if ($placeId === '' || $token === '') responder([]);
    try {
        $itens = obterReviewsApify($placeId, $token, $b['id']);
    } catch (Throwable $e) {
        error_log('[api/v1] reviews: ' . $e->getMessage());
        $itens = [];
    }
    // A cache normalizada só tem author_name, rating e text (ver config/reviews.php)
    responder(array_values(array_map(fn(array $r) => [
        'autor'    => (string)($r['author_name'] ?? 'Cliente'),
        'foto_url' => null,
        'estrelas' => max(1, min(5, (int)($r['rating'] ?? 5))),
        'texto'    => ($t = trim((string)($r['text'] ?? ''))) !== '' ? $t : null,
        'data'     => null,
    ], array_filter((array)$itens, 'is_array'))));
}

/* ===================== Router ===================== */

$rotas = [
    ['GET', '#^barbearias/([a-z0-9-]+)$#', 'h_barbearia'],
    ['GET', '#^barbearias/([a-z0-9-]+)/servicos$#', 'h_servicos'],
    ['GET', '#^barbearias/([a-z0-9-]+)/equipa$#', 'h_equipa'],
    ['GET', '#^barbearias/([a-z0-9-]+)/reviews$#', 'h_reviews'],
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
