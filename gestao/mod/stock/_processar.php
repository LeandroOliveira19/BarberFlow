<?php
declare(strict_types=1);
// stock: processamento original do formulário; cada ação define $acao e inclui este ficheiro (não é uma ação)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }


    if ($eGestor) {
        // ---------- GESTOR: desativar material ----------
        if ($acao === 'apagar') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE materiais SET ativo = 0 WHERE id = ? AND barbearia_id = ?");
            $stmt->execute([$id, $bid]);
            $msg = 'Material desativado com sucesso.';
        }
        // ---------- GESTOR: criar / editar material ----------
        elseif ($acao === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim($_POST['nome'] ?? '');
            $unidade = $_POST['unidade'] ?? 'un';
            $stock_geral = (float)str_replace(',', '.', $_POST['stock_geral'] ?? '0');
            $stock_minimo = (float)str_replace(',', '.', $_POST['stock_minimo'] ?? '0');

            if ($nome === '') {
                $erro = 'O nome do material é obrigatório.';
            } else {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE materiais SET nome=?, unidade=?, stock_geral=?, stock_minimo=?, ativo=1 WHERE id=? AND barbearia_id=?");
                    $stmt->execute([$nome, $unidade, $stock_geral, $stock_minimo, $id, $bid]);
                    $msg = 'Material atualizado com sucesso.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO materiais (barbearia_id, nome, unidade, stock_geral, stock_minimo) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$bid, $nome, $unidade, $stock_geral, $stock_minimo]);
                    $msg = 'Material adicionado com sucesso.';
                }
            }
        }
        // ---------- GESTOR: transferir stock geral para uma bancada ----------
        elseif ($acao === 'transferir') {
            $material_id     = (int)($_POST['material_id'] ?? 0);
            $bancada_destino = (int)($_POST['bancada_id'] ?? 0);
            $quantidade      = (float)str_replace(',', '.', $_POST['quantidade'] ?? '0');

            if ($material_id <= 0 || $bancada_destino <= 0 || $quantidade <= 0) {
                $erro = 'Preenche todos os campos com valores válidos.';
            } else {
                $stmt = $pdo->prepare("SELECT stock_geral, nome FROM materiais WHERE id = ? AND barbearia_id = ? AND ativo = 1");
                $stmt->execute([$material_id, $bid]);
                $material = $stmt->fetch();

                if (!$material) {
                    $erro = 'Material inválido ou desativado.';
                } else {
                    $stmt = $pdo->prepare("SELECT id FROM bancadas WHERE id = ? AND barbearia_id = ? AND ativo = 1");
                    $stmt->execute([$bancada_destino, $bid]);
                    if (!$stmt->fetch()) {
                        $erro = 'Bancada inválida ou inativa.';
                    } elseif ((float)$material['stock_geral'] < $quantidade) {
                        $erro = 'Stock geral insuficiente para "' . $material['nome'] . '" (disponível: ' . $material['stock_geral'] . ').';
                    } else {
                        $pdo->beginTransaction();
                        try {
                            // Descontar do stock geral
                            $pdo->prepare("UPDATE materiais SET stock_geral = stock_geral - ? WHERE id = ? AND barbearia_id = ?")
                                ->execute([$quantidade, $material_id, $bid]);

                            // Somar (ou criar) na bancada de destino
                            $stmt = $pdo->prepare("SELECT 1 FROM bancada_materiais WHERE bancada_id = ? AND material_id = ?");
                            $stmt->execute([$bancada_destino, $material_id]);
                            if ($stmt->fetch()) {
                                $pdo->prepare("UPDATE bancada_materiais SET quantidade = quantidade + ? WHERE bancada_id = ? AND material_id = ?")
                                    ->execute([$quantidade, $bancada_destino, $material_id]);
                            } else {
                                // quantidade_minima começa a 0 (sem alerta até o gestor definir)
                                $pdo->prepare("INSERT INTO bancada_materiais (bancada_id, material_id, quantidade, quantidade_minima) VALUES (?, ?, ?, 0)")
                                    ->execute([$bancada_destino, $material_id, $quantidade]);
                            }

                            $pdo->commit();
                            $msg = 'Material transferido para a bancada com sucesso.';
                        } catch (Exception $e) {
                            $pdo->rollBack();
                            $erro = 'Erro ao transferir o material: ' . $e->getMessage();
                        }
                    }
                }
            }
        }
    } else {
        // ---------- BARBEIRO: pedir reposição ----------
        if ($acao === 'pedir_reposicao' && $bancada_id) {
            $material_id = (int)($_POST['material_id'] ?? 0);
            $quantidade  = (float)str_replace(',', '.', $_POST['quantidade'] ?? '0');

            if ($material_id <= 0 || $quantidade <= 0) {
                $erro = 'Indica uma quantidade válida.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT bm.material_id
                    FROM bancada_materiais bm
                    JOIN materiais m ON bm.material_id = m.id
                    WHERE bm.bancada_id = ? AND bm.material_id = ? AND m.barbearia_id = ? AND m.ativo = 1
                ");
                $stmt->execute([$bancada_id, $material_id, $bid]);
                if (!$stmt->fetch()) {
                    $erro = 'Material inválido para a tua bancada.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO pedidos_reposicao (barbearia_id, bancada_id, material_id, quantidade, estado) VALUES (?, ?, ?, ?, 'pendente')");
                    $stmt->execute([$bid, $bancada_id, $material_id, $quantidade]);
                    $msg = 'Pedido de reposição enviado ao gestor com sucesso.';
                }
            }
        }
    }
