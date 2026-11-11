<?php
declare(strict_types=1);
// Módulo: bancadas / listar — incluído por gestao/index.php
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

$msg = '';
$erro = '';


$TIPOS = [
    'equipamento' => 'Equipamento',
    'material'    => 'Consumíveis',
    'produto'     => 'Produtos',
    'brinde'      => 'Brindes',
];
$ORDEM_TIPOS = ['equipamento', 'material', 'produto', 'brinde'];
// 2. Ver se estamos a editar
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM bancadas WHERE id = ? AND barbearia_id = ?");
    $stmt->execute([(int)$_GET['editar'], $bid]);
    $editar = $stmt->fetch();
}

// 3. Listar bancadas com nome do barbeiro
$stmt = $pdo->prepare("
    SELECT b.*, u.nome AS barbeiro_nome
    FROM bancadas b
    LEFT JOIN utilizadores u ON b.barbeiro_id = u.id
    WHERE b.barbearia_id = ?
    ORDER BY b.id ASC
");
$stmt->execute([$bid]);
$bancadas = $stmt->fetchAll();

// 4. Listar barbeiros livres para o dropdown
$stmt = $pdo->prepare("
    SELECT u.id, u.nome FROM utilizadores u
    LEFT JOIN bancadas b ON u.id = b.barbeiro_id AND b.ativo = 1
    WHERE u.barbearia_id = ? AND u.tipo = 'barbeiro' AND u.ativo = 1 AND (b.id IS NULL OR b.id = ?)
");
$stmt->execute([$bid, $editar['id'] ?? 0]);
$barbeiros_livres = $stmt->fetchAll();

// 5. Materiais de cada bancada, agrupados por tipo
$materiaisPorBancada = [];
$stmt = $pdo->prepare("
    SELECT bm.bancada_id, bm.quantidade, bm.quantidade_minima, m.nome, m.unidade, m.tipo
    FROM bancada_materiais bm
    JOIN materiais m ON bm.material_id = m.id
    WHERE m.barbearia_id = ? AND m.ativo = 1
    ORDER BY m.nome
");
$stmt->execute([$bid]);
foreach ($stmt->fetchAll() as $r) {
    $tipo = $r['tipo'] ?? 'material';
    $materiaisPorBancada[(int)$r['bancada_id']][$tipo][] = $r;
}
?>

<style>
    .bancadas-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 1.25rem; align-items: start; }

    .bancada-card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; }
    .bancada-card.inativa { opacity: 0.55; }

    .card-head { padding: 1.1rem 1.4rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; gap: 0.8rem; }
    .card-head h3 { font-family: 'Cinzel', serif; font-size: 1.05rem; color: #fff; margin: 0; }
    .card-head .barbeiro { color: var(--gold); font-size: 0.85rem; margin-top: 0.25rem; }
    .head-badges { display: flex; flex-direction: column; align-items: flex-end; gap: 0.3rem; flex: none; }

    .card-body { max-height: 320px; overflow-y: auto; padding: 0.4rem 1.4rem 1rem; }
    .card-body::-webkit-scrollbar { width: 8px; }
    .card-body::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 4px; }

    .grupo-titulo { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--gold); margin: 1rem 0 0.3rem; padding-bottom: 0.2rem; border-bottom: 1px solid rgba(255,255,255,0.06); }
    .grupo-titulo .n { color: var(--muted); font-weight: 400; }

    .mat-linha { display: flex; justify-content: space-between; align-items: center; gap: 0.6rem; padding: 0.32rem 0; font-size: 0.88rem; border-bottom: 1px dashed rgba(255,255,255,0.05); }
    .mat-linha:last-child { border-bottom: none; }
    .mat-nome { color: #f8f9fa; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .mat-qtd { color: var(--muted); font-size: 0.82rem; white-space: nowrap; display: flex; align-items: center; gap: 0.5rem; }

    .badge { padding: 0.22rem 0.5rem; border-radius: 4px; font-size: 0.72rem; font-weight: 600; display: inline-block; }
    .ok { color: #4ade80; background: rgba(34, 197, 94, 0.15); }
    .low { color: #f87171; background: rgba(239, 68, 68, 0.15); }
    .badge-falta { color: #f87171; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.35); }

    .sem-itens { color: var(--muted); font-size: 0.85rem; padding: 0.8rem 0; }

    .card-foot { padding: 0.9rem 1.4rem; border-top: 1px solid var(--border); display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }

    .form-toggle { margin-bottom: 1.5rem; }
    .form-toggle summary { list-style: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; background: var(--gold); color: #0a0a0a; font-weight: 700; padding: 0.75rem 1.3rem; border-radius: 8px; transition: 0.25s; user-select: none; }
    .form-toggle summary:hover { background: #e0be9a; }
    .form-toggle summary::-webkit-details-marker { display: none; }
    .form-toggle[open] summary { background: #1a1a1a; color: var(--gold); border: 1px solid var(--gold); }
    .form-card { background: var(--card); border-radius: 14px; border: 1px solid var(--border); overflow: hidden; max-width: 440px; margin-top: 1.25rem; }
    .form-head { display: flex; align-items: center; gap: 0.8rem; padding: 1.1rem 1.5rem; border-bottom: 1px solid var(--border); background: rgba(205,164,124,0.06); }
    .form-ico { width: 38px; height: 38px; border-radius: 10px; background: rgba(205,164,124,0.15); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex: none; }
    .form-head h2 { color: #fff; font-size: 1.1rem; margin: 0; font-family: 'Cinzel', serif; }
    .form-head p { color: var(--muted); font-size: 0.8rem; margin: 0.15rem 0 0; }
    .form-body { padding: 1.5rem; }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Gestão de Bancadas</h1>
            <p style="color: var(--muted); margin-top: 0.5rem;">Consulta o equipamento e os consumíveis de cada barbeiro.</p>
        </div>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <details class="form-toggle" <?= $editar ? 'open' : '' ?>>
        <summary><?= $editar ? '✏️ Editar Bancada' : '➕ Nova Bancada' ?></summary>
        <div class="form-card">
            <div class="form-head">
                <span class="form-ico"><?= $editar ? '✏️' : '➕' ?></span>
                <div>
                    <h2><?= $editar ? 'Editar Bancada' : 'Nova Bancada' ?></h2>
                    <p><?= $editar ? 'Altera o nome ou o barbeiro atribuído.' : 'Cria uma bancada e atribui um barbeiro.' ?></p>
                </div>
            </div>
            <div class="form-body">
                <form method="POST" action="<?= e(urlModulo('bancadas', $editar ? 'editar' : 'criar')) ?>">
                    <?= campoCsrf() ?>
                    <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>

                    <div class="input-group">
                        <label>Nome da Bancada</label>
                        <input type="text" name="nome" placeholder="Ex: Bancada 1 (em branco gera automaticamente)" value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">
                    </div>
                    <div class="input-group">
                        <label>Atribuir Barbeiro <span style="color:var(--muted);">(opcional)</span></label>
                        <select name="barbeiro_id">
                            <option value="">Nenhum (Livre)</option>
                            <?php foreach ($barbeiros_livres as $bl): ?>
                                <option value="<?= $bl['id'] ?>" <?= ($editar['barbeiro_id'] ?? '') == $bl['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bl['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit"><?= $editar ? 'Guardar Alterações' : 'Criar Bancada' ?></button>
                    <?php if ($editar): ?>
                        <a href="<?= e(urlModulo('bancadas', 'listar')) ?>" style="display:block; text-align:center; margin-top:1rem; color:var(--muted);">Cancelar</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </details>

    <div class="bancadas-grid">
        <?php foreach ($bancadas as $b): ?>
            <?php
            $grupos = $materiaisPorBancada[(int)$b['id']] ?? [];
            $totalItens = 0;
            $emFalta = 0;
            foreach ($grupos as $itens) {
                foreach ($itens as $i) {
                    $totalItens++;
                    if ((float)$i['quantidade'] <= (float)$i['quantidade_minima']) $emFalta++;
                }
            }
            ?>
            <div class="bancada-card <?= !$b['ativo'] ? 'inativa' : '' ?>">
                <div class="card-head">
                    <div>
                        <h3><?= htmlspecialchars($b['nome']) ?></h3>
                        <div class="barbeiro">
                            <?= $b['barbeiro_nome'] ? htmlspecialchars($b['barbeiro_nome']) : '<span style="color:#4ade80;">Livre</span>' ?>
                        </div>
                    </div>
                    <div class="head-badges">
                        <span class="badge <?= $b['ativo'] ? 'ok' : 'low' ?>"><?= $b['ativo'] ? 'Ativa' : 'Inativa' ?></span>
                        <?php if ($emFalta > 0): ?>
                            <span class="badge badge-falta"><?= $emFalta ?> em falta</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (empty($grupos)): ?>
                        <div class="sem-itens">Sem materiais atribuídos. Usa <strong>Stock e Materiais → Transferir</strong> para abastecer.</div>
                    <?php else: ?>
                        <?php foreach ($ORDEM_TIPOS as $tipo): ?>
                            <?php if (empty($grupos[$tipo])) continue; ?>
                            <div class="grupo-titulo"><?= htmlspecialchars($TIPOS[$tipo]) ?> <span class="n">(<?= count($grupos[$tipo]) ?>)</span></div>
                            <?php foreach ($grupos[$tipo] as $i): ?>
                                <?php $baixo = (float)$i['quantidade'] <= (float)$i['quantidade_minima']; ?>
                                <div class="mat-linha">
                                    <span class="mat-nome"><?= htmlspecialchars($i['nome']) ?></span>
                                    <span class="mat-qtd">
                                        <?= (float)$i['quantidade'] ?> <?= htmlspecialchars(strtoupper($i['unidade'] ?? 'un')) ?>
                                        <span class="badge <?= $baixo ? 'low' : 'ok' ?>"><?= $baixo ? 'Repor' : 'OK' ?></span>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="card-foot">
                    <a href="<?= e(urlModulo('bancadas', 'listar', ['editar' => $b['id']])) ?>" class="btn-acao">Editar</a>
                    <?php if ($b['ativo']): ?>
                        <form method="POST" action="<?= e(urlModulo('bancadas', 'apagar')) ?>" style="display:inline;">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn-acao btn-perigo" onclick="return confirm('Desativar esta bancada?')">Desativar</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" action="<?= e(urlModulo('bancadas', 'ativar')) ?>" style="display:inline;">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn-acao">Reativar</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (empty($bancadas)): ?>
            <div class="alerta erro">Ainda não existem bancadas.</div>
        <?php endif; ?>
    </div>

</div>
