<?php
// Estrutura das páginas públicas (incluída pelo index.php). O <head> vem do módulo ($CABECALHO).
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
<?php if ($CABECALHO === ''): ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($barbearia['nome'] ?? 'BarberFlow') ?></title>
<?php else: echo $CABECALHO; endif; ?>
</head>
<body>
<?= $conteudo ?>
</body>
</html>
