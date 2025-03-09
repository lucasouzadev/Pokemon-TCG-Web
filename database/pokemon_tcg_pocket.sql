-- Criação do banco de dados
CREATE DATABASE IF NOT EXISTS pokemon_tcg_pocket;
USE pokemon_tcg_pocket;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Tabela de coleção (relação entre usuários e cartas)
CREATE TABLE IF NOT EXISTS colecao (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    carta_id INT NOT NULL,
    quantidade INT DEFAULT 1,
    origem INT NOT NULL,
    data_obtencao DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (carta_id) REFERENCES cartas(id)
);

-- Tabela de cartas
CREATE TABLE IF NOT EXISTS cartas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    raridade VARCHAR(50) NOT NULL,
    hp INT NOT NULL,
    imagem VARCHAR(255) NOT NULL,
    descricao TEXT
);

-- Inserir algumas cartas de exemplo
INSERT INTO cartas (nome, tipo, raridade, hp, imagem, descricao) VALUES
('Pikachu', 'Elétrico', 'Ultra Rara', 120, 'pikachu-ultra-rara.jpg', 'Rato eletrico ultra raro'),
('Pikachu', 'Elétrico', 'Comum', 60, 'pikachu.jpg', 'Quando fica irritado, ele imediatamente descarrega a energia armazenada nas bolsas em suas bochechas.\nIlustrado por Mitsuhiro Arita'),
('Charmander', 'Fogo', 'Comum', 60, 'charmander.jpg', 'Pokémon de chama'),
('Bulbasaur', 'Planta', 'Incomum', 70, 'bulbasaur.jpg', 'Pokémon de planta'),
('Squirtle', 'Água', 'comum', 60, 'squirtle.jpg', 'Pokémon de água'),
('Charizard', 'Fogo', 'Ultra Rara', 180, 'charizard-ultra-rara.jpg', 'Pokémon de chama'),
('Charizard', 'Fogo', 'Incomum', 120, 'charizard.jpg', 'Pokémon de chama'),
('Blastoise', 'Água', 'Rara', 150, 'blastoise.jpg', 'Pokémon Marisco'),
('Blastoise', 'Água', 'Ultra Rara', 180, 'blastoise-ultra-rara.jpg', 'Pokémon Marisco'),
('Venusaur', 'Planta', 'Rara', 190, 'venusaur.jpg', 'Pokémon Semente'),
('Venusaur', 'Planta', 'Ultra Rara', 220, 'venusaur-ultra-rara.jpg', 'Pokémon Semente'),
('Mewtwo', 'Psíquico', 'Rara', 120, 'mewtwo.jpg', 'Pokémon Genético'),
('Mewtwo', 'Psíquico', 'Ultra Rara', 150, 'mewtwo-ultra-rara.jpg', 'Pokémon Genético'),
('Gengar', 'Fantasma', 'Rara', 170, 'gengar.jpg', 'Pokémon Sombra'),
('Gengar', 'Fantasma', 'Ultra Rara', 170, 'gengar-ultra-rara.jpg', 'Pokémon Sombra'),
('Gyarados', 'Água', 'Incomum', 130, 'gyarados.jpg', 'Pokémon Atrocidade'),
('Gyarados', 'Água', 'Ultra Rara', 180, 'gyarados-ultra-rara.jpg', 'Pokémon Atrocidade'),
('Snorlax', 'Normal', 'Comum', 150, 'snorlax.jpg', 'Pokémon Dorminhoco'),
('Snorlax', 'Normal', 'Incomum', 150, 'snorlax-incomum.jpg', 'Pokémon Dorminhoco');
