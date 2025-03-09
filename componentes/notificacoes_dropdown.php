<?php
// Verificar se o arquivo notificacoes.php já foi incluído
if (!function_exists('buscarNotificacoesNaoLidas')) {
    require_once 'notificacoes.php';
}

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    // Se não estiver logado, não exibir o componente
    return;
}

// Buscar notificações não lidas
try {
    $notificacoes_nao_lidas = buscarNotificacoesNaoLidas($_SESSION['usuario_id']);
    $total_nao_lidas = count($notificacoes_nao_lidas);
} catch (Exception $e) {
    error_log("Erro ao buscar notificações: " . $e->getMessage());
    $notificacoes_nao_lidas = [];
    $total_nao_lidas = 0;
}

// Processar ações de notificações
if (isset($_POST['marcar_lida'])) {
    $notificacao_id = $_POST['notificacao_id'];
    marcarNotificacaoComoLida($notificacao_id, $_SESSION['usuario_id']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['marcar_todas_lidas'])) {
    marcarTodasNotificacoesComoLidas($_SESSION['usuario_id']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!-- Apenas o dropdown de notificações, sem elementos de header -->
<div class="notifications-dropdown">
    <button class="notifications-toggle">
        <img src="img/icons/notification.png" alt="Notificações" class="nav-icon">
        <?php if ($total_nao_lidas > 0): ?>
            <span class="notifications-badge"><?php echo $total_nao_lidas; ?></span>
        <?php endif; ?>
    </button>
    
    <div class="notifications-content">
        <div class="notifications-header">
            <h3>Notificações</h3>
            <?php if ($total_nao_lidas > 0): ?>
                <form method="post" class="mark-all-read">
                    <button type="submit" name="marcar_todas_lidas">Marcar todas como lidas</button>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="notifications-list">
            <?php if (empty($notificacoes_nao_lidas)): ?>
                <div class="empty-notifications">
                    <p>Você não tem novas notificações.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notificacoes_nao_lidas as $notificacao): ?>
                    <div class="notification-item">
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
                        <form method="post" class="mark-read">
                            <input type="hidden" name="notificacao_id" value="<?php echo $notificacao['id']; ?>">
                            <button type="submit" name="marcar_lida" title="Marcar como lida">✓</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notifications-footer">
            <a href="notificacoes_pagina.php">Ver todas as notificações</a>
        </div>
    </div>
</div>