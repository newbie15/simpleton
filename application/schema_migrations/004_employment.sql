SET @karyawan_is_active_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'karyawan'
    AND COLUMN_NAME = 'is_active'
);
SET @karyawan_status_sql = IF(
  @karyawan_is_active_exists = 0,
  'ALTER TABLE `karyawan` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1',
  'SELECT 1'
);
PREPARE karyawan_status_stmt FROM @karyawan_status_sql;
EXECUTE karyawan_status_stmt;
DEALLOCATE PREPARE karyawan_status_stmt;

SET @karyawan_bank_name_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'karyawan'
    AND COLUMN_NAME = 'nama_bank'
);
SET @karyawan_status_sql = IF(
  @karyawan_bank_name_exists = 0,
  'ALTER TABLE `karyawan` ADD COLUMN `nama_bank` varchar(64) DEFAULT NULL',
  'SELECT 1'
);
PREPARE karyawan_status_stmt FROM @karyawan_status_sql;
EXECUTE karyawan_status_stmt;
DEALLOCATE PREPARE karyawan_status_stmt;

SET @karyawan_account_number_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'karyawan'
    AND COLUMN_NAME = 'nomor_rekening'
);
SET @karyawan_status_sql = IF(
  @karyawan_account_number_exists = 0,
  'ALTER TABLE `karyawan` ADD COLUMN `nomor_rekening` varchar(64) DEFAULT NULL',
  'SELECT 1'
);
PREPARE karyawan_status_stmt FROM @karyawan_status_sql;
EXECUTE karyawan_status_stmt;
DEALLOCATE PREPARE karyawan_status_stmt;

SET @karyawan_resign_date_exists = (
  SELECT COUNT(1)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'karyawan'
    AND COLUMN_NAME = 'resign_date'
);
SET @karyawan_status_sql = IF(
  @karyawan_resign_date_exists = 0,
  'ALTER TABLE `karyawan` ADD COLUMN `resign_date` date DEFAULT NULL',
  'SELECT 1'
);
PREPARE karyawan_status_stmt FROM @karyawan_status_sql;
EXECUTE karyawan_status_stmt;
DEALLOCATE PREPARE karyawan_status_stmt;
