<?php
declare(strict_types=1);

function obterReviewsApify(string $placeId, string $apifyToken, int $bid): array {
    $cacheDir = __DIR__ . '/../cache';
    if (!is_dir($cacheDir)) { @mkdir($cacheDir, 0755, true); }
    $cacheFile = $cacheDir . '/reviews_' . $bid . '.json';
    $cacheTtl = 86400; // 24 horas

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
        $json = file_get_contents($cacheFile);
        $data = json_decode($json, true);
        if (is_array($data) && count($data) > 0) {
            return $data;
        }
    }

    $actorId = 'compass~google-maps-reviews-scraper';
    $url = "https://api.apify.com/v2/acts/{$actorId}/run-sync-get-dataset-items";

    $input = [
        'placeIds' => [$placeId],
        'maxReviews' => 20,
        'language' => 'pt-PT',
        'reviewsSort' => 'mostRelevant' 
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apifyToken
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("[Apify] cURL falhou: " . $curlError);
        return [];
    }

    $items = json_decode($response, true);

    if (isset($items['error'])) {
        error_log("[Apify] Erro: " . json_encode($items['error']));
        return [];
    }

    if (is_array($items) && !empty($items)) {
        $reviews = [];
        foreach ($items as $item) {
            $autor = $item['name'] ?? 'Cliente';
            $rating = (int)($item['stars'] ?? 5);
            $texto = trim($item['text'] ?? '');

            // --- LIMPEZA DO TEXTO AQUI ---
            if ($texto !== '') {
                // 1. Substitui <br> por espaço
                $texto = preg_replace('/<br\s*\/?>/i', ' ', $texto);
                // 2. Remove outras tags HTML por segurança
                $texto = strip_tags($texto);
                // 3. Remove espaços duplos que possam ter ficado
                $texto = preg_replace('/\s+/', ' ', $texto);
                $texto = trim($texto);
            }
            // -----------------------------

            if ($texto !== '') {
                $reviews[] = [
                    'author_name' => $autor,
                    'rating' => $rating,
                    'text' => $texto,
                ];
            }
        }

        if (count($reviews) > 0) {
            @file_put_contents($cacheFile, json_encode($reviews));
            return $reviews;
        }
    }

    return [];
}