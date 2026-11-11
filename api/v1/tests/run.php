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

/* ===================== Task 4 ===================== */
const EMAIL_TESTE = 'teste+api@example.com';   // domínio reservado: o mailer não envia

/** Escolhe slots espaçados de pelo menos $minutos para não se sobreporem. */
function slotsEspacados(array $slots, int $minutos): array {
    $res = []; $ultimo = -100000;
    foreach ($slots as $h) {
        [$hh, $mm] = array_map('intval', explode(':', $h));
        $m = $hh * 60 + $mm;
        if ($m - $ultimo >= $minutos) { $res[] = $h; $ultimo = $m; }
    }
    return $res;
}

function novaMarcacao(string $hora, string $telemovel, ?string $email = EMAIL_TESTE, array $extra = []): array {
    $s = $GLOBALS['slot_teste'];
    return pedido('POST', 'barbearias/rlbarber/marcacoes', $extra + [
        'servico_id' => $s['servico_id'], 'barbeiro_id' => $s['barbeiro_id'], 'data' => $s['data'],
        'hora' => $hora, 'nome' => 'Teste API', 'telemovel' => $telemovel, 'email' => $email,
    ]);
}

function teste_criar_marcacao(): void {
    if (empty($GLOBALS['slot_teste'])) { verifica(false, 'slot_teste disponível (Task 3)'); return; }
    $sufixo = (string)random_int(10, 99);
    $GLOBALS['tel_digitos'] = '9120001' . $sufixo;
    $GLOBALS['tel_outro']   = '9130001' . $sufixo;
    $horas = slotsEspacados($GLOBALS['slot_teste']['slots'], 120);
    verifica(count($horas) >= 4, 'há 4 slots espaçados para os testes');
    if (count($horas) < 4) return;
    $GLOBALS['horas_teste'] = $horas;

    $r = novaMarcacao($horas[0], '+351 912 000 1' . $sufixo);
    verifica($r['status'] === 201, 'criar marcação 201', $r);
    verifica(is_int($r['json']['marcacao_id'] ?? null) && ($r['json']['estado'] ?? '') === 'pendente_verificacao', 'id e estado pendente', $r);
    verifica(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)($r['json']['codigo_expira_em'] ?? '')) === 1, 'codigo_expira_em formatado', $r);
    verifica(strlen((string)($r['json']['codigo_dev'] ?? '')) === 6, 'codigo_dev em modo dev', $r);
    $GLOBALS['marcacao'] = ['id' => (int)($r['json']['marcacao_id'] ?? 0), 'codigo' => (string)($r['json']['codigo_dev'] ?? '')];

    // Review Focus 1: o mesmo slot não pode ser reservado duas vezes
    $r = novaMarcacao($horas[0], $GLOBALS['tel_outro']);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'slot_ocupado', 'mesmo slot 409 slot_ocupado', $r);
    $d = pedido('GET', 'barbearias/rlbarber/disponibilidade', null, [
        'servico_id' => $GLOBALS['slot_teste']['servico_id'], 'barbeiro_id' => $GLOBALS['slot_teste']['barbeiro_id'], 'data' => $GLOBALS['slot_teste']['data']]);
    verifica(!in_array($horas[0], (array)($d['json']['slots'] ?? []), true), 'slot reservado deixa de estar livre', $d);

    $r = novaMarcacao($horas[1], '12345');
    verifica($r['status'] === 422 && ($r['json']['erro']['codigo'] ?? '') === 'dados_invalidos', 'telemóvel inválido 422', $r);
    $r = novaMarcacao($horas[1], $GLOBALS['tel_outro'], '');
    verifica($r['status'] === 422 && ($r['json']['erro']['codigo'] ?? '') === 'dados_invalidos', 'email vazio 422', $r);
    $r = novaMarcacao('25:00', $GLOBALS['tel_outro']);
    verifica($r['status'] === 422, 'hora inválida 422', $r);
    $r = novaMarcacao($horas[1], $GLOBALS['tel_outro'], EMAIL_TESTE, ['nome' => 'A']);
    verifica($r['status'] === 422, 'nome curto 422', $r);
    $r = pedido('POST', 'barbearias/rlbarber/marcacoes', ['servico_id' => 'x']);
    verifica($r['status'] === 400 || $r['status'] === 422, 'corpo incompleto 400/422', $r);

    // Review Focus 2: formatos diferentes do mesmo telemóvel caem no mesmo cliente
    $tel00 = '00351' . $GLOBALS['tel_digitos'];
    $r2 = novaMarcacao($horas[1], $tel00);
    $r3 = novaMarcacao($horas[2], $GLOBALS['tel_digitos']);
    verifica($r2['status'] === 201 && $r3['status'] === 201, '2.ª e 3.ª marcações do mesmo cliente 201', $r3);
    $r4 = novaMarcacao($horas[3], '+351' . $GLOBALS['tel_digitos']);
    verifica($r4['status'] === 409 && ($r4['json']['erro']['codigo'] ?? '') === 'limite_marcacoes', '4.ª marcação ativa 409 limite_marcacoes', $r4);
    $GLOBALS['marcacoes_extra'] = [(int)($r2['json']['marcacao_id'] ?? 0), (int)($r3['json']['marcacao_id'] ?? 0)];
}

/* ===================== Task 5 ===================== */
function teste_verificar(): void {
    $m = $GLOBALS['marcacao'] ?? null;
    if (!$m || !$m['id']) { verifica(false, 'marcação criada na Task 4'); return; }
    $rota = "marcacoes/{$m['id']}/verificar";
    $errado = $m['codigo'] === '000000' ? '111111' : '000000';

    $r = pedido('POST', $rota, ['codigo' => $errado]);
    verifica($r['status'] === 422 && ($r['json']['erro']['codigo'] ?? '') === 'codigo_errado', 'código errado 422', $r);
    verifica(($r['json']['erro']['tentativas_restantes'] ?? null) === 4, 'tentativas_restantes = 4', $r);
    $r = pedido('POST', $rota, ['codigo' => '12a']);
    verifica($r['status'] === 422 && ($r['json']['erro']['codigo'] ?? '') === 'codigo_invalido', 'código mal formado 422', $r);

    $r = pedido('POST', "marcacoes/{$m['id']}/reenviar");
    verifica($r['status'] === 200 && strlen((string)($r['json']['codigo_dev'] ?? '')) === 6, 'reenviar 200 com novo código', $r);
    $novo = (string)($r['json']['codigo_dev'] ?? '');
    $r = pedido('POST', $rota, ['codigo' => $m['codigo'] === $novo ? $errado : $m['codigo']]);
    verifica($r['status'] === 422, 'código antigo deixa de valer após reenvio', $r);

    $r = pedido('POST', $rota, ['codigo' => $novo]);
    verifica($r['status'] === 200 && ($r['json']['estado'] ?? '') === 'confirmada', 'código certo confirma', $r);
    verifica(preg_match('/^[0-9a-f]{64}$/', (string)($r['json']['token'] ?? '')) === 1, 'token hex de 64', $r);
    $GLOBALS['token'] = (string)($r['json']['token'] ?? '');

    // Review Focus 5: nunca confirmar duas vezes
    $r = pedido('POST', $rota, ['codigo' => $novo]);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'ja_confirmada', 'verificar de novo 409 ja_confirmada', $r);
    $r = pedido('POST', 'marcacoes/999999/verificar', ['codigo' => '123456']);
    verifica($r['status'] === 404 && ($r['json']['erro']['codigo'] ?? '') === 'marcacao_nao_encontrada', 'marcação inexistente 404', $r);

    // Limite de tentativas: à 5.ª errada a marcação expira (regra de publico/verificar.php)
    [$a, $b] = $GLOBALS['marcacoes_extra'] ?? [0, 0];
    for ($i = 1; $i <= 4; $i++) $r = pedido('POST', "marcacoes/$a/verificar", ['codigo' => '000001']);
    verifica(($r['json']['erro']['tentativas_restantes'] ?? null) === 1, '4.ª errada deixa 1 tentativa', $r);
    $r = pedido('POST', "marcacoes/$a/verificar", ['codigo' => '000001']);
    verifica($r['status'] === 410 && ($r['json']['erro']['codigo'] ?? '') === 'expirada', '5.ª errada 410 expirada', $r);
    $r = pedido('POST', "marcacoes/$a/reenviar");
    verifica($r['status'] === 410, 'reenviar marcação expirada 410', $r);

    // Limite de reenvios: 1 envio original + MAX_REENVIOS (2)
    $r1 = pedido('POST', "marcacoes/$b/reenviar");
    $r2 = pedido('POST', "marcacoes/$b/reenviar");
    $r3 = pedido('POST', "marcacoes/$b/reenviar");
    verifica($r1['status'] === 200 && $r2['status'] === 200, 'dois reenvios permitidos', $r2);
    verifica($r3['status'] === 429 && ($r3['json']['erro']['codigo'] ?? '') === 'limite_reenvios', '3.º reenvio 429', $r3);
}

/* ===================== Task 6 ===================== */
function teste_listar(): void {
    $id = (int)($GLOBALS['marcacao']['id'] ?? 0);
    $tok = (string)($GLOBALS['token'] ?? '');
    if (!$id || $tok === '') { verifica(false, 'token obtido na Task 5'); return; }

    $r = pedido('GET', 'marcacoes', null, ['tokens' => "$id:$tok"]);
    verifica($r['status'] === 200 && count((array)$r['json']) === 1, 'lista com 1 marcação', $r);
    $m = $r['json'][0] ?? [];
    verifica(($m['id'] ?? null) === $id && ($m['estado'] ?? '') === 'confirmada', 'id e estado confirmada', $r);
    verifica(($m['barbearia_slug'] ?? '') === 'rlbarber' && is_string($m['servico_nome'] ?? null) && is_string($m['barbeiro_nome'] ?? null), 'resumo da marcação', $r);
    verifica(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)($m['inicio'] ?? '')) === 1, 'inicio formatado', $r);
    verifica(!array_key_exists('email', $m) && !array_key_exists('cliente_id', $m), 'sem dados do cliente', $r);

    // Review Focus 4: token adulterado ou usado com outro id não revela nada
    $adulterado = substr($tok, 0, -1) . ($tok[-1] === 'a' ? 'b' : 'a');
    $r = pedido('GET', 'marcacoes', null, ['tokens' => "$id:$adulterado"]);
    verifica($r['status'] === 200 && $r['json'] === [], 'token adulterado → []', $r);
    $outro = (int)($GLOBALS['marcacoes_extra'][1] ?? ($id + 1));
    $r = pedido('GET', 'marcacoes', null, ['tokens' => "$outro:$tok"]);
    verifica($r['status'] === 200 && $r['json'] === [], 'token de outra marcação → []', $r);
    $r = pedido('GET', 'marcacoes', null, ['tokens' => "$id:$adulterado,$id:$tok,lixo,:,999999:abc"]);
    verifica(count((array)$r['json']) === 1, 'pares inválidos ignorados, válido mantido', $r);
    teste_listar_leitura();
}

/** Parte só-leitura (segura para correr na UA). */
function teste_listar_leitura(): void {
    $r = pedido('GET', 'marcacoes', null, ['tokens' => 'lixo']);
    verifica($r['status'] === 200 && $r['json'] === [], 'tokens=lixo → [] 200', $r);
    $r = pedido('GET', 'marcacoes');
    verifica($r['status'] === 200 && $r['json'] === [], 'sem tokens → [] 200', $r);
}

/** Apaga tudo o que os testes criaram (só em local: usa a BD diretamente). */
function limpar_dados_teste(): void {
    $tels = array_filter([$GLOBALS['tel_digitos'] ?? null, $GLOBALS['tel_outro'] ?? null]);
    if (!$tels) return;
    require __DIR__ . '/../../../config/db.php';
    $marcas = implode(',', array_fill(0, count($tels), '?'));
    $ids = $pdo->prepare("SELECT id FROM clientes WHERE barbearia_id = 1 AND telemovel IN ($marcas)");
    $ids->execute(array_values($tels));
    $clientes = $ids->fetchAll(PDO::FETCH_COLUMN);
    if (!$clientes) return;
    $mc = implode(',', array_fill(0, count($clientes), '?'));
    $pdo->prepare("DELETE v FROM verificacoes v JOIN marcacoes m ON m.id = v.marcacao_id WHERE m.cliente_id IN ($mc)")->execute($clientes);
    $pdo->prepare("DELETE FROM marcacoes WHERE cliente_id IN ($mc)")->execute($clientes);
    $pdo->prepare("DELETE FROM clientes WHERE id IN ($mc)")->execute($clientes);
    echo "(limpeza: " . count($clientes) . " cliente(s) de teste removido(s))\n";
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
