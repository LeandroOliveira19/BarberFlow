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
$erro = '';

// 1. Processar formulários
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $morada = trim($_POST['morada'] ?? '');
    
    if ($nome && $morada) {
        $stmt = $pdo->prepare("UPDATE barbearias SET nome = ?, morada = ? WHERE id = ?");
        $stmt->execute([$nome, $morada, $bid]);

        $configs = [
            'slogan' => $_POST['slogan'] ?? '',
            'sobre' => $_POST['sobre'] ?? '',
            'hora_abertura' => $_POST['hora_abertura'] ?? '09:00',
            'hora_fecho' => $_POST['hora_fecho'] ?? '19:00',
            'tema_cor' => $_POST['tema_cor'] ?? '#cda47c',
            'tema_fundo' => $_POST['tema_fundo'] ?? '#0a0a0a',
            'tema_layout' => $_POST['tema_layout'] ?? 'classico',
            'tema_hero' => $_POST['tema_hero'] ?? '',
            'tema_fonte' => $_POST['tema_fonte'] ?? 'Cinzel',
            'link_instagram' => $_POST['link_instagram'] ?? '',
            'link_facebook' => $_POST['link_facebook'] ?? '',
            'galeria_img_1' => $_POST['galeria_img_1'] ?? '',
            'galeria_img_2' => $_POST['galeria_img_2'] ?? '',
            'galeria_img_3' => $_POST['galeria_img_3'] ?? ''
        ];

        $stmtCfg = $pdo->prepare("INSERT INTO configuracoes (barbearia_id, chave, valor) VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        foreach ($configs as $chave => $valor) {
            $stmtCfg->execute([$bid, $chave, $valor]);
        }
        $msg = 'Definições guardadas com sucesso!';
    }
}

// 2. Ir buscar os dados atuais
$stmt = $pdo->prepare("SELECT nome, morada FROM barbearias WHERE id = ?");
$stmt->execute([$bid]);
$barbearia = $stmt->fetch();

$stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE barbearia_id = ?");
$stmt->execute([$bid]);
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

function cfg(string $key, string $default = ''): string {
    global $config;
    return htmlspecialchars($config[$key] ?? $default);
}
?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
    .grid-definicoes { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; align-items: start; }
    @media (max-width: 900px) { .grid-definicoes { grid-template-columns: 1fr; } }
    .card { background: var(--card); border-radius: 12px; border: 1px solid var(--border); padding: 2rem; }
    .card h2 { font-family: 'Cinzel', serif; font-size: 1.2rem; color: var(--gold); margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; }
    .input-group input[type="color"] { padding: 0.2rem; height: 42px; cursor: pointer; }
    .btn-submit { max-width: 300px; margin-left: auto; display: block; font-size: 1rem; }
</style>

<div class="main-content">
    <div class="header">
        <div>
            <h1>Definições</h1>
            <p>Personaliza a identidade e a aparência da tua barbearia.</p>
        </div>
        <a href="logout.php" class="btn-logout">Terminar Sessão</a>
    </div>

    <?php if ($msg): ?><div class="alerta sucesso"><?= $msg ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="alerta erro"><?= $erro ?></div><?php endif; ?>

    <form method="POST">
        <div class="grid-definicoes">
            <!-- Coluna 1: Dados Gerais -->
            <div class="card">
                <h2>Informação Pública</h2>
                <div class="input-group">
                    <label>Nome da Barbearia</label>
                    <input type="text" name="nome" required value="<?= htmlspecialchars($barbearia['nome']) ?>">
                </div>
                <div class="input-group">
                    <label>Morada</label>
                    <input type="text" name="morada" required value="<?= htmlspecialchars($barbearia['morada']) ?>">
                </div>
                <div class="input-group">
                    <label>Slogan (Site Público)</label>
                    <input type="text" name="slogan" value="<?= cfg('slogan') ?>">
                </div>
                <div class="input-group">
                    <label>Sobre Nós (Site Público)</label>
                    <textarea name="sobre" rows="4"><?= cfg('sobre') ?></textarea>
                </div>
                <div style="display:flex; gap:1rem;">
                    <div class="input-group" style="flex:1;">
                        <label>Hora de Abertura</label>
                        <input type="time" name="hora_abertura" value="<?= cfg('hora_abertura', '09:00') ?>">
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label>Hora de Fecho</label>
                        <input type="time" name="hora_fecho" value="<?= cfg('hora_fecho', '19:00') ?>">
                    </div>
                </div>
            </div>

            <!-- Coluna 2: Design e Layout -->
            <div class="card">
                <h2>Identidade Visual</h2>
                <div class="input-group">
                    <label>Layout Base</label>
                    <select name="tema_layout">
                        <option value="classico" <?= cfg('tema_layout') === 'classico' ? 'selected' : '' ?>>Clássico (Escuro)</option>
                        <option value="minimalista" <?= cfg('tema_layout') === 'minimalista' ? 'selected' : '' ?>>Minimalista (Claro)</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Fonte dos Títulos</label>
                    <select name="tema_fonte">
                        <option value="Cinzel" <?= cfg('tema_fonte') === 'Cinzel' ? 'selected' : '' ?>>Cinzel (Clássico)</option>
                        <option value="Playfair Display" <?= cfg('tema_fonte') === 'Playfair Display' ? 'selected' : '' ?>>Playfair Display (Elegante)</option>
                        <option value="Montserrat" <?= cfg('tema_fonte') === 'Montserrat' ? 'selected' : '' ?>>Montserrat (Moderno)</option>
                        <option value="Outfit" <?= cfg('tema_fonte') === 'Outfit' ? 'selected' : '' ?>>Outfit (Minimalista)</option>
                    </select>
                </div>
                <div style="display:flex; gap:1rem;">
                    <div class="input-group" style="flex:1;">
                        <label>Cor Principal</label>
                        <input type="color" name="tema_cor" value="<?= cfg('tema_cor', '#cda47c') ?>">
                    </div>
                    <div class="input-group" style="flex:1;">
                        <label>Cor de Fundo</label>
                        <input type="color" name="tema_fundo" value="<?= cfg('tema_fundo', '#0a0a0a') ?>">
                    </div>
                </div>
                <div class="input-group">
                    <label>Imagem Principal (Hero)</label>
                    <input type="url" name="tema_hero" value="<?= cfg('tema_hero') ?>" placeholder="https://...">
                </div>
            </div>
        </div>

        <!-- Redes Sociais & Galeria -->
        <div class="card" style="margin-bottom: 2rem;">
            <h2>Redes Sociais & Galeria</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <div>
                    <div class="input-group">
                        <label>Link do Instagram</label>
                        <input type="url" name="link_instagram" value="<?= cfg('link_instagram') ?>" placeholder="https://instagram.com/...">
                    </div>
                    <div class="input-group">
                        <label>Link do Facebook (Opcional)</label>
                        <input type="url" name="link_facebook" value="<?= cfg('link_facebook') ?>" placeholder="https://facebook.com/...">
                    </div>
                </div>
                <div>
                    <div class="input-group">
                        <label>Imagem da Galeria 1 (URL)</label>
                        <input type="url" name="galeria_img_1" value="<?= cfg('galeria_img_1') ?>">
                    </div>
                    <div class="input-group">
                        <label>Imagem da Galeria 2 (URL)</label>
                        <input type="url" name="galeria_img_2" value="<?= cfg('galeria_img_2') ?>">
                    </div>
                    <div class="input-group">
                        <label>Imagem da Galeria 3 (URL)</label>
                        <input type="url" name="galeria_img_3" value="<?= cfg('galeria_img_3') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Secção Google Maps (Apenas informativa) -->
        <div class="card" style="margin-bottom: 2rem;">
            <h2>Integração Google Maps</h2>
            <p style="color: var(--muted); margin-bottom: 1.5rem; font-size: 0.95rem;">
                O ID da tua barbearia no Google Maps está configurado no sistema.
            </p>
            
            <?php if (!empty($config['google_place_id'])): ?>
                <p style="color: #4ade80; margin-bottom: 1.5rem; font-weight: 500;">
                    ✅ ID do Google Maps: <?= htmlspecialchars($config['google_place_id']) ?><br>
                    <small style="color: var(--muted); font-weight: normal;">As avaliações estão a ser carregadas através da Apify.</small>
                </p>
            <?php else: ?>
                <p style="color: #f87171; margin-bottom: 1.5rem; font-weight: 500;">
                    ❌ Nenhum ID do Google Maps configurado. Contacta o suporte.
                </p>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-submit">Guardar Alterações</button>
    </form>
</div>
</body>
</html>