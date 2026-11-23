<?php
declare(strict_types=1);
// Módulo: stock / listar — incluído por gestao/index.php
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

require_once __DIR__ . '/_comum.php';
$msg = '';
$erro = '';

// Categorias dos materiais (coluna materiais.tipo), pela ordem em que aparecem
$CATEGORIAS = [
    'material' => 'Consumíveis',
    'produto'  => 'Produtos',
    'brinde'   => 'Brindes',
];

// =========================================================
// FILTROS (GET)
// =========================================================
$filtro_busca  = trim($_GET['q'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';

// =========================================================
// MODOS DE FORMULÁRIO DO GESTOR (editar / transferir)
// =========================================================
$editar = null;
$transferir = null;
if ($eGestor && isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM materiais WHERE id = ? AND barbearia_id = ?");
    $stmt->execute([(int)$_GET['editar'], $bid]);
    $editar = $stmt->fetch();
}
if ($eGestor && isset($_GET['transferir'])) {
    $stmt = $pdo->prepare("SELECT * FROM materiais WHERE id = ? AND barbearia_id = ? AND ativo = 1");
    $stmt->execute([(int)$_GET['transferir'], $bid]);
    $transferir = $stmt->fetch();
}

// =========================================================
// QUERIES DE LISTAGEM (os que estão em falta vêm primeiro)
// =========================================================
$materiais = [];
$bancadas_disponiveis = [];
$pendentes_por_material = [];

if ($eGestor) {
    // Stock geral
    $sql = "SELECT * FROM materiais WHERE barbearia_id = ? AND ativo = 1";
    $params = [$bid];
    if ($filtro_busca !== '') {
        $sql .= " AND nome LIKE ?";
        $params[] = likeSeguro($filtro_busca);
    }
    if ($filtro_estado === 'low') {
        $sql .= " AND stock_geral <= stock_minimo";
    }
    $sql .= " ORDER BY (stock_geral <= stock_minimo) DESC, nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $materiais = $stmt->fetchAll();

    // Bancadas ativas para o formulário de transferência
    $stmt = $pdo->prepare("
        SELECT b.id, b.nome, u.nome AS barbeiro_nome
        FROM bancadas b
        LEFT JOIN utilizadores u ON b.barbeiro_id = u.id
        WHERE b.barbearia_id = ? AND b.ativo = 1
        ORDER BY b.nome
    ");
    $stmt->execute([$bid]);
    $bancadas_disponiveis = $stmt->fetchAll();
} else {
    if ($bancada_id) {
        $sql = "SELECT m.id, m.nome, m.unidade, m.tipo, bm.quantidade, bm.quantidade_minima AS stock_minimo
                FROM bancada_materiais bm
                JOIN materiais m ON bm.material_id = m.id
                WHERE bm.bancada_id = ? AND m.barbearia_id = ? AND m.ativo = 1";
        $params = [$bancada_id, $bid];
        if ($filtro_busca !== '') {
            $sql .= " AND m.nome LIKE ?";
            $params[] = likeSeguro($filtro_busca);
        }
        if ($filtro_estado === 'low') {
            $sql .= " AND bm.quantidade <= bm.quantidade_minima";
        }
        $sql .= " ORDER BY (bm.quantidade <= bm.quantidade_minima) DESC, m.nome";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $materiais = $stmt->fetchAll();

        // Pedidos pendentes por material (para mostrar estado no card)
        $stmt = $pdo->prepare("
            SELECT material_id, COUNT(*) AS total
            FROM pedidos_reposicao
            WHERE barbearia_id = ? AND bancada_id = ? AND estado = 'pendente'
            GROUP BY material_id
        ");
        $stmt->execute([$bid, $bancada_id]);
        foreach ($stmt->fetchAll() as $row) {
            $pendentes_por_material[(int)$row['material_id']] = (int)$row['total'];
        }
    }
}

// Agrupar por categoria, mantendo a ordem da query dentro de cada uma
$porCategoria = [];
foreach ($materiais as $m) {
    $quantidade = $eGestor ? (float)$m['stock_geral'] : (float)$m['quantidade'];
    $m['qtd'] = $quantidade;
    $m['em_falta'] = $quantidade <= (float)$m['stock_minimo'];
    $porCategoria[$m['tipo'] ?? 'material'][] = $m;
}
$ordemCategorias = array_merge(
    array_keys(array_intersect_key($CATEGORIAS, $porCategoria)),
    array_keys(array_diff_key($porCategoria, $CATEGORIAS))
);
$idAberto = (int)($editar['id'] ?? $transferir['id'] ?? 0);
?>

<style>
    .grid-stock { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start; }
    @media (max-width: 900px) { .grid-stock { grid-template-columns: 1fr; } }
    .form-card { background: var(--card); border-radius: var(--raio); border: 1px solid var(--border); padding: 1.5rem; }
    .form-card h2 { font-size: 1.2rem; margin-bottom: 1.5rem; color: var(--gold); font-family: 'Cinzel', serif; }
    .btn-cancelar { display: block; text-align: center; margin-top: 0.8rem; color: var(--muted); text-decoration: none; font-size: 0.85rem; }

    /* Estilos dos Filtros */
    .filter-bar { padding: 1rem; background: var(--superficie); border: 1px solid var(--border); border-radius: var(--raio); margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
    .filter-bar input { flex: 1; min-width: 200px; padding: 0.6rem 1rem; background: var(--fundo); border: 1px solid var(--border); border-radius: var(--raio-campo); color: #fff; }
    .filter-bar select { padding: 0.6rem 1rem; background: var(--fundo); border: 1px solid var(--border); border-radius: var(--raio-campo); color: #fff; }
    .filter-bar button { padding: 0.6rem 1.5rem; background: var(--gold); color: #000; border: none; border-radius: var(--raio-campo); font-weight: 600; cursor: pointer; transition: 0.3s; }
    .filter-bar button:hover { background: #e0be9a; }
    .btn-limpar { color: var(--muted); text-decoration: none; font-size: 0.85rem; }
    .btn-limpar:hover { color: #fff; }

    /* Formulário de pedido de reposição (barbeiro) */
    .pedido-form { display: flex; gap: 0.4rem; align-items: center; }
    .pedido-form input { width: 80px; background: var(--fundo); border: 1px solid var(--border); color: #fff; padding: 0.4rem 0.5rem; border-radius: 999px; font-size: 0.85rem; }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1><?= $eGestor ? 'Stock Geral' : 'Minha Bancada' ?></h1>
            <p style="color: var(--muted); margin-top: 0.5rem;">
                <?= $eGestor ? 'Gere o catálogo de materiais e transfere stock para as bancadas.' : 'Os itens disponíveis na tua bancada e pedidos de reposição.' ?>
            </p>
        </div>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if (!$eGestor && !$bancada_id): ?>
        <div class="alerta erro">
            Ainda não tens uma bancada atribuída pelo gestor. Pede-lhe que te associe uma bancada em <strong>Bancadas</strong>.
        </div>
    <?php else: ?>
        <div class="<?= $eGestor ? 'grid-stock' : '' ?>">
            <!-- Cards por categoria -->
            <div>
                <form method="GET" action="index.php" class="filter-bar">
                    <input type="hidden" name="m" value="stock"><input type="hidden" name="a" value="listar">
                    <input type="text" name="q" value="<?= htmlspecialchars($filtro_busca) ?>" placeholder="Pesquisar por nome...">
                    <select name="estado">
                        <option value="">Todos os estados</option>
                        <option value="low" <?= $filtro_estado === 'low' ? 'selected' : '' ?>>Apenas em falta</option>
                    </select>
                    <button type="submit">Filtrar</button>
                    <?php if ($filtro_busca !== '' || $filtro_estado !== ''): ?>
                        <a href="<?= e(urlModulo('stock', 'listar')) ?>" class="btn-limpar">Limpar</a>
                    <?php endif; ?>
                </form>

                <?php if (empty($materiais)): ?>
                    <div class="sem-cards">Nenhum item encontrado.</div>
                <?php endif; ?>

                <?php foreach ($ordemCategorias as $cat): ?>
                    <?php
                    $itens = $porCategoria[$cat];
                    $faltaCat = count(array_filter($itens, fn($i) => $i['em_falta']));
                    ?>
                    <section class="grupo-cards">
                        <h2 class="grupo-cards-titulo">
                            <?= e($CATEGORIAS[$cat] ?? ucfirst((string)$cat)) ?>
                            <span class="n">
                                <?= count($itens) ?> <?= count($itens) === 1 ? 'item' : 'itens' ?>
                                <?php if ($faltaCat > 0): ?> · <span class="falta"><?= $faltaCat ?> em falta</span><?php endif; ?>
                            </span>
                        </h2>
                        <div class="cards-grid">
                            <?php foreach ($itens as $m): ?>
                                <?php $unidade = strtoupper($m['unidade'] ?? 'un'); ?>
                                <details class="item-card<?= $m['em_falta'] ? ' falta' : '' ?>" <?= (int)$m['id'] === $idAberto ? 'open' : '' ?>>
                                    <summary>
                                        <span class="item-nome"><?= e($m['nome']) ?></span>
                                        <span class="item-resumo">
                                            <span><?= $m['qtd'] ?> <?= e($unidade) ?></span>
                                            <span class="selos">
                                                <?php if (isset($pendentes_por_material[$m['id']])): ?>
                                                    <span class="badge pendente"><?= $pendentes_por_material[$m['id']] ?> pendente(s)</span>
                                                <?php endif; ?>
                                                <span class="badge <?= $m['em_falta'] ? 'low' : 'ok' ?>"><?= $m['em_falta'] ? 'Repor' : 'OK' ?></span>
                                            </span>
                                        </span>
                                    </summary>
                                    <div class="item-corpo">
                                        <dl class="item-dados">
                                            <div><dt><?= $eGestor ? 'Stock geral' : 'Na bancada' ?></dt><dd><?= $m['qtd'] ?></dd></div>
                                            <div><dt>Mínimo</dt><dd><?= (float)$m['stock_minimo'] ?></dd></div>
                                            <div><dt>Unidade</dt><dd><?= e($unidade) ?></dd></div>
                                        </dl>
                                        <?php if ($eGestor): ?>
                                            <div class="item-acoes">
                                                <a href="<?= e(urlModulo('stock', 'listar', ['editar' => $m['id']])) ?>" class="btn-acao">Editar</a>
                                                <a href="<?= e(urlModulo('stock', 'listar', ['transferir' => $m['id']])) ?>" class="btn-acao">Transferir</a>
                                                <form method="POST" action="<?= e(urlModulo('stock', 'apagar')) ?>" style="display:inline;">
                                                    <?= campoCsrf() ?>
                                                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                    <button type="submit" class="btn-acao btn-perigo" onclick="return confirm('Desativar este material?')">Desativar</button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <form method="POST" action="<?= e(urlModulo('stock', 'pedir_reposicao')) ?>" class="pedido-form">
                                                <?= campoCsrf() ?>
                                                <input type="hidden" name="material_id" value="<?= $m['id'] ?>">
                                                <input type="number" name="quantidade" step="0.01" min="0.01" required placeholder="Qtd">
                                                <button type="submit" class="btn-acao">Pedir Reposição</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>

            <!-- Formulário (Apenas Gestor) -->
            <?php if ($eGestor): ?>
            <div class="form-card">
                <?php if ($editar): ?>
                    <h2>Editar Material</h2>
                    <form method="POST" action="<?= e(urlModulo('stock', 'guardar')) ?>">
                        <?= campoCsrf() ?>
                        <input type="hidden" name="id" value="<?= $editar['id'] ?>">
                        <div class="input-group">
                            <label>Nome do Material</label>
                            <input type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>" placeholder="Ex: Lâminas">
                        </div>
                        <div class="input-group">
                            <label>Unidade</label>
                            <select name="unidade">
                                <option value="un" <?= ($editar['unidade'] ?? '') === 'un' ? 'selected' : '' ?>>Unidade (un)</option>
                                <option value="ml" <?= ($editar['unidade'] ?? '') === 'ml' ? 'selected' : '' ?>>Mililitros (ml)</option>
                                <option value="g" <?= ($editar['unidade'] ?? '') === 'g' ? 'selected' : '' ?>>Gramas (g)</option>
                                <option value="kg" <?= ($editar['unidade'] ?? '') === 'kg' ? 'selected' : '' ?>>Quilogramas (kg)</option>
                                <option value="l" <?= ($editar['unidade'] ?? '') === 'l' ? 'selected' : '' ?>>Litros (l)</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Stock Geral</label>
                            <input type="number" step="0.01" name="stock_geral" required value="<?= htmlspecialchars((string)($editar['stock_geral'] ?? '0')) ?>">
                        </div>
                        <div class="input-group">
                            <label>Stock Mínimo (Alerta)</label>
                            <input type="number" step="0.01" name="stock_minimo" required value="<?= htmlspecialchars((string)($editar['stock_minimo'] ?? '0')) ?>">
                        </div>
                        <button type="submit" class="btn-submit">Guardar Alterações</button>
                        <a href="<?= e(urlModulo('stock', 'listar')) ?>" class="btn-cancelar">Cancelar edição</a>
                    </form>

                <?php elseif ($transferir): ?>
                    <h2>Transferir Material</h2>
                    <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 1.2rem;">
                        Enviar stock geral de <strong style="color:#fff;"><?= htmlspecialchars($transferir['nome']) ?></strong>
                        (disponível: <?= (float)$transferir['stock_geral'] ?> <?= htmlspecialchars(strtoupper($transferir['unidade'] ?? 'un')) ?>)
                        para uma bancada.
                    </p>
                    <?php if (empty($bancadas_disponiveis)): ?>
                        <div class="alerta erro">Não existem bancadas ativas. Vai à Gestão de Bancadas para criar uma.</div>
                    <?php else: ?>
                        <form method="POST" action="<?= e(urlModulo('stock', 'transferir')) ?>">
                            <?= campoCsrf() ?>
                            <input type="hidden" name="material_id" value="<?= $transferir['id'] ?>">
                            <div class="input-group">
                                <label>Bancada de destino</label>
                                <select name="bancada_id" required>
                                    <option value="">Escolhe uma bancada...</option>
                                    <?php foreach ($bancadas_disponiveis as $b): ?>
                                        <option value="<?= $b['id'] ?>">
                                            <?= htmlspecialchars($b['nome']) ?><?= $b['barbeiro_nome'] ? ' — ' . htmlspecialchars($b['barbeiro_nome']) : ' (livre)' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="input-group">
                                <label>Quantidade a transferir</label>
                                <input type="number" step="0.01" min="0.01" name="quantidade" required placeholder="Ex: 10">
                            </div>
                            <button type="submit" class="btn-submit">Transferir</button>
                            <a href="<?= e(urlModulo('stock', 'listar')) ?>" class="btn-cancelar">Cancelar</a>
                        </form>
                    <?php endif; ?>

                <?php else: ?>
                    <h2>Novo Material</h2>
                    <form method="POST" action="<?= e(urlModulo('stock', 'guardar')) ?>">
                        <?= campoCsrf() ?>
                        <div class="input-group">
                            <label>Nome do Material</label>
                            <input type="text" name="nome" required placeholder="Ex: Lâminas">
                        </div>
                        <div class="input-group">
                            <label>Unidade</label>
                            <select name="unidade">
                                <option value="un">Unidade (un)</option>
                                <option value="ml">Mililitros (ml)</option>
                                <option value="g">Gramas (g)</option>
                                <option value="kg">Quilogramas (kg)</option>
                                <option value="l">Litros (l)</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Stock Geral</label>
                            <input type="number" step="0.01" name="stock_geral" required value="0">
                        </div>
                        <div class="input-group">
                            <label>Stock Mínimo (Alerta)</label>
                            <input type="number" step="0.01" name="stock_minimo" required value="0">
                        </div>
                        <button type="submit" class="btn-submit">Adicionar Material</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
