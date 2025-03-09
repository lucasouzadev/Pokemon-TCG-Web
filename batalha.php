<?php
session_start();
require_once 'config.php';
require_once 'notificacoes.php';
require_once 'economia.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

$mensagem = '';
$tipo_mensagem = '';
$resultado_batalha = null;

// Buscar cartas do usuário
$stmt = $pdo->prepare("
    SELECT c.*, MIN(co.id) as colecao_id, SUM(co.quantidade) as quantidade, 
           MIN(co.origem) as origem, MIN(co.data_obtencao) as data_obtencao
    FROM colecao co 
    JOIN cartas c ON co.carta_id = c.id 
    WHERE co.usuario_id = ? AND co.quantidade > 0
    GROUP BY c.id
    ORDER BY c.nome
");
$stmt->execute([$_SESSION['usuario_id']]);
$minhas_cartas = $stmt->fetchAll();

// Processar batalha
if (isset($_POST['iniciar_batalha'])) {
    $carta_id = $_POST['carta_id'];
    
    // Verificar se o usuário possui a carta
    $stmt = $pdo->prepare("SELECT * FROM colecao WHERE usuario_id = ? AND carta_id = ? AND quantidade > 0");
    $stmt->execute([$_SESSION['usuario_id'], $carta_id]);
    $possui_carta = $stmt->fetch();
    
    if (!$possui_carta) {
        $mensagem = "Você não possui esta carta para batalhar.";
        $tipo_mensagem = 'erro';
    } else {
        // Buscar informações da carta do usuário
        $stmt = $pdo->prepare("SELECT * FROM cartas WHERE id = ?");
        $stmt->execute([$carta_id]);
        $minha_carta = $stmt->fetch();
        
        // Selecionar uma carta aleatória para o oponente
        $stmt = $pdo->query("SELECT * FROM cartas ORDER BY RAND() LIMIT 1");
        $carta_oponente = $stmt->fetch();
        
        // Calcular pontuação baseada em HP e tipo
        $pontuacao_minha = $minha_carta['hp'];
        $pontuacao_oponente = $carta_oponente['hp'];
        
        // Adicionar bônus baseado em tipo (sistema simples de vantagens)
        $vantagens = [
            'Fogo' => 'Planta',
            'Água' => 'Fogo',
            'Planta' => 'Água',
            'Elétrico' => 'Água',
            'Psíquico' => 'Lutador',
            'Lutador' => 'Normal',
            'Terra' => 'Elétrico',
            'Voador' => 'Lutador',
            'Gelo' => 'Planta',
            'Fantasma' => 'Psíquico',
            'Dragão' => 'Dragão'
        ];
        
        // Verificar vantagens de tipo
        if (isset($vantagens[$minha_carta['tipo']]) && $vantagens[$minha_carta['tipo']] === $carta_oponente['tipo']) {
            $pontuacao_minha += 20; // Bônus por vantagem de tipo
        }
        
        if (isset($vantagens[$carta_oponente['tipo']]) && $vantagens[$carta_oponente['tipo']] === $minha_carta['tipo']) {
            $pontuacao_oponente += 20; // Bônus por vantagem de tipo
        }
        
        // Adicionar elemento aleatório para tornar as batalhas mais interessantes
        $pontuacao_minha += rand(1, 10);
        $pontuacao_oponente += rand(1, 10);
        
        // Determinar o vencedor
        if ($pontuacao_minha > $pontuacao_oponente) {
            $resultado = 'vitoria';
            $mensagem = "Você venceu a batalha!";
            
            // Atualizar estatísticas - vitória
            atualizarEstatisticasBatalha($_SESSION['usuario_id'], 'vitoria');
            
            // Verificar conquistas
            verificarConquistasBatalha($_SESSION['usuario_id']);
            
            // Enviar notificação sobre o resultado da batalha
            adicionarNotificacao($_SESSION['usuario_id'], 'batalha', "Você venceu uma batalha contra {$carta_oponente['nome']}!");
            
            // Gerar recompensa para o jogador
            $recompensa = gerarRecompensaBatalha($_SESSION['usuario_id'], 'vitoria');
            
            if ($recompensa) {
                if ($recompensa['tipo'] == 'carta') {
                    $carta_ganha = $recompensa['carta'];
                    $mensagem .= " Como recompensa, você ganhou uma carta: " . $carta_ganha['nome'] . "!";
                    
                    // Atualizar estatísticas - carta ganha
                    atualizarEstatisticasCartasGanhas($_SESSION['usuario_id']);
                } elseif ($recompensa['tipo'] == 'moedas') {
                    $mensagem .= " Como recompensa, você ganhou " . $recompensa['moedas_comuns'] . " moedas comuns";
                    
                    if (isset($recompensa['moedas_premium'])) {
                        $mensagem .= " e " . $recompensa['moedas_premium'] . " moedas premium";
                    }
                    
                    $mensagem .= "!";
                } else {
                    $mensagem .= " Infelizmente, você não ganhou nenhuma recompensa desta vez.";
                }
            }
            
            // Adicionar carta ao vencedor
            $stmt = $pdo->prepare("
                INSERT INTO colecao (usuario_id, carta_id, quantidade, origem, data_obtencao) 
                VALUES (?, ?, 1, 'batalha', NOW())
            ");
            $stmt->execute([$_SESSION['usuario_id'], $carta_ganha['id']]);
            
        } elseif ($pontuacao_minha < $pontuacao_oponente) {
            $resultado = 'derrota';
            $mensagem = "Você perdeu a batalha.";
            
            // Atualizar estatísticas - derrota
            atualizarEstatisticasBatalha($_SESSION['usuario_id'], 'derrota');
            
            // Enviar notificação sobre o resultado da batalha
            adicionarNotificacao($_SESSION['usuario_id'], 'batalha', "Você perdeu uma batalha contra {$carta_oponente['nome']}. Tente novamente!");
            
            // Penalizar o jogador com perda de pontos no ranking
            $stmt = $pdo->prepare("SELECT pontuacao FROM ranking WHERE usuario_id = ?");
            $stmt->execute([$_SESSION['usuario_id']]);
            $ranking = $stmt->fetch();
            
            if ($ranking && $ranking['pontuacao'] > 0) {
                // Perder entre 3 e 9 pontos de ranking
                $pontos_perdidos = mt_rand(3, 9);
                $nova_pontuacao = max(0, $ranking['pontuacao'] - $pontos_perdidos);
                
                $stmt = $pdo->prepare("UPDATE ranking SET pontuacao = ?, ultima_atualizacao = NOW() WHERE usuario_id = ?");
                $stmt->execute([$nova_pontuacao, $_SESSION['usuario_id']]);
                
                // Atualizar posições no ranking
                atualizarPosicoesRanking();
                
                $mensagem .= " Você perdeu {$pontos_perdidos} pontos de ranking.";
                
                adicionarNotificacao(
                    $_SESSION['usuario_id'], 
                    'batalha', 
                    "Você perdeu {$pontos_perdidos} pontos de ranking devido à derrota na batalha."
                );
            }
            
            // Dar uma pequena recompensa de consolação (10% de chance)
            if (mt_rand(1, 100) <= 10) {
                $moedas_consolacao = mt_rand(2, 5);
                adicionarMoedas($_SESSION['usuario_id'], 'comum', $moedas_consolacao, 'Consolação por derrota em batalha');
                
                $mensagem .= " Como consolação, você ganhou {$moedas_consolacao} moedas comuns.";
                
                adicionarNotificacao(
                    $_SESSION['usuario_id'], 
                    'batalha', 
                    "Você recebeu {$moedas_consolacao} moedas comuns como consolação pela derrota."
                );
            }
            
        } else {
            $resultado = 'empate';
            $mensagem = "A batalha terminou em empate.";
            
            // Atualizar estatísticas - empate
            atualizarEstatisticasBatalha($_SESSION['usuario_id'], 'empate');
            
            // Enviar notificação sobre o resultado da batalha
            adicionarNotificacao($_SESSION['usuario_id'], 'batalha', "Sua batalha contra {$carta_oponente['nome']} terminou em empate!");
            
            // Gerar recompensa para o jogador
            $recompensa = gerarRecompensaBatalha($_SESSION['usuario_id'], 'empate');
            
            if ($recompensa) {
                if ($recompensa['tipo'] == 'carta') {
                    $carta_ganha = $recompensa['carta'];
                    $mensagem .= " Como recompensa, você ganhou uma carta: " . $carta_ganha['nome'] . "!";
                    
                    // Atualizar estatísticas - carta ganha
                    atualizarEstatisticasCartasGanhas($_SESSION['usuario_id']);
                } elseif ($recompensa['tipo'] == 'moedas') {
                    $mensagem .= " Como recompensa, você ganhou " . $recompensa['moedas_comuns'] . " moedas comuns!";
                } else {
                    $mensagem .= " Infelizmente, você não ganhou nenhuma recompensa desta vez.";
                }
            }
        }
        
        $tipo_mensagem = 'info';
        
        // Armazenar resultado da batalha para exibição
        $resultado_batalha = [
            'minha_carta' => $minha_carta,
            'carta_oponente' => $carta_oponente,
            'pontuacao_minha' => $pontuacao_minha,
            'pontuacao_oponente' => $pontuacao_oponente,
            'resultado' => $resultado,
            'carta_ganha' => $carta_ganha ?? null,
            'recompensa' => $recompensa ?? null
        ];
    }
}

// Função para atualizar estatísticas de batalha
function atualizarEstatisticasBatalha($usuario_id, $resultado) {
    global $pdo;
    
    // Verificar se o usuário já tem estatísticas
    $stmt = $pdo->prepare("SELECT * FROM estatisticas_batalha WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $estatisticas = $stmt->fetch();
    
    if ($estatisticas) {
        // Atualizar estatísticas existentes
        $campo = $resultado . 's'; // vitorias, derrotas ou empates
        $stmt = $pdo->prepare("UPDATE estatisticas_batalha SET $campo = $campo + 1, ultima_batalha = NOW() WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
    } else {
        // Criar novas estatísticas
        $vitorias = $resultado === 'vitoria' ? 1 : 0;
        $derrotas = $resultado === 'derrota' ? 1 : 0;
        $empates = $resultado === 'empate' ? 1 : 0;
        
        $stmt = $pdo->prepare("INSERT INTO estatisticas_batalha (usuario_id, vitorias, derrotas, empates, ultima_batalha) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$usuario_id, $vitorias, $derrotas, $empates]);
    }
    
    // Atualizar ranking
    atualizarRanking($usuario_id);
}

// Função para atualizar estatísticas de cartas ganhas
function atualizarEstatisticasCartasGanhas($usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE estatisticas_batalha SET cartas_ganhas = cartas_ganhas + 1 WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
}

// Função para atualizar ranking
function atualizarRanking($usuario_id) {
    global $pdo;
    
    // Buscar estatísticas de batalha
    $stmt = $pdo->prepare("SELECT * FROM estatisticas_batalha WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $estatisticas = $stmt->fetch();
    
    if ($estatisticas) {
        // Calcular pontuação (vitorias * 3 + empates - derrotas)
        $pontuacao = ($estatisticas['vitorias'] * 3) + $estatisticas['empates'] - $estatisticas['derrotas'];
        if ($pontuacao < 0) $pontuacao = 0;
        
        // Calcular nível (1 + vitorias / 10)
        $nivel = 1 + floor($estatisticas['vitorias'] / 10);
        
        // Verificar se o usuário já tem ranking
        $stmt = $pdo->prepare("SELECT * FROM ranking WHERE usuario_id = ?");
        $stmt->execute([$usuario_id]);
        $ranking = $stmt->fetch();
        
        if ($ranking) {
            // Atualizar ranking existente
            $stmt = $pdo->prepare("UPDATE ranking SET pontuacao = ?, nivel = ?, ultima_atualizacao = NOW() WHERE usuario_id = ?");
            $stmt->execute([$pontuacao, $nivel, $usuario_id]);
        } else {
            // Criar novo ranking
            $stmt = $pdo->prepare("INSERT INTO ranking (usuario_id, pontuacao, nivel, ultima_atualizacao) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$usuario_id, $pontuacao, $nivel]);
        }
        
        // Atualizar posições no ranking
        atualizarPosicoesRanking();
    }
}

// Função para atualizar posições no ranking
function atualizarPosicoesRanking() {
    global $pdo;
    
    try {
        // Iniciar transação para garantir consistência
        $pdo->beginTransaction();
        
        // Buscar todos os rankings ordenados por pontuação (decrescente) e, em caso de empate, por última atualização (mais recente primeiro)
        $stmt = $pdo->query("
            SELECT id, usuario_id, pontuacao 
            FROM ranking 
            ORDER BY pontuacao DESC, ultima_atualizacao DESC
        ");
        $rankings = $stmt->fetchAll();
        
        // Atualizar posição de cada usuário
        $posicao = 1;
        foreach ($rankings as $ranking) {
            $stmt = $pdo->prepare("UPDATE ranking SET posicao = ? WHERE id = ?");
            $stmt->execute([$posicao, $ranking['id']]);
            $posicao++;
        }
        
        // Confirmar transação
        $pdo->commit();
        
        return true;
    } catch (Exception $e) {
        // Reverter em caso de erro
        $pdo->rollBack();
        error_log("Erro ao atualizar posições no ranking: " . $e->getMessage());
        return false;
    }
}

// Função para verificar conquistas de batalha
function verificarConquistasBatalha($usuario_id) {
    global $pdo;
    
    // Buscar estatísticas de batalha
    $stmt = $pdo->prepare("SELECT * FROM estatisticas_batalha WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $estatisticas = $stmt->fetch();
    
    if ($estatisticas) {
        // Verificar conquista "Primeiro Sangue"
        if ($estatisticas['vitorias'] == 1) {
            adicionarConquista($usuario_id, 'Primeiro Sangue');
        }
        
        // Verificar conquista "Guerreiro"
        if ($estatisticas['vitorias'] == 10) {
            adicionarConquista($usuario_id, 'Guerreiro');
        }
        
        // Verificar conquista "Campeão"
        if ($estatisticas['vitorias'] == 50) {
            adicionarConquista($usuario_id, 'Campeão');
        }
    }
}

// Função para adicionar conquista ao usuário
function adicionarConquista($usuario_id, $nome_conquista) {
    global $pdo;
    
    // Buscar ID da conquista
    $stmt = $pdo->prepare("SELECT id, pontos FROM conquistas WHERE nome = ?");
    $stmt->execute([$nome_conquista]);
    $conquista = $stmt->fetch();
    
    if ($conquista) {
        // Verificar se o usuário já tem esta conquista
        $stmt = $pdo->prepare("SELECT * FROM usuario_conquistas WHERE usuario_id = ? AND conquista_id = ?");
        $stmt->execute([$usuario_id, $conquista['id']]);
        $ja_tem = $stmt->fetch();
        
        if (!$ja_tem) {
            // Adicionar conquista ao usuário
            $stmt = $pdo->prepare("INSERT INTO usuario_conquistas (usuario_id, conquista_id) VALUES (?, ?)");
            $stmt->execute([$usuario_id, $conquista['id']]);
            
            // Adicionar pontos ao ranking
            $stmt = $pdo->prepare("UPDATE ranking SET pontuacao = pontuacao + ? WHERE usuario_id = ?");
            $stmt->execute([$conquista['pontos'], $usuario_id]);
            
            // Atualizar posições no ranking
            atualizarPosicoesRanking();
            
            return true;
        }
    }
    
    return false;
}

// Adicionar esta função ao arquivo batalha.php
function gerarRecompensaBatalha($usuario_id, $resultado) {
    global $pdo;
    
    // Se for derrota, não ganha recompensa
    if ($resultado == 'derrota') {
        return null;
    }
    
    // Definir probabilidades de recompensa
    $chance = mt_rand(1, 100);
    
    // Vitória: 40% chance de carta, 40% chance de moedas, 20% chance de nada
    // Empate: 20% chance de carta, 30% chance de moedas, 50% chance de nada
    if ($resultado == 'vitoria') {
        if ($chance <= 40) {
            // Recompensa: Carta
            return darCartaComoRecompensa($usuario_id, $resultado);
        } elseif ($chance <= 80) {
            // Recompensa: Moedas
            $moedas_comuns = mt_rand(5, 15);
            adicionarMoedas($usuario_id, 'comum', $moedas_comuns, 'Recompensa por vitória em batalha');
            
            // 5% de chance de ganhar moedas premium
            if (mt_rand(1, 100) <= 5) {
                $moedas_premium = mt_rand(1, 3);
                adicionarMoedas($usuario_id, 'premium', $moedas_premium, 'Recompensa especial por vitória em batalha');
                
                adicionarNotificacao(
                    $usuario_id, 
                    'batalha', 
                    "Você ganhou {$moedas_comuns} moedas comuns e {$moedas_premium} moedas premium como recompensa por sua vitória!"
                );
                
                return [
                    'tipo' => 'moedas',
                    'moedas_comuns' => $moedas_comuns,
                    'moedas_premium' => $moedas_premium
                ];
            } else {
                adicionarNotificacao(
                    $usuario_id, 
                    'batalha', 
                    "Você ganhou {$moedas_comuns} moedas comuns como recompensa por sua vitória!"
                );
                
                return [
                    'tipo' => 'moedas',
                    'moedas_comuns' => $moedas_comuns
                ];
            }
        } else {
            // Sem recompensa
            adicionarNotificacao(
                $usuario_id, 
                'batalha', 
                "Você não recebeu nenhuma recompensa desta vez. Tente novamente!"
            );
            
            return [
                'tipo' => 'nada'
            ];
        }
    } elseif ($resultado == 'empate') {
        if ($chance <= 20) {
            // Recompensa: Carta
            return darCartaComoRecompensa($usuario_id, $resultado);
        } elseif ($chance <= 50) {
            // Recompensa: Moedas
            $moedas_comuns = mt_rand(3, 10);
            adicionarMoedas($usuario_id, 'comum', $moedas_comuns, 'Recompensa por empate em batalha');
            
            // 2% de chance de ganhar moedas premium
            if (mt_rand(1, 100) <= 2) {
                $moedas_premium = mt_rand(1, 2);
                adicionarMoedas($usuario_id, 'premium', $moedas_premium, 'Recompensa especial por empate em batalha');
                
                adicionarNotificacao(
                    $usuario_id, 
                    'batalha', 
                    "Você ganhou {$moedas_comuns} moedas comuns e {$moedas_premium} moedas premium como recompensa pelo empate!"
                );
                
                return [
                    'tipo' => 'moedas',
                    'moedas_comuns' => $moedas_comuns,
                    'moedas_premium' => $moedas_premium
                ];
            } else {
                adicionarNotificacao(
                    $usuario_id, 
                    'batalha', 
                    "Você ganhou {$moedas_comuns} moedas comuns como recompensa pelo empate!"
                );
                
                return [
                    'tipo' => 'moedas',
                    'moedas_comuns' => $moedas_comuns
                ];
            }
        } else {
            // Sem recompensa
            adicionarNotificacao(
                $usuario_id, 
                'batalha', 
                "Você não recebeu nenhuma recompensa desta vez. Tente novamente!"
            );
            
            return [
                'tipo' => 'nada'
            ];
        }
    }
    
    return null;
}

// Função auxiliar para dar uma carta como recompensa
function darCartaComoRecompensa($usuario_id, $resultado) {
    global $pdo;
    
    // Definir distribuição de raridades com base no resultado
    $distribuicao = [];
    
    if ($resultado == 'vitoria') {
        // Distribuição para vitórias
        $distribuicao = [
            'Comum' => 70,
            'Incomum' => 25,
            'Rara' => 4,
            'Ultra Rara' => 1
        ];
    } else { // empate
        // Distribuição para empates
        $distribuicao = [
            'Comum' => 85,
            'Incomum' => 14,
            'Rara' => 1,
            'Ultra Rara' => 0
        ];
    }
    
    // Determinar raridade baseada na distribuição
    $rand = mt_rand(1, 100);
    $acumulado = 0;
    $raridade_selecionada = 'Comum'; // Padrão
    
    foreach ($distribuicao as $raridade => $chance) {
        $acumulado += $chance;
        if ($rand <= $acumulado) {
            $raridade_selecionada = $raridade;
            break;
        }
    }
    
    // Verificar se existem cartas da raridade selecionada
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cartas WHERE raridade = ?");
    $stmt->execute([$raridade_selecionada]);
    $count = $stmt->fetchColumn();
    
    // Se não houver cartas da raridade selecionada, usar Comum como fallback
    if ($count == 0) {
        $raridade_selecionada = 'Comum';
    }
    
    // Selecionar uma carta aleatória da raridade escolhida
    $stmt = $pdo->prepare("SELECT id FROM cartas WHERE raridade = ? ORDER BY RAND() LIMIT 1");
    $stmt->execute([$raridade_selecionada]);
    $carta = $stmt->fetch();
    
    if ($carta) {
        // Verificar se o usuário já possui esta carta
        $stmt = $pdo->prepare("SELECT id, quantidade FROM colecao WHERE usuario_id = ? AND carta_id = ?");
        $stmt->execute([$usuario_id, $carta['id']]);
        $carta_existente = $stmt->fetch();
        
        if ($carta_existente) {
            // Se a carta já existe, apenas incrementar a quantidade
            $stmt = $pdo->prepare("UPDATE colecao SET quantidade = quantidade + 1 WHERE id = ?");
            $stmt->execute([$carta_existente['id']]);
        } else {
            // Se a carta não existe, adicionar normalmente
            $stmt = $pdo->prepare("
                INSERT INTO colecao (usuario_id, carta_id, quantidade, origem, data_obtencao) 
                VALUES (?, ?, 1, 'batalha', NOW())
            ");
            $stmt->execute([$usuario_id, $carta['id']]);
        }
        
        // Buscar informações da carta para exibição
        $stmt = $pdo->prepare("SELECT * FROM cartas WHERE id = ?");
        $stmt->execute([$carta['id']]);
        $carta_info = $stmt->fetch();
        
        // Adicionar notificação sobre a recompensa
        $mensagem = "Você ganhou uma carta {$carta_info['nome']} ({$carta_info['raridade']}) como recompensa por sua " . 
            ($resultado == 'vitoria' ? 'vitória' : 'participação') . "!";
        
        adicionarNotificacao(
            $usuario_id, 
            'batalha', 
            $mensagem
        );
        
        return [
            'tipo' => 'carta',
            'carta' => $carta_info
        ];
    }
    
    return null;
}

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batalha - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="battle">
        <div class="container">
            <h1>Arena de Batalha</h1>
            
            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <p><?php echo $mensagem; ?></p>
                </div>
            <?php endif; ?>
            
            <?php if ($resultado_batalha): ?>
                <div class="battle-result">
                    <h2>Resultado da Batalha</h2>
                    
                    <div class="battle-cards">
                        <div class="battle-card <?php echo $resultado_batalha['resultado'] === 'vitoria' ? 'winner' : ''; ?>">
                            <h3>Sua Carta</h3>
                            <img src="img/cards/<?php echo htmlspecialchars($resultado_batalha['minha_carta']['imagem']); ?>" alt="<?php echo htmlspecialchars($resultado_batalha['minha_carta']['nome']); ?>">
                            <div class="battle-card-info">
                                <p class="card-name"><?php echo htmlspecialchars($resultado_batalha['minha_carta']['nome']); ?></p>
                                <p>Tipo: <?php echo htmlspecialchars($resultado_batalha['minha_carta']['tipo']); ?></p>
                                <p>HP: <?php echo htmlspecialchars($resultado_batalha['minha_carta']['hp']); ?></p>
                                <p class="battle-score">Pontuação: <?php echo $resultado_batalha['pontuacao_minha']; ?></p>
                            </div>
                        </div>
                        
                        <div class="battle-vs">
                            <img src="img/icons/versus.png" alt="VS">
                        </div>
                        
                        <div class="battle-card <?php echo $resultado_batalha['resultado'] === 'derrota' ? 'winner' : ''; ?>">
                            <h3>Carta Oponente</h3>
                            <img src="img/cards/<?php echo htmlspecialchars($resultado_batalha['carta_oponente']['imagem']); ?>" alt="<?php echo htmlspecialchars($resultado_batalha['carta_oponente']['nome']); ?>">
                            <div class="battle-card-info">
                                <p class="card-name"><?php echo htmlspecialchars($resultado_batalha['carta_oponente']['nome']); ?></p>
                                <p>Tipo: <?php echo htmlspecialchars($resultado_batalha['carta_oponente']['tipo']); ?></p>
                                <p>HP: <?php echo htmlspecialchars($resultado_batalha['carta_oponente']['hp']); ?></p>
                                <p class="battle-score">Pontuação: <?php echo $resultado_batalha['pontuacao_oponente']; ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="battle-outcome">
                        <h3 class="outcome-<?php echo $resultado_batalha['resultado']; ?>">
                            <?php 
                                switch($resultado_batalha['resultado']) {
                                    case 'vitoria':
                                        echo 'Você Venceu!';
                                        break;
                                    case 'derrota':
                                        echo 'Você Perdeu!';
                                        break;
                                    case 'empate':
                                        echo 'Empate!';
                                        break;
                                }
                            ?>
                        </h3>
                        
                        <?php if (isset($resultado_batalha['carta_ganha'])): ?>
                            <div class="battle-prize">
                                <h4>Carta Conquistada:</h4>
                                <div class="prize-card">
                                    <img src="img/cards/<?php echo htmlspecialchars($resultado_batalha['carta_ganha']['imagem']); ?>" alt="<?php echo htmlspecialchars($resultado_batalha['carta_ganha']['nome']); ?>">
                                    <p><?php echo htmlspecialchars($resultado_batalha['carta_ganha']['nome']); ?></p>
                                    <p class="card-rarity <?php echo strtolower(str_replace(' ', '-', $resultado_batalha['carta_ganha']['raridade'])); ?>">
                                        <?php echo htmlspecialchars($resultado_batalha['carta_ganha']['raridade']); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($resultado_batalha['recompensa']) && $resultado_batalha['recompensa']['tipo'] == 'moedas'): ?>
                            <div class="battle-prize">
                                <h4>Moedas Conquistadas:</h4>
                                <div class="prize-coins">
                                    <?php if (isset($resultado_batalha['recompensa']['moedas_comuns'])): ?>
                                        <div class="coin-reward">
                                            <img src="img/icons/coin.png" alt="Moedas Comuns" class="coin-icon" width="32" height="32">
                                            <p><?php echo $resultado_batalha['recompensa']['moedas_comuns']; ?> moedas comuns</p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($resultado_batalha['recompensa']['moedas_premium'])): ?>
                                        <div class="coin-reward">
                                            <img src="img/icons/premium-coin.png" alt="Moedas Premium" class="coin-icon" width="32" height="32">
                                            <p><?php echo $resultado_batalha['recompensa']['moedas_premium']; ?> moedas premium</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="battle-actions">
                        <a href="batalha.php" class="btn-primary">Nova Batalha</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="battle-start">
                    <h2>Escolha uma Carta para Batalhar</h2>
                    
                    <?php if (empty($minhas_cartas)): ?>
                        <p class="empty-message">Você não possui cartas para batalhar. Visite a <a href="loja.php">loja</a> para adquirir pacotes.</p>
                    <?php else: ?>
                        <div class="battle-selection">
                            <form method="post" class="battle-form">
                                <div class="form-group">
                                    <label for="carta_id">Selecione sua carta:</label>
                                    <select name="carta_id" id="carta_id" required>
                                        <option value="">Escolha uma carta</option>
                                        <?php foreach ($minhas_cartas as $carta): ?>
                                            <option value="<?php echo $carta['id']; ?>">
                                                <?php echo htmlspecialchars($carta['nome']); ?> (<?php echo $carta['tipo']; ?>, HP: <?php echo $carta['hp']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" name="iniciar_batalha" class="btn-primary">Iniciar Batalha</button>
                            </form>
                        </div>
                        
                        <div class="battle-rules">
                            <h3>Regras da Batalha</h3>
                            <ul>
                                <li>Cada carta tem uma pontuação base igual ao seu HP.</li>
                                <li>Cartas com vantagem de tipo recebem +20 pontos.</li>
                                <li>Um elemento aleatório (1-10 pontos) é adicionado para cada carta.</li>
                                <li>A carta com maior pontuação vence a batalha.</li>
                                <li>Ao vencer, você tem 30% de chance de ganhar uma carta aleatória.</li>
                            </ul>
                            
                            <h4>Vantagens de Tipo:</h4>
                            <ul class="type-advantages">
                                <li>Fogo > Planta</li>
                                <li>Água > Fogo</li>
                                <li>Planta > Água</li>
                                <li>Elétrico > Água</li>
                                <li>Psíquico > Lutador</li>
                                <li>Lutador > Normal</li>
                                <li>Terra > Elétrico</li>
                                <li>Voador > Lutador</li>
                                <li>Gelo > Planta</li>
                                <li>Fantasma > Psíquico</li>
                                <li>Dragão > Dragão</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
