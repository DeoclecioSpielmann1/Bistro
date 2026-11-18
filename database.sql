-- Bistrô Sabor da Vila — esquema reconstruído a partir das consultas do código.
-- Modelo lógico: produtos 1—N itens_comanda N—1 comandas.
-- Em hospedagem (InfinityFree) remova as 2 linhas abaixo (CREATE DATABASE / USE)
-- e importe com o banco já selecionado no phpMyAdmin.
CREATE DATABASE IF NOT EXISTS bistro_devops CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bistro_devops;

CREATE TABLE IF NOT EXISTS produtos (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome       VARCHAR(120) NOT NULL,
  descricao  VARCHAR(255) NULL,
  categoria  VARCHAR(60)  NOT NULL,
  preco      DECIMAL(10,2) NOT NULL,
  estoque    INT UNSIGNED NOT NULL DEFAULT 0,
  ativo      TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_produtos_nome (nome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comandas (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  numero        INT UNSIGNED NULL,                       -- preenchido com o próprio id logo após o INSERT
  status        ENUM('aberta','entregue') NOT NULL DEFAULT 'aberta',
  observacao    TEXT NULL,
  data_abertura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_entrega  DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_comandas_numero (numero),
  KEY idx_comandas_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS itens_comanda (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  comanda_id     INT UNSIGNED NOT NULL,
  produto_id     INT UNSIGNED NOT NULL,
  nome_produto   VARCHAR(120) NOT NULL,                  -- cópia histórica do nome
  quantidade     SMALLINT UNSIGNED NOT NULL,
  preco_unitario DECIMAL(10,2) NOT NULL,                 -- cópia histórica do preço
  PRIMARY KEY (id),
  KEY idx_itens_comanda (comanda_id),
  KEY idx_itens_produto (produto_id),
  CONSTRAINT fk_itens_comanda FOREIGN KEY (comanda_id) REFERENCES comandas (id) ON DELETE CASCADE,
  CONSTRAINT fk_itens_produto FOREIGN KEY (produto_id) REFERENCES produtos (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO produtos (nome, descricao, categoria, preco, ativo, estoque) VALUES
('Prato feito do dia',       'Arroz, feijão, mistura e salada',      'Pratos feitos', 24.90, 1, 20),
('Lasanha à bolonhesa',      'Porção individual gratinada',          'Pratos feitos', 32.00, 1, 20),
('Filé de frango grelhado',  'Acompanha arroz e legumes',            'Pratos feitos', 28.50, 1, 20),
('Feijoada completa',        'Servida aos sábados',                  'Pratos feitos', 38.00, 1, 20),
('Porção de batata frita',   'Porção média',                         'Petiscos',      22.00, 1, 20),
('Pastel de queijo',         'Unidade',                              'Petiscos',       9.50, 1, 20),
('Suco natural',             'Laranja, limão ou abacaxi (400 ml)',   'Bebidas',        9.00, 1, 20),
('Refrigerante lata',        '350 ml',                               'Bebidas',        6.50, 1, 20),
('Café coado',               'Xícara 150 ml',                        'Bebidas',        4.50, 1, 20),
('Pudim de leite',           'Fatia individual',                     'Sobremesas',    12.00, 1, 20);
