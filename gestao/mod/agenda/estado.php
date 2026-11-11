<?php
declare(strict_types=1);
// Módulo: agenda / estado (POST) — muda o estado de uma marcação. O CSRF já foi validado pelo index.php.
if (count(get_included_files()) === 1) { header('Location: ../../index.php'); exit; }

require_once __DIR__ . '/_comum.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirecionar(urlAgenda());

    $id   = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
    $novo = (string)($_POST['estado'] ?? '');

    if ($id && isset(ESTADOS[$novo])) {
        $sql    = "UPDATE marcacoes SET estado = ? WHERE id = ? AND barbearia_id = ?";
        $params = [$novo, $id, $bid];
        if (!$eGestor) { 
            $sql     .= " AND barbeiro_id = ?";
            $params[] = $uid;
        }
        $pdo->prepare($sql)->execute($params);
    }
    header('Location: ' . urlAgenda());
    exit;
