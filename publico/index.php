<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/reviews.php'; // Onde está a função obterReviewsApify

// 1. Identificar a Barbearia
$slug = trim($_GET['slug'] ?? 'rlbarber');
$stmt = $pdo->prepare("SELECT * FROM barbearias WHERE slug = ? AND ativa = 1");
$stmt->execute([$slug]);
$barbearia = $stmt->fetch();
if (!$barbearia) { die("Barbearia não encontrada."); }

$id_barbearia = $barbearia['id'];

// 2. Ir buscar os dados (Serviços, Barbeiros e Configurações)
$stmt = $pdo->prepare("SELECT * FROM servicos WHERE barbearia_id = ? AND ativo = 1 ORDER BY preco ASC");
$stmt->execute([$id_barbearia]);
$servicos = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT nome, foto_url FROM utilizadores WHERE barbearia_id = ? AND tipo = 'barbeiro' AND ativo = 1");
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
$credenciais = require __DIR__ . '/../config/credenciais.php';
$apifyToken = $credenciais['apify_token'] ?? '';
$googlePlaceId = getConfig($config, 'google_place_id', '');

if (!empty($apifyToken) && !empty($googlePlaceId)) {
    $googleReviews = obterReviewsApify($googlePlaceId, $apifyToken, $id_barbearia);
}

// 5. ROTEADOR DE LAYOUTS
$layout_escolhido = $config['tema_layout'] ?? 'classico';
$caminho_layout = __DIR__ . '/layouts/' . $layout_escolhido . '.php';

if (file_exists($caminho_layout)) {
    require $caminho_layout;
} else {
    require __DIR__ . '/layouts/classico.php'; // Fallback de segurança
}