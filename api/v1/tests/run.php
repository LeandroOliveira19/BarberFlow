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

function guardarPendente(array $r): void {
    $id = (int)($r['json']['marcacao_id'] ?? 0);
    if ($id) $GLOBALS['pend'][$id] = (string)($r['json']['token_pendente'] ?? '');
}

/** POST marcacoes/{id}/{acao} com o token_pendente devolvido na criação. */
function acao(int $id, string $acao, array $corpo = []): array {
    return pedido('POST', "marcacoes/$id/$acao", $corpo + ['token_pendente' => $GLOBALS['pend'][$id] ?? '']);
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
    verifica(preg_match('/^[0-9a-f]{64}$/', (string)($r['json']['token_pendente'] ?? '')) === 1, 'token_pendente devolvido', $r);
    guardarPendente($r);

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

    // Regra nova: um cliente só tem uma marcação ativa por dia (Review Focus 2: noutro formato do mesmo telemóvel)
    $tel00 = '00351' . $GLOBALS['tel_digitos'];
    $r = novaMarcacao($horas[1], $tel00);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'marcacao_no_dia', '2.ª marcação do mesmo cliente no mesmo dia 409', $r);
    $r = novaMarcacao($horas[1], $GLOBALS['tel_outro']);
    verifica($r['status'] === 201, 'outro cliente no mesmo dia continua a poder marcar', $r);

    // Marcar para outra pessoa (ex.: o pai marca para o filho): a regra do dia é por pessoa
    $rTiago = novaMarcacao($horas[2], $tel00, EMAIL_TESTE, ['para_nome' => 'Tiago']);
    verifica($rTiago['status'] === 201, 'mesmo telemóvel, mesmo dia, para outra pessoa 201', $rTiago);
    $r = novaMarcacao($horas[3], $GLOBALS['tel_digitos'], EMAIL_TESTE, ['para_nome' => '  tiago ']);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'marcacao_no_dia', 'mesma pessoa (Tiago/tiago) no mesmo dia 409', $r);
    $r = novaMarcacao($horas[3], $GLOBALS['tel_digitos'], EMAIL_TESTE, ['para_nome' => str_repeat('x', 121)]);
    verifica($r['status'] === 422 && ($r['json']['erro']['codigo'] ?? '') === 'dados_invalidos', 'para_nome com mais de 120 caracteres 422', $r);

    // Limite de marcações ativas (MAX_MARCACOES_ATIVAS_CLIENTE = 3), agora em dias diferentes
    $dias = outrosDiasComSlot(3);
    verifica(count($dias) === 3, 'há 3 outros dias com horários livres');
    if (count($dias) < 3) return;
    $r2 = novaMarcacao($dias[0][1], $tel00, EMAIL_TESTE, ['data' => $dias[0][0]]);
    $r3 = novaMarcacao($dias[1][1], $GLOBALS['tel_digitos'], EMAIL_TESTE, ['data' => $dias[1][0]]);
    verifica($r2['status'] === 201 && $r3['status'] === 201, '2.ª e 3.ª marcações do mesmo cliente (outros dias) 201', $r3);
    guardarPendente($r2);
    guardarPendente($r3);
    $r4 = novaMarcacao($dias[2][1], '+351' . $GLOBALS['tel_digitos'], EMAIL_TESTE, ['data' => $dias[2][0]]);
    verifica($r4['status'] === 409 && ($r4['json']['erro']['codigo'] ?? '') === 'limite_marcacoes', '4.ª marcação ativa 409 limite_marcacoes', $r4);
    // O limite é por pessoa: o Tiago (mesmo telemóvel) ainda pode marcar nesse dia
    $r5 = novaMarcacao($dias[2][1], $GLOBALS['tel_digitos'], EMAIL_TESTE, ['data' => $dias[2][0], 'para_nome' => 'Tiago']);
    verifica($r5['status'] === 201, 'limite de 3 é por pessoa (Tiago ainda marca) 201', $r5);
    $GLOBALS['marcacoes_extra'] = [(int)($r2['json']['marcacao_id'] ?? 0), (int)($r3['json']['marcacao_id'] ?? 0)];
}

/** Primeiro horário livre de $n dias diferentes (depois do dia de teste), para o mesmo serviço e barbeiro. */
function outrosDiasComSlot(int $n): array {
    $s = $GLOBALS['slot_teste'];
    $res = [];
    $dia = new DateTime($s['data']);
    for ($i = 0; $i < 25 && count($res) < $n; $i++) {
        $dia->modify('+1 day');
        $d = pedido('GET', 'barbearias/rlbarber/disponibilidade', null, [
            'servico_id' => $s['servico_id'], 'barbeiro_id' => $s['barbeiro_id'], 'data' => $dia->format('Y-m-d')]);
        if (!empty($d['json']['slots'])) $res[] = [$dia->format('Y-m-d'), $d['json']['slots'][0]];
    }
    return $res;
}

/* ===================== Task 5 ===================== */
function teste_verificar(): void {
    $m = $GLOBALS['marcacao'] ?? null;
    if (!$m || !$m['id']) { verifica(false, 'marcação criada na Task 4'); return; }
    $rota = "marcacoes/{$m['id']}/verificar";
    $errado = $m['codigo'] === '000000' ? '111111' : '000000';

    // Revisão final #1: sem o token_pendente ninguém pode gastar tentativas nem reenviar
    $r = pedido('POST', $rota, ['codigo' => $errado]);
    verifica($r['status'] === 403 && ($r['json']['erro']['codigo'] ?? '') === 'token_invalido', 'verificar sem token_pendente 403', $r);
    $r = pedido('POST', $rota, ['codigo' => $errado, 'token_pendente' => str_repeat('a', 64)]);
    verifica($r['status'] === 403, 'verificar com token_pendente errado 403', $r);
    $r = pedido('POST', "marcacoes/{$m['id']}/reenviar", ['token_pendente' => str_repeat('b', 64)]);
    verifica($r['status'] === 403, 'reenviar com token_pendente errado 403', $r);
    $outro = (int)($GLOBALS['marcacoes_extra'][0] ?? 0);
    $r = pedido('POST', "marcacoes/$outro/verificar", ['codigo' => $errado, 'token_pendente' => $GLOBALS['pend'][$m['id']] ?? '']);
    verifica($r['status'] === 403, 'token_pendente de outra marcação 403', $r);

    $r = acao($m['id'], 'verificar', ['codigo' => $errado]);
    verifica($r['status'] === 422 && ($r['json']['erro']['codigo'] ?? '') === 'codigo_errado', 'código errado 422', $r);
    verifica(($r['json']['erro']['tentativas_restantes'] ?? null) === 4, 'tentativas_restantes = 4', $r);
    $r = acao($m['id'], 'verificar', ['codigo' => '12a']);
    verifica($r['status'] === 422 && ($r['json']['erro']['codigo'] ?? '') === 'codigo_invalido', 'código mal formado 422', $r);

    $r = acao($m['id'], 'reenviar');
    verifica($r['status'] === 200 && strlen((string)($r['json']['codigo_dev'] ?? '')) === 6, 'reenviar 200 com novo código', $r);
    $novo = (string)($r['json']['codigo_dev'] ?? '');
    $r = acao($m['id'], 'verificar', ['codigo' => $m['codigo'] === $novo ? $errado : $m['codigo']]);
    verifica($r['status'] === 422, 'código antigo deixa de valer após reenvio', $r);

    $r = acao($m['id'], 'verificar', ['codigo' => $novo]);
    verifica($r['status'] === 200 && ($r['json']['estado'] ?? '') === 'confirmada', 'código certo confirma', $r);
    verifica(preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/', (string)($r['json']['token'] ?? '')) === 1, 'token devolvido é um JWT', $r);
    $GLOBALS['token'] = (string)($r['json']['token'] ?? '');

    // Review Focus 5: nunca confirmar duas vezes
    $r = acao($m['id'], 'verificar', ['codigo' => $novo]);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'ja_confirmada', 'verificar de novo 409 ja_confirmada', $r);
    $r = pedido('POST', 'marcacoes/999999/verificar', ['codigo' => '123456']);
    verifica($r['status'] === 404 && ($r['json']['erro']['codigo'] ?? '') === 'marcacao_nao_encontrada', 'marcação inexistente 404', $r);

    // Limite de tentativas: à 5.ª errada a marcação expira (regra de publico/verificar.php)
    [$a, $b] = $GLOBALS['marcacoes_extra'] ?? [0, 0];
    for ($i = 1; $i <= 4; $i++) $r = acao($a, 'verificar', ['codigo' => '000001']);
    verifica(($r['json']['erro']['tentativas_restantes'] ?? null) === 1, '4.ª errada deixa 1 tentativa', $r);
    $r = acao($a, 'verificar', ['codigo' => '000001']);
    verifica($r['status'] === 410 && ($r['json']['erro']['codigo'] ?? '') === 'expirada', '5.ª errada 410 expirada', $r);
    $r = acao($a, 'reenviar');
    verifica($r['status'] === 410, 'reenviar marcação expirada 410', $r);

    // Limite de reenvios: 1 envio original + MAX_REENVIOS (2)
    $r1 = acao($b, 'reenviar');
    $r2 = acao($b, 'reenviar');
    $r3 = acao($b, 'reenviar');
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
    verifica(array_key_exists('para_nome', $m) && $m['para_nome'] === null, 'lista inclui para_nome (null = para quem marcou)', $r);

    // Requisitos web: o token devolvido é um JWT, e o formato antigo (apps já instaladas) continua aceite
    verifica(count(explode('.', $tok)) === 3, 'o token da marcação é um JWT (3 partes)');
    require_once __DIR__ . '/../_lib.php';
    $antigo = function_exists('tokenMarcacaoAntigo') ? tokenMarcacaoAntigo($id, (int)($m['cliente_id'] ?? clienteDaMarcacao($id))) : '';
    $GLOBALS['token_antigo'] = $antigo;
    $r = pedido('GET', 'marcacoes', null, ['tokens' => "$id:$antigo"]);
    verifica(count((array)$r['json']) === 1, 'token antigo (HMAC) continua a listar', $r);

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

/* ===================== Cancelar e remarcar pelo cliente ===================== */
function bdTeste(): PDO {
    static $pdo = null;
    if ($pdo === null) { require __DIR__ . '/../../../config/db.php'; }
    return $pdo;
}

/** Cria e confirma uma marcação (código devolvido em modo dev); devolve [id, token]. */
function marcacaoConfirmada(string $hora, string $tel, array $extra = []): array {
    $r = novaMarcacao($hora, $tel, EMAIL_TESTE, $extra);
    guardarPendente($r);
    $id = (int)($r['json']['marcacao_id'] ?? 0);
    $v = acao($id, 'verificar', ['codigo' => (string)($r['json']['codigo_dev'] ?? '')]);
    return [$id, (string)($v['json']['token'] ?? '')];
}

function moverInicio(int $id, int $segundosDeAgora): void {
    // A API usa Europe/Lisbon; o PHP da linha de comandos pode ter outro fuso (aqui: Europe/Berlin)
    $ini = (new DateTime('@' . (time() + $segundosDeAgora)))->setTimezone(new DateTimeZone('Europe/Lisbon'))->format('Y-m-d H:i:s');
    bdTeste()->prepare("UPDATE marcacoes SET inicio = ?, fim = DATE_ADD(?, INTERVAL 30 MINUTE) WHERE id = ?")->execute([$ini, $ini, $id]);
}

/** Antecedência da RLBarber só para o teste (null = apagar a chave, volta às 24 h por omissão). */
function antecedenciaTeste(?int $horas): void {
    $GLOBALS['antecedencia_mexida'] = $horas !== null;   // limpar_dados_teste repõe se o teste parar a meio
    $pdo = bdTeste();
    $pdo->prepare("DELETE FROM configuracoes WHERE barbearia_id = 1 AND chave = 'antecedencia_alteracao_horas'")->execute();
    if ($horas !== null) {
        $pdo->prepare("INSERT INTO configuracoes (barbearia_id, chave, valor) VALUES (1, 'antecedencia_alteracao_horas', ?)")->execute([(string)$horas]);
    }
}

/**
 * Simula um "criar marcação" noutra ligação: bloqueia o barbeiro, lança o remarcar em paralelo (que fica à espera)
 * e depois pede a própria marcação, como faz o scan FOR UPDATE do slotsLivres. Devolve [sem deadlock?, resposta].
 */
function remarcarEnquantoOutroSeguraOBarbeiro(int $id, string $tok, int $barbeiroId, string $data, string $hora): array {
    global $BASE;
    bdTeste();   // define DB_NAME
    $c = require __DIR__ . '/../../../config/credenciais.php';
    $outra = new PDO('mysql:host=' . ($c['db_host'] ?? 'mysql-sa.mgmt.ua.pt') . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        $c['db_user'] ?? $c['user'], $c['db_pass'] ?? $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $outra->exec('SET SESSION innodb_lock_wait_timeout = 8');
    $outra->beginTransaction();
    $outra->prepare('SELECT id FROM utilizadores WHERE id = ? FOR UPDATE')->execute([$barbeiroId]);

    $ch = curl_init($BASE . '?' . http_build_query(['r' => "marcacoes/$id/remarcar"]));
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['token' => $tok, 'barbeiro_id' => $barbeiroId, 'data' => $data, 'hora' => $hora])]);
    $mh = curl_multi_init();
    curl_multi_add_handle($mh, $ch);
    $ate = microtime(true) + 1.0;   // dá tempo ao pedido para chegar ao servidor e ficar à espera do bloqueio
    do { curl_multi_exec($mh, $ativos); curl_multi_select($mh, 0.1); } while (microtime(true) < $ate);

    $semDeadlock = true;
    try {
        $outra->prepare('SELECT id FROM marcacoes WHERE id = ? FOR UPDATE')->execute([$id]);
        $outra->commit();
    } catch (PDOException $e) {
        $semDeadlock = false;
        if ($outra->inTransaction()) $outra->rollBack();
    }
    do { curl_multi_exec($mh, $ativos); curl_multi_select($mh, 0.2); } while ($ativos > 0);
    $corpo = (string)curl_multi_getcontent($ch);
    $resp = ['status' => (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'json' => json_decode($corpo, true), 'corpo' => $corpo];
    curl_multi_remove_handle($mh, $ch);
    curl_multi_close($mh);
    return [$semDeadlock, $resp];
}

function clienteDaMarcacao(int $id): int {
    $st = bdTeste()->prepare("SELECT cliente_id FROM marcacoes WHERE id = ?");
    $st->execute([$id]);
    return (int)$st->fetchColumn();
}

function notasMarcacao(int $id): string {
    $st = bdTeste()->prepare("SELECT COALESCE(notas, '') FROM marcacoes WHERE id = ?");
    $st->execute([$id]);
    return (string)$st->fetchColumn();
}

function teste_alterar(): void {
    $s   = $GLOBALS['slot_teste'] ?? null;
    $id  = (int)($GLOBALS['marcacao']['id'] ?? 0);
    $tok = (string)($GLOBALS['token'] ?? '');
    if (!$s || !$id || $tok === '') { verifica(false, 'marcação confirmada das tasks anteriores'); return; }
    $horas = $GLOBALS['horas_teste'];

    $r = pedido('GET', 'barbearias/rlbarber');
    verifica(($r['json']['antecedencia_alteracao_horas'] ?? null) === 24, 'barbearia inclui antecedencia_alteracao_horas = 24 por omissão', $r);
    $r = pedido('GET', 'marcacoes', null, ['tokens' => "$id:$tok"]);
    $m = $r['json'][0] ?? [];
    verifica(($m['servico_id'] ?? null) === $s['servico_id'] && ($m['barbeiro_id'] ?? null) === $s['barbeiro_id'], 'lista inclui servico_id e barbeiro_id', $r);

    // Review Focus 3: token adulterado ou de outra marcação → 404
    $adulterado = substr($tok, 0, -1) . ($tok[-1] === 'a' ? 'b' : 'a');
    $r = pedido('POST', "marcacoes/$id/cancelar", ['token' => $adulterado]);
    verifica($r['status'] === 404 && ($r['json']['erro']['codigo'] ?? '') === 'marcacao_nao_encontrada', 'cancelar com token adulterado 404', $r);
    $outro = (int)($GLOBALS['marcacoes_extra'][1] ?? 0);
    $r = pedido('POST', "marcacoes/$outro/cancelar", ['token' => $tok]);
    verifica($r['status'] === 404, 'cancelar com token de outra marcação 404', $r);
    $r = pedido('POST', "marcacoes/$id/remarcar", ['token' => $adulterado, 'barbeiro_id' => $s['barbeiro_id'], 'data' => $s['data'], 'hora' => $horas[0]]);
    verifica($r['status'] === 404, 'remarcar com token adulterado 404', $r);

    // O dia de teste pode ser amanhã (menos de 24 h): para os casos de remarcar, antecedência 0
    antecedenciaTeste(0);
    $r = pedido('GET', 'barbearias/rlbarber');
    verifica(($r['json']['antecedencia_alteracao_horas'] ?? null) === 0, 'antecedência configurada (0) chega à API', $r);

    // Review Focus 1: o horário seguinte sobrepõe-se ao da própria marcação e tem de ser aceite
    $seguinte = $s['slots'][array_search($horas[0], $s['slots'], true) + 1] ?? null;
    $r = pedido('POST', "marcacoes/$id/remarcar", ['token' => $tok, 'barbeiro_id' => $s['barbeiro_id'], 'data' => $s['data'], 'hora' => $seguinte]);
    verifica($r['status'] === 200 && ($r['json']['estado'] ?? '') === 'confirmada' && ($r['json']['inicio'] ?? '') === "{$s['data']} $seguinte:00",
        'remarcar para horário sobreposto ao próprio 200', $r);
    verifica(($r['json']['barbeiro_id'] ?? null) === $s['barbeiro_id'] && is_string($r['json']['servico_nome'] ?? null), 'resposta no formato da lista', $r);
    verifica(str_contains(notasMarcacao($id), 'Remarcada pelo cliente na app'), 'notas com o rasto da remarcação');

    // Revisão final #1: a disponibilidade pode ignorar a própria marcação (só com o token dela)
    $q = ['servico_id' => $s['servico_id'], 'barbeiro_id' => $s['barbeiro_id'], 'data' => $s['data']];
    $r = pedido('GET', 'barbearias/rlbarber/disponibilidade', null, $q);
    verifica(!in_array($seguinte, (array)($r['json']['slots'] ?? []), true), 'disponibilidade normal: o horário da própria marcação está ocupado', $r);
    $r = pedido('GET', 'barbearias/rlbarber/disponibilidade', null, $q + ['marcacao_id' => $id, 'token' => $tok]);
    verifica(in_array($seguinte, (array)($r['json']['slots'] ?? []), true), 'disponibilidade com marcacao_id e token: a própria marcação não ocupa', $r);
    $r = pedido('GET', 'barbearias/rlbarber/disponibilidade', null, $q + ['marcacao_id' => $id, 'token' => $adulterado]);
    verifica($r['status'] === 200 && !in_array($seguinte, (array)($r['json']['slots'] ?? []), true), 'disponibilidade com token errado: ignorado', $r);

    // Revisão final #2: ordem dos bloqueios. Um "criar" que já tem o barbeiro e depois precisa da marcação
    // não pode entrar em deadlock com um remarcar que corre ao mesmo tempo.
    [$semDeadlock, $rp] = remarcarEnquantoOutroSeguraOBarbeiro($id, $tok, $s['barbeiro_id'], $s['data'], $horas[0]);
    verifica($semDeadlock, 'outro pedido obtém a marcação enquanto o remarcar espera pelo barbeiro (sem deadlock)');
    verifica($rp['status'] === 200 && ($rp['json']['inicio'] ?? '') === "{$s['data']} {$horas[0]}:00", 'o remarcar concorrente termina com 200', $rp);

    $r = pedido('POST', "marcacoes/$id/remarcar", ['token' => $tok, 'barbeiro_id' => $s['barbeiro_id'], 'data' => $s['data'], 'hora' => $horas[1]]);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'slot_ocupado', 'remarcar para horário ocupado 409', $r);
    $r = pedido('POST', "marcacoes/$id/remarcar", ['token' => $tok, 'barbeiro_id' => $s['barbeiro_id'], 'data' => $s['data'], 'hora' => '25:00']);
    verifica($r['status'] === 422, 'remarcar com hora inválida 422', $r);

    // A mesma pessoa já tem marcação noutro dia (a pendente r3 da Task 4)
    $dias = outrosDiasComSlot(2);
    if (count($dias) === 2) {
        $r = pedido('POST', "marcacoes/$id/remarcar", ['token' => $tok, 'barbeiro_id' => $s['barbeiro_id'], 'data' => $dias[1][0], 'hora' => $dias[1][1]]);
        verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'marcacao_no_dia', 'remarcar para dia em que a pessoa já tem marcação 409', $r);
    } else {
        verifica(false, 'há 2 outros dias com horários livres');
    }

    // Review Focus 5: limite do prazo (24 h, valor por omissão)
    antecedenciaTeste(null);
    $GLOBALS['tel_alt'] = '9150001' . substr($GLOBALS['tel_digitos'], -2);
    [$b, $tokB] = marcacaoConfirmada($horas[3], $GLOBALS['tel_alt']);
    verifica($b > 0 && $tokB !== '', 'segunda marcação confirmada para os testes de prazo');
    moverInicio($b, 24 * 3600 - 120);
    $r = pedido('POST', "marcacoes/$b/cancelar", ['token' => $tokB]);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'fora_do_prazo', 'cancelar a menos de 24 h 409 fora_do_prazo', $r);
    $r = pedido('POST', "marcacoes/$b/remarcar", ['token' => $tokB, 'barbeiro_id' => $s['barbeiro_id'], 'data' => $s['data'], 'hora' => $horas[3]]);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'fora_do_prazo', 'remarcar a menos de 24 h 409 fora_do_prazo', $r);

    moverInicio($id, 24 * 3600 + 120);
    $r = pedido('POST', "marcacoes/$id/cancelar", ['token' => $tok]);
    verifica($r['status'] === 200 && ($r['json']['estado'] ?? '') === 'cancelada', 'cancelar a mais de 24 h 200 cancelada', $r);
    verifica(str_contains(notasMarcacao($id), 'Cancelada pelo cliente na app'), 'notas com o rasto do cancelamento');
    $r = pedido('POST', "marcacoes/$id/cancelar", ['token' => $tok]);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'estado_invalido', 'cancelar de novo 409 estado_invalido', $r);
    // Token antigo (apps já instaladas) é aceite no cancelar: chega à regra do estado (409) e não dá 404
    $r = pedido('POST', "marcacoes/$id/cancelar", ['token' => (string)($GLOBALS['token_antigo'] ?? '')]);
    verifica($r['status'] === 409 && ($r['json']['erro']['codigo'] ?? '') === 'estado_invalido', 'cancelar com token antigo é aceite (409, não 404)', $r);
    // JWT válido mas de outra marcação → 404
    $r = pedido('POST', "marcacoes/$b/cancelar", ['token' => $tok]);
    verifica($r['status'] === 404, 'JWT de outra marcação 404', $r);
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
    if (!empty($GLOBALS['antecedencia_mexida'])) antecedenciaTeste(null);
    $tels = array_filter([$GLOBALS['tel_digitos'] ?? null, $GLOBALS['tel_outro'] ?? null, $GLOBALS['tel_alt'] ?? null]);
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

/* ===================== Revisão final #2: segredo ===================== */
/* ===================== Requisitos web: JWT (HS256) ===================== */
function b64url(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

function teste_jwt(): void {
    $f = __DIR__ . '/../../../config/jwt.php';
    if (!is_file($f)) { verifica(false, 'config/jwt.php existe'); return; }
    require_once $f;
    $seg = str_repeat('ab', 32);
    $jwt = jwtCriar(['sub' => 7, 'cli' => 9, 'iat' => 1700000000], $seg);
    verifica(count(explode('.', $jwt)) === 3, 'JWT tem 3 partes');
    verifica(jwtValidar($jwt, $seg) === ['sub' => 7, 'cli' => 9, 'iat' => 1700000000], 'criar e validar dá ida e volta');
    [$h, $p, $s] = explode('.', $jwt);
    verifica(json_decode(base64_decode(strtr($h, '-_', '+/')), true) === ['alg' => 'HS256', 'typ' => 'JWT'], 'cabeçalho HS256/JWT');

    // Review Focus 2: tokens forjados
    $semAlg = b64url('{"alg":"none","typ":"JWT"}') . '.' . $p . '.';
    verifica(jwtValidar($semAlg, $seg) === null, 'alg none recusado');
    $hs512 = b64url('{"alg":"HS512","typ":"JWT"}') . '.' . $p;
    $hs512 .= '.' . b64url(hash_hmac('sha512', $hs512, $seg, true));
    verifica(jwtValidar($hs512, $seg) === null, 'alg HS512 recusado');
    verifica(jwtValidar(jwtCriar(['sub' => 7], str_repeat('cd', 32)), $seg) === null, 'assinatura com outro segredo recusada');
    $alterado = $h . '.' . b64url('{"sub":8,"cli":9,"iat":1700000000}') . '.' . $s;
    verifica(jwtValidar($alterado, $seg) === null, 'payload alterado recusado');
    foreach (['', 'a.b', 'a.b.c.d', '!!!.###.$$$', $h . '.' . $p . '.'] as $mau) {
        verifica(jwtValidar($mau, $seg) === null, 'JWT mal formado recusado: "' . substr($mau, 0, 12) . '"');
    }
}

function teste_segredo(): void {
    require_once __DIR__ . '/../_lib.php';
    verifica(function_exists('segredoValido') && segredoValido(str_repeat('ab', 32)), 'segredo de 64 hex aceite');
    verifica(function_exists('segredoValido') && !segredoValido('GERAR_COM_bin2hex(random_bytes(32))'), 'placeholder do example rejeitado');
    verifica(function_exists('segredoValido') && !segredoValido(str_repeat('a', 63)), 'segredo de 63 rejeitado');
    verifica(function_exists('segredoValido') && !segredoValido(''), 'segredo vazio rejeitado');
}

/* ===================== Código no ecrã quando o email falha (UA sem acesso externo) ===================== */
function teste_politica_codigo(): void {
    require_once __DIR__ . '/../_lib.php';
    $ok = function_exists('deveDevolverCodigo');
    verifica($ok && deveDevolverCodigo(['api_dev_devolver_codigo' => true], true), 'modo dev devolve sempre');
    verifica($ok && deveDevolverCodigo(['api_codigo_se_email_falhar' => true], false), 'email falhou + opção ligada devolve');
    verifica($ok && !deveDevolverCodigo(['api_codigo_se_email_falhar' => true], true), 'email enviado nunca devolve (sem dev)');
    verifica($ok && !deveDevolverCodigo([], false), 'sem opções nunca devolve');
    verifica($ok && !deveDevolverCodigo(['api_codigo_se_email_falhar' => 'sim'], false), 'só aceita true literal');
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
$leitura = ['teste_jwt', 'teste_segredo', 'teste_politica_codigo','teste_barbearia','teste_catalogo', 'teste_disponibilidade', 'teste_sempre_json'];
$escrita = ['teste_criar_marcacao', 'teste_verificar', 'teste_listar', 'teste_alterar'];

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
