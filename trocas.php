<?php
session_start();
require_once 'config.php';
require_once 'notificacoes.php';
require_once 'limite_trocas.php';
require_once 'contadores.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

$mensagem = '';
$tipo_mensagem = '';

// Verificar limite de trocas
$limite_trocas = verificarLimiteTrocas($usuario_id);
$pode_trocar = podeTrocar($usuario_id);

// Adicionar no início do arquivo, logo após as verificações iniciais
$mostrar_animacao = false;
$tipo_animacao = '';
$carta_animacao = null;
$mensagem_animacao = '';

// Processar solicitação de troca
if (isset($_POST['solicitar_troca'])) {
    $carta_oferecida_id = $_POST['carta_oferecida'];
    $carta_desejada_id = $_POST['carta_desejada'];
    $usuario_destino_id = $_POST['usuario_destino'];
    
    // Verificar se o usuário pode realizar trocas
    if (!$pode_trocar) {
        if ($limite_trocas['trocas_restantes'] <= 0) {
            $mensagem = "Você atingiu o limite de {$limite_trocas['limite_diario']} trocas diárias.";
        } else {
            // Obter informações sobre a troca pendente
            $troca_pendente = obterTrocaPendente($usuario_id);
            
            if ($troca_pendente) {
                if ($troca_pendente['tipo'] === 'enviada') {
                    $mensagem = "Processo de troca em andamento, aguardando a resposta de: {$troca_pendente['usuario_nome']}.";
                } else {
                    $mensagem = "Você tem uma solicitação de troca pendente de {$troca_pendente['usuario_nome']}. Responda-a antes de iniciar uma nova troca.";
                }
            } else {
                $mensagem = "Você já tem uma troca pendente. Aguarde a resposta ou cancele a troca atual para iniciar uma nova.";
            }
        }
        $tipo_mensagem = 'erro';
    } else {
        // Verificar se o usuário possui a carta oferecida
        $stmt = $pdo->prepare("SELECT * FROM colecao WHERE usuario_id = ? AND carta_id = ? AND quantidade > 0");
        $stmt->execute([$_SESSION['usuario_id'], $carta_oferecida_id]);
        $possui_carta = $stmt->fetch();
        
        if (!$possui_carta) {
            $mensagem = "Você não possui esta carta para oferecer.";
            $tipo_mensagem = 'erro';
        } else {
            // Verificar se o usuário de destino possui a carta desejada
            $stmt = $pdo->prepare("SELECT * FROM colecao WHERE usuario_id = ? AND carta_id = ? AND quantidade > 0");
            $stmt->execute([$usuario_destino_id, $carta_desejada_id]);
            $destino_possui_carta = $stmt->fetch();
            
            if (!$destino_possui_carta) {
                $mensagem = "O usuário selecionado não possui a carta desejada.";
                $tipo_mensagem = 'erro';
            } else {
                // Inserir solicitação de troca
                $stmt = $pdo->prepare("
                    INSERT INTO trocas (usuario_origem_id, usuario_destino_id, carta_oferecida_id, carta_desejada_id, status, data_solicitacao)
                    VALUES (?, ?, ?, ?, 'pendente', NOW())
                ");
                $stmt->execute([$_SESSION['usuario_id'], $usuario_destino_id, $carta_oferecida_id, $carta_desejada_id]);
                
                // Obter o ID da troca inserida
                $troca_id = $pdo->lastInsertId();
                
                // Incrementar contador de trocas realizadas
                incrementarTrocasRealizadas($usuario_id);
                
                // Enviar notificação sobre a nova solicitação de troca
                notificarTroca($troca_id);
                
                $mensagem = "Solicitação de troca enviada com sucesso!";
                $tipo_mensagem = 'sucesso';
                
                // Atualizar limite de trocas
                $limite_trocas = verificarLimiteTrocas($usuario_id);
                $pode_trocar = podeTrocar($usuario_id);
            }
        }
    }
}

// Processar resposta a solicitação de troca
if (isset($_POST['responder_troca'])) {
    $troca_id = $_POST['troca_id'];
    $resposta = $_POST['responder_troca'];
    
    // Buscar informações da troca
    $stmt = $pdo->prepare("SELECT * FROM trocas WHERE id = ? AND usuario_destino_id = ? AND status = 'pendente'");
    $stmt->execute([$troca_id, $_SESSION['usuario_id']]);
    $troca = $stmt->fetch();
    
    if (!$troca) {
        $mensagem = "Solicitação de troca não encontrada ou já processada.";
        $tipo_mensagem = 'erro';
    } else {
        if ($resposta === 'aceitar') {
            // Verificar se ambos ainda possuem as cartas
            $stmt = $pdo->prepare("SELECT * FROM colecao WHERE usuario_id = ? AND carta_id = ? AND quantidade > 0");
            $stmt->execute([$troca['usuario_origem_id'], $troca['carta_oferecida_id']]);
            $origem_tem_carta = $stmt->fetch();
            
            $stmt = $pdo->prepare("SELECT * FROM colecao WHERE usuario_id = ? AND carta_id = ? AND quantidade > 0");
            $stmt->execute([$_SESSION['usuario_id'], $troca['carta_desejada_id']]);
            $destino_tem_carta = $stmt->fetch();
            
            if (!$origem_tem_carta || !$destino_tem_carta) {
                $stmt = $pdo->prepare("UPDATE trocas SET status = 'cancelada', data_resposta = NOW() WHERE id = ?");
                $stmt->execute([$troca_id]);
                
                $mensagem = "A troca não pode ser realizada porque uma das cartas não está mais disponível.";
                $tipo_mensagem = 'erro';
            } else {
                // Iniciar transação
                $pdo->beginTransaction();
                
                try {
                    // Remover carta do usuário de origem
                    $stmt = $pdo->prepare("UPDATE colecao SET quantidade = quantidade - 1 WHERE usuario_id = ? AND carta_id = ?");
                    $stmt->execute([$troca['usuario_origem_id'], $troca['carta_oferecida_id']]);
                    
                    // Verificar se o usuário de destino já possui esta carta
                    $stmt = $pdo->prepare("SELECT id, quantidade FROM colecao WHERE usuario_id = ? AND carta_id = ?");
                    $stmt->execute([$_SESSION['usuario_id'], $troca['carta_oferecida_id']]);
                    $carta_existente = $stmt->fetch();
                    
                    if ($carta_existente) {
                        // Mesmo que o usuário já tenha esta carta, adicionamos uma nova entrada com origem 'troca'
                        $stmt = $pdo->prepare("
                            INSERT INTO colecao (usuario_id, carta_id, quantidade, origem, data_obtencao) 
                            VALUES (?, ?, 1, 'troca', NOW())
                        ");
                        $stmt->execute([$_SESSION['usuario_id'], $troca['carta_oferecida_id']]);
                    } else {
                        // Se a carta não existe, adicionar normalmente
                        $stmt = $pdo->prepare("
                            INSERT INTO colecao (usuario_id, carta_id, quantidade, origem, data_obtencao) 
                            VALUES (?, ?, 1, 'troca', NOW())
                        ");
                        $stmt->execute([$_SESSION['usuario_id'], $troca['carta_oferecida_id']]);
                    }
                    
                    // Remover carta do usuário de destino
                    $stmt = $pdo->prepare("UPDATE colecao SET quantidade = quantidade - 1 WHERE usuario_id = ? AND carta_id = ?");
                    $stmt->execute([$_SESSION['usuario_id'], $troca['carta_desejada_id']]);
                    
                    // Verificar se o usuário de origem já possui esta carta
                    $stmt = $pdo->prepare("SELECT id, quantidade FROM colecao WHERE usuario_id = ? AND carta_id = ?");
                    $stmt->execute([$troca['usuario_origem_id'], $troca['carta_desejada_id']]);
                    $carta_existente = $stmt->fetch();
                    
                    if ($carta_existente) {
                        // Mesmo que o usuário já tenha esta carta, adicionamos uma nova entrada com origem 'troca'
                        $stmt = $pdo->prepare("
                            INSERT INTO colecao (usuario_id, carta_id, quantidade, origem, data_obtencao) 
                            VALUES (?, ?, 1, 'troca', NOW())
                        ");
                        $stmt->execute([$troca['usuario_origem_id'], $troca['carta_desejada_id']]);
                    } else {
                        // Se a carta não existe, adicionar normalmente
                        $stmt = $pdo->prepare("
                            INSERT INTO colecao (usuario_id, carta_id, quantidade, origem, data_obtencao) 
                            VALUES (?, ?, 1, 'troca', NOW())
                        ");
                        $stmt->execute([$troca['usuario_origem_id'], $troca['carta_desejada_id']]);
                    }
                    
                    // Atualizar status da troca
                    $stmt = $pdo->prepare("UPDATE trocas SET status = 'concluida', data_resposta = NOW() WHERE id = ?");
                    $stmt->execute([$troca_id]);
                    
                    $pdo->commit();
                    
                    // Verificar conquistas de troca para ambos os usuários
                    verificarConquistasTroca($troca['usuario_origem_id']);
                    verificarConquistasTroca($_SESSION['usuario_id']);
                    
                    // Buscar informações das cartas para a notificação
                    $stmt = $pdo->prepare("SELECT * FROM cartas WHERE id = ?");
                    $stmt->execute([$troca['carta_oferecida_id']]);
                    $carta_oferecida = $stmt->fetch();
                    
                    // Adicionar notificação para o usuário de origem
                    adicionarNotificacao(
                        $troca['usuario_origem_id'], 
                        'troca', 
                        "Sua oferta de troca para " . $carta_oferecida['nome'] . " foi aceita!"
                    );
                    
                    // Adicionar notificação para o usuário atual
                    adicionarNotificacao(
                        $_SESSION['usuario_id'], 
                        'troca', 
                        "Você aceitou a oferta de troca e recebeu " . $carta_oferecida['nome'] . "!"
                    );
                    
                    // Configurar animação de troca aceita
                    $mostrar_animacao = true;
                    $tipo_animacao = 'aceita';
                    $carta_animacao = $carta_oferecida;
                    $mensagem_animacao = "Troca Concluída com Sucesso!";
                    
                    $mensagem = "Troca aceita com sucesso!";
                    $tipo_mensagem = 'sucesso';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $mensagem = "Erro ao processar a troca: " . $e->getMessage();
                    $tipo_mensagem = 'erro';
                }
            }
        } else if ($resposta === 'recusar') {
            // Atualizar status da troca
            $stmt = $pdo->prepare("UPDATE trocas SET status = 'recusada', data_resposta = NOW() WHERE id = ?");
            $stmt->execute([$troca_id]);
            
            // Buscar informações das cartas para a notificação
            $stmt = $pdo->prepare("SELECT * FROM cartas WHERE id = ?");
            $stmt->execute([$troca['carta_oferecida_id']]);
            $carta_oferecida = $stmt->fetch();
            
            // Adicionar notificação para o usuário de origem
            adicionarNotificacao(
                $troca['usuario_origem_id'], 
                'troca', 
                "Sua oferta de troca para " . $carta_oferecida['nome'] . " foi recusada."
            );
            
            // Configurar animação de troca recusada
            $mostrar_animacao = true;
            $tipo_animacao = 'recusada';
            $carta_animacao = $carta_oferecida;
            $mensagem_animacao = "Troca Recusada!";
            
            $mensagem = "Troca recusada com sucesso.";
            $tipo_mensagem = 'info';
        }
    }
}

// Processar cancelamento de troca
if (isset($_POST['cancelar_troca'])) {
    $troca_id = $_POST['troca_id'];
    
    // Verificar se a troca pertence ao usuário
    $stmt = $pdo->prepare("SELECT * FROM trocas WHERE id = ? AND usuario_origem_id = ? AND status = 'pendente'");
    $stmt->execute([$troca_id, $_SESSION['usuario_id']]);
    $troca = $stmt->fetch();
    
    if (!$troca) {
        $mensagem = "Solicitação de troca não encontrada ou não pode ser cancelada.";
        $tipo_mensagem = 'erro';
    } else {
        // Cancelar a troca
        $stmt = $pdo->prepare("UPDATE trocas SET status = 'cancelada', data_resposta = NOW() WHERE id = ?");
        $stmt->execute([$troca_id]);
        
        // Notificar o usuário de destino
        adicionarNotificacao(
            $troca['usuario_destino_id'],
            'troca',
            "Uma solicitação de troca foi cancelada pelo remetente."
        );
        
        $mensagem = "Solicitação de troca cancelada com sucesso.";
        $tipo_mensagem = 'sucesso';
        
        // Atualizar limite de trocas
        $limite_trocas = verificarLimiteTrocas($usuario_id);
        $pode_trocar = podeTrocar($usuario_id);
    }
}

// Função para verificar conquistas de troca
function verificarConquistasTroca($usuario_id) {
    global $pdo;
    
    // Contar trocas concluídas
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_trocas
        FROM trocas
        WHERE (usuario_origem_id = ? OR usuario_destino_id = ?)
        AND status = 'concluida'
    ");
    $stmt->execute([$usuario_id, $usuario_id]);
    $resultado = $stmt->fetch();
    $total_trocas = $resultado['total_trocas'];
    
    // Verificar conquista "Negociante"
    if ($total_trocas == 1) {
        adicionarConquista($usuario_id, 'Negociante');
    }
    
    // Verificar conquista "Mercador"
    if ($total_trocas == 10) {
        adicionarConquista($usuario_id, 'Mercador');
    }
    
    // Verificar conquista "Magnata"
    if ($total_trocas == 30) {
        adicionarConquista($usuario_id, 'Magnata');
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
            $stmt = $pdo->prepare("
                INSERT INTO ranking (usuario_id, pontuacao, ultima_atualizacao)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE pontuacao = pontuacao + ?, ultima_atualizacao = NOW()
            ");
            $stmt->execute([$usuario_id, $conquista['pontos'], $conquista['pontos']]);
            
            // Atualizar posições no ranking
            atualizarPosicoesRanking();
            
            return true;
        }
    }
    
    return false;
}

// Função para atualizar posições no ranking
function atualizarPosicoesRanking() {
    global $pdo;
    
    // Primeiro, remover duplicatas
    $pdo->query("
        CREATE TEMPORARY TABLE temp_ranking AS
        SELECT usuario_id, MAX(pontuacao) as pontuacao, MIN(ultima_atualizacao) as ultima_atualizacao
        FROM ranking
        GROUP BY usuario_id
    ");
    
    $pdo->query("TRUNCATE TABLE ranking");
    
    $pdo->query("
        INSERT INTO ranking (usuario_id, pontuacao, ultima_atualizacao)
        SELECT usuario_id, pontuacao, ultima_atualizacao FROM temp_ranking
    ");
    
    $pdo->query("DROP TEMPORARY TABLE temp_ranking");
    
    // Agora, atualizar posições
    $stmt = $pdo->query("SELECT id, usuario_id, pontuacao FROM ranking ORDER BY pontuacao DESC, ultima_atualizacao ASC");
    $rankings = $stmt->fetchAll();
    
    // Atualizar posição de cada usuário
    $posicao = 1;
    foreach ($rankings as $ranking) {
        $stmt = $pdo->prepare("UPDATE ranking SET posicao = ? WHERE id = ?");
        $stmt->execute([$posicao, $ranking['id']]);
        $posicao++;
    }
}

// Buscar solicitações de troca recebidas
$stmt = $pdo->prepare("
    SELECT t.*, 
           u_origem.nome as usuario_origem_nome,
           c_oferecida.nome as carta_oferecida_nome, 
           c_oferecida.imagem as carta_oferecida_imagem,
           c_desejada.nome as carta_desejada_nome,
           c_desejada.imagem as carta_desejada_imagem
    FROM trocas t
    JOIN usuarios u_origem ON t.usuario_origem_id = u_origem.id
    JOIN cartas c_oferecida ON t.carta_oferecida_id = c_oferecida.id
    JOIN cartas c_desejada ON t.carta_desejada_id = c_desejada.id
    WHERE t.usuario_destino_id = ? AND t.status = 'pendente'
    ORDER BY t.data_solicitacao DESC
");
$stmt->execute([$_SESSION['usuario_id']]);
$trocas_recebidas = $stmt->fetchAll();

// Buscar solicitações de troca enviadas
$stmt = $pdo->prepare("
    SELECT t.*, 
           u_destino.nome as usuario_destino_nome,
           c_oferecida.nome as carta_oferecida_nome, 
           c_oferecida.imagem as carta_oferecida_imagem,
           c_desejada.nome as carta_desejada_nome,
           c_desejada.imagem as carta_desejada_imagem
    FROM trocas t
    JOIN usuarios u_destino ON t.usuario_destino_id = u_destino.id
    JOIN cartas c_oferecida ON t.carta_oferecida_id = c_oferecida.id
    JOIN cartas c_desejada ON t.carta_desejada_id = c_desejada.id
    WHERE t.usuario_origem_id = ?
    ORDER BY t.data_solicitacao DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['usuario_id']]);
$trocas_enviadas = $stmt->fetchAll();

// Buscar usuários para oferecer troca
$stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE id != ? ORDER BY nome");
$stmt->execute([$_SESSION['usuario_id']]);
$usuarios = $stmt->fetchAll();

// Buscar cartas do usuário para oferecer
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

// Buscar todas as cartas para desejar
$stmt = $pdo->query("SELECT * FROM cartas ORDER BY nome");
$todas_cartas = $stmt->fetchAll();

// Adicionar esta função no arquivo trocas.php
function notificarTroca($troca_id) {
    global $pdo;
    
    // Buscar informações da troca
    $stmt = $pdo->prepare("
        SELECT t.*, 
               u_origem.nome as usuario_origem_nome,
               u_destino.nome as usuario_destino_nome,
               c_oferecida.nome as carta_oferecida_nome, 
               c_desejada.nome as carta_desejada_nome
        FROM trocas t
        JOIN usuarios u_origem ON t.usuario_origem_id = u_origem.id
        JOIN usuarios u_destino ON t.usuario_destino_id = u_destino.id
        JOIN cartas c_oferecida ON t.carta_oferecida_id = c_oferecida.id
        JOIN cartas c_desejada ON t.carta_desejada_id = c_desejada.id
        WHERE t.id = ?
    ");
    $stmt->execute([$troca_id]);
    $troca = $stmt->fetch();
    
    if ($troca) {
        // Notificar o usuário de origem sobre a solicitação
        adicionarNotificacao(
            $troca['usuario_destino_id'], 
            'troca', 
            "Você recebeu uma oferta de troca de {$troca['usuario_origem_nome']}. Ele oferece {$troca['carta_oferecida_nome']} em troca de {$troca['carta_desejada_nome']}."
        );
        
        return true;
    }
    
    return false;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trocas - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="trades">
        <div class="container">
            <h1>Sistema de Trocas</h1>
            
            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <p><?php echo $mensagem; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="trade-limits-info">
                <p>Limite diário: <strong><?php echo $limite_trocas['trocas_realizadas']; ?>/<?php echo $limite_trocas['limite_diario']; ?></strong> trocas realizadas hoje</p>
                <p>Trocas restantes: <strong><?php echo $limite_trocas['trocas_restantes']; ?></strong></p>
                <?php if (!$pode_trocar): ?>
                    <?php if ($limite_trocas['trocas_restantes'] <= 0): ?>
                        <p class="warning">Você atingiu o limite de trocas diárias.</p>
                    <?php else: ?>
                        <p class="warning">Você já tem uma troca pendente. Aguarde a resposta ou cancele a troca atual para iniciar uma nova.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="trades-content">
                <div class="new-trade">
                    <h2>Solicitar Nova Troca</h2>
                    
                    <?php if (empty($minhas_cartas)): ?>
                        <p class="empty-message">Você não possui cartas para oferecer. Visite a <a href="loja.php">loja</a> para adquirir pacotes.</p>
                    <?php elseif (empty($usuarios)): ?>
                        <p class="empty-message">Não há outros usuários disponíveis para troca.</p>
                    <?php elseif (!$pode_trocar): ?>
                        <p class="empty-message">Você não pode iniciar novas trocas no momento.</p>
                    <?php else: ?>
                        <form method="post" class="trade-form">
                            <div class="form-group">
                                <label for="usuario_destino">Usuário para troca:</label>
                                <select name="usuario_destino" id="usuario_destino" required onchange="carregarCartasUsuario(this.value)">
                                    <option value="">Selecione um usuário</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?php echo $usuario['id']; ?>"><?php echo htmlspecialchars($usuario['nome']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group hidden-selects">
                                <input type="hidden" name="carta_oferecida" id="carta_oferecida" required>
                                <input type="hidden" name="carta_desejada" id="carta_desejada" required>
                            </div>
                            
                            <div class="trade-preview" id="trade-preview" style="display: none;">
                                <h3>Prévia da Troca</h3>
                                <div class="trade-cards">
                                    <div class="trade-card-preview">
                                        <h4>Você oferece:</h4>
                                        <div id="carta-oferecida-preview" class="card-preview">
                                            <p class="empty-selection">Selecione uma carta sua abaixo</p>
                                        </div>
                                    </div>
                                    <div class="trade-card-preview">
                                        <h4>Você deseja:</h4>
                                        <div id="carta-desejada-preview" class="card-preview">
                                            <p class="empty-selection">Selecione uma carta do outro usuário</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="solicitar_troca" id="btn-solicitar-troca" class="btn-primary" disabled>Solicitar Troca</button>
                        </form>
                        
                        <div class="trade-selection-container">
                            <div id="minhas-cartas-container" class="cartas-container" style="display: none;">
                                <h3>Suas Cartas</h3>
                                <div id="minhas-cartas-grid" class="cards-grid"></div>
                            </div>
                            
                            <div id="cartas-usuario-container" class="cartas-container" style="display: none;">
                                <h3>Cartas de: <span id="nome-usuario-selecionado"></span></h3>
                                <div id="cartas-usuario-grid" class="cards-grid"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="received-trades">
                    <h2>Solicitações de Troca Recebidas</h2>
                    
                    <?php if (empty($trocas_recebidas)): ?>
                        <p class="empty-message">Você não tem solicitações de troca pendentes.</p>
                    <?php else: ?>
                        <div class="trades-list">
                            <?php foreach ($trocas_recebidas as $troca): ?>
                                <div class="trade-item">
                                    <div class="trade-info">
                                        <div class="trade-header">
                                            <h3>Troca de <strong><?php echo htmlspecialchars($troca['usuario_origem_nome']); ?></strong></h3>
                                            <div class="trade-status-badge status-<?php echo $troca['status']; ?>">Pendente</div>
                                        </div>
                                        
                                        <div class="trade-cards">
                                            <div class="trade-card-container">
                                                <h4>Você recebe:</h4>
                                                <div class="trade-card">
                                                    <img src="img/cards/<?php echo htmlspecialchars($troca['carta_oferecida_imagem']); ?>" alt="<?php echo htmlspecialchars($troca['carta_oferecida_nome']); ?>">
                                                    <p><?php echo htmlspecialchars($troca['carta_oferecida_nome']); ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="trade-arrow">
                                                <img src="img/icons/arrow-right.png" alt="Trocar por">
                                            </div>
                                            
                                            <div class="trade-card-container">
                                                <h4>Você entrega:</h4>
                                                <div class="trade-card">
                                                    <img src="img/cards/<?php echo htmlspecialchars($troca['carta_desejada_imagem']); ?>" alt="<?php echo htmlspecialchars($troca['carta_desejada_nome']); ?>">
                                                    <p><?php echo htmlspecialchars($troca['carta_desejada_nome']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="trade-footer">
                                            <p class="trade-date">
                                                Data: <?php echo date('d/m/Y H:i', strtotime($troca['data_solicitacao'])); ?>
                                            </p>
                                            
                                            <div class="trade-actions">
                                                <form method="post">
                                                    <input type="hidden" name="troca_id" value="<?php echo $troca['id']; ?>">
                                                    <button type="submit" name="responder_troca" value="aceitar" class="btn-primary">Aceitar</button>
                                                    <button type="submit" name="responder_troca" value="recusar" class="btn-secondary">Recusar</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="sent-trades">
                    <h2>Solicitações de Troca Enviadas</h2>
                    
                    <?php if (empty($trocas_enviadas)): ?>
                        <p class="empty-message">Você não enviou solicitações de troca recentemente.</p>
                    <?php else: ?>
                        <div class="trades-list">
                            <?php foreach ($trocas_enviadas as $troca): ?>
                                <div class="trade-item">
                                    <div class="trade-info">
                                        <div class="trade-header">
                                            <h3>Troca com <strong><?php echo htmlspecialchars($troca['usuario_destino_nome']); ?></strong></h3>
                                            <div class="trade-status-badge status-<?php echo $troca['status']; ?>">
                                                <?php 
                                                    switch($troca['status']) {
                                                        case 'pendente':
                                                            echo 'Pendente';
                                                            break;
                                                        case 'concluida':
                                                            echo 'Concluída';
                                                            break;
                                                        case 'recusada':
                                                            echo 'Recusada';
                                                            break;
                                                        case 'cancelada':
                                                            echo 'Cancelada';
                                                            break;
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                        
                                        <div class="trade-cards">
                                            <div class="trade-card-container">
                                                <h4>Você oferece:</h4>
                                                <div class="trade-card">
                                                    <img src="img/cards/<?php echo htmlspecialchars($troca['carta_oferecida_imagem']); ?>" alt="<?php echo htmlspecialchars($troca['carta_oferecida_nome']); ?>">
                                                    <p><?php echo htmlspecialchars($troca['carta_oferecida_nome']); ?></p>
                                                </div>
                                            </div>
                                            
                                            <div class="trade-arrow">
                                                <img src="img/icons/arrow-right.png" alt="Trocar por">
                                            </div>
                                            
                                            <div class="trade-card-container">
                                                <h4>Você deseja:</h4>
                                                <div class="trade-card">
                                                    <img src="img/cards/<?php echo htmlspecialchars($troca['carta_desejada_imagem']); ?>" alt="<?php echo htmlspecialchars($troca['carta_desejada_nome']); ?>">
                                                    <p><?php echo htmlspecialchars($troca['carta_desejada_nome']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="trade-footer">
                                            <p class="trade-date">
                                                Data: <?php echo date('d/m/Y H:i', strtotime($troca['data_solicitacao'])); ?>
                                            </p>
                                            
                                            <?php if ($troca['status'] === 'pendente'): ?>
                                                <div class="trade-actions">
                                                    <form method="post">
                                                        <input type="hidden" name="troca_id" value="<?php echo $troca['id']; ?>">
                                                        <button type="submit" name="cancelar_troca" class="btn-secondary">Cancelar Troca</button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
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

    <script>
    let cartaSelecionadaUsuario = null;
    let minhaCartaSelecionada = null;
    
    // Função para carregar minhas cartas
    function carregarMinhasCartas() {
        // Usar os dados já disponíveis na página
        const minhasCartasGrid = document.getElementById('minhas-cartas-grid');
        minhasCartasGrid.innerHTML = '';
        
        // Mostrar o container de minhas cartas
        document.getElementById('minhas-cartas-container').style.display = 'block';
        document.getElementById('trade-preview').style.display = 'block';
        
        // Criar cartas
        let cartasHTML = '';
        
        <?php foreach ($minhas_cartas as $index => $carta): ?>
            // Determinar as classes com base na raridade e origem
            let cardClasses<?php echo $index; ?> = ['card'];
            
            // Adicionar classe de raridade
            const raridade<?php echo $index; ?> = '<?php echo strtolower($carta['raridade']); ?>';
            if (raridade<?php echo $index; ?> === 'rara') {
                cardClasses<?php echo $index; ?>.push('rara');
            } else if (raridade<?php echo $index; ?> === 'ultra rara') {
                cardClasses<?php echo $index; ?>.push('ultra-rara');
            }
            
            // Adicionar classe de origem
            const origem<?php echo $index; ?> = '<?php echo strtolower($carta['origem']); ?>';
            if (origem<?php echo $index; ?> === 'troca') {
                cardClasses<?php echo $index; ?>.push('origem-troca', 'troca');
            } else if (origem<?php echo $index; ?> === 'batalha') {
                cardClasses<?php echo $index; ?>.push('origem-batalha', 'batalha');
            } else if (origem<?php echo $index; ?> === 'evento') {
                cardClasses<?php echo $index; ?>.push('origem-evento', 'evento');
            } else if (origem<?php echo $index; ?> === 'pacote') {
                cardClasses<?php echo $index; ?>.push('origem-pacote');
            }
            
            // Adicionar indicador de quantidade se for maior que 1
            let quantityHtml<?php echo $index; ?> = '';
            <?php if ($carta['quantidade'] > 1): ?>
                quantityHtml<?php echo $index; ?> = '<div class="card-quantity"><?php echo $carta['quantidade']; ?></div>';
            <?php endif; ?>
            
            // Criar HTML da carta
            cartasHTML += `
                <div class="${cardClasses<?php echo $index; ?>.join(' ')}" data-id="<?php echo $carta['id']; ?>">
                    <img src="img/cards/<?php echo htmlspecialchars($carta['imagem']); ?>" alt="<?php echo htmlspecialchars($carta['nome']); ?>">
                    ${quantityHtml<?php echo $index; ?>}
                </div>
            `;
        <?php endforeach; ?>
        
        // Adicionar cartas ao grid
        minhasCartasGrid.innerHTML = cartasHTML;
        
        // Adicionar eventos de clique
        const cartasDivs = minhasCartasGrid.querySelectorAll('.card');
        cartasDivs.forEach(cartaDiv => {
            cartaDiv.addEventListener('click', function() {
                // Remover seleção anterior
                const cartasSelecionadas = document.querySelectorAll('#minhas-cartas-grid .card.selected');
                cartasSelecionadas.forEach(c => c.classList.remove('selected'));
                
                // Adicionar classe de seleção
                this.classList.add('selected');
                
                // Obter ID da carta
                const cartaId = this.getAttribute('data-id');
                
                // Encontrar dados da carta
                <?php foreach ($minhas_cartas as $carta): ?>
                    if (cartaId === '<?php echo $carta['id']; ?>') {
                        minhaCartaSelecionada = {
                            id: '<?php echo $carta['id']; ?>',
                            nome: '<?php echo htmlspecialchars(addslashes($carta['nome'])); ?>',
                            imagem: '<?php echo htmlspecialchars($carta['imagem']); ?>'
                        };
                    }
                <?php endforeach; ?>
                
                // Atualizar o campo oculto
                document.getElementById('carta_oferecida').value = minhaCartaSelecionada.id;
                
                // Atualizar a prévia
                atualizarPrevia();
            });
        });
    }
    
    // Função para carregar as cartas do usuário selecionado
    function carregarCartasUsuario(usuarioId) {
        if (!usuarioId) {
            document.getElementById('cartas-usuario-container').style.display = 'none';
            document.getElementById('minhas-cartas-container').style.display = 'none';
            document.getElementById('trade-preview').style.display = 'none';
            return;
        }
        
        // Carregar minhas cartas
        carregarMinhasCartas();
        
        fetch('buscar_cartas_usuario.php?usuario_id=' + usuarioId)
            .then(response => response.json())
            .then(data => {
                if (data.erro) {
                    alert('Erro: ' + data.erro);
                    return;
                }
                
                // Preencher o grid de cartas do usuário
                const cartasGrid = document.getElementById('cartas-usuario-grid');
                cartasGrid.innerHTML = '';
                
                if (data.cartas.length === 0) {
                    document.getElementById('cartas-usuario-container').style.display = 'none';
                    alert('Este usuário não possui cartas para troca.');
                } else {
                    // Mostrar o nome do usuário
                    document.getElementById('nome-usuario-selecionado').textContent = data.usuario;
                    document.getElementById('cartas-usuario-container').style.display = 'block';
                    
                    // Criar HTML para as cartas
                    let cartasHTML = '';
                    
                    // Adicionar cada carta ao grid
                    data.cartas.forEach(carta => {
                        // Determinar as classes com base na raridade e origem
                        let cardClasses = ['card'];
                        
                        // Adicionar classe de raridade
                        const raridade = carta.raridade.toLowerCase();
                        if (raridade === 'rara') {
                            cardClasses.push('rara');
                        } else if (raridade === 'ultra rara') {
                            cardClasses.push('ultra-rara');
                        }
                        
                        // Adicionar classe de origem
                        if (carta.origem) {
                            const origem = carta.origem.toLowerCase();
                            if (origem === 'troca') {
                                cardClasses.push('origem-troca', 'troca');
                            } else if (origem === 'batalha') {
                                cardClasses.push('origem-batalha', 'batalha');
                            } else if (origem === 'evento') {
                                cardClasses.push('origem-evento', 'evento');
                            } else if (origem === 'pacote') {
                                cardClasses.push('origem-pacote');
                            }
                        }
                        
                        // Adicionar indicador de quantidade se for maior que 1
                        let quantityHtml = '';
                        if (carta.quantidade > 1) {
                            quantityHtml = `<div class="card-quantity">${carta.quantidade}</div>`;
                        }
                        
                        // Criar HTML da carta
                        cartasHTML += `
                            <div class="${cardClasses.join(' ')}" data-id="${carta.id}">
                                <img src="img/cards/${carta.imagem}" alt="${carta.nome}">
                                ${quantityHtml}
                            </div>
                        `;
                    });
                    
                    // Adicionar cartas ao grid
                    cartasGrid.innerHTML = cartasHTML;
                    
                    // Adicionar eventos de clique
                    const cartasDivs = cartasGrid.querySelectorAll('.card');
                    cartasDivs.forEach(cartaDiv => {
                        cartaDiv.addEventListener('click', function() {
                            // Remover seleção anterior
                            const cartasSelecionadas = document.querySelectorAll('#cartas-usuario-grid .card.selected');
                            cartasSelecionadas.forEach(c => c.classList.remove('selected'));
                            
                            // Adicionar classe de seleção
                            this.classList.add('selected');
                            
                            // Obter ID da carta
                            const cartaId = this.getAttribute('data-id');
                            
                            // Encontrar dados da carta
                            data.cartas.forEach(carta => {
                                if (cartaId === carta.id.toString()) {
                                    cartaSelecionadaUsuario = {
                                        id: carta.id,
                                        nome: carta.nome,
                                        imagem: carta.imagem
                                    };
                                }
                            });
                            
                            // Atualizar o campo oculto
                            document.getElementById('carta_desejada').value = cartaSelecionadaUsuario.id;
                            
                            // Atualizar a prévia
                            atualizarPrevia();
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Erro ao carregar cartas:', error);
                alert('Ocorreu um erro ao carregar as cartas do usuário.');
            });
    }
    
    // Função para atualizar a prévia da troca
    function atualizarPrevia() {
        const cartaOferecidaPreview = document.getElementById('carta-oferecida-preview');
        const cartaDesejadaPreview = document.getElementById('carta-desejada-preview');
        const btnSolicitarTroca = document.getElementById('btn-solicitar-troca');
        
        // Atualizar prévia da carta oferecida
        if (minhaCartaSelecionada) {
            cartaOferecidaPreview.innerHTML = `
                <img src="img/cards/${minhaCartaSelecionada.imagem}" alt="${minhaCartaSelecionada.nome}">
                <p>${minhaCartaSelecionada.nome}</p>
            `;
        } else {
            cartaOferecidaPreview.innerHTML = `<p class="empty-selection">Selecione uma carta sua abaixo</p>`;
        }
        
        // Atualizar prévia da carta desejada
        if (cartaSelecionadaUsuario) {
            cartaDesejadaPreview.innerHTML = `
                <img src="img/cards/${cartaSelecionadaUsuario.imagem}" alt="${cartaSelecionadaUsuario.nome}">
                <p>${cartaSelecionadaUsuario.nome}</p>
            `;
        } else {
            cartaDesejadaPreview.innerHTML = `<p class="empty-selection">Selecione uma carta do outro usuário</p>`;
        }
        
        // Habilitar/desabilitar botão de solicitar troca
        btnSolicitarTroca.disabled = !(minhaCartaSelecionada && cartaSelecionadaUsuario);
    }
    </script>

    <script src="js/main.js"></script>
    
    <?php if ($mostrar_animacao): ?>
    <!-- Animação de troca -->
    <div class="trade-animation-overlay" id="tradeAnimation">
        <div class="trade-animation-container <?php echo $tipo_animacao; ?>">
            <div class="trade-animation-content">
                <h2><?php echo $mensagem_animacao; ?></h2>
                
                <?php if ($tipo_animacao === 'aceita'): ?>
                    <div class="confetti-container">
                        <?php for ($i = 0; $i < 50; $i++): ?>
                            <div class="confetti"></div>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                
                <div class="trade-animation-card">
                    <img src="img/cards/<?php echo htmlspecialchars($carta_animacao['imagem']); ?>" alt="<?php echo htmlspecialchars($carta_animacao['nome']); ?>">
                    <p><?php echo htmlspecialchars($carta_animacao['nome']); ?></p>
                </div>
                
                <button class="btn-primary close-animation">Continuar</button>
            </div>
        </div>
    </div>
    
    <script>
        // Mostrar animação
        document.addEventListener('DOMContentLoaded', function() {
            const tradeAnimation = document.getElementById('tradeAnimation');
            const closeButton = document.querySelector('.close-animation');
            
            // Mostrar animação
            if (tradeAnimation) {
                tradeAnimation.classList.add('active');
                
                // Fechar animação ao clicar no botão
                if (closeButton) {
                    closeButton.addEventListener('click', function() {
                        tradeAnimation.classList.remove('active');
                        setTimeout(() => {
                            tradeAnimation.style.display = 'none';
                        }, 500);
                    });
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
