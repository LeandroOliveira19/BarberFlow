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

/* ===================== Task 3: agenda e perfil ===================== */
$GLOBALS['LIMPEZA'] = [];

/** Marcação de teste (amanhã 10:00) para o barbeiro de teste. Devolve o id. */
function criarMarcacaoTeste(PDO $pdo, string $estado = 'confirmada'): int {
    $barb = (int)$pdo->query("SELECT id FROM utilizadores WHERE email = '" . TESTE_BARBEIRO . "'")->fetchColumn();
    $pdo->prepare("INSERT INTO clientes (barbearia_id, nome, telemovel, email) VALUES (1, 'Cliente Rotas', '919990001', 'rotas@example.com')
                   ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)")->execute();
    $cli = (int)$pdo->lastInsertId();
    $sv  = (int)$pdo->query("SELECT id FROM servicos WHERE barbearia_id = 1 AND ativo = 1 LIMIT 1")->fetchColumn();
    $ini = (new DateTime('tomorrow'))->setTime(10, 0);
    $pdo->prepare("INSERT INTO marcacoes (barbearia_id, cliente_id, barbeiro_id, servico_id, inicio, fim, estado) VALUES (1, ?, ?, ?, ?, ?, ?)")
        ->execute([$cli, $barb, $sv, $ini->format('Y-m-d H:i:s'), (clone $ini)->modify('+30 minutes')->format('Y-m-d H:i:s'), $estado]);
    return (int)$pdo->lastInsertId();
}

function estadoMarcacao(PDO $pdo, int $id): string {
    $st = $pdo->prepare("SELECT estado FROM marcacoes WHERE id = ?");
    $st->execute([$id]);
    return (string)$st->fetchColumn();
}

function limparDadosTeste(PDO $pdo): void {
    $pdo->exec("DELETE m FROM marcacoes m JOIN clientes c ON c.id = m.cliente_id WHERE c.telemovel = '919990001'");
    $pdo->exec("DELETE FROM clientes WHERE telemovel = '919990001'");
    foreach ($GLOBALS['LIMPEZA'] as $sql) $pdo->exec($sql);
}

function teste_agenda_perfil(): void {
    global $BASE, $pdo;
    $g = new Sessao($BASE); $g->login(TESTE_GESTOR);
    $b = new Sessao($BASE); $b->login(TESTE_BARBEIRO);
    $dia = (new DateTime('tomorrow'))->format('Y-m-d');

    foreach (['gestor' => $g, 'barbeiro' => $b] as $quem => $s) {
        $r = $s->get('gestao/index.php?m=agenda&a=listar');
        ok($r['status'] === 200 && str_contains($r['html'], 'Agenda') && semErrosPhp($r['html']), "$quem: agenda/listar 200", $r);
        $r = $s->get('gestao/index.php?m=perfil&a=editar');
        ok($r['status'] === 200 && str_contains($r['html'], 'O Meu Perfil') && semErrosPhp($r['html']), "$quem: perfil/editar 200", $r);
    }

    $id = criarMarcacaoTeste($pdo);
    $r = $g->get("gestao/index.php?m=agenda&a=listar&data=$dia");
    ok(str_contains($r['html'], 'Cliente Rotas'), 'marcação de teste aparece na agenda do dia', $r);
    $csrf = Sessao::csrf($r['html']);
    $r = $g->post('gestao/index.php?m=agenda&a=estado', ['id' => $id, 'estado' => 'concluida']);
    ok(estadoMarcacao($pdo, $id) === 'confirmada', 'POST sem CSRF não muda o estado', $r);
    $r = $g->post("gestao/index.php?m=agenda&a=estado&data=$dia", ['id' => $id, 'estado' => 'concluida', 'csrf' => $csrf]);
    ok(estadoMarcacao($pdo, $id) === 'concluida', 'POST com CSRF muda o estado', $r);
    ok($r['status'] === 302 && str_contains($r['local'], 'm=agenda') && str_contains($r['local'], 'a=listar') && str_contains($r['local'], "data=$dia"),
       'depois do POST volta à agenda com os mesmos filtros', $r);
    $r = $g->get('gestao/index.php?m=agenda&a=estado');
    ok($r['status'] === 302 && str_contains($r['local'], 'a=listar'), 'GET a uma ação de escrita volta à listagem', $r);

    $r = $g->get('gestao/agenda.php?vista=semana');
    ok($r['status'] === 302 && str_contains($r['local'], 'index.php?m=agenda&a=listar') && str_contains($r['local'], 'vista=semana'), 'agenda.php antigo redireciona', $r);
    $r = $g->get('gestao/perfil.php?force=1');
    ok($r['status'] === 302 && str_contains($r['local'], 'm=perfil') && str_contains($r['local'], 'force=1'), 'perfil.php antigo redireciona', $r);

    // Review Focus 3: obrigação de alterar a password não se contorna com ?m=
    $pdo->exec("UPDATE utilizadores SET precisa_alterar_password = 1 WHERE email = '" . TESTE_BARBEIRO . "'");
    $f = new Sessao($BASE); $f->login(TESTE_BARBEIRO);
    $r = $f->get('gestao/index.php?m=agenda&a=listar');
    ok($r['status'] === 302 && str_contains($r['local'], 'm=perfil') && str_contains($r['local'], 'force=1'), 'com password por alterar, agenda → perfil', $r);
    $r = $f->get('gestao/index.php?m=perfil&a=editar&force=1');
    ok($r['status'] === 200 && str_contains($r['html'], 'Obrigatório'), 'perfil mostra a alteração obrigatória', $r);
    $r = $f->post('gestao/index.php?m=perfil&a=alterar_password', [
        'nova_password' => TESTE_PASSWORD, 'confirmar_password' => TESTE_PASSWORD, 'csrf' => Sessao::csrf($r['html'])]);
    $flag = (int)$pdo->query("SELECT precisa_alterar_password FROM utilizadores WHERE email = '" . TESTE_BARBEIRO . "'")->fetchColumn();
    ok($flag === 0, 'alterar password limpa a obrigação', $r);
    $r = $f->get('gestao/index.php?m=agenda&a=listar');
    ok($r['status'] === 200, 'depois de alterar, a agenda abre', $r);

    // Perfil: guardar o nome (POST → flash → editar)
    $r = $g->get('gestao/index.php?m=perfil&a=editar');
    $r = $g->post('gestao/index.php?m=perfil&a=guardar', ['nome' => 'Gestor Teste 2', 'csrf' => Sessao::csrf($r['html'])]);
    ok($r['status'] === 302 && str_contains($r['local'], 'a=editar'), 'perfil/guardar redireciona para editar', $r);
    $r = $g->get('gestao/index.php?m=perfil&a=editar');
    ok(str_contains($r['html'], 'Perfil atualizado com sucesso') && str_contains($r['html'], 'Gestor Teste 2'), 'mensagem de sucesso e nome novo', $r);
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
