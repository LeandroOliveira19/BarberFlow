<?php
declare(strict_types=1);

/**
 * Emails das contas da gestão (códigos de segurança e convites), pela API do Brevo, como o mailer_codigo.php.
 * Devolvem false se o email não sair (sem chave, domínio de testes, servidor sem acesso externo como a UA):
 * quem chama decide então se mostra o código/link no ecrã (config/politica_codigo.php).
 */

function enviarEmailBrevo(string $para, string $assunto, string $html): bool {
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return false;
    if (defined('ENVIAR_EMAIL') && ENVIAR_EMAIL === false) return false;
    // Domínios reservados para testes (RFC 2606/6761) nunca recebem email
    if (preg_match('/@(example\.(com|net|org)|[^@]+\.(test|invalid|example))$/i', $para)) return false;
    $cred = require __DIR__ . '/credenciais.php';
    $apiKey = (string)($cred['brevo_api_key'] ?? '');
    if ($apiKey === '') return false;

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POSTFIELDS => json_encode([
            'sender' => ['name' => 'BarberFlow', 'email' => 'barberflowproject2026@gmail.com'],
            'to' => [['email' => $para]],
            'subject' => $assunto,
            'htmlContent' => $html,
        ]),
        CURLOPT_HTTPHEADER => ['accept: application/json', 'api-key: ' . $apiKey, 'content-type: application/json'],
    ]);
    $resp = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $resp !== false && $status >= 200 && $status < 300;
}

function enviarConviteEmail(string $para, string $nome, string $nomeBarbearia, string $link): bool {
    $n = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $b = htmlspecialchars($nomeBarbearia, ENT_QUOTES, 'UTF-8');
    $l = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    return enviarEmailBrevo($para, "Ativa a tua conta — {$nomeBarbearia}", "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
            <h2 style='color: #cda47c;'>{$b}</h2>
            <p>Olá {$n}, foste adicionado à equipa no BarberFlow.</p>
            <p>Para ativares a conta, abre o link e escolhe a tua senha:</p>
            <p style='text-align: center; margin: 25px 0;'>
                <a href='{$l}' style='background: #cda47c; color: #0a0a0a; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Ativar conta</a>
            </p>
            <p style='color: #999; font-size: 12px;'>O link é válido durante 48 horas e só pode ser usado uma vez.</p>
        </div>");
}

function enviarCodigoSegurancaEmail(string $para, string $nome, string $codigo, string $finalidade): bool {
    $acao = $finalidade === 'recuperar_password' ? 'recuperar a tua senha' : 'mudar a tua senha';
    $n = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
    $c = htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8');
    return enviarEmailBrevo($para, 'O teu código de segurança — BarberFlow', "
        <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
            <h2 style='color: #cda47c;'>BarberFlow</h2>
            <p>Olá {$n}, usa este código para {$acao}:</p>
            <div style='background: #f4f4f4; padding: 15px; margin: 20px 0; border-radius: 8px; text-align: center;'>
                <span style='font-size: 28px; letter-spacing: 6px; font-weight: bold;'>{$c}</span>
            </div>
            <p style='color: #999; font-size: 12px;'>Válido durante 15 minutos. Se não foste tu, ignora este email: a tua senha não muda.</p>
        </div>");
}
