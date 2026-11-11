<?php
declare(strict_types=1);
// Módulo: barbeiros / listar — incluído por gestao/index.php
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

$msg = '';
$erro = '';
$nova_pass_info = ''; // as mensagens chegam por flash

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
                                <a href="<?= e(urlModulo('barbeiros', 'listar', ['editar' => $b['id']])) ?>" class="btn-acao">Editar</a>
                                <?php if ($b['ativo']): ?>
                                    <form method="POST" action="<?= e(urlModulo('barbeiros', 'apagar')) ?>" style="display:inline;">
                                        <?= campoCsrf() ?>
                                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-acao btn-perigo" onclick="return confirm('Desativar este barbeiro? A bancada ficará livre.')">X</button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" action="<?= e(urlModulo('barbeiros', 'ativar')) ?>" style="display:inline;">
                                        <?= campoCsrf() ?>
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
                <form method="POST" action="<?= e(urlModulo('barbeiros', $editar ? 'editar' : 'criar')) ?>">
                    <?= campoCsrf() ?>
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
                        <a href="<?= e(urlModulo('barbeiros', 'listar')) ?>" style="display:block; text-align:center; margin-top:1rem; color:var(--muted);">Cancelar</a>
                    <?php endif; ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
