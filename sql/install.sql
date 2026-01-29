-- Tabele GPSR — autor: Prestado

CREATE TABLE IF NOT EXISTS `ps_gpsr_entity` (
  `id_gpsr_entity` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(64) NOT NULL,
  `entity_type` TINYINT UNSIGNED NOT NULL DEFAULT 0, -- 0: Ogólny, 1: Producent, 2: Importer
  `name` VARCHAR(255) NOT NULL,
  `country_code` CHAR(2) NOT NULL,
  `street` VARCHAR(255) NOT NULL,
  `postcode` VARCHAR(32) NOT NULL,
  `city` VARCHAR(128) NOT NULL,
  `email` VARCHAR(255) NULL,
  `phone` VARCHAR(64) NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  UNIQUE KEY `uniq_gpsr_entity_identifier` (`identifier`),
  KEY `idx_entity_type` (`entity_type`),
  KEY `idx_country_city` (`country_code`,`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ps_gpsr_attachment` (
  `id_gpsr_attachment` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `file_original` VARCHAR(255) NOT NULL,
  `file_saved` VARCHAR(255) NOT NULL,
  `mime` VARCHAR(128) NULL,
  `size` INT UNSIGNED NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  KEY `idx_attachment_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ps_gpsr_entity_product` (
  `id_gpsr_entity` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id_gpsr_entity`,`id_product`,`id_shop`),
  KEY `idx_entity_product_product` (`id_product`),
  CONSTRAINT `fk_gep_entity` FOREIGN KEY (`id_gpsr_entity`) REFERENCES `ps_gpsr_entity` (`id_gpsr_entity`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ps_gpsr_attachment_product` (
  `id_gpsr_attachment` INT UNSIGNED NOT NULL,
  `id_product` INT UNSIGNED NOT NULL,
  `id_shop` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id_gpsr_attachment`,`id_product`,`id_shop`),
  KEY `idx_attach_product_product` (`id_product`),
  CONSTRAINT `fk_gap_attachment` FOREIGN KEY (`id_gpsr_attachment`) REFERENCES `ps_gpsr_attachment` (`id_gpsr_attachment`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reguły auto-przypisywania (użyjemy w Krok 5)
CREATE TABLE IF NOT EXISTS `ps_gpsr_entity_rule` (
  `id_rule` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_gpsr_entity` INT UNSIGNED NOT NULL,
  `rule_type` ENUM('category','manufacturer','supplier') NOT NULL,
  `id_target` INT UNSIGNED NOT NULL,
  `include_children` TINYINT(1) NOT NULL DEFAULT 1,
  `id_shop` INT UNSIGNED DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  KEY `idx_rule_target` (`rule_type`,`id_target`),
  CONSTRAINT `fk_ger_entity` FOREIGN KEY (`id_gpsr_entity`) REFERENCES `ps_gpsr_entity` (`id_gpsr_entity`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ps_gpsr_attachment_rule` (
  `id_rule` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `id_gpsr_attachment` INT UNSIGNED NOT NULL,
  `rule_type` ENUM('category','manufacturer','supplier') NOT NULL,
  `id_target` INT UNSIGNED NOT NULL,
  `include_children` TINYINT(1) NOT NULL DEFAULT 1,
  `id_shop` INT UNSIGNED DEFAULT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `date_add` DATETIME NOT NULL,
  `date_upd` DATETIME NOT NULL,
  KEY `idx_rule_target` (`rule_type`,`id_target`),
  CONSTRAINT `fk_gar_attachment` FOREIGN KEY (`id_gpsr_attachment`) REFERENCES `ps_gpsr_attachment` (`id_gpsr_attachment`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

