<?php
session_start();
require_once 'config.php';
require_once 'notificacoes.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    http_response_code(401); // Não autorizado
    echo json_encode(['success' => false, 'error' => 'Usuário não autenticado']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$response = ['success' => false];

try {
    // Marcar uma notificação específica como lida
    if (isset($_POST['marcar_lida']) && isset($_POST['notificacao_id'])) {
        $notificacao_id = (int)$_POST['notificacao_id'];
        $success = marcarNotificacaoComoLida($notificacao_id, $usuario_id);
        
        $response = [
            'success' => $success,
            'message' => $success ? 'Notificação marcada como lida' : 'Erro ao marcar notificação'
        ];
    }

    // Marcar todas as notificações como lidas
    if (isset($_POST['marcar_todas_lidas'])) {
        $count = marcarTodasNotificacoesComoLidas($usuario_id);
        
        $response = [
            'success' => true,
            'count' => $count,
            'message' => 'Todas as notificações foram marcadas como lidas'
        ];
    }
} catch (Exception $e) {
    // Registrar o erro no log do servidor
    error_log('Erro ao processar notificações: ' . $e->getMessage());
    
    // Retornar mensagem de erro
    $response = [
        'success' => false,
        'error' => 'Erro ao processar a solicitação: ' . $e->getMessage()
    ];
    http_response_code(500); // Definir código de status HTTP
}

// Retornar resposta como JSON
header('Content-Type: application/json');
echo json_encode($response);
?> 