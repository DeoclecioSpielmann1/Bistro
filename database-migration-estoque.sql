-- Execute uma unica vez no banco existente, com o banco bistro_devops selecionado.
ALTER TABLE produtos
  ADD COLUMN estoque INT UNSIGNED NOT NULL DEFAULT 0 AFTER preco;
