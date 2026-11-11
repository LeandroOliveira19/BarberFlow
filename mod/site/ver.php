<?php
declare(strict_types=1);
// Módulo: site / ver — página da barbearia ($barbearia vem do index.php público)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

require_once __DIR__ . '/../../config/reviews.php'; // obterReviewsApify

$id_barbearia = $barbearia['id'];

// 2. Ir buscar os dados (Serviços, Barbeiros e Configurações)
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE barbearia_id = ? AND ativo = 1 ORDER BY preco ASC");
$stmt->execute([$id_barbearia]);
$servicos = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT nome, foto_url, bio, especialidades, instagram FROM utilizadores WHERE barbearia_id = ? AND tipo = 'barbeiro' AND ativo = 1");
$stmt->execute([$id_barbearia]);
$equipa = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT chave, valor FROM configuracoes WHERE barbearia_id = ?");
$stmt->execute([$id_barbearia]);
$config = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); 

// Helper com TIPAGEM (corrige o aviso do VS Code)
function getConfig(array $config, string $chave, string $default = ''): string { 
    return htmlspecialchars((string)($config[$chave] ?? $default)); 
}

$telemovel = preg_replace('/[^0-9]/', '', getConfig($config, 'telemovel_contacto', '351900000000'));

// 3. Preparar a galeria
$galeria = [
    getConfig($config, 'galeria_img_1', 'https://images.unsplash.com/photo-1599351431202-1e0f0137899a?auto=format&fit=crop&w=900&q=80'),
    getConfig($config, 'galeria_img_2', 'https://images.unsplash.com/photo-1622286342621-4bd786c2447c?auto=format&fit=crop&w=900&q=80'),
    getConfig($config, 'galeria_img_3', 'https://images.unsplash.com/photo-1503951914875-452162b0f3f1?auto=format&fit=crop&w=900&q=80')
];

// 4. Ir buscar as reviews via APIFY
$googleReviews = [];
$credenciais = require __DIR__ . '/../../config/credenciais.php';
$apifyToken = $credenciais['apify_token'] ?? '';
$googlePlaceId = getConfig($config, 'google_place_id', '');

if (!empty($apifyToken) && !empty($googlePlaceId)) {
    $googleReviews = obterReviewsApify($googlePlaceId, $apifyToken, $id_barbearia);
}

// Tema escolhido nas Definições (lista fechada: só ficheiros que existem em temas/)
$layout_escolhido = (string)($config['tema_layout'] ?? 'classico');
$caminho_layout = __DIR__ . '/temas/' . $layout_escolhido . '.php';
if (!preg_match('/^[a-z_]+$/', $layout_escolhido) || !is_file($caminho_layout)) {
    $caminho_layout = __DIR__ . '/temas/classico.php'; // fallback de segurança
}
require $caminho_layout;
