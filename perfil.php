<?php
session_start();
require_once 'config.php';
require_once 'notificacoes.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar informações do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$usuario = $stmt->fetch();

// Processar atualização de perfil
$mensagem = '';
$tipo_mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = limparDados($_POST['nome']);
    $email = limparDados($_POST['email']);
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    
    // Verificar se o email já existe para outro usuário
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
    $stmt->execute([$email, $_SESSION['usuario_id']]);
    $email_existente = $stmt->fetch();
    
    if ($email_existente) {
        $mensagem = "Este email já está sendo usado por outro usuário.";
        $tipo_mensagem = 'erro';
    } else {
        // Se a senha atual foi fornecida, verificar se está correta
        if (!empty($senha_atual)) {
            if (password_verify($senha_atual, $usuario['senha'])) {
                // Se a nova senha foi fornecida, atualizar a senha
                if (!empty($nova_senha)) {
                    $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, senha = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $senha_hash, $_SESSION['usuario_id']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
                    $stmt->execute([$nome, $email, $_SESSION['usuario_id']]);
                }
                
                $mensagem = "Perfil atualizado com sucesso!";
                $tipo_mensagem = 'sucesso';
                
                // Atualizar informações do usuário
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
                $stmt->execute([$_SESSION['usuario_id']]);
                $usuario = $stmt->fetch();
                
                // Atualizar nome na sessão
                $_SESSION['usuario_nome'] = $nome;
            } else {
                $mensagem = "Senha atual incorreta.";
                $tipo_mensagem = 'erro';
            }
        } else {
            // Se não foi fornecida senha, apenas atualizar nome e email
            $stmt = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
            $stmt->execute([$nome, $email, $_SESSION['usuario_id']]);
            
            $mensagem = "Perfil atualizado com sucesso!";
            $tipo_mensagem = 'sucesso';
            
            // Atualizar informações do usuário
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            $usuario = $stmt->fetch();
            
            // Atualizar nome na sessão
            $_SESSION['usuario_nome'] = $nome;
        }
    }
}

// Estatísticas da coleção
$stmt = $pdo->prepare("SELECT COUNT(*) as total_cartas, SUM(quantidade) as total_exemplares FROM colecao WHERE usuario_id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$colecao = $stmt->fetch();

// Contar cartas por raridade
$stmt = $pdo->prepare("
    SELECT c.raridade, COUNT(*) as quantidade 
    FROM colecao co 
    JOIN cartas c ON co.carta_id = c.id 
    WHERE co.usuario_id = ? 
    GROUP BY c.raridade
");
$stmt->execute([$_SESSION['usuario_id']]);
$cartas_por_raridade = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="profile">
        <div class="container">
            <h1>Meu Perfil</h1>
            
            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <p><?php echo $mensagem; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="profile-content">
                <div class="profile-info">
                    <h2>Informações Pessoais</h2>
                    <form method="post" class="profile-form">
                        <div class="form-group">
                            <label for="nome">Nome</label>
                            <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($usuario['nome']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($usuario['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="senha_atual">Senha Atual (necessária para alterar a senha)</label>
                            <input type="password" id="senha_atual" name="senha_atual">
                        </div>
                        
                        <div class="form-group">
                            <label for="nova_senha">Nova Senha (deixe em branco para manter a atual)</label>
                            <input type="password" id="nova_senha" name="nova_senha">
                        </div>
                        
                        <button type="submit" class="btn-primary">Atualizar Perfil</button>
                    </form>
                </div>
                
                <div class="profile-stats">
                    <h2>Estatísticas da Coleção</h2>
                    
                    <div class="stats-card">
                        <p>Data de cadastro: <?php echo date('d/m/Y', strtotime($usuario['data_cadastro'])); ?></p>
                        <p>Total de cartas únicas: <?php echo $colecao['total_cartas'] ?? 0; ?></p>
                        <p>Total de exemplares: <?php echo $colecao['total_exemplares'] ?? 0; ?></p>
                        
                        <?php if (!empty($cartas_por_raridade)): ?>
                            <h3>Cartas por Raridade</h3>
                            <ul class="raridade-list">
                                <?php foreach ($cartas_por_raridade as $raridade): ?>
                                    <li>
                                        <span class="raridade-nome"><?php echo htmlspecialchars($raridade['raridade']); ?>:</span>
                                        <span class="raridade-qtd"><?php echo $raridade['quantidade']; ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <p>&copy; 2023 Pokémon TCG Pocket. Todos os direitos reservados.</p>
            <p>Pokémon e suas marcas são propriedade da Nintendo, Game Freak e The Pokémon Company.</p>
        </div>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>
