<?php
require_once __DIR__ . '/../config/sessao.php';
iniciarSessaoSegura();
session_destroy();
header('Location: login.php');
exit;