ALTER TABLE controle_acesso 
ADD COLUMN id_alterado INT NULL AFTER descricao,
ADD COLUMN nome_alterado VARCHAR(255) NULL AFTER id_alterado;