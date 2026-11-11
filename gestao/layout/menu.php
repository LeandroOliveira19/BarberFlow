<?php
// Menu lateral da gestão (incluído por gestao/index.php).
// Realça o módulo ativo ($modulo) e só mostra as opções permitidas ao perfil ($MODULOS[$perfil]).
if (count(get_included_files()) === 1) { header('Location: ../index.php'); exit; }

/** [módulo, ação por omissão, texto] — a ordem do menu */
$entradasMenu = [
    ['dashboard',    'ver',    'Dashboard'],
    ['agenda',       'listar', 'Agenda de Marcações'],
    ['perfil',       'editar', 'O Meu Perfil'],
    ['servicos',     'listar', 'Serviços'],
    ['bancadas',     'listar', 'Bancadas'],
    ['barbeiros',    'listar', 'Barbeiros'],
    ['pedidos',      'listar', 'Pedidos de Stock'],
    ['fornecedores', 'listar', 'Fornecedores'],
    ['stock',        'listar', $eGestor ? 'Stock e Materiais' : 'Minha Bancada'],
    ['definicoes',   'editar', 'Definições'],
];
?>
<div class="sidebar">
    <a href="index.php" class="logo">BarberFlow</a>
    <nav>
        <?php foreach ($entradasMenu as [$m, $a, $texto]): ?>
            <?php if (isset($MODULOS[$perfil][$m])): ?>
                <a href="<?= e(urlModulo($m, $a)) ?>" class="<?= $modulo === $m ? 'active' : '' ?>"><?= e($texto) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
</div>
