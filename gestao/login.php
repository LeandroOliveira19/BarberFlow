<?php
declare(strict_types=1);
require __DIR__ . '/../config/db.php';
session_start();

// Se já estiver logado, vai direto para o painel
if (isset($_SESSION['utilizador_id'])) {
    header('Location: index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM utilizadores WHERE email = ? AND ativo = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // VERIFICAÇÃO REAL
        if ($user && password_verify($password, $user['password_hash'])) {
            
            // Guardar dados na sessão
            $_SESSION['utilizador_id'] = $user['id'];
            $_SESSION['utilizador_nome'] = $user['nome'];
            $_SESSION['barbearia_id'] = $user['barbearia_id'];
            $_SESSION['tipo'] = $user['tipo'];
            
            // Guardar a flag que obriga a alterar a password
            $_SESSION['precisa_alterar_password'] = $user['precisa_alterar_password'] ?? 0;

            // Redirecionar para o painel
            header('Location: index.php');
            exit;
        } else {
            $erro = "Email ou password incorretos.";
        }
    } else {
        $erro = "Preenche todos os campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — BarberFlow</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Outfit:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', sans-serif; background: #0a0a0a; color: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; background-image: linear-gradient(rgba(10, 10, 10, 0.85), rgba(10, 10, 10, 0.95)), url('https://images.unsplash.com/photo-1585747860715-2ba37e788b70?ixlib=rb-4.0.3&auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center; }
        .login-box { background: #121212; padding: 3rem; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08); width: 100%; max-width: 400px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .login-box h1 { font-family: 'Cinzel', serif; color: #cda47c; text-align: center; margin-bottom: 2rem; font-size: 1.8rem; }
        .input-group { margin-bottom: 1.5rem; }
        .input-group label { display: block; font-size: 0.9rem; color: #9ca3af; margin-bottom: 0.5rem; }
        .input-group input { width: 100%; padding: 0.8rem 1rem; background: #1a1a1a; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 6px; color: #fff; font-family: 'Outfit', sans-serif; outline: none; transition: 0.3s; }
        .input-group input:focus { border-color: #cda47c; }
        .btn-submit { width: 100%; padding: 1rem; background: #cda47c; color: #0a0a0a; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; cursor: pointer; transition: 0.3s; }
        .btn-submit:hover { background: #e0be9a; }
        .error-msg { background: rgba(248, 113, 113, 0.1); color: #f87171; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem; text-align: center; border: 1px solid rgba(248, 113, 113, 0.2); }
        .back-link { display: block; text-align: center; margin-top: 1.5rem; color: #9ca3af; text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .back-link:hover { color: #cda47c; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Área Restrita</h1>
        
        <?php if ($erro): ?>
            <div class="error-msg"><?= htmlspecialchars($erro) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="input-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="gestor@barberflow.pt">
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn-submit">Entrar</button>
        </form>
        
        <a href="../index.php?slug=rlbarber" class="back-link">← Voltar ao site</a>
    </div>
</body>
</html>