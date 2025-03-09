<?php
// Verificar se a sessão já está ativa antes de iniciar
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o arquivo config.php já foi incluído
if (!isset($pdo)) {
    require_once 'config.php';
}

// Funções para gerenciar notificações

// Adicionar uma notificação
function adicionarNotificacao($usuario_id, $tipo, $mensagem) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO notificacoes (usuario_id, tipo, mensagem) VALUES (?, ?, ?)");
    $stmt->execute([(int)$usuario_id, $tipo, $mensagem]);
    
    return $pdo->lastInsertId();
}

// Marcar notificação como lida
function marcarNotificacaoComoLida($notificacao_id, $usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE notificacoes SET lida = TRUE WHERE id = ? AND usuario_id = ?");
    $stmt->execute([(int)$notificacao_id, (int)$usuario_id]);
    
    return $stmt->rowCount() > 0;
}

// Marcar todas as notificações como lidas
function marcarTodasNotificacoesComoLidas($usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE notificacoes SET lida = TRUE WHERE usuario_id = ?");
    $stmt->execute([(int)$usuario_id]);
    
    return $stmt->rowCount();
}

// Buscar notificações não lidas
function buscarNotificacoesNaoLidas($usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM notificacoes 
        WHERE usuario_id = ? AND lida = FALSE 
        ORDER BY data_criacao DESC
    ");
    $stmt->execute([(int)$usuario_id]);
    
    return $stmt->fetchAll();
}

// Buscar todas as notificações
function buscarTodasNotificacoes($usuario_id, $limite = 20) {
    global $pdo;
    
    // Converter o limite para inteiro
    $limite = (int)$limite;
    
    // Usar a consulta com o limite diretamente na string SQL
    $stmt = $pdo->prepare("
        SELECT * FROM notificacoes 
        WHERE usuario_id = ? 
        ORDER BY data_criacao DESC
        LIMIT $limite
    ");
    $stmt->execute([(int)$usuario_id]);
    
    return $stmt->fetchAll();
}

// Contar notificações não lidas
function contarNotificacoesNaoLidas($usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM notificacoes WHERE usuario_id = ? AND lida = FALSE");
    $stmt->execute([(int)$usuario_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $resultado ? (int)$resultado['total'] : 0;
}

// Excluir notificação
function excluirNotificacao($notificacao_id, $usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM notificacoes WHERE id = ? AND usuario_id = ?");
    $stmt->execute([(int)$notificacao_id, (int)$usuario_id]);
    
    return $stmt->rowCount() > 0;
}

// Excluir todas as notificações lidas
function excluirNotificacoesLidas($usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM notificacoes WHERE usuario_id = ? AND lida = TRUE");
    $stmt->execute([(int)$usuario_id]);
    
    return $stmt->rowCount();
}