ALTER TABLE `sales_validation_runs`
	MODIFY `validation_mode` enum('period','week','nota','cutoff') NOT NULL;

ALTER TABLE `sales_validation_items`
	ADD COLUMN `payment_method` varchar(20) NOT NULL DEFAULT '' AFTER `billable_total`;

ALTER TABLE `sales_billing_items`
	ADD COLUMN `payment_method` varchar(20) NOT NULL DEFAULT '' AFTER `billable_total`,
	ADD COLUMN `adjustment_note` text AFTER `payment_method`;

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
