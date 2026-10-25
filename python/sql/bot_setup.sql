-- Setup DB untuk bot Telegram anggota. Jalankan sebagai admin MySQL.
-- Ganti password 'GANTI_PASSWORD_KUAT' sebelum menjalankan.
-- Asumsi: "tagihan" = transaksi metode potong_gaji 60 hari terakhir; "SHU" = simpanan_mutasi jenis 'shu'.

CREATE TABLE IF NOT EXISTS `telegram_links` (
  `chat_id` bigint(20) NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `linked_at` datetime NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`chat_id`),
  KEY `idx_tg_karyawan` (`karyawan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `telegram_login_fail` (
  `karyawan_id` int(11) NOT NULL,
  `fails` int(11) NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`karyawan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE OR REPLACE VIEW `v_bot_anggota` AS
  SELECT `id`, `npk`, `nama` FROM `karyawan`;

-- Prasyarat: application/sql/telegram_pin.sql (kolom users.telegram_pin).
-- Hanya npk+pin user aktif yang terekspos ke bot, bukan password/salt.
CREATE OR REPLACE VIEW `v_bot_login` AS
  SELECT k.`id` AS karyawan_id, k.`npk`, u.`telegram_pin` AS pin
  FROM `users` u
  JOIN `karyawan` k ON k.`npk` = u.`npk`
  WHERE u.`active` = 1 AND u.`telegram_pin` IS NOT NULL AND u.`telegram_pin` <> '';

CREATE OR REPLACE VIEW `v_bot_simpanan` AS
  SELECT `karyawan_id`, `jenis_simpanan`, `saldo`, `updated_at` FROM `simpanan`;

CREATE OR REPLACE VIEW `v_bot_belanja` AS
  SELECT k.`id` AS karyawan_id, p.`nota`, p.`metode_pembayaran`, p.`total_tagihan`, p.`waktu_transaksi`
  FROM `penjualan_pembayaran` p
  JOIN `karyawan` k ON k.`npk` = p.`id_karyawan`;

CREATE OR REPLACE VIEW `v_bot_tagihan` AS
  SELECT k.`id` AS karyawan_id, p.`nota`, p.`total_tagihan`, p.`waktu_transaksi`
  FROM `penjualan_pembayaran` p
  JOIN `karyawan` k ON k.`npk` = p.`id_karyawan`
  WHERE p.`metode_pembayaran` = 'potong_gaji'
    AND p.`waktu_transaksi` >= (NOW() - INTERVAL 60 DAY);

CREATE OR REPLACE VIEW `v_bot_shu` AS
  SELECT `karyawan_id`, `jenis_simpanan`, `nominal`, `tgl_transaksi`, `keterangan`
  FROM `simpanan_mutasi` WHERE `jenis_mutasi` = 'shu';

-- Ringkasan agregat saja: posisi sampai hari ini, pendapatan/beban dan perubahan kas YTD.
-- Rumus saldo akun mengikuti laporan akuntansi di aplikasi.
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

-- Bot: baca view saja + kelola link/lockout. Tidak ada akses langsung ke tabel users.
-- Kompatibel MySQL 5.6: GRANT ... IDENTIFIED BY membuat user bila belum ada.
GRANT SELECT ON `simpleton`.`v_bot_anggota` TO 'bot_tg'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT';
GRANT SELECT ON `simpleton`.`v_bot_simpanan` TO 'bot_tg'@'localhost';
GRANT SELECT ON `simpleton`.`v_bot_belanja` TO 'bot_tg'@'localhost';
GRANT SELECT ON `simpleton`.`v_bot_tagihan` TO 'bot_tg'@'localhost';
GRANT SELECT ON `simpleton`.`v_bot_shu` TO 'bot_tg'@'localhost';
GRANT SELECT ON `simpleton`.`v_bot_financial_summary` TO 'bot_tg'@'localhost';
GRANT SELECT ON `simpleton`.`v_bot_products` TO 'bot_tg'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `simpleton`.`telegram_links` TO 'bot_tg'@'localhost';
GRANT SELECT ON `simpleton`.`v_bot_login` TO 'bot_tg'@'localhost';
GRANT SELECT, INSERT, UPDATE ON `simpleton`.`telegram_login_fail` TO 'bot_tg'@'localhost';
FLUSH PRIVILEGES;
