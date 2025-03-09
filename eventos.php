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

$mensagem = '';
$tipo_mensagem = '';

// Processar participação em evento
if (isset($_POST['participar_evento'])) {
    $evento_id = $_POST['evento_id'];
    
    // Verificar se o evento existe e está ativo
    $stmt = $pdo->prepare("
        SELECT * FROM eventos 
        WHERE id = ? AND ativo = TRUE 
        AND NOW() BETWEEN data_inicio AND data_fim
    ");
    $stmt->execute([$evento_id]);
    $evento = $stmt->fetch();
    
    if (!$evento) {
        $mensagem = "Este evento não está disponível para participação.";
        $tipo_mensagem = 'erro';
    } else {
        // Verificar se o usuário já participou deste evento
        $stmt = $pdo->prepare("SELECT * FROM evento_participacoes WHERE evento_id = ? AND usuario_id = ?");
        $stmt->execute([$evento_id, $_SESSION['usuario_id']]);
        $ja_participou = $stmt->fetch();
        
        if ($ja_participou) {
            $mensagem = "Você já participou deste evento.";
            $tipo_mensagem = 'info';
        } else {
            // Registrar participação
            $stmt = $pdo->prepare("INSERT INTO evento_participacoes (evento_id, usuario_id) VALUES (?, ?)");
            $stmt->execute([$evento_id, $_SESSION['usuario_id']]);
            
            // Buscar recompensas do evento
            $stmt = $pdo->prepare("SELECT * FROM evento_recompensas WHERE evento_id = ?");
            $stmt->execute([$evento_id]);
            $recompensas = $stmt->fetchAll();
            
            // Processar recompensas
            $recompensas_texto = [];
            
            foreach ($recompensas as $recompensa) {
                switch ($recompensa['tipo']) {
                    case 'carta':
                        // Dar cartas aleatórias ao usuário
                        for ($i = 0; $i < $recompensa['valor']; $i++) {
                            // Selecionar uma carta aleatória
                            $stmt = $pdo->query("SELECT * FROM cartas ORDER BY RAND() LIMIT 1");
                            $carta = $stmt->fetch();
                            
                            // Verificar se o usuário já possui esta carta
                            $stmt = $pdo->prepare("SELECT id, quantidade FROM colecao WHERE usuario_id = ? AND carta_id = ?");
                            $stmt->execute([$_SESSION['usuario_id'], $carta['id']]);
                            $carta_existente = $stmt->fetch();
                            
                            if ($carta_existente) {
                                // Se a carta já existe, apenas incrementar a quantidade
                                $stmt = $pdo->prepare("UPDATE colecao SET quantidade = quantidade + 1 WHERE id = ?");
                                $stmt->execute([$carta_existente['id']]);
                            } else {
                                // Se a carta não existe, adicionar normalmente
                                $stmt = $pdo->prepare("
                                    INSERT INTO colecao (usuario_id, carta_id, quantidade, origem, data_obtencao) 
                                    VALUES (?, ?, 1, 'evento', NOW())
                                ");
                                $stmt->execute([$_SESSION['usuario_id'], $carta['id']]);
                            }
                            
                            $recompensas_texto[] = "Carta: " . $carta['nome'];
                        }
                        break;
                        
                    case 'pontos':
                        // Adicionar pontos ao ranking
                        $stmt = $pdo->prepare("
                            INSERT INTO ranking (usuario_id, pontuacao, ultima_atualizacao)
                            VALUES (?, ?, NOW())
                            ON DUPLICATE KEY UPDATE pontuacao = pontuacao + ?, ultima_atualizacao = NOW()
                        ");
                        $stmt->execute([$_SESSION['usuario_id'], $recompensa['valor'], $recompensa['valor']]);
                        
                        // Atualizar posições no ranking
                        $stmt = $pdo->query("SELECT id, pontuacao FROM ranking ORDER BY pontuacao DESC, ultima_atualizacao ASC");
                        $rankings = $stmt->fetchAll();
                        
                        $posicao = 1;
                        foreach ($rankings as $ranking) {
                            $stmt = $pdo->prepare("UPDATE ranking SET posicao = ? WHERE id = ?");
                            $stmt->execute([$posicao, $ranking['id']]);
                            $posicao++;
                        }
                        
                        $recompensas_texto[] = $recompensa['valor'] . " pontos de ranking";
                        break;
                }
            }
            
            // Marcar recompensas como recebidas
            $stmt = $pdo->prepare("UPDATE evento_participacoes SET recompensa_recebida = TRUE WHERE evento_id = ? AND usuario_id = ?");
            $stmt->execute([$evento_id, $_SESSION['usuario_id']]);
            
            // Adicionar notificação
            $mensagem_notificacao = "Você participou do evento '{$evento['titulo']}' e recebeu: " . implode(", ", $recompensas_texto);
            adicionarNotificacao($_SESSION['usuario_id'], 'evento', $mensagem_notificacao);
            
            $mensagem = "Você participou com sucesso do evento '{$evento['titulo']}' e recebeu suas recompensas!";
            $tipo_mensagem = 'sucesso';
        }
    }
}

// Buscar eventos ativos
$stmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM evento_participacoes WHERE evento_id = e.id) as total_participantes,
           (SELECT COUNT(*) FROM evento_participacoes WHERE evento_id = e.id AND usuario_id = ?) as usuario_participou
    FROM eventos e
    WHERE e.ativo = TRUE
    ORDER BY 
        CASE 
            WHEN NOW() BETWEEN e.data_inicio AND e.data_fim THEN 0
            WHEN e.data_inicio > NOW() THEN 1
            ELSE 2
        END,
        e.data_inicio ASC
");
$stmt->execute([$_SESSION['usuario_id']]);
$eventos = $stmt->fetchAll();

// Buscar eventos passados
$stmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM evento_participacoes WHERE evento_id = e.id) as total_participantes,
           (SELECT COUNT(*) FROM evento_participacoes WHERE evento_id = e.id AND usuario_id = ?) as usuario_participou
    FROM eventos e
    WHERE e.ativo = FALSE OR e.data_fim < NOW()
    ORDER BY e.data_fim DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['usuario_id']]);
$eventos_passados = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventos Especiais - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="events">
        <div class="container">
            <h1>Eventos Especiais</h1>
            
            <?php if (!empty($mensagem)): ?>
                <div class="mensagem <?php echo $tipo_mensagem; ?>">
                    <p><?php echo $mensagem; ?></p>
                </div>
            <?php endif; ?>
            
            <div class="events-content">
                <div class="active-events">
                    <h2>Eventos Ativos</h2>
                    
                    <?php if (empty($eventos)): ?>
                        <p class="empty-message">Não há eventos ativos no momento. Volte mais tarde!</p>
                    <?php else: ?>
                        <div class="events-grid">
                            <?php foreach ($eventos as $evento): ?>
                                <?php
                                    $evento_ativo = (strtotime($evento['data_inicio']) <= time() && strtotime($evento['data_fim']) >= time());
                                    $evento_futuro = (strtotime($evento['data_inicio']) > time());
                                    $usuario_participou = $evento['usuario_participou'] > 0;
                                    
                                    $status_class = $evento_ativo ? 'active' : ($evento_futuro ? 'upcoming' : 'ended');
                                    $status_text = $evento_ativo ? 'Ativo' : ($evento_futuro ? 'Em breve' : 'Encerrado');
                                ?>
                                <div class="event-card <?php echo $status_class; ?>">
                                    <div class="event-image">
                                        <img src="img/events/<?php echo htmlspecialchars($evento['imagem']); ?>" alt="<?php echo htmlspecialchars($evento['titulo']); ?>">
                                        <span class="event-status"><?php echo $status_text; ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h3><?php echo htmlspecialchars($evento['titulo']); ?></h3>
                                        <p class="event-description"><?php echo htmlspecialchars($evento['descricao']); ?></p>
                                        <div class="event-details">
                                            <p><strong>Tipo:</strong> <?php echo ucfirst(htmlspecialchars($evento['tipo'])); ?></p>
                                            <p><strong>Início:</strong> <?php echo date('d/m/Y H:i', strtotime($evento['data_inicio'])); ?></p>
                                            <p><strong>Término:</strong> <?php echo date('d/m/Y H:i', strtotime($evento['data_fim'])); ?></p>
                                            <p><strong>Participantes:</strong> <?php echo $evento['total_participantes']; ?></p>
                                        </div>
                                        
                                        <?php if ($evento_ativo && !$usuario_participou): ?>
                                            <form method="post" class="event-action">
                                                <input type="hidden" name="evento_id" value="<?php echo $evento['id']; ?>">
                                                <button type="submit" name="participar_evento" class="btn-primary">Participar</button>
                                            </form>
                                        <?php elseif ($usuario_participou): ?>
                                            <div class="event-participated">
                                                <p>Você já participou deste evento!</p>
                                            </div>
                                        <?php elseif ($evento_futuro): ?>
                                            <div class="event-countdown">
                                                <p>Começa em: <span class="countdown" data-time="<?php echo strtotime($evento['data_inicio']); ?>"></span></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="past-events">
                    <h2>Eventos Passados</h2>
                    
                    <?php if (empty($eventos_passados)): ?>
                        <p class="empty-message">Não há eventos passados.</p>
                    <?php else: ?>
                        <div class="events-grid">
                            <?php foreach ($eventos_passados as $evento): ?>
                                <?php
                                    $evento_ativo = (strtotime($evento['data_inicio']) <= time() && strtotime($evento['data_fim']) >= time());
                                    $evento_futuro = (strtotime($evento['data_inicio']) > time());
                                    $usuario_participou = $evento['usuario_participou'] > 0;
                                    
                                    $status_class = $evento_ativo ? 'active' : ($evento_futuro ? 'upcoming' : 'ended');
                                    $status_text = $evento_ativo ? 'Ativo' : ($evento_futuro ? 'Em breve' : 'Encerrado');
                                ?>
                                <div class="event-card <?php echo $status_class; ?>">
                                    <div class="event-image">
                                        <img src="img/events/<?php echo htmlspecialchars($evento['imagem']); ?>" alt="<?php echo htmlspecialchars($evento['titulo']); ?>">
                                        <span class="event-status"><?php echo $status_text; ?></span>
                                    </div>
                                    <div class="event-info">
                                        <h3><?php echo htmlspecialchars($evento['titulo']); ?></h3>
                                        <p><?php echo htmlspecialchars($evento['descricao']); ?></p>
                                    </div>
                                    <?php if ($evento_ativo): ?>
                                        <a href="participar_evento.php?evento_id=<?php echo $evento['id']; ?>" class="btn-primary">Participar</a>
                                    <?php endif; ?>
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

    <script src="js/main.js"></script>
    <script>
        // Script para o dropdown de notificações
        document.addEventListener('DOMContentLoaded', function() {
            const notificationsToggle = document.querySelector('.notifications-toggle');
            const notificationsContent = document.querySelector('.notifications-content');
            
            if (notificationsToggle) {
                notificationsToggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    notificationsContent.classList.toggle('active');
                });
            }
            
            // Fechar o dropdown quando clicar fora dele
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.notifications-dropdown')) {
                    if (notificationsContent) {
                        notificationsContent.classList.remove('active');
                    }
                }
            });
            
            // Atualizar contadores de tempo
            const countdowns = document.querySelectorAll('.countdown');
            
            function updateCountdowns() {
                countdowns.forEach(function(countdown) {
                    const targetTime = parseInt(countdown.dataset.time) * 1000;
                    const now = new Date().getTime();
                    const difference = targetTime - now;
                    
                    if (difference <= 0) {
                        countdown.textContent = "Agora!";
                        return;
                    }
                    
                    const days = Math.floor(difference / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((difference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((difference % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((difference % (1000 * 60)) / 1000);
                    
                    let timeString = "";
                    if (days > 0) timeString += days + "d ";
                    if (hours > 0 || days > 0) timeString += hours + "h ";
                    if (minutes > 0 || hours > 0 || days > 0) timeString += minutes + "m ";
                    timeString += seconds + "s";
                    
                    countdown.textContent = timeString;
                });
            }
            
            if (countdowns.length > 0) {
                updateCountdowns();
                setInterval(updateCountdowns, 1000);
            }
        });
    </script>

    <?php include 'componentes/notificacoes_fix.php'; ?>
</body>
</html>
