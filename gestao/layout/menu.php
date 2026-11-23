<?php
// Menu lateral da gestão (incluído por gestao/index.php).
// Grupos com ícones, contadores e o utilizador no fundo. Só mostra as opções permitidas ao perfil ($MODULOS[$perfil]).
// Define também $tituloPagina e $barbeariaMenu, usados pelo cabeçalho fixo (gestao/index.php).
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

/** Ícones de linha (SVG, traço com currentColor) por módulo. */
$ICONES_MENU = [
    'dashboard'    => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
    'agenda'       => '<rect x="3" y="4" width="18" height="17" rx="3"/><path d="M8 2v4M16 2v4M3 10h18"/>',
    'servicos'     => '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><path d="M8 7.5L20 18M8 16.5L20 6"/>',
    'barbeiros'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5M16 4.5a3.5 3.5 0 010 7M18 14.8c1.8.7 3 2.5 3.5 5.2"/>',
    'fornecedores' => '<path d="M3 7h11v10H3zM14 10h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
    'stock'        => '<path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8"/>',
    'bancadas'     => '<rect x="3" y="11" width="18" height="4" rx="1"/><path d="M5 15v6M19 15v6M8 11V5h8v6"/>',
    'pedidos'      => '<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 13l2 2 4-4"/>',
    'perfil'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1-4 4.2-6 8-6s7 2 8 6"/>',
    'definicoes'   => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
];

/** [grupo => [[módulo, ação por omissão, texto], ...]] — a ordem do menu */
$gruposMenu = [
    'Hoje'    => [['dashboard', 'ver', 'Dashboard'], ['agenda', 'listar', 'Agenda']],
    'Negócio' => [['servicos', 'listar', 'Serviços'], ['barbeiros', 'listar', 'Barbeiros'], ['fornecedores', 'listar', 'Fornecedores']],
    'Stock'   => [['stock', 'listar', $eGestor ? 'Stock e Materiais' : 'Minha Bancada'], ['bancadas', 'listar', 'Bancadas'], ['pedidos', 'listar', 'Pedidos de Stock']],
    'Conta'   => [['perfil', 'editar', 'O Meu Perfil'], ['definicoes', 'editar', 'Definições']],
];

// Contadores: marcações de hoje (o barbeiro só vê as suas) e pedidos pendentes (só o gestor)
$uidMenu = (int)$_SESSION['utilizador_id'];
$sql = "SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND DATE(inicio) = CURDATE() AND estado IN ('confirmada','pendente_verificacao')";
$params = [$bid];
if (!$eGestor) { $sql .= " AND barbeiro_id = ?"; $params[] = $uidMenu; }
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$contadores = ['agenda' => (int)$stmt->fetchColumn()];
if ($eGestor) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos_reposicao WHERE barbearia_id = ? AND estado = 'pendente'");
    $stmt->execute([$bid]);
    $contadores['pedidos'] = (int)$stmt->fetchColumn();
}

// Utilizador e barbearia (para o fundo do menu e o cabeçalho fixo)
$stmt = $pdo->prepare("SELECT nome, foto_url FROM utilizadores WHERE id = ? AND barbearia_id = ?");
$stmt->execute([$uidMenu, $bid]);
$euMenu = $stmt->fetch() ?: ['nome' => (string)($_SESSION['utilizador_nome'] ?? ''), 'foto_url' => null];
$stmt = $pdo->prepare("SELECT nome, slug FROM barbearias WHERE id = ?");
$stmt->execute([$bid]);
$barbeariaMenu = $stmt->fetch() ?: ['nome' => '', 'slug' => ''];

$fotoMenu = (string)($euMenu['foto_url'] ?? '');
if ($fotoMenu !== '' && !str_starts_with($fotoMenu, 'http')) {
    $fotoMenu = '../' . ltrim($fotoMenu, '/');
}
$partesNome = preg_split('/\s+/', trim((string)$euMenu['nome'])) ?: [''];
$iniciaisMenu = mb_strtoupper(mb_substr($partesNome[0], 0, 1) . (count($partesNome) > 1 ? mb_substr(end($partesNome), 0, 1) : ''));

$tituloPagina = 'Dashboard';
foreach ($gruposMenu as $itens) {
    foreach ($itens as [$m, , $texto]) {
        if ($m === $modulo) $tituloPagina = $texto;
    }
}
?>
<div class="sidebar">
    <a href="index.php" class="logo">BarberFlow</a>
    <nav>
        <?php foreach ($gruposMenu as $grupo => $itens): ?>
            <?php $visiveis = array_filter($itens, fn($i) => isset($MODULOS[$perfil][$i[0]])); ?>
            <?php if (!$visiveis) continue; ?>
            <div class="menu-grupo"><?= e($grupo) ?></div>
            <?php foreach ($visiveis as [$m, $a, $texto]): ?>
                <a href="<?= e(urlModulo($m, $a)) ?>" class="<?= $modulo === $m ? 'active' : '' ?>"<?= $modulo === $m ? ' aria-current="page"' : '' ?>>
                    <svg class="menu-ico" viewBox="0 0 24 24" aria-hidden="true"><?= $ICONES_MENU[$m] ?></svg>
                    <span><?= e($texto) ?></span>
                    <?php if (!empty($contadores[$m])): ?><span class="contador"><?= (int)$contadores[$m] ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="menu-eu">
        <?php if ($fotoMenu !== ''): ?>
            <img class="menu-avatar" src="<?= e($fotoMenu) ?>" alt="">
        <?php else: ?>
            <span class="menu-avatar" aria-hidden="true"><?= e($iniciaisMenu) ?></span>
        <?php endif; ?>
        <div class="menu-eu-txt">
            <b><?= e((string)$euMenu['nome']) ?></b>
            <small><?= $eGestor ? 'Gestor' : 'Barbeiro' ?> · <?= e((string)$barbeariaMenu['nome']) ?></small>
        </div>
        <a href="logout.php" class="menu-sair" aria-label="Terminar sessão" title="Terminar sessão">
            <svg class="menu-ico" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        </a>
    </div>
</div>
