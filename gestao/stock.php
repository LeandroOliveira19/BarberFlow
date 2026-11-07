<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['utilizador_id'], $_SESSION['barbearia_id'], $_SESSION['tipo'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['utilizador_id'];
$bid = (int)$_SESSION['barbearia_id'];
$tipo = (string)$_SESSION['tipo'];
$eGestor = ($tipo === 'gestor');

$msg = '';
$erro = '';

// LÓGICA DO GESTOR (CRUD de Materiais)
if ($eGestor && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'apagar') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE materiais SET ativo = 0 WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $bid]);
        $msg = 'Material desativado com sucesso.';
    } 
    elseif ($acao === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $unidade = $_POST['unidade'] ?? 'un';
        $stock_geral = (float)str_replace(',', '.', $_POST['stock_geral'] ?? '0');
        $stock_minimo = (float)str_replace(',', '.', $_POST['stock_minimo'] ?? '0');

        if ($nome === '') {
            $erro = 'O nome do material é obrigatório.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE materiais SET nome=?, unidade=?, stock_geral=?, stock_minimo=?, ativo=1 WHERE id=? AND barbearia_id=?");
                $stmt->execute([$nome, $unidade, $stock_geral, $stock_minimo, $id, $bid]);
                $msg = 'Material atualizado com sucesso.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO materiais (barbearia_id, nome, unidade, stock_geral, stock_minimo) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$bid, $nome, $unidade, $stock_geral, $stock_minimo]);
                $msg = 'Material adicionado com sucesso.';
            }
        }
    }
}

// LÓGICA DE FILTROS
$filtro_busca = trim($_GET['q'] ?? '');
$filtro_estado = $_GET['estado'] ?? '';

$bancada_id = null;
if (!$eGestor) {
    $stmt = $pdo->prepare("SELECT id FROM bancadas WHERE barbeiro_id = ? AND barbearia_id = ?");
    $stmt->execute([$uid, $bid]);
    $bancada_id = $stmt->fetchColumn();
}

if ($eGestor) {
    // Query para o Gestor (Stock Geral)
    $sql = "SELECT * FROM materiais WHERE barbearia_id = ? AND ativo = 1";
    $params = [$bid];
    
    if ($filtro_busca) {
        $sql .= " AND nome LIKE ?";
        $params[] = "%{$filtro_busca}%";
    }
    if ($filtro_estado === 'low') {
        $sql .= " AND stock_geral <= stock_minimo";
    }
    
    $sql .= " ORDER BY nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

} else {
    // Query para o Barbeiro (Minha Bancada)
    if (!$bancada_id) {
        die("Ainda não tens uma bancada atribuída pelo gestor.");
    }
    
    $sql = "SELECT m.id, m.nome, m.unidade, bm.quantidade, bm.quantidade_minima as stock_minimo 
            FROM bancada_materiais bm 
            JOIN materiais m ON bm.material_id = m.id 
            WHERE bm.bancada_id = ? AND m.barbearia_id = ? AND m.ativo = 1";
    $params = [$bancada_id, $bid];
    
    if ($filtro_busca) {
        $sql .= " AND m.nome LIKE ?";
        $params[] = "%{$filtro_busca}%";
    }
    if ($filtro_estado === 'low') {
        $sql .= " AND bm.quantidade <= bm.quantidade_minima";
    }
    
    $sql .= " ORDER BY m.nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}

$materiais = $stmt->fetchAll();

// Verificar se estamos a editar (só gestor)
$editar = null;
if ($eGestor && isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM materiais WHERE id = ? AND barbearia_id = ?");
    $stmt->execute([(int)$_GET['editar'], $bid]);
    $editar = $stmt->fetch();
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
    .grid-stock { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start; }
    @media (max-width: 900px) { .grid-stock { grid-template-columns: 1fr; } }
    .form-card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); padding: 1.5rem; }
    .form-card h2 { font-size: 1.2rem; margin-bottom: 1.5rem; color: var(--gold); font-family: 'Cinzel', serif; }
    .btn-cancelar { display: block; text-align: center; margin-top: 0.8rem; color: var(--muted); text-decoration: none; font-size: 0.85rem; }
    .badge { padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; }
    .ok { color: #4ade80; background: rgba(34, 197, 94, 0.15); }
    .low { color: #f87171; background: rgba(239, 68, 68, 0.15); }
    
    /* Estilos dos Filtros */
    .filter-bar { padding: 1rem; background: #1a1a1a; border-bottom: 1px solid var(--border); display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
    .filter-bar input { flex: 1; min-width: 200px; padding: 0.6rem 1rem; background: #0a0a0a; border: 1px solid var(--border); border-radius: 6px; color: #fff; }
    .filter-bar select { padding: 0.6rem 1rem; background: #0a0a0a; border: 1px solid var(--border); border-radius: 6px; color: #fff; }
    .filter-bar button { padding: 0.6rem 1.5rem; background: var(--gold); color: #000; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.3s; }
    .filter-bar button:hover { background: #e0be9a; }
    .btn-limpar { color: var(--muted); text-decoration: none; font-size: 0.85rem; }
    .btn-limpar:hover { color: #fff; }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1><?= $eGestor ? 'Stock Geral' : 'Minha Bancada' ?></h1>
            <p style="color: var(--muted); margin-top: 0.5rem;">
                <?= $eGestor ? 'Gere o catálogo de materiais da barbearia.' : 'Os itens disponíveis na tua bancada.' ?>
            </p>
        </div>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= $msg ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta erro"><?= $erro ?></div><?php endif; ?>

    <div class="grid-stock">
        <!-- Tabela -->
        <div class="table-container">
            
            <!-- Barra de Filtros -->
            <form method="GET" class="filter-bar">
                <input type="text" name="q" value="<?= htmlspecialchars($filtro_busca) ?>" placeholder="Pesquisar por nome...">
                
                <select name="estado">
                    <option value="">Todos os estados</option>
                    <option value="low" <?= $filtro_estado === 'low' ? 'selected' : '' ?>>Apenas em falta</option>
                </select>
                
                <button type="submit">Filtrar</button>
                
                <?php if ($filtro_busca || $filtro_estado): ?>
                    <a href="stock.php" class="btn-limpar">Limpar</a>
                <?php endif; ?>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Unidade</th>
                        <th><?= $eGestor ? 'Stock Geral' : 'Qtd na Bancada' ?></th>
                        <th>Mínimo</th>
                        <?php if ($eGestor): ?><th>Estado</th><?php endif; ?>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiais as $m): 
                        $emFalta = $eGestor ? ($m['stock_geral'] <= $m['stock_minimo']) : ($m['quantidade'] <= $m['stock_minimo']);
                    ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($m['nome']) ?></strong></td>
                            <td><?= strtoupper($m['unidade']) ?></td>
                            <td><?= $eGestor ? (float)$m['stock_geral'] : (float)$m['quantidade'] ?></td>
                            <td><?= (float)$m['stock_minimo'] ?></td>
                            <?php if ($eGestor): ?>
                                <td>
                                    <span class="badge <?= $emFalta ? 'low' : 'ok' ?>">
                                        <?= $emFalta ? 'Repor' : 'OK' ?>
                                    </span>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($eGestor): ?>
                                    <div style="display:flex; gap:0.5rem;">
                                        <a href="?editar=<?= $m['id'] ?>" class="btn-acao">Editar</a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="acao" value="apagar">
                                            <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                            <button type="submit" class="btn-acao btn-perigo" onclick="return confirm('Desativar este material?')">X</button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <button class="btn-acao">Pedir Reposição</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($materiais)): ?>
                        <tr><td colspan="<?= $eGestor ? '6' : '5' ?>" style="text-align:center; padding: 3rem; color: var(--muted);">Nenhum item encontrado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Formulário (Apenas Gestor) -->
        <?php if ($eGestor): ?>
        <div class="form-card">
            <h2><?= $editar ? 'Editar Material' : 'Novo Material' ?></h2>
            <form method="POST">
                <input type="hidden" name="acao" value="guardar">
                <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>

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

                <button type="submit" class="btn-submit"><?= $editar ? 'Guardar Alterações' : 'Adicionar Material' ?></button>
                <?php if ($editar): ?>
                    <a href="stock.php" class="btn-cancelar">Cancelar edição</a>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>