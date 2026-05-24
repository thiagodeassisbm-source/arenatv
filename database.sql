-- Criar o Banco de Dados se não existir (Opcional, o usuário pode criar manualmente)
-- CREATE DATABASE IF NOT EXISTS `venda_canais` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE `venda_canais`;

-- 1. TABELA DE CONFIGURAÇÕES
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `meta_key` VARCHAR(100) NOT NULL UNIQUE,
  `meta_value` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABELA DE CANAIS
CREATE TABLE IF NOT EXISTS `channels` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `logo` VARCHAR(500) NULL,
  `url` TEXT NOT NULL,
  `group_name` VARCHAR(150) NULL,
  `tvg_id` VARCHAR(100) NULL,
  `is_sports` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Criar índices para otimizar a busca e filtragem
CREATE INDEX idx_channels_sports ON `channels`(`is_sports`);
CREATE INDEX idx_channels_name ON `channels`(`name`);

-- 3. TABELA DE JOGOS PARA VENDA
CREATE TABLE IF NOT EXISTS `games` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `channel_id` INT NULL,
  `game_date` DATETIME NULL,
  `price` DECIMAL(10, 2) DEFAULT 0.00,
  `status` VARCHAR(50) DEFAULT 'ativo',
  `external_link` VARCHAR(500) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`channel_id`) REFERENCES `channels`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir algumas configurações iniciais padrão
INSERT INTO `settings` (`meta_key`, `meta_value`) VALUES
('site_name', 'Arena Stream - Venda de Jogos'),
('currency', 'BRL'),
('m3u_url', '')
ON DUPLICATE KEY UPDATE `meta_value` = `meta_value`;
