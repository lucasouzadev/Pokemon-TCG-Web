<?php
session_start();
require_once 'config.php';
require_once 'notificacoes.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Não autorizado']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// Verificar se o ID da carta foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'ID da carta não fornecido']);
        exit;
    }
    header('Location: colecao.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$carta_id = $_GET['id'];

// Buscar informações da carta
$stmt = $pdo->prepare("
    SELECT c.*, co.quantidade, co.origem, co.data_obtencao 
    FROM cartas c 
    LEFT JOIN colecao co ON c.id = co.carta_id AND co.usuario_id = ? 
    WHERE c.id = ?
");
$stmt->execute([$_SESSION['usuario_id'], $carta_id]);
$carta = $stmt->fetch(PDO::FETCH_ASSOC);

// Se a carta não existir, redirecionar ou retornar erro
if (!$carta) {
    if (isset($_GET['format']) && $_GET['format'] === 'json') {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Carta não encontrada']);
        exit;
    }
    header('Location: colecao.php');
    exit;
}

// Buscar histórico de aquisição da carta
$stmt = $pdo->prepare("
    SELECT origem, COUNT(*) as total, MAX(data_obtencao) as ultima_data
    FROM colecao 
    WHERE carta_id = ? AND usuario_id = ?
    GROUP BY origem
    ORDER BY ultima_data DESC
");
$stmt->execute([$carta_id, $usuario_id]);
$origens = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Se o formato solicitado for JSON, retornar os dados da carta como JSON
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($carta);
    exit;
}

// Continuar com o HTML normal se não for solicitado JSON
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($carta['nome']); ?> - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/png">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include 'componentes/header.php'; ?>

    <section class="card-details">
        <div class="container">
            <div class="back-link">
                <a href="colecao.php">&larr; Voltar para a coleção</a>
            </div>
            
            <div class="card-detail-content">
                <div class="card-image">
                    <img src="img/cards/<?php echo htmlspecialchars($carta['imagem']); ?>" alt="<?php echo htmlspecialchars($carta['nome']); ?>">
                </div>
                
                <div class="card-info-detailed">
                    <h1><?php echo htmlspecialchars($carta['nome']); ?></h1>
                    
                    <div class="card-attributes">
                        <div class="attribute">
                            <span class="attribute-label">Tipo:</span>
                            <span class="attribute-value"><?php echo htmlspecialchars($carta['tipo']); ?></span>
                        </div>
                        
                        <div class="attribute">
                            <span class="attribute-label">Raridade:</span>
                            <span class="attribute-value"><?php echo htmlspecialchars($carta['raridade']); ?></span>
                        </div>
                        
                        <div class="attribute">
                            <span class="attribute-label">HP:</span>
                            <span class="attribute-value"><?php echo htmlspecialchars($carta['hp']); ?></span>
                        </div>
                        
                        <?php if (isset($carta['quantidade'])): ?>
                            <div class="attribute">
                                <span class="attribute-label">Quantidade na coleção:</span>
                                <span class="attribute-value"><?php echo htmlspecialchars($carta['quantidade']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-description">
                        <h3>Descrição</h3>
                        <p><?php echo htmlspecialchars($carta['descricao']); ?></p>
                    </div>
                    
                    <?php if (!empty($origens)): ?>
                    <div class="card-acquisition-history">
                        <h3>Histórico de Aquisição</h3>
                        <div class="acquisition-tabs">
                            <?php 
                            // Origens que queremos mostrar
                            $todas_origens = ['pacote', 'troca', 'batalha', 'evento'];
                            
                            // Criar um array associativo com as origens existentes
                            $origens_existentes = [];
                            foreach ($origens as $origem) {
                                $origens_existentes[$origem['origem']] = $origem;
                            }
                            
                            // Mostrar todas as origens possíveis
                            foreach ($todas_origens as $tipo_origem):
                                // Verificar se temos esta origem nos dados
                                $tem_origem = isset($origens_existentes[$tipo_origem]);
                                $dados_origem = $tem_origem ? $origens_existentes[$tipo_origem] : null;
                                $quantidade = $tem_origem ? $dados_origem['total'] : 0;
                            ?>
                                <div class="acquisition-tab <?php echo $quantidade > 0 ? 'ativa' : 'inativa'; ?>">
                                    <div class="acquisition-icon <?php echo $tipo_origem; ?>">
                                        <img src="img/icons/<?php 
                                            if ($tipo_origem === 'troca') {
                                                echo 'trade-icon.png';
                                            } elseif ($tipo_origem === 'batalha') {
                                                echo 'battle-icon.png';
                                            } elseif ($tipo_origem === 'evento') {
                                                echo 'event-icon.png';
                                            } else {
                                                echo 'store-icon.png';
                                            }
                                        ?>" alt="<?php echo ucfirst($tipo_origem); ?>">
                                    </div>
                                    <div class="acquisition-info">
                                        <h4><?php 
                                            if ($tipo_origem === 'troca') {
                                                echo 'Obtida por Troca';
                                            } elseif ($tipo_origem === 'batalha') {
                                                echo 'Troféu de Batalha';
                                            } elseif ($tipo_origem === 'evento') {
                                                echo 'Carta de Evento';
                                            } else {
                                                echo 'Obtida por Pacote';
                                            }
                                        ?></h4>
                                        <p>Quantidade: <?php echo htmlspecialchars($quantidade); ?></p>
                                        <p>Duplicada: <?php echo htmlspecialchars($carta['quantidade']); ?></p>
                                        <?php if ($quantidade > 0): ?>
                                            <p>Última aquisição: <?php echo date('d/m/Y', strtotime($dados_origem['ultima_data'])); ?></p>
                                        <?php else: ?>
                                            <p>Nenhuma carta obtida desta forma</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <script src="js/main.js"></script>
</body>
</html>
