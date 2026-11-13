<?php
declare(strict_types=1);
// Parte partilhada do Perfil (não é uma ação: começa por "_" e não está na lista branca):
// 1) pedir o código por email; 2) escrever o código e a senha nova.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
?>
<p style="color: var(--muted); margin-bottom: 1rem; font-size: 0.9rem;">
    Por segurança, enviamos um código para o teu email antes de mudar a senha.
</p>
<form method="POST" action="<?= e(urlModulo('perfil', 'enviar_codigo')) ?>" style="margin-bottom: 1.5rem;">
    <?= campoCsrf() ?>
    <button type="submit" class="btn-acao">📧 Enviar código para o meu email</button>
</form>
<form method="POST" action="<?= e(urlModulo('perfil', 'alterar_password')) ?>">
    <?= campoCsrf() ?>
    <div class="input-group">
        <label>Código recebido por email</label>
        <input type="text" name="codigo" inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="one-time-code" placeholder="6 dígitos">
    </div>
    <div class="input-group">
        <label>Nova Password</label>
        <input type="password" name="nova_password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres, com letras e números">
    </div>
    <div class="input-group">
        <label>Confirmar Nova Password</label>
        <input type="password" name="confirmar_password" required minlength="8" autocomplete="new-password" placeholder="Repete a password">
    </div>
    <button type="submit" class="btn-submit">Guardar Nova Password</button>
</form>
