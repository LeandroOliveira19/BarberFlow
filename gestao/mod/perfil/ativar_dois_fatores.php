<?php
declare(strict_types=1);
// Módulo: perfil / ativar_dois_fatores (POST). O CSRF e a sessão já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('perfil', 'editar'));

require_once __DIR__ . '/../../../config/totp.php';

$uid     = (int)$_SESSION['utilizador_id'];
$segredo = (string)($_SESSION['totp_pendente'] ?? '');
$codigo  = preg_replace('/\D/', '', (string)($_POST['codigo'] ?? ''));

// Só se grava depois de provar que a app autenticadora gera o mesmo código
if ($segredo !== '' && totpVerificar($segredo, $codigo, time())) {
    $pdo->prepare("UPDATE utilizadores SET totp_segredo = ?, totp_ativo = 1 WHERE id = ? AND barbearia_id = ?")
        ->execute([$segredo, $uid, $bid]);
    unset($_SESSION['totp_pendente']);
    flash('sucesso', '2FA ativado. A partir de agora o login pede o código da app.');
} else {
    flash('erro', 'Código errado. Confirma que leste o QR code certo e que a hora do telemóvel está certa.');
}
redirecionar(urlModulo('perfil', 'editar'));
