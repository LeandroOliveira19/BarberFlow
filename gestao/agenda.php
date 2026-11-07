<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/marcacoes.php';
session_start();

if (!isset($_SESSION['utilizador_id'], $_SESSION['barbearia_id'], $_SESSION['tipo'])) {
    header('Location: login.php');
    exit;
}

$uid             = (int)$_SESSION['utilizador_id'];
$bid             = (int)$_SESSION['barbearia_id'];
$tipo            = (string)$_SESSION['tipo'];
$nome_utilizador = (string)($_SESSION['utilizador_nome'] ?? '');
$eGestor         = ($tipo === 'gestor');

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

const ESTADOS = [
    'pendente_verificacao' => 'Por verificar',
    'confirmada'           => 'Confirmada',
    'concluida'            => 'Concluída',
    'cancelada'            => 'Cancelada',
    'expirada'             => 'Expirada',
];
const ACOES = [
    'pendente_verificacao' => ['confirmada' => 'Confirmar', 'cancelada' => 'Cancelar'],
    'confirmada'           => ['concluida'  => 'Concluir',  'cancelada' => 'Cancelar'],
    'concluida'            => [],
    'cancelada'            => [],
    'expirada'             => [],
];

expirarPendentes($pdo, $bid);

$vista = in_array($_GET['vista'] ?? '', ['dia', 'semana', 'proximas'], true) ? $_GET['vista'] : 'dia';

$dataStr = (string)($_GET['data'] ?? '');
$dataRef = DateTime::createFromFormat('!Y-m-d', $dataStr);
if (!$dataRef || $dataRef->format('Y-m-d') !== $dataStr) {
    $dataRef = new DateTime('today');
}

$filtroEstado   = isset(ESTADOS[$_GET['estado'] ?? '']) ? (string)$_GET['estado'] : '';
$filtroBarbeiro = $eGestor ? (int)($_GET['barbeiro'] ?? 0) : 0;

function urlAgenda(array $sobrepor = []): string {
    global $vista, $dataRef, $filtroEstado, $filtroBarbeiro;
    $p = array_merge([
        'vista'    => $vista,
        'data'     => $dataRef->format('Y-m-d'),
        'estado'   => $filtroEstado,
        'barbeiro' => $filtroBarbeiro ?: '',
    ], $sobrepor);
    return 'agenda.php?' . http_build_query(array_filter($p, fn($v) => $v !== '' && $v !== null));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Pedido inválido.');
    }
    $id   = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $novo = (string)($_POST['estado'] ?? '');

    if ($id && isset(ESTADOS[$novo])) {
        $sql    = "UPDATE marcacoes SET estado = ? WHERE id = ? AND barbearia_id = ?";
        $params = [$novo, $id, $bid];
        if (!$eGestor) { 
            $sql     .= " AND barbeiro_id = ?";
            $params[] = $uid;
        }
        $pdo->prepare($sql)->execute($params);
    }
    header('Location: ' . urlAgenda());
    exit;
}

$passo = null;
if ($vista === 'dia') {
    $de = clone $dataRef;
    $ate = (clone $de)->modify('+1 day');
    $passo = '1 day';
    $tituloIntervalo = dataExtenso($de);
} elseif ($vista === 'semana') {
    $de = (clone $dataRef)->modify('monday this week');
    $ate = (clone $de)->modify('+7 days');
    $passo = '7 days';
    $fimSemana = (clone $ate)->modify('-1 day');
    $tituloIntervalo = $de->format('j') . ' ' . MESES[(int)$de->format('n') - 1] . ' — ' . $fimSemana->format('j') . ' ' . MESES[(int)$fimSemana->format('n') - 1];
} else {
    $de = new DateTime('today');
    $ate = (clone $de)->modify('+90 days');
    $tituloIntervalo = 'Próximos 90 dias';
}

$sql = "
    SELECT m.id, m.inicio, m.fim, m.estado,
           c.nome AS cliente_nome, c.telemovel AS cliente_telemovel, c.email AS cliente_email, m.notas,
           s.nome AS servico_nome, s.preco AS servico_preco,
           u.nome AS barbeiro_nome
    FROM marcacoes m
    JOIN clientes    c ON c.id = m.cliente_id  AND c.barbearia_id = m.barbearia_id
    JOIN servicos    s ON s.id = m.servico_id  AND s.barbearia_id = m.barbearia_id
    JOIN utilizadores u ON u.id = m.barbeiro_id AND u.barbearia_id = m.barbearia_id
    WHERE m.barbearia_id = ?
      AND m.inicio >= ? AND m.inicio < ?
";
$params = [$bid, $de->format('Y-m-d H:i:s'), $ate->format('Y-m-d H:i:s')];

if (!$eGestor) {
    $sql .= " AND m.barbeiro_id = ?";
    $params[] = $uid;
} elseif ($filtroBarbeiro > 0) {
    $sql .= " AND m.barbeiro_id = ?";
    $params[] = $filtroBarbeiro;
}
if ($filtroEstado !== '') {
    $sql .= " AND m.estado = ?";
    $params[] = $filtroEstado;
} else {
    $sql .= " AND m.estado <> 'expirada'";
}
$sql .= " ORDER BY m.inicio ASC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$marcacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$porDia = [];
foreach ($marcacoes as $m) {
    $porDia[substr($m['inicio'], 0, 10)][] = $m;
}
$contagem = array_count_values(array_column($marcacoes, 'estado'));
$receita  = 0.0;
foreach ($marcacoes as $m) {
    if ($m['estado'] === 'concluida') $receita += (float)$m['servico_preco'];
}

$barbeiros = [];
if ($eGestor) {
    $b = $pdo->prepare("SELECT id, nome FROM utilizadores WHERE barbearia_id = ? AND ativo = 1 AND tipo IN ('barbeiro','gestor') ORDER BY nome");
    $b->execute([$bid]);
    $barbeiros = $b->fetchAll(PDO::FETCH_ASSOC);
}

$sl = $pdo->prepare("SELECT slug FROM barbearias WHERE id = ?");
$sl->execute([$bid]);
$slug = (string)$sl->fetchColumn();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
    .toolbar { display: flex; flex-wrap: wrap; gap: 1rem; align-items: center; justify-content: space-between; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1.5rem; }
    .tabs, .nav-data { display: flex; gap: 0.4rem; align-items: center; }
    .tabs a, .nav-data a { color: var(--muted); text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.9rem; border: 1px solid transparent; transition: 0.3s; }
    .tabs a:hover, .nav-data a:hover { color: var(--gold); }
    .tabs a.active { background: rgba(205,164,124,0.12); color: var(--gold); border-color: rgba(205,164,124,0.3); }
    .intervalo { font-family: 'Cinzel', serif; font-size: 1.05rem; padding: 0 0.6rem; min-width: 160px; text-align: center; }
    .filtros { display: flex; gap: 0.6rem; flex-wrap: wrap; }
    .filtros select, .filtros input, .filtros button { background: #1b1b1b; color: #f8f9fa; border: 1px solid var(--border); border-radius: 6px; padding: 0.5rem 0.8rem; font-family: inherit; font-size: 0.9rem; }
    .filtros button { cursor: pointer; color: var(--gold); border-color: rgba(205,164,124,0.3); }
    .resumo { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .resumo div { background: var(--card); border: 1px solid var(--border); border-left: 3px solid var(--gold); border-radius: 10px; padding: 1rem 1.2rem; }
    .resumo small { display: block; color: var(--muted); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.08em; }
    .resumo strong { font-size: 1.7rem; font-weight: 500; }
    .dia-titulo { font-family: 'Cinzel', serif; color: var(--gold); font-size: 1.1rem; margin: 2rem 0 0.8rem; }
    .dia-titulo:first-child { margin-top: 0; }
    .marcacao { display: grid; grid-template-columns: 110px 1fr auto auto; gap: 1.2rem; align-items: center; background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 1.1rem 1.4rem; margin-bottom: 0.7rem; }
    .marcacao.cancelada, .marcacao.expirada { opacity: 0.55; }
    .hora { font-size: 1.3rem; font-weight: 500; }
    .hora small { display: block; font-size: 0.8rem; color: var(--muted); font-weight: 300; }
    .cliente strong { display: block; font-weight: 500; }
    .cliente span { color: var(--muted); font-size: 0.9rem; }
    .cliente a { color: var(--muted); text-decoration: none; }
    .cliente a:hover { color: var(--gold); }
    .badge { font-size: 0.78rem; padding: 0.3rem 0.8rem; border-radius: 50px; border: 1px solid; white-space: nowrap; }
    .badge.pendente_verificacao { color: #fbbf24; border-color: rgba(251,191,36,0.4);  background: rgba(251,191,36,0.08); }
    .badge.confirmada { color: #60a5fa; border-color: rgba(96,165,250,0.4);  background: rgba(96,165,250,0.08); }
    .badge.concluida  { color: #4ade80; border-color: rgba(74,222,128,0.4);  background: rgba(74,222,128,0.08); }
    .badge.cancelada  { color: #f87171; border-color: rgba(248,113,113,0.4); background: rgba(248,113,113,0.08); }
    .badge.expirada   { color: #9ca3af; border-color: rgba(156,163,175,0.4); background: rgba(156,163,175,0.08); }
    .notas { display: block; margin-top: 0.35rem; font-style: italic; color: var(--muted); font-size: 0.85rem; }
    .acoes { display: flex; gap: 0.4rem; justify-content: flex-end; }
    .acoes form { display: inline; }
    .acoes button { background: transparent; color: var(--muted); border: 1px solid var(--border); border-radius: 6px; padding: 0.4rem 0.8rem; font-family: inherit; font-size: 0.82rem; cursor: pointer; transition: 0.3s; }
    .acoes button:hover { color: var(--gold); border-color: rgba(205,164,124,0.4); }
    .acoes button.perigo:hover { color: #f87171; border-color: rgba(248,113,113,0.4); }
    .vazio { text-align: center; color: var(--muted); background: var(--card); border: 1px dashed var(--border); border-radius: 12px; padding: 3.5rem 1.5rem; }
    .vazio a { color: var(--gold); }
    @media (max-width: 900px) { .marcacao { grid-template-columns: 1fr auto; } .acoes { grid-column: 1 / -1; justify-content: flex-start; flex-wrap: wrap; } }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Agenda</h1>
            <p><?= $eGestor ? 'Todas as marcações da barbearia' : 'As tuas marcações' ?> · <?= e($nome_utilizador) ?> (<?= e(ucfirst($tipo)) ?>)</p>
        </div>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <div class="toolbar">
        <div class="tabs">
            <a href="<?= e(urlAgenda(['vista' => 'dia'])) ?>"      class="<?= $vista === 'dia' ? 'active' : '' ?>">Dia</a>
            <a href="<?= e(urlAgenda(['vista' => 'semana'])) ?>"   class="<?= $vista === 'semana' ? 'active' : '' ?>">Semana</a>
            <a href="<?= e(urlAgenda(['vista' => 'proximas'])) ?>" class="<?= $vista === 'proximas' ? 'active' : '' ?>">Próximas</a>
        </div>

        <div class="nav-data">
            <?php if ($passo): ?>
                <a href="<?= e(urlAgenda(['data' => (clone $dataRef)->modify('-' . $passo)->format('Y-m-d')])) ?>" aria-label="Anterior">‹</a>
            <?php endif; ?>
            <span class="intervalo"><?= e($tituloIntervalo) ?></span>
            <?php if ($passo): ?>
                <a href="<?= e(urlAgenda(['data' => (clone $dataRef)->modify('+' . $passo)->format('Y-m-d')])) ?>" aria-label="Seguinte">›</a>
                <a href="<?= e(urlAgenda(['data' => (new DateTime('today'))->format('Y-m-d')])) ?>">Hoje</a>
            <?php endif; ?>
        </div>

        <form class="filtros" method="get" action="agenda.php">
            <input type="hidden" name="vista" value="<?= e($vista) ?>">
            <?php if ($passo): ?><input type="date" name="data" value="<?= e($dataRef->format('Y-m-d')) ?>" aria-label="Data"><?php endif; ?>
            <select name="estado" aria-label="Estado">
                <option value="">Todos os estados</option>
                <?php foreach (ESTADOS as $k => $rotulo): ?>
                    <option value="<?= e($k) ?>" <?= $filtroEstado === $k ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($eGestor): ?>
            <select name="barbeiro" aria-label="Barbeiro">
                <option value="0">Todos os barbeiros</option>
                <?php foreach ($barbeiros as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= $filtroBarbeiro === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit">Filtrar</button>
        </form>
    </div>

    <div class="resumo">
        <div><small>Marcações</small><strong><?= count($marcacoes) ?></strong></div>
        <div><small>Por verificar</small><strong><?= (int)($contagem['pendente_verificacao'] ?? 0) ?></strong></div>
        <div><small>Confirmadas</small><strong><?= (int)($contagem['confirmada'] ?? 0) ?></strong></div>
        <div><small>Faturado (concluídas)</small><strong><?= number_format($receita, 2, ',', '') ?> €</strong></div>
    </div>

    <?php if (!$marcacoes): ?>
        <div class="vazio">
            <p>Sem marcações para este período.</p>
            <?php if ($slug): ?><p style="margin-top:.6rem;font-size:.9rem;">Os clientes podem marcar em <a href="../publico/marcar.php?slug=<?= e(rawurlencode($slug)) ?>" target="_blank" rel="noopener">a página de marcação</a>.</p><?php endif; ?>
        </div>
    <?php else: ?>
        <?php foreach ($porDia as $diaStr => $lista): ?>
            <?php if ($vista !== 'dia'): ?>
                <h2 class="dia-titulo"><?= e(dataExtenso(new DateTime($diaStr))) ?></h2>
            <?php endif; ?>
            <?php foreach ($lista as $m):
                $ini = new DateTime($m['inicio']);
                $fim = new DateTime($m['fim']);
                $acoes = ACOES[$m['estado']] ?? [];
                $estadoLabel = ESTADOS[$m['estado']] ?? $m['estado'];
            ?>
            <div class="marcacao <?= e($m['estado']) ?>">
                <div class="hora"><?= $ini->format('H:i') ?><small>até <?= $fim->format('H:i') ?></small></div>
                <div class="cliente">
                    <strong><?= e($m['cliente_nome']) ?></strong>
                    <span>
                        <?= e($m['servico_nome']) ?> · <?= number_format((float)$m['servico_preco'], 2, ',', '') ?> €
                        <?php if ($eGestor): ?> · com <?= e($m['barbeiro_nome']) ?><?php endif; ?>
                        <br><a href="tel:<?= e($m['cliente_telemovel']) ?>"><?= e($m['cliente_telemovel']) ?></a>
                        <?php if (!empty($m['cliente_email'])): ?> · <a href="mailto:<?= e($m['cliente_email']) ?>"><?= e($m['cliente_email']) ?></a><?php endif; ?>
                    </span>
                    <?php if (!empty($m['notas'])): ?><span class="notas">“<?= e($m['notas']) ?>”</span><?php endif; ?>
                </div>
                <span class="badge <?= e($m['estado']) ?>"><?= e($estadoLabel) ?></span>
                <div class="acoes">
                    <?php foreach ($acoes as $novo => $rotulo): ?>
                        <form method="post" action="<?= e(urlAgenda()) ?>">
                            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                            <input type="hidden" name="estado" value="<?= e($novo) ?>">
                            <button type="submit" class="<?= $novo === 'cancelada' ? 'perigo' : '' ?>"><?= e($rotulo) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>