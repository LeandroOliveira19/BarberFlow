<?php
declare(strict_types=1);

/**
 * Utilitários dos testes web: um "browser" com cookies próprios e utilizadores de teste na BD local.
 * Só para testes locais (php -S 127.0.0.1:8080).
 */

const TESTE_PASSWORD = 'Teste!2026';
const TESTE_GESTOR   = 'gestor.teste@example.com';
const TESTE_BARBEIRO = 'barbeiro.teste@example.com';

final class Sessao {
    private string $jar;

    public function __construct(private string $base) {
        $this->jar = (string)tempnam(sys_get_temp_dir(), 'bfcj');
    }

    public function __destruct() { @unlink($this->jar); }

    /** @return array{status:int, html:string, local:string} */
    public function get(string $url): array { return $this->pedido($url, null); }

    /** @return array{status:int, html:string, local:string} */
    public function post(string $url, array $dados): array { return $this->pedido($url, $dados); }

    private function pedido(string $url, ?array $dados): array {
        $ch = curl_init(str_starts_with($url, 'http') ? $url : $this->base . $url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 60,
        ]);
        if ($dados !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($dados));
        }
        $resp = (string)curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $tamCab = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $cab = substr($resp, 0, $tamCab);
        preg_match('/^Location:\s*(.+)$/mi', $cab, $m);
        return ['status' => $status, 'html' => substr($resp, $tamCab), 'local' => trim($m[1] ?? '')];
    }

    public function login(string $email, string $password = TESTE_PASSWORD): bool {
        $r = $this->post('gestao/login.php', ['email' => $email, 'password' => $password]);
        return $r['status'] === 302 && str_contains($r['local'], 'index.php');
    }

    /** Lê o token CSRF de um formulário da página. */
    public static function csrf(string $html): string {
        return preg_match('/name="csrf" value="([0-9a-f]+)"/', $html, $m) ? $m[1] : '';
    }
}

/** Página sem avisos/erros PHP visíveis nem marcas PHP soltas no texto (ex.: "?>" duplicado ao mover código). */
function semErrosPhp(string $html): bool {
    if (preg_match('/(Warning|Notice|Deprecated|Fatal error|Parse error|Uncaught)\b.*?(on line|in \/|in [A-Z]:)/s', $html)) return false;
    $texto = strip_tags(preg_replace('#<(script|style)\b.*?</\1>#s', '', $html));
    return !str_contains($texto, '?>') && !str_contains($texto, '<?');
}

/** Cria (ou recria) um gestor e um barbeiro de teste na barbearia 1. Devolve [gestorId, barbeiroId]. */
function criarUtilizadoresTeste(PDO $pdo): array {
    apagarUtilizadoresTeste($pdo);
    $hash = password_hash(TESTE_PASSWORD, PASSWORD_DEFAULT);
    $st = $pdo->prepare("INSERT INTO utilizadores (nome, email, password_hash, tipo, ativo, barbearia_id, precisa_alterar_password)
                         VALUES (?, ?, ?, ?, 1, 1, 0)");
    $st->execute(['Gestor Teste', TESTE_GESTOR, $hash, 'gestor']);
    $g = (int)$pdo->lastInsertId();
    $st->execute(['Barbeiro Teste', TESTE_BARBEIRO, $hash, 'barbeiro']);
    $b = (int)$pdo->lastInsertId();
    return [$g, $b];
}

function apagarUtilizadoresTeste(PDO $pdo): void {
    $ids = $pdo->query("SELECT id FROM utilizadores WHERE email IN ('" . TESTE_GESTOR . "','" . TESTE_BARBEIRO . "')")
               ->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return;
    $in = implode(',', array_map('intval', $ids));
    // Bancadas criadas automaticamente para o barbeiro de teste (se existirem)
    $pdo->exec("DELETE bm FROM bancada_materiais bm JOIN bancadas b ON b.id = bm.bancada_id WHERE b.barbeiro_id IN ($in)");
    $pdo->exec("DELETE FROM bancadas WHERE barbeiro_id IN ($in)");
    $pdo->exec("DELETE FROM utilizadores WHERE id IN ($in)");
}
