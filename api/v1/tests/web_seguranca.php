<?php
declare(strict_types=1);

/**
 * Testes das funções de segurança puras (sem servidor nem BD): TOTP/base32 (RFC 6238).
 * Uso: php api/v1/tests/web_seguranca.php
 */

$TOTAL = 0; $FALHAS = 0;
function ok(bool $c, string $t): void {
    global $TOTAL, $FALHAS;
    $TOTAL++;
    if (!$c) $FALHAS++;
    echo ($c ? 'OK      ' : 'FALHOU  ') . $t . "\n";
}

if (!is_file(__DIR__ . '/../../../config/totp.php')) {
    ok(false, 'config/totp.php existe');
} else {
    require_once __DIR__ . '/../../../config/totp.php';

    // Base32 (RFC 4648): ida e volta
    ok(base32Codificar('12345678901234567890') === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 'base32 do segredo do RFC 6238');
    ok(base32Decodificar('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ') === '12345678901234567890', 'base32 descodifica');
    ok(base32Decodificar('gezd gnbv gy3t qojq gezd gnbv gy3t qojq') === '12345678901234567890', 'base32 aceita minúsculas e espaços');
    ok(base32Decodificar('ABC1') === null, 'base32 inválido → null');
    $bytes = random_bytes(20);
    ok(base32Decodificar(base32Codificar($bytes)) === $bytes, 'base32 ida e volta com bytes aleatórios');

    // Vetores de teste do RFC 6238 (SHA1, 8 dígitos no RFC; aqui os 6 últimos)
    $seg = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
    ok(totpCodigo($seg, 59) === '287082', 'RFC 6238 T=59 → 287082');
    ok(totpCodigo($seg, 1111111109) === '081804', 'RFC 6238 T=1111111109 → 081804');
    ok(totpCodigo($seg, 1111111111) === '050471', 'RFC 6238 T=1111111111 → 050471');
    ok(totpCodigo($seg, 1234567890) === '005924', 'RFC 6238 T=1234567890 → 005924');
    ok(totpCodigo($seg, 2000000000) === '279037', 'RFC 6238 T=2000000000 → 279037');

    // Janela de ±1 período
    $t = 1700000000;
    ok(totpVerificar($seg, totpCodigo($seg, $t), $t), 'código atual aceite');
    ok(totpVerificar($seg, totpCodigo($seg, $t - 30), $t), 'código do período anterior aceite');
    ok(totpVerificar($seg, totpCodigo($seg, $t + 30), $t), 'código do período seguinte aceite');
    ok(!totpVerificar($seg, totpCodigo($seg, $t - 60), $t), 'código de 2 períodos antes recusado');
    ok(!totpVerificar($seg, '12345', $t) && !totpVerificar($seg, 'abcdef', $t) && !totpVerificar($seg, '', $t), 'código mal formado recusado');

    $novo = totpGerarSegredo();
    ok(strlen((string)base32Decodificar($novo)) === 20, 'segredo novo tem 20 bytes');
    ok($novo !== totpGerarSegredo(), 'segredos novos são diferentes');

    $uri = totpUri($seg, 'gestor+teste@example.com');
    ok(str_starts_with($uri, 'otpauth://totp/') && str_contains($uri, 'secret=' . $seg) && str_contains($uri, 'issuer=BarberFlow'), 'URI otpauth com segredo e emissor');
    ok(str_contains($uri, rawurlencode('gestor+teste@example.com')), 'URI codifica o email');
}

echo "\n$TOTAL testes, $FALHAS falhas\n";
exit($FALHAS > 0 ? 1 : 0);
