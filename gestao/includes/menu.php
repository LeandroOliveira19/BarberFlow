<?php
// gestao/includes/menu.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pagina_atual = basename($_SERVER['PHP_SELF']);
$eGestor = (isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'gestor');
?>
<div class="sidebar">
    <a href="index.php" class="logo">BarberFlow</a>
    <nav>
        <!-- Visível para todos -->
        <a href="index.php" class="<?= $pagina_atual === 'index.php' ? 'active' : '' ?>">Dashboard</a>
        <a href="agenda.php" class="<?= $pagina_atual === 'agenda.php' ? 'active' : '' ?>">Agenda de Marcações</a>
        <a href="perfil.php" class="<?= $pagina_atual === 'perfil.php' ? 'active' : '' ?>">O Meu Perfil</a>

        <!-- Apenas o Gestor vê os Serviços -->
                <?php if ($eGestor): ?>
            <a href="servicos.php" class="<?= $pagina_atual === 'servicos.php' ? 'active' : '' ?>">Serviços</a>
            <a href="bancadas.php" class="<?= $pagina_atual === 'bancadas.php' ? 'active' : '' ?>">Bancadas</a>
            <a href="barbeiros.php" class="<?= $pagina_atual === 'barbeiros.php' ? 'active' : '' ?>">Barbeiros</a>
            <a href="pedidos.php" class="<?= $pagina_atual === 'pedidos.php' ? 'active' : '' ?>">Pedidos de Stock</a>
            <a href="fornecedores.php" class="<?= $pagina_atual === 'fornecedores.php' ? 'active' : '' ?>">Fornecedores</a>
        <?php endif; ?>

        <!-- Muda o nome consoante o utilizador -->
        <a href="stock.php" class="<?= $pagina_atual === 'stock.php' ? 'active' : '' ?>">
            <?= $eGestor ? 'Stock e Materiais' : 'Minha Bancada' ?>
        </a>

        <!-- Apenas o Gestor vê as Definições -->
        <?php if ($eGestor): ?>
            <a href="definicoes.php" class="<?= $pagina_atual === 'definicoes.php' ? 'active' : '' ?>">Definições</a>
        <?php endif; ?>
    </nav>
</div>