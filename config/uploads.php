<?php
declare(strict_types=1);

/**
 * Guardar imagens enviadas a partir do dispositivo (fotografia do perfil, imagens do site da barbearia).
 * Aceita só JPG, PNG e WEBP até 2 MB, confirmados pelo conteúdo do ficheiro (não só pela extensão).
 */
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

const IMAGEM_MAX_BYTES = 2 * 1024 * 1024;
const IMAGEM_TIPOS     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

/** Há um ficheiro neste campo do $_FILES? (o campo vazio do formulário chega como UPLOAD_ERR_NO_FILE) */
function imagemFoiEnviada(?array $ficheiro): bool {
    return $ficheiro !== null && ($ficheiro['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

/**
 * Valida e move a imagem para $pastaAbs, com o nome "$prefixo_<tempo>_<aleatório>.<ext>".
 * @return array{erro: ?string, nome: ?string} nome do ficheiro gravado (sem a pasta)
 */
function guardarImagemEnviada(array $ficheiro, string $pastaAbs, string $prefixo): array {
    $erro = fn(string $m): array => ['erro' => $m, 'nome' => null];
    if (($ficheiro['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)$ficheiro['size'] > IMAGEM_MAX_BYTES) {
        return $erro('A imagem tem de ter no máximo 2 MB.');
    }
    $ext = strtolower(pathinfo((string)$ficheiro['name'], PATHINFO_EXTENSION));
    $tipo = (string)(new finfo(FILEINFO_MIME_TYPE))->file((string)$ficheiro['tmp_name']);
    if (!isset(IMAGEM_TIPOS[$tipo]) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || @getimagesize((string)$ficheiro['tmp_name']) === false) {
        return $erro('A imagem tem de ser JPG, PNG ou WEBP.');
    }
    if (!is_dir($pastaAbs) && !mkdir($pastaAbs, 0755, true) && !is_dir($pastaAbs)) {
        error_log("uploads: não foi possível criar a pasta $pastaAbs");
        return $erro('Não foi possível guardar a imagem. Tenta de novo.');
    }
    $nome = $prefixo . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . IMAGEM_TIPOS[$tipo];
    if (!move_uploaded_file((string)$ficheiro['tmp_name'], rtrim($pastaAbs, '/\\') . '/' . $nome)) {
        error_log("uploads: move_uploaded_file falhou para $nome");
        return $erro('Não foi possível guardar a imagem. Tenta de novo.');
    }
    return ['erro' => null, 'nome' => $nome];
}

/** Apaga um ficheiro enviado antes, só se estiver dentro da pasta indicada (nunca fora dela). */
function apagarImagemEnviada(string $caminhoRelativo, string $prefixoPermitido, string $raizAbs): void {
    if (!str_starts_with($caminhoRelativo, $prefixoPermitido) || str_contains($caminhoRelativo, '..')) return;
    $abs = rtrim($raizAbs, '/\\') . '/' . $caminhoRelativo;
    if (is_file($abs)) @unlink($abs);
}
