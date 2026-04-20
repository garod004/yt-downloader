-- Script para adicionar a coluna senha_gov na tabela filhos_menores
-- Execute este script no seu banco de dados MySQL

ALTER TABLE filhos_menores 
ADD COLUMN senha_gov VARCHAR(255) NULL 
AFTER cpf;

-- Verificar se a coluna foi adicionada corretamente
-- DESCRIBE filhos_menores;
