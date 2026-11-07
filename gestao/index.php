<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
session_start();

// 1. Proteção de Acesso
if (!isset($_SESSION['utilizador_id'])) {
    header('Location: login.php');
    exit;
}

$nome_utilizador = $_SESSION['utilizador_nome'];
$tipo_utilizador = $_SESSION['tipo'];
$barbearia_id    = $_SESSION['barbearia_id'];
$uid             = (int)$_SESSION['utilizador_id'];

$eGestor = ($tipo_utilizador === 'gestor');

// ==========================================
// 2. ESTATÍSTICAS DIFERENCIADAS POR PERFIL
// ==========================================
if ($eGestor) {
    // --- ESTATÍSTICAS DO GESTOR ---
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM servicos WHERE barbearia_id = ? AND ativo = 1");
    $stmt->execute([$barbearia_id]);
    $total_servicos = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND DATE(inicio) = CURDATE()");
    $stmt->execute([$barbearia_id]);
    $marcacoes_hoje = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pedidos_reposicao WHERE barbearia_id = ? AND estado = 'pendente'");
    $stmt->execute([$barbearia_id]);
    $pedidos_pendentes = $stmt->fetchColumn();
    
    $txt_card_1 = 'Marcações Hoje (Total)';
    $txt_card_2 = 'Serviços Ativos';
    $txt_card_3 = 'Pedidos Pendentes';
    $txt_welcome = 'A partir daqui poderás gerir a agenda global, os serviços e o stock da tua barbearia.';
} else {
    // --- ESTATÍSTICAS DO BARBEIRO ---
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND barbeiro_id = ? AND DATE(inicio) = CURDATE()");
    $stmt->execute([$barbearia_id, $uid]);
    $marcacoes_hoje = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marcacoes WHERE barbearia_id = ? AND barbeiro_id = ? AND estado = 'concluida'");
    $stmt->execute([$barbearia_id, $uid]);
    $total_servicos = $stmt->fetchColumn(); // Marcações concluídas

    // Pedidos pendentes da bancada dele
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pedidos_reposicao pr 
        JOIN bancadas b ON pr.bancada_id = b.id 
        WHERE pr.barbearia_id = ? AND b.barbeiro_id = ? AND pr.estado = 'pendente'
    ");
    $stmt->execute([$barbearia_id, $uid]);
    $pedidos_pendentes = $stmt->fetchColumn();
    
    $txt_card_1 = 'As Tuas Marcações Hoje';
    $txt_card_2 = 'Serviços Concluídos';
    $txt_card_3 = 'Os Teus Pedidos Pendentes';
    $txt_welcome = 'A partir daqui poderás consultar a tua agenda diária e gerir o stock da tua bancada.';
}

// 3. Chamar o Header (que já abre o HTML, o <body> e o Menu)
require_once __DIR__ . '/includes/header.php'; 
?>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Dashboard</h1>
            <p style="color: var(--muted); margin-top: 0.5rem;">Bem-vindo de volta, <?= htmlspecialchars($nome_utilizador) ?> (<?= htmlspecialchars(ucfirst($tipo_utilizador)) ?>)</p>
        </div>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $txt_card_1 ?></h3>
            <div class="number"><?= $marcacoes_hoje ?></div>
        </div>
        <div class="stat-card">
            <h3><?= $txt_card_2 ?></h3>
            <div class="number"><?= $total_servicos ?></div>
        </div>
        <div class="stat-card">
            <h3><?= $txt_card_3 ?></h3>
            <div class="number"><?= $pedidos_pendentes ?></div>
        </div>
    </div>

    <div class="welcome-box">
        <h2>O teu sistema está online!</h2>
        <p><?= $txt_welcome ?></p>
    </div>
</div>
</body>
</html>