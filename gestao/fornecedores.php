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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $contacto = trim($_POST['contacto'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $nif = trim($_POST['nif'] ?? '');

    if ($nome) {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE fornecedores SET nome=?, contacto=?, telefone=?, email=?, nif=? WHERE id=? AND barbearia_id=?");
            $stmt->execute([$nome, $contacto, $telefone, $email, $nif, $id, $bid]);
            $msg = 'Fornecedor atualizado.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO fornecedores (barbearia_id, nome, contacto, telefone, email, nif) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$bid, $nome, $contacto, $telefone, $email, $nif]);
            $msg = 'Fornecedor adicionado.';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE barbearia_id = ? AND ativo = 1 ORDER BY nome");
$stmt->execute([$bid]);
$fornecedores = $stmt->fetchAll();

$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM fornecedores WHERE id = ? AND barbearia_id = ?");
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
    .btn-acao { text-decoration: none; color: #fff; background: #1a1a1a; border: 1px solid rgba(255,255,255,0.1); padding: 0.3rem 0.6rem; border-radius: 4px; font-size: 0.8rem; transition: 0.3s; cursor: pointer; }
    .btn-acao:hover { border-color: var(--gold); color: var(--gold); }
</style>

<div class="main-content">
    <div class="header">
        <h1>Gestão de Fornecedores</h1>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= htmlspecialchars($msg) ?></div><?php endif; ?>

    <div class="grid">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nome / Contacto</th>
                        <th>Telefone / Email</th>
                        <th>NIF</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fornecedores as $f): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($f['nome']) ?></strong><br>
                                <small style="color:#666;"><?= htmlspecialchars($f['contacto'] ?? '') ?></small>
                            </td>
                            <td>
                                <?= htmlspecialchars($f['telefone'] ?? '') ?><br>
                                <small style="color:#666;"><?= htmlspecialchars($f['email'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($f['nif'] ?? '') ?></td>
                            <td>
                                <a href="?editar=<?= $f['id'] ?>" class="btn-acao">Editar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($fornecedores)): ?>
                        <tr><td colspan="4" style="text-align:center; padding: 2rem; color: #9ca3af;">Nenhum fornecedor registado.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="form-card">
            <h2><?= $editar ? 'Editar Fornecedor' : 'Novo Fornecedor' ?></h2>
            <form method="POST">
                <?php if ($editar): ?>
                    <input type="hidden" name="id" value="<?= $editar['id'] ?>">
                <?php endif; ?>
                <div class="input-group">
                    <label>Nome da Empresa *</label>
                    <input type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Pessoa de Contacto</label>
                    <input type="text" name="contacto" value="<?= htmlspecialchars($editar['contacto'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Telefone</label>
                    <input type="tel" name="telefone" value="<?= htmlspecialchars($editar['telefone'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editar['email'] ?? '') ?>">
                </div>
                <div class="input-group">
                    <label>NIF</label>
                    <input type="text" name="nif" value="<?= htmlspecialchars($editar['nif'] ?? '') ?>">
                </div>
                <button type="submit" class="btn-submit"><?= $editar ? 'Guardar' : 'Adicionar' ?></button>
                <?php if ($editar): ?>
                    <a href="fornecedores.php" class="btn-cancelar">Cancelar edição</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
</body>
</html>