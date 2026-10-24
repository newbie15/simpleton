CREATE TABLE IF NOT EXISTS `sales_billing_periods` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`period_name` varchar(64) NOT NULL,
	`start_date` date NOT NULL,
	`end_date` date NOT NULL,
	`status` enum('open','finalized') NOT NULL DEFAULT 'open',
	`created_by` varchar(32) NOT NULL,
	`created_at` datetime NOT NULL,
	`finalized_by` varchar(32) DEFAULT NULL,
	`finalized_at` datetime DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_sales_billing_period_dates` (`start_date`,`end_date`),
	KEY `idx_sales_billing_period_status` (`status`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_validation_runs` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`period_id` int(11) NOT NULL,
	`validation_mode` enum('period','week','nota') NOT NULL,
	`start_date` date NOT NULL,
	`end_date` date NOT NULL,
	`nota_filter` varchar(64) DEFAULT NULL,
	`result` enum('passed','failed') NOT NULL,
	`checked_count` int(11) NOT NULL DEFAULT '0',
	`created_by` varchar(32) NOT NULL,
	`created_at` datetime NOT NULL,
	PRIMARY KEY (`id`),
	KEY `idx_sales_validation_period` (`period_id`,`id`),
	KEY `idx_sales_validation_result` (`period_id`,`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_validation_items` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`run_id` int(11) NOT NULL,
	`nota` varchar(64) NOT NULL,
	`npk` varchar(32) NOT NULL,
	`transaction_date` date NOT NULL,
	`status` enum('valid','invalid') NOT NULL,
	`issues` text,
	`total_sales` decimal(15,2) NOT NULL DEFAULT '0.00',
	`approved_returns` decimal(15,2) NOT NULL DEFAULT '0.00',
	`billable_total` decimal(15,2) NOT NULL DEFAULT '0.00',
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_sales_validation_run_nota` (`run_id`,`nota`),
	KEY `idx_sales_validation_nota` (`nota`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_billing_items` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`period_id` int(11) NOT NULL,
	`nota` varchar(64) NOT NULL,
	`npk` varchar(32) NOT NULL,
	`employee_name` varchar(128) DEFAULT NULL,
	`transaction_date` datetime NOT NULL,
	`total_sales` decimal(15,2) NOT NULL DEFAULT '0.00',
	`approved_returns` decimal(15,2) NOT NULL DEFAULT '0.00',
	`billable_total` decimal(15,2) NOT NULL DEFAULT '0.00',
	PRIMARY KEY (`id`),
	UNIQUE KEY `uq_sales_billing_period_nota` (`period_id`,`nota`),
	KEY `idx_sales_billing_period_npk` (`period_id`,`npk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;