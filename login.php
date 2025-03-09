<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();
    
    if ($usuario && password_verify($senha, $usuario['senha'])) {
        $_SESSION['usuario_id'] = $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        header('Location: dashboard.php');
        exit;
    } else {
        $erro = "Email ou senha incorretos";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/png">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div>
        <div class="login-form">
            <h2>Login</h2>
            <?php if (isset($erro)): ?>
                <div class="erro"><?php echo $erro; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="senha">Senha</label>
                    <input type="password" id="senha" name="senha" required>
                </div>
                <button type="submit" class="btn-primary">Entrar</button>
            </form>
            <p>Não tem uma conta? <a href="cadastro.php">Cadastre-se</a></p>
        </div>
    </div>
</body>
</html>