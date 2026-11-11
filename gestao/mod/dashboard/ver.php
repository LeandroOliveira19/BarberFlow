<?php
declare(strict_types=1);
// Módulo: dashboard / ver  (incluído por gestao/index.php)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

$nome = (string)$_SESSION['utilizador_nome'];
$tipo = (string)$_SESSION['tipo'];
$uid  = (int)$_SESSION['utilizador_id'];
// $pdo, $bid e $eGestor vêm do gestao/index.php

$ESTADOS = [
    'pendente_verificacao' => 'Por verificar',
    'confirmada'           => 'Confirmada',
    'concluida'            => 'Concluída',
    'cancelada'            => 'Cancelada',
    'expirada'             => 'Expirada',
];

if ($eGestor) {
    // ---- KPIs do negócio ----
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(s.preco),0) FROM marcacoes m JOIN servicos s ON m.servico_id = s.id WHERE m.barbearia_id = ? AND m.estado = 'concluida' AND DATE(m.inicio) = CURDATE()");
    $stmt->execute([$bid]);
    $receitaHoje = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(s.preco),0) FROM marcacoes m JOIN servicos s ON m.servico_id = s.id WHERE m.barbearia_id = ? AND m.estado = 'concluida' AND MONTH(m.inicio) = MONTH(CURDATE()) AND YEAR(m.inicio) = YEAR(CURDATE())");
    $stmt->execute([$bid]);
    $receitaMes = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND DATE(inicio) = CURDATE() AND estado IN ('confirmada','concluida','pendente_verificacao')");
    $stmt->execute([$bid]);
    $marcacoesHoje = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE barbearia_id = ?");
    $stmt->execute([$bid]);
    $totalClientes = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND estado = 'concluida' AND MONTH(inicio) = MONTH(CURDATE()) AND YEAR(inicio) = YEAR(CURDATE())");
    $stmt->execute([$bid]);
    $concluidasMes = (int)$stmt->fetchColumn();
    $ticketMedio = $concluidasMes > 0 ? $receitaMes / $concluidasMes : 0.0;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes WHERE barbearia_id = ? AND MONTH(criado_em) = MONTH(CURDATE()) AND YEAR(criado_em) = YEAR(CURDATE())");
    $stmt->execute([$bid]);
    $novosClientesMes = (int)$stmt->fetchColumn();

    // ---- Alertas ----
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM materiais WHERE barbearia_id = ? AND ativo = 1 AND stock_geral <= stock_minimo");
    $stmt->execute([$bid]);
    $stockEmFalta = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos_reposicao WHERE barbearia_id = ? AND estado = 'pendente'");
    $stmt->execute([$bid]);
    $pedidosPendentes = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND estado = 'pendente_verificacao'");
    $stmt->execute([$bid]);
    $porVerificar = (int)$stmt->fetchColumn();

    // ---- Serviços mais populares (30 dias) ----
    $stmt = $pdo->prepare("
        SELECT s.nome, COUNT(m.id) AS total
        FROM marcacoes m
        JOIN servicos s ON m.servico_id = s.id
        WHERE m.barbearia_id = ? AND m.inicio >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY s.id, s.nome
        ORDER BY total DESC
        LIMIT 5
    ");
    $stmt->execute([$bid]);
    $servicosPopulares = $stmt->fetchAll();

    // ---- Top barbeiros por faturação (30 dias) ----
    $stmt = $pdo->prepare("
        SELECT u.nome, COUNT(m.id) AS total,
               COALESCE(SUM(CASE WHEN m.estado = 'concluida' THEN s.preco ELSE 0 END), 0) AS receita
        FROM marcacoes m
        JOIN utilizadores u ON m.barbeiro_id = u.id
        JOIN servicos s ON m.servico_id = s.id
        WHERE m.barbearia_id = ? AND m.inicio >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY u.id, u.nome
        ORDER BY receita DESC, total DESC
    ");
    $stmt->execute([$bid]);
    $topBarbeiros = $stmt->fetchAll();

    // ---- Próximas marcações ----
    $stmt = $pdo->prepare("
        SELECT m.inicio, m.estado, c.nome AS cliente, u.nome AS barbeiro, s.nome AS servico
        FROM marcacoes m
        JOIN clientes c ON m.cliente_id = c.id
        JOIN utilizadores u ON m.barbeiro_id = u.id
        JOIN servicos s ON m.servico_id = s.id
        WHERE m.barbearia_id = ? AND m.inicio >= NOW() AND m.estado IN ('confirmada','pendente_verificacao')
        ORDER BY m.inicio ASC
        LIMIT 6
    ");
    $stmt->execute([$bid]);
    $proximas = $stmt->fetchAll();
} else {
    // ---- KPIs do barbeiro ----
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND barbeiro_id = ? AND DATE(inicio) = CURDATE() AND estado IN ('confirmada','concluida','pendente_verificacao')");
    $stmt->execute([$bid, $uid]);
    $marcacoesHoje = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND barbeiro_id = ? AND estado = 'concluida' AND MONTH(inicio) = MONTH(CURDATE()) AND YEAR(inicio) = YEAR(CURDATE())");
    $stmt->execute([$bid, $uid]);
    $concluidasMes = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pedidos_reposicao pr
        JOIN bancadas b ON pr.bancada_id = b.id
        WHERE pr.barbearia_id = ? AND b.barbeiro_id = ? AND pr.estado = 'pendente'
    ");
    $stmt->execute([$bid, $uid]);
    $pedidosPendentes = (int)$stmt->fetchColumn();

    // ---- Próximas marcações do barbeiro ----
    $stmt = $pdo->prepare("
        SELECT m.inicio, m.estado, c.nome AS cliente, s.nome AS servico
        FROM marcacoes m
        JOIN clientes c ON m.cliente_id = c.id
        JOIN servicos s ON m.servico_id = s.id
        WHERE m.barbearia_id = ? AND m.barbeiro_id = ? AND m.inicio >= NOW() AND m.estado IN ('confirmada','pendente_verificacao')
        ORDER BY m.inicio ASC
        LIMIT 6
    ");
    $stmt->execute([$bid, $uid]);
    $proximas = $stmt->fetchAll();
}
?>

<style>
    .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
    .kpi { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 1.4rem 1.5rem; border-left: 4px solid var(--gold); }
    .kpi .label { font-size: 0.78rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; }
    .kpi .valor { font-size: 2rem; font-weight: 600; color: #fff; margin-top: 0.35rem; }
    .kpi .valor small { font-size: 1rem; color: var(--gold); font-weight: 500; }
    .kpi .sub { font-size: 0.78rem; color: #4ade80; margin-top: 0.3rem; }

    .alertas { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem; }
    .alerta-card { display: flex; align-items: center; gap: 1rem; padding: 1.1rem 1.4rem; border-radius: 12px; border: 1px solid var(--border); text-decoration: none; background: var(--card); transition: 0.25s; }
    .alerta-card:hover { border-color: var(--gold); }
    .alerta-card .ico { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex: none; }
    .alerta-card .num { font-size: 1.6rem; font-weight: 700; line-height: 1; }
    .alerta-card .txt { font-size: 0.8rem; color: var(--muted); }
    .alerta-card .seta { margin-left: auto; color: var(--muted); font-size: 1.2rem; }
    .alerta-verde  .ico { background: rgba(34,197,94,0.15); }  .alerta-verde  .num { color: #4ade80; }
    .alerta-vermelho .ico { background: rgba(239,68,68,0.15); } .alerta-vermelho .num { color: #f87171; }
    .alerta-ambar   .ico { background: rgba(251,191,36,0.15); } .alerta-ambar   .num { color: #fbbf24; }

    .painel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
    @media (max-width: 900px) { .painel-grid { grid-template-columns: 1fr; } }
    .painel { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 1.5rem; }
    .painel h3 { font-family: 'Cinzel', serif; font-size: 1.05rem; color: var(--gold); margin: 0 0 1rem; }

    .bar-row { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 0.6rem; font-size: 0.88rem; }
    .bar-label { width: 45%; color: #f8f9fa; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-track { flex: 1; height: 8px; background: #1a1a1a; border-radius: 4px; overflow: hidden; }
    .bar-fill { height: 100%; background: linear-gradient(90deg, #cda47c, #e0be9a); border-radius: 4px; }
    .bar-val { width: 2rem; text-align: right; color: var(--muted); }

    .rank-lista { list-style: none; margin: 0; padding: 0; }
    .rank-lista li { display: flex; align-items: center; gap: 0.7rem; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
    .rank-lista li:last-child { border-bottom: none; }
    .rank-pos { width: 24px; height: 24px; border-radius: 50%; background: #1a1a1a; color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex: none; }
    .rank-nome { color: #f8f9fa; flex: 1; }
    .rank-total { color: var(--muted); }

    .proximas-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
    .proximas-table th { text-align: left; color: var(--muted); font-size: 0.75rem; text-transform: uppercase; padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border); }
    .proximas-table td { padding: 0.65rem 0.8rem; border-bottom: 1px solid rgba(255,255,255,0.05); }
    .badge { padding: 0.22rem 0.5rem; border-radius: 4px; font-size: 0.72rem; font-weight: 600; }
    .badge.confirmada { color: #4ade80; background: rgba(34,197,94,0.15); }
    .badge.pendente_verificacao { color: #fbbf24; background: rgba(251,191,36,0.15); }
    .vazio { color: var(--muted); font-size: 0.9rem; padding: 0.8rem 0; }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Dashboard</h1>
            <p style="color: var(--muted); margin-top: 0.5rem;">
                <?= $eGestor
                    ? 'Check-up rápido do negócio: receita, marcações, stock e alertas.'
                    : 'A tua agenda e o estado da tua bancada.' ?>
            </p>
        </div>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($eGestor): ?>
        <!-- KPIs -->
        <div class="kpis">
            <div class="kpi">
                <div class="label">Receita hoje</div>
                <div class="valor"><?= number_format($receitaHoje, 2, ',', '.') ?> <small>€</small></div>
            </div>
            <div class="kpi">
                <div class="label">Receita este mês</div>
                <div class="valor"><?= number_format($receitaMes, 2, ',', '.') ?> <small>€</small></div>
            </div>
            <div class="kpi">
                <div class="label">Ticket médio</div>
                <div class="valor"><?= number_format($ticketMedio, 2, ',', '.') ?> <small>€</small></div>
            </div>
            <div class="kpi">
                <div class="label">Marcações hoje</div>
                <div class="valor"><?= $marcacoesHoje ?></div>
            </div>
            <div class="kpi">
                <div class="label">Clientes registados</div>
                <div class="valor"><?= $totalClientes ?></div>
                <div class="sub"><?= $novosClientesMes > 0 ? '+' . $novosClientesMes . ' este mês' : 'sem novos este mês' ?></div>
            </div>
        </div>

        <!-- Alertas -->
        <div class="alertas">
            <a href="<?= urlModulo('stock', 'listar') ?>" class="alerta-card alerta-<?= $stockEmFalta > 0 ? 'vermelho' : 'verde' ?>">
                <span class="ico">📦</span>
                <div>
                    <div class="num"><?= $stockEmFalta ?></div>
                    <div class="txt">materiais em falta</div>
                </div>
                <span class="seta">→</span>
            </a>
            <a href="<?= urlModulo('pedidos', 'listar') ?>" class="alerta-card alerta-<?= $pedidosPendentes > 0 ? 'ambar' : 'verde' ?>">
                <span class="ico">🔁</span>
                <div>
                    <div class="num"><?= $pedidosPendentes ?></div>
                    <div class="txt">pedidos de reposição</div>
                </div>
                <span class="seta">→</span>
            </a>
            <a href="<?= urlModulo('agenda', 'listar') ?>" class="alerta-card alerta-<?= $porVerificar > 0 ? 'ambar' : 'verde' ?>">
                <span class="ico">⏳</span>
                <div>
                    <div class="num"><?= $porVerificar ?></div>
                    <div class="txt">marcações por verificar</div>
                </div>
                <span class="seta">→</span>
            </a>
        </div>

        <!-- Painéis: serviços + barbeiros -->
        <div class="painel-grid">
            <div class="painel">
                <h3>Serviços mais populares <span style="color:var(--muted); font-size:0.75rem;">(30 dias)</span></h3>
                <?php if (empty($servicosPopulares)): ?>
                    <div class="vazio">Ainda sem dados suficientes.</div>
                <?php else: ?>
                    <?php $maxS = max(array_column($servicosPopulares, 'total')); ?>
                    <?php foreach ($servicosPopulares as $s): ?>
                        <div class="bar-row">
                            <span class="bar-label"><?= htmlspecialchars($s['nome']) ?></span>
                            <div class="bar-track"><div class="bar-fill" style="width: <?= $maxS > 0 ? round(((int)$s['total'] / $maxS) * 100) : 0 ?>%"></div></div>
                            <span class="bar-val"><?= (int)$s['total'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="painel">
                <h3>Top barbeiros (faturação) <span style="color:var(--muted); font-size:0.75rem;">(30 dias)</span></h3>
                <?php if (empty($topBarbeiros)): ?>
                    <div class="vazio">Ainda sem dados.</div>
                <?php else: ?>
                    <ul class="rank-lista">
                        <?php foreach ($topBarbeiros as $i => $b): ?>
                            <li>
                                <span class="rank-pos"><?= $i + 1 ?></span>
                                <span class="rank-nome"><?= htmlspecialchars($b['nome']) ?></span>
                                <span class="rank-total"><?= number_format((float)$b['receita'], 2, ',', '.') ?> € · <?= (int)$b['total'] ?> marc.</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Próximas marcações -->
        <div class="painel">
            <h3>Próximas marcações</h3>
            <?php if (empty($proximas)): ?>
                <div class="vazio">Sem marcações agendadas.</div>
            <?php else: ?>
                <table class="proximas-table">
                    <thead>
                        <tr><th>Data</th><th>Cliente</th><th>Barbeiro</th><th>Serviço</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proximas as $p): ?>
                            <tr>
                                <td><?= date('d/m H:i', strtotime($p['inicio'])) ?></td>
                                <td><?= htmlspecialchars($p['cliente']) ?></td>
                                <td><?= htmlspecialchars($p['barbeiro']) ?></td>
                                <td><?= htmlspecialchars($p['servico']) ?></td>
                                <td><span class="badge <?= htmlspecialchars($p['estado']) ?>"><?= htmlspecialchars($ESTADOS[$p['estado']] ?? $p['estado']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- KPIs do barbeiro -->
        <div class="kpis">
            <div class="kpi">
                <div class="label">Marcações hoje</div>
                <div class="valor"><?= $marcacoesHoje ?></div>
            </div>
            <div class="kpi">
                <div class="label">Serviços este mês</div>
                <div class="valor"><?= $concluidasMes ?></div>
            </div>
            <div class="kpi">
                <div class="label">Pedidos pendentes</div>
                <div class="valor"><?= $pedidosPendentes ?></div>
            </div>
        </div>

        <div class="painel">
            <h3>Próximas marcações</h3>
            <?php if (empty($proximas)): ?>
                <div class="vazio">Sem marcações agendadas.</div>
            <?php else: ?>
                <table class="proximas-table">
                    <thead>
                        <tr><th>Data</th><th>Cliente</th><th>Serviço</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proximas as $p): ?>
                            <tr>
                                <td><?= date('d/m H:i', strtotime($p['inicio'])) ?></td>
                                <td><?= htmlspecialchars($p['cliente']) ?></td>
                                <td><?= htmlspecialchars($p['servico']) ?></td>
                                <td><span class="badge <?= htmlspecialchars($p['estado']) ?>"><?= htmlspecialchars($ESTADOS[$p['estado']] ?? $p['estado']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
