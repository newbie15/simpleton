SET @penjualan_hpp_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'penjualan'
    AND COLUMN_NAME = 'hpp'
);
SET @sales_accounting_sql = IF(
  @penjualan_hpp_exists = 0,
  'ALTER TABLE `penjualan` ADD COLUMN `hpp` decimal(15,4) DEFAULT NULL AFTER `harga`',
  'SELECT 1'
);
PREPARE sales_accounting_stmt FROM @sales_accounting_sql;
EXECUTE sales_accounting_stmt;
DEALLOCATE PREPARE sales_accounting_stmt;

SET @penjualan_payment_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'penjualan'
    AND COLUMN_NAME = 'metode_pembayaran'
);
SET @sales_accounting_sql = IF(
  @penjualan_payment_exists = 0,
  'ALTER TABLE `penjualan` ADD COLUMN `metode_pembayaran` varchar(20) DEFAULT NULL AFTER `ppn`',
  'SELECT 1'
);
PREPARE sales_accounting_stmt FROM @sales_accounting_sql;
EXECUTE sales_accounting_stmt;
DEALLOCATE PREPARE sales_accounting_stmt;

SET @penjualan_payment_index_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'penjualan'
    AND INDEX_NAME = 'idx_penjualan_metode_tgl'
);
SET @sales_accounting_sql = IF(
  @penjualan_payment_index_exists = 0,
  'CREATE INDEX `idx_penjualan_metode_tgl` ON `penjualan` (`metode_pembayaran`, `tgl`)',
  'SELECT 1'
);
PREPARE sales_accounting_stmt FROM @sales_accounting_sql;
EXECUTE sales_accounting_stmt;
DEALLOCATE PREPARE sales_accounting_stmt;
