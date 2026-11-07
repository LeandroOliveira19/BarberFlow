<?php
declare(strict_types=1);

/**
 * Envia um email de credenciais via API REST do Brevo (HTTPS).
 * Funciona no XAMPP e no servidor da UA (contorna a firewall).
 */
function enviarCredenciaisEmail(string $destinatario, string $nome, string $password): bool {
    if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) return false;

    // Carregar a chave da API do ficheiro de credenciais
    $credenciais = require __DIR__ . '/credenciais.php';
    $apiKey = $credenciais['brevo_api_key'] ?? '';

    // ⚠️ Se não houver chave, avisa no log
    if (empty($apiKey)) {
        error_log("[Mailer] Erro: A chave 'brevo_api_key' não está configurada no credenciais.php");
        return false;
    }

    $payload = [
        'sender' => [
            'name' => 'BarberFlow',
            'email' => 'barberflowproject2026@gmail.com' // O teu email verificado no Brevo
        ],
        'to' => [
            ['email' => $destinatario, 'name' => $nome]
        ],
        'subject' => 'As tuas credenciais de acesso - BarberFlow',
        'htmlContent' => "
            <div style='font-family: Arial, sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                <h2 style='color: #cda47c;'>Olá, {$nome}!</h2>
                <p style='color: #333;'>A tua conta de barbeiro foi criada com sucesso.</p>
                <p style='color: #333;'>Aqui estão as tuas credenciais de acesso:</p>
                <div style='background: #f4f4f4; padding: 15px; margin: 20px 0; border-radius: 8px;'>
                    <p style='margin: 5px 0;'><strong>Email:</strong> {$destinatario}</p>
                    <p style='margin: 5px 0;'><strong>Password:</strong> {$password}</p>
                </div>
                <p style='color: #999; font-size: 12px;'>Recomendamos que alteres a tua password após o primeiro login.</p>
                <a href='http://localhost/projeto/projeto/gestao/login.php' style='display: inline-block; padding: 10px 20px; background: #cda47c; color: #fff; text-decoration: none; border-radius: 5px;'>Entrar no Painel</a>
            </div>
        "
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json',
        'api-key: ' . $apiKey,
        'content-type: application/json'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 201) {
        return true;
    } else {
        error_log("[Mailer] Erro Brevo API (HTTP $httpCode): " . $response);
        return false;
    }
}