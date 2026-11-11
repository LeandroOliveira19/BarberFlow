<?php
// Página antiga: agora é o módulo "stock" do gestao/index.php (atividade 06)
require __DIR__ . '/../config/redirecionar_antigo.php';
redirecionarParaModulo('index.php', 'stock', 'listar');
