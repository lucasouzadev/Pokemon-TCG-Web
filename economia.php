<?php
require_once 'config.php';

/**
 * Adiciona moedas a um usuário
 */
function adicionarMoedas($usuario_id, $tipo_moeda, $quantidade, $motivo) {
    global $pdo;
    
    // Verificar tipo de moeda
    if ($tipo_moeda != 'comum' && $tipo_moeda != 'premium') {
        return false;
    }
    
    // Verificar se já existe uma transação ativa
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
        // Atualizar saldo do usuário
        $coluna = ($tipo_moeda == 'comum') ? 'moedas_comuns' : 'moedas_premium';
        $stmt = $pdo->prepare("UPDATE usuarios SET $coluna = $coluna + ? WHERE id = ?");
        $stmt->execute([$quantidade, $usuario_id]);
        
        // Registrar transação
        $stmt = $pdo->prepare("
            INSERT INTO transacoes_moedas (usuario_id, tipo_moeda, quantidade, motivo)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$usuario_id, $tipo_moeda, $quantidade, $motivo]);
        
        // Confirmar transação apenas se nós a iniciamos
        if (!$transaction_active) {
            $pdo->commit();
        }
        return true;
    } catch (Exception $e) {
        // Reverter transação apenas se nós a iniciamos
        if (!$transaction_active && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

/**
 * Remove moedas de um usuário (para compras)
 */
function removerMoedas($usuario_id, $tipo_moeda, $quantidade, $descricao = '') {
    global $pdo;
    
    try {
        // DEBUG: Início da função
        error_log("Iniciando removerMoedas para usuário $usuario_id, tipo $tipo_moeda, quantidade $quantidade");
        
        // Verificar se já existe uma transação ativa
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
        
        // Verificar saldo atual
        $stmt = $pdo->prepare("SELECT moedas_comuns, moedas_premium FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        
        if (!$usuario) {
            error_log("Usuário não encontrado");
            if (!$transaction_active && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
        
        // DEBUG: Saldo atual
        error_log("Saldo atual: Comum = {$usuario['moedas_comuns']}, Premium = {$usuario['moedas_premium']}");
        
        // Verificar se tem saldo suficiente
        if ($tipo_moeda == 'comum' && $usuario['moedas_comuns'] < $quantidade) {
            error_log("Saldo comum insuficiente");
            if (!$transaction_active && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        } else if ($tipo_moeda == 'premium' && $usuario['moedas_premium'] < $quantidade) {
            error_log("Saldo premium insuficiente");
            if (!$transaction_active && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return false;
        }
        
        // Atualizar saldo
        if ($tipo_moeda == 'comum') {
            $stmt = $pdo->prepare("UPDATE usuarios SET moedas_comuns = moedas_comuns - ? WHERE id = ?");
        } else {
            $stmt = $pdo->prepare("UPDATE usuarios SET moedas_premium = moedas_premium - ? WHERE id = ?");
        }
        
        $stmt->execute([$quantidade, $usuario_id]);
        
        // Verificar se a tabela transacoes_moedas existe
        try {
            // Registrar transação
            $stmt = $pdo->prepare("
                INSERT INTO transacoes_moedas (usuario_id, tipo, quantidade, tipo_moeda, descricao, data_transacao)
                VALUES (?, 'saida', ?, ?, ?, NOW())
            ");
            $stmt->execute([$usuario_id, $quantidade, $tipo_moeda, $descricao]);
        } catch (Exception $e) {
            // Se a tabela não existir, apenas ignorar este erro
            error_log("Aviso: Não foi possível registrar a transação: " . $e->getMessage());
            // Mas continuar com a transação principal
        }
        
        // Confirmar transação apenas se nós a iniciamos
        if (!$transaction_active) {
            $pdo->commit();
        }
        error_log("Transação concluída com sucesso");
        return true;
        
    } catch (Exception $e) {
        // Reverter em caso de erro, apenas se nós iniciamos a transação
        if (!$transaction_active && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro na função removerMoedas: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtém o saldo de moedas de um usuário
 */
function obterSaldoMoedas($usuario_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT moedas_comuns, moedas_premium FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Obtém o histórico de transações de moedas de um usuário
 */
function obterHistoricoMoedas($usuario_id, $limite = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM transacoes_moedas 
        WHERE usuario_id = ? 
        ORDER BY data_transacao DESC 
        LIMIT ?
    ");
    $stmt->execute([$usuario_id, $limite]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?> 