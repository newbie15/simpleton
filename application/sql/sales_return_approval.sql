ALTER TABLE `retur_penjualan`
	MODIFY COLUMN `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending';

UPDATE `retur_penjualan` SET `status` = 'approved' WHERE `status` = 'completed';

SET @sales_return_approved_by_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retur_penjualan'
		AND COLUMN_NAME = 'approved_by'
);
SET @sales_return_sql = IF(
	@sales_return_approved_by_exists = 0,
	'ALTER TABLE `retur_penjualan` ADD COLUMN `approved_by` varchar(32) DEFAULT NULL AFTER `status`',
	'SELECT 1'
);
PREPARE sales_return_stmt FROM @sales_return_sql;
EXECUTE sales_return_stmt;
DEALLOCATE PREPARE sales_return_stmt;

SET @sales_return_approved_at_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retur_penjualan'
		AND COLUMN_NAME = 'approved_at'
);
SET @sales_return_sql = IF(
	@sales_return_approved_at_exists = 0,
	'ALTER TABLE `retur_penjualan` ADD COLUMN `approved_at` datetime DEFAULT NULL AFTER `approved_by`',
	'SELECT 1'
);
PREPARE sales_return_stmt FROM @sales_return_sql;
EXECUTE sales_return_stmt;
DEALLOCATE PREPARE sales_return_stmt;

SET @sales_return_rejection_reason_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retur_penjualan'
		AND COLUMN_NAME = 'rejection_reason'
);
SET @sales_return_sql = IF(
	@sales_return_rejection_reason_exists = 0,
	'ALTER TABLE `retur_penjualan` ADD COLUMN `rejection_reason` varchar(500) DEFAULT NULL AFTER `approved_at`',
	'SELECT 1'
);
PREPARE sales_return_stmt FROM @sales_return_sql;
EXECUTE sales_return_stmt;
DEALLOCATE PREPARE sales_return_stmt;

SET @sales_return_rejected_by_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retur_penjualan'
		AND COLUMN_NAME = 'rejected_by'
);
SET @sales_return_sql = IF(
	@sales_return_rejected_by_exists = 0,
	'ALTER TABLE `retur_penjualan` ADD COLUMN `rejected_by` varchar(32) DEFAULT NULL AFTER `approved_at`',
	'SELECT 1'
);
PREPARE sales_return_stmt FROM @sales_return_sql;
EXECUTE sales_return_stmt;
DEALLOCATE PREPARE sales_return_stmt;

SET @sales_return_rejected_at_exists = (
	SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'retur_penjualan'
		AND COLUMN_NAME = 'rejected_at'
);
SET @sales_return_sql = IF(
	@sales_return_rejected_at_exists = 0,
	'ALTER TABLE `retur_penjualan` ADD COLUMN `rejected_at` datetime DEFAULT NULL AFTER `rejected_by`',
	'SELECT 1'
);
PREPARE sales_return_stmt FROM @sales_return_sql;
EXECUTE sales_return_stmt;
DEALLOCATE PREPARE sales_return_stmt;