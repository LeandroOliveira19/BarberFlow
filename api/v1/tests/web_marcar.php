<?php
declare(strict_types=1);

/**
 * Teste do formulário do SITE (publico/marcar.php): um cliente não pode fazer duas marcações no mesmo dia.
 * Só local (usa a BD para limpar). Uso: php api/v1/tests/web_marcar.php
 */

$BASE = 'http://127.0.0.1:8080/projeto/projeto';
$jar  = tempnam(sys_get_temp_dir(), 'cj');
$tel  = '9140001' . random_int(10, 99);

function http(string $url, ?array $post = null): string {
    global $jar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_FOLLOWLOCATION => false]);
    if ($post !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post)); }
    $r = (string)curl_exec($ch);
    $GLOBALS['ULTIMO_STATUS'] = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $r;
}

/** Abre o passo 2 do formulário (para obter o CSRF) e submete uma marcação. */
function marcarNoSite(int $sv, int $bb, string $data, string $hora, string $tel, string $paraNome = ''): array {
    global $BASE;
    $pagina = http("$BASE/publico/marcar.php?slug=rlbarber&servico=$sv&barbeiro=$bb&data=$data");
    preg_match('/name="csrf" value="([0-9a-f]+)"/', $pagina, $m);
    $html = http("$BASE/publico/marcar.php?slug=rlbarber", [
        'csrf' => $m[1] ?? '', 'slug' => 'rlbarber', 'servico' => $sv, 'barbeiro' => $bb, 'data' => $data, 'hora' => $hora,
        'nome' => 'Teste Site', 'telemovel' => $tel, 'email' => 'site@example.com', 'para_nome' => $paraNome,
    ]);
    return [$GLOBALS['ULTIMO_STATUS'], $html];
}

// Escolhe um dia com pelo menos 2 horários livres afastados (API local)
$api = "$BASE/api/v1/index.php";
$sv = 5; $bb = 2; $data = null; $horas = [];
for ($d = 1; $d <= 14 && !$data; $d++) {
    $dia = (new DateTime('today'))->modify("+$d days")->format('Y-m-d');
    $j = json_decode(http("$api?r=barbearias/rlbarber/disponibilidade&servico_id=$sv&barbeiro_id=$bb&data=$dia"), true);
    $s = $j['slots'] ?? [];
    if (count($s) >= 10) { $data = $dia; $horas = [$s[0], $s[count($s) - 1]]; }
}

$falhas = 0;
$ok = function (bool $c, string $t) use (&$falhas) { echo ($c ? "OK      " : "FALHOU  ") . "$t\n"; if (!$c) $falhas++; };

try {
    $ok($data !== null, 'há um dia com horários livres');
    [$st1] = marcarNoSite($sv, $bb, (string)$data, $horas[0], $tel);
    $ok($st1 === 302, '1.ª marcação no site redireciona para verificar.php');

    @unlink($jar); $jar = tempnam(sys_get_temp_dir(), 'cj');   // sessão nova, como outro separador
    [$st2, $html2] = marcarNoSite($sv, $bb, (string)$data, $horas[1], $tel);
    $ok($st2 === 200 && str_contains($html2, 'Já tens uma marcação para este dia'), '2.ª marcação no mesmo dia é recusada com a mensagem');

    // O pai marca para o filho no mesmo dia, com o mesmo telemóvel → aceite e guardado em para_nome
    @unlink($jar); $jar = tempnam(sys_get_temp_dir(), 'cj');
    [$st3] = marcarNoSite($sv, $bb, (string)$data, $horas[1], $tel, 'Tiago');
    $ok($st3 === 302, 'marcação para outra pessoa no mesmo dia é aceite');
    require_once __DIR__ . '/../../../config/db.php';
    $q = $pdo->prepare("SELECT COUNT(*) FROM marcacoes m JOIN clientes c ON c.id = m.cliente_id WHERE c.telemovel = ? AND m.para_nome = 'Tiago'");
    $q->execute([$tel]);
    $ok((int)$q->fetchColumn() === 1, 'para_nome guardado na marcação');
    $pagina = http("$BASE/publico/marcar.php?slug=rlbarber&servico=$sv&barbeiro=$bb&data=$data");
    $ok(str_contains($pagina, 'name="para_nome"'), 'formulário tem o campo "Para quem é a marcação?"');
} finally {
    require_once __DIR__ . '/../../../config/db.php';
    $pdo->prepare("DELETE v FROM verificacoes v JOIN marcacoes m ON m.id = v.marcacao_id JOIN clientes c ON c.id = m.cliente_id WHERE c.barbearia_id = 1 AND c.telemovel = ?")->execute([$tel]);
    $pdo->prepare("DELETE m FROM marcacoes m JOIN clientes c ON c.id = m.cliente_id WHERE c.barbearia_id = 1 AND c.telemovel = ?")->execute([$tel]);
    $pdo->prepare("DELETE FROM clientes WHERE barbearia_id = 1 AND telemovel = ?")->execute([$tel]);
    @unlink($jar);
}
echo "\n$falhas falhas\n";
exit($falhas > 0 ? 1 : 0);
