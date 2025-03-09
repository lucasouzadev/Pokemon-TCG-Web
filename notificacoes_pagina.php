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

// Processar ações de notificações
if (isset($_POST['marcar_lida'])) {
    $notificacao_id = $_POST['notificacao_id'];
    marcarNotificacaoComoLida($notificacao_id, $_SESSION['usuario_id']);
    header('Location: notificacoes_pagina.php');
    exit;
}

if (isset($_POST['marcar_todas_lidas'])) {
    marcarTodasNotificacoesComoLidas($_SESSION['usuario_id']);
    header('Location: notificacoes_pagina.php');
    exit;
}

if (isset($_POST['excluir_lidas'])) {
    excluirNotificacoesLidas($_SESSION['usuario_id']);
    header('Location: notificacoes_pagina.php');
    exit;
}

// Buscar todas as notificações
$notificacoes = buscarTodasNotificacoes($_SESSION['usuario_id'], 50);
$total_nao_lidas = contarNotificacoesNaoLidas($_SESSION['usuario_id']);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Pokémon TCG Pocket</title>
    <link rel="icon" href="img/icons/pokemon-tcg-pocket-logo.png" type="image/x-icon">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'componentes/header.php'; ?>

    <section class="notifications-page">
        <div class="container">
            <h1>Suas Notificações</h1>
            
            <div class="notifications-actions">
                <?php if ($total_nao_lidas > 0): ?>
                    <form method="post" class="notification-action-form">
                        <button type="submit" name="marcar_todas_lidas" class="btn-secondary">Marcar todas como lidas</button>
                    </form>
                <?php endif; ?>
                
                <form method="post" class="notification-action-form">
                    <button type="submit" name="excluir_lidas" class="btn-secondary">Excluir notificações lidas</button>
                </form>
            </div>
            
            <div class="notifications-container">
                <?php if (empty($notificacoes)): ?>
                    <div class="empty-notifications">
                        <p>Você não tem notificações.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($notificacoes as $notificacao): ?>
                        <div class="notification-item <?php echo $notificacao['lida'] ? 'read' : 'unread'; ?>">
                            <div class="notification-icon <?php echo $notificacao['tipo']; ?>">
                                <?php
                                    $icone = 'notification.png';
                                    switch ($notificacao['tipo']) {
                                        case 'conquista':
                                            $icone = 'achievement.png';
                                            break;
                                        case 'batalha':
                                            $icone = 'battle.png';
                                            break;
                                        case 'troca':
                                            $icone = 'trade.png';
                                            break;
                                        case 'evento':
                                            $icone = 'event.png';
                                            break;
                                        case 'sistema':
                                            $icone = 'system.png';
                                            break;
                                    }
                                ?>
                                <img src="img/icons/<?php echo $icone; ?>" alt="<?php echo $notificacao['tipo']; ?>">
                            </div>
                            <div class="notification-content">
                                <p><?php echo htmlspecialchars($notificacao['mensagem']); ?></p>
                                <span class="notification-time"><?php echo date('d/m/Y H:i', strtotime($notificacao['data_criacao'])); ?></span>
                            </div>
                            <?php if (!$notificacao['lida']): ?>
                                <form method="post" class="mark-read">
                                    <input type="hidden" name="notificacao_id" value="<?php echo $notificacao['id']; ?>">
                                    <button type="submit" name="marcar_lida" title="Marcar como lida">✓</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <script src="js/main.js"></script>
</body>
</html>
