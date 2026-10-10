-- Jalankan setelah auth_npk_group_pages.sql.
INSERT IGNORE INTO `group_pages` (`group_id`, `page_key`)
SELECT `id`, 'petty_cash'
FROM `groups`
WHERE `name` IN ('kasir', 'management', 'pengurus');
