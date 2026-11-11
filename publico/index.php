<?php
// Página antiga: agora é o módulo "site" do index.php público (atividade 06)
require __DIR__ . '/../config/redirecionar_antigo.php';
redirecionarParaModulo('../index.php', 'site', 'ver');
