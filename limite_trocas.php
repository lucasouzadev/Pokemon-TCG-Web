<?php
// Verificar se a sessão já está ativa antes de iniciar
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o arquivo config.php já foi incluído
if (!isset($pdo)) {
    require_once 'config.php';
}

/**
 * Verifica se o usuário atingiu o limite de trocas diárias
 * @param int $usuario_id ID do usuário
 * @return array Informações sobre o limite de trocas
 */
function verificarLimiteTrocas($usuario_id) {
    global $pdo;
    
    $data_atual = date('Y-m-d');
    $limite_diario = 3; // Limite de 3 trocas por dia
    
    // Verificar se já existe um registro para hoje
    $stmt = $pdo->prepare("SELECT * FROM limite_trocas WHERE usuario_id = ? AND data_limite = ?");
    $stmt->execute([$usuario_id, $data_atual]);
    $limite = $stmt->fetch();
    
    if (!$limite) {
        // Criar um novo registro para hoje
        $stmt = $pdo->prepare("INSERT INTO limite_trocas (usuario_id, data_limite, trocas_realizadas) VALUES (?, ?, 0)");
        $stmt->execute([$usuario_id, $data_atual]);
        
        $limite = [
            'trocas_realizadas' => 0,
            'limite_diario' => $limite_diario,
            'trocas_restantes' => $limite_diario
        ];
    } else {
        $limite['limite_diario'] = $limite_diario;
        $limite['trocas_restantes'] = $limite_diario - $limite['trocas_realizadas'];
    }
    
    return $limite;
}

/**
 * Verifica se o usuário pode realizar uma nova troca
 * @param int $usuario_id ID do usuário
 * @return bool True se pode realizar troca, False caso contrário
 */
function podeTrocar($usuario_id) {
    $limite = verificarLimiteTrocas($usuario_id);
    
    // Verificar se o usuário já tem trocas pendentes
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as trocas_pendentes 
        FROM trocas 
        WHERE (usuario_origem_id = ? OR usuario_destino_id = ?) 
        AND status = 'pendente'
    ");
    $stmt->execute([$usuario_id, $usuario_id]);
    $resultado = $stmt->fetch();
    
    // Não pode trocar se já tiver trocas pendentes ou se atingiu o limite diário
    if ($resultado['trocas_pendentes'] > 0 || $limite['trocas_restantes'] <= 0) {
        return false;
    }
    
    return true;
}

/**
 * Incrementa o contador de trocas realizadas
 * @param int $usuario_id ID do usuário
 * @return bool True se sucesso, False caso contrário
 */
function incrementarTrocasRealizadas($usuario_id) {
    global $pdo;
    
    $data_atual = date('Y-m-d');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE limite_trocas 
            SET trocas_realizadas = trocas_realizadas + 1 
            WHERE usuario_id = ? AND data_limite = ?
        ");
        $stmt->execute([$usuario_id, $data_atual]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtém informações sobre trocas pendentes do usuário
 * @param int $usuario_id ID do usuário
 * @return array Informações sobre trocas pendentes ou null se não houver
 */
function obterTrocaPendente($usuario_id) {
    global $pdo;
    
    // Verificar se o usuário tem trocas pendentes como origem
    $stmt = $pdo->prepare("
        SELECT t.*, u.nome as usuario_nome
        FROM trocas t
        JOIN usuarios u ON t.usuario_destino_id = u.id
        WHERE t.usuario_origem_id = ? AND t.status = 'pendente'
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $troca_origem = $stmt->fetch();
    
    if ($troca_origem) {
        return [
            'tipo' => 'enviada',
            'troca' => $troca_origem,
            'usuario_nome' => $troca_origem['usuario_nome']
        ];
    }
    
    // Verificar se o usuário tem trocas pendentes como destino
    $stmt = $pdo->prepare("
        SELECT t.*, u.nome as usuario_nome
        FROM trocas t
        JOIN usuarios u ON t.usuario_origem_id = u.id
        WHERE t.usuario_destino_id = ? AND t.status = 'pendente'
        LIMIT 1
    ");
    $stmt->execute([$usuario_id]);
    $troca_destino = $stmt->fetch();
    
    if ($troca_destino) {
        return [
            'tipo' => 'recebida',
            'troca' => $troca_destino,
            'usuario_nome' => $troca_destino['usuario_nome']
        ];
    }
    
    return null;
}
?> 