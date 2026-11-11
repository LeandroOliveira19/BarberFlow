<?php
declare(strict_types=1);
// pedidos: processamento original do formulário; cada ação define $acao e inclui este ficheiro (não é uma ação)
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

    $id   = (int)($_POST['id'] ?? 0);

    if ($id > 0 && in_array($acao, ['aprovar', 'rejeitar', 'entregar'], true)) {
        $stmt = $pdo->prepare("SELECT * FROM pedidos_reposicao WHERE id = ? AND barbearia_id = ?");
        $stmt->execute([$id, $bid]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            $erro = 'Pedido não encontrado.';
        } else {
            // ----- APROVAR: pendente -> aprovado (transfere stock) -----
            if ($acao === 'aprovar' && $pedido['estado'] === 'pendente') {
                $stmt = $pdo->prepare("SELECT stock_geral, nome FROM materiais WHERE id = ? AND barbearia_id = ? AND ativo = 1");
                $stmt->execute([$pedido['material_id'], $bid]);
                $material = $stmt->fetch();

                if (!$material) {
                    $erro = 'O material já não está disponível (foi desativado).';
                } elseif ((float)$material['stock_geral'] < (float)$pedido['quantidade']) {
                    $erro = 'Stock geral insuficiente para "' . $material['nome'] . '" (disponível: ' . $material['stock_geral'] . ').';
                } else {
                    $pdo->beginTransaction();
                    try {
                        // Descontar do stock geral
                        $pdo->prepare("UPDATE materiais SET stock_geral = stock_geral - ? WHERE id = ? AND barbearia_id = ?")
                            ->execute([(float)$pedido['quantidade'], $pedido['material_id'], $bid]);

                        // Somar (ou criar) na bancada
                        $stmt = $pdo->prepare("SELECT 1 FROM bancada_materiais WHERE bancada_id = ? AND material_id = ?");
                        $stmt->execute([$pedido['bancada_id'], $pedido['material_id']]);
                        if ($stmt->fetch()) {
                            $pdo->prepare("UPDATE bancada_materiais SET quantidade = quantidade + ? WHERE bancada_id = ? AND material_id = ?")
                                ->execute([(float)$pedido['quantidade'], $pedido['bancada_id'], $pedido['material_id']]);
                        } else {
                            $pdo->prepare("INSERT INTO bancada_materiais (bancada_id, material_id, quantidade, quantidade_minima) VALUES (?, ?, ?, 0)")
                                ->execute([$pedido['bancada_id'], $pedido['material_id'], (float)$pedido['quantidade']]);
                        }

                        // Marcar como aprovado
                        $pdo->prepare("UPDATE pedidos_reposicao SET estado = 'aprovado' WHERE id = ? AND barbearia_id = ?")
                            ->execute([$id, $bid]);

                        $pdo->commit();
                        $msg = 'Pedido aprovado e material transferido para a bancada.';
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $erro = 'Erro ao aprovar o pedido: ' . $e->getMessage();
                    }
                }
            }
            // ----- REJEITAR: pendente -> rejeitado -----
            elseif ($acao === 'rejeitar' && $pedido['estado'] === 'pendente') {
                $pdo->prepare("UPDATE pedidos_reposicao SET estado = 'rejeitado' WHERE id = ? AND barbearia_id = ?")
                    ->execute([$id, $bid]);
                $msg = 'Pedido rejeitado.';
            }
            // ----- ENTREGAR: aprovado -> entregue (fecho manual) -----
            elseif ($acao === 'entregar' && $pedido['estado'] === 'aprovado') {
                $pdo->prepare("UPDATE pedidos_reposicao SET estado = 'entregue' WHERE id = ? AND barbearia_id = ?")
                    ->execute([$id, $bid]);
                $msg = 'Pedido marcado como entregue.';
            }
        }
    }
