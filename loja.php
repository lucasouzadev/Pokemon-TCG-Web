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

// Obter saldo de moedas
$saldo = obterSaldoMoedas($usuario_id);

// Processar compra de pacote
if (isset($_POST['comprar_pacote'])) {
    $pacote_id = $_POST['pacote_id'];
    
    // Buscar informações do pacote
    $stmt = $pdo->prepare("SELECT * FROM pacotes WHERE id = ?");
    $stmt->execute([$pacote_id]);
    $pacote = $stmt->fetch();
    
    if (!$pacote) {
        $mensagem = "Pacote não encontrado.";
        $tipo_mensagem = 'erro';
    } else {
        $tipo_moeda = $pacote['tipo_moeda'];
        $preco = $pacote['preco'];
        
        // Verificar se o usuário tem moedas suficientes
        if (($tipo_moeda == 'comum' && $saldo['moedas_comuns'] >= $preco) || 
            ($tipo_moeda == 'premium' && $saldo['moedas_premium'] >= $preco)) {
            
            // Remover moedas
            if (removerMoedas($usuario_id, $tipo_moeda, $preco, "Compra de pacote: " . $pacote['nome'])) {
                // Gerar cartas aleatórias baseadas no pacote
                $cartas = gerarCartasPacote($pacote);
                
                // Adicionar cartas à coleção do usuário
                foreach ($cartas as $carta) {
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
                            VALUES (?, ?, 1, 'pacote', NOW())
                        ");
                        $stmt->execute([$usuario_id, $carta['id']]);
                    }
                }
                
                // Atualizar saldo após a compra
                $saldo = obterSaldoMoedas($usuario_id);
                
                $mensagem = "Pacote aberto com sucesso! Você recebeu " . count($cartas) . " cartas.";
                $tipo_mensagem = 'sucesso';
                
                // Salvar as cartas na sessão para exibição
                $_SESSION['cartas_abertas'] = $cartas;
                $_SESSION['pacote_aberto'] = $pacote;
                
                // Redirecionar para a página de exibição das cartas
                header('Location: abrir_pacote.php');
                exit;
            } else {
                $mensagem = "Erro ao processar a compra.";
                $tipo_mensagem = 'erro';
            }
        } else {
            $mensagem = "Você não tem moedas suficientes para comprar este pacote.";
            $tipo_mensagem = 'erro';
        }
    }
}

// Função para gerar cartas aleatórias baseadas no pacote
function gerarCartasPacote($pacote) {
    global $pdo;
    
    $cartas = [];
    $quantidade_cartas = $pacote['quantidade_cartas'];
    
    // Distribuição de raridades baseada no tipo de pacote
    $distribuicao = [];
    
    if ($pacote['tipo_moeda'] == 'comum') {
        // Pacote comum
        $distribuicao = [
            'Comum' => 70,
            'Incomum' => 25,
            'Rara' => 5,
            'Ultra Rara' => 0
        ];
    } elseif ($pacote['tipo_moeda'] == 'premium') {
        // Pacote premium
        $distribuicao = [
            'Comum' => 40,
            'Incomum' => 35,
            'Rara' => 20,
            'Ultra Rara' => 5
        ];
    }
    
    // Garantir pelo menos uma carta rara em pacotes premium
    $garantir_rara = ($pacote['tipo_moeda'] == 'premium');
    
    // Gerar cartas aleatórias
    for ($i = 0; $i < $quantidade_cartas; $i++) {
        // Se for a última carta e precisamos garantir uma rara
        if ($garantir_rara && $i == $quantidade_cartas - 1 && !in_array('Rara', array_column($cartas, 'raridade')) && !in_array('Ultra Rara', array_column($cartas, 'raridade'))) {
            // Forçar uma carta rara ou ultra rara
            $raridade = (rand(1, 100) <= 80) ? 'Rara' : 'Ultra Rara';
        } else {
            // Determinar raridade baseada na distribuição
            $rand = rand(1, 100);
            $acumulado = 0;
            $raridade = 'Comum'; // Padrão
            
            foreach ($distribuicao as $r => $chance) {
                $acumulado += $chance;
                if ($rand <= $acumulado) {
                    $raridade = $r;
                    break;
                }
            }
        }
        
        // Buscar uma carta aleatória da raridade selecionada
        $stmt = $pdo->prepare("SELECT * FROM cartas WHERE raridade = ? ORDER BY RAND() LIMIT 1");
        $stmt->execute([$raridade]);
        $carta = $stmt->fetch();
        
        if ($carta) {
            $cartas[] = $carta;
        } else {
            // Tentar buscar qualquer carta como fallback
            $stmt = $pdo->prepare("SELECT * FROM cartas ORDER BY RAND() LIMIT 1");
            $stmt->execute();
            $carta = $stmt->fetch();
            
            if ($carta) {
                $cartas[] = $carta;
            }
        }
    }
    
    return $cartas;
}

// Buscar pacotes disponíveis
$stmt = $pdo->query("SELECT * FROM pacotes ORDER BY tipo_moeda, preco");
$pacotes = $stmt->fetchAll();

// Separar pacotes por tipo
$pacotes_comuns = array_filter($pacotes, function($p) { return $p['tipo_moeda'] == 'comum'; });
$pacotes_premium = array_filter($pacotes, function($p) { return $p['tipo_moeda'] == 'premium'; });
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loja - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/loja.css">
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="store">
        <div class="container">
            <h1>Loja de Pacotes</h1>
            
            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <p><?php echo $mensagem; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="user-balance">
                <div class="balance-item">
                    <img width="32" height="32" src="https://img.icons8.com/windows/32/cheap-2--v1.png" alt="Moedas Comuns" class="coin-icon">
                    <span><?php echo $saldo['moedas_comuns']; ?></span>
                </div>
                <div class="balance-item premium">
                    <img width="32" height="32" src="./img/icons/premium-coin.png" alt="Moedas Premium" class="coin-icon"> 
                    <span><?php echo $saldo['moedas_premium']; ?></span>
                </div>
            </div>
            
            <div class="store-tabs">
                <div class="tabs-nav">
                    <button class="tab-btn active" data-tab="common">Pacotes Comuns</button>
                    <button class="tab-btn" data-tab="premium">Pacotes Premium</button>
                    <button class="tab-btn" data-tab="special">Eventos Especiais</button>
                </div>
                
                <div class="tab-content active" id="common-tab">
                    <div class="packages-grid">
                        <?php 
                        // Definir os nomes dos pacotes que queremos exibir, na ordem desejada
                        $pacotes_desejados = ['Pacote Básico', 'Pacote Avançado', 'Pacote Elemento Fogo', 'Pacote Elemento Água'];
                        
                        // Filtrar e ordenar os pacotes conforme a ordem desejada
                        $pacotes_ordenados = [];
                        
                        // Primeiro, encontrar os pacotes específicos na ordem desejada
                        foreach ($pacotes_desejados as $nome_pacote) {
                            foreach ($pacotes_comuns as $pacote) {
                                if ($pacote['nome'] == $nome_pacote) {
                                    $pacotes_ordenados[] = $pacote;
                                    break;
                                }
                            }
                        }
                        
                        // Se não encontrar todos os pacotes específicos, adicionar os restantes
                        if (count($pacotes_ordenados) < count($pacotes_desejados)) {
                            foreach ($pacotes_comuns as $pacote) {
                                if (!in_array($pacote, $pacotes_ordenados)) {
                                    $pacotes_ordenados[] = $pacote;
                                }
                            }
                        }
                        
                        // Exibir os pacotes na ordem definida
                        foreach ($pacotes_ordenados as $pacote): 
                        ?>
                            <div class="package-card">
                                <img src="img/packages/<?php echo htmlspecialchars($pacote['imagem']); ?>" alt="<?php echo htmlspecialchars($pacote['nome']); ?>">
                                <div class="package-price">
                                    <img src="img/icons/coin.png" alt="Moedas" class="coin-icon" width="16" height="16">
                                    <span><?php echo $pacote['preco']; ?></span>
                                </div>
                                <div class="package-info">
                                    <h3><?php echo htmlspecialchars($pacote['nome']); ?></h3>
                                    <p><?php echo htmlspecialchars($pacote['descricao']); ?></p>
                                    <p class="package-details">Contém <?php echo $pacote['quantidade_cartas']; ?> cartas</p>
                                    <form method="post">
                                        <input type="hidden" name="pacote_id" value="<?php echo $pacote['id']; ?>">
                                        <button type="submit" name="comprar_pacote" class="btn-primary">Comprar Pacote</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="tab-content" id="premium-tab">
                    <div class="packages-grid">
                        <?php foreach ($pacotes_premium as $pacote): ?>
                            <div class="package-card premium">
                                <img src="img/packages/<?php echo htmlspecialchars($pacote['imagem']); ?>" alt="<?php echo htmlspecialchars($pacote['nome']); ?>">
                                <div class="package-price premium">
                                    <img src="img/icons/premium-coin.png" alt="Moedas Premium" class="coin-icon">
                                    <span><?php echo $pacote['preco']; ?></span>
                                </div>
                                <div class="package-info">
                                    <h3><?php echo htmlspecialchars($pacote['nome']); ?></h3>
                                    <p><?php echo htmlspecialchars($pacote['descricao']); ?></p>
                                    <p class="package-details">Contém <?php echo $pacote['quantidade_cartas']; ?> cartas</p>
                                    <form method="post">
                                        <input type="hidden" name="pacote_id" value="<?php echo $pacote['id']; ?>">
                                        <button type="submit" name="comprar_pacote" class="btn-premium">Comprar Pacote</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="tab-content" id="special-tab">
                    <div class="special-events">
                        <div class="event-card">
                            <div class="event-banner">
                                <img src="img/events/evento-especial.jpg" alt="Evento Especial">
                            </div>
                            <div class="event-info">
                                <h3>Evento Especial: Lendários de Kanto</h3>
                                <p class="event-date">Disponível de 15/07 a 30/07</p>
                                <p>Pacotes exclusivos com chances aumentadas de obter Pokémon lendários da região de Kanto!</p>
                                <p class="event-details">Limite de 3 pacotes por usuário</p>
                                <div class="event-cta">
                                    <button class="btn-special" disabled>Em breve</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="store-info">
                <h3>Como obter moedas?</h3>
                <ul>
                    <li><strong>Moedas Comuns:</strong> Ganhe participando de batalhas, completando conquistas diárias e subindo de nível.</li>
                    <li><strong>Moedas Premium:</strong> Ganhe ao atingir posições altas no ranking, participar de eventos especiais e completar coleções.</li>
                </ul>
            </div>
        </div>
    </section>
    <script src="js/main.js"></script>
    <script src="js/loja.js"></script>
</body>
</html>
