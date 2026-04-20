-- Cria a tabela de logs de acesso e alteração
CREATE TABLE IF NOT EXISTS controle_acesso (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(100) NOT NULL,
    acao VARCHAR(50) NOT NULL, -- 'acesso' ou 'alteracao'
    descricao TEXT, -- descrição da ação ou alteração
    data_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);