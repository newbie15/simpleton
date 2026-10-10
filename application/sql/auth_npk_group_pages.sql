-- One-time upgrade for databases created before NPK and group page access were added.
-- Fresh installs should use the updated ion_auth.sql or simpleton dump instead.
UPDATE `users` SET `username` = `email` WHERE `username` IS NULL OR `username` = '';

ALTER TABLE `users`
  MODIFY COLUMN `email` varchar(100) DEFAULT NULL,
  ADD COLUMN `npk` varchar(32) DEFAULT NULL AFTER `username`,
  ADD UNIQUE KEY `users_username_unique` (`username`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_npk_unique` (`npk`);

CREATE TABLE `group_pages` (
  `group_id` mediumint(8) unsigned NOT NULL,
  `page_key` varchar(64) NOT NULL,
  PRIMARY KEY (`group_id`, `page_key`),
  CONSTRAINT `fk_group_pages_groups` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO `group_pages` (`group_id`, `page_key`)
SELECT `id`, 'member_portal' FROM `groups` WHERE `name` = 'members'
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
UNION ALL SELECT `id`, 'rekap_penjualan' FROM `groups` WHERE `name` NOT IN ('admin', 'members');

INSERT IGNORE INTO `group_pages` (`group_id`, `page_key`)
SELECT `id`, 'product_conversion' FROM `groups` WHERE `name` = 'management'
UNION ALL SELECT `id`, 'vouchers' FROM `groups` WHERE `name` = 'management'
UNION ALL SELECT `id`, 'sales_return' FROM `groups` WHERE `name` = 'management'
UNION ALL SELECT `id`, 'supplier_return' FROM `groups` WHERE `name` IN ('management', 'pengurus')
UNION ALL SELECT `id`, 'accounting' FROM `groups` WHERE `name` IN ('management', 'pengurus')
UNION ALL SELECT `id`, 'bank_reconciliation' FROM `groups` WHERE `name` IN ('management', 'pengurus');
