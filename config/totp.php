<?php
declare(strict_types=1);

/**
 * 2.º fator de autenticação com app autenticadora (Google/Microsoft Authenticator…), sem bibliotecas.
 * TOTP (RFC 6238) = HOTP (RFC 4226) com contador = floor(tempo / 30 s), HMAC-SHA1 e truncagem dinâmica.
 * O segredo partilhado viaja em base32 (RFC 4648), que é o formato que as apps leem do QR code.
 */

const BASE32_ALFABETO = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function base32Codificar(string $bytes): string {
    $bits = '';
    foreach (str_split($bytes) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $saida = '';
    foreach (str_split($bits, 5) as $bloco) {
        $saida .= BASE32_ALFABETO[bindec(str_pad($bloco, 5, '0', STR_PAD_RIGHT))];
    }
    return $saida;   // sem '=' (as apps autenticadoras não o usam)
}

/** Aceita minúsculas, espaços e '='; devolve null se houver caracteres fora do alfabeto. */
function base32Decodificar(string $texto): ?string {
    $texto = strtoupper(str_replace([' ', '='], '', $texto));
    if ($texto === '' || strspn($texto, BASE32_ALFABETO) !== strlen($texto)) return null;
    $bits = '';
    foreach (str_split($texto) as $c) $bits .= str_pad(decbin(strpos(BASE32_ALFABETO, $c)), 5, '0', STR_PAD_LEFT);
    $bytes = '';
    foreach (str_split($bits, 8) as $octeto) {
        if (strlen($octeto) === 8) $bytes .= chr(bindec($octeto));   // os bits que sobram no fim são enchimento
    }
    return $bytes;
}

function totpGerarSegredo(): string {
    return base32Codificar(random_bytes(20));   // 160 bits, o tamanho recomendado para HMAC-SHA1
}

/** Código de $digitos para o instante dado (segundos Unix). */
function totpCodigo(string $segredoB32, int $instante, int $periodo = 30, int $digitos = 6): string {
    $chave = base32Decodificar($segredoB32) ?? '';
    $contador = intdiv($instante, $periodo);
    $msg = pack('N2', ($contador >> 32) & 0xFFFFFFFF, $contador & 0xFFFFFFFF);   // 8 bytes big-endian
    $hmac = hash_hmac('sha1', $msg, $chave, true);
    $offset = ord($hmac[19]) & 0x0F;                                              // truncagem dinâmica
    $binario = ((ord($hmac[$offset]) & 0x7F) << 24)
             | ((ord($hmac[$offset + 1]) & 0xFF) << 16)
             | ((ord($hmac[$offset + 2]) & 0xFF) << 8)
             |  (ord($hmac[$offset + 3]) & 0xFF);
    return str_pad((string)($binario % (10 ** $digitos)), $digitos, '0', STR_PAD_LEFT);
}

/** Aceita o código do período atual e de ±$janela períodos (relógios ligeiramente desacertados). */
function totpVerificar(string $segredoB32, string $codigo, int $instante, int $janela = 1): bool {
    if (!preg_match('/^\d{6}$/', $codigo) || base32Decodificar($segredoB32) === null) return false;
    for ($i = -$janela; $i <= $janela; $i++) {
        if (hash_equals(totpCodigo($segredoB32, $instante + $i * 30), $codigo)) return true;
    }
    return false;
}

/** URI lido pelas apps autenticadoras (é o conteúdo do QR code). */
function totpUri(string $segredoB32, string $email, string $emissor = 'BarberFlow'): string {
    return 'otpauth://totp/' . rawurlencode($emissor) . ':' . rawurlencode($email)
         . '?secret=' . $segredoB32 . '&issuer=' . rawurlencode($emissor) . '&algorithm=SHA1&digits=6&period=30';
}
