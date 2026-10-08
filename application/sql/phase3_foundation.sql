-- =====================================================================
-- Migrasi Database Fase 3: Fondasi Skema & Otorisasi Koperasi Simpleton
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Penambahan Role / Groups di Ion Auth
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `groups` (`id`, `name`, `description`) VALUES
(1, 'admin', 'Superadmin / Pengelola Sistem'),
(2, 'members', 'Anggota Koperasi'),
(3, 'kasir', 'Kasir Toko'),
(4, 'management', 'Manajemen Operasional & Toko'),
(5, 'pengurus', 'Pengurus Koperasi & Keuangan');

-- ---------------------------------------------------------------------
-- 2. Konversi Kemasan (Multi-tier Dus vs Pcs & Rasio)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_conversions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barcode_dus` varchar(64) NOT NULL,
  `barcode_pcs` varchar(64) NOT NULL,
  `nama_kemasan` varchar(64) NOT NULL,
  `rasio` int(11) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_barcode_dus` (`barcode_dus`),
  KEY `idx_barcode_pcs` (`barcode_pcs`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------
-- 3. Master Voucher & Riwayat Penggunaan Voucher
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode` varchar(32) NOT NULL,
  `nama` varchar(128) NOT NULL,
  `tipe` enum('diskon_nominal','diskon_persen','cashback') NOT NULL DEFAULT 'diskon_nominal',
  `nilai` decimal(15,2) NOT NULL DEFAULT '0.00',
  `min_belanja` int(11) NOT NULL DEFAULT '0',
  `max_diskon` decimal(15,2) DEFAULT NULL,
  `kuota` int(11) NOT NULL DEFAULT '0',
  `terpakai` int(11) NOT NULL DEFAULT '0',
  `tgl_mulai` datetime NOT NULL,
  `tgl_kadaluarsa` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_voucher_kode` (`kode`),
  KEY `idx_voucher_active` (`is_active`,`tgl_kadaluarsa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `voucher_usages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `nota` varchar(64) NOT NULL,
  `npk_anggota` varchar(32) DEFAULT NULL,
  `nominal_diskon` decimal(15,2) NOT NULL DEFAULT '0.00',
  `used_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usage_voucher` (`voucher_id`),
  KEY `idx_usage_nota` (`nota`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------
-- 4. Header Pembayaran POS (Tunai, Kembalian, EDC, Transfer, Voucher)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `penjualan_pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nota` varchar(64) NOT NULL,
  `metode_pembayaran` varchar(32) NOT NULL,
  `total_tagihan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `nominal_bayar` decimal(15,2) NOT NULL DEFAULT '0.00',
  `kembalian` decimal(15,2) NOT NULL DEFAULT '0.00',
  `kode_voucher` varchar(32) DEFAULT NULL,
  `diskon_voucher` decimal(15,2) NOT NULL DEFAULT '0.00',
  `nomor_referensi` varchar(64) DEFAULT NULL,
  `bank_tujuan` varchar(64) DEFAULT NULL,
  `id_user` varchar(32) NOT NULL,
  `id_karyawan` varchar(32) NOT NULL,
  `waktu_transaksi` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_pembayaran_nota` (`nota`),
  KEY `idx_pembayaran_metode_waktu` (`metode_pembayaran`,`waktu_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------
-- 5. Retur Penjualan (Customer Return) & Items
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `retur_penjualan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_retur` varchar(64) NOT NULL,
  `nota_penjualan` varchar(64) NOT NULL,
  `id_user` varchar(32) NOT NULL,
  `id_karyawan` varchar(32) NOT NULL,
  `tgl_retur` datetime NOT NULL,
  `total_retur` decimal(15,2) NOT NULL DEFAULT '0.00',
  `alasan` text,
  `status` enum('completed','cancelled') NOT NULL DEFAULT 'completed',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_retur_nomor` (`nomor_retur`),
  KEY `idx_retur_nota` (`nota_penjualan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `retur_penjualan_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `retur_id` int(11) NOT NULL,
  `penjualan_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `qty_retur` int(11) NOT NULL,
  `harga_satuan` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `kondisi` enum('layak_jual','rusak') NOT NULL DEFAULT 'layak_jual',
  `kembali_ke_stok` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `idx_retur_items_retur` (`retur_id`),
  KEY `idx_retur_items_barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------
-- 6. Retur Supplier & Items
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `retur_supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_retur` varchar(64) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `goods_receipt_id` int(11) DEFAULT NULL,
  `id_user` varchar(32) NOT NULL,
  `tgl_retur` datetime NOT NULL,
  `total_nominal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `alasan` text,
  `status` enum('draft','final') NOT NULL DEFAULT 'final',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_retur_supp_nomor` (`nomor_retur`),
  KEY `idx_retur_supp_supplier` (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `retur_supplier_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `retur_supplier_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `qty_retur` int(11) NOT NULL,
  `harga_beli` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `alasan_detail` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_retur_supp_items_parent` (`retur_supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------
-- 7. Kolom Tambahan Purchasing (PPN Nominal Faktur & Diskon per Line)
-- ---------------------------------------------------------------------
SET @gr_ppn_exists = (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goods_receipts' AND COLUMN_NAME = 'ppn_nominal'
);
SET @sql_gr_ppn = IF(@gr_ppn_exists = 0,
  'ALTER TABLE `goods_receipts` ADD COLUMN `ppn_nominal` decimal(15,2) NOT NULL DEFAULT "0.00" AFTER `supplier_document`',
  'SELECT 1'
);
PREPARE stmt_gr_ppn FROM @sql_gr_ppn;
EXECUTE stmt_gr_ppn;
DEALLOCATE PREPARE stmt_gr_ppn;

SET @gri_disc_exists = (
  SELECT COUNT(1) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goods_receipt_items' AND COLUMN_NAME = 'discount_type'
);
SET @sql_gri_disc = IF(@gri_disc_exists = 0,
  'ALTER TABLE `goods_receipt_items` 
   ADD COLUMN `discount_type` enum("none","percent","nominal") NOT NULL DEFAULT "none" AFTER `unit_price`,
   ADD COLUMN `discount_value` decimal(15,2) NOT NULL DEFAULT "0.00" AFTER `discount_type`,
   ADD COLUMN `discount_amount` decimal(15,2) NOT NULL DEFAULT "0.00" AFTER `discount_value`,
   ADD COLUMN `subtotal` decimal(15,2) NOT NULL DEFAULT "0.00" AFTER `discount_amount`',
  'SELECT 1'
);
PREPARE stmt_gri_disc FROM @sql_gri_disc;
EXECUTE stmt_gri_disc;
DEALLOCATE PREPARE stmt_gri_disc;

-- ---------------------------------------------------------------------
-- 8. Simpanan Anggota & Buku Mutasi Simpanan (Untuk RAT)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `simpanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `karyawan_id` int(11) NOT NULL,
  `jenis_simpanan` enum('pokok','wajib','sukarela') NOT NULL,
  `saldo` decimal(15,2) NOT NULL DEFAULT '0.00',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_simpanan_anggota_jenis` (`karyawan_id`,`jenis_simpanan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `simpanan_mutasi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_bukti` varchar(64) NOT NULL,
  `karyawan_id` int(11) NOT NULL,
  `jenis_simpanan` enum('pokok','wajib','sukarela') NOT NULL,
  `jenis_mutasi` enum('setor','tarik','bunga','shu','koreksi') NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `saldo_sebelum` decimal(15,2) NOT NULL,
  `saldo_setelah` decimal(15,2) NOT NULL,
  `tgl_transaksi` datetime NOT NULL,
  `keterangan` text,
  `id_user` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_mutasi_bukti` (`nomor_bukti`),
  KEY `idx_mutasi_anggota_tgl` (`karyawan_id`,`tgl_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------
-- 9. Bagan Akun (Chart of Accounts), Jurnal & Periode Akuntansi
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `acc_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(32) NOT NULL,
  `name` varchar(128) NOT NULL,
  `type` enum('asset','liability','equity','income','cogs','expense') NOT NULL,
  `normal_balance` enum('debit','credit') NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_code` (`code`),
  KEY `idx_acc_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_journals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_number` varchar(64) NOT NULL,
  `date` date NOT NULL,
  `reference_type` varchar(32) NOT NULL,
  `reference_id` varchar(64) DEFAULT NULL,
  `description` text,
  `is_posted` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `created_by` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_journal_number` (`journal_number`),
  KEY `idx_journal_date` (`date`),
  KEY `idx_journal_ref` (`reference_type`,`reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_journal_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `journal_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `memo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_item_journal` (`journal_id`),
  KEY `idx_item_account` (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_name` varchar(32) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT '0',
  `closed_at` datetime DEFAULT NULL,
  `closed_by` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_period_dates` (`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_bank_statements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) NOT NULL,
  `statement_number` varchar(64) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `closing_balance` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','reconciled') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL,
  `created_by` varchar(32) NOT NULL,
  `reconciled_at` datetime DEFAULT NULL,
  `reconciled_by` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_bank_statement_number` (`statement_number`),
  UNIQUE KEY `idx_acc_bank_statement_period` (`account_id`,`start_date`,`end_date`),
  KEY `idx_acc_bank_statement_status` (`status`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_bank_statement_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `statement_id` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `external_reference` varchar(128) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `matched_journal_item_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` varchar(32) NOT NULL,
  `matched_at` datetime DEFAULT NULL,
  `matched_by` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_bank_match_journal_item` (`matched_journal_item_id`),
  KEY `idx_acc_bank_statement_item` (`statement_id`,`transaction_date`),
  KEY `idx_acc_bank_statement_match` (`statement_id`,`matched_journal_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---------------------------------------------------------------------
-- 10. Seeding Bagan Akun Standar Koperasi
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `acc_accounts` (`code`, `name`, `type`, `normal_balance`, `parent_id`, `is_active`) VALUES
('1101', 'Kas Kasir Toko', 'asset', 'debit', NULL, 1),
('1102', 'Kas Bank Koperasi', 'asset', 'debit', NULL, 1),
('1103', 'Piutang Belanja Anggota (Potong Gaji)', 'asset', 'debit', NULL, 1),
('1104', 'Persediaan Barang Dagang', 'asset', 'debit', NULL, 1),
('1105', 'PPN Masukan', 'asset', 'debit', NULL, 1),
('2101', 'Hutang Dagang Supplier', 'liability', 'credit', NULL, 1),
('2102', 'Simpanan Sukarela Anggota', 'liability', 'credit', NULL, 1),
('2103', 'PPN Keluaran', 'liability', 'credit', NULL, 1),
('3101', 'Simpanan Pokok Anggota', 'equity', 'credit', NULL, 1),
('3102', 'Simpanan Wajib Anggota', 'equity', 'credit', NULL, 1),
('3103', 'Dana Cadangan Koperasi', 'equity', 'credit', NULL, 1),
('3104', 'SHU Tahun Berjalan', 'equity', 'credit', NULL, 1),
('4101', 'Pendapatan Penjualan Toko', 'income', 'credit', NULL, 1),
('4102', 'Pendapatan Non-Operasional', 'income', 'credit', NULL, 1),
('4201', 'Retur Penjualan', 'income', 'debit', NULL, 1),
('4202', 'Potongan / Diskon Penjualan', 'income', 'debit', NULL, 1),
('5101', 'Harga Pokok Penjualan (HPP)', 'cogs', 'debit', NULL, 1),
('6101', 'Beban Selisih Stok Opname', 'expense', 'debit', NULL, 1),
('6102', 'Beban Kerusakan / Afkir Barang', 'expense', 'debit', NULL, 1),
('6103', 'Beban Operasional Toko', 'expense', 'debit', NULL, 1);
