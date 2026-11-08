-- Retur supplier ikut mengembalikan PPN masukan secara proporsional.
-- total_nominal = subtotal barang + ppn_nominal.
SET @ppn_col_exists = (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retur_supplier' AND COLUMN_NAME = 'ppn_nominal'
);
SET @sql_ppn_col = IF(@ppn_col_exists = 0,
  'ALTER TABLE `retur_supplier` ADD COLUMN `ppn_nominal` decimal(15,2) NOT NULL DEFAULT "0.00" AFTER `total_nominal`',
  'SELECT 1'
);
PREPARE stmt_ppn_col FROM @sql_ppn_col;
EXECUTE stmt_ppn_col;
DEALLOCATE PREPARE stmt_ppn_col;
