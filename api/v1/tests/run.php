<?php
declare(strict_types=1);

/**
 * Testes da API v1 (CLI).
 * Uso: php api/v1/tests/run.php [base_url] [--so-leitura]
 *   base_url por omissão: http://127.0.0.1:8080/projeto/projeto/api/v1/index.php
 *   --so-leitura: só testes GET (para correr contra a UA sem criar dados)
 */

$args = array_slice($argv, 1);
$SO_LEITURA = in_array('--so-leitura', $args, true);
$args = array_values(array_filter($args, fn($a) => $a !== '--so-leitura'));
$BASE = $args[0] ?? 'http://127.0.0.1:8080/projeto/projeto/api/v1/index.php';

$FALHAS = 0;
$TOTAL  = 0;

/** @return array{status:int, json:mixed, tipo:string, corpo:string} */
function pedido(string $metodo, string $rota, ?array $corpo = null, array $query = []): array {
    global $BASE;
    $url = $BASE . '?' . http_build_query(['r' => $rota] + $query);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    if ($corpo !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($corpo));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $resp = curl_exec($ch);
    if ($resp === false) {
        $erro = curl_error($ch);
        curl_close($ch);
        return ['status' => 0, 'json' => null, 'tipo' => '', 'corpo' => $erro];
    }
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $tipo   = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    return ['status' => $status, 'json' => json_decode((string)$resp, true), 'tipo' => $tipo, 'corpo' => (string)$resp];
}

function verifica(bool $cond, string $descricao, ?array $resp = null): void {
    global $FALHAS, $TOTAL;
    $TOTAL++;
    if ($cond) {
        echo "OK      $descricao\n";
    } else {
        $FALHAS++;
        $extra = $resp ? '  [' . $resp['status'] . '] ' . substr($resp['corpo'], 0, 200) : '';
        echo "FALHOU  $descricao$extra\n";
    }
}

function saltado(string $descricao): void { echo "SALTADO $descricao\n"; }

/* ===================== Task 1 ===================== */
function teste_barbearia(): void {
    $r = pedido('GET', 'barbearias/rlbarber');
    verifica($r['status'] === 200, 'barbearia rlbarber 200', $r);
    verifica(($r['json']['slug'] ?? null) === 'rlbarber', 'slug devolvido', $r);
    verifica(is_array($r['json']['dias_funcionamento'] ?? null), 'dias_funcionamento é lista', $r);
    verifica(($r['json']['max_dias'] ?? null) === 30, 'max_dias = MAX_DIAS', $r);
    verifica(($r['json']['codigo_validade_min'] ?? null) === 15, 'codigo_validade_min = 15', $r);
    $r = pedido('GET', 'barbearias/nao-existe');
    verifica($r['status'] === 404 && ($r['json']['erro']['codigo'] ?? '') === 'barbearia_nao_encontrada', 'slug inexistente 404', $r);
    $r = pedido('GET', 'coisa/qualquer');
    verifica($r['status'] === 404 && ($r['json']['erro']['codigo'] ?? '') === 'rota_nao_encontrada', 'rota inexistente 404', $r);
    $r = pedido('POST', 'barbearias/rlbarber', []);
    verifica($r['status'] === 405, 'método errado 405', $r);
    $r = pedido('OPTIONS', 'barbearias/rlbarber');
    verifica($r['status'] === 204, 'preflight CORS 204', $r);
}

/* ===================== Task 2 ===================== */
function teste_catalogo(): void {
    $s = pedido('GET', 'barbearias/rlbarber/servicos');
    verifica($s['status'] === 200 && is_array($s['json']) && count($s['json']) > 0, 'servicos não vazio', $s);
    $p = $s['json'][0] ?? [];
    verifica(is_int($p['id'] ?? null) && is_int($p['duracao_minutos'] ?? null) && is_float($p['preco'] ?? null), 'tipos numéricos em servicos', $s);
    verifica(!array_key_exists('ativo', $p) && !array_key_exists('barbearia_id', $p), 'servicos sem campos internos', $s);
    $GLOBALS['servicos'] = $s['json'];

    $e = pedido('GET', 'barbearias/rlbarber/equipa');
    verifica($e['status'] === 200 && is_array($e['json']) && count($e['json']) > 0, 'equipa não vazia', $e);
    $b = $e['json'][0] ?? [];
    verifica(array_key_exists('instagram', $b) && array_key_exists('bio', $b) && array_key_exists('especialidades', $b), 'equipa com perfil público', $e);
    verifica(!array_key_exists('email', $b) && !array_key_exists('password_hash', $b) && !array_key_exists('telefone', $b), 'equipa sem dados privados', $e);
    $fotosOk = true;
    foreach ($e['json'] as $x) { if ($x['foto_url'] !== null && !str_starts_with($x['foto_url'], 'http')) $fotosOk = false; }
    verifica($fotosOk, 'foto_url absoluto ou null', $e);
    $GLOBALS['equipa'] = $e['json'];

    $outra = pedido('GET', 'barbearias/navalha/equipa');
    $idsRl = array_column($e['json'], 'id');
    verifica(!array_intersect($idsRl, array_column((array)$outra['json'], 'id')), 'equipa isolada por barbearia', $outra);

    $rv = pedido('GET', 'barbearias/rlbarber/reviews');
    verifica($rv['status'] === 200 && is_array($rv['json']), 'reviews lista', $rv);
    if ($rv['json']) {
        $r0 = $rv['json'][0];
        verifica(is_string($r0['autor'] ?? null) && is_int($r0['estrelas'] ?? null) && array_key_exists('texto', $r0), 'review com autor/estrelas/texto', $rv);
    }
}

/* ===================== Task 3 ===================== */
function teste_disponibilidade(): void {
    $sv = (int)($GLOBALS['servicos'][0]['id'] ?? 0);
    $rota = 'barbearias/rlbarber/disponibilidade';
    $encontrado = null;
    foreach ($GLOBALS['equipa'] ?? [] as $barb) {
        for ($d = 1; $d <= 14 && !$encontrado; $d++) {
            $data = (new DateTime('today'))->modify("+$d days")->format('Y-m-d');
            $r = pedido('GET', $rota, null, ['servico_id' => $sv, 'barbeiro_id' => $barb['id'], 'data' => $data]);
            if ($r['status'] !== 200) { verifica(false, "disponibilidade $data 200", $r); return; }
            if (!empty($r['json']['slots'])) $encontrado = [$sv, (int)$barb['id'], $data, $r['json']];
        }
        if ($encontrado) break;
    }
    verifica($encontrado !== null, 'há pelo menos um dia com slots livres nos próximos 14 dias');
    if (!$encontrado) return;
    [$sv, $bb, $data, $json] = $encontrado;
    verifica($json['data'] === $data, 'data ecoada');
    $formatoOk = array_reduce($json['slots'], fn($ok, $h) => $ok && preg_match('/^\d{2}:\d{2}$/', $h) === 1, true);
    verifica($formatoOk, 'slots no formato HH:MM');
    $GLOBALS['slot_teste'] = ['servico_id' => $sv, 'barbeiro_id' => $bb, 'data' => $data, 'slots' => $json['slots']];

    $ontem = (new DateTime('yesterday'))->format('Y-m-d');
    $r = pedido('GET', $rota, null, ['servico_id' => $sv, 'barbeiro_id' => $bb, 'data' => $ontem]);
    verifica($r['status'] === 422 && ($r['json']['erro']['codigo'] ?? '') === 'data_invalida', 'data passada 422', $r);
    $longe = (new DateTime('today'))->modify('+31 days')->format('Y-m-d');
    $r = pedido('GET', $rota, null, ['servico_id' => $sv, 'barbeiro_id' => $bb, 'data' => $longe]);
    verifica($r['status'] === 422, 'data além de MAX_DIAS 422', $r);
    $r = pedido('GET', $rota, null, ['servico_id' => $sv, 'barbeiro_id' => $bb, 'data' => '2026-02-30']);
    verifica($r['status'] === 422, 'data inexistente 422', $r);
    $r = pedido('GET', $rota, null, ['servico_id' => 999999, 'barbeiro_id' => $bb, 'data' => $data]);
    verifica($r['status'] === 404 && ($r['json']['erro']['codigo'] ?? '') === 'servico_nao_encontrado', 'serviço inexistente 404', $r);
    $r = pedido('GET', $rota, null, ['servico_id' => $sv, 'barbeiro_id' => 999999, 'data' => $data]);
    verifica($r['status'] === 404 && ($r['json']['erro']['codigo'] ?? '') === 'barbeiro_nao_encontrado', 'barbeiro inexistente 404', $r);
    $r = pedido('GET', $rota, null, ['servico_id' => $sv, 'data' => $data]);
    verifica($r['status'] === 400 && ($r['json']['erro']['codigo'] ?? '') === 'parametros_invalidos', 'sem barbeiro_id 400', $r);
    // Isolamento: barbeiro da RLBarber pedido através de outra barbearia
    $r = pedido('GET', 'barbearias/navalha/disponibilidade', null, ['servico_id' => $sv, 'barbeiro_id' => $bb, 'data' => $data]);
    verifica($r['status'] === 404, 'serviço/barbeiro de outra barbearia 404', $r);
}

/** Review Focus 3: todas as rotas GET respondem JSON válido */
function teste_sempre_json(): void {
    $rotas = [
        'barbearias/rlbarber', 'barbearias/nao-existe', 'coisa/qualquer',
        'barbearias/rlbarber/servicos', 'barbearias/rlbarber/equipa', 'barbearias/rlbarber/reviews',
        'barbearias/rlbarber/disponibilidade', 'marcacoes',
    ];
    foreach ($rotas as $rota) {
        $r = pedido('GET', $rota);
        verifica(str_starts_with($r['tipo'], 'application/json') && $r['json'] !== null, "JSON válido em $rota", $r);
    }
}

/* ===================== Execução ===================== */
$leitura = ['teste_barbearia', 'teste_catalogo', 'teste_disponibilidade', 'teste_sempre_json'];
$escrita = ['teste_criar_marcacao', 'teste_verificar', 'teste_listar'];

foreach ($leitura as $t) { if (function_exists($t)) { echo "--- $t\n"; $t(); } }
if ($SO_LEITURA) {
    if (function_exists('teste_listar_leitura')) { echo "--- teste_listar_leitura\n"; teste_listar_leitura(); }
} else {
    try {
        foreach ($escrita as $t) { if (function_exists($t)) { echo "--- $t\n"; $t(); } }
    } finally {
        if (function_exists('limpar_dados_teste')) limpar_dados_teste();
    }
}

echo "\n$TOTAL testes, $FALHAS falhas\n";
exit($FALHAS > 0 ? 1 : 0);
