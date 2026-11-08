-- Schema-only migration from simpleton (2).sql to simpleton-latest.sql.
-- No existing table is dropped and no dump rows are imported.
-- Back up the database before running; MySQL DDL implicitly commits.
-- Existing PPN values cannot be inferred from the old schema; old rows are initialized to 0.

USE `simpleton`;

-- Add columns to tables present in the original dump.
ALTER TABLE `penjualan`
  ADD COLUMN `hpp` DECIMAL(15,4) DEFAULT NULL AFTER `harga`,
  ADD COLUMN `ppn` FLOAT NULL DEFAULT NULL AFTER `jumlah`,
  ADD COLUMN `dpp_satuan` DECIMAL(15,2) DEFAULT NULL AFTER `ppn`,
  ADD COLUMN `ppn_satuan` DECIMAL(15,2) DEFAULT NULL AFTER `dpp_satuan`,
  ADD COLUMN `metode_pembayaran` VARCHAR(20) DEFAULT NULL AFTER `ppn_satuan`;
UPDATE `penjualan` SET `ppn` = 0 WHERE `ppn` IS NULL;
ALTER TABLE `penjualan` MODIFY COLUMN `ppn` FLOAT NOT NULL;
ALTER TABLE `penjualan` ADD KEY `idx_penjualan_metode_tgl` (`metode_pembayaran`, `tgl`);

ALTER TABLE `stok` ADD COLUMN `ppn` FLOAT NULL DEFAULT NULL AFTER `distributor`;
UPDATE `stok` SET `ppn` = 0 WHERE `ppn` IS NULL;
ALTER TABLE `stok` MODIFY COLUMN `ppn` FLOAT NOT NULL;

ALTER TABLE `users` ADD COLUMN `telegram_pin` VARCHAR(6) DEFAULT NULL;

-- Add tables absent from the original dump, ordered by foreign-key dependency.

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

CREATE TABLE IF NOT EXISTS `acc_cash_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fund_id` int(11) NOT NULL,
  `closing_date` date NOT NULL,
  `system_balance` decimal(15,2) NOT NULL,
  `counted_balance` decimal(15,2) NOT NULL,
  `variance` decimal(15,2) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `closed_by` varchar(32) NOT NULL,
  `closed_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_cash_closing_fund_date` (`fund_id`,`closing_date`),
  KEY `idx_acc_cash_closing_date` (`closing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_cash_funds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fund_code` varchar(32) NOT NULL,
  `name` varchar(100) NOT NULL,
  `fund_type` enum('cashier','main','petty') NOT NULL,
  `fund_slot` varchar(16) DEFAULT NULL,
  `custodian_user_id` varchar(32) DEFAULT NULL,
  `imprest_limit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL,
  `created_by` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_cash_fund_code` (`fund_code`),
  UNIQUE KEY `idx_acc_cash_fund_slot` (`fund_slot`),
  KEY `idx_acc_cash_fund_type_active` (`fund_type`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `acc_cash_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(64) NOT NULL,
  `fund_id` int(11) NOT NULL,
  `transaction_type` enum('funding','expense','supplier_payment') NOT NULL,
  `transaction_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `funding_source_account_id` int(11) DEFAULT NULL,
  `expense_account_id` int(11) DEFAULT NULL,
  `goods_receipt_id` int(11) DEFAULT NULL,
  `counterparty` varchar(128) DEFAULT NULL,
  `description` varchar(500) NOT NULL,
  `evidence_reference` varchar(128) NOT NULL,
  `proof_filename` varchar(255) DEFAULT NULL,
  `status` enum('pending','posted','rejected') NOT NULL DEFAULT 'pending',
  `journal_id` int(11) DEFAULT NULL,
  `created_by` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL,
  `approved_by` varchar(32) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_acc_cash_transaction_number` (`transaction_number`),
  UNIQUE KEY `idx_acc_cash_transaction_journal` (`journal_id`),
  KEY `idx_acc_cash_transaction_fund_date` (`fund_id`,`transaction_date`,`status`),
  KEY `idx_acc_cash_transaction_status_date` (`status`,`transaction_date`),
  KEY `idx_acc_cash_transaction_receipt` (`goods_receipt_id`,`status`),
  KEY `idx_acc_cash_transaction_type_date` (`transaction_type`,`transaction_date`,`id`),
  KEY `idx_acc_cash_transaction_receipt_type_status` (`goods_receipt_id`,`transaction_type`,`status`)
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

CREATE TABLE IF NOT EXISTS `inventory_cost_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `unit_cost` decimal(15,4) NOT NULL,
  `previous_average_cost` decimal(15,4) DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `created_by` varchar(32) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_cost_history_product_date` (`product_id`,`recorded_at`),
  KEY `idx_inventory_cost_history_barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `inventory_costs` (
  `product_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL DEFAULT '',
  `average_cost` decimal(15,4) NOT NULL DEFAULT '0.0000',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `penjualan_pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nota` varchar(64) NOT NULL,
  `checkout_request_id` varchar(64) DEFAULT NULL,
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
  UNIQUE KEY `uq_pembayaran_checkout_request` (`checkout_request_id`),
  KEY `idx_pembayaran_metode_waktu` (`metode_pembayaran`,`waktu_transaksi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `penjualan_tiket_pesawat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_booking` varchar(32) NOT NULL,
  `tanggal_keberangkatan` datetime NOT NULL,
  `tanggal_kedatangan` datetime NOT NULL,
  `flight_number` varchar(64) NOT NULL,
  `asal` varchar(32) NOT NULL,
  `tujuan` varchar(32) NOT NULL,
  `maskapai` varchar(32) NOT NULL,
  `fasilitas` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `penumpang_tiket_pesawat` (
  `kode_booking` varchar(32) NOT NULL,
  `nama` varchar(64) NOT NULL,
  `no_ktp` varchar(64) NOT NULL,
  `no_telepon` varchar(32) NOT NULL,
  `harga` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `pos_receipt_sequences` (
  `business_date` date NOT NULL,
  `npk` varchar(32) NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`business_date`,`npk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE IF NOT EXISTS `retur_penjualan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_retur` varchar(64) NOT NULL,
  `nota_penjualan` varchar(64) NOT NULL,
  `id_user` varchar(32) NOT NULL,
  `id_karyawan` varchar(32) NOT NULL,
  `tgl_retur` datetime NOT NULL,
  `total_retur` decimal(15,2) NOT NULL DEFAULT '0.00',
  `alasan` text,
  `status` enum('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by` varchar(32) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejected_by` varchar(32) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_retur_nomor` (`nomor_retur`),
  KEY `idx_retur_nota` (`nota_penjualan`),
  KEY `idx_retur_tanggal_id` (`tgl_retur`,`id`),
  KEY `idx_retur_status_id` (`status`,`id`)
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

CREATE TABLE IF NOT EXISTS `retur_supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nomor_retur` varchar(64) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `goods_receipt_id` int(11) DEFAULT NULL,
  `id_user` varchar(32) NOT NULL,
  `tgl_retur` datetime NOT NULL,
  `total_nominal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `ppn_nominal` decimal(15,2) NOT NULL DEFAULT '0.00',
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

CREATE TABLE IF NOT EXISTS `sales_billing_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_id` int(11) NOT NULL,
  `nota` varchar(64) NOT NULL,
  `correct_source` enum('system','physical') NOT NULL,
  `system_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `correct_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `note` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `revised_by` varchar(32) NOT NULL,
  `revised_at` datetime NOT NULL,
  `decided_by` varchar(32) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_billing_adjust_period_nota` (`period_id`,`nota`),
  KEY `idx_sales_billing_adjust_status` (`period_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_billing_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_id` int(11) NOT NULL,
  `nota` varchar(64) NOT NULL,
  `npk` varchar(32) NOT NULL,
  `employee_name` varchar(128) DEFAULT NULL,
  `transaction_date` datetime NOT NULL,
  `total_sales` decimal(15,2) NOT NULL DEFAULT '0.00',
  `approved_returns` decimal(15,2) NOT NULL DEFAULT '0.00',
  `billable_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(20) NOT NULL DEFAULT '',
  `adjustment_note` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_billing_period_nota` (`period_id`,`nota`),
  KEY `idx_sales_billing_period_npk` (`period_id`,`npk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_billing_periods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_name` varchar(64) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('open','finalized') NOT NULL DEFAULT 'open',
  `created_by` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL,
  `finalized_by` varchar(32) DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_billing_period_dates` (`start_date`,`end_date`),
  KEY `idx_sales_billing_period_status` (`status`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_billing_scans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_id` int(11) NOT NULL,
  `nota` varchar(64) NOT NULL,
  `scan_time` datetime NOT NULL,
  `scanned_by` varchar(32) NOT NULL,
  `system_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `nota_total` decimal(15,2) DEFAULT NULL,
  `status` enum('match','mismatch') NOT NULL DEFAULT 'match',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_billing_scan_period_nota` (`period_id`,`nota`),
  KEY `idx_sales_billing_scan_period` (`period_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_tax_settings` (
  `id` tinyint(3) unsigned NOT NULL,
  `rate_percent` decimal(5,2) NOT NULL DEFAULT '11.00',
  `updated_by` varchar(32) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_validation_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `run_id` int(11) NOT NULL,
  `nota` varchar(64) NOT NULL,
  `npk` varchar(32) NOT NULL,
  `transaction_date` date NOT NULL,
  `status` enum('valid','invalid') NOT NULL,
  `issues` text,
  `total_sales` decimal(15,2) NOT NULL DEFAULT '0.00',
  `approved_returns` decimal(15,2) NOT NULL DEFAULT '0.00',
  `billable_total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `payment_method` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sales_validation_run_nota` (`run_id`,`nota`),
  KEY `idx_sales_validation_nota` (`nota`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `sales_validation_runs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `period_id` int(11) NOT NULL,
  `validation_mode` enum('period','week','nota','cutoff') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `nota_filter` varchar(64) DEFAULT NULL,
  `result` enum('passed','failed') NOT NULL,
  `checked_count` int(11) NOT NULL DEFAULT '0',
  `created_by` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sales_validation_period` (`period_id`,`id`),
  KEY `idx_sales_validation_result` (`period_id`,`result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE IF NOT EXISTS `stock_opnames` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `opname_number` varchar(32) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_opnames_number` (`opname_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `stock_opname_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_opname_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `product_name` varchar(64) NOT NULL,
  `system_qty` int(11) NOT NULL,
  `counted_qty` int(11) DEFAULT NULL,
  `unit_cost` decimal(15,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stock_opname_product` (`stock_opname_id`,`product_id`),
  KEY `idx_stock_opname_items_product` (`product_id`),
  CONSTRAINT `fk_stock_opname_items_opname` FOREIGN KEY (`stock_opname_id`) REFERENCES `stock_opnames` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `phone` varchar(32) NOT NULL,
  `address` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_suppliers_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(32) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `ordered_at` datetime NOT NULL,
  `printed_at` datetime DEFAULT NULL,
  `notes` text,
  `status` enum('open','closed','cancelled') NOT NULL DEFAULT 'open',
  `closed_at` datetime DEFAULT NULL,
  `closed_by` varchar(32) DEFAULT NULL,
  `close_reason` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_orders_number` (`order_number`),
  KEY `idx_purchase_orders_supplier` (`supplier_id`),
  KEY `idx_purchase_orders_status` (`status`),
  CONSTRAINT `fk_purchase_orders_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `goods_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(32) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `supplier_document` varchar(64) DEFAULT NULL,
  `ppn_nominal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `received_at` datetime NOT NULL,
  `notes` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_goods_receipts_number` (`receipt_number`),
  KEY `idx_goods_receipts_order` (`purchase_order_id`),
  CONSTRAINT `fk_goods_receipts_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `barcode` varchar(64) NOT NULL,
  `product_name` varchar(64) NOT NULL,
  `qty_ordered` int(11) NOT NULL,
  `purchase_price` int(11) NOT NULL,
  `is_adhoc` tinyint(1) NOT NULL DEFAULT '0',
  `authorized_by` varchar(32) DEFAULT NULL,
  `authorized_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_po_items_order` (`purchase_order_id`),
  KEY `idx_po_items_product` (`product_id`),
  CONSTRAINT `fk_po_items_order` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `fk_po_items_product` FOREIGN KEY (`product_id`) REFERENCES `stok` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `goods_receipt_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_receipt_id` int(11) NOT NULL,
  `purchase_order_item_id` int(11) NOT NULL,
  `qty_received` int(11) NOT NULL,
  `unit_price` int(11) DEFAULT NULL,
  `discount_type` enum('none','percent','nominal') NOT NULL DEFAULT 'none',
  `discount_value` decimal(15,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `subtotal` decimal(15,2) NOT NULL DEFAULT '0.00',
  `average_cost_after` decimal(15,4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_receipt_items_receipt` (`goods_receipt_id`),
  KEY `idx_receipt_items_po_item` (`purchase_order_item_id`),
  CONSTRAINT `fk_receipt_items_po_item` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`),
  CONSTRAINT `fk_receipt_items_receipt` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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

CREATE TABLE IF NOT EXISTS `voucher_coupons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `serial_code` varchar(32) NOT NULL,
  `assigned_npk` varchar(32) DEFAULT NULL,
  `status` enum('available','used','void') NOT NULL DEFAULT 'available',
  `used_nota` varchar(64) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` varchar(32) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voucher_coupon_serial` (`serial_code`),
  UNIQUE KEY `uq_voucher_coupon_nota` (`used_nota`),
  KEY `idx_voucher_coupon_available` (`voucher_id`,`status`),
  KEY `idx_voucher_coupon_npk` (`assigned_npk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `voucher_usages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `voucher_coupon_id` int(11) DEFAULT NULL,
  `nota` varchar(64) NOT NULL,
  `npk_anggota` varchar(32) DEFAULT NULL,
  `nominal_diskon` decimal(15,2) NOT NULL DEFAULT '0.00',
  `used_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usage_voucher` (`voucher_id`),
  KEY `idx_usage_nota` (`nota`),
  KEY `idx_usage_coupon` (`voucher_coupon_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

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
