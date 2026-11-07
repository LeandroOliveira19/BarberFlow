<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['utilizador_id']) || $_SESSION['tipo'] !== 'gestor') {
    header('Location: index.php');
    exit;
}

$bid = (int)$_SESSION['barbearia_id'];
$msg = '';

// 1. Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $barbeiro_id = !empty($_POST['barbeiro_id']) ? (int)$_POST['barbeiro_id'] : null;

    if ($acao === 'criar') {
        // Gerar nome automático se não for fornecido
        if ($nome === '') {
            $stmt = $pdo->query("SELECT MAX(id) FROM bancadas WHERE barbearia_id = $bid");
            $prox = ($stmt->fetchColumn() ?? 0) + 1;
            $nome = 'Bancada ' . $prox;
        }
        $stmt = $pdo->prepare("INSERT INTO bancadas (barbearia_id, nome, barbeiro_id) VALUES (?, ?, ?)");
        $stmt->execute([$bid, $nome, $barbeiro_id]);
        $msg = 'Bancada criada com sucesso!';
    } 
    elseif ($acao === 'editar') {
        // Se mudou o barbeiro, temos de garantir que ele não está noutra bancada
        if ($barbeiro_id) {
            $stmt = $pdo->prepare("UPDATE bancadas SET barbeiro_id = NULL WHERE barbeiro_id = ? AND barbearia_id = ? AND id != ?");
            $stmt->execute([$barbeiro_id, $bid, $id]);
        }
        $stmt = $pdo->prepare("UPDATE bancadas SET nome = ?, barbeiro_id = ? WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$nome, $barbeiro_id, $id, $bid]);
        $msg = 'Bancada atualizada com sucesso!';
    }
    elseif ($acao === 'apagar') {
        // Soft delete (apenas desativa)
        $stmt = $pdo->prepare("UPDATE bancadas SET ativo = 0 WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $bid]);
        $msg = 'Bancada desativada.';
    }
    elseif ($acao === 'ativar') {
        $stmt = $pdo->prepare("UPDATE bancadas SET ativo = 1 WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $bid]);
        $msg = 'Bancada reativada.';
    }
}

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
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="main-content">
    <div class="header">
        <h1>Gestão de Bancadas</h1>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= $msg ?></div><?php endif; ?>

    <div class="grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Barbeiro Atribuído</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bancadas as $b): ?>
                        <tr style="<?= !$b['ativo'] ? 'opacity:0.5;' : '' ?>">
                            <td><strong><?= htmlspecialchars($b['nome']) ?></strong></td>
                            <td>
                                <?php if ($b['barbeiro_nome']): ?>
                                    <span style="color:var(--primary);"><?= htmlspecialchars($b['barbeiro_nome']) ?></span>
                                <?php else: ?>
                                    <span style="color:#4ade80;">Livre</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $b['ativo'] ? 'ok' : 'low' ?>">
                                    <?= $b['ativo'] ? 'Ativa' : 'Inativa' ?>
                                </span>
                            </td>
                            <td>
                                <a href="?editar=<?= $b['id'] ?>" class="btn-acao">Editar</a>
                                <?php if ($b['ativo']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="acao" value="apagar">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-acao btn-perigo" onclick="return confirm('Desativar esta bancada?')">X</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="acao" value="ativar">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-acao">Reativar</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-card" style="background:var(--card); padding:1.5rem; border-radius:12px; border:1px solid var(--border);">
            <h2 style="color:var(--gold); margin-bottom:1.5rem;"><?= $editar ? 'Editar Bancada' : 'Nova Bancada' ?></h2>
            <form method="POST">
                <input type="hidden" name="acao" value="<?= $editar ? 'editar' : 'criar' ?>">
                <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>

                <div class="input-group">
                    <label>Nome da Bancada (deixa em branco para gerar automaticamente)</label>
                    <input type="text" name="nome" placeholder="Ex: Bancada 1" value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Atribuir Barbeiro (Opcional)</label>
                    <select name="barbeiro_id">
                        <option value="">Nenhum (Livre)</option>
                        <?php foreach ($barbeiros_livres as $bl): ?>
                            <option value="<?= $bl['id'] ?>" <?= ($editar['barbeiro_id'] ?? '') == $bl['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bl['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-submit"><?= $editar ? 'Guardar' : 'Criar Bancada' ?></button>
                <?php if ($editar): ?>
                    <a href="bancadas.php" style="display:block; text-align:center; margin-top:1rem; color:var(--muted);">Cancelar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
</body>
</html>