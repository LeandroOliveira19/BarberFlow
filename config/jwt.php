<?php
declare(strict_types=1);

/**
 * JSON Web Token (RFC 7519) com HS256, feito sem bibliotecas.
 * Formato: base64url(cabeçalho) . base64url(payload) . base64url(HMAC-SHA256(cabeçalho.payload, segredo))
 * Só se aceita HS256: tokens com "alg":"none" ou outro algoritmo são recusados (ataque de troca de algoritmo).
 */

function jwtB64Codificar(string $dados): string {
    return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
}

/** Devolve null se não for base64url válido. */
function jwtB64Descodificar(string $texto): ?string {
    if ($texto === '' || preg_match('/[^A-Za-z0-9_-]/', $texto)) return null;
    $bin = base64_decode(strtr($texto, '-_', '+/') . str_repeat('=', (4 - strlen($texto) % 4) % 4), true);
    return $bin === false ? null : $bin;
}

function jwtCriar(array $payload, string $segredo): string {
    $cab = jwtB64Codificar(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $dados = $cab . '.' . jwtB64Codificar(json_encode($payload));
    return $dados . '.' . jwtB64Codificar(hash_hmac('sha256', $dados, $segredo, true));
}

/** Payload do token se a assinatura e o formato forem válidos; null em qualquer outro caso. */
function jwtValidar(string $jwt, string $segredo): ?array {
    $partes = explode('.', $jwt);
    if (count($partes) !== 3) return null;
    [$cab64, $pay64, $ass64] = $partes;

    $cab = jwtB64Descodificar($cab64);
    $pay = jwtB64Descodificar($pay64);
    if ($cab === null || $pay === null) return null;

    $cabecalho = json_decode($cab, true);
    if (!is_array($cabecalho) || ($cabecalho['alg'] ?? null) !== 'HS256') return null;

    // Compara o TEXTO da assinatura (forma canónica): o último carácter base64url só usa 2 dos seus 6 bits,
    // por isso comparar os bytes descodificados aceitaria 4 variantes da mesma assinatura.
    $esperada = jwtB64Codificar(hash_hmac('sha256', $cab64 . '.' . $pay64, $segredo, true));
    if (!hash_equals($esperada, $ass64)) return null;

    $payload = json_decode($pay, true);
    return is_array($payload) ? $payload : null;
}
