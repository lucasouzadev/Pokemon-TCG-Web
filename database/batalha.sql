-- Adicionar novos campos à tabela de cartas
ALTER TABLE cartas
ADD COLUMN recuo INT DEFAULT 1 COMMENT 'Custo de energia para recuar',
ADD COLUMN fraqueza VARCHAR(20) DEFAULT NULL COMMENT 'Tipo contra o Pokémon é fraco',
ADD COLUMN resistencia VARCHAR(20) DEFAULT NULL COMMENT 'Tipo contra o Pokémon é resistente',
ADD COLUMN estagio VARCHAR(20) DEFAULT 'Básico' COMMENT 'Estágio do Pokémon (Básico, Estágio 1, Estágio 2)',
ADD COLUMN evolui_de INT DEFAULT NULL COMMENT 'ID da carta da qual este Pokémon evolui';

-- Criar tabela de ataques
CREATE TABLE ataques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carta_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    dano INT DEFAULT 0 COMMENT 'Dano base do ataque',
    custo_energia JSON COMMENT 'JSON com tipos e quantidades de energia necessárias',
    efeitos JSON COMMENT 'Efeitos especiais do ataque em formato JSON',
    FOREIGN KEY (carta_id) REFERENCES cartas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar tabela de habilidades (passivas)
CREATE TABLE habilidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carta_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    tipo_gatilho VARCHAR(50) COMMENT 'Quando a habilidade é ativada (início do turno, ao receber dano, etc.)',
    efeitos JSON COMMENT 'Efeitos da habilidade em formato JSON',
    FOREIGN KEY (carta_id) REFERENCES cartas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar tabela de energias
CREATE TABLE energias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(50) NOT NULL COMMENT 'Tipo de energia (Fogo, Água, etc.)',
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    imagem VARCHAR(255) COMMENT 'Caminho para a imagem da carta de energia',
    efeito_especial JSON DEFAULT NULL COMMENT 'Efeitos especiais para energias especiais'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar tabela de batalhas (reformulada para sistema de turnos)
CREATE TABLE IF NOT EXISTS batalhas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    oponente_id INT COMMENT 'ID do oponente (usuário ou NPC)',
    oponente_tipo ENUM('npc', 'jogador') DEFAULT 'npc',
    jogador_atual INT COMMENT 'ID do jogador que está jogando no momento',
    turno_atual INT DEFAULT 1,
    dados_batalha JSON COMMENT 'Estado atual da batalha (cartas em jogo, mão, etc.)',
    status ENUM('aguardando', 'em_andamento', 'concluida', 'cancelada') DEFAULT 'aguardando',
    vencedor_id INT DEFAULT NULL,
    data_inicio DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_fim DATETIME DEFAULT NULL,
    ultima_acao DATETIME DEFAULT CURRENT_TIMESTAMP,
    log_batalha JSON COMMENT 'Registro de todas as ações da batalha',
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar tabela para cartas em jogo durante a batalha
CREATE TABLE cartas_em_jogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batalha_id INT NOT NULL,
    carta_id INT NOT NULL,
    usuario_id INT NOT NULL,
    posicao ENUM('ativo', 'banco', 'mao', 'deck', 'descarte') NOT NULL,
    hp_atual INT COMMENT 'HP atual da carta em jogo',
    status JSON COMMENT 'Status atuais (envenenado, confuso, etc.)',
    energias_anexadas JSON COMMENT 'Energias anexadas à carta',
    ordem INT DEFAULT 0 COMMENT 'Ordem na posição (importante para o banco)',
    FOREIGN KEY (batalha_id) REFERENCES batalhas(id) ON DELETE CASCADE,
    FOREIGN KEY (carta_id) REFERENCES cartas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar tabela para ações de batalha
CREATE TABLE acoes_batalha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    batalha_id INT NOT NULL,
    usuario_id INT NOT NULL,
    turno INT NOT NULL,
    tipo_acao ENUM('ataque', 'recuo', 'evolucao', 'energia', 'item', 'habilidade', 'passar') NOT NULL,
    carta_origem_id INT COMMENT 'Carta que realizou a ação',
    carta_alvo_id INT COMMENT 'Carta que recebeu a ação',
    ataque_id INT DEFAULT NULL,
    habilidade_id INT DEFAULT NULL,
    energia_id INT DEFAULT NULL,
    detalhes JSON COMMENT 'Detalhes adicionais da ação',
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (batalha_id) REFERENCES batalhas(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Criar tabela para decks de batalha
CREATE TABLE decks_batalha (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    descricao TEXT,
    cartas JSON NOT NULL COMMENT 'Lista de IDs de cartas no deck',
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultima_modificacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserir tipos de energia básicos
INSERT INTO energias (tipo, nome, descricao, imagem) VALUES
('Fogo', 'Energia de Fogo', 'Energia básica do tipo Fogo', 'energia_fogo.png'),
('Água', 'Energia de Água', 'Energia básica do tipo Água', 'energia_agua.png'),
('Planta', 'Energia de Planta', 'Energia básica do tipo Planta', 'energia_planta.png'),
('Elétrico', 'Energia Elétrica', 'Energia básica do tipo Elétrico', 'energia_eletrico.png'),
('Psíquico', 'Energia Psíquica', 'Energia básica do tipo Psíquico', 'energia_psiquico.png'),
('Lutador', 'Energia de Lutador', 'Energia básica do tipo Lutador', 'energia_lutador.png'),
('Normal', 'Energia Normal', 'Energia básica do tipo Normal', 'energia_normal.png'),
('Metal', 'Energia Metálica', 'Energia básica do tipo Metal', 'energia_metal.png'),
('Escuridão', 'Energia das Trevas', 'Energia básica do tipo Escuridão', 'energia_escuridao.png'),
('Fada', 'Energia de Fada', 'Energia básica do tipo Fada', 'energia_fada.png'),
('Incolor', 'Energia Incolor', 'Energia que pode ser usada como qualquer tipo', 'energia_incolor.png');
