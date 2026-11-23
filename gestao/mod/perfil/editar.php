<?php
declare(strict_types=1);
// Módulo: perfil / editar (incluído por gestao/index.php)
// Os meus dados; perfil público (só barbeiros, aparece na Equipa do site); segurança (password e 2FA).
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

$uid = (int)$_SESSION['utilizador_id'];
$forcar_alteracao = isset($_GET['force']) || (($_SESSION['precisa_alterar_password'] ?? 0) == 1);

$stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE id = ? AND barbearia_id = ?");
$stmt->execute([$uid, $bid]);
$user = $stmt->fetch();
$eBarbeiro = ($user['tipo'] ?? '') === 'barbeiro';

// Foto: ligação externa tal como está; ficheiro enviado fica em ../uploads/
$foto = (string)($user['foto_url'] ?? '');
$fotoSrc = $foto === '' ? '' : (preg_match('#^https?://#i', $foto) ? $foto : '../' . ltrim($foto, '/'));

// 2FA: enquanto não estiver ativo, o segredo novo fica só na sessão (é gravado ao confirmar com um código)
require_once __DIR__ . '/../../../config/totp.php';
$tem2fa = !empty($user['totp_ativo']);
if (!$tem2fa && empty($_SESSION['totp_pendente'])) $_SESSION['totp_pendente'] = totpGerarSegredo();
$segredo2fa = $tem2fa ? '' : (string)$_SESSION['totp_pendente'];
?>

<style>
    .perfil { max-width: 760px; }
    .perfil-topo { display: flex; align-items: center; gap: 1.2rem; margin-bottom: 2rem; }
    .perfil-foto { width: 84px; height: 84px; border-radius: 50%; object-fit: cover; flex: none; display: grid; place-items: center; background: linear-gradient(135deg, var(--gold), #7a5a37); color: #000; font-size: 2rem; font-weight: 600; }
    .perfil-topo h2 { font-size: 1.5rem; font-weight: 700; letter-spacing: -0.03em; }
    .perfil-topo p { color: var(--muted); font-size: 0.9rem; }
    .papel { display: inline-block; margin-top: 0.35rem; font-size: 0.75rem; font-weight: 600; color: var(--gold); background: rgba(205,164,124,0.14); border-radius: 999px; padding: 0.15rem 0.6rem; }
    .campo-linha input[type="file"] { flex: 1; min-width: 0; color: var(--muted); font-size: 0.82rem; }
    .campo-linha input[type="file"]::file-selector-button { background: var(--superficie); color: var(--texto); border: 1px solid var(--linha); border-radius: 999px; padding: 0.35rem 0.9rem; margin-right: 0.6rem; cursor: pointer; font: inherit; font-size: 0.82rem; }
    .seguranca details > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 1rem; padding: 0.8rem 1.2rem; }
    .seguranca details > summary::-webkit-details-marker { display: none; }
    .seguranca details > summary .txt b { display: block; font-weight: 500; font-size: 0.92rem; }
    .seguranca details > summary .txt small { color: var(--muted); font-size: 0.8rem; }
    .seguranca details > summary .dir { margin-left: auto; display: flex; align-items: center; gap: 0.8rem; }
    .seguranca details > summary .chev { color: #48484a; transition: transform 0.2s; }
    .seguranca details[open] > summary .chev { transform: rotate(90deg); }
    .seguranca details + details { border-top: 1px solid var(--linha); }
    .seguranca .conteudo { padding: 0.4rem 1.2rem 1.3rem; }
    .seguranca .conteudo .btn-submit { width: auto; padding: 0.7rem 1.6rem; }
    .qr { background: #fff; display: inline-block; padding: 8px; border-radius: 12px; margin: 0.5rem 0 1rem; }
    .passos { color: var(--muted); font-size: 0.88rem; margin-bottom: 0.8rem; line-height: 1.7; }
</style>

<div class="main-content perfil">
    <div class="header"><h1>O Meu Perfil</h1></div>

    <?php if ($forcar_alteracao): ?>
        <div class="alerta erro"><strong>Obrigatório:</strong> como é a primeira vez que entras, tens de mudar a password temporária.</div>
    <?php endif; ?>

    <div class="perfil-topo">
        <?php if ($fotoSrc !== ''): ?>
            <img class="perfil-foto" src="<?= e($fotoSrc) ?>" alt="">
        <?php else: ?>
            <span class="perfil-foto" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string)$user['nome'], 0, 1))) ?></span>
        <?php endif; ?>
        <div>
            <h2><?= e((string)$user['nome']) ?></h2>
            <p><?= e((string)$user['email']) ?></p>
            <span class="papel"><?= $eBarbeiro ? 'Barbeiro' : 'Gestor' ?></span>
        </div>
    </div>

    <?php if ($forcar_alteracao): ?>
        <section class="grupo-form">
            <h2>Nova password</h2>
            <div class="lista"><div class="campo-linha coluna"><?php require __DIR__ . '/_form_senha.php'; ?></div></div>
        </section>
    <?php else: ?>
        <form method="POST" action="<?= e(urlModulo('perfil', 'guardar')) ?>" enctype="multipart/form-data">
            <?= campoCsrf() ?>
            <section class="grupo-form">
                <h2>Os meus dados</h2>
                <div class="lista">
                    <div class="campo-linha"><label for="p-nome">Nome</label><input id="p-nome" type="text" name="nome" required minlength="2" maxlength="120" value="<?= e((string)$user['nome']) ?>"></div>
                    <div class="campo-linha"><label for="p-tel">Telefone</label><input id="p-tel" type="tel" name="telefone" maxlength="20" placeholder="912 345 678" value="<?= e((string)($user['telefone'] ?? '')) ?>"></div>
                    <div class="campo-linha"><label for="p-upload">Fotografia<small>JPG, PNG ou WEBP, até 2 MB</small></label><input id="p-upload" type="file" name="foto_upload" accept="image/jpeg, image/png, image/webp"></div>
                    <div class="campo-linha"><label for="p-foto">Ou ligação da imagem</label><input id="p-foto" type="url" name="foto_url" maxlength="500" placeholder="https://…" value="<?= e(preg_match('#^https?://#i', $foto) ? $foto : '') ?>"></div>
                </div>
            </section>

            <?php if ($eBarbeiro): ?>
                <section class="grupo-form">
                    <h2>Perfil público</h2>
                    <p class="ajuda">Aparece na secção Equipa do site da barbearia.</p>
                    <div class="lista">
                        <div class="campo-linha"><label for="p-esp">Especialidades<small>Separadas por vírgulas</small></label><input id="p-esp" type="text" name="especialidades" maxlength="150" placeholder="Degradê, Barba, Colorimetria" value="<?= e((string)($user['especialidades'] ?? '')) ?>"></div>
                        <div class="campo-linha"><label for="p-insta">Instagram</label><input id="p-insta" type="url" name="instagram" maxlength="500" placeholder="https://instagram.com/…" value="<?= e((string)($user['instagram'] ?? '')) ?>"></div>
                        <div class="campo-linha coluna"><label for="p-bio">Sobre mim</label><textarea id="p-bio" name="bio" rows="4" maxlength="1000" placeholder="A tua experiência, o teu estilo…"><?= e((string)($user['bio'] ?? '')) ?></textarea></div>
                    </div>
                </section>
            <?php endif; ?>

            <div class="barra-guardar"><button type="submit" class="btn-submit">Guardar perfil</button></div>
        </form>

        <section class="grupo-form seguranca">
            <h2>Segurança</h2>
            <div class="lista">
                <details>
                    <summary>
                        <div class="txt"><b>Password</b><small>Muda-a com um código enviado para o teu email</small></div>
                        <div class="dir"><span class="chev">›</span></div>
                    </summary>
                    <div class="conteudo"><?php require __DIR__ . '/_form_senha.php'; ?></div>
                </details>
                <details>
                    <summary>
                        <div class="txt"><b>Verificação em dois passos</b><small><?= $tem2fa ? 'Ligada: o login pede o código da app autenticadora' : 'Desligada: liga-a para proteger a tua conta' ?></small></div>
                        <div class="dir">
                            <input class="interruptor" type="checkbox" disabled <?= $tem2fa ? 'checked' : '' ?> aria-label="Verificação em dois passos <?= $tem2fa ? 'ligada' : 'desligada' ?>">
                            <span class="chev">›</span>
                        </div>
                    </summary>
                    <div class="conteudo">
                        <?php if ($tem2fa): ?>
                            <p class="passos">Para desligar, confirma com a tua password e com o código atual da app.</p>
                            <form method="POST" action="<?= e(urlModulo('perfil', 'desativar_dois_fatores')) ?>">
                                <?= campoCsrf() ?>
                                <div class="input-group"><label for="p-2fa-pass">A tua password</label><input id="p-2fa-pass" type="password" name="password" required autocomplete="current-password"></div>
                                <div class="input-group"><label for="p-2fa-cod">Código atual da app</label><input id="p-2fa-cod" type="text" name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code"></div>
                                <button type="submit" class="btn-submit">Desativar 2FA</button>
                            </form>
                        <?php else: ?>
                            <p class="passos">
                                1. Instala uma app autenticadora (Google Authenticator, Microsoft Authenticator…).<br>
                                2. Lê este QR code com a app, ou escreve a chave à mão.<br>
                                3. Escreve o código de 6 dígitos que a app mostra.
                            </p>
                            <div id="qr2fa" class="qr" data-uri="<?= e(totpUri($segredo2fa, (string)$user['email'])) ?>"></div>
                            <p class="passos">Chave: <code id="chave2fa" style="letter-spacing: 0.1em; user-select: all;"><?= e($segredo2fa) ?></code></p>
                            <form method="POST" action="<?= e(urlModulo('perfil', 'ativar_dois_fatores')) ?>">
                                <?= campoCsrf() ?>
                                <div class="input-group"><label for="p-2fa-novo">Código da app</label><input id="p-2fa-novo" type="text" name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code"></div>
                                <button type="submit" class="btn-submit">Ativar 2FA</button>
                            </form>
                            <!-- Biblioteca só de desenho do QR (qrcode-generator, MIT), guardada no projeto -->
                            <script src="../assets/js/qrcode.js"></script>
                            <script>
                                (function () {
                                    var alvo = document.getElementById('qr2fa');
                                    if (!alvo || typeof qrcode !== 'function') return;
                                    var qr = qrcode(0, 'M');
                                    qr.addData(alvo.getAttribute('data-uri'));
                                    qr.make();
                                    alvo.innerHTML = qr.createSvgTag(4, 0);
                                })();
                            </script>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </section>
    <?php endif; ?>
</div>
