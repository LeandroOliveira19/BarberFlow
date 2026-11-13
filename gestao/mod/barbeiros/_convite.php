<?php
declare(strict_types=1);
// Parte partilhada por barbeiros/criar e barbeiros/reenviar_convite (o "_" impede que seja ação).
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

require_once __DIR__ . '/../../../config/contas.php';
require_once __DIR__ . '/../../../config/mailer_contas.php';
require_once __DIR__ . '/../../../config/politica_codigo.php';

/**
 * Envia o email do convite e prepara a mensagem para o gestor. Se o email não sair (ex.: servidor da UA)
 * ou em desenvolvimento, o link fica na sessão para a lista o mostrar com um botão "Copiar"
 * (o gestor envia-o por WhatsApp). O link é como uma senha: não vai para as mensagens flash.
 */
function enviarConviteEAvisar(PDO $pdo, int $bid, string $nome, string $email, string $token): void {
    $st = $pdo->prepare("SELECT nome FROM barbearias WHERE id = ?");
    $st->execute([$bid]);
    $link    = linkAtivacao($token);
    $enviado = enviarConviteEmail($email, $nome, (string)$st->fetchColumn(), $link);
    if ($enviado) {
        flash('sucesso', "Convite enviado para {$email}. O link é válido durante 48 horas.");
    } else {
        flash('aviso', "Não foi possível enviar o email para {$email}. Copia o link abaixo e envia-o ao barbeiro.");
    }
    if (!$enviado || mostrarCodigoNoEcra($enviado)) {
        $_SESSION['convite_link'] = ['nome' => $nome, 'link' => $link];
    }
}
