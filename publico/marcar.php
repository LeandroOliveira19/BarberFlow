<?php
// Página antiga: agora é o módulo "marcar" do index.php público (atividade 06)
require __DIR__ . '/../config/redirecionar_antigo.php';
redirecionarParaModulo('../index.php', 'marcar', 'formulario');
