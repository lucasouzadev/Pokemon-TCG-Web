-- Tabela de estatísticas de batalha
CREATE TABLE IF NOT EXISTS estatisticas_batalha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    vitorias INT DEFAULT 0,
    derrotas INT DEFAULT 0,
    empates INT DEFAULT 0,
    cartas_ganhas INT DEFAULT 0,
    ultima_batalha DATETIME,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de ranking
CREATE TABLE IF NOT EXISTS ranking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    pontuacao INT DEFAULT 0,
    nivel INT DEFAULT 1,
    posicao INT,
    ultima_atualizacao DATETIME,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de conquistas
CREATE TABLE IF NOT EXISTS conquistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT NOT NULL,
    icone VARCHAR(255) NOT NULL,
    pontos INT DEFAULT 10
);

-- Tabela de conquistas do usuário
CREATE TABLE IF NOT EXISTS usuario_conquistas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    conquista_id INT NOT NULL,
    data_obtida DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (conquista_id) REFERENCES conquistas(id),
    UNIQUE KEY (usuario_id, conquista_id)
);

-- Inserir algumas conquistas básicas
INSERT INTO conquistas (nome, descricao, icone, pontos) VALUES
('Colecionador Iniciante', 'Colete 10 cartas diferentes', 'badge-collector-1.png', 10),
('Colecionador Avançado', 'Colete 30 cartas diferentes', 'badge-collector-2.png', 30),
('Mestre Colecionador', 'Colete 50 cartas diferentes', 'badge-collector-3.png', 50),
('Primeiro Sangue', 'Vença sua primeira batalha', 'badge-battle-1.png', 10),
('Guerreiro', 'Vença 10 batalhas', 'badge-battle-2.png', 20),
('Campeão', 'Vença 50 batalhas', 'badge-battle-3.png', 50),
('Negociante', 'Complete sua primeira troca', 'badge-trade-1.png', 10),
('Mercador', 'Complete 10 trocas', 'badge-trade-2.png', 30),
('Magnata', 'Complete 30 trocas', 'badge-trade-3.png', 50);
