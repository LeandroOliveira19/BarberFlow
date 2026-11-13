<?php
declare(strict_types=1);
// Módulo: perfil / desativar_dois_fatores (POST). Pede a senha e um código atual (quem roubou a sessão não consegue desligar).
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('perfil', 'editar'));

require_once __DIR__ . '/../../../config/totp.php';

$uid = (int)$_SESSION['utilizador_id'];
$st = $pdo->prepare("SELECT password_hash, totp_segredo FROM utilizadores WHERE id = ? AND barbearia_id = ?");
$st->execute([$uid, $bid]);
$u = $st->fetch(PDO::FETCH_ASSOC);

$senhaOk  = $u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash']);
$codigoOk = $u && totpVerificar((string)$u['totp_segredo'], preg_replace('/\D/', '', (string)($_POST['codigo'] ?? '')), time());

if ($senhaOk && $codigoOk) {
    $pdo->prepare("UPDATE utilizadores SET totp_segredo = NULL, totp_ativo = 0 WHERE id = ? AND barbearia_id = ?")->execute([$uid, $bid]);
    flash('sucesso', '2FA desativado.');
} else {
    flash('erro', 'Password ou código errado. O 2FA continua ativo.');
}
redirecionar(urlModulo('perfil', 'editar'));
