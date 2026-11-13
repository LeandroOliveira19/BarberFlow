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
    // A alteração obrigatória também passa pelo código enviado por email (em modo dev aparece no ecrã)
    $codigo = pedirCodigoSenha($f, Sessao::csrf($r['html']), true);
    $r = $f->post('gestao/index.php?m=perfil&a=alterar_password', [
        'codigo' => $codigo, 'nova_password' => TESTE_PASSWORD, 'confirmar_password' => TESTE_PASSWORD, 'csrf' => Sessao::csrf($r['html'])]);
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

    teste_definicoes_antecedencia($g);
}

/** Definições: campo da antecedência para cancelar/remarcar na app. */
function antecedenciaNaBd(PDO $pdo): ?string {
    $v = $pdo->query("SELECT valor FROM configuracoes WHERE barbearia_id = 1 AND chave = 'antecedencia_alteracao_horas'")->fetchColumn();
    return $v === false ? null : (string)$v;
}

function teste_definicoes_antecedencia(Sessao $g): void {
    global $pdo;
    $antes = antecedenciaNaBd($pdo);
    $r = $g->get('gestao/index.php?m=definicoes&a=editar');
    ok(str_contains($r['html'], 'name="antecedencia_alteracao_horas"') && str_contains($r['html'], 'Cancelar/remarcar na app até'),
       'Definições mostra o campo da antecedência', $r);

    // O guardar grava todos os campos: envia os valores atuais para não mexer no resto da configuração
    $cfg  = $pdo->query("SELECT chave, valor FROM configuracoes WHERE barbearia_id = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    $barb = $pdo->query("SELECT nome, morada FROM barbearias WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $form = ['nome' => $barb['nome'], 'morada' => $barb['morada'], 'csrf' => Sessao::csrf($r['html'])];
    foreach (['slogan', 'sobre', 'hora_abertura', 'hora_fecho', 'tema_cor', 'tema_fundo', 'tema_layout', 'tema_hero', 'tema_fonte',
              'link_instagram', 'link_facebook', 'galeria_img_1', 'galeria_img_2', 'galeria_img_3'] as $k) {
        if (isset($cfg[$k])) $form[$k] = $cfg[$k];
    }
    try {
        $r = $g->post('gestao/index.php?m=definicoes&a=guardar', $form + ['antecedencia_alteracao_horas' => '12']);
        ok($r['status'] === 302 && antecedenciaNaBd($pdo) === '12', 'guardar grava a antecedência (12)', $r);
        $r = $g->post('gestao/index.php?m=definicoes&a=guardar', $form + ['antecedencia_alteracao_horas' => '200']);
        $r = $g->get('gestao/index.php?m=definicoes&a=editar');
        ok(antecedenciaNaBd($pdo) === '12' && str_contains($r['html'], 'entre 0 e 168'), 'antecedência 200 é recusada com mensagem', $r);
        ok(str_contains($r['html'], 'name="antecedencia_alteracao_horas"') && str_contains($r['html'], 'value="12"'), 'campo mostra o valor gravado', $r);
    } finally {
        $pdo->prepare("DELETE FROM configuracoes WHERE barbearia_id = 1 AND chave = 'antecedencia_alteracao_horas'")->execute();
        if ($antes !== null) {
            $pdo->prepare("INSERT INTO configuracoes (barbearia_id, chave, valor) VALUES (1, 'antecedencia_alteracao_horas', ?)")->execute([$antes]);
        }
    }
}

/* ===================== Requisitos web: pesquisa na agenda ===================== */
function teste_pesquisa_agenda(): void {
    global $BASE, $pdo;
    criarMarcacaoTeste($pdo);   // "Cliente Rotas", 919990001, amanhã, barbeiro de teste
    // Mesmo nome noutra barbearia: nunca pode aparecer
    $bid2 = (int)$pdo->query("SELECT id FROM barbearias WHERE id <> 1 ORDER BY id LIMIT 1")->fetchColumn();
    $sv2  = (int)$pdo->query("SELECT id FROM servicos WHERE barbearia_id = $bid2 LIMIT 1")->fetchColumn();
    $bb2  = (int)$pdo->query("SELECT id FROM utilizadores WHERE barbearia_id = $bid2 LIMIT 1")->fetchColumn();
    if ($bid2 && $sv2 && $bb2) {
        $pdo->prepare("INSERT INTO clientes (barbearia_id, nome, telemovel, email) VALUES (?, 'Cliente Rotas Navalha', '919990002', 'rotas2@example.com')")->execute([$bid2]);
        $cli2 = (int)$pdo->lastInsertId();
        $ini = (new DateTime('tomorrow'))->setTime(11, 0)->format('Y-m-d H:i:s');
        $pdo->prepare("INSERT INTO marcacoes (barbearia_id, cliente_id, barbeiro_id, servico_id, inicio, fim, estado) VALUES (?, ?, ?, ?, ?, DATE_ADD(?, INTERVAL 30 MINUTE), 'confirmada')")
            ->execute([$bid2, $cli2, $bb2, $sv2, $ini, $ini]);
        $GLOBALS['LIMPEZA'][] = "DELETE FROM marcacoes WHERE cliente_id = $cli2";
        $GLOBALS['LIMPEZA'][] = "DELETE FROM clientes WHERE id = $cli2";
    }
    $g = new Sessao($BASE); $g->login(TESTE_GESTOR);
    $b = new Sessao($BASE); $b->login(TESTE_BARBEIRO);
    $url = fn(string $q) => 'gestao/index.php?m=agenda&a=listar&vista=proximas&q=' . rawurlencode($q);

    $r = $g->get($url('Rotas'));
    ok(str_contains($r['html'], 'Cliente Rotas') && !str_contains($r['html'], 'Navalha'), 'pesquisa pelo nome encontra e não mostra outra barbearia', $r);
    ok(str_contains($r['html'], 'name="q"') && str_contains($r['html'], 'value="Rotas"'), 'o campo de pesquisa mantém o texto', $r);
    $r = $g->get($url('99900'));
    ok(str_contains($r['html'], 'Cliente Rotas'), 'pesquisa por parte do telemóvel', $r);
    $r = $g->get($url('%'));
    ok(!str_contains($r['html'], 'Cliente Rotas') && semErrosPhp($r['html']), '"%" é texto, não um curinga', $r);
    $r = $g->get($url('Ninguem Com Este Nome'));
    ok(!str_contains($r['html'], 'Cliente Rotas'), 'pesquisa sem resultados', $r);
    $r = $b->get($url('Rotas'));
    ok(str_contains($r['html'], 'Cliente Rotas'), 'barbeiro encontra as suas marcações', $r);
}

/* ===================== Requisitos web: login robusto, cookie e 2FA ===================== */
function teste_login_seguro(): void {
    global $BASE, $pdo;
    require_once __DIR__ . '/../../../config/totp.php';
    $limpar = fn() => $pdo->exec("DELETE FROM tentativas_login WHERE email IN ('" . TESTE_GESTOR . "','" . TESTE_BARBEIRO . "')");
    $limpar();

    // CSRF obrigatório
    $s = new Sessao($BASE);
    $r = $s->post('gestao/login.php', ['email' => TESTE_GESTOR, 'password' => TESTE_PASSWORD]);
    ok($r['status'] === 200 && !str_contains($r['local'], 'index.php'), 'login sem CSRF não entra', $r);

    // Sessão regenerada no login
    $s = new Sessao($BASE);
    $s->get('gestao/login.php');
    $antes = $s->cookie('PHPSESSID');
    ok($s->login(TESTE_GESTOR) && $antes !== null && $s->cookie('PHPSESSID') !== $antes, 'login certo regenera o id da sessão');

    // Cookie "Lembrar o meu email" (só o email, HttpOnly)
    $s = new Sessao($BASE);
    $f = $s->get('gestao/login.php');
    ok(str_contains($f['html'], 'name="lembrar_email"'), 'login tem a opção "Lembrar o meu email"', $f);
    $s->post('gestao/login.php', ['email' => TESTE_GESTOR, 'password' => TESTE_PASSWORD, 'lembrar_email' => '1', 'csrf' => Sessao::csrf($f['html'])]);
    $linha = (string)$s->cookieLinha('bf_email');
    ok(str_starts_with($linha, '#HttpOnly_') && rawurldecode((string)$s->cookie('bf_email')) === TESTE_GESTOR, 'cookie bf_email HttpOnly com o email');
    $s->get('gestao/logout.php');
    $r = $s->get('gestao/login.php');
    ok(str_contains($r['html'], 'value="' . TESTE_GESTOR . '"'), 'o login seguinte já traz o email', $r);
    $s->post('gestao/login.php', ['email' => TESTE_GESTOR, 'password' => TESTE_PASSWORD, 'csrf' => Sessao::csrf($r['html'])]);
    ok($s->cookie('bf_email') === null, 'sem "Lembrar", o cookie é apagado');

    // Limite de tentativas: 5 falhas por email em 15 min
    $s = new Sessao($BASE);
    for ($i = 0; $i < 5; $i++) $s->login(TESTE_GESTOR, 'errada-' . $i);
    ok(!$s->login(TESTE_GESTOR) && str_contains($s->ultima['html'] ?? '', 'Demasiadas tentativas'), '6.º login (mesmo certo) bloqueado com mensagem', $s->ultima);
    $limpar();

    // 2FA (Review Focus 1): com a senha certa e sem o código não se entra em nada
    $segredo = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    $pdo->prepare("UPDATE utilizadores SET totp_segredo = ?, totp_ativo = 1 WHERE email = ?")->execute([$segredo, TESTE_BARBEIRO]);
    try {
        $s = new Sessao($BASE);
        $f = $s->get('gestao/login.php');
        $r = $s->post('gestao/login.php', ['email' => TESTE_BARBEIRO, 'password' => TESTE_PASSWORD, 'csrf' => Sessao::csrf($f['html'])]);
        ok($r['status'] === 200 && str_contains($r['html'], 'name="codigo_2fa"'), 'senha certa com 2FA pede o código da app', $r);
        $r = $s->get('gestao/index.php?m=agenda&a=listar');
        ok($r['status'] === 302 && str_contains($r['local'], 'login.php'), 'sem o código não abre a gestão', $r);
        for ($i = 0; $i < 5; $i++) {
            $f = $s->get('gestao/login.php');
            $r = $s->post('gestao/login.php', ['codigo_2fa' => '000000', 'csrf' => Sessao::csrf($f['html'])]);
        }
        ok(!str_contains($r['html'], 'name="codigo_2fa"') && str_contains($r['html'], 'name="password"'), '5 códigos errados voltam ao passo da senha', $r);
        $s = new Sessao($BASE);
        ok($s->login(TESTE_BARBEIRO, TESTE_PASSWORD, totpCodigo($segredo, time())), 'código certo da app entra', $s->ultima);
    } finally {
        $pdo->prepare("UPDATE utilizadores SET totp_segredo = NULL, totp_ativo = 0 WHERE email = ?")->execute([TESTE_BARBEIRO]);
        $limpar();
    }
}

/* ===================== Requisitos web: ativar/desativar 2FA no Perfil ===================== */
function totpAtivo(PDO $pdo, string $email): int {
    $st = $pdo->prepare("SELECT totp_ativo FROM utilizadores WHERE email = ?");
    $st->execute([$email]);
    return (int)$st->fetchColumn();
}

function teste_2fa_perfil(): void {
    global $BASE, $pdo;
    require_once __DIR__ . '/../../../config/totp.php';
    try {
        $g = new Sessao($BASE); $g->login(TESTE_GESTOR);
        $r = $g->get('gestao/index.php?m=perfil&a=editar');
        preg_match('/id="chave2fa"[^>]*>([A-Z2-7]{16,})</', $r['html'], $m);
        $chave = $m[1] ?? '';
        ok(str_contains($r['html'], 'Ativar 2FA') && $chave !== '' && str_contains($r['html'], 'assets/js/qrcode.js'), 'Perfil mostra "Ativar 2FA", a chave e o QR', $r);

        $r = $g->post('gestao/index.php?m=perfil&a=ativar_dois_fatores', ['codigo' => '000000', 'csrf' => Sessao::csrf($r['html'])]);
        ok(totpAtivo($pdo, TESTE_GESTOR) === 0, 'código errado não ativa o 2FA', $r);
        $r = $g->get('gestao/index.php?m=perfil&a=editar');
        $r = $g->post('gestao/index.php?m=perfil&a=ativar_dois_fatores', ['codigo' => totpCodigo($chave, time()), 'csrf' => Sessao::csrf($r['html'])]);
        ok(totpAtivo($pdo, TESTE_GESTOR) === 1 && $r['status'] === 302, 'código certo ativa o 2FA', $r);

        $s = new Sessao($BASE);
        ok(!$s->login(TESTE_GESTOR) && str_contains($s->ultima['html'] ?? '', 'name="codigo_2fa"'), 'o login seguinte pede o código da app', $s->ultima);
        ok($s->login(TESTE_GESTOR, TESTE_PASSWORD, totpCodigo($chave, time())), 'e entra com o código', $s->ultima);

        $r = $g->get('gestao/index.php?m=perfil&a=editar');
        ok(str_contains($r['html'], 'Desativar 2FA'), 'com 2FA ativo o Perfil mostra "Desativar 2FA"', $r);
        $csrf = Sessao::csrf($r['html']);
        $g->post('gestao/index.php?m=perfil&a=desativar_dois_fatores', ['password' => 'errada', 'codigo' => totpCodigo($chave, time()), 'csrf' => $csrf]);
        ok(totpAtivo($pdo, TESTE_GESTOR) === 1, 'desativar com a senha errada é recusado');
        $g->post('gestao/index.php?m=perfil&a=desativar_dois_fatores', ['password' => TESTE_PASSWORD, 'codigo' => totpCodigo($chave, time()), 'csrf' => $csrf]);
        ok(totpAtivo($pdo, TESTE_GESTOR) === 0, 'desativar com senha e código desliga o 2FA');
    } finally {
        $pdo->prepare("UPDATE utilizadores SET totp_segredo = NULL, totp_ativo = 0 WHERE email = ?")->execute([TESTE_GESTOR]);
    }
}

/* ===================== Requisitos web: mudar e recuperar a senha com código por email ===================== */
/** Pede o código no Perfil e lê-o do ecrã (modo dev: api_dev_devolver_codigo na BD local). */
function pedirCodigoSenha(Sessao $s, string $csrf, bool $forcado = false): string {
    $s->post('gestao/index.php?m=perfil&a=enviar_codigo', ['csrf' => $csrf]);
    $r = $s->get('gestao/index.php?m=perfil&a=editar' . ($forcado ? '&force=1' : ''));
    return preg_match('/Código: (\d{6})/', $r['html'], $m) ? $m[1] : '';
}

function senhaFunciona(string $email, string $senha): bool {
    global $BASE;
    return (new Sessao($BASE))->login($email, $senha);
}

function teste_senha_com_codigo(): void {
    global $BASE, $pdo;
    $repor = fn() => $pdo->prepare("UPDATE utilizadores SET password_hash = ? WHERE email = ?")->execute([password_hash(TESTE_PASSWORD, PASSWORD_DEFAULT), TESTE_GESTOR]);
    $limparTent = fn() => $pdo->exec("DELETE FROM tentativas_login WHERE email = '" . TESTE_GESTOR . "'");
    try {
        $g = new Sessao($BASE); $g->login(TESTE_GESTOR);
        $r = $g->get('gestao/index.php?m=perfil&a=editar');
        ok(str_contains($r['html'], 'Enviar código'), 'Perfil tem "Enviar código" para mudar a senha', $r);
        $csrf = Sessao::csrf($r['html']);
        $codigo = pedirCodigoSenha($g, $csrf);
        ok(preg_match('/^\d{6}$/', $codigo) === 1, 'o código aparece no ecrã em modo dev');
        $n = (int)$pdo->query("SELECT COUNT(*) FROM codigos_seguranca cs JOIN utilizadores u ON u.id = cs.utilizador_id WHERE u.email = '" . TESTE_GESTOR . "' AND cs.usado = 0 AND cs.codigo_hash <> '$codigo'")->fetchColumn();
        ok($n === 1, 'o código fica guardado só como hash');

        $errado = $codigo === '000000' ? '111111' : '000000';
        $g->post('gestao/index.php?m=perfil&a=alterar_password', ['codigo' => $errado, 'nova_password' => 'Nova!2026x', 'confirmar_password' => 'Nova!2026x', 'csrf' => $csrf]);
        ok(senhaFunciona(TESTE_GESTOR, TESTE_PASSWORD), 'código errado não muda a senha');
        $g->post('gestao/index.php?m=perfil&a=alterar_password', ['codigo' => $codigo, 'nova_password' => 'abc', 'confirmar_password' => 'abc', 'csrf' => $csrf]);
        $r = $g->get('gestao/index.php?m=perfil&a=editar');
        ok(senhaFunciona(TESTE_GESTOR, TESTE_PASSWORD) && str_contains($r['html'], '8 caracteres'), 'senha fraca recusada com mensagem', $r);
        $g->post('gestao/index.php?m=perfil&a=alterar_password', ['codigo' => $codigo, 'nova_password' => 'Nova!2026x', 'confirmar_password' => 'Nova!2026x', 'csrf' => $csrf]);
        ok(senhaFunciona(TESTE_GESTOR, 'Nova!2026x'), 'código certo muda a senha');
        $repor();
        $g->post('gestao/index.php?m=perfil&a=alterar_password', ['codigo' => $codigo, 'nova_password' => 'Outra!2026x', 'confirmar_password' => 'Outra!2026x', 'csrf' => $csrf]);
        ok(senhaFunciona(TESTE_GESTOR, TESTE_PASSWORD), 'o mesmo código não serve duas vezes');

        // 5 códigos errados gastam o código, mesmo que depois chegue o certo
        $codigo = pedirCodigoSenha($g, $csrf);
        for ($i = 0; $i < 5; $i++) {
            $g->post('gestao/index.php?m=perfil&a=alterar_password', ['codigo' => $codigo === '000000' ? '111111' : '000000', 'nova_password' => 'Nova!2026x', 'confirmar_password' => 'Nova!2026x', 'csrf' => $csrf]);
        }
        $g->post('gestao/index.php?m=perfil&a=alterar_password', ['codigo' => $codigo, 'nova_password' => 'Nova!2026x', 'confirmar_password' => 'Nova!2026x', 'csrf' => $csrf]);
        ok(senhaFunciona(TESTE_GESTOR, TESTE_PASSWORD), 'depois de 5 códigos errados o certo já não serve');

        // Recuperar a senha (Review Focus 4: a resposta não revela se o email existe)
        $a = new Sessao($BASE);
        $f = $a->get('gestao/recuperar.php');
        ok($f['status'] === 200 && str_contains($f['html'], 'name="email"'), 'recuperar.php pede o email', $f);
        $r1 = $a->post('gestao/recuperar.php', ['email' => 'nao.existe.rotas@example.com', 'csrf' => Sessao::csrf($f['html'])]);
        $b = new Sessao($BASE);
        $f = $b->get('gestao/recuperar.php');
        $r2 = $b->post('gestao/recuperar.php', ['email' => TESTE_GESTOR, 'csrf' => Sessao::csrf($f['html'])]);
        ok($r1['status'] === $r2['status'] && $r1['local'] === $r2['local'] && $r1['html'] === $r2['html'], 'email inexistente e existente têm a mesma resposta', $r2);
        $p2 = $b->get('gestao/recuperar.php?passo=2');
        $codigo = preg_match('/Código: (\d{6})/', $p2['html'], $m) ? $m[1] : '';
        ok($codigo !== '' && str_contains($p2['html'], 'name="codigo"'), 'passo 2 pede o código (em modo dev mostra-o)', $p2);
        $r = $b->post('gestao/recuperar.php?passo=2', ['email' => TESTE_GESTOR, 'codigo' => $codigo, 'nova_password' => 'Recup!2026x', 'confirmar_password' => 'Recup!2026x', 'csrf' => Sessao::csrf($p2['html'])]);
        ok($r['status'] === 302 && str_contains($r['local'], 'login.php') && senhaFunciona(TESTE_GESTOR, 'Recup!2026x'), 'recuperar com o código muda a senha e volta ao login', $r);
    } finally {
        $repor();
        $limparTent();
        $pdo->exec("DELETE cs FROM codigos_seguranca cs JOIN utilizadores u ON u.id = cs.utilizador_id WHERE u.email IN ('" . TESTE_GESTOR . "','" . TESTE_BARBEIRO . "')");
    }
}

/* ===================== Revisão final: correções de segurança ===================== */
function mediaLoginMs(string $email, string $senha, int $vezes = 3): float {
    global $BASE;
    $total = 0.0;
    for ($i = 0; $i < $vezes; $i++) {
        $s = new Sessao($BASE);
        $f = $s->get('gestao/login.php');
        $t = microtime(true);
        $s->post('gestao/login.php', ['email' => sprintf($email, $i), 'password' => $senha, 'csrf' => Sessao::csrf($f['html'])]);
        $total += (microtime(true) - $t) * 1000;
    }
    return $total / $vezes;
}

function teste_revisao_seguranca(): void {
    global $BASE, $pdo;
    require_once __DIR__ . '/../../../config/totp.php';
    $limpar = fn() => $pdo->exec("DELETE FROM tentativas_login WHERE email LIKE '%rotas%@%' OR email IN ('" . TESTE_GESTOR . "','" . TESTE_BARBEIRO . "')");
    $limpar();
    $hashTeste = password_hash(TESTE_PASSWORD, PASSWORD_DEFAULT);
    try {
        // C1: o código de recuperação NUNCA aparece no ecrã para um email real (só para domínios de teste)
        $pdo->prepare("INSERT INTO utilizadores (nome, email, password_hash, tipo, ativo, barbearia_id) VALUES ('Recuperar Rotas', 'recuperar.rotas@barberflow.pt', ?, 'gestor', 1, 1)")->execute([$hashTeste]);
        $a = new Sessao($BASE);
        $f = $a->get('gestao/recuperar.php');
        $a->post('gestao/recuperar.php', ['email' => 'recuperar.rotas@barberflow.pt', 'csrf' => Sessao::csrf($f['html'])]);
        $p2 = $a->get('gestao/recuperar.php?passo=2');
        ok($p2['status'] === 200 && !preg_match('/Código: \d{6}/', $p2['html']), 'recuperar não mostra o código de uma conta real no ecrã', $p2);

        // I3: o passo 1 do recuperar tem limite (5 pedidos por email em 15 min)
        for ($i = 0; $i < 7; $i++) {
            $f = $a->get('gestao/recuperar.php');
            $r = $a->post('gestao/recuperar.php', ['email' => 'recuperar.rotas@barberflow.pt', 'csrf' => Sessao::csrf($f['html'])]);
        }
        $n = (int)$pdo->query("SELECT COUNT(*) FROM codigos_seguranca cs JOIN utilizadores u ON u.id = cs.utilizador_id WHERE u.email = 'recuperar.rotas@barberflow.pt'")->fetchColumn();
        ok($n <= 5, "recuperar: no máximo 5 códigos por email em 15 min ($n criados)");

        // I2: códigos 2FA errados contam para o limite de tentativas (não basta repetir a senha)
        $segredo = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $pdo->prepare("UPDATE utilizadores SET totp_segredo = ?, totp_ativo = 1 WHERE email = ?")->execute([$segredo, TESTE_BARBEIRO]);
        for ($ronda = 0; $ronda < 2; $ronda++) {
            $s = new Sessao($BASE);
            $f = $s->get('gestao/login.php');
            $r = $s->post('gestao/login.php', ['email' => TESTE_BARBEIRO, 'password' => TESTE_PASSWORD, 'csrf' => Sessao::csrf($f['html'])]);
            for ($i = 0; $i < 5 && str_contains($r['html'], 'name="codigo_2fa"'); $i++) {
                $r = $s->post('gestao/login.php', ['codigo_2fa' => '000000', 'csrf' => Sessao::csrf($r['html'])]);
            }
        }
        $s = new Sessao($BASE);
        ok(!$s->login(TESTE_BARBEIRO, TESTE_PASSWORD, totpCodigo($segredo, time())) && str_contains($s->ultima['html'] ?? '', 'Demasiadas tentativas'),
           'depois de muitos códigos 2FA errados o login fica bloqueado', $s->ultima);
        $pdo->prepare("UPDATE utilizadores SET totp_segredo = NULL, totp_ativo = 0 WHERE email = ?")->execute([TESTE_BARBEIRO]);
        $limpar();

        // I4: um email inexistente não responde muito mais depressa do que um existente (não revela contas)
        $semConta = mediaLoginMs('tempo.rotas.%d@example.com', 'errada-1');
        $comConta = mediaLoginMs(TESTE_GESTOR, 'errada-1');
        ok($semConta >= 0.6 * $comConta, sprintf('login: email inexistente demora como um existente (%.0f ms vs %.0f ms)', $semConta, $comConta));
    } finally {
        $pdo->prepare("UPDATE utilizadores SET totp_segredo = NULL, totp_ativo = 0 WHERE email = ?")->execute([TESTE_BARBEIRO]);
        $pdo->exec("DELETE FROM utilizadores WHERE email = 'recuperar.rotas@barberflow.pt'");
        $limpar();
    }
}

/* ===================== Requisitos web: convite e ativação de barbeiros ===================== */
function linkConvite(string $html): string {
    return preg_match('#ativar\.php\?t=([0-9a-f]{64})#', $html, $m) ? $m[1] : '';
}

function teste_convite_barbeiro(): void {
    global $BASE, $pdo;
    $GLOBALS['LIMPEZA'][] = "DELETE FROM tentativas_login WHERE email LIKE 'convite%.rotas@example.com'";
    $GLOBALS['LIMPEZA'][] = "DELETE FROM utilizadores WHERE email LIKE 'convite%.rotas@example.com'";
    $g = new Sessao($BASE); $g->login(TESTE_GESTOR);
    $r = $g->get('gestao/index.php?m=barbeiros&a=listar');
    $csrf = Sessao::csrf($r['html']);
    ok(str_contains($r['html'], 'name="email"') && !str_contains($r['html'], 'Password temporária'), 'formulário de convite disponível (sem senha temporária)', $r);

    // Convite sem bancada (passou a opcional)
    $r = $g->post('gestao/index.php?m=barbeiros&a=criar', ['nome' => 'Barbeiro Convite Rotas', 'email' => 'convite1.rotas@example.com', 'telefone' => '', 'csrf' => $csrf]);
    ok($r['status'] === 302, 'gestor convida barbeiro sem bancada', $r);
    $r = $g->get('gestao/index.php?m=barbeiros&a=listar');
    $token = linkConvite($r['html']);
    ok(str_contains($r['html'], 'Por ativar') && $token !== '' && str_contains($r['html'], 'Copiar'), 'lista mostra "Por ativar" e o link para copiar (email não saiu)', $r);
    $n = (int)$pdo->query("SELECT COUNT(*) FROM utilizadores WHERE email = 'convite1.rotas@example.com' AND convite_token_hash = '" . hash('sha256', $token) . "'")->fetchColumn();
    ok($n === 1, 'na BD fica só o hash do token');

    $s = new Sessao($BASE);
    ok(!$s->login('convite1.rotas@example.com', 'qualquer1') && str_contains($s->ultima['html'] ?? '', 'Ativa a conta'), 'conta por ativar não entra', $s->ultima);

    // Ativar (Review Focus 3: o link só serve uma vez)
    $a = new Sessao($BASE);
    $f = $a->get('gestao/ativar.php?t=' . $token);
    ok($f['status'] === 200 && str_contains($f['html'], 'name="nova_password"') && str_contains($f['html'], 'Barbeiro Convite Rotas'), 'link de ativação abre o formulário da senha', $f);
    $r = $a->post('gestao/ativar.php?t=' . $token, ['nova_password' => 'fraca', 'confirmar_password' => 'fraca', 'csrf' => Sessao::csrf($f['html'])]);
    ok($r['status'] === 200 && str_contains($r['html'], '8 caracteres'), 'senha fraca recusada na ativação', $r);
    $r = $a->post('gestao/ativar.php?t=' . $token, ['nova_password' => 'Ativa!2026x', 'confirmar_password' => 'Ativa!2026x', 'csrf' => Sessao::csrf($f['html'])]);
    ok($r['status'] === 302 && str_contains($r['local'], 'login.php'), 'ativação concluída volta ao login', $r);
    ok((new Sessao($BASE))->login('convite1.rotas@example.com', 'Ativa!2026x'), 'barbeiro ativado entra com a senha que escolheu');
    $r = (new Sessao($BASE))->get('gestao/ativar.php?t=' . $token);
    ok(!str_contains($r['html'], 'name="nova_password"') && str_contains($r['html'], 'inválido'), 'o mesmo link não serve outra vez', $r);

    // Reenviar convite invalida o link anterior; link expirado não serve
    $g->post('gestao/index.php?m=barbeiros&a=criar', ['nome' => 'Barbeiro Convite Dois', 'email' => 'convite2.rotas@example.com', 'csrf' => $csrf]);
    $antigo = linkConvite($g->get('gestao/index.php?m=barbeiros&a=listar')['html']);
    $id2 = (int)$pdo->query("SELECT id FROM utilizadores WHERE email = 'convite2.rotas@example.com'")->fetchColumn();
    $r = $g->post('gestao/index.php?m=barbeiros&a=reenviar_convite', ['id' => $id2, 'csrf' => $csrf]);
    $novo = linkConvite($g->get('gestao/index.php?m=barbeiros&a=listar')['html']);
    ok($novo !== '' && $novo !== $antigo, 'reenviar convite gera um link novo', $r);
    ok(str_contains((new Sessao($BASE))->get('gestao/ativar.php?t=' . $antigo)['html'], 'inválido'), 'o link antigo deixa de valer');
    ok(str_contains((new Sessao($BASE))->get('gestao/ativar.php?t=' . $novo)['html'], 'name="nova_password"'), 'o link novo funciona');
    $pdo->exec("UPDATE utilizadores SET convite_expira_em = NOW() - INTERVAL 1 HOUR WHERE id = $id2");
    ok(str_contains((new Sessao($BASE))->get('gestao/ativar.php?t=' . $novo)['html'], 'inválido'), 'link expirado não serve');

    // Barbeiro (perfil) não pode reenviar convites
    $hashAntes = (string)$pdo->query("SELECT convite_token_hash FROM utilizadores WHERE id = $id2")->fetchColumn();
    $b = new Sessao($BASE); $b->login(TESTE_BARBEIRO);
    $rb = $b->get('gestao/index.php?m=agenda&a=listar');
    $b->post('gestao/index.php?m=barbeiros&a=reenviar_convite', ['id' => $id2, 'csrf' => Sessao::csrf($rb['html'])]);
    ok((string)$pdo->query("SELECT convite_token_hash FROM utilizadores WHERE id = $id2")->fetchColumn() === $hashAntes, 'barbeiro não consegue reenviar convites');

    // Bug reportado na UA: ativar o convite no MESMO browser onde o gestor tem sessão deixava a pessoa na conta do gestor
    $g3 = new Sessao($BASE); $g3->login(TESTE_GESTOR);
    $r = $g3->get('gestao/index.php?m=barbeiros&a=listar');
    $g3->post('gestao/index.php?m=barbeiros&a=criar', ['nome' => 'Barbeiro Convite Tres', 'email' => 'convite3.rotas@example.com', 'csrf' => Sessao::csrf($r['html'])]);
    $token3 = linkConvite($g3->get('gestao/index.php?m=barbeiros&a=listar')['html']);
    $f = $g3->get('gestao/ativar.php?t=' . $token3);
    ok(str_contains($f['html'], 'name="nova_password"'), 'o link abre no browser onde o gestor tem sessão', $f);
    $g3->post('gestao/ativar.php?t=' . $token3, ['nova_password' => 'Tres!2026x', 'confirmar_password' => 'Tres!2026x', 'csrf' => Sessao::csrf($f['html'])]);
    $r = $g3->get('gestao/login.php?ativada=1');
    ok($r['status'] === 200 && str_contains($r['html'], 'name="password"'), 'depois de ativar aparece o login (não entra na conta do gestor)', $r);
    $r = $g3->get('gestao/index.php?m=servicos&a=listar');
    ok($r['status'] === 302 && str_contains($r['local'], 'login.php'), 'a sessão do gestor foi terminada nesse browser', $r);
    ok($g3->login('convite3.rotas@example.com', 'Tres!2026x'), 'o barbeiro entra com a conta dele', $g3->ultima);
    $r = $g3->get('gestao/index.php?m=servicos&a=listar');
    ok(str_contains($r['html'], 'Página não encontrada'), 'e só com privilégios de barbeiro', $r);
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
              'teste_stock_pedidos', 'teste_publico', 'teste_pesquisa_agenda', 'teste_login_seguro', 'teste_2fa_perfil', 'teste_senha_com_codigo', 'teste_convite_barbeiro', 'teste_revisao_seguranca'] as $t) {
        if (function_exists($t)) { echo "--- $t\n"; $t(); }
    }
} finally {
    if (function_exists('limparDadosTeste')) limparDadosTeste($pdo);
    apagarUtilizadoresTeste($pdo);
}
echo "\n$TOTAL testes, $FALHAS falhas\n";
exit($FALHAS > 0 ? 1 : 0);
