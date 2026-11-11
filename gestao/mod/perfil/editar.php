<?php
declare(strict_types=1);
// Módulo: perfil / editar (incluído por gestao/index.php)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

// As mensagens chegam por flash (mostradas pelo index.php); $msg/$erro ficam vazios
$uid = (int)$_SESSION['utilizador_id'];
$msg = '';
$erro = '';

$forcar_alteracao = isset($_GET['force']) || (($_SESSION['precisa_alterar_password'] ?? 0) == 1);

$stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
?>

<style>
    /* O CSS do perfil que já tinhas mantém-se igual */
    .perfil-layout { display: grid; grid-template-columns: 300px 1fr; gap: 2.5rem; align-items: start; max-width: 1100px; }
    @media (max-width: 900px) { .perfil-layout { grid-template-columns: 1fr; } }
    .perfil-card { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 2rem; text-align: center; }
    .avatar-preview { width: 160px; height: 160px; border-radius: 50%; object-fit: cover; border: 3px solid var(--gold); margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--gold); font-family: 'Cinzel', serif; background: #1a1a1a; }
    .perfil-card h2 { font-family: 'Cinzel', serif; font-size: 1.4rem; margin-bottom: 0.5rem; }
    .perfil-card p { color: var(--muted); font-size: 0.9rem; margin-bottom: 0.2rem; }
    .form-section { background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 2rem; margin-bottom: 1.5rem; }
    .form-section h3 { font-family: 'Cinzel', serif; font-size: 1.2rem; color: var(--gold); margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
    .file-upload-wrapper { border: 2px dashed var(--border); padding: 1rem; border-radius: 8px; text-align: center; transition: 0.3s; }
    details.password-toggle { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; margin-top: 1rem; }
    details.password-toggle summary { padding: 1rem; cursor: pointer; font-weight: 500; list-style: none; display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; }
    details.password-toggle summary::-webkit-details-marker { display: none; }
    details.password-toggle summary::after { content: '+'; color: var(--gold); font-size: 1.5rem; transition: transform 0.3s; }
    details.password-toggle[open] summary::after { transform: rotate(45deg); }
    details.password-toggle .details-content { padding: 1.5rem; background: var(--card); }
</style>

<div class="main-content">
    <div class="header">
        <h1>O Meu Perfil</h1>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta erro"><?= htmlspecialchars($erro) ?></div><?php endif; ?>

    <?php if ($forcar_alteracao): ?>
        <div class="alerta erro" style="background:rgba(248,113,113,0.15); color:#f87171; border:1px solid #f87171; margin-bottom: 2rem;">
            <strong>Ação Necessária:</strong> Como esta é a tua primeira vez a entrar, deves alterar a tua password temporária.
        </div>
    <?php endif; ?>

    <div class="perfil-layout">
        <!-- COLUNA ESQUERDA -->
        <div class="perfil-card">
            <?php if ($user['foto_url']): ?>
                <img src="../<?= htmlspecialchars($user['foto_url']) ?>" alt="Perfil" class="avatar-preview">
            <?php else: ?>
                <div class="avatar-preview"><?= mb_strtoupper(mb_substr($user['nome'], 0, 1)) ?></div>
            <?php endif; ?>
            <h2><?= htmlspecialchars($user['nome']) ?></h2>
            <p><?= htmlspecialchars($user['email']) ?></p>
            
            <?php if ($user['especialidades']): ?>
                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                    <span style="font-size: 0.8rem; color: var(--muted); text-transform: uppercase;">Especialidades</span><br>
                    <strong style="color: var(--gold); font-size: 0.9rem;"><?= htmlspecialchars($user['especialidades']) ?></strong>
                </div>
            <?php endif; ?>
        </div>

        <!-- COLUNA DIREITA -->
        <div>
            <?php if ($forcar_alteracao): ?>
                <div class="form-section">
                    <h3>Alterar Password (Obrigatório)</h3>
                    <form method="POST" action="<?= e(urlModulo('perfil', 'alterar_password')) ?>">
                        <?= campoCsrf() ?>
                        <div class="input-group">
                            <label>Nova Password</label>
                            <input type="password" name="nova_password" required placeholder="Mínimo 6 caracteres">
                        </div>
                        <div class="input-group">
                            <label>Confirmar Nova Password</label>
                            <input type="password" name="confirmar_password" required placeholder="Repete a password">
                        </div>
                        <button type="submit" class="btn-submit">Guardar Nova Password</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="form-section">
                    <h3>Dados Pessoais e Profissionais</h3>
                    <form method="POST" action="<?= e(urlModulo('perfil', 'guardar')) ?>" enctype="multipart/form-data">
                        <?= campoCsrf() ?>
                        
                        <div class="input-group">
                            <label>Nome</label>
                            <input type="text" name="nome" required value="<?= htmlspecialchars($user['nome']) ?>">
                        </div>
                        <div class="input-group">
                            <label>Especialidades (separadas por vírgula)</label>
                            <input type="text" name="especialidades" value="<?= htmlspecialchars($user['especialidades'] ?? '') ?>" placeholder="Ex: Degradê, Barba, Colorimetria">
                        </div>
                        <div class="input-group">
                            <label>Link do Instagram</label>
                            <input type="url" name="instagram" value="<?= htmlspecialchars($user['instagram'] ?? '') ?>" placeholder="https://instagram.com/o_teu_perfil">
                        </div>
                        <div class="input-group">
                            <label>Biografia / Sobre mim</label>
                            <textarea name="bio" rows="4" placeholder="Escreve um pouco sobre a tua experiência..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>

                        <div class="input-group" style="margin-top: 1.5rem;">
                            <label>Fotografia (Máx: 2MB)</label>
                            <div class="file-upload-wrapper">
                                <input type="file" name="foto_upload" accept="image/jpeg, image/png, image/webp">
                            </div>
                        </div>
                        <div class="input-group">
                            <label>Ou URL da imagem</label>
                            <input type="url" name="foto_url" value="<?= htmlspecialchars($user['foto_url'] ?? '') ?>">
                        </div>

                        <button type="submit" class="btn-submit">Guardar Perfil</button>
                    </form>
                </div>

                <div class="form-section">
                    <h3>Segurança</h3>
                    <details class="password-toggle">
                        <summary>Alterar a minha Password</summary>
                        <div class="details-content">
                            <form method="POST" action="<?= e(urlModulo('perfil', 'alterar_password')) ?>">
                                <?= campoCsrf() ?>
                                <div class="input-group">
                                    <label>Nova Password</label>
                                    <input type="password" name="nova_password" required>
                                </div>
                                <div class="input-group">
                                    <label>Confirmar Nova Password</label>
                                    <input type="password" name="confirmar_password" required>
                                </div>
                                <button type="submit" class="btn-submit">Guardar Nova Password</button>
                            </form>
                        </div>
                    </details>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
