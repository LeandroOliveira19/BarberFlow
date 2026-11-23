<?php
declare(strict_types=1);
// Módulo: perfil / guardar (POST) — dados pessoais, foto e (só barbeiros) perfil público. O CSRF já foi validado pelo index.php.
// As regras de cada campo estão em config/formularios.php (validarPerfil).
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('perfil', 'editar'));
// Enquanto houver password por alterar, os dados não se podem guardar
if (($_SESSION['precisa_alterar_password'] ?? 0) == 1) redirecionar(urlModulo('perfil', 'editar', ['force' => 1]));

require_once __DIR__ . '/../../../config/formularios.php';
require_once __DIR__ . '/../../../config/uploads.php';

$uid = (int)$_SESSION['utilizador_id'];
$stmt = $pdo->prepare("SELECT tipo, foto_url, telefone, especialidades, bio, instagram FROM utilizadores WHERE id = ? AND barbearia_id = ?");
$stmt->execute([$uid, $bid]);
$atual = $stmt->fetch();
if (!$atual) redirecionar('logout.php');
$eBarbeiro = $atual['tipo'] === 'barbeiro';

// Envio parcial: o que não vier no pedido mantém o valor atual
$dadosPost = $_POST + array_map(fn($v) => (string)($v ?? ''), [
    'telefone' => $atual['telefone'], 'especialidades' => $atual['especialidades'],
    'bio' => $atual['bio'], 'instagram' => $atual['instagram'], 'foto_url' => $atual['foto_url'],
]);

// Foto: um ficheiro enviado tem prioridade; depois a ligação escrita; senão mantém o ficheiro que já lá estava
$fotoGuardada = (string)($atual['foto_url'] ?? '');
if (trim((string)($dadosPost['foto_url'] ?? '')) === '' && $fotoGuardada !== '' && !preg_match('#^https?://#i', $fotoGuardada)) {
    $dadosPost['foto_url'] = $fotoGuardada;
}

$envio = $_FILES['foto_upload'] ?? null;
if (imagemFoiEnviada($envio)) {
    $r = guardarImagemEnviada($envio, dirname(__DIR__, 3) . '/uploads/barbeiros/', 'user_' . $uid);
    if ($r['erro'] !== null) {
        flash('erro', $r['erro']);
        redirecionar(urlModulo('perfil', 'editar'));
    }
    $dadosPost['foto_url'] = 'uploads/barbeiros/' . $r['nome'];
}

$valido = validarPerfil($dadosPost, $eBarbeiro);
if ($valido['erro'] !== null) {
    flash('erro', $valido['erro']);
    redirecionar(urlModulo('perfil', 'editar'));
}

$d = $valido['dados'];
if ($eBarbeiro) {
    $stmt = $pdo->prepare("UPDATE utilizadores SET nome = ?, telefone = ?, foto_url = ?, especialidades = ?, bio = ?, instagram = ? WHERE id = ? AND barbearia_id = ?");
    $stmt->execute([$d['nome'], $d['telefone'], $d['foto_url'], $d['especialidades'], $d['bio'], $d['instagram'], $uid, $bid]);
} else {
    $stmt = $pdo->prepare("UPDATE utilizadores SET nome = ?, telefone = ?, foto_url = ? WHERE id = ? AND barbearia_id = ?");
    $stmt->execute([$d['nome'], $d['telefone'], $d['foto_url'], $uid, $bid]);
}
$_SESSION['utilizador_nome'] = $d['nome'];

flash('sucesso', 'Perfil atualizado com sucesso.');
redirecionar(urlModulo('perfil', 'editar'));
