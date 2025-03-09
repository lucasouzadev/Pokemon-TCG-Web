-- Tabela de notificações
CREATE TABLE IF NOT EXISTS notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    mensagem TEXT NOT NULL,
    lida BOOLEAN DEFAULT FALSE,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- Tabela de eventos especiais
CREATE TABLE IF NOT EXISTS eventos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(100) NOT NULL,
    descricao TEXT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    data_inicio DATETIME NOT NULL,
    data_fim DATETIME NOT NULL,
    imagem VARCHAR(255),
    ativo BOOLEAN DEFAULT TRUE
);

-- Tabela de recompensas de eventos
CREATE TABLE IF NOT EXISTS evento_recompensas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    valor INT NOT NULL,
    descricao VARCHAR(255),
    FOREIGN KEY (evento_id) REFERENCES eventos(id)
);

-- Tabela de participação em eventos
CREATE TABLE IF NOT EXISTS evento_participacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL,
    usuario_id INT NOT NULL,
    data_participacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    recompensa_recebida BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (evento_id) REFERENCES eventos(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY (evento_id, usuario_id)
);

-- Tabela para controlar o limite de trocas diárias
CREATE TABLE IF NOT EXISTS limite_trocas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    data_limite DATE NOT NULL,
    trocas_realizadas INT DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY (usuario_id, data_limite)
);

-- Inserir alguns eventos de exemplo
INSERT INTO eventos (titulo, descricao, tipo, data_inicio, data_fim, imagem, ativo) VALUES
('Festival de Fogo', 'Participe do Festival de Fogo e ganhe cartas de Pokémon do tipo Fogo!', 'festival', 
 NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 'evento-fogo.jpg', TRUE),
('Torneio Relâmpago', 'Participe do Torneio Relâmpago e ganhe pontos extras no ranking!', 'torneio', 
 NOW(), DATE_ADD(NOW(), INTERVAL 3 DAY), 'evento-torneio.jpg', TRUE),
('Caça ao Tesouro', 'Encontre cartas especiais escondidas e ganhe recompensas!', 'caça', 
 DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_ADD(NOW(), INTERVAL 12 DAY), 'evento-tesouro.jpg', TRUE);

-- Inserir recompensas para os eventos
INSERT INTO evento_recompensas (evento_id, tipo, valor, descricao) VALUES
(1, 'carta', 1, 'Uma carta rara de Pokémon do tipo Fogo'),
(1, 'pontos', 20, '20 pontos de ranking'),
(2, 'pontos', 50, '50 pontos de ranking'),
(2, 'carta', 2, 'Duas cartas aleatórias'),
(3, 'carta', 3, 'Três cartas raras'),
(3, 'pontos', 30, '30 pontos de ranking');
