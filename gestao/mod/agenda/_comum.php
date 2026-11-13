<?php
declare(strict_types=1);
// Agenda: estados, filtros e urlAgenda() partilhados por listar.php e estado.php
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

$uid             = (int)$_SESSION['utilizador_id'];
$bid             = (int)$_SESSION['barbearia_id'];
$tipo            = (string)$_SESSION['tipo'];
$nome_utilizador = (string)($_SESSION['utilizador_nome'] ?? '');
$eGestor         = ($tipo === 'gestor');

tokenCsrf(); // garante $_SESSION['csrf'] para os formulários

const ESTADOS = [
    'pendente_verificacao' => 'Por verificar',
    'confirmada'           => 'Confirmada',
    'concluida'            => 'Concluída',
    'cancelada'            => 'Cancelada',
    'expirada'             => 'Expirada',
];
const ACOES = [
    'pendente_verificacao' => ['confirmada' => 'Confirmar', 'cancelada' => 'Cancelar'],
    'confirmada'           => ['concluida'  => 'Concluir',  'cancelada' => 'Cancelar'],
    'concluida'            => [],
    'cancelada'            => [],
    'expirada'             => [],
];

$vista = in_array($_GET['vista'] ?? '', ['dia', 'semana', 'proximas'], true) ? $_GET['vista'] : 'dia';

$dataStr = (string)($_GET['data'] ?? '');
$dataRef = DateTime::createFromFormat('!Y-m-d', $dataStr);
if (!$dataRef || $dataRef->format('Y-m-d') !== $dataStr) {
    $dataRef = new DateTime('today');
}

$filtroEstado   = isset(ESTADOS[$_GET['estado'] ?? '']) ? (string)$_GET['estado'] : '';
$filtroBarbeiro = $eGestor ? (int)($_GET['barbeiro'] ?? 0) : 0;
// Pesquisa por nome ou telemóvel do cliente (até 60 caracteres)
$pesquisa = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';

function urlAgenda(array $sobrepor = [], string $acao = 'listar'): string {
    global $vista, $dataRef, $filtroEstado, $filtroBarbeiro, $pesquisa;
    $p = array_merge([
        'vista'    => $vista,
        'data'     => $dataRef->format('Y-m-d'),
        'estado'   => $filtroEstado,
        'barbeiro' => $filtroBarbeiro ?: '',
        'q'        => $pesquisa,
    ], $sobrepor);
    return urlModulo('agenda', $acao, array_filter($p, fn($v) => $v !== '' && $v !== null));
}
