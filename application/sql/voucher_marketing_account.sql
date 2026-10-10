-- Add the voucher marketing expense account for existing installations.
INSERT IGNORE INTO `acc_accounts`
	(`code`, `name`, `type`, `normal_balance`, `parent_id`, `is_active`)
VALUES
	('6104', 'Beban Pemasaran - Voucher', 'expense', 'debit', NULL, 1);
