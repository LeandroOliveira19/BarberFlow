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

    // Receita do mesmo dia da semana anterior (para a comparação) e dos últimos 7 dias (mini-gráfico)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(s.preco),0) FROM marcacoes m JOIN servicos s ON m.servico_id = s.id AND s.barbearia_id = m.barbearia_id WHERE m.barbearia_id = ? AND m.estado = 'concluida' AND DATE(m.inicio) = DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
    $stmt->execute([$bid]);
    $variacaoHoje = variacaoPercentual($receitaHoje, (float)$stmt->fetchColumn());

    $stmt = $pdo->prepare("SELECT DATE(m.inicio) AS dia, SUM(s.preco) AS total FROM marcacoes m JOIN servicos s ON m.servico_id = s.id AND s.barbearia_id = m.barbearia_id WHERE m.barbearia_id = ? AND m.estado = 'concluida' AND m.inicio >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND m.inicio < DATE_ADD(CURDATE(), INTERVAL 1 DAY) GROUP BY DATE(m.inicio)");
    $stmt->execute([$bid]);
    $porDia = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $receita7dias = [];
    for ($d = 6; $d >= 0; $d--) {
        $dia = date('Y-m-d', strtotime("-$d day"));
        $receita7dias[$dia] = (float)($porDia[$dia] ?? 0);
    }
    $maxReceita7 = max($receita7dias) ?: 1.0;

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
<?php
$primeiroNome = explode(' ', trim($nome))[0] ?? '';
$icoStock   = '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>';
$icoPedido  = '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 13l2 2 4-4"/>';
$icoRelogio = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
?>

<style>
    .saudacao-data { color: var(--muted); font-size: 0.95rem; margin-bottom: 0.15rem; }
    .header h1 .ouro { color: var(--gold); }

    .kpis { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2.4rem; }
    .kpis > .kpi { flex: 1 1 180px; min-width: 0; }
    .kpis > .kpi.destaque { flex: 2 1 320px; }
    .kpi { background: var(--card); border: 1px solid var(--border); border-radius: 24px; padding: 1.3rem 1.4rem; }
    .kpi.destaque { background: radial-gradient(120% 140% at 0% 0%, rgba(205,164,124,0.22), transparent 60%), var(--card); }
    .kpi .label { font-size: 0.82rem; color: var(--muted); }
    .kpi .valor { font-size: 2.3rem; font-weight: 600; letter-spacing: -0.045em; color: var(--texto); margin-top: 0.2rem; line-height: 1.1; }
    .kpi.destaque .valor { font-size: 3.1rem; }
    .kpi .valor small { font-size: 0.55em; color: var(--muted); font-weight: 500; letter-spacing: 0; }
    .kpi .sub { font-size: 0.8rem; color: var(--muted); margin-top: 0.3rem; }
    .kpi .sobe { color: #30d158; } .kpi .desce { color: #ff453a; }
    .spark { display: flex; align-items: flex-end; gap: 5px; height: 42px; margin-top: 0.8rem; }
    .spark i { flex: 1; min-height: 3px; background: rgba(205,164,124,0.35); border-radius: 3px; }
    .spark i:last-child { background: var(--gold); }

    .secao-titulo { display: flex; align-items: center; gap: 0.6rem; font-size: 1.2rem; font-weight: 600; letter-spacing: -0.02em; margin: 0 0 0.85rem; }
    .secao-titulo .n { font-size: 0.8rem; font-weight: 400; color: var(--muted); }
    .secao-titulo a { margin-left: auto; font-size: 0.85rem; font-weight: 500; color: var(--gold); text-decoration: none; }
    .duas { display: grid; grid-template-columns: 1.5fr 1fr; gap: 1.4rem; align-items: start; margin-bottom: 2.4rem; }
    @media (max-width: 1000px) { .duas { grid-template-columns: 1fr; } }

    .hora { width: 58px; flex: none; font-weight: 600; font-size: 0.95rem; font-variant-numeric: tabular-nums; }
    .hora small { display: block; color: var(--muted); font-size: 0.72rem; font-weight: 400; }
    .ico-lista { width: 36px; height: 36px; flex: none; border-radius: 10px; display: grid; place-items: center; }
    .ico-lista svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
    .ico-lista.vermelho { background: rgba(255,69,58,0.14); color: #ff453a; }
    .ico-lista.ambar { background: rgba(255,214,10,0.13); color: #ffd60a; }
    .ico-lista.verde { background: rgba(48,209,88,0.14); color: #30d158; }
    .chev { color: #48484a; font-size: 1.1rem; }
    .badge.confirmada { color: #30d158; background: rgba(48,209,88,0.14); }
    .badge.pendente_verificacao { color: #ffd60a; background: rgba(255,214,10,0.13); }

    .painel-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.4rem; }
    @media (max-width: 900px) { .painel-grid { grid-template-columns: 1fr; } }
    .painel { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 1.3rem 1.4rem; }
    .bar-row { display: flex; align-items: center; gap: 0.7rem; margin-bottom: 0.6rem; font-size: 0.88rem; }
    .bar-label { width: 45%; color: var(--texto); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .bar-track { flex: 1; height: 8px; background: var(--superficie); border-radius: 999px; overflow: hidden; }
    .bar-fill { height: 100%; background: linear-gradient(90deg, #cda47c, #e0be9a); border-radius: 999px; }
    .bar-val { width: 2rem; text-align: right; color: var(--muted); }
    .rank-lista { list-style: none; margin: 0; padding: 0; }
    .rank-lista li { display: flex; align-items: center; gap: 0.7rem; padding: 0.55rem 0; border-bottom: 1px solid var(--linha); font-size: 0.9rem; }
    .rank-lista li:last-child { border-bottom: none; }
    .rank-pos { width: 24px; height: 24px; border-radius: 50%; background: var(--superficie); color: var(--gold); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; flex: none; }
    .rank-nome { color: var(--texto); flex: 1; }
    .rank-total { color: var(--muted); }
    .vazio { color: var(--muted); font-size: 0.9rem; padding: 0.4rem 0; }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <p class="saudacao-data"><?= e(dataPorExtenso(time())) ?></p>
            <h1><?= e(saudacaoPorHora((int)date('G'))) ?>, <span class="ouro"><?= e($primeiroNome) ?></span>.</h1>
        </div>
    </div>

    <?php if ($eGestor): ?>
        <div class="kpis">
            <div class="kpi destaque">
                <div class="label">Receita hoje</div>
                <div class="valor"><?= number_format($receitaHoje, 2, ',', '.') ?> <small>€</small></div>
                <div class="sub">
                    <?php if ($variacaoHoje === null): ?>
                        Sem receita no mesmo dia da semana passada
                    <?php else: ?>
                        <span class="<?= $variacaoHoje >= 0 ? 'sobe' : 'desce' ?>"><?= $variacaoHoje >= 0 ? '▲' : '▼' ?> <?= abs($variacaoHoje) ?>%</span> face ao mesmo dia da semana passada
                    <?php endif; ?>
                </div>
                <div class="spark" role="img" aria-label="Receita dos últimos 7 dias">
                    <?php foreach ($receita7dias as $dia => $valor): ?>
                        <i style="height: <?= max(4, (int)round($valor / $maxReceita7 * 100)) ?>%" title="<?= e(date('d/m', strtotime($dia)) . ': ' . number_format($valor, 2, ',', '.') . ' €') ?>"></i>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="kpi">
                <div class="label">Marcações hoje</div>
                <div class="valor"><?= $marcacoesHoje ?></div>
                <div class="sub">receita do mês: <?= number_format($receitaMes, 2, ',', '.') ?> €</div>
            </div>
            <div class="kpi">
                <div class="label">Ticket médio</div>
                <div class="valor"><?= number_format($ticketMedio, 2, ',', '.') ?> <small>€</small></div>
                <div class="sub">este mês · <?= $concluidasMes ?> serviços</div>
            </div>
            <div class="kpi">
                <div class="label">Clientes</div>
                <div class="valor"><?= $totalClientes ?></div>
                <div class="sub"><?= $novosClientesMes > 0 ? '<span class="sobe">+' . $novosClientesMes . '</span> este mês' : 'sem novos este mês' ?></div>
            </div>
        </div>

        <div class="duas">
            <div>
                <h2 class="secao-titulo">Próximas marcações <a href="<?= e(urlModulo('agenda', 'listar')) ?>">Ver agenda</a></h2>
                <div class="lista">
                    <?php if (empty($proximas)): ?>
                        <div class="lista-vazia">Sem marcações agendadas.</div>
                    <?php endif; ?>
                    <?php foreach ($proximas as $p): ?>
                        <?php $ts = strtotime($p['inicio']); ?>
                        <div class="lista-linha">
                            <span class="hora"><?= date('H:i', $ts) ?><small><?= date('d/m', $ts) ?></small></span>
                            <div class="txt"><b><?= e($p['cliente']) ?></b><small><?= e($p['servico']) ?> · com <?= e($p['barbeiro']) ?></small></div>
                            <div class="dir"><span class="badge <?= e($p['estado']) ?>"><?= e($ESTADOS[$p['estado']] ?? $p['estado']) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h2 class="secao-titulo">Precisa de atenção</h2>
                <div class="lista">
                    <a class="lista-linha" href="<?= e(urlModulo('stock', 'listar', ['estado' => 'low'])) ?>">
                        <span class="ico-lista <?= $stockEmFalta > 0 ? 'vermelho' : 'verde' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $icoStock ?></svg></span>
                        <div class="txt"><b><?= $stockEmFalta ?> <?= $stockEmFalta === 1 ? 'material em falta' : 'materiais em falta' ?></b><small>no stock geral</small></div>
                        <div class="dir"><span class="chev">›</span></div>
                    </a>
                    <a class="lista-linha" href="<?= e(urlModulo('pedidos', 'listar', ['estado' => 'pendente'])) ?>">
                        <span class="ico-lista <?= $pedidosPendentes > 0 ? 'ambar' : 'verde' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $icoPedido ?></svg></span>
                        <div class="txt"><b><?= $pedidosPendentes ?> <?= $pedidosPendentes === 1 ? 'pedido de reposição' : 'pedidos de reposição' ?></b><small>à espera de aprovação</small></div>
                        <div class="dir"><span class="chev">›</span></div>
                    </a>
                    <a class="lista-linha" href="<?= e(urlModulo('agenda', 'listar')) ?>">
                        <span class="ico-lista <?= $porVerificar > 0 ? 'ambar' : 'verde' ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><?= $icoRelogio ?></svg></span>
                        <div class="txt"><b><?= $porVerificar ?> <?= $porVerificar === 1 ? 'marcação por verificar' : 'marcações por verificar' ?></b><small>o cliente ainda não confirmou o código</small></div>
                        <div class="dir"><span class="chev">›</span></div>
                    </a>
                </div>
            </div>
        </div>

        <div class="painel-grid">
            <div>
                <h2 class="secao-titulo">Serviços mais populares <span class="n">30 dias</span></h2>
                <div class="painel">
                    <?php if (empty($servicosPopulares)): ?>
                        <div class="vazio">Ainda sem dados suficientes.</div>
                    <?php else: ?>
                        <?php $maxS = max(array_column($servicosPopulares, 'total')); ?>
                        <?php foreach ($servicosPopulares as $s): ?>
                            <div class="bar-row">
                                <span class="bar-label"><?= e($s['nome']) ?></span>
                                <div class="bar-track"><div class="bar-fill" style="width: <?= $maxS > 0 ? round(((int)$s['total'] / $maxS) * 100) : 0 ?>%"></div></div>
                                <span class="bar-val"><?= (int)$s['total'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <h2 class="secao-titulo">Top barbeiros <span class="n">faturação · 30 dias</span></h2>
                <div class="painel">
                    <?php if (empty($topBarbeiros)): ?>
                        <div class="vazio">Ainda sem dados.</div>
                    <?php else: ?>
                        <ul class="rank-lista">
                            <?php foreach ($topBarbeiros as $i => $b): ?>
                                <li>
                                    <span class="rank-pos"><?= $i + 1 ?></span>
                                    <span class="rank-nome"><?= e($b['nome']) ?></span>
                                    <span class="rank-total"><?= number_format((float)$b['receita'], 2, ',', '.') ?> € · <?= (int)$b['total'] ?> marc.</span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="kpis">
            <div class="kpi destaque">
                <div class="label">Marcações hoje</div>
                <div class="valor"><?= $marcacoesHoje ?></div>
                <div class="sub">na tua agenda</div>
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

        <h2 class="secao-titulo">Próximas marcações <a href="<?= e(urlModulo('agenda', 'listar')) ?>">Ver agenda</a></h2>
        <div class="lista">
            <?php if (empty($proximas)): ?>
                <div class="lista-vazia">Sem marcações agendadas.</div>
            <?php endif; ?>
            <?php foreach ($proximas as $p): ?>
                <?php $ts = strtotime($p['inicio']); ?>
                <div class="lista-linha">
                    <span class="hora"><?= date('H:i', $ts) ?><small><?= date('d/m', $ts) ?></small></span>
                    <div class="txt"><b><?= e($p['cliente']) ?></b><small><?= e($p['servico']) ?></small></div>
                    <div class="dir"><span class="badge <?= e($p['estado']) ?>"><?= e($ESTADOS[$p['estado']] ?? $p['estado']) ?></span></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
