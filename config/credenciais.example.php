<?php
/**
 * Credenciais e opções (exemplo).
 *
 * INSTALAÇÃO: copia este ficheiro para "credenciais.php" e preenche os valores.
 * O "credenciais.php" NUNCA vai para o GitHub (está no .gitignore): tem senhas e chaves.
 */

return [
    // ---------- Base de dados ----------
    // Servidor da UA: utilizador e senha da BD esan-dsg09 (anfitrião mysql-sa.mgmt.ua.pt).
    'user' => 'esan-dsg09-web',
    'pass' => '',
    // Local (XAMPP): descomenta e ajusta. Com estas chaves, o db.php usa esta BD em vez da da UA.
    // 'db_host' => '127.0.0.1', 'db_user' => 'root', 'db_pass' => '',

    // ---------- Integrações (opcionais: sem chave, a funcionalidade fica desligada) ----------
    'brevo_api_key'  => '',   // envio de emails (códigos de marcação, códigos de segurança, convites)
    'apify_token'    => '',   // reviews do Google Maps no site
    'google_api_key' => '',   // pesquisa de moradas

    // ---------- API da app e segurança ----------
    // Segredo que assina os tokens JWT da app. Gera com: php -r "echo bin2hex(random_bytes(32));"
    'api_segredo' => 'GERAR_COM_bin2hex(random_bytes(32))',

    // Mostrar códigos/links no ecrã em vez de só por email:
    //  - api_dev_devolver_codigo: true SÓ em desenvolvimento local (mostra sempre);
    //  - api_codigo_se_email_falhar: true num servidor sem saída para a internet (ex.: UA), onde o email nunca chega.
    // Em produção ficam as duas a false. Por segurança, o código de "Recuperar senha" de uma conta real nunca aparece no ecrã.
    'api_dev_devolver_codigo'    => false,
    'api_codigo_se_email_falhar' => false,
];
