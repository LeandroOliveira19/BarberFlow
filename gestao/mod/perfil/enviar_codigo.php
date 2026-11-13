<?php
declare(strict_types=1);
// Módulo: perfil / enviar_codigo (POST): envia para o email da conta o código para mudar a senha.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('perfil', 'editar'));

require_once __DIR__ . '/../../../config/contas.php';
require_once __DIR__ . '/../../../config/mailer_contas.php';
require_once __DIR__ . '/../../../config/politica_codigo.php';

$uid = (int)$_SESSION['utilizador_id'];
$st = $pdo->prepare("SELECT nome, email FROM utilizadores WHERE id = ? AND barbearia_id = ?");
$st->execute([$uid, $bid]);
$u = $st->fetch(PDO::FETCH_ASSOC);

$codigo  = criarCodigoSeguranca($pdo, $uid, 'alterar_password');
$enviado = enviarCodigoSegurancaEmail((string)$u['email'], (string)$u['nome'], $codigo, 'alterar_password');

$texto = $enviado
    ? "Enviámos um código para {$u['email']} (válido 15 minutos)."
    : "Não foi possível enviar o email para {$u['email']}.";
// Desenvolvimento local ou servidor sem email (UA): o código aparece aqui, em vez de só no email
if (mostrarCodigoNoEcra($enviado)) $texto .= " Código: {$codigo}";
flash($enviado || mostrarCodigoNoEcra($enviado) ? 'sucesso' : 'erro', $texto);

$forcado = (($_SESSION['precisa_alterar_password'] ?? 0) == 1);
redirecionar(urlModulo('perfil', 'editar', $forcado ? ['force' => 1] : []));
