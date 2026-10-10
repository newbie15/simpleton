-- Kas utama dan kas kecil berbasis imprest.
INSERT IGNORE INTO `acc_accounts`
  (`code`, `name`, `type`, `normal_balance`, `parent_id`, `is_active`)
VALUES
  ('1106', 'Kas Utama', 'asset', 'debit', NULL, 1),
  ('1107', 'Kas Kecil', 'asset', 'debit', NULL, 1);

CREATE TABLE IF NOT EXISTS `acc_cash_funds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fund_code` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `fund_type` enum('cashier','main','petty') NOT NULL,
  `fund_slot` varchar(16) DEFAULT NULL,
  `custodian_user_id` varchar(32) DEFAULT NULL,
  `imprest_limit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `created_by` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_cash_fund_code` (`fund_code`),
  UNIQUE KEY `idx_acc_cash_fund_slot` (`fund_slot`),
  KEY `idx_acc_cash_fund_type_active` (`fund_type`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_cash_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(64) NOT NULL,
  `fund_id` int(11) NOT NULL,
  `transaction_type` enum('funding','expense','supplier_payment') NOT NULL,
  `transaction_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `funding_source_account_id` int(11) DEFAULT NULL,
  `expense_account_id` int(11) DEFAULT NULL,
  `goods_receipt_id` int(11) DEFAULT NULL,
  `counterparty` varchar(128) DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `evidence_reference` varchar(128) NOT NULL,
  `proof_filename` varchar(255) DEFAULT NULL,
  `status` enum('pending','posted','rejected') NOT NULL DEFAULT 'pending',
  `journal_id` int(11) DEFAULT NULL,
  `created_by` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL,
  `approved_by` varchar(32) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_cash_transaction_number` (`transaction_number`),
  UNIQUE KEY `idx_acc_cash_transaction_journal` (`journal_id`),
  KEY `idx_acc_cash_transaction_fund_date` (`fund_id`,`transaction_date`,`status`),
  KEY `idx_acc_cash_transaction_status_date` (`status`,`transaction_date`),
  KEY `idx_acc_cash_transaction_receipt` (`goods_receipt_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_cash_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fund_id` int(11) NOT NULL,
  `closing_date` date NOT NULL,
  `system_balance` decimal(15,2) NOT NULL,
  `counted_balance` decimal(15,2) NOT NULL,
  `variance` decimal(15,2) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `closed_by` varchar(32) NOT NULL,
  `closed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_cash_closing_fund_date` (`fund_id`,`closing_date`),
  KEY `idx_acc_cash_closing_date` (`closing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `acc_cash_funds`
  (`fund_code`, `name`, `fund_type`, `fund_slot`, `custodian_user_id`,
   `imprest_limit`, `is_active`, `created_at`, `created_by`)
VALUES
  ('CASH-POS', 'Laci Kasir POS', 'cashier', 'cashier', NULL, '0.00', 1, NOW(), 'system');
