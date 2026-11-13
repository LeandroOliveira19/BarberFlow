<?php
declare(strict_types=1);

/**
 * BarberFlow — ponto de entrada ÚNICO da parte pública (modelo da atividade 06 de PAW).
 *
 *   1. Sessão (CSRF e contexto da verificação) e barbearia pelo slug (?slug=..., por omissão rlbarber).
 *   2. Obtém o módulo e a ação (?m=...&a=...) e valida-os contra a lista branca.
 *   3. Em pedidos POST, valida o token CSRF.
 *   4. Inclui o ficheiro do módulo (mod/<módulo>/<ação>.php) com a saída em buffer.
 *   5. Desenha a estrutura da página com o <head> do módulo ($CABECALHO) e o conteúdo.
 *
 * A área de gestão tem o seu próprio ponto de entrada (gestao/index.php) e a app usa a API (api/v1/).
 */

require __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/marcacoes.php';   // utilitários e regras (e(), corHex(), ...)
require_once __DIR__ . '/config/modular.php';
ini_set('display_errors', '0');

require_once __DIR__ . '/config/sessao.php';
iniciarSessaoSegura();
tokenCsrf();

/** Módulos e ações da parte pública (lista branca). A 1.ª ação é a de omissão. */
$MODULOS = [
    'site'      => ['ver'],
    'marcar'    => ['formulario'],
    'verificar' => ['formulario'],
];

/* ---------- 1. Barbearia (multi-tenant: sempre pelo slug, resolvido aqui) ---------- */
$slugPedido = $_GET['slug'] ?? 'rlbarber';
$slug = (is_string($slugPedido) && preg_match('/^[a-z0-9-]{1,60}$/', $slugPedido)) ? $slugPedido : '';
$st = $pdo->prepare("SELECT * FROM barbearias WHERE slug = ? AND ativa = 1");
$st->execute([$slug]);
$barbearia = $st->fetch(PDO::FETCH_ASSOC);
if (!$barbearia) {
    http_response_code(404);
    $CABECALHO = '<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Barbearia não encontrada</title>';
    $conteudo  = '<div style="font-family:system-ui;background:#0a0a0a;color:#f4f1ec;display:grid;place-items:center;min-height:100vh;text-align:center;padding:2rem">'
               . '<div><h1>Barbearia não encontrada</h1><p style="color:#a1a1a6;margin-top:.5rem">Confirma o endereço que recebeste.</p></div></div>';
    require __DIR__ . '/layout/estrutura.php';
    exit;
}
$_GET['slug'] = $barbearia['slug'];   // os módulos leem o slug daqui

/* ---------- 2. Módulo e ação ---------- */
$modulo = lerParametro('m', 'site');
$acao   = lerParametro('a', $MODULOS[$modulo][0] ?? 'ver');
if (!validarModulo($modulo, $acao, $MODULOS) || !is_file(__DIR__ . "/mod/$modulo/$acao.php")) {
    $modulo = 'site';
    $acao   = 'ver';
}

/* ---------- 3. CSRF em todos os pedidos POST ---------- */
// Os formulários do cliente (marcar, verificar) não perdem o que foi escrito: em vez de
// redirecionar, o módulo recebe $csrfValido = false, não grava nada e mostra "A sessão expirou".
$csrfValido = $_SERVER['REQUEST_METHOD'] !== 'POST' || csrfValido();
if (!$csrfValido && !in_array($modulo, ['marcar', 'verificar'], true)) {
    redirecionar(urlModulo($modulo, $MODULOS[$modulo][0], ['slug' => $barbearia['slug']]));
}

/* ---------- 4. Incluir o módulo (saída em buffer) ---------- */
$CABECALHO = '';
ob_start();
require __DIR__ . "/mod/$modulo/$acao.php";
$conteudo = (string)ob_get_clean();

/* ---------- 5. Desenhar a página ---------- */
require __DIR__ . '/layout/estrutura.php';
