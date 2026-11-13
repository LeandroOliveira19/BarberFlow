<?php
declare(strict_types=1);
// Módulo: barbeiros / criar (POST) — convida um barbeiro: a conta fica "por ativar" até ele escolher a senha
// pelo link recebido por email (gestao/ativar.php). O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('barbeiros', 'listar'));

require_once __DIR__ . '/../../../config/contas.php';
require_once __DIR__ . '/_convite.php';

$nome       = trim((string)($_POST['nome'] ?? ''));
$email      = trim((string)($_POST['email'] ?? ''));
$telefone   = trim((string)($_POST['telefone'] ?? ''));
$bancada_id = !empty($_POST['bancada_id']) ? (int)$_POST['bancada_id'] : null;   // opcional: pode atribuir-se depois

if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    flash('erro', 'Indica o nome e um email válido.');
    redirecionar(urlModulo('barbeiros', 'listar'));
}
$st = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
$st->execute([$email]);
if ($st->fetch()) {
    flash('erro', 'Este email já está registado.');
    redirecionar(urlModulo('barbeiros', 'listar'));
}

$pdo->beginTransaction();
try {
    // Senha aleatória que ninguém conhece: só o link do convite permite escolher a verdadeira
    $pdo->prepare("INSERT INTO utilizadores (barbearia_id, nome, email, password_hash, telefone, tipo, precisa_alterar_password)
                   VALUES (?, ?, ?, ?, ?, 'barbeiro', 0)")
        ->execute([$bid, $nome, $email, password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT), $telefone !== '' ? $telefone : null]);
    $novoId = (int)$pdo->lastInsertId();
    if ($bancada_id) {
        $pdo->prepare("UPDATE bancadas SET barbeiro_id = ? WHERE id = ? AND barbearia_id = ? AND barbeiro_id IS NULL")
            ->execute([$novoId, $bancada_id, $bid]);
    }
    $token = criarConvite($pdo, $novoId);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[barbeiros/criar] ' . $e->getMessage());
    flash('erro', 'Não foi possível criar o barbeiro. Tenta de novo.');
    redirecionar(urlModulo('barbeiros', 'listar'));
}

enviarConviteEAvisar($pdo, $bid, $nome, $email, $token);
redirecionar(urlModulo('barbeiros', 'listar'));
