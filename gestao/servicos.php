<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['utilizador_id']) || $_SESSION['tipo'] !== 'gestor') {
    header('Location: index.php');
    exit;
}

$bid = (int)$_SESSION['barbearia_id'];

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$msg = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        die('Erro de segurança CSRF.');
    }

    $acao = $_POST['acao'] ?? '';
    
    if ($acao === 'apagar') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE servicos SET ativo = 0 WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $bid]);
        $msg = 'Serviço desativado com sucesso.';
    } 
    elseif ($acao === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $duracao = (int)($_POST['duracao_minutos'] ?? 30);
        $preco = (float)str_replace(',', '.', $_POST['preco'] ?? '0');
        $descricao = trim($_POST['descricao'] ?? '');

        if ($nome === '' || $preco <= 0) {
            $erro = 'O nome e o preço são obrigatórios.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE servicos SET nome=?, duracao_minutos=?, preco=?, descricao=?, ativo=1 WHERE id=? AND barbearia_id=?");
                $stmt->execute([$nome, $duracao, $preco, $descricao, $id, $bid]);
                $msg = 'Serviço atualizado com sucesso.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO servicos (barbearia_id, nome, duracao_minutos, preco, descricao) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$bid, $nome, $duracao, $preco, $descricao]);
                $msg = 'Serviço adicionado com sucesso.';
            }
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM servicos WHERE barbearia_id = ? AND ativo = 1 ORDER BY nome");
$stmt->execute([$bid]);
$servicos = $stmt->fetchAll();

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM servicos WHERE id = ? AND barbearia_id = ?");
    $stmt->execute([(int)$_GET['editar'], $bid]);
    $editar = $stmt->fetch();
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
    .grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start; }
    @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
    .form-card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); padding: 1.5rem; }
    .form-card h2 { font-size: 1.2rem; margin-bottom: 1.5rem; color: var(--gold); font-family: 'Cinzel', serif; }
    .btn-cancelar { display: block; text-align: center; margin-top: 0.8rem; color: var(--muted); text-decoration: none; font-size: 0.85rem; }
    .acoes { display: flex; gap: 0.5rem; }
    .preco { color: var(--gold); font-weight: 500; }
</style>

<div class="main-content">
    <div class="header">
        <h1>Gestão de Serviços</h1>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <div class="grid">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Serviço</th>
                        <th>Duração</th>
                        <th>Preço</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servicos as $s): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($s['nome']) ?></strong><br>
                                <small style="color: #666;"><?= htmlspecialchars($s['descricao'] ?? '') ?></small>
                            </td>
                            <td><?= $s['duracao_minutos'] ?> min</td>
                            <td class="preco"><?= number_format((float)$s['preco'], 2, ',', '') ?> €</td>
                            <td>
                                <div class="acoes">
                                    <a href="?editar=<?= $s['id'] ?>" class="btn-acao">Editar</a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                                        <input type="hidden" name="acao" value="apagar">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn-acao btn-perigo" onclick="return confirm('Desativar este serviço?')">X</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-card">
            <h2><?= $editar ? 'Editar Serviço' : 'Novo Serviço' ?></h2>
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= $_SESSION['csrf'] ?>">
                <input type="hidden" name="acao" value="guardar">
                <?php if ($editar): ?>
                    <input type="hidden" name="id" value="<?= $editar['id'] ?>">
                <?php endif; ?>

                <div class="input-group">
                    <label>Nome do Serviço</label>
                    <input type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Duração (minutos)</label>
                    <input type="number" name="duracao_minutos" required value="<?= htmlspecialchars((string)($editar['duracao_minutos'] ?? '30')) ?>">
                </div>
                <div class="input-group">
                    <label>Preço (€)</label>
                    <input type="number" step="0.01" name="preco" required value="<?= htmlspecialchars((string)($editar['preco'] ?? '')) ?>">
                </div>
                <div class="input-group">
                    <label>Descrição (opcional)</label>
                    <textarea name="descricao" rows="2"><?= htmlspecialchars($editar['descricao'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn-submit"><?= $editar ? 'Guardar Alterações' : 'Adicionar Serviço' ?></button>
                <?php if ($editar): ?>
                    <a href="servicos.php" class="btn-cancelar">Cancelar edição</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
</body>
</html>