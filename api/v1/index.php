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
require_once __DIR__ . '/../../config/reservas.php';

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
        'antecedencia_alteracao_horas' => antecedenciaAlteracaoHoras($cfg),
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

/** Inteiro positivo de $_GET ou erro 400. */
function paramInt(string $nome): int {
    $v = $_GET[$nome] ?? null;
    if (!is_string($v) || !ctype_digit($v) || (int)$v <= 0) erro(400, 'parametros_invalidos', 'Pedido incompleto ou inválido.');
    return (int)$v;
}

function h_disponibilidade(PDO $pdo, array $p): never {
    $b        = resolverBarbearia($pdo, $p[0]);
    $servico  = paramInt('servico_id');
    $barbeiro = paramInt('barbeiro_id');
    $dataStr  = (string)($_GET['data'] ?? '');
    if ($dataStr === '') erro(400, 'parametros_invalidos', 'Pedido incompleto ou inválido.');

    $s   = servicoDaBarbearia($pdo, $b['id'], $servico);
    barbeiroDaBarbearia($pdo, $b['id'], $barbeiro);
    $dia = validarDataMarcacao($dataStr);

    expirarPendentes($pdo, $b['id']);
    $cfg   = configBarbearia($pdo, $b['id']);
    $slots = slotsLivres($pdo, $b['id'], $barbeiro, $dia, (int)$s['duracao_minutos'], $cfg, false);
    responder(['data' => $dia->format('Y-m-d'), 'slots' => array_values($slots)]);
}


function h_criar_marcacao(PDO $pdo, array $p): never {
    $b = resolverBarbearia($pdo, $p[0]);
    segredoApi(); // falha cedo (500) se o segredo não estiver configurado, antes de criar a marcação
    $res = criarMarcacao($pdo, $b, corpoJson());
    $out = [
        'marcacao_id'      => $res['marcacao_id'],
        'estado'           => $res['estado'],
        'codigo_expira_em' => $res['codigo_expira_em'],
        'email_enviado'    => $res['email_enviado'],
        'token_pendente'   => tokenPendente($res['marcacao_id'], $res['cliente_id']),
    ];
    if ($res['codigo'] !== null && deveDevolverCodigo(credenciais(), $res['email_enviado'])) $out['codigo_dev'] = $res['codigo'];
    responder($out, 201);
}

function marcacaoOu404(PDO $pdo, string $id): array {
    $m = ctype_digit($id) ? carregarMarcacaoPorId($pdo, (int)$id) : null;
    if (!$m) erro(404, 'marcacao_nao_encontrada', 'Marcação não encontrada.');
    return $m;
}

function h_verificar(PDO $pdo, array $p): never {
    $m = marcacaoOu404($pdo, $p[0]);
    $corpo = corpoJson();
    exigirTokenPendente($m, $corpo); // também garante que o segredo é válido antes de confirmar
    $codigo = $corpo['codigo'] ?? '';
    $ok = verificarCodigo($pdo, $m, is_scalar($codigo) ? (string)$codigo : '');
    responder([
        'marcacao_id' => (int)$ok['id'],
        'estado'      => $ok['estado'],
        'token'       => tokenMarcacao((int)$ok['id'], (int)$ok['cliente_id']),
    ]);
}

function h_reenviar(PDO $pdo, array $p): never {
    $m = marcacaoOu404($pdo, $p[0]);
    exigirTokenPendente($m, corpoJson());
    $res = reenviarCodigo($pdo, $m);
    $out = ['codigo_expira_em' => $res['codigo_expira_em'], 'email_enviado' => $res['email_enviado']];
    if (deveDevolverCodigo(credenciais(), $res['email_enviado'])) $out['codigo_dev'] = $res['codigo'];
    responder($out);
}

/** GET marcacoes?tokens=id:tok,id:tok — só devolve marcações cujo token é válido. */
function h_listar_marcacoes(PDO $pdo, array $p): never {
    $pares = [];
    foreach (array_slice(explode(',', (string)($_GET['tokens'] ?? '')), 0, 50) as $par) {
        if (preg_match('/^(\d{1,10}):([0-9a-f]{64})$/', trim($par), $m)) $pares[(int)$m[1]] = $m[2];
    }
    $lista = [];
    $expiradas = [];
    foreach ($pares as $id => $tok) {
        $m = carregarMarcacaoPorId($pdo, $id);
        if (!$m || !hash_equals(tokenMarcacao((int)$m['id'], (int)$m['cliente_id']), $tok)) continue;
        if (!isset($expiradas[$m['barbearia_id']])) { expirarPendentes($pdo, (int)$m['barbearia_id']); $expiradas[$m['barbearia_id']] = true; }
        $m = normalizarEstadoMarcacao($pdo, carregarMarcacaoPorId($pdo, $id) ?? $m);
        $lista[] = itemMarcacao($m);
    }
    usort($lista, fn($a, $b) => strcmp($b['inicio'], $a['inicio']));
    responder($lista);
}

/** Formato público de uma marcação (lista, cancelar, remarcar): sem dados do cliente. */
function itemMarcacao(array $m): array {
    return [
        'id'             => (int)$m['id'],
        'estado'         => $m['estado'],
        'inicio'         => $m['inicio'],
        'fim'            => $m['fim'],
        'servico_id'     => (int)$m['servico_id'],
        'servico_nome'   => $m['servico_nome'],
        'para_nome'      => $m['para_nome'],
        'barbeiro_id'    => (int)$m['barbeiro_id'],
        'barbeiro_nome'  => $m['barbeiro_nome'],
        'barbearia_slug' => $m['barbearia_slug'],
        'barbearia_nome' => $m['barbearia_nome'],
    ];
}

/** Marcação cujo token (o mesmo da lista) é válido; caso contrário 404, sem revelar se existe. */
function marcacaoComToken(PDO $pdo, string $id, array $corpo): array {
    $m = ctype_digit($id) ? carregarMarcacaoPorId($pdo, (int)$id) : null;
    $tok = $corpo['token'] ?? '';
    if (!$m || !is_string($tok) || !hash_equals(tokenMarcacao((int)$m['id'], (int)$m['cliente_id']), $tok)) {
        erro(404, 'marcacao_nao_encontrada', 'Marcação não encontrada.');
    }
    return $m;
}

/** POST marcacoes/{id}/cancelar {token} */
function h_cancelar(PDO $pdo, array $p): never {
    $m = marcacaoComToken($pdo, $p[0], corpoJson());
    cancelarMarcacaoCliente($pdo, $m);
    responder(itemMarcacao(carregarMarcacaoPorId($pdo, (int)$m['id'])));
}

/** POST marcacoes/{id}/remarcar {token, barbeiro_id, data, hora} */
function h_remarcar(PDO $pdo, array $p): never {
    $corpo = corpoJson();
    $m = marcacaoComToken($pdo, $p[0], $corpo);
    remarcarMarcacaoCliente($pdo, $m, $corpo);
    responder(itemMarcacao(carregarMarcacaoPorId($pdo, (int)$m['id'])));
}

/* ===================== Router ===================== */

$rotas = [
    ['GET', '#^barbearias/([a-z0-9-]+)$#', 'h_barbearia'],
    ['GET', '#^barbearias/([a-z0-9-]+)/servicos$#', 'h_servicos'],
    ['GET', '#^barbearias/([a-z0-9-]+)/equipa$#', 'h_equipa'],
    ['GET', '#^barbearias/([a-z0-9-]+)/reviews$#', 'h_reviews'],
    ['GET', '#^barbearias/([a-z0-9-]+)/disponibilidade$#', 'h_disponibilidade'],
    ['POST', '#^barbearias/([a-z0-9-]+)/marcacoes$#', 'h_criar_marcacao'],
    ['POST', '#^marcacoes/(\d+)/verificar$#', 'h_verificar'],
    ['POST', '#^marcacoes/(\d+)/reenviar$#', 'h_reenviar'],
    ['POST', '#^marcacoes/(\d+)/cancelar$#', 'h_cancelar'],
    ['POST', '#^marcacoes/(\d+)/remarcar$#', 'h_remarcar'],
    ['GET', '#^marcacoes$#', 'h_listar_marcacoes'],
];

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$rota   = trim((string)($_GET['r'] ?? ''), '/');
$metodoErrado = false;

foreach ($rotas as [$m, $regex, $handler]) {
    if (!preg_match($regex, $rota, $match)) continue;
    if ($m !== $metodo) { $metodoErrado = true; continue; }
    try {
        $handler($pdo, array_slice($match, 1));
    } catch (ReservaErro $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        erro($e->status, $e->codigo, $e->getMessage(), $e->extra);
    }
}
if ($metodoErrado) erro(405, 'metodo_nao_permitido', 'Método não permitido.');
erro(404, 'rota_nao_encontrada', 'Recurso não encontrado.');
