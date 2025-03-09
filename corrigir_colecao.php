<?php
require_once 'config.php';

// Verificar se o script está sendo executado via CLI ou navegador
$is_cli = (php_sapi_name() === 'cli');

// Função para exibir mensagens
function exibir($mensagem) {
    global $is_cli;
    if ($is_cli) {
        echo $mensagem . PHP_EOL;
    } else {
        echo $mensagem . '<br>';
    }
}

// Iniciar a correção
exibir("Iniciando correção da tabela colecao...");

try {
    // Iniciar transação
    $pdo->beginTransaction();
    
    // 1. Criar tabela temporária para armazenar os dados agrupados
    exibir("Criando tabela temporária...");
    $pdo->exec("
        CREATE TEMPORARY TABLE temp_colecao AS
        SELECT 
            usuario_id,
            carta_id,
            SUM(quantidade) as quantidade_total,
            MIN(origem) as origem,
            MIN(data_obtencao) as data_obtencao
        FROM colecao
        GROUP BY usuario_id, carta_id
    ");
    
    // 2. Limpar a tabela original
    exibir("Limpando tabela original...");
    $pdo->exec("DELETE FROM colecao");
    
    // 3. Inserir os dados agrupados de volta na tabela original
    exibir("Inserindo dados agrupados...");
    $pdo->exec("
        INSERT INTO colecao (usuario_id, carta_id, quantidade, origem, data_obtencao)
        SELECT 
            usuario_id,
            carta_id,
            quantidade_total,
            origem,
            data_obtencao
        FROM temp_colecao
    ");
    
    // 4. Remover a tabela temporária
    exibir("Removendo tabela temporária...");
    $pdo->exec("DROP TEMPORARY TABLE temp_colecao");
    
    // Confirmar as alterações
    $pdo->commit();
    
    exibir("Correção concluída com sucesso!");
    
} catch (Exception $e) {
    // Reverter em caso de erro
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    exibir("Erro durante a correção: " . $e->getMessage());
}

// Exibir estatísticas
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM colecao");
    $total = $stmt->fetchColumn();
    
    exibir("Total de registros na tabela colecao após a correção: " . $total);
    
    $stmt = $pdo->query("
        SELECT usuario_id, COUNT(*) as total_cartas, SUM(quantidade) as total_quantidade
        FROM colecao
        GROUP BY usuario_id
    ");
    
    exibir("\nEstatísticas por usuário:");
    exibir("------------------------");
    
    while ($row = $stmt->fetch()) {
        exibir("Usuário ID: " . $row['usuario_id'] . 
               " | Cartas únicas: " . $row['total_cartas'] . 
               " | Quantidade total: " . $row['total_quantidade']);
    }
    
} catch (Exception $e) {
    exibir("Erro ao exibir estatísticas: " . $e->getMessage());
}

// Adicionar link para voltar se estiver no navegador
if (!$is_cli) {
    echo '<p><a href="index.php">Voltar para a página inicial</a></p>';
}
?> 