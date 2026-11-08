-- Repeat-safe upgrade for databases created before NPK and group page access were added.
-- Fresh installs should use the updated ion_auth.sql or simpleton dump instead.
UPDATE `users` SET `username` = `email` WHERE `username` IS NULL OR `username` = '';

ALTER TABLE `users`
  MODIFY COLUMN `email` varchar(100) DEFAULT NULL;

SET @auth_npk_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'npk'
);
SET @auth_upgrade_sql = IF(@auth_npk_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `npk` varchar(32) DEFAULT NULL AFTER `username`', 'SELECT 1');
PREPARE auth_upgrade_stmt FROM @auth_upgrade_sql;
EXECUTE auth_upgrade_stmt;
DEALLOCATE PREPARE auth_upgrade_stmt;

SET @auth_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_username_unique'
);
SET @auth_upgrade_sql = IF(@auth_index_exists = 0,
  'ALTER TABLE `users` ADD UNIQUE KEY `users_username_unique` (`username`)', 'SELECT 1');
PREPARE auth_upgrade_stmt FROM @auth_upgrade_sql;
EXECUTE auth_upgrade_stmt;
DEALLOCATE PREPARE auth_upgrade_stmt;

SET @auth_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_email_unique'
);
SET @auth_upgrade_sql = IF(@auth_index_exists = 0,
  'ALTER TABLE `users` ADD UNIQUE KEY `users_email_unique` (`email`)', 'SELECT 1');
PREPARE auth_upgrade_stmt FROM @auth_upgrade_sql;
EXECUTE auth_upgrade_stmt;
DEALLOCATE PREPARE auth_upgrade_stmt;

SET @auth_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_npk_unique'
);
SET @auth_upgrade_sql = IF(@auth_index_exists = 0,
  'ALTER TABLE `users` ADD UNIQUE KEY `users_npk_unique` (`npk`)', 'SELECT 1');
PREPARE auth_upgrade_stmt FROM @auth_upgrade_sql;
EXECUTE auth_upgrade_stmt;
DEALLOCATE PREPARE auth_upgrade_stmt;

SET @auth_group_pages_new = (
  SELECT COUNT(*) = 0 FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'group_pages'
);
CREATE TABLE IF NOT EXISTS `group_pages` (
  `group_id` mediumint(8) unsigned NOT NULL,
  `page_key` varchar(64) NOT NULL,
  PRIMARY KEY (`group_id`, `page_key`),
  CONSTRAINT `fk_group_pages_groups` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `group_pages` (`group_id`, `page_key`)
SELECT seed_rows.group_id, seed_rows.page_key FROM (
SELECT `id` AS group_id, 'member_portal' AS page_key FROM `groups` WHERE `name` = 'members'
UNION ALL SELECT `id`, 'penjualan' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'tiket' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'stok' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'stock_opname' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'purchasing' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'suppliers' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'karyawan' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'kartu' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'kamera' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'update_data' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
UNION ALL SELECT `id`, 'rekap_penjualan' FROM `groups` WHERE `name` NOT IN ('admin', 'members')
) seed_rows WHERE @auth_group_pages_new = 1;

INSERT IGNORE INTO `group_pages` (`group_id`, `page_key`)
SELECT seed_rows.group_id, seed_rows.page_key FROM (
SELECT `id` AS group_id, 'product_conversion' AS page_key FROM `groups` WHERE `name` = 'management'
UNION ALL SELECT `id`, 'vouchers' FROM `groups` WHERE `name` = 'management'
UNION ALL SELECT `id`, 'sales_return' FROM `groups` WHERE `name` = 'management'
UNION ALL SELECT `id`, 'supplier_return' FROM `groups` WHERE `name` IN ('management', 'pengurus')
UNION ALL SELECT `id`, 'accounting' FROM `groups` WHERE `name` IN ('management', 'pengurus')
UNION ALL SELECT `id`, 'bank_reconciliation' FROM `groups` WHERE `name` IN ('management', 'pengurus')
) seed_rows WHERE @auth_group_pages_new = 1;
