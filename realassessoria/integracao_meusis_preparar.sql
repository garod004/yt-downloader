SET @db_name = DATABASE();

SET @sql = IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'clientes'
          AND COLUMN_NAME = 'meusis_id'
    ),
    'SELECT ''clientes.meusis_id ja existe''',
    'ALTER TABLE clientes ADD COLUMN meusis_id INT(10) UNSIGNED NULL AFTER id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF (
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'clientes'
          AND INDEX_NAME = 'uniq_clientes_meusis_id'
    ),
    'SELECT ''indice uniq_clientes_meusis_id ja existe''',
    'ALTER TABLE clientes ADD UNIQUE KEY uniq_clientes_meusis_id (meusis_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'usuarios'
          AND COLUMN_NAME = 'meusis_id'
    ),
    'SELECT ''usuarios.meusis_id ja existe''',
    'ALTER TABLE usuarios ADD COLUMN meusis_id INT(10) UNSIGNED NULL AFTER id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF (
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'usuarios'
          AND INDEX_NAME = 'uniq_usuarios_meusis_id'
    ),
    'SELECT ''indice uniq_usuarios_meusis_id ja existe''',
    'ALTER TABLE usuarios ADD UNIQUE KEY uniq_usuarios_meusis_id (meusis_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF (
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'filhos_menores'
          AND COLUMN_NAME = 'meusis_id'
    ),
    'SELECT ''filhos_menores.meusis_id ja existe''',
    'ALTER TABLE filhos_menores ADD COLUMN meusis_id INT(10) UNSIGNED NULL AFTER id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF (
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @db_name
          AND TABLE_NAME = 'filhos_menores'
          AND INDEX_NAME = 'idx_filhos_menores_meusis_id'
    ),
    'SELECT ''indice idx_filhos_menores_meusis_id ja existe''',
    'ALTER TABLE filhos_menores ADD KEY idx_filhos_menores_meusis_id (meusis_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
