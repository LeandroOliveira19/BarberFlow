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
        ok(stripos(ltrim($r['html']), '<!DOCTYPE html>') === 0, "$quem: página da gestão começa com <!DOCTYPE html>", $r);
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
    $r = $f->get('gestao/index.php?m=perfil&a=xyz');
    ok($r['status'] === 302 && str_contains($r['local'], 'm=perfil') && str_contains($r['local'], 'force=1'), 'com password por alterar, perfil/ação inválida → perfil (não o dashboard)', $r);
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

/* ===================== Task 4: módulos só de gestor ===================== */
function servicoTesteExiste(PDO $pdo, string $nome): bool {
    $st = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE barbearia_id = 1 AND nome = ? AND ativo = 1");
    $st->execute([$nome]);
    return (int)$st->fetchColumn() > 0;
}

function teste_modulos_gestor(): void {
    global $BASE, $pdo;
    $GLOBALS['LIMPEZA'][] = "DELETE FROM servicos WHERE barbearia_id = 1 AND nome LIKE 'Serviço Rotas%'";
    $GLOBALS['LIMPEZA'][] = "DELETE FROM fornecedores WHERE barbearia_id = 1 AND nome = 'Fornecedor Rotas'";
    $g = new Sessao($BASE); $g->login(TESTE_GESTOR);
    $b = new Sessao($BASE); $b->login(TESTE_BARBEIRO);

    $leituras = ['servicos' => 'listar', 'barbeiros' => 'listar', 'bancadas' => 'listar', 'fornecedores' => 'listar', 'definicoes' => 'editar'];
    $titulos  = ['servicos' => 'Serviços', 'barbeiros' => 'Barbeiros', 'bancadas' => 'Bancadas', 'fornecedores' => 'Fornecedores', 'definicoes' => 'Definições'];
    foreach ($leituras as $m => $a) {
        $r = $g->get("gestao/index.php?m=$m&a=$a");
        ok($r['status'] === 200 && str_contains($r['html'], $titulos[$m]) && semErrosPhp($r['html']) && !str_contains($r['html'], 'Página não encontrada'),
           "gestor: $m/$a 200", $r);
        $r = $b->get("gestao/index.php?m=$m&a=$a");
        ok($r['status'] === 200 && str_contains($r['html'], 'Página não encontrada'), "barbeiro: $m é só de gestor", $r);
        $r = $g->get("gestao/$m.php");
        ok($r['status'] === 302 && str_contains($r['local'], "m=$m"), "$m.php antigo redireciona", $r);
    }

    // Criar → editar → apagar um serviço pela gestão (Review Focus 4: os 302 provam que não há saída antes do header)
    $r = $g->get('gestao/index.php?m=servicos&a=listar');
    $csrf = Sessao::csrf($r['html']);
    $r = $g->post('gestao/index.php?m=servicos&a=guardar', ['nome' => 'Serviço Rotas', 'duracao_minutos' => 30, 'preco' => '9,50', 'descricao' => '', 'csrf' => $csrf]);
    ok($r['status'] === 302 && str_contains($r['local'], 'a=listar') && servicoTesteExiste($pdo, 'Serviço Rotas'), 'gestor cria serviço', $r);
    $r = $g->get('gestao/index.php?m=servicos&a=listar');
    ok(str_contains($r['html'], 'Serviço adicionado com sucesso'), 'mensagem de sucesso ao criar', $r);
    $id = (int)$pdo->query("SELECT id FROM servicos WHERE barbearia_id = 1 AND nome = 'Serviço Rotas'")->fetchColumn();
    $r = $g->get("gestao/index.php?m=servicos&a=listar&editar=$id");
    ok(str_contains($r['html'], 'value="Serviço Rotas"'), 'link Editar carrega o serviço no formulário', $r);
    $r = $g->post('gestao/index.php?m=servicos&a=guardar', ['id' => $id, 'nome' => 'Serviço Rotas 2', 'duracao_minutos' => 45, 'preco' => '10', 'csrf' => $csrf]);
    ok(servicoTesteExiste($pdo, 'Serviço Rotas 2'), 'gestor edita serviço', $r);
    $r = $g->post('gestao/index.php?m=servicos&a=guardar', ['nome' => '', 'preco' => '0', 'csrf' => $csrf]);
    $r = $g->get('gestao/index.php?m=servicos&a=listar');
    ok(str_contains($r['html'], 'O nome e o preço são obrigatórios'), 'erro de validação chega por mensagem', $r);

    // Review Focus 2: barbeiro com CSRF válido a chamar uma ação de gestor
    $rb = $b->get('gestao/index.php?m=agenda&a=listar');
    $r = $b->post('gestao/index.php?m=servicos&a=apagar', ['id' => $id, 'csrf' => Sessao::csrf($rb['html'])]);
    ok(servicoTesteExiste($pdo, 'Serviço Rotas 2'), 'barbeiro NÃO consegue apagar serviço (POST direto)', $r);

    $r = $g->post('gestao/index.php?m=servicos&a=apagar', ['id' => $id, 'csrf' => $csrf]);
    ok(!servicoTesteExiste($pdo, 'Serviço Rotas 2'), 'gestor desativa serviço', $r);

    $r = $g->post('gestao/index.php?m=fornecedores&a=guardar', ['nome' => 'Fornecedor Rotas', 'csrf' => $csrf]);
    $n = (int)$pdo->query("SELECT COUNT(*) FROM fornecedores WHERE barbearia_id = 1 AND nome = 'Fornecedor Rotas'")->fetchColumn();
    ok($n === 1 && $r['status'] === 302, 'gestor cria fornecedor', $r);
}

/* ===================== Task 5: stock e pedidos ===================== */
function teste_stock_pedidos(): void {
    global $BASE, $pdo;
    // Material e bancada de teste para o barbeiro de teste (bancada_materiais tem PK composta, sem id)
    $barb = (int)$pdo->query("SELECT id FROM utilizadores WHERE email = '" . TESTE_BARBEIRO . "'")->fetchColumn();
    $pdo->prepare("INSERT INTO materiais (barbearia_id, nome, unidade, stock_geral, stock_minimo, tipo) VALUES (1, 'Material Rotas', 'un', 50, 5, 'material')")->execute();
    $mat = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO bancadas (barbearia_id, nome, barbeiro_id) VALUES (1, 'Bancada Rotas', ?)")->execute([$barb]);
    $banc = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO bancada_materiais (bancada_id, material_id, quantidade, quantidade_minima) VALUES (?, ?, 2, 1)")->execute([$banc, $mat]);
    $GLOBALS['LIMPEZA'][] = "DELETE FROM pedidos_reposicao WHERE material_id = $mat";
    $GLOBALS['LIMPEZA'][] = "DELETE FROM movimentos_stock WHERE material_id = $mat";
    $GLOBALS['LIMPEZA'][] = "DELETE FROM bancada_materiais WHERE material_id = $mat";
    $GLOBALS['LIMPEZA'][] = "DELETE FROM bancadas WHERE id = $banc";
    $GLOBALS['LIMPEZA'][] = "DELETE FROM materiais WHERE id = $mat";

    $g = new Sessao($BASE); $g->login(TESTE_GESTOR);
    $b = new Sessao($BASE); $b->login(TESTE_BARBEIRO);

    $r = $g->get('gestao/index.php?m=stock&a=listar');
    ok($r['status'] === 200 && str_contains($r['html'], 'Stock Geral') && semErrosPhp($r['html']), 'gestor: stock/listar 200', $r);
    $r = $g->get('gestao/index.php?m=pedidos&a=listar');
    ok($r['status'] === 200 && str_contains($r['html'], 'Pedidos') && semErrosPhp($r['html']), 'gestor: pedidos/listar 200', $r);
    $r = $b->get('gestao/index.php?m=stock&a=listar');
    ok($r['status'] === 200 && str_contains($r['html'], 'Minha Bancada') && str_contains($r['html'], 'Material Rotas') && semErrosPhp($r['html']), 'barbeiro: a sua bancada', $r);
    $csrfB = Sessao::csrf($r['html']);
    $r = $b->get('gestao/index.php?m=pedidos&a=listar');
    ok(str_contains($r['html'], 'Página não encontrada'), 'barbeiro: pedidos é só de gestor', $r);

    // Barbeiro não consegue transferir stock (ação de gestor) mesmo com CSRF válido
    $antes = (float)$pdo->query("SELECT stock_geral FROM materiais WHERE id = $mat")->fetchColumn();
    $r = $b->post('gestao/index.php?m=stock&a=transferir', ['material_id' => $mat, 'bancada_id' => $banc, 'quantidade' => 5, 'csrf' => $csrfB]);
    $depois = (float)$pdo->query("SELECT stock_geral FROM materiais WHERE id = $mat")->fetchColumn();
    ok($antes === $depois, 'barbeiro NÃO transfere stock', $r);

    // Barbeiro pede reposição → gestor rejeita
    $r = $b->post('gestao/index.php?m=stock&a=pedir_reposicao', ['material_id' => $mat, 'quantidade' => 3, 'csrf' => $csrfB]);
    $pedido = $pdo->query("SELECT id, estado FROM pedidos_reposicao WHERE material_id = $mat ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    ok($r['status'] === 302 && ($pedido['estado'] ?? '') === 'pendente', 'barbeiro pede reposição (pendente)', $r);
    $r = $b->get('gestao/index.php?m=stock&a=listar');
    ok(str_contains($r['html'], 'Pedido de reposição enviado'), 'mensagem do pedido aparece', $r);

    $rg = $g->get('gestao/index.php?m=pedidos&a=listar');
    $r = $g->post('gestao/index.php?m=pedidos&a=rejeitar', ['id' => (int)($pedido['id'] ?? 0), 'csrf' => Sessao::csrf($rg['html'])]);
    $estado = $pdo->query("SELECT estado FROM pedidos_reposicao WHERE id = " . (int)($pedido['id'] ?? 0))->fetchColumn();
    ok($estado === 'rejeitado' && $r['status'] === 302, 'gestor rejeita o pedido', $r);

    // Gestor transfere stock geral → bancada
    $r = $g->post('gestao/index.php?m=stock&a=transferir', ['material_id' => $mat, 'bancada_id' => $banc, 'quantidade' => 5, 'csrf' => Sessao::csrf($rg['html'])]);
    $geral = (float)$pdo->query("SELECT stock_geral FROM materiais WHERE id = $mat")->fetchColumn();
    $naBancada = (float)$pdo->query("SELECT quantidade FROM bancada_materiais WHERE bancada_id = $banc AND material_id = $mat")->fetchColumn();
    ok($geral === 45.0 && $naBancada === 7.0, 'gestor transfere 5 unidades (50→45, bancada 2→7)', $r);

    $r = $g->get("gestao/index.php?m=stock&a=listar&transferir=$mat");
    ok(str_contains($r['html'], 'Material Rotas') && semErrosPhp($r['html']), 'link Transferir abre o formulário', $r);
    foreach (['stock', 'pedidos'] as $m) {
        $r = $g->get("gestao/$m.php");
        ok($r['status'] === 302 && str_contains($r['local'], "m=$m"), "$m.php antigo redireciona", $r);
    }
}

/* ===================== Task 6: parte pública ===================== */
function teste_publico(): void {
    global $BASE;
    $s = new Sessao($BASE);
    foreach (['index.php?slug=rlbarber', 'index.php', 'index.php?m=site&a=ver&slug=rlbarber'] as $u) {
        $r = $s->get($u);
        ok($r['status'] === 200 && str_contains($r['html'], 'RLBarber') && substr_count($r['html'], '<html') === 1 && semErrosPhp($r['html']),
           "site: $u", $r);
    }
    $r = $s->get('index.php?slug=nao-existe');
    ok($r['status'] === 404 && str_contains($r['html'], 'Barbearia não encontrada'), 'slug inexistente → 404', $r);
    foreach (['?m=../config&a=db&slug=rlbarber', '?m=site&a=../../config/db&slug=rlbarber', '?m=gestao&a=index&slug=rlbarber'] as $q) {
        $r = $s->get("index.php$q");
        ok($r['status'] === 200 && str_contains($r['html'], 'RLBarber') && semErrosPhp($r['html']), "público: parâmetros inválidos $q → site", $r);
    }
    $r = $s->get('index.php?m=marcar&slug=rlbarber');
    ok($r['status'] === 200 && str_contains($r['html'], 'name="servico"') && substr_count($r['html'], '<html') === 1 && semErrosPhp($r['html']),
       'marcar/formulario 200', $r);
    $r = $s->get('index.php?m=verificar&slug=rlbarber');
    ok($r['status'] === 302, 'verificar sem marcação em curso → redireciona', $r);
    foreach (['mod/marcar/formulario.php', 'mod/verificar/formulario.php', 'mod/site/ver.php', 'mod/site/temas/classico.php'] as $f) {
        $r = $s->get($f);
        ok($r['status'] === 302 && str_contains($r['local'], 'index.php'), "acesso direto bloqueado: $f", $r);
    }
    $r = $s->get('publico/index.php?slug=rlbarber');
    ok($r['status'] === 302 && str_contains($r['local'], 'index.php?m=site') && str_contains($r['local'], 'slug=rlbarber'), 'publico/index.php antigo redireciona', $r);
    $r = $s->get('publico/marcar.php?slug=rlbarber&servico=5');
    ok($r['status'] === 302 && str_contains($r['local'], 'm=marcar') && str_contains($r['local'], 'servico=5'), 'publico/marcar.php antigo redireciona', $r);
    $r = $s->get('publico/verificar.php');
    ok($r['status'] === 302 && str_contains($r['local'], 'm=verificar'), 'publico/verificar.php antigo redireciona', $r);
    $r = $s->post('index.php?m=marcar&slug=rlbarber', ['servico' => 5, 'nome' => 'Cliente Sem Token']);
    ok(!str_contains($r['local'] ?? '', 'm=verificar'), 'POST público sem CSRF é recusado', $r);
    ok($r['status'] === 200 && str_contains($r['html'], 'A sessão expirou'), 'marcar sem CSRF mostra "A sessão expirou" (não redireciona)', $r);
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
