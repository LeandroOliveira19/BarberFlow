<?php
// Página antiga: agora é o módulo "agenda" do gestao/index.php (atividade 06)
require __DIR__ . '/../config/redirecionar_antigo.php';
redirecionarParaModulo('index.php', 'agenda', 'listar');
