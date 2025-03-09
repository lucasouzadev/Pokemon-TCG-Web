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

// Buscar estatísticas de batalha do usuário
$stmt = $pdo->prepare("SELECT * FROM estatisticas_batalha WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$estatisticas = $stmt->fetch();

// Se não existirem estatísticas, criar um registro vazio
if (!$estatisticas) {
    $estatisticas = [
        'vitorias' => 0,
        'derrotas' => 0,
        'empates' => 0,
        'cartas_ganhas' => 0
    ];
}

// Calcular taxa de vitórias
$total_batalhas = $estatisticas['vitorias'] + $estatisticas['derrotas'] + $estatisticas['empates'];
$taxa_vitorias = $total_batalhas > 0 ? round(($estatisticas['vitorias'] / $total_batalhas) * 100, 1) : 0;

// Buscar posição no ranking
$stmt = $pdo->query("
    SELECT r.posicao, u.nome, u.id as usuario_id, r.pontuacao
    FROM ranking r
    JOIN usuarios u ON r.usuario_id = u.id
    GROUP BY u.id
    ORDER BY r.pontuacao DESC, r.ultima_atualizacao ASC
    LIMIT 20
");
$ranking = $stmt->fetchAll();

// Buscar conquistas do usuário
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM usuario_conquistas uc 
    JOIN conquistas c ON uc.conquista_id = c.id 
    WHERE uc.usuario_id = ?
");
$stmt->execute([$usuario_id]);
$conquistas_usuario = $stmt->fetchAll();

// Buscar top 10 jogadores
$stmt = $pdo->prepare("
    SELECT r.posicao, r.pontos, u.nome, r.nivel
    FROM ranking r
    JOIN usuarios u ON r.usuario_id = u.id
    ORDER BY r.posicao ASC
    LIMIT 10
");
$stmt->execute();
$top_jogadores = $stmt->fetchAll();

// Contar total de cartas na coleção
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT carta_id) as total FROM colecao WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$total_cartas = $stmt->fetch()['total'];

// Contar total de cartas disponíveis no jogo
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM cartas");
$stmt->execute();
$total_cartas_jogo = $stmt->fetch()['total'];

// Calcular progresso da coleção
$progresso_colecao = $total_cartas_jogo > 0 ? round(($total_cartas / $total_cartas_jogo) * 100, 1) : 0;

// Buscar a posição do usuário atual no ranking
$stmt = $pdo->prepare("
    SELECT posicao 
    FROM ranking 
    WHERE usuario_id = ?
");
$stmt->execute([$_SESSION['usuario_id']]);
$resultado_ranking = $stmt->fetch();
$posicao_ranking = $resultado_ranking ? $resultado_ranking['posicao'] : 'N/A';

/**
 * Atualiza os pontos de um usuário no ranking
 * @param int $usuario_id ID do usuário
 * @param int $pontos_adicionais Pontos a serem adicionados
 */
function atualizarPontosRanking($pdo, $usuario_id, $pontos_adicionais) {
    // Verificar se o usuário já existe no ranking
    $stmt = $pdo->prepare("SELECT * FROM ranking WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $ranking = $stmt->fetch();
    
    if ($ranking) {
        // Atualizar pontos existentes
        $novos_pontos = $ranking['pontos'] + $pontos_adicionais;
        $stmt = $pdo->prepare("UPDATE ranking SET pontos = ? WHERE usuario_id = ?");
        $stmt->execute([$novos_pontos, $usuario_id]);
    } else {
        // Criar novo registro no ranking
        $stmt = $pdo->prepare("INSERT INTO ranking (usuario_id, pontos, nivel, posicao) VALUES (?, ?, 1, 0)");
        $stmt->execute([$usuario_id, $pontos_adicionais]);
    }
    
    // Recalcular posições no ranking
    recalcularPosicoesRanking($pdo);
}

/**
 * Recalcula as posições de todos os usuários no ranking
 */
function recalcularPosicoesRanking($pdo) {
    // Buscar todos os usuários ordenados por pontos (decrescente)
    $stmt = $pdo->prepare("SELECT usuario_id FROM ranking ORDER BY pontos DESC, nivel DESC");
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Atualizar posição de cada usuário
    $posicao = 1;
    foreach ($usuarios as $usuario_id) {
        $stmt = $pdo->prepare("UPDATE ranking SET posicao = ? WHERE usuario_id = ?");
        $stmt->execute([$posicao, $usuario_id]);
        $posicao++;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estatísticas - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Estilos específicos para o dropdown de notificações */
        .notification-dropdown {
            display: none;
            position: absolute;
            top: 100%;
            right: 0;
            width: 300px;
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 1000;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-dropdown.active {
            display: block;
        }
        
        .notifications-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ff3860;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .notification-icon {
            position: relative;
            cursor: pointer;
        }
        
        /* Estilos para as conquistas */
        .achievement img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            max-width: 100%;
            border-radius: 50%;
            background-color: #f5f5f5;
            padding: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .achievement {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        
        .achievement:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .achievement-info {
            margin-left: 15px;
            flex: 1;
        }
        
        .achievement-info h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #333;
        }
        
        .achievement-info p {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #666;
        }
        
        .achievement-points {
            display: inline-block;
            padding: 3px 8px;
            background-color: #ffcb05;
            color: #333;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .achievements-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        /* Novo espaçamento entre seções */
        .achievements {
            margin-bottom: 40px; /* Espaço abaixo da seção de conquistas */
            padding-bottom: 30px; /* Padding interno adicional */
            border-bottom: 1px solid #eaeaea; /* Linha separadora opcional */
        }
        
        .global-ranking {
            margin-top: 40px; /* Espaço acima da seção de ranking */
            padding-top: 20px; /* Padding interno adicional */
        }
        
        /* Espaçamento geral entre todas as seções */
        .stats-container > div {
            margin-bottom: 30px;
        }
        
        /* Estilo para títulos de seção */
        .statistics-page h2 {
            margin-top: 30px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ffcb05;
            color: #333;
            font-size: 22px;
        }
        
        @media (max-width: 768px) {
            .achievements-grid {
                grid-template-columns: 1fr;
            }
            
            /* Ajuste de espaçamento para dispositivos móveis */
            .achievements {
                margin-bottom: 30px;
                padding-bottom: 20px;
            }
            
            .global-ranking {
                margin-top: 30px;
            }
        }
    </style>
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="statistics-page">
        <div class="container">
            <h1>Suas Estatísticas</h1>
            
            <div class="stats-container">
                <div class="player-stats">
                    <h2>Estatísticas de Batalha</h2>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $estatisticas['vitorias']; ?></span>
                            <span class="stat-label">Vitórias</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $estatisticas['derrotas']; ?></span>
                            <span class="stat-label">Derrotas</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $estatisticas['empates']; ?></span>
                            <span class="stat-label">Empates</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $taxa_vitorias; ?>%</span>
                            <span class="stat-label">Taxa de Vitórias</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $estatisticas['cartas_ganhas']; ?></span>
                            <span class="stat-label">Cartas Ganhas</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-value"><?php echo $posicao_ranking; ?></span>
                            <span class="stat-label">Ranking Global</span>
                        </div>
                    </div>
                </div>
                
                <div class="collection-stats">
                    <h2>Progresso da Coleção</h2>
                    <div class="progress-bar">
                        <div class="progress" style="width: <?php echo $progresso_colecao; ?>%"></div>
                    </div>
                    <p><?php echo $total_cartas; ?> de <?php echo $total_cartas_jogo; ?> cartas (<?php echo $progresso_colecao; ?>%)</p>
                </div>
                
                <div class="achievements">
                    <h2>Suas Conquistas</h2>
                    <?php if (empty($conquistas_usuario)): ?>
                        <p class="no-achievements">Você ainda não desbloqueou nenhuma conquista.</p>
                    <?php else: ?>
                        <div class="achievements-grid">
                            <?php foreach ($conquistas_usuario as $conquista): ?>
                                <div class="achievement">
                                    <img src="img/badges/<?php echo htmlspecialchars($conquista['icone']); ?>" alt="<?php echo htmlspecialchars($conquista['nome']); ?>">
                                    <div class="achievement-info">
                                        <h3><?php echo htmlspecialchars($conquista['nome']); ?></h3>
                                        <p><?php echo htmlspecialchars($conquista['descricao']); ?></p>
                                        <span class="achievement-points"><?php echo $conquista['pontos']; ?> pontos</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="global-ranking">
                <h2>Ranking Global</h2>
                <table class="ranking-table">
                    <thead>
                        <tr>
                            <th>Posição</th>
                            <th>Jogador</th>
                            <th>Pontos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $usuario_atual_id = $_SESSION['usuario_id'];
                        foreach ($ranking as $posicao): 
                            $is_current_user = ($posicao['usuario_id'] == $usuario_atual_id);
                            $position_class = '';
                            if ($posicao['posicao'] == 1) $position_class = 'top-1';
                            else if ($posicao['posicao'] == 2) $position_class = 'top-2';
                            else if ($posicao['posicao'] == 3) $position_class = 'top-3';
                        ?>
                            <tr class="<?php echo $is_current_user ? 'current-user' : ''; ?>">
                                <td class="ranking-position <?php echo $position_class; ?>"><?php echo $posicao['posicao']; ?></td>
                                <td><?php echo htmlspecialchars($posicao['nome']); ?></td>
                                <td class="ranking-points"><?php echo number_format($posicao['pontuacao'], 0, ',', '.'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($ranking)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">Nenhum jogador no ranking ainda.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <?php include 'componentes/footer.php'; ?>

    <script>
        // Script específico para o dropdown de notificações
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM carregado, inicializando script de notificações");
            
            const notificationIcon = document.querySelector('.notification-icon');
            const notificationDropdown = document.querySelector('.notification-dropdown');
            
            if (notificationIcon && notificationDropdown) {
                console.log("Elementos de notificação encontrados");
                
                notificationIcon.addEventListener('click', function(e) {
                    console.log("Ícone de notificação clicado");
                    e.preventDefault();
                    notificationDropdown.classList.toggle('active');
                    console.log("Estado do dropdown:", notificationDropdown.classList.contains('active') ? "ativo" : "inativo");
                });
                
                // Fechar dropdown quando clicar fora
                document.addEventListener('click', function(e) {
                    if (notificationDropdown.classList.contains('active') && 
                        !notificationIcon.contains(e.target) && 
                        !notificationDropdown.contains(e.target)) {
                        console.log("Clique fora do dropdown detectado, fechando dropdown");
                        notificationDropdown.classList.remove('active');
                    }
                });
            } else {
                console.log("Elementos de notificação não encontrados:", 
                            "notificationIcon:", notificationIcon, 
                            "notificationDropdown:", notificationDropdown);
            }
        });
    </script>

    <?php include 'componentes/notificacoes_fix.php'; ?>
</body>
</html>
