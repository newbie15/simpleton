CREATE TABLE IF NOT EXISTS `pos_receipt_sequences` (
	`business_date` date NOT NULL,
	`npk` varchar(32) NOT NULL,
	`last_number` int(10) unsigned NOT NULL DEFAULT '0',
	PRIMARY KEY (`business_date`, `npk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET @checkout_request_column_exists = (
	SELECT COUNT(1)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'penjualan_pembayaran'
		AND COLUMN_NAME = 'checkout_request_id'
);
SET @checkout_request_column_sql = IF(
	@checkout_request_column_exists = 0,
	'ALTER TABLE `penjualan_pembayaran` ADD COLUMN `checkout_request_id` varchar(64) DEFAULT NULL AFTER `nota`',
	'SELECT 1'
);
PREPARE checkout_request_column_stmt FROM @checkout_request_column_sql;
EXECUTE checkout_request_column_stmt;
DEALLOCATE PREPARE checkout_request_column_stmt;

SET @checkout_request_index_exists = (
	SELECT COUNT(1)
	FROM INFORMATION_SCHEMA.STATISTICS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'penjualan_pembayaran'
		AND INDEX_NAME = 'uq_pembayaran_checkout_request'
);
SET @checkout_request_index_sql = IF(
	@checkout_request_index_exists = 0,
	'ALTER TABLE `penjualan_pembayaran` ADD UNIQUE KEY `uq_pembayaran_checkout_request` (`checkout_request_id`)',
	'SELECT 1'
);
PREPARE checkout_request_index_stmt FROM @checkout_request_index_sql;
EXECUTE checkout_request_index_stmt;
DEALLOCATE PREPARE checkout_request_index_stmt;