<?php
declare(strict_types=1);
// Módulo: barbeiros / criar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('barbeiros', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';
    $id = (int)($_POST['id'] ?? 0);

        $nome = trim($_POST['nome'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $bancada_id = !empty($_POST['bancada_id']) ? (int)$_POST['bancada_id'] : null;

        if ($nome && filter_var($email, FILTER_VALIDATE_EMAIL) && $bancada_id) {
            // Verificar se email já existe
            $stmt = $pdo->prepare("SELECT id FROM utilizadores WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $erro = 'Este email já está registado.';
            } else {
                // Gerar password temporária
                $temp_pass = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
                $hash = password_hash($temp_pass, PASSWORD_DEFAULT);

                $pdo->beginTransaction();
                try {
                    // Inserir utilizador
                    $stmt = $pdo->prepare("INSERT INTO utilizadores (barbearia_id, nome, email, password_hash, telefone, tipo, precisa_alterar_password) VALUES (?, ?, ?, ?, ?, 'barbeiro', 1)");
                    $stmt->execute([$bid, $nome, $email, $hash, $telefone]);
                    $novo_id = $pdo->lastInsertId();

                    // Atribuir a bancada
                    $stmt = $pdo->prepare("UPDATE bancadas SET barbeiro_id = ? WHERE id = ? AND barbearia_id = ? AND barbeiro_id IS NULL");
                    $stmt->execute([$novo_id, $bancada_id, $bid]);

                    $pdo->commit();

                    // Tentar enviar email
                    $email_enviado = enviarCredenciaisEmail($email, $nome, $temp_pass);

                    if ($email_enviado) {
                        $nova_pass_info = "Barbeiro criado com sucesso! O email com as credenciais foi enviado para <strong>{$email}</strong>.";
                    } else {
                        $nova_pass_info = "Barbeiro criado com sucesso! (Aviso: O envio do email falhou). Password temporária: <strong>" . $temp_pass . "</strong> (Entrega esta password ao barbeiro).";
                    }

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $erro = 'Erro ao criar o barbeiro: ' . $e->getMessage();
                }
            }
        } else {
            $erro = 'Preenche todos os campos obrigatórios.';
        }

if ($erro) flash('erro', $erro);
if ($nova_pass_info) flash('sucesso', strip_tags($nova_pass_info));
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('barbeiros', 'listar'));
