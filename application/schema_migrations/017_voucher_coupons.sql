CREATE TABLE IF NOT EXISTS `voucher_coupons` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`voucher_id` int(11) NOT NULL,
	`serial_code` varchar(32) NOT NULL,
	`assigned_npk` varchar(32) DEFAULT NULL,
	`status` enum('available','used','void') NOT NULL DEFAULT 'available',
	`used_nota` varchar(64) DEFAULT NULL,
	`used_at` datetime DEFAULT NULL,
	`created_at` datetime NOT NULL,
	`created_by` varchar(32) NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_voucher_coupon_serial` (`serial_code`),
	UNIQUE KEY `uq_voucher_coupon_nota` (`used_nota`),
	KEY `idx_voucher_coupon_available` (`voucher_id`,`status`),
	KEY `idx_voucher_coupon_npk` (`assigned_npk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

SET @voucher_usage_coupon_exists = (
	SELECT COUNT(1)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
		AND TABLE_NAME = 'voucher_usages'
		AND COLUMN_NAME = 'voucher_coupon_id'
);
SET @voucher_coupon_sql = IF(
	@voucher_usage_coupon_exists = 0,
	'ALTER TABLE `voucher_usages` ADD COLUMN `voucher_coupon_id` int(11) DEFAULT NULL AFTER `voucher_id`, ADD KEY `idx_usage_coupon` (`voucher_coupon_id`)',
	'SELECT 1'
);
PREPARE voucher_coupon_stmt FROM @voucher_coupon_sql;
EXECUTE voucher_coupon_stmt;
DEALLOCATE PREPARE voucher_coupon_stmt;