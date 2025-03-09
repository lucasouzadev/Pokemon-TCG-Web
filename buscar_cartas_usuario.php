<?php
session_start();
require_once 'config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Usuário não autenticado']);
    exit;
}

// Verificar se o ID do usuário foi fornecido
if (!isset($_GET['usuario_id']) || empty($_GET['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'ID do usuário não fornecido']);
    exit;
}

$usuario_id = (int)$_GET['usuario_id'];

// Buscar cartas do usuário
try {
    $stmt = $pdo->prepare("
        SELECT c.*, MIN(co.id) as colecao_id, SUM(co.quantidade) as quantidade, 
               MIN(co.origem) as origem, MIN(co.data_obtencao) as data_obtencao
        FROM colecao co 
        JOIN cartas c ON co.carta_id = c.id 
        WHERE co.usuario_id = ? AND co.quantidade > 0
        GROUP BY c.id
        ORDER BY c.raridade DESC, c.nome
    ");
    $stmt->execute([$usuario_id]);
    $cartas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Buscar nome do usuário
    $stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $resposta = [
        'sucesso' => true,
        'usuario' => $usuario ? $usuario['nome'] : 'Usuário desconhecido',
        'cartas' => $cartas
    ];
    
    header('Content-Type: application/json');
    echo json_encode($resposta);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['erro' => 'Erro ao buscar cartas: ' . $e->getMessage()]);
}
?> 