<?php
declare(strict_types=1);
// Módulo: pedidos / listar — incluído por gestao/index.php
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

require_once __DIR__ . '/_comum.php';
$msg = '';
$erro = '';

// =========================================================
// FILTRO POR ESTADO
// =========================================================
$filtro_estado = $_GET['estado'] ?? '';
if (!array_key_exists($filtro_estado, $ESTADOS)) {
    $filtro_estado = '';
}

// =========================================================
// LISTAGEM DE PEDIDOS
// =========================================================
$sql = "SELECT pr.id, pr.quantidade, pr.estado,
               m.nome AS material_nome, m.unidade,
               b.nome AS bancada_nome, u.nome AS barbeiro_nome
        FROM pedidos_reposicao pr
        JOIN materiais m ON pr.material_id = m.id
        JOIN bancadas b ON pr.bancada_id = b.id
        LEFT JOIN utilizadores u ON b.barbeiro_id = u.id
        WHERE pr.barbearia_id = ?";
$params = [$bid];

if ($filtro_estado !== '') {
    $sql .= " AND pr.estado = ?";
    $params[] = $filtro_estado;
}
$sql .= " ORDER BY pr.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll();
?>

<style>
    .filter-bar { padding: 1rem; background: #1a1a1a; border-bottom: 1px solid var(--border); display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; }
    .filter-bar select { padding: 0.6rem 1rem; background: #0a0a0a; border: 1px solid var(--border); border-radius: 6px; color: #fff; }
    .filter-bar button { padding: 0.6rem 1.5rem; background: var(--gold); color: #000; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; transition: 0.3s; }
    .filter-bar button:hover { background: #e0be9a; }
    .btn-limpar { color: var(--muted); text-decoration: none; font-size: 0.85rem; }
    .btn-limpar:hover { color: #fff; }

    .badge { padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; display: inline-block; }
    .badge.pendente  { color: #fbbf24; background: rgba(251, 191, 36, 0.15); }
    .badge.aprovado  { color: #4ade80; background: rgba(34, 197, 94, 0.15); }
    .badge.entregue  { color: #60a5fa; background: rgba(96, 165, 250, 0.15); }
    .badge.rejeitado { color: #f87171; background: rgba(239, 68, 68, 0.15); }

    .acoes { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
    .btn-aprovar { text-decoration: none; color: #065f46; background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.4); padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.85rem; cursor: pointer; transition: 0.3s; display: inline-block; }
    .btn-aprovar:hover { background: rgba(34, 197, 94, 0.35); }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Pedidos de Stock</h1>
            <p style="color: var(--muted); margin-top: 0.5rem;">
                Aprova ou rejeita os pedidos de reposição enviados pelos barbeiros.
            </p>
        </div>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <div class="table-container">
        <form method="GET" action="index.php" class="filter-bar">
<input type="hidden" name="m" value="pedidos"><input type="hidden" name="a" value="listar">
            <select name="estado">
                <option value="">Todos os estados</option>
                <?php foreach ($ESTADOS as $valor => $label): ?>
                    <option value="<?= $valor ?>" <?= $filtro_estado === $valor ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filtrar</button>
            <?php if ($filtro_estado !== ''): ?>
                <a href="<?= e(urlModulo('pedidos', 'listar')) ?>" class="btn-limpar">Limpar</a>
            <?php endif; ?>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Material</th>
                    <th>Quantidade</th>
                    <th>Bancada / Barbeiro</th>
                    <th>Estado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $p): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($p['material_nome']) ?></strong><br>
                            <small style="color:#666;"><?= htmlspecialchars(strtoupper($p['unidade'] ?? 'un')) ?></small>
                        </td>
                        <td><?= (float)$p['quantidade'] ?> <?= htmlspecialchars(strtoupper($p['unidade'] ?? 'un')) ?></td>
                        <td>
                            <?= htmlspecialchars($p['bancada_nome'] ?? '-') ?><br>
                            <small style="color:#666;"><?= htmlspecialchars($p['barbeiro_nome'] ?? 'Livre') ?></small>
                        </td>
                        <td>
                            <span class="badge <?= htmlspecialchars($p['estado']) ?>">
                                <?= htmlspecialchars($ESTADOS[$p['estado']] ?? $p['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="acoes">
                                <?php if ($p['estado'] === 'pendente'): ?>
                                    <form method="POST" action="<?= e(urlModulo('pedidos', 'aprovar')) ?>" style="display:inline;">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-aprovar">Aprovar</button>
                                    </form>
                                    <form method="POST" action="<?= e(urlModulo('pedidos', 'rejeitar')) ?>" style="display:inline;">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-acao btn-perigo" onclick="return confirm('Rejeitar este pedido?')">Rejeitar</button>
                                    </form>
                                <?php elseif ($p['estado'] === 'aprovado'): ?>
                                    <form method="POST" action="<?= e(urlModulo('pedidos', 'entregar')) ?>" style="display:inline;">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn-acao">Marcar Entregue</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--muted); font-size: 0.85rem;">—</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($pedidos)): ?>
                    <tr><td colspan="5" style="text-align:center; padding: 3rem; color: var(--muted);">Nenhum pedido encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
