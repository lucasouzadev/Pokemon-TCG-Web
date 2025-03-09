<?php
session_start();
require_once 'config.php';
require_once 'economia.php';
require_once 'notificacoes.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$resposta = ['sucesso' => false, 'mensagem' => '', 'moedas' => 0];

// Verificar se a requisição é POST e se o ID da carta foi fornecido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['carta_id'])) {
    $carta_id = (int)$_POST['carta_id'];
    $colecao_id = isset($_POST['colecao_id']) ? (int)$_POST['colecao_id'] : null;
    
    // Verificar se já existe uma transação ativa antes de iniciar uma nova
    $transaction_active = false;
    try {
        $transaction_active = $pdo->inTransaction();
    } catch (Exception $e) {
        // Ignorar erro e assumir que não há transação ativa
    }
    
    // Iniciar transação apenas se não houver uma ativa
    if (!$transaction_active) {
        $pdo->beginTransaction();
    }
    
    try {
        // Verificar se o usuário possui a carta e se tem mais de uma
        if ($colecao_id) {
            $stmt = $pdo->prepare("SELECT c.*, co.quantidade, co.id as colecao_id FROM colecao co JOIN cartas c ON co.carta_id = c.id WHERE co.id = ? AND co.usuario_id = ?");
            $stmt->execute([$colecao_id, $usuario_id]);
        } else {
            $stmt = $pdo->prepare("SELECT c.*, co.quantidade, co.id as colecao_id FROM colecao co JOIN cartas c ON co.carta_id = c.id WHERE co.carta_id = ? AND co.usuario_id = ?");
            $stmt->execute([$carta_id, $usuario_id]);
        }
        
        $carta = $stmt->fetch();
        
        if (!$carta) {
            throw new Exception("Carta não encontrada na sua coleção.");
        }
        
        if ($carta['quantidade'] <= 1) {
            throw new Exception("Você precisa ter pelo menos 2 cartas para vender uma.");
        }
        
        // Calcular valor da carta com base na raridade
        $valor = 0;
        switch (strtolower($carta['raridade'])) {
            case 'comum':
                $valor = 5;
                break;
            case 'incomum':
                $valor = 10;
                break;
            case 'rara':
                $valor = 20;
                break;
            case 'ultra rara':
                $valor = 50;
                break;
            default:
                $valor = 5;
        }
        
        // Reduzir a quantidade da carta na coleção
        $stmt = $pdo->prepare("UPDATE colecao SET quantidade = quantidade - 1 WHERE id = ?");
        $stmt->execute([$carta['colecao_id']]);
        
        // Adicionar moedas ao usuário
        if (!adicionarMoedas($usuario_id, 'comum', $valor, "Venda de carta: " . $carta['nome'])) {
            throw new Exception("Erro ao adicionar moedas.");
        }
        
        // Obter saldo atualizado
        $saldo = obterSaldoMoedas($usuario_id);
        
        // Adicionar notificação
        adicionarNotificacao(
            $usuario_id,
            'economia',
            "Você vendeu uma carta {$carta['nome']} e recebeu {$valor} moedas comuns."
        );
        
        // Confirmar transação apenas se nós a iniciamos
        if (!$transaction_active) {
            $pdo->commit();
        }
        
        $resposta = [
            'sucesso' => true,
            'mensagem' => "Carta vendida com sucesso! Você recebeu {$valor} moedas comuns.",
            'moedas' => $saldo['moedas_comuns']
        ];
    } catch (Exception $e) {
        // Reverter transação apenas se nós a iniciamos
        if (!$transaction_active && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $resposta = [
            'sucesso' => false,
            'mensagem' => $e->getMessage()
        ];
    }
}

// Retornar resposta em JSON
header('Content-Type: application/json');
echo json_encode($resposta); 