-- Run once before enabling VAT-inclusive POS sales.
-- New line fields are nullable so historical rows keep their original price/tax meaning.
CREATE TABLE IF NOT EXISTS `sales_tax_settings` (
  `id` tinyint unsigned NOT NULL,
  `rate_percent` decimal(5,2) NOT NULL DEFAULT '11.00',
  `updated_by` varchar(32) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `sales_tax_settings` (`id`, `rate_percent`)
VALUES (1, 11.00);

SET @sales_tax_dpp_exists = (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penjualan'
    AND COLUMN_NAME = 'dpp_satuan'
);
SET @sales_tax_sql = IF(
  @sales_tax_dpp_exists = 0,
  'ALTER TABLE `penjualan` ADD COLUMN `dpp_satuan` decimal(15,2) DEFAULT NULL AFTER `ppn`',
  'SELECT 1'
);
PREPARE sales_tax_stmt FROM @sales_tax_sql;
EXECUTE sales_tax_stmt;
DEALLOCATE PREPARE sales_tax_stmt;

SET @sales_tax_ppn_exists = (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'penjualan'
    AND COLUMN_NAME = 'ppn_satuan'
);
SET @sales_tax_sql = IF(
  @sales_tax_ppn_exists = 0,
  'ALTER TABLE `penjualan` ADD COLUMN `ppn_satuan` decimal(15,2) DEFAULT NULL AFTER `dpp_satuan`',
  'SELECT 1'
);
PREPARE sales_tax_stmt FROM @sales_tax_sql;
EXECUTE sales_tax_stmt;
DEALLOCATE PREPARE sales_tax_stmt;
