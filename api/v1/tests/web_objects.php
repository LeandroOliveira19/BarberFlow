<?php
declare(strict_types=1);

/**
 * Testes das classes de entidade (objects/) — CRUD em POO (atividade 06).
 * Só local (usa a BD diretamente). Uso: php api/v1/tests/web_objects.php
 */

require_once __DIR__ . '/../../../config/db.php';
$O = __DIR__ . '/../../../objects/';
$FALHAS = 0; $TOTAL = 0;
function ok(bool $c, string $t): void {
    global $FALHAS, $TOTAL; $TOTAL++;
    echo ($c ? 'OK      ' : 'FALHOU  ') . "$t\n";
    if (!$c) $FALHAS++;
}

foreach (['Servico', 'Fornecedor', 'Bancada', 'Configuracao'] as $c) {
    ok(is_file("$O$c.php"), "objects/$c.php existe");
    if (is_file("$O$c.php")) require_once "$O$c.php";
}
if ($FALHAS > 0) { echo "\n$TOTAL testes, $FALHAS falhas\n"; exit(1); }

$criados = ['servicos' => [], 'fornecedores' => [], 'bancadas' => []];
try {
    /* ---------- Servico ---------- */
    $s = new Servico($pdo, 1);
    $id = $s->create(['nome' => 'Objeto Teste', 'duracao_minutos' => 25, 'preco' => 8.5, 'descricao' => 'x']);
    $criados['servicos'][] = $id;
    $um = $s->readOne($id);
    ok($id > 0 && $um !== null && $um['nome'] === 'Objeto Teste' && (int)$um['duracao_minutos'] === 25, 'Servico: create + readOne');
    ok($s->update($id, ['nome' => 'Objeto Teste 2', 'duracao_minutos' => 30, 'preco' => 9, 'descricao' => '']), 'Servico: update');
    ok(in_array('Objeto Teste 2', array_column($s->read(), 'nome'), true), 'Servico: read (ativos) contém o atualizado');
    ok((new Servico($pdo, 2))->readOne($id) === null, 'Servico: isolado por barbearia (outra barbearia não vê)');
    ok(!(new Servico($pdo, 2))->delete($id), 'Servico: outra barbearia não consegue desativar');
    ok($s->delete($id) && !in_array($id, array_column($s->read(), 'id')), 'Servico: delete desativa (sai da lista)');

    /* ---------- Fornecedor ---------- */
    $f = new Fornecedor($pdo, 1);
    $fid = $f->create(['nome' => 'Fornecedor Objeto', 'contacto' => 'Ana', 'telefone' => '', 'email' => '', 'nif' => '']);
    $criados['fornecedores'][] = $fid;
    ok($f->readOne($fid)['contacto'] === 'Ana', 'Fornecedor: create + readOne');
    ok($f->update($fid, ['nome' => 'Fornecedor Objeto', 'contacto' => 'Rui', 'telefone' => '', 'email' => '', 'nif' => '']) && $f->readOne($fid)['contacto'] === 'Rui', 'Fornecedor: update');
    ok((new Fornecedor($pdo, 2))->readOne($fid) === null, 'Fornecedor: isolado por barbearia');

    /* ---------- Bancada ---------- */
    $b = new Bancada($pdo, 1);
    $bid = $b->create('', null);
    $criados['bancadas'][] = $bid;
    ok(str_starts_with((string)$b->readOne($bid)['nome'], 'Bancada '), 'Bancada: nome automático quando vazio');
    ok($b->setAtiva($bid, false) && (int)$b->readOne($bid)['ativo'] === 0, 'Bancada: desativar');
    ok($b->setAtiva($bid, true) && (int)$b->readOne($bid)['ativo'] === 1, 'Bancada: reativar');
    ok(in_array($bid, array_map('intval', array_column($b->read(), 'id')), true), 'Bancada: read inclui a nova');

    /* ---------- Configuracao ---------- */
    $cfg = new Configuracao($pdo, 1);
    $antes = $cfg->read();
    ok(isset($antes['tema_layout']), 'Configuracao: read devolve chave => valor');
    $cfg->save(['slogan' => 'Slogan Objeto']);
    ok($cfg->read()['slogan'] === 'Slogan Objeto', 'Configuracao: save');
    $cfg->save(['slogan' => (string)($antes['slogan'] ?? '')]);
} finally {
    foreach ($criados['servicos'] as $id) $pdo->exec("DELETE FROM servicos WHERE id = " . (int)$id);
    foreach ($criados['fornecedores'] as $id) $pdo->exec("DELETE FROM fornecedores WHERE id = " . (int)$id);
    foreach ($criados['bancadas'] as $id) $pdo->exec("DELETE FROM bancadas WHERE id = " . (int)$id);
}
echo "\n$TOTAL testes, $FALHAS falhas\n";
exit($FALHAS > 0 ? 1 : 0);
