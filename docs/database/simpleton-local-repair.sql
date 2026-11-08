-- Local simpleton repair. Back up the target before use.
-- Missing columns and views are added; existing tables and rows are retained.
-- Unknown employee join dates remain NULL.
-- Missing receipt subtotals are reconstructed from quantity and unit price.
-- purchase_order_list is replaced using the current project migration.
-- New views use the executing account as their SQL SECURITY DEFINER.
-- MySQL DDL commits implicitly: stop and inspect on errors.
USE `simpleton`;
SET SESSION sql_mode = CONCAT(@@sql_mode, ',', 'STRICT_ALL_TABLES');

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'karyawan' AND COLUMN_NAME = 'join_date'), 'ALTER TABLE `karyawan` ADD COLUMN `join_date` DATE DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'npk'), 'ALTER TABLE `users` ADD COLUMN `npk` varchar(32) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'status'), 'ALTER TABLE `purchase_orders` ADD COLUMN `status` enum(''open'',''closed'',''cancelled'') NOT NULL DEFAULT ''open''', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'closed_at'), 'ALTER TABLE `purchase_orders` ADD COLUMN `closed_at` datetime DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'closed_by'), 'ALTER TABLE `purchase_orders` ADD COLUMN `closed_by` varchar(32) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND COLUMN_NAME = 'close_reason'), 'ALTER TABLE `purchase_orders` ADD COLUMN `close_reason` varchar(500) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_items' AND COLUMN_NAME = 'is_adhoc'), 'ALTER TABLE `purchase_order_items` ADD COLUMN `is_adhoc` tinyint(1) NOT NULL DEFAULT ''0''', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_items' AND COLUMN_NAME = 'authorized_by'), 'ALTER TABLE `purchase_order_items` ADD COLUMN `authorized_by` varchar(32) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_items' AND COLUMN_NAME = 'authorized_at'), 'ALTER TABLE `purchase_order_items` ADD COLUMN `authorized_at` datetime DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goods_receipts' AND COLUMN_NAME = 'ppn_nominal'), 'ALTER TABLE `goods_receipts` ADD COLUMN `ppn_nominal` decimal(15,2) NOT NULL DEFAULT ''0.00''', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goods_receipt_items' AND COLUMN_NAME = 'discount_type'), 'ALTER TABLE `goods_receipt_items` ADD COLUMN `discount_type` enum(''none'',''percent'',''nominal'') NOT NULL DEFAULT ''none''', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goods_receipt_items' AND COLUMN_NAME = 'discount_value'), 'ALTER TABLE `goods_receipt_items` ADD COLUMN `discount_value` decimal(15,2) NOT NULL DEFAULT ''0.00''', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goods_receipt_items' AND COLUMN_NAME = 'discount_amount'), 'ALTER TABLE `goods_receipt_items` ADD COLUMN `discount_amount` decimal(15,2) NOT NULL DEFAULT ''0.00''', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goods_receipt_items' AND COLUMN_NAME = 'subtotal'), 'ALTER TABLE `goods_receipt_items` ADD COLUMN `subtotal` DECIMAL(15,2) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'goods_receipt_items' AND COLUMN_NAME = 'average_cost_after'), 'ALTER TABLE `goods_receipt_items` ADD COLUMN `average_cost_after` decimal(15,4) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'karyawan' AND COLUMN_NAME = 'is_active'), 'ALTER TABLE `karyawan` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'karyawan' AND COLUMN_NAME = 'nama_bank'), 'ALTER TABLE `karyawan` ADD COLUMN `nama_bank` VARCHAR(64) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'karyawan' AND COLUMN_NAME = 'nomor_rekening'), 'ALTER TABLE `karyawan` ADD COLUMN `nomor_rekening` VARCHAR(64) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'karyawan' AND COLUMN_NAME = 'resign_date'), 'ALTER TABLE `karyawan` ADD COLUMN `resign_date` DATE DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

UPDATE `goods_receipt_items` SET `subtotal` = `qty_received` * `unit_price` WHERE `subtotal` IS NULL AND `unit_price` IS NOT NULL;
ALTER TABLE `goods_receipt_items` MODIFY COLUMN `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00;

SET @repair_sql = IF(EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'email' AND IS_NULLABLE = 'NO'), 'ALTER TABLE `users` MODIFY COLUMN `email` VARCHAR(100) DEFAULT NULL', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_username_unique'), 'ALTER TABLE `users` ADD UNIQUE KEY `users_username_unique` (`username`)', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_email_unique'), 'ALTER TABLE `users` ADD UNIQUE KEY `users_email_unique` (`email`)', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'users_npk_unique'), 'ALTER TABLE `users` ADD UNIQUE KEY `users_npk_unique` (`npk`)', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND INDEX_NAME = 'idx_purchase_orders_status'), 'ALTER TABLE `purchase_orders` ADD KEY `idx_purchase_orders_status` (`status`)', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

CREATE OR REPLACE VIEW `purchase_order_list` AS
SELECT
	po.`id`,
	po.`order_number`,
	po.`ordered_at`,
	supplier.`name` AS `supplier_name`,
	COALESCE((
		SELECT SUM(item.`qty_ordered`)
		FROM `purchase_order_items` item
		WHERE item.`purchase_order_id` = po.`id` AND item.`is_adhoc` = 0
	), 0) AS `ordered_qty`,
	COALESCE((
		SELECT SUM(receipt_item.`qty_received`)
		FROM `purchase_order_items` item
		JOIN `goods_receipt_items` receipt_item ON receipt_item.`purchase_order_item_id` = item.`id`
		WHERE item.`purchase_order_id` = po.`id`
	), 0) AS `received_qty`,
	CASE
		WHEN COALESCE((
			SELECT SUM(receipt_item.`qty_received`)
			FROM `purchase_order_items` item
			JOIN `goods_receipt_items` receipt_item ON receipt_item.`purchase_order_item_id` = item.`id`
			WHERE item.`purchase_order_id` = po.`id`
		), 0) = 0 THEN 'Belum diterima'
		WHEN (
			SELECT COUNT(*) FROM `purchase_order_items` item
			WHERE item.`purchase_order_id` = po.`id`
				AND item.`is_adhoc` = 0
				AND item.`qty_ordered` < COALESCE((
					SELECT SUM(receipt_item.`qty_received`)
					FROM `goods_receipt_items` receipt_item
					WHERE receipt_item.`purchase_order_item_id` = item.`id`
				), 0)
		) > 0 THEN 'Lebih diterima'
		WHEN (
			SELECT COUNT(*) FROM `purchase_order_items` item
			WHERE item.`purchase_order_id` = po.`id`
				AND item.`is_adhoc` = 0
				AND item.`qty_ordered` > COALESCE((
					SELECT SUM(receipt_item.`qty_received`)
					FROM `goods_receipt_items` receipt_item
					WHERE receipt_item.`purchase_order_item_id` = item.`id`
				), 0)
		) > 0 THEN 'Diterima sebagian'
		ELSE 'Sesuai'
	END AS `receipt_status`,
	(
		SELECT COUNT(*) FROM `purchase_order_items` item
		WHERE item.`purchase_order_id` = po.`id`
			AND item.`is_adhoc` = 0
			AND item.`qty_ordered` > COALESCE((
				SELECT SUM(receipt_item.`qty_received`)
				FROM `goods_receipt_items` receipt_item
				WHERE receipt_item.`purchase_order_item_id` = item.`id`
			), 0)
	) AS `incomplete_items`,
	(
		SELECT COUNT(*) FROM `purchase_order_items` item
		WHERE item.`purchase_order_id` = po.`id`
			AND item.`is_adhoc` = 0
			AND item.`qty_ordered` < COALESCE((
				SELECT SUM(receipt_item.`qty_received`)
				FROM `goods_receipt_items` receipt_item
				WHERE receipt_item.`purchase_order_item_id` = item.`id`
			), 0)
	) AS `excess_items`,
	po.`printed_at`, po.`status`, po.`closed_at`, po.`closed_by`, po.`close_reason`
FROM `purchase_orders` po
JOIN `suppliers` supplier ON supplier.`id` = po.`supplier_id`;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_payment_details'), 'CREATE SQL SECURITY DEFINER VIEW `purchase_order_payment_details` AS select `po`.`id` AS `id`,`po`.`order_number` AS `order_number`,`po`.`ordered_at` AS `ordered_at`,`po`.`supplier_name` AS `supplier_name`,`po`.`ordered_qty` AS `ordered_qty`,`po`.`received_qty` AS `received_qty`,`po`.`receipt_status` AS `receipt_status`,`po`.`incomplete_items` AS `incomplete_items`,`po`.`excess_items` AS `excess_items`,`po`.`printed_at` AS `printed_at`,`po`.`status` AS `status`,`po`.`closed_at` AS `closed_at`,`po`.`closed_by` AS `closed_by`,`po`.`close_reason` AS `close_reason`,(select count(0) from `goods_receipts` `receipt` where (`receipt`.`purchase_order_id` = `po`.`id`)) AS `receipt_count`,(coalesce((select sum((case when ((`receipt_item`.`subtotal` > 0) or ((`receipt_item`.`qty_received` * coalesce(`receipt_item`.`unit_price`,0)) = 0)) then `receipt_item`.`subtotal` else greatest(((`receipt_item`.`qty_received` * coalesce(`receipt_item`.`unit_price`,0)) - coalesce(`receipt_item`.`discount_amount`,0)),0) end)) from (`goods_receipts` `receipt` join `goods_receipt_items` `receipt_item` on((`receipt_item`.`goods_receipt_id` = `receipt`.`id`))) where (`receipt`.`purchase_order_id` = `po`.`id`)),0) + coalesce((select sum(`receipt`.`ppn_nominal`) from `goods_receipts` `receipt` where (`receipt`.`purchase_order_id` = `po`.`id`)),0)) AS `invoice_amount`,coalesce((select sum(`return_header`.`total_nominal`) from (`retur_supplier` `return_header` join `goods_receipts` `receipt` on((`receipt`.`id` = `return_header`.`goods_receipt_id`))) where ((`receipt`.`purchase_order_id` = `po`.`id`) and (`return_header`.`status` = ''final''))),0) AS `return_amount`,coalesce((select sum(`payment`.`amount`) from (`acc_cash_transactions` `payment` join `goods_receipts` `receipt` on((`receipt`.`id` = `payment`.`goods_receipt_id`))) where ((`receipt`.`purchase_order_id` = `po`.`id`) and (`payment`.`transaction_type` = ''supplier_payment'') and (`payment`.`status` = ''posted''))),0) AS `paid_amount`,coalesce((select sum(`payment`.`amount`) from (`acc_cash_transactions` `payment` join `goods_receipts` `receipt` on((`receipt`.`id` = `payment`.`goods_receipt_id`))) where ((`receipt`.`purchase_order_id` = `po`.`id`) and (`payment`.`transaction_type` = ''supplier_payment'') and (`payment`.`status` = ''pending''))),0) AS `pending_payment_amount` from `purchase_order_list` `po`', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_order_payment_list'), 'CREATE SQL SECURITY DEFINER VIEW `purchase_order_payment_list` AS select `payment_details`.`id` AS `id`,`payment_details`.`order_number` AS `order_number`,`payment_details`.`ordered_at` AS `ordered_at`,`payment_details`.`supplier_name` AS `supplier_name`,`payment_details`.`ordered_qty` AS `ordered_qty`,`payment_details`.`received_qty` AS `received_qty`,`payment_details`.`receipt_status` AS `receipt_status`,`payment_details`.`incomplete_items` AS `incomplete_items`,`payment_details`.`excess_items` AS `excess_items`,`payment_details`.`printed_at` AS `printed_at`,`payment_details`.`status` AS `status`,`payment_details`.`closed_at` AS `closed_at`,`payment_details`.`closed_by` AS `closed_by`,`payment_details`.`close_reason` AS `close_reason`,`payment_details`.`receipt_count` AS `receipt_count`,`payment_details`.`invoice_amount` AS `invoice_amount`,`payment_details`.`return_amount` AS `return_amount`,`payment_details`.`paid_amount` AS `paid_amount`,`payment_details`.`pending_payment_amount` AS `pending_payment_amount`,greatest(((`payment_details`.`invoice_amount` - `payment_details`.`return_amount`) - `payment_details`.`paid_amount`),0) AS `outstanding_amount`,(case when (`payment_details`.`receipt_count` = 0) then ''Belum ada tagihan'' when (`payment_details`.`invoice_amount` <= 0.01) then ''Perlu cek nilai faktur'' when (greatest(((`payment_details`.`invoice_amount` - `payment_details`.`return_amount`) - `payment_details`.`paid_amount`),0) <= 0.01) then ''Lunas'' when ((`payment_details`.`pending_payment_amount` > 0) and (`payment_details`.`paid_amount` > 0)) then ''Sebagian dibayar - menunggu approval'' when (`payment_details`.`pending_payment_amount` > 0) then ''Menunggu approval'' when (`payment_details`.`paid_amount` > 0) then ''Sebagian dibayar'' else ''Belum dibayar'' end) AS `payment_status` from `purchase_order_payment_details` `payment_details`', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_bot_anggota'), 'CREATE SQL SECURITY DEFINER VIEW `v_bot_anggota` AS select `karyawan`.`id` AS `id`,`karyawan`.`npk` AS `npk`,`karyawan`.`nama` AS `nama` from `karyawan`', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_bot_belanja'), 'CREATE SQL SECURITY DEFINER VIEW `v_bot_belanja` AS select `k`.`id` AS `karyawan_id`,`p`.`nota` AS `nota`,`p`.`metode_pembayaran` AS `metode_pembayaran`,`p`.`total_tagihan` AS `total_tagihan`,`p`.`waktu_transaksi` AS `waktu_transaksi` from (`penjualan_pembayaran` `p` join `karyawan` `k` on((convert(`k`.`npk` using utf8) = `p`.`id_karyawan`)))', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_bot_financial_summary'), 'CREATE SQL SECURITY DEFINER VIEW `v_bot_financial_summary` AS select curdate() AS `as_of_date`,coalesce((select sum((case when (`a`.`normal_balance` = ''debit'') then (`i`.`debit` - `i`.`credit`) else (`i`.`credit` - `i`.`debit`) end)) from ((`acc_accounts` `a` join `acc_journal_items` `i` on((`i`.`account_id` = `a`.`id`))) join `acc_journals` `j` on((`j`.`id` = `i`.`journal_id`))) where ((`a`.`is_active` = 1) and (`a`.`type` = ''asset'') and (`j`.`is_posted` = 1) and (`j`.`date` <= curdate()))),0) AS `assets`,coalesce((select sum((case when (`a`.`normal_balance` = ''debit'') then (`i`.`debit` - `i`.`credit`) else (`i`.`credit` - `i`.`debit`) end)) from ((`acc_accounts` `a` join `acc_journal_items` `i` on((`i`.`account_id` = `a`.`id`))) join `acc_journals` `j` on((`j`.`id` = `i`.`journal_id`))) where ((`a`.`is_active` = 1) and (`a`.`type` = ''liability'') and (`j`.`is_posted` = 1) and (`j`.`date` <= curdate()))),0) AS `liabilities`,coalesce(((select sum((case when (`a`.`normal_balance` = ''debit'') then (`i`.`debit` - `i`.`credit`) else (`i`.`credit` - `i`.`debit`) end)) from ((`acc_accounts` `a` join `acc_journal_items` `i` on((`i`.`account_id` = `a`.`id`))) join `acc_journals` `j` on((`j`.`id` = `i`.`journal_id`))) where ((`a`.`is_active` = 1) and (`a`.`type` = ''equity'') and (`j`.`is_posted` = 1) and (`j`.`date` <= curdate()))) + coalesce((select sum((`i`.`credit` - `i`.`debit`)) from ((`acc_accounts` `a` join `acc_journal_items` `i` on((`i`.`account_id` = `a`.`id`))) join `acc_journals` `j` on((`j`.`id` = `i`.`journal_id`))) where ((`a`.`is_active` = 1) and (`a`.`type` in (''income'',''cogs'',''expense'')) and (`j`.`is_posted` = 1) and (`j`.`date` <= curdate()))),0)),0) AS `equity`,coalesce((select sum((`i`.`credit` - `i`.`debit`)) from ((`acc_accounts` `a` join `acc_journal_items` `i` on((`i`.`account_id` = `a`.`id`))) join `acc_journals` `j` on((`j`.`id` = `i`.`journal_id`))) where ((`a`.`is_active` = 1) and (`a`.`type` = ''income'') and (`j`.`is_posted` = 1) and (`j`.`date` >= date_format(curdate(),''%Y-01-01'')) and (`j`.`date` <= curdate()))),0) AS `income_ytd`,coalesce((select sum((`i`.`debit` - `i`.`credit`)) from ((`acc_accounts` `a` join `acc_journal_items` `i` on((`i`.`account_id` = `a`.`id`))) join `acc_journals` `j` on((`j`.`id` = `i`.`journal_id`))) where ((`a`.`is_active` = 1) and (`a`.`type` in (''cogs'',''expense'')) and (`j`.`is_posted` = 1) and (`j`.`date` >= date_format(curdate(),''%Y-01-01'')) and (`j`.`date` <= curdate()))),0) AS `expenses_ytd`,coalesce((select sum((`i`.`debit` - `i`.`credit`)) from ((`acc_accounts` `a` join `acc_journal_items` `i` on((`i`.`account_id` = `a`.`id`))) join `acc_journals` `j` on((`j`.`id` = `i`.`journal_id`))) where ((`a`.`is_active` = 1) and (`a`.`code` in (''1101'',''1102'',''1106'',''1107'')) and (`j`.`is_posted` = 1) and (`j`.`date` >= date_format(curdate(),''%Y-01-01'')) and (`j`.`date` <= curdate()))),0) AS `cash_change_ytd`', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_bot_login'), 'CREATE SQL SECURITY DEFINER VIEW `v_bot_login` AS select `k`.`id` AS `karyawan_id`,`k`.`npk` AS `npk`,`u`.`telegram_pin` AS `pin` from (`users` `u` join `karyawan` `k` on((convert(`k`.`npk` using utf8) = `u`.`npk`))) where ((`u`.`active` = 1) and (`u`.`telegram_pin` is not null) and (`u`.`telegram_pin` <> ''''))', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_bot_products'), 'CREATE SQL SECURITY DEFINER VIEW `v_bot_products` AS select `stok`.`barcode` AS `barcode`,`stok`.`nama` AS `nama`,`stok`.`jumlah` AS `jumlah` from `stok`', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_bot_shu'), 'CREATE SQL SECURITY DEFINER VIEW `v_bot_shu` AS select `simpanan_mutasi`.`karyawan_id` AS `karyawan_id`,`simpanan_mutasi`.`jenis_simpanan` AS `jenis_simpanan`,`simpanan_mutasi`.`nominal` AS `nominal`,`simpanan_mutasi`.`tgl_transaksi` AS `tgl_transaksi`,`simpanan_mutasi`.`keterangan` AS `keterangan` from `simpanan_mutasi` where (`simpanan_mutasi`.`jenis_mutasi` = ''shu'')', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_bot_simpanan'), 'CREATE SQL SECURITY DEFINER VIEW `v_bot_simpanan` AS select `simpanan`.`karyawan_id` AS `karyawan_id`,`simpanan`.`jenis_simpanan` AS `jenis_simpanan`,`simpanan`.`saldo` AS `saldo`,`simpanan`.`updated_at` AS `updated_at` from `simpanan`', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

SET @repair_sql = IF(NOT EXISTS (SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'v_bot_tagihan'), 'CREATE SQL SECURITY DEFINER VIEW `v_bot_tagihan` AS select `k`.`id` AS `karyawan_id`,`p`.`nota` AS `nota`,`p`.`total_tagihan` AS `total_tagihan`,`p`.`waktu_transaksi` AS `waktu_transaksi` from (`penjualan_pembayaran` `p` join `karyawan` `k` on((convert(`k`.`npk` using utf8) = `p`.`id_karyawan`))) where ((`p`.`metode_pembayaran` = ''potong_gaji'') and (`p`.`waktu_transaksi` >= (now() - interval 60 day)))', 'SELECT 1');
PREPARE repair_stmt FROM @repair_sql;
EXECUTE repair_stmt;
DEALLOCATE PREPARE repair_stmt;

