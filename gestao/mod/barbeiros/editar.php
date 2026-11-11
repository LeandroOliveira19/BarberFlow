<?php
declare(strict_types=1);
// Módulo: barbeiros / editar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('barbeiros', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';
    $id = (int)($_POST['id'] ?? 0);

        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $bancada_id = !empty($_POST['bancada_id']) ? (int)$_POST['bancada_id'] : null;

        if ($nome) {
            $pdo->beginTransaction();
            try {
                // Atualizar dados
                $stmt = $pdo->prepare("UPDATE utilizadores SET nome = ?, telefone = ? WHERE id = ? AND barbearia_id = ?");
                $stmt->execute([$nome, $telefone, $id, $bid]);

                // Atualizar Bancada
                // 1. Remover de qualquer bancada atual
                $pdo->prepare("UPDATE bancadas SET barbeiro_id = NULL WHERE barbeiro_id = ? AND barbearia_id = ?")->execute([$id, $bid]);
                // 2. Atribuir à nova
                if ($bancada_id) {
                    $pdo->prepare("UPDATE bancadas SET barbeiro_id = ? WHERE id = ? AND barbearia_id = ?")->execute([$id, $bancada_id, $bid]);
                }
                $pdo->commit();
                $msg = 'Barbeiro atualizado com sucesso!';
            } catch (Exception $e) {
                $pdo->rollBack();
                $erro = 'Erro ao atualizar.';
            }
        }

if ($erro) flash('erro', $erro);
if ($nova_pass_info) flash('sucesso', strip_tags($nova_pass_info));
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('barbeiros', 'listar'));
