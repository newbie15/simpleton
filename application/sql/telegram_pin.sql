-- PIN login bot Telegram per user (plaintext, 6 digit, diisi admin lewat halaman Edit/Add User).
ALTER TABLE `users` ADD COLUMN `telegram_pin` varchar(6) DEFAULT NULL;
UPDATE `users`
SET `telegram_pin` = '123456'
WHERE `telegram_pin` IS NULL OR TRIM(`telegram_pin`) = '';
ALTER TABLE `users`
MODIFY COLUMN `telegram_pin` varchar(6) NOT NULL DEFAULT '123456';
