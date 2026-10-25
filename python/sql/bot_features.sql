-- Add bot financial summary and product search views to an existing installation.
-- Run as a DB administrator after bot_setup.sql. This does not change the bot password.
CREATE OR REPLACE VIEW `v_bot_financial_summary` AS
  SELECT
    CURDATE() AS `as_of_date`,
    COALESCE((
      SELECT SUM(CASE WHEN a.`normal_balance` = 'debit'
        THEN i.`debit` - i.`credit` ELSE i.`credit` - i.`debit` END)
      FROM `acc_accounts` a
      JOIN `acc_journal_items` i ON i.`account_id` = a.`id`
      JOIN `acc_journals` j ON j.`id` = i.`journal_id`
      WHERE a.`is_active` = 1 AND a.`type` = 'asset'
        AND j.`is_posted` = 1 AND j.`date` <= CURDATE()
    ), 0) AS `assets`,
    COALESCE((
      SELECT SUM(CASE WHEN a.`normal_balance` = 'debit'
        THEN i.`debit` - i.`credit` ELSE i.`credit` - i.`debit` END)
      FROM `acc_accounts` a
      JOIN `acc_journal_items` i ON i.`account_id` = a.`id`
      JOIN `acc_journals` j ON j.`id` = i.`journal_id`
      WHERE a.`is_active` = 1 AND a.`type` = 'liability'
        AND j.`is_posted` = 1 AND j.`date` <= CURDATE()
    ), 0) AS `liabilities`,
    COALESCE((
      SELECT SUM(CASE WHEN a.`normal_balance` = 'debit'
        THEN i.`debit` - i.`credit` ELSE i.`credit` - i.`debit` END)
      FROM `acc_accounts` a
      JOIN `acc_journal_items` i ON i.`account_id` = a.`id`
      JOIN `acc_journals` j ON j.`id` = i.`journal_id`
      WHERE a.`is_active` = 1 AND a.`type` = 'equity'
        AND j.`is_posted` = 1 AND j.`date` <= CURDATE()
    ) + COALESCE((
      SELECT SUM(i.`credit` - i.`debit`)
      FROM `acc_accounts` a
      JOIN `acc_journal_items` i ON i.`account_id` = a.`id`
      JOIN `acc_journals` j ON j.`id` = i.`journal_id`
      WHERE a.`is_active` = 1 AND a.`type` IN ('income', 'cogs', 'expense')
        AND j.`is_posted` = 1 AND j.`date` <= CURDATE()
    ), 0), 0) AS `equity`,
    COALESCE((
      SELECT SUM(i.`credit` - i.`debit`)
      FROM `acc_accounts` a
      JOIN `acc_journal_items` i ON i.`account_id` = a.`id`
      JOIN `acc_journals` j ON j.`id` = i.`journal_id`
      WHERE a.`is_active` = 1 AND a.`type` = 'income'
        AND j.`is_posted` = 1 AND j.`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')
        AND j.`date` <= CURDATE()
    ), 0) AS `income_ytd`,
    COALESCE((
      SELECT SUM(i.`debit` - i.`credit`)
      FROM `acc_accounts` a
      JOIN `acc_journal_items` i ON i.`account_id` = a.`id`
      JOIN `acc_journals` j ON j.`id` = i.`journal_id`
      WHERE a.`is_active` = 1 AND a.`type` IN ('cogs', 'expense')
        AND j.`is_posted` = 1 AND j.`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')
        AND j.`date` <= CURDATE()
    ), 0) AS `expenses_ytd`,
    COALESCE((
      SELECT SUM(i.`debit` - i.`credit`)
      FROM `acc_accounts` a
      JOIN `acc_journal_items` i ON i.`account_id` = a.`id`
      JOIN `acc_journals` j ON j.`id` = i.`journal_id`
      WHERE a.`is_active` = 1 AND a.`code` IN ('1101', '1102', '1106', '1107')
        AND j.`is_posted` = 1 AND j.`date` >= DATE_FORMAT(CURDATE(), '%Y-01-01')
        AND j.`date` <= CURDATE()
    ), 0) AS `cash_change_ytd`;

CREATE OR REPLACE VIEW `v_bot_products` AS
  SELECT `barcode`, `nama`, `jumlah`
  FROM `stok`;

GRANT SELECT ON `simpleton`.`v_bot_financial_summary` TO 'bot_tg'@'localhost';
GRANT SELECT ON `simpleton`.`v_bot_products` TO 'bot_tg'@'localhost';
