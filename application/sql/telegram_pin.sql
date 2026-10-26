-- PIN login bot Telegram per user (plaintext, 6 digit, diisi admin lewat halaman Edit/Add User).
ALTER TABLE `users` ADD COLUMN `telegram_pin` varchar(6) DEFAULT NULL;
