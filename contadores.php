<?php
/**
 * Funções para calcular contadores de notificações para diferentes seções do site
 */

// Verificar se a sessão já está ativa antes de iniciar
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar se o arquivo config.php já foi incluído
if (!isset($pdo)) {
    require_once 'config.php';
}

/**
 * Conta o número de solicitações de troca pendentes para o usuário
 * 
 * @param int $usuario_id ID do usuário
 * @param PDO $pdo_conn Conexão com o banco de dados (opcional)
 * @return int Número de trocas pendentes
 */
function contarTrocasPendentes($usuario_id, $pdo_conn = null) {
    global $pdo;
    $conn = $pdo_conn ?: $pdo;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM trocas 
        WHERE usuario_destino_id = ? AND status = 'pendente'
    ");
    $stmt->execute([(int)$usuario_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? (int)$resultado['total'] : 0;
}

/**
 * Conta o número de eventos ativos disponíveis para participação
 * 
 * @param int $usuario_id ID do usuário
 * @param PDO $pdo_conn Conexão com o banco de dados (opcional)
 * @return int Número de eventos disponíveis
 */
function contarEventosDisponiveis($usuario_id, $pdo_conn = null) {
    global $pdo;
    $conn = $pdo_conn ?: $pdo;
    
    $data_atual = date('Y-m-d H:i:s');
    
    // Buscar eventos ativos que o usuário ainda não participou
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM eventos 
        WHERE data_inicio <= ? 
        AND data_fim >= ? 
        AND id NOT IN (
            SELECT evento_id FROM evento_participacoes WHERE usuario_id = ?
        )
    ");
    $stmt->execute([$data_atual, $data_atual, (int)$usuario_id]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? (int)$resultado['total'] : 0;
}

/**
 * Conta o número de cartas novas adquiridas desde a última visita à coleção
 * 
 * @param int $usuario_id ID do usuário
 * @param PDO $pdo_conn Conexão com o banco de dados (opcional)
 * @return int Número de cartas novas
 */
function contarCartasNovas($usuario_id, $pdo_conn = null) {
    global $pdo;
    $conn = $pdo_conn ?: $pdo;
    
    // Buscar a última visita do usuário à página de coleção
    $stmt = $conn->prepare("
        SELECT ultima_visita_colecao 
        FROM usuarios 
        WHERE id = ?
    ");
    $stmt->execute([(int)$usuario_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Se nunca visitou, retornar 0
    if (!$usuario || !$usuario['ultima_visita_colecao']) {
        return 0;
    }
    
    // Contar cartas adquiridas após a última visita
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total 
        FROM colecao 
        WHERE usuario_id = ? AND data_obtencao > ?
    ");
    $stmt->execute([(int)$usuario_id, $usuario['ultima_visita_colecao']]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? (int)$resultado['total'] : 0;
}

/**
 * Atualiza a data da última visita do usuário à página de coleção
 * 
 * @param int $usuario_id ID do usuário
 * @param PDO $pdo_conn Conexão com o banco de dados (opcional)
 * @return bool Sucesso da operação
 */
function atualizarUltimaVisitaColecao($usuario_id, $pdo_conn = null) {
    global $pdo;
    $conn = $pdo_conn ?: $pdo;
    
    $stmt = $conn->prepare("
        UPDATE usuarios 
        SET ultima_visita_colecao = NOW() 
        WHERE id = ?
    ");
    return $stmt->execute([(int)$usuario_id]);
}

/**
 * Carrega todos os contadores de uma vez
 * 
 * @param int $usuario_id ID do usuário
 * @param PDO $pdo_conn Conexão com o banco de dados (opcional)
 * @return array Array com todos os contadores
 */
function carregarContadores($usuario_id, $pdo_conn = null) {
    return [
        'trocas_pendentes' => contarTrocasPendentes($usuario_id, $pdo_conn),
        'eventos_disponiveis' => contarEventosDisponiveis($usuario_id, $pdo_conn),
        'cartas_novas' => contarCartasNovas($usuario_id, $pdo_conn)
    ];
}
?> 