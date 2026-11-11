<?php
declare(strict_types=1);
// Módulo: perfil / guardar (POST) — dados pessoais e foto. O CSRF já foi validado pelo index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('perfil', 'editar'));
// Enquanto houver password por alterar, os dados não se podem guardar (igual à página antiga)
if (($_SESSION['precisa_alterar_password'] ?? 0) == 1) redirecionar(urlModulo('perfil', 'editar', ['force' => 1]));

$uid  = (int)$_SESSION['utilizador_id'];
$msg  = '';
$erro = '';

        $nome = trim($_POST['nome'] ?? '');
        $foto_url = trim($_POST['foto_url'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $especialidades = trim($_POST['especialidades'] ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        
        // Garantir que a pasta de uploads existe
        $uploadDir = __DIR__ . '/../../../uploads/barbeiros/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (isset($_FILES['foto_upload']) && $_FILES['foto_upload']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['foto_upload'];
            if ($file['size'] > 2 * 1024 * 1024) {
                $erro = "O ficheiro é demasiado grande. Máximo 2MB.";
            } else {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $novoNome = 'user_' . $uid . '_' . time() . '.' . $ext;
                    $destino = $uploadDir . $novoNome;
                    if (move_uploaded_file($file['tmp_name'], $destino)) {
                        $foto_url = 'uploads/barbeiros/' . $novoNome;
                    } else {
                        $erro = "Erro ao mover o ficheiro.";
                    }
                }
            }
        }

        if (!$erro && $nome) {
            $stmt = $pdo->prepare("UPDATE utilizadores SET nome = ?, foto_url = ?, bio = ?, especialidades = ?, instagram = ? WHERE id = ?");
            $stmt->execute([$nome, $foto_url ?: null, $bio, $especialidades, $instagram, $uid]);
            $_SESSION['utilizador_nome'] = $nome;
            $msg = 'Perfil atualizado com sucesso!';
        }

if ($erro) flash('erro', $erro);
elseif ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('perfil', 'editar'));
