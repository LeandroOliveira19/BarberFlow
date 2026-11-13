<?php
declare(strict_types=1);

/**
 * Funções do modelo de aplicação modular (atividade 06 de PAW).
 * Usadas por gestao/index.php e pelo index.php público, e pelos ficheiros de módulo.
 */

// Este ficheiro só pode ser incluído, nunca aberto diretamente (slide 19)
if (count(get_included_files()) === 1) {
    header('Location: ../index.php');
    exit;
}

/*
 * Ficheiros de módulo, layout e classes começam pela verificação do slide 19, escrita no próprio ficheiro
 * (quando são abertos diretamente, este ficheiro ainda não foi carregado):
 *
 *   if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
 */

/** Lê um parâmetro de navegação (m, a) do URL. Só aceita [a-z_]; qualquer outra coisa → $omissao. */
function lerParametro(string $nome, string $omissao): string {
    $v = filter_input(INPUT_GET, $nome, FILTER_UNSAFE_RAW);
    if ($v === null || $v === false) $v = $_GET[$nome] ?? null;   // filter_input não vê $_GET alterado (CLI/testes)
    return (is_string($v) && preg_match('/^[a-z_]{1,40}$/', $v)) ? $v : $omissao;
}

/** O módulo/ação está na lista branca do perfil? (formato: ['agenda' => ['listar', 'estado'], ...]) */
function validarModulo(string $m, string $a, array $permitidos): bool {
    return isset($permitidos[$m]) && in_array($a, $permitidos[$m], true);
}

/** URL interno de um módulo/ação: index.php?m=...&a=...&extra */
function urlModulo(string $m, string $a, array $extra = []): string {
    return 'index.php?' . http_build_query(['m' => $m, 'a' => $a] + $extra);
}

/** Mensagem para mostrar no próximo pedido (sobrevive ao redirect). $tipo: sucesso | erro | aviso */
function flash(string $tipo, string $texto): void {
    $_SESSION['flash'][] = ['tipo' => $tipo, 'texto' => $texto];
}

/** @return list<array{tipo:string, texto:string}> Lê e apaga as mensagens pendentes. */
function lerFlashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($f) ? $f : [];
}

function tokenCsrf(): string {
    if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

/** Campo escondido para colocar em todos os formulários POST. */
function campoCsrf(): string {
    return '<input type="hidden" name="csrf" value="' . tokenCsrf() . '">';
}

function csrfValido(): bool {
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado) && $enviado !== '' && hash_equals(tokenCsrf(), $enviado);
}

function redirecionar(string $url): never {
    header('Location: ' . $url);
    exit;
}

/** Texto para LIKE ... ESCAPE '\\': o que o utilizador escreve é procurado como texto (% e _ não são curingas). */
function likeSeguro(string $texto): string {
    return '%' . addcslashes($texto, '\\%_') . '%';
}
