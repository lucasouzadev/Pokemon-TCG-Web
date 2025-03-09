<?php
session_start();
require_once 'config.php';
require_once 'notificacoes.php';
require_once 'contadores.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Atualizar a última visita à coleção
atualizarUltimaVisitaColecao($usuario_id);

// Filtros
$tipo = isset($_GET['tipo']) ? limparDados($_GET['tipo']) : '';
$raridade = isset($_GET['raridade']) ? limparDados($_GET['raridade']) : '';
$busca = isset($_GET['busca']) ? limparDados($_GET['busca']) : '';

// Construir a consulta SQL com filtros
$sql = "
    SELECT c.*, MIN(co.id) as colecao_id, SUM(co.quantidade) as quantidade, 
           MIN(co.origem) as origem, MIN(co.data_obtencao) as data_obtencao
    FROM colecao co 
    JOIN cartas c ON co.carta_id = c.id 
    WHERE co.usuario_id = ? AND co.quantidade > 0
";
$params = [$_SESSION['usuario_id']];

if (!empty($tipo)) {
    $sql .= " AND c.tipo = ?";
    $params[] = $tipo;
}

if (!empty($raridade)) {
    $sql .= " AND c.raridade = ?";
    $params[] = $raridade;
}

if (!empty($busca)) {
    $sql .= " AND (c.nome LIKE ? OR c.descricao LIKE ?)";
    $params[] = "%$busca%";
    $params[] = "%$busca%";
}

$sql .= " GROUP BY c.id ORDER BY c.raridade DESC, c.nome";

// Executar a consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cartas = $stmt->fetchAll();

// Buscar tipos e raridades disponíveis para os filtros
$stmt = $pdo->query("SELECT DISTINCT tipo FROM cartas ORDER BY tipo");
$tipos = $stmt->fetchAll();

$stmt = $pdo->query("SELECT DISTINCT raridade FROM cartas ORDER BY raridade");
$raridades = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minha Coleção - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="collection">
        <div class="container">
            <h1>Minha Coleção</h1>
            
            <div class="collection-filters">
                <form method="get" action="colecao.php" class="filter-form">
                    <div class="form-group">
                        <input type="text" name="busca" placeholder="Buscar cartas..." value="<?php echo htmlspecialchars($busca); ?>">
                    </div>
                    
                    <div class="form-group">
                        <select name="tipo">
                            <option value="">Todos os tipos</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['tipo']); ?>" <?php echo $tipo === $t['tipo'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t['tipo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <select name="raridade">
                            <option value="">Todas as raridades</option>
                            <?php foreach ($raridades as $r): ?>
                                <option value="<?php echo htmlspecialchars($r['raridade']); ?>" <?php echo $raridade === $r['raridade'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($r['raridade']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn-secondary">Filtrar</button>
                    <a href="colecao.php" class="btn-link">Limpar filtros</a>
                </form>
            </div>
            
            <div class="collection-stats">
                <p>Total de cartas na coleção: <strong><?php echo count($cartas); ?></strong></p>
            </div>
            
            <?php if (empty($cartas)): ?>
                <div class="empty-collection">
                    <p>Você ainda não tem cartas na sua coleção.</p>
                    <a href="loja.php" class="btn-primary">Visitar a loja</a>
                </div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($cartas as $carta): ?>
                        <?php
                        $origem_class = '';
                        $origem_badge = '';
                        $origem_texto = '';
                        
                        if ($carta['origem'] == 'troca') {
                            $origem_class = 'origem-troca';
                            $origem_badge = '<span class="card-origem-badge origem-troca-badge">Troca</span>';
                            $origem_texto = 'Obtida através de troca';
                        } elseif ($carta['origem'] == 'batalha') {
                            $origem_class = 'origem-batalha';
                            $origem_badge = '<span class="card-origem-badge origem-batalha-badge">Batalha</span>';
                            $origem_texto = 'Conquistada em batalha';
                        } elseif ($carta['origem'] == 'evento') {
                            $origem_class = 'origem-evento';
                            $origem_badge = '<span class="card-origem-badge origem-evento-badge">Evento</span>';
                            $origem_texto = 'Obtida em evento especial';
                        } elseif ($carta['origem'] == 'pacote') {
                            $origem_class = 'origem-pacote';
                            $origem_badge = '<span class="card-origem-badge origem-pacote-badge">Pacote</span>';
                            $origem_texto = 'Obtida em pacote da loja';
                        }
                        
                        // Adicionar classe de raridade
                        $raridade_class = '';
                        if (strtolower($carta['raridade']) == 'rara') {
                            $raridade_class = 'rara';
                        } elseif (strtolower($carta['raridade']) == 'ultra rara') {
                            $raridade_class = 'ultra-rara';
                        }

                        $batalha_class = '';
                        if (strtolower($carta['origem']) == 'batalha') {
                            $batalha_class = 'batalha';
                        }
                        $troca_class = '';  
                        if (strtolower($carta['origem']) == 'troca') {
                            $troca_class = 'troca';
                        }
                        $evento_class = '';
                        if (strtolower($carta['origem']) == 'evento') {
                            $evento_class = 'evento';
                        }
                        
                        // Combinar todas as classes em uma string
                        $classes = "card";
                        if (!empty($origem_class)) $classes .= " " . $origem_class;
                        if (!empty($raridade_class)) $classes .= " " . $raridade_class;
                        if (!empty($batalha_class)) $classes .= " " . $batalha_class;
                        if (!empty($troca_class)) $classes .= " " . $troca_class;
                        if (!empty($evento_class)) $classes .= " " . $evento_class;
                        ?>
                        <div class="<?php echo $classes; ?>" data-id="<?php echo $carta['id']; ?>" data-colecao-id="<?php echo $carta['colecao_id']; ?>" data-origem="<?php echo $carta['origem']; ?>" title="<?php echo $origem_texto; ?>">
                            <?php echo $origem_badge; ?>
                            <img src="img/cards/<?php echo htmlspecialchars($carta['imagem']); ?>" alt="<?php echo htmlspecialchars($carta['nome']); ?>">
                            <?php if ($carta['quantidade'] > 1): ?>
                                <div class="card-quantity"><?php echo $carta['quantidade']; ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Substitua o modal existente por este painel lateral -->
    <div class="panel-overlay"></div>
    <div class="card-details-panel">
        <span class="panel-close">&times;</span>
        <div class="panel-card-image">
            <img id="panel-card-image" src="" alt="">
        </div>
        <div class="panel-card-info">
            <h2 id="panel-card-name"></h2>
            
            <div class="panel-attribute">
                <span class="panel-attribute-label">Tipo:</span>
                <span id="panel-card-type" class="panel-attribute-value"></span>
            </div>
            
            <div class="panel-attribute">
                <span class="panel-attribute-label">Raridade:</span>
                <span id="panel-card-rarity" class="panel-attribute-value"></span>
            </div>
            
            <div class="panel-attribute">
                <span class="panel-attribute-label">Quantidade:</span>
                <span id="panel-card-quantity" class="panel-attribute-value"></span>
            </div>
            
            <div class="panel-description">
                <h3>Descrição</h3>
                <p id="panel-card-description"></p>
            </div>
            
            <div id="panel-card-origin-info"></div>
        </div>
        <div class="panel-actions">
            <a id="panel-view-details" href="" class="btn-secondary">Ver detalhes completos</a>
        </div>
    </div>
    <script src="js/main.js"></script>
</body>
</html>
