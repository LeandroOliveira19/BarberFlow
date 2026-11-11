<?php
declare(strict_types=1);
// Módulo: servicos / listar — incluído por gestao/index.php
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

$msg = '';
$erro = '';


$servico  = new Servico($pdo, $bid);   // objects/Servico.php (CRUD em POO)
$servicos = $servico->read();

$editar = null;
if (isset($_GET['editar'])) {
    $editar = $servico->readOne((int)$_GET['editar']);
}
?>

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
                                    <a href="<?= e(urlModulo('servicos', 'listar', ['editar' => $s['id']])) ?>" class="btn-acao">Editar</a>
                                    <form method="POST" action="<?= e(urlModulo('servicos', 'apagar')) ?>" style="display:inline;">
                                        <?= campoCsrf() ?>
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
            <form method="POST" action="<?= e(urlModulo('servicos', 'guardar')) ?>">
                <?= campoCsrf() ?>
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
                    <a href="<?= e(urlModulo('servicos', 'listar')) ?>" class="btn-cancelar">Cancelar edição</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
