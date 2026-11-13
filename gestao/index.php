<?php
declare(strict_types=1);

/**
 * BarberFlow — ponto de entrada ÚNICO da área de gestão (modelo da atividade 06 de PAW).
 *
 *   1. Verifica a sessão (sem sessão → login.php) e a obrigação de alterar a password.
 *   2. Obtém o módulo e a ação (?m=...&a=...) e valida-os contra a lista do perfil.
 *   3. Em pedidos POST, valida o token CSRF (segurança centralizada aqui).
 *   4. Inclui o ficheiro do módulo (mod/<módulo>/<ação>.php) com a saída em buffer,
 *      para que as ações de escrita possam redirecionar (POST → Redirect → GET).
 *   5. Desenha a estrutura da página: cabeçalho, menu, mensagens, conteúdo do módulo, rodapé.
 */

require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/marcacoes.php';   // utilitários partilhados (e(), corHex(), ...)
require_once __DIR__ . '/../config/modular.php';

// Classes das entidades (objects/Servico.php, ...) carregadas quando são usadas pela primeira vez
spl_autoload_register(function (string $classe): void {
    if (preg_match('/^[A-Z][A-Za-z]+$/', $classe) && is_file($f = __DIR__ . '/../objects/' . $classe . '.php')) {
        require_once $f;
    }
});
ini_set('display_errors', '0');

session_start();

/* ---------- 1. Sessão ---------- */
if (!isset($_SESSION['utilizador_id'], $_SESSION['barbearia_id'], $_SESSION['tipo'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$perfil  = $_SESSION['tipo'] === 'gestor' ? 'gestor' : 'barbeiro';
$eGestor = $perfil === 'gestor';
$bid     = (int)$_SESSION['barbearia_id'];

/** Módulos e ações permitidos a cada perfil (lista branca). A 1.ª ação de cada módulo é a de leitura. */
$MODULOS = [
    'gestor' => [
        'dashboard' => ['ver'],
        'agenda'    => ['listar', 'estado'],
        'perfil'    => ['editar', 'guardar', 'alterar_password', 'enviar_codigo', 'ativar_dois_fatores', 'desativar_dois_fatores'],
        'servicos'     => ['listar', 'guardar', 'apagar'],
        'barbeiros'    => ['listar', 'criar', 'editar', 'apagar', 'ativar'],
        'bancadas'     => ['listar', 'criar', 'editar', 'apagar', 'ativar'],
        'fornecedores' => ['listar', 'guardar'],
        'definicoes'   => ['editar', 'guardar'],
        'stock'        => ['listar', 'guardar', 'apagar', 'transferir'],
        'pedidos'      => ['listar', 'aprovar', 'rejeitar', 'entregar'],
    ],
    'barbeiro' => [
        'dashboard' => ['ver'],
        'agenda'    => ['listar', 'estado'],
        'perfil'    => ['editar', 'guardar', 'alterar_password', 'enviar_codigo', 'ativar_dois_fatores', 'desativar_dois_fatores'],
        'stock'     => ['listar', 'pedir_reposicao'],   // a sua bancada
    ],
];

/* ---------- 2. Módulo e ação ---------- */
$modulo = lerParametro('m', 'dashboard');
$acao   = lerParametro('a', $MODULOS[$perfil][$modulo][0] ?? 'ver');

if (!validarModulo($modulo, $acao, $MODULOS[$perfil]) || !is_file(__DIR__ . "/mod/$modulo/$acao.php")) {
    if (isset($_GET['m'])) flash('erro', 'Página não encontrada.');
    $modulo = 'dashboard';
    $acao   = 'ver';
}

// Enquanto tiver de alterar a password, só o perfil está disponível (o logout é um ficheiro à parte).
// Corre depois da validação acima, para que uma ação inválida (?m=perfil&a=xyz) não abra o dashboard.
if (($_SESSION['precisa_alterar_password'] ?? 0) == 1 && $modulo !== 'perfil') {
    redirecionar(urlModulo('perfil', 'editar', ['force' => 1]));
}

/* ---------- 3. CSRF em todos os pedidos POST ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValido()) {
    flash('erro', 'O formulário expirou. Tenta de novo.');
    redirecionar(urlModulo($modulo, $MODULOS[$perfil][$modulo][0]));
}

/* ---------- 4. Incluir o módulo (saída em buffer) ---------- */
ob_start();
require __DIR__ . "/mod/$modulo/$acao.php";
$conteudo = (string)ob_get_clean();

/* ---------- 5. Desenhar a página ---------- */
$mensagens = lerFlashes();
require __DIR__ . '/layout/cabecalho.php';
require __DIR__ . '/layout/menu.php';
if ($mensagens): ?>
    <div class="flashes">
        <?php foreach ($mensagens as $f): ?>
            <div class="flash flash-<?= e($f['tipo']) ?>"><?= e($f['texto']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif;
echo $conteudo;
require __DIR__ . '/layout/rodape.php';
