<?php
session_start();
require_once 'config.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

// Verificar se o ID da carta foi fornecido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID da carta não fornecido']);
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$carta_id = $_GET['id'];

// Verificar se foi fornecido um ID de coleção específico
if (isset($_GET['colecao_id']) && is_numeric($_GET['colecao_id'])) {
    // Buscar informações da carta com o ID de coleção específico
    $stmt = $pdo->prepare("
        SELECT c.*, co.id as colecao_id, co.quantidade, co.origem, co.data_obtencao 
        FROM cartas c 
        JOIN colecao co ON c.id = co.carta_id 
        WHERE c.id = ? AND co.usuario_id = ? AND co.id = ?
    ");
    $stmt->execute([$carta_id, $usuario_id, $_GET['colecao_id']]);
} else {
    // Buscar informações da carta com a quantidade total
    $stmt = $pdo->prepare("
        SELECT c.*, MIN(co.id) as colecao_id, SUM(co.quantidade) as quantidade, 
               MIN(co.origem) as origem, MIN(co.data_obtencao) as data_obtencao 
        FROM cartas c 
        JOIN colecao co ON c.id = co.carta_id 
        WHERE c.id = ? AND co.usuario_id = ? AND co.quantidade > 0
        GROUP BY c.id
    ");
    $stmt->execute([$carta_id, $usuario_id]);
}

$carta = $stmt->fetch(PDO::FETCH_ASSOC);

// Se a carta não existir, retornar erro
if (!$carta) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Carta não encontrada']);
    exit;
}

// Retornar os dados da carta como JSON
header('Content-Type: application/json');
echo json_encode($carta);
?> 