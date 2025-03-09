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

// Contar cartas na coleção do usuário
$stmt = $pdo->prepare("SELECT COUNT(*) as total_cartas, SUM(quantidade) as total_exemplares FROM colecao WHERE usuario_id = ?");
$stmt->execute([$_SESSION['usuario_id']]);
$colecao = $stmt->fetch();

// Buscar cartas recentes do usuário
$stmt = $pdo->prepare("
    SELECT c.*, co.quantidade, co.data_obtencao 
    FROM colecao co 
    JOIN cartas c ON co.carta_id = c.id 
    WHERE co.usuario_id = ? 
    ORDER BY co.data_obtencao DESC 
    LIMIT 4
");
$stmt->execute([$_SESSION['usuario_id']]);
$cartas_recentes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/png">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="dashboard">
        <div class="container">
            <h1>Bem-vindo, <?php echo htmlspecialchars($usuario['nome']); ?>!</h1>
            
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Sua Coleção</h3>
                    <p>Cartas únicas: <?php echo $colecao['total_cartas'] ?? 0; ?></p>
                    <p>Total de exemplares: <?php echo $colecao['total_exemplares'] ?? 0; ?></p>
                    <a href="colecao.php" class="btn-secondary">Ver coleção</a>
                </div>
                
                <div class="stat-card">
                    <h3>Loja</h3>
                    <p>Adquira novos pacotes de cartas para expandir sua coleção!</p>
                    <a href="loja.php" class="btn-secondary">Visitar loja</a>
                </div>
            </div>
            
            <div class="recent-cards">
                <h2>Cartas Recentes</h2>
                <?php if (empty($cartas_recentes)): ?>
                    <p class="empty-message">Você ainda não tem cartas. Visite a <a href="loja.php">loja</a> para adquirir seus primeiros pacotes!</p>
                <?php else: ?>
                    <div class="cards-grid">
                        <?php foreach ($cartas_recentes as $carta): ?>
                            <div class="card">
                                <img src="img/cards/<?php echo htmlspecialchars($carta['imagem']); ?>" alt="<?php echo htmlspecialchars($carta['nome']); ?>">
                                <div class="card-info">
                                    <h3><?php echo htmlspecialchars($carta['nome']); ?></h3>
                                    <p>Tipo: <?php echo htmlspecialchars($carta['tipo']); ?></p>
                                    <p>Raridade: <?php echo htmlspecialchars($carta['raridade']); ?></p>
                                    <p>Quantidade: <?php echo htmlspecialchars($carta['quantidade']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php include 'componentes/footer.php'; ?>
    <?php include 'componentes/notificacoes_fix.php'; ?>
</body>
</html>
