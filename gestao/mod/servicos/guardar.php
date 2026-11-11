<?php
declare(strict_types=1);
// Módulo: servicos / guardar — (POST) O CSRF e o perfil já foram validados pelo gestao/index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlModulo('servicos', 'listar'));

$msg = '';
$erro = '';
$nova_pass_info = '';

        $id = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $duracao = (int)($_POST['duracao_minutos'] ?? 30);
        $preco = (float)str_replace(',', '.', $_POST['preco'] ?? '0');
        $descricao = trim($_POST['descricao'] ?? '');

        if ($nome === '' || $preco <= 0) {
            $erro = 'O nome e o preço são obrigatórios.';
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE servicos SET nome=?, duracao_minutos=?, preco=?, descricao=?, ativo=1 WHERE id=? AND barbearia_id=?");
                $stmt->execute([$nome, $duracao, $preco, $descricao, $id, $bid]);
                $msg = 'Serviço atualizado com sucesso.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO servicos (barbearia_id, nome, duracao_minutos, preco, descricao) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$bid, $nome, $duracao, $preco, $descricao]);
                $msg = 'Serviço adicionado com sucesso.';
            }
        }

if ($erro) flash('erro', $erro);
if ($msg) flash('sucesso', $msg);
redirecionar(urlModulo('servicos', 'listar'));
