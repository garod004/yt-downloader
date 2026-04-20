CREATE TABLE IF NOT EXISTS `modelos_documentos` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(150) NOT NULL COMMENT 'Nome do modelo',
  `categoria`   VARCHAR(80)  DEFAULT 'Geral',
  `descricao`   VARCHAR(255) DEFAULT NULL,
  `conteudo`    LONGTEXT NOT NULL COMMENT 'HTML com marcadores {{campo}}',
  `ativo`       TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=soft delete',
  `criado_por`  VARCHAR(100) DEFAULT NULL,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ativo` (`ativo`),
  KEY `idx_categoria` (`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
