-- Adicionar colunas de avaliação teórica e prática à tabela evaluations
-- Execute este script no seu banco de dados MySQL

ALTER TABLE evaluations 
ADD COLUMN IF NOT EXISTS score_teorica DECIMAL(3,1) DEFAULT 0 AFTER score_learning,
ADD COLUMN IF NOT EXISTS score_pratica DECIMAL(3,1) DEFAULT 0 AFTER score_teorica;

-- Verificar se as colunas foram adicionadas
SHOW COLUMNS FROM evaluations WHERE Field IN ('score_teorica', 'score_pratica');
