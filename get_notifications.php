<?php
session_start();
require_once 'config.php';
require_once 'notificacoes.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

// Adicionar um pequeno atraso para simular carregamento (opcional, remova em produção)
// usleep(300000); // 300ms

// Buscar notificações não lidas
try {
    $notificacoes = buscarNotificacoesNaoLidas($_SESSION['usuario_id']);
    $total = count($notificacoes);
    
    // Retornar as notificações como JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'total' => $total,
        'notificacoes' => $notificacoes
    ]);
} catch (Exception $e) {
    // Registrar o erro no log do servidor
    error_log('Erro ao buscar notificações: ' . $e->getMessage());
    
    // Retornar mensagem de erro
    header('Content-Type: application/json');
    http_response_code(500); // Definir código de status HTTP
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao buscar notificações: ' . $e->getMessage()
    ]);
}
?> 