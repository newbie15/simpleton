-- Data demo Petty Cash dari sheet PENGELUARAN Agustus 2026.
-- Jalankan ulang aman. Semua transaksi tetap pending sampai disetujui di aplikasi.
-- created_by=2 adalah akun non-approver lokal agar administrator dapat menguji persetujuan.

INSERT IGNORE INTO `acc_cash_funds`
	(`fund_code`, `name`, `fund_type`, `fund_slot`, `custodian_user_id`,
	 `imprest_limit`, `is_active`, `created_at`, `created_by`)
VALUES
	('DEMO-MAIN26', 'DEMO Kas Utama untuk Petty Cash (Agustus 2026)', 'main', 'main', NULL, 0.00, 1, NOW(), '1'),
	('DEMO-SMBK26', 'DEMO Petty Cash Sembako & ATK (Agustus 2026)', 'petty', NULL, NULL, 4573000.00, 1, NOW(), '1'),
	('DEMO-DEPO26', 'DEMO Petty Cash Depo (Agustus 2026)', 'petty', NULL, NULL, 1820000.00, 1, NOW(), '1');

-- Pengeluaran dimasukkan lebih dulu agar permintaan isi dana tampil paling atas dan disetujui lebih dahulu.
INSERT IGNORE INTO `acc_cash_transactions`
	(`transaction_number`, `fund_id`, `transaction_type`, `transaction_date`, `amount`,
	 `funding_source_account_id`, `expense_account_id`, `goods_receipt_id`, `counterparty`,
	 `description`, `evidence_reference`, `proof_filename`, `status`, `created_by`, `created_at`)
SELECT x.transaction_number, fund.id, 'expense', '2026-08-31', x.amount,
	NULL, account.id, NULL, NULL, x.description, x.evidence_reference, NULL, 'pending', '2', NOW()
FROM (
	SELECT 'DEMO-AGU26-SMBK-01' transaction_number, 'DEMO-SMBK26' fund_code, '6103' account_code, 1000000.00 amount, 'Oprasional Sembako Periode Agustus 2026' description, 'EXCEL-AGU26-SMBK-01' evidence_reference
	UNION ALL SELECT 'DEMO-AGU26-SMBK-02', 'DEMO-SMBK26', '6103', 95000.00, 'Pembelian Bensin u/ Genset', 'EXCEL-AGU26-SMBK-02'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-03', 'DEMO-SMBK26', '6103', 150000.00, 'Biaya Service AC', 'EXCEL-AGU26-SMBK-03'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-04', 'DEMO-SMBK26', '6103', 550000.00, 'Biaya Belanja Borneo', 'EXCEL-AGU26-SMBK-04'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-05', 'DEMO-SMBK26', '6103', 80000.00, 'Pembelian Bendera', 'EXCEL-AGU26-SMBK-05'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-06', 'DEMO-SMBK26', '6103', 300000.00, 'Pembelian Solar', 'EXCEL-AGU26-SMBK-06'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-07', 'DEMO-SMBK26', '6103', 5000.00, 'Biaya Parkir', 'EXCEL-AGU26-SMBK-07'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-08', 'DEMO-SMBK26', '6103', 60000.00, 'Exfood Bongkaran', 'EXCEL-AGU26-SMBK-08'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-09', 'DEMO-SMBK26', '6103', 60000.00, 'Exfood Meeting Pengurus', 'EXCEL-AGU26-SMBK-09'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-10', 'DEMO-SMBK26', '6103', 400000.00, 'Biaya Belanja Borneo', 'EXCEL-AGU26-SMBK-10'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-11', 'DEMO-SMBK26', '6103', 326000.00, 'Pembelian Catridge dan Kertas Kasir', 'EXCEL-AGU26-SMBK-11'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-12', 'DEMO-SMBK26', '6103', 10000.00, 'Biaya Parkir', 'EXCEL-AGU26-SMBK-12'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-13', 'DEMO-SMBK26', '6103', 90000.00, 'Exfood Bongkaran', 'EXCEL-AGU26-SMBK-13'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-14', 'DEMO-SMBK26', '6103', 500000.00, 'Pembelian Solar', 'EXCEL-AGU26-SMBK-14'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-15', 'DEMO-SMBK26', '6103', 52000.00, 'Pembelian Tali Rafia', 'EXCEL-AGU26-SMBK-15'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-16', 'DEMO-SMBK26', '6103', 45000.00, 'Pembelian Engsel Pintu', 'EXCEL-AGU26-SMBK-16'
	UNION ALL SELECT 'DEMO-AGU26-SMBK-17', 'DEMO-SMBK26', '6103', 850000.00, 'Pembelian Plastik', 'EXCEL-AGU26-SMBK-17'
	UNION ALL SELECT 'DEMO-AGU26-DEPO-01', 'DEMO-DEPO26', '6105', 500000.00, 'Oprasional Depo Periode Agustus 2026', 'EXCEL-AGU26-DEPO-01'
	UNION ALL SELECT 'DEMO-AGU26-DEPO-02', 'DEMO-DEPO26', '6105', 1320000.00, 'Pembelian Tutup Galon dan Filter', 'EXCEL-AGU26-DEPO-02'
) x
JOIN `acc_cash_funds` fund ON fund.fund_code = x.fund_code
JOIN `acc_accounts` account ON account.code = x.account_code AND account.is_active = 1
WHERE NOT EXISTS (
	SELECT 1 FROM `acc_cash_transactions` existing
	WHERE existing.transaction_number = x.transaction_number
);

-- Isi dana awal sesuai nilai total pengeluaran di Excel.
INSERT IGNORE INTO `acc_cash_transactions`
	(`transaction_number`, `fund_id`, `transaction_type`, `transaction_date`, `amount`,
	 `funding_source_account_id`, `expense_account_id`, `goods_receipt_id`, `counterparty`,
	 `description`, `evidence_reference`, `proof_filename`, `status`, `created_by`, `created_at`)
SELECT x.transaction_number, fund.id, 'funding', '2026-08-01', x.amount,
	account.id, NULL, NULL, 'Kas Utama', x.description, x.evidence_reference, NULL, 'pending', '2', NOW()
FROM (
	SELECT 'DEMO-AGU26-SMBK-FUND' transaction_number, 'DEMO-SMBK26' fund_code, 4573000.00 amount, 'Setoran awal demo Sembako & ATK sesuai laporan Agustus 2026' description, 'EXCEL-AGU26-SMBK-FUND' evidence_reference
	UNION ALL SELECT 'DEMO-AGU26-DEPO-FUND', 'DEMO-DEPO26', 1820000.00, 'Setoran awal demo Depo sesuai laporan Agustus 2026', 'EXCEL-AGU26-DEPO-FUND'
) x
JOIN `acc_cash_funds` fund ON fund.fund_code = x.fund_code
JOIN `acc_accounts` account ON account.code = '1106' AND account.is_active = 1
WHERE NOT EXISTS (
	SELECT 1 FROM `acc_cash_transactions` existing
	WHERE existing.transaction_number = x.transaction_number
);

UPDATE `acc_cash_transactions` cash_tx
JOIN `acc_cash_funds` fund ON fund.id = cash_tx.fund_id
JOIN `acc_accounts` source ON source.code = '1106' AND source.is_active = 1
SET cash_tx.funding_source_account_id = source.id
WHERE cash_tx.status = 'pending'
	AND cash_tx.transaction_number IN ('DEMO-AGU26-SMBK-FUND', 'DEMO-AGU26-DEPO-FUND')
	AND fund.fund_code IN ('DEMO-SMBK26', 'DEMO-DEPO26');

UPDATE `acc_cash_transactions`
SET transaction_date = '2026-08-01', counterparty = 'Kas Utama'
WHERE status = 'pending'
	AND transaction_number IN ('DEMO-AGU26-SMBK-FUND', 'DEMO-AGU26-DEPO-FUND');

-- Sumber kas utama demo berasal dari laci POS agar alur approval bisa diuji bertahap.
INSERT IGNORE INTO `acc_cash_transactions`
	(`transaction_number`, `fund_id`, `transaction_type`, `transaction_date`, `amount`,
	 `funding_source_account_id`, `expense_account_id`, `goods_receipt_id`, `counterparty`,
	 `description`, `evidence_reference`, `proof_filename`, `status`, `created_by`, `created_at`)
SELECT 'DEMO-AGU26-MAIN-FUND', fund.id, 'funding', '2026-08-01', 6393000.00,
	account.id, NULL, NULL, 'Laci Kasir POS', 'Setoran demo ke Kas Utama untuk pendanaan petty cash Agustus 2026',
	'EXCEL-AGU26-MAIN-FUND', NULL, 'pending', '2', NOW()
FROM `acc_cash_funds` fund
JOIN `acc_accounts` account ON account.code = '1101' AND account.is_active = 1
WHERE fund.fund_code = 'DEMO-MAIN26'
	AND NOT EXISTS (SELECT 1 FROM `acc_cash_transactions` existing WHERE existing.transaction_number = 'DEMO-AGU26-MAIN-FUND');