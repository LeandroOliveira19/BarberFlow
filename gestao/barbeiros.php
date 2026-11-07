<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mailer.php'; // <-- Movido para o topo
session_start();

if (!isset($_SESSION['utilizador_id']) || $_SESSION['tipo'] !== 'gestor') {
    header('Location: index.php');
    exit;
}

$bid = (int)$_SESSION['barbearia_id'];
$msg = '';
$erro = '';
$nova_pass_info = '';

// 1. Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $bancada_id = !empty($_POST['bancada_id']) ? (int)$_POST['bancada_id'] : null;

        if ($nome && filter_var($email, FILTER_VALIDATE_EMAIL) && $bancada_id) {
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erro = 'Este email já está registado.';
            } else {
                // Gerar password temporária
                $temp_pass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
                $hash = password_hash($temp_pass, PASSWORD_DEFAULT);

                $pdo->beginTransaction();
                try {
                    // Inserir utilizador
                    $stmt = $pdo->prepare("INSERT INTO utilizadores (barbearia_id, nome, email, password_hash, telefone, tipo, precisa_alterar_password) VALUES (?, ?, ?, ?, ?, 'barbeiro', 1)");
                    $stmt->execute([$bid, $nome, $email, $hash, $telefone]);
                    $novo_id = $pdo->lastInsertId();

                    // Atribuir a bancada
                    $stmt = $pdo->prepare("UPDATE bancadas SET barbeiro_id = ? WHERE id = ? AND barbearia_id = ? AND barbeiro_id IS NULL");
                    $stmt->execute([$novo_id, $bancada_id, $bid]);

                    $pdo->commit();

                    // Tentar enviar email
                    $email_enviado = enviarCredenciaisEmail($email, $nome, $temp_pass);

                    if ($email_enviado) {
                        $nova_pass_info = "Barbeiro criado com sucesso! O email com as credenciais foi enviado para <strong>{$email}</strong>.";
                    } else {
                        $nova_pass_info = "Barbeiro criado com sucesso! (Aviso: O envio do email falhou). Password temporária: <strong>" . $temp_pass . "</strong> (Entrega esta password ao barbeiro).";
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $erro = 'Erro ao criar o barbeiro: ' . $e->getMessage();
                }
            }
        } else {
            $erro = 'Preenche todos os campos obrigatórios.';
        }
    }
    elseif ($acao === 'editar') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $bancada_id = !empty($_POST['bancada_id']) ? (int)$_POST['bancada_id'] : null;

        if ($nome) {
            $pdo->beginTransaction();
            try {
                // Atualizar dados
                $stmt = $pdo->prepare("UPDATE utilizadores SET nome = ?, telefone = ? WHERE id = ? AND barbearia_id = ?");
                $stmt->execute([$nome, $telefone, $id, $bid]);

                // Atualizar Bancada
                // 1. Remover de qualquer bancada atual
                $pdo->prepare("UPDATE bancadas SET barbeiro_id = NULL WHERE barbeiro_id = ? AND barbearia_id = ?")->execute([$id, $bid]);
                // 2. Atribuir à nova
                if ($bancada_id) {
                    $pdo->prepare("UPDATE bancadas SET barbeiro_id = ? WHERE id = ? AND barbearia_id = ?")->execute([$id, $bancada_id, $bid]);
                }
                $pdo->commit();
                $msg = 'Barbeiro atualizado com sucesso!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = 'Erro ao atualizar.';
            }
        }
    }
    elseif ($acao === 'apagar') {
        $pdo->beginTransaction();
        try {
            // Desativar utilizador
            $pdo->prepare("UPDATE utilizadores SET ativo = 0 WHERE id = ? AND barbearia_id = ?")->execute([$id, $bid]);
            // Libertar bancada
            $pdo->prepare("UPDATE bancadas SET barbeiro_id = NULL WHERE barbeiro_id = ? AND barbearia_id = ?")->execute([$id, $bid]);
            $pdo->commit();
            $msg = 'Barbeiro desativado e bancada libertada.';
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
    elseif ($acao === 'ativar') {
        $pdo->prepare("UPDATE utilizadores SET ativo = 1 WHERE id = ? AND barbearia_id = ?")->execute([$id, $bid]);
        $msg = 'Barbeiro reativado.';
    }
}

// 2. Ver se estamos a editar
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("
        SELECT u.*, b.id AS bancada_id 
        FROM utilizadores u 
        LEFT JOIN bancadas b ON u.id = b.barbeiro_id 
        WHERE u.id = ? AND u.barbearia_id = ?
    ");
    $stmt->execute([(int)$_GET['editar'], $bid]);
    $editar = $stmt->fetch();
}

// 3. Listar barbeiros
$stmt = $pdo->prepare("
    SELECT u.*, b.nome AS bancada_nome 
    FROM utilizadores u 
    LEFT JOIN bancadas b ON u.id = b.barbeiro_id 
    WHERE u.barbearia_id = ? AND u.tipo = 'barbeiro' 
    ORDER BY u.nome
");
$stmt->execute([$bid]);
$barbeiros = $stmt->fetchAll();

// 4. Listar bancadas livres para o dropdown (inclui a atual se estiver a editar)
$id_editar = $editar['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT id, nome FROM bancadas 
    WHERE barbearia_id = ? AND ativo = 1 AND (barbeiro_id IS NULL OR barbeiro_id = ?)
    ORDER BY nome
");
$stmt->execute([$bid, $id_editar]);
$bancadas_livres = $stmt->fetchAll();
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<div class="main-content">
    <div class="header">
        <h1>Gestão de Barbeiros</h1>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($erro): ?><div class="alerta erro"><?= $erro ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alerta sucesso"><?= $msg ?></div><?php endif; ?>
    <?php if ($nova_pass_info): ?><div class="alerta sucesso"><?= $nova_pass_info ?></div><?php endif; ?>

    <div class="grid" style="display:grid; grid-template-columns: 2fr 1fr; gap:2rem;">
        <!-- Tabela de Barbeiros -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email / Telefone</th>
                        <th>Bancada</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($barbeiros as $b): ?>
                        <tr style="<?= !$b['ativo'] ? 'opacity:0.5;' : '' ?>">
                            <td><strong><?= htmlspecialchars($b['nome']) ?></strong></td>
                            <td>
                                <?= htmlspecialchars($b['email']) ?><br>
                                <small style="color:#666;"><?= htmlspecialchars($b['telefone'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($b['bancada_nome'] ?? '-') ?></td>
                            <td>
                                <span class="badge <?= $b['ativo'] ? 'ok' : 'low' ?>">
                                    <?= $b['ativo'] ? 'Ativo' : 'Inativo' ?>
                                </span>
                            </td>
                            <td>
                                <a href="?editar=<?= $b['id'] ?>" class="btn-acao">Editar</a>
                                <?php if ($b['ativo']): ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="acao" value="apagar">
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-acao btn-perigo" onclick="return confirm('Desativar este barbeiro? A bancada ficará livre.')">X</button>
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

        <!-- Formulário de Criação/Edição -->
        <div class="form-card" style="background:var(--card); padding:1.5rem; border-radius:12px; border:1px solid var(--border);">
            <h2 style="color:var(--gold); margin-bottom:1.5rem;"><?= $editar ? 'Editar Barbeiro' : 'Adicionar Barbeiro' ?></h2>
            
            <?php if (!$editar && empty($bancadas_livres)): ?>
                <div class="alerta erro">
                    Não existem bancadas livres. Vai à Gestão de Bancadas para criar ou libertar uma.
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="acao" value="<?= $editar ? 'editar' : 'criar' ?>">
                    <?php if ($editar): ?><input type="hidden" name="id" value="<?= $editar['id'] ?>"><?php endif; ?>

                    <div class="input-group">
                        <label>Nome Completo</label>
                        <input type="text" name="nome" required value="<?= htmlspecialchars($editar['nome'] ?? '') ?>">
                    </div>
                    <?php if (!$editar): ?>
                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" name="email" required>
                    </div>
                    <?php endif; ?>
                    <div class="input-group">
                        <label>Telemóvel</label>
                        <input type="tel" name="telefone" value="<?= htmlspecialchars($editar['telefone'] ?? '') ?>">
                    </div>
                    <div class="input-group">
                        <label>Atribuir Bancada *</label>
                        <select name="bancada_id" required>
                            <option value="">Escolhe uma bancada...</option>
                            <?php foreach ($bancadas_livres as $bl): ?>
                                <option value="<?= $bl['id'] ?>" <?= ($editar['bancada_id'] ?? '') == $bl['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bl['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit"><?= $editar ? 'Guardar Alterações' : 'Criar Conta' ?></button>
                    <?php if ($editar): ?>
                        <a href="barbeiros.php" style="display:block; text-align:center; margin-top:1rem; color:var(--muted);">Cancelar</a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>