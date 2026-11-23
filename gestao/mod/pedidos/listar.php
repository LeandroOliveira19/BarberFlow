<?php
declare(strict_types=1);
// Módulo: pedidos / listar — incluído por gestao/index.php
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

require_once __DIR__ . '/_comum.php';
$msg = '';
$erro = '';

// Título de cada grupo de cards; os já resolvidos ficam fechados para não alongar a página
$GRUPOS = [
    'pendente'  => ['titulo' => 'Pendentes',                 'fechado' => false],
    'aprovado'  => ['titulo' => 'Aprovados — por entregar',  'fechado' => false],
    'entregue'  => ['titulo' => 'Entregues',                 'fechado' => true],
    'rejeitado' => ['titulo' => 'Rejeitados',                'fechado' => true],
];

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
$sql = "SELECT pr.id, pr.quantidade, pr.estado, pr.criado_em,
               m.nome AS material_nome, m.unidade,
               b.nome AS bancada_nome, u.nome AS barbeiro_nome
        FROM pedidos_reposicao pr
        JOIN materiais m ON pr.material_id = m.id AND m.barbearia_id = pr.barbearia_id
        JOIN bancadas b ON pr.bancada_id = b.id AND b.barbearia_id = pr.barbearia_id
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

$porEstado = [];
foreach ($pedidos as $p) {
    $porEstado[$p['estado']][] = $p;
}
?>

<style>
    .filtro-pedidos { margin-bottom: 1.6rem; }

    .badge.aprovado  { color: #4ade80; background: rgba(34, 197, 94, 0.15); }
    .badge.entregue  { color: #60a5fa; background: rgba(96, 165, 250, 0.15); }
    .badge.rejeitado { color: #f87171; background: rgba(239, 68, 68, 0.15); }

    /* Card de cada pedido: cor da margem segundo o estado */
    .pedido-card { background: var(--card); border: 1px solid var(--border); border-left: 3px solid #fbbf24; border-radius: 20px; padding: 0.9rem 1rem; display: flex; flex-direction: column; gap: 0.55rem; }
    .pedido-card.aprovado  { border-left-color: #4ade80; }
    .pedido-card.entregue  { border-left-color: #60a5fa; }
    .pedido-card.rejeitado { border-left-color: #f87171; opacity: 0.8; }
    .pedido-topo { display: flex; justify-content: space-between; align-items: flex-start; gap: 0.5rem; }
    .pedido-material { font-weight: 500; color: #fff; }
    .pedido-qtd { font-size: 1.3rem; color: var(--gold); font-weight: 600; }
    .pedido-qtd small { font-size: 0.8rem; color: var(--muted); font-weight: 400; }
    .pedido-quem { font-size: 0.85rem; color: var(--texto); }
    .pedido-quem small, .pedido-data { color: var(--muted); font-size: 0.78rem; }

    .grupo-fechado > summary { list-style: none; cursor: pointer; user-select: none; }
    .grupo-fechado > summary::-webkit-details-marker { display: none; }
    .grupo-fechado > summary::after { content: '▸'; color: var(--muted); margin-left: auto; transition: transform 0.2s; }
    .grupo-fechado[open] > summary::after { transform: rotate(90deg); }

    .btn-aprovar { text-decoration: none; color: #4ade80; background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.4); padding: 0.4rem 0.8rem; border-radius: 999px; font-size: 0.85rem; cursor: pointer; transition: 0.3s; display: inline-block; }
    .btn-aprovar:hover { background: rgba(34, 197, 94, 0.3); }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Pedidos de Stock</h1>
            <p style="color: var(--muted); margin-top: 0.5rem;">
                Aprova ou rejeita os pedidos de reposição enviados pelos barbeiros.
            </p>
        </div>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <div class="filtro-pedidos">
    <nav class="segmentado" aria-label="Filtrar por estado">
        <a href="<?= e(urlModulo('pedidos', 'listar')) ?>" class="<?= $filtro_estado === '' ? 'on' : '' ?>"<?= $filtro_estado === '' ? ' aria-current="true"' : '' ?>>Todos</a>
        <?php foreach ($ESTADOS as $valor => $label): ?>
            <a href="<?= e(urlModulo('pedidos', 'listar', ['estado' => $valor])) ?>" class="<?= $filtro_estado === $valor ? 'on' : '' ?>"<?= $filtro_estado === $valor ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    </div>

    <?php if (empty($pedidos)): ?>
        <div class="sem-cards">Nenhum pedido encontrado.</div>
    <?php endif; ?>

    <?php foreach ($GRUPOS as $estado => $grupo): ?>
        <?php if (empty($porEstado[$estado])) continue; ?>
        <?php
        $lista = $porEstado[$estado];
        $fechado = $grupo['fechado'] && $filtro_estado !== $estado;
        $tagGrupo = $fechado ? 'details' : 'section';
        $tagTitulo = $fechado ? 'summary' : 'h2';
        ?>
        <<?= $tagGrupo ?> class="grupo-cards<?= $fechado ? ' grupo-fechado' : '' ?>">
            <<?= $tagTitulo ?> class="grupo-cards-titulo">
                <?= e($grupo['titulo']) ?>
                <span class="n"><?= count($lista) ?> <?= count($lista) === 1 ? 'pedido' : 'pedidos' ?></span>
            </<?= $tagTitulo ?>>
            <div class="cards-grid">
                <?php foreach ($lista as $p): ?>
                    <?php $unidade = strtoupper($p['unidade'] ?? 'un'); ?>
                    <div class="pedido-card <?= e($p['estado']) ?>">
                        <div class="pedido-topo">
                            <span class="pedido-material"><?= e($p['material_nome']) ?></span>
                            <span class="badge <?= e($p['estado']) ?>"><?= e($ESTADOS[$p['estado']] ?? $p['estado']) ?></span>
                        </div>
                        <div class="pedido-qtd"><?= (float)$p['quantidade'] ?> <small><?= e($unidade) ?></small></div>
                        <div class="pedido-quem">
                            <?= e($p['bancada_nome'] ?? '-') ?>
                            <small>· <?= e($p['barbeiro_nome'] ?? 'Livre') ?></small>
                        </div>
                        <div class="pedido-data">Pedido a <?= e(date('d/m/Y H:i', strtotime((string)$p['criado_em']))) ?></div>

                        <?php if ($p['estado'] === 'pendente'): ?>
                            <div class="item-acoes">
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
                            </div>
                        <?php elseif ($p['estado'] === 'aprovado'): ?>
                            <div class="item-acoes">
                                <form method="POST" action="<?= e(urlModulo('pedidos', 'entregar')) ?>" style="display:inline;">
                                    <?= campoCsrf() ?>
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-acao">Marcar Entregue</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </<?= $tagGrupo ?>>
    <?php endforeach; ?>
</div>
