<?php
declare(strict_types=1);
// Módulo: definicoes / guardar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('definicoes', 'editar'));

$msg = '';
$erro = '';
$nova_pass_info = '';

    $nome = trim($_POST['nome'] ?? '');
    $morada = trim($_POST['morada'] ?? '');
    
    if ($nome && $morada) {
        $stmt = $pdo->prepare("UPDATE barbearias SET nome = ?, morada = ? WHERE id = ?");
        $stmt->execute([$nome, $morada, $bid]);

        $configs = [
            'slogan' => $_POST['slogan'] ?? '',
            'sobre' => $_POST['sobre'] ?? '',
            'hora_abertura' => $_POST['hora_abertura'] ?? '09:00',
            'hora_fecho' => $_POST['hora_fecho'] ?? '19:00',
            'tema_cor' => $_POST['tema_cor'] ?? '#cda47c',
            'tema_fundo' => $_POST['tema_fundo'] ?? '#0a0a0a',
            'tema_layout' => $_POST['tema_layout'] ?? 'classico',
            'tema_hero' => $_POST['tema_hero'] ?? '',
            'tema_fonte' => $_POST['tema_fonte'] ?? 'Cinzel',
            'link_instagram' => $_POST['link_instagram'] ?? '',
            'link_facebook' => $_POST['link_facebook'] ?? '',
            'galeria_img_1' => $_POST['galeria_img_1'] ?? '',
            'galeria_img_2' => $_POST['galeria_img_2'] ?? '',
            'galeria_img_3' => $_POST['galeria_img_3'] ?? ''
        ];

        $stmtCfg = $pdo->prepare("INSERT INTO configuracoes (barbearia_id, chave, valor) VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        foreach ($configs as $chave => $valor) {
            $stmtCfg->execute([$bid, $chave, $valor]);
        }
        $msg = 'Definições guardadas com sucesso!';
    }

if ($erro) flash('erro', $erro);
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('definicoes', 'editar'));
