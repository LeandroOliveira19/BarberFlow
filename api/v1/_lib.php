<?php
declare(strict_types=1);

/**
 * Helpers HTTP/JSON da API v1 do BarberFlow.
 * Todas as respostas passam por responder()/erro(); qualquer saída que não seja JSON
 * (warnings, die() de outros ficheiros, erros fatais) é descartada pelo handler de shutdown.
 */

$GLOBALS['API_RESPONDIDO'] = false;

function cabecalhosApi(): void {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Cache-Control: no-store');
}

function responder(mixed $dados, int $status = 200): never {
    while (ob_get_level() > 0) ob_end_clean();
    $GLOBALS['API_RESPONDIDO'] = true;
    http_response_code($status);
    cabecalhosApi();
    if ($status !== 204) {
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
    exit;
}

function erro(int $status, string $codigo, string $mensagem, array $extra = []): never {
    responder(['erro' => ['codigo' => $codigo, 'mensagem' => $mensagem] + $extra], $status);
}

/** Garante JSON mesmo quando algo termina o script fora de responder() (die, erro fatal). */
function instalarProtecaoJson(): void {
    ob_start();
    register_shutdown_function(function (): void {
        if ($GLOBALS['API_RESPONDIDO']) return;
        $fatal = error_get_last();
        $saida = '';
        while (ob_get_level() > 0) $saida .= (string)ob_get_clean();
        error_log('[api/v1] resposta não-JSON: ' . substr(strip_tags($saida), 0, 300) . ($fatal ? ' | ' . $fatal['message'] : ''));
        $GLOBALS['API_RESPONDIDO'] = true;
        http_response_code(500);
        cabecalhosApi();
        echo json_encode(['erro' => ['codigo' => 'erro_interno', 'mensagem' => 'Ocorreu um erro. Tenta de novo.']], JSON_UNESCAPED_UNICODE);
    });
}

function corpoJson(): array {
    $bruto = (string)file_get_contents('php://input');
    if (trim($bruto) === '') return [];
    $dados = json_decode($bruto, true);
    if (!is_array($dados)) erro(400, 'json_invalido', 'Pedido inválido.');
    return $dados;
}

function credenciais(): array {
    static $c = null;
    if ($c === null) $c = require __DIR__ . '/../../config/credenciais.php';
    return $c;
}

/** @return array{id:int, nome:string, slug:string, morada:?string} */
function resolverBarbearia(PDO $pdo, string $slug): array {
    $st = $pdo->prepare("SELECT id, nome, slug, morada FROM barbearias WHERE slug = ? AND ativa = 1");
    $st->execute([$slug]);
    $b = $st->fetch(PDO::FETCH_ASSOC);
    if (!$b) erro(404, 'barbearia_nao_encontrada', 'Barbearia não encontrada.');
    $b['id'] = (int)$b['id'];
    return $b;
}

/** O segredo tem de ser exatamente 64 hex (bin2hex(random_bytes(32))); o placeholder do example nunca passa. */
function segredoValido(string $s): bool {
    return strlen($s) === 64 && ctype_xdigit($s);
}

function segredoApi(): string {
    $s = (string)(credenciais()['api_segredo'] ?? '');
    if (!segredoValido($s)) throw new RuntimeException('api_segredo inválido em credenciais.php (gera com bin2hex(random_bytes(32)))');
    return $s;
}

/** Token de acesso a uma marcação confirmada: HMAC do id + cliente (sem guardar nada na BD). */
function tokenMarcacao(int $marcacaoId, int $clienteId): string {
    return hash_hmac('sha256', $marcacaoId . '|' . $clienteId, segredoApi());
}

/** Token que prova ser quem criou a marcação (exigido para verificar/reenviar o código). */
function tokenPendente(int $marcacaoId, int $clienteId): string {
    return hash_hmac('sha256', 'pend|' . $marcacaoId . '|' . $clienteId, segredoApi());
}

function exigirTokenPendente(array $m, array $corpo): void {
    $tok = $corpo['token_pendente'] ?? '';
    $esperado = tokenPendente((int)$m['id'], (int)$m['cliente_id']);
    if (!is_string($tok) || !hash_equals($esperado, $tok)) {
        erro(403, 'token_invalido', 'Não foi possível validar este pedido. Faz uma nova marcação.');
    }
}

/**
 * Decide se a resposta pode incluir o código de confirmação (campo codigo_dev):
 *  - sempre, em desenvolvimento local ('api_dev_devolver_codigo' => true);
 *  - só quando o email falhou, se 'api_codigo_se_email_falhar' => true
 *    (servidor da UA: não deixa sair pedidos para o Brevo, por isso o email nunca chega).
 */
function deveDevolverCodigo(array $cred, bool $emailEnviado): bool {
    if (($cred['api_dev_devolver_codigo'] ?? false) === true) return true;
    return !$emailEnviado && ($cred['api_codigo_se_email_falhar'] ?? false) === true;
}

/** URL base do site (até à pasta que contém api/), ex.: http://host/projeto/projeto/ */
function urlBaseSite(): string {
    $https  = !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
    $host   = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir    = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/api/v1/index.php')));
    $raiz   = preg_replace('#/api/v1$#', '', rtrim($dir, '/'));
    return ($https ? 'https' : 'http') . '://' . $host . $raiz . '/';
}

function urlAbsoluta(?string $caminho): ?string {
    $caminho = trim((string)$caminho);
    if ($caminho === '') return null;
    if (preg_match('#^https?://#i', $caminho)) return $caminho;
    return urlBaseSite() . ltrim(preg_replace('#^(\./|\.\./)+#', '', $caminho), '/');
}
