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

// Buscar cartas em destaque
$stmt = $pdo->query("SELECT * FROM cartas ORDER BY RAND() LIMIT 4");
$cartasDestaque = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
    
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="home">
        <div class="hero">
            <div class="hero-content">
                <h1>Bem-vindo ao Pokémon TCG Pocket</h1>
                <p>Colecione, troque e jogue com suas cartas Pokémon favoritas!</p>
                <a href="colecao.php" class="btn-primary">Ver minha coleção</a>
            </div>
        </div>
    </section>

    <section class="featured-cards">
        <div class="container">
            <h2>Cartas em Destaque</h2>
            <div class="cards-grid">
                <?php foreach ($cartasDestaque as $carta): ?>
                    <div class="card">
                        <img src="img/cards/<?php echo $carta['imagem']; ?>" alt="<?php echo $carta['nome']; ?>">
                        <div class="card-info">
                            <h3><?php echo $carta['nome']; ?></h3>
                            <p>Tipo: <?php echo $carta['tipo']; ?></p>
                            <p>Raridade: <?php echo $carta['raridade']; ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <h2>Recursos do Jogo</h2>
            <div class="features-grid">
                <div class="feature">
                    <img src="img/icons/collection.png" alt="Coleção">
                    <h3>Coleção</h3>
                    <p>Colecione centenas de cartas Pokémon diferentes.</p>
                </div>
                <div class="feature">
                    <img src="img/icons/battle.png" alt="Batalha">
                    <h3>Batalha</h3>
                    <p>Desafie outros jogadores em batalhas emocionantes.</p>
                </div>
                <div class="feature">
                    <img src="img/icons/trade.png" alt="Troca">
                    <h3>Troca</h3>
                    <p>Troque cartas com amigos para completar sua coleção.</p>
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
