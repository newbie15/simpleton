ALTER TABLE `sales_validation_runs`
	MODIFY `validation_mode` enum('period','week','nota','cutoff') NOT NULL;

SET @billing_column_exists = (
	SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_validation_items' AND COLUMN_NAME = 'payment_method'
);
SET @billing_upgrade_sql = IF(@billing_column_exists = 0,
	'ALTER TABLE `sales_validation_items` ADD COLUMN `payment_method` varchar(20) NOT NULL DEFAULT '''' AFTER `billable_total`', 'SELECT 1');
PREPARE billing_upgrade_stmt FROM @billing_upgrade_sql;
EXECUTE billing_upgrade_stmt;
DEALLOCATE PREPARE billing_upgrade_stmt;

SET @billing_column_exists = (
	SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_billing_items' AND COLUMN_NAME = 'payment_method'
);
SET @billing_upgrade_sql = IF(@billing_column_exists = 0,
	'ALTER TABLE `sales_billing_items` ADD COLUMN `payment_method` varchar(20) NOT NULL DEFAULT '''' AFTER `billable_total`', 'SELECT 1');
PREPARE billing_upgrade_stmt FROM @billing_upgrade_sql;
EXECUTE billing_upgrade_stmt;
DEALLOCATE PREPARE billing_upgrade_stmt;

SET @billing_column_exists = (
	SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales_billing_items' AND COLUMN_NAME = 'adjustment_note'
);
SET @billing_upgrade_sql = IF(@billing_column_exists = 0,
	'ALTER TABLE `sales_billing_items` ADD COLUMN `adjustment_note` text AFTER `payment_method`', 'SELECT 1');
PREPARE billing_upgrade_stmt FROM @billing_upgrade_sql;
EXECUTE billing_upgrade_stmt;
DEALLOCATE PREPARE billing_upgrade_stmt;

CREATE TABLE IF NOT EXISTS `sales_billing_adjustments` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`period_id` int(11) NOT NULL,
	`nota` varchar(64) NOT NULL,
	`correct_source` enum('system','physical') NOT NULL,
	`system_total` decimal(15,2) NOT NULL DEFAULT '0.00',
	`correct_total` decimal(15,2) NOT NULL DEFAULT '0.00',
	`note` text NOT NULL,
	`status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
	`revised_by` varchar(32) NOT NULL,
	`revised_at` datetime NOT NULL,
	`decided_by` varchar(32) DEFAULT NULL,
	`decided_at` datetime DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_sales_billing_adjust_period_nota` (`period_id`,`nota`),
	KEY `idx_sales_billing_adjust_status` (`period_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
