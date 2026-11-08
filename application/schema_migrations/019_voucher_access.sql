-- Grant access to the voucher master for existing installations.
INSERT IGNORE INTO `group_pages` (`group_id`, `page_key`)
SELECT `id`, 'vouchers' FROM `groups` WHERE `name` = 'management';
