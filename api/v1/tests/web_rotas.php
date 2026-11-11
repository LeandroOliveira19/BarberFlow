<?php
declare(strict_types=1);

/**
 * Testes da aplicação web modular (gestao/index.php e index.php público).
 * Só local. Uso: php api/v1/tests/web_rotas.php
 */

require __DIR__ . '/_sessao.php';
require_once __DIR__ . '/../../../config/db.php';

$BASE = 'http://127.0.0.1:8080/projeto/projeto/';
$FALHAS = 0; $TOTAL = 0;

function ok(bool $c, string $t, ?array $r = null): void {
    global $FALHAS, $TOTAL;
    $TOTAL++;
    if ($c) { echo "OK      $t\n"; return; }
    $FALHAS++;
    $extra = $r ? "  [{$r['status']}" . ($r['local'] ? " -> {$r['local']}" : '') . '] ' . substr(strip_tags($r['html']), 0, 160) : '';
    echo "FALHOU  $t$extra\n";
}

/* ===================== Task 1: helpers ===================== */
function teste_modular_helpers(): void {
    $ficheiro = __DIR__ . '/../../../config/modular.php';
    ok(is_file($ficheiro), 'config/modular.php existe');
    if (!is_file($ficheiro)) return;
    require_once $ficheiro;
    $_GET = ['m' => '../x', 'a' => 'Agenda', 'b' => ''];
    ok(lerParametro('m', 'dashboard') === 'dashboard', 'lerParametro recusa ../');
    ok(lerParametro('a', 'ver') === 'ver', 'lerParametro recusa maiúsculas');
    ok(lerParametro('b', 'ver') === 'ver', 'lerParametro: vazio → omissão');
    ok(lerParametro('nao_existe', 'ver') === 'ver', 'lerParametro: ausente → omissão');
    $_GET = ['m' => 'agenda'];
    ok(lerParametro('m', 'dashboard') === 'agenda', 'lerParametro aceita [a-z_]');
    $p = ['agenda' => ['listar', 'estado']];
    ok(validarModulo('agenda', 'listar', $p), 'validarModulo: ação permitida');
    ok(!validarModulo('agenda', 'apagar', $p), 'validarModulo: ação não permitida');
    ok(!validarModulo('x', 'y', $p), 'validarModulo: módulo inexistente');
    ok(urlModulo('servicos', 'listar', ['id' => 3]) === 'index.php?m=servicos&a=listar&id=3', 'urlModulo');
}

/* ===================== Task 2: gestao/index.php ===================== */
function teste_gestao_base(): void {
    global $BASE;
    $anon = new Sessao($BASE);
    $r = $anon->get('gestao/index.php?m=agenda&a=listar');
    ok($r['status'] === 302 && str_contains($r['local'], 'login.php'), 'anónimo → login.php', $r);

    $g = new Sessao($BASE);
    ok($g->login(TESTE_GESTOR), 'login do gestor de teste');
    $r = $g->get('gestao/index.php');
    ok($r['status'] === 200 && str_contains($r['html'], 'Dashboard') && semErrosPhp($r['html']), 'gestor: dashboard 200 sem erros PHP', $r);
    ok(str_contains($r['html'], 'class="sidebar"') && str_contains($r['html'], '</html>'), 'estrutura (menu + fecho) desenhada pelo index', $r);
    ok(substr_count($r['html'], '<html') === 1, 'só um <html> (o módulo não desenha a estrutura)', $r);

    // Review Focus 1: parâmetros maliciosos nunca incluem fora de mod/
    foreach (['?m=../../config&a=db', '?m=dashboard&a=../../../config/db', '?m%5B%5D=x', '?m=DASHBOARD&a=ver', '?m=nao_existe&a=ver'] as $q) {
        $r = $g->get('gestao/index.php' . $q);
        ok($r['status'] === 200 && str_contains($r['html'], 'Dashboard') && semErrosPhp($r['html']), "parâmetros inválidos $q → dashboard", $r);
    }
    $r = $g->get('gestao/index.php?m=nao_existe&a=ver');
    ok(str_contains($r['html'], 'Página não encontrada'), 'módulo inválido mostra aviso', $r);

    // Acesso direto aos ficheiros incluídos
    foreach (['gestao/mod/dashboard/ver.php', 'gestao/layout/cabecalho.php', 'gestao/layout/menu.php', 'gestao/layout/rodape.php'] as $f) {
        $r = $anon->get($f);
        ok($r['status'] === 302 && str_contains($r['local'], 'index.php'), "acesso direto bloqueado: $f", $r);
    }
}

/* ===================== Execução ===================== */
try {
    criarUtilizadoresTeste($pdo);
    foreach (['teste_modular_helpers', 'teste_gestao_base', 'teste_agenda_perfil', 'teste_modulos_gestor',
              'teste_stock_pedidos', 'teste_publico'] as $t) {
        if (function_exists($t)) { echo "--- $t\n"; $t(); }
    }
} finally {
    if (function_exists('limparDadosTeste')) limparDadosTeste($pdo);
    apagarUtilizadoresTeste($pdo);
}
echo "\n$TOTAL testes, $FALHAS falhas\n";
exit($FALHAS > 0 ? 1 : 0);
