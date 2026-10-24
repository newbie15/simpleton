-- Contoh data: LAPORAN BULANAN AGUSTUS 2026 (docs/accounting). Aman dijalankan ulang.
-- Metode: persediaan periodik; HPP = stok awal + pembelian - stok akhir.

INSERT IGNORE INTO `acc_accounts` (`code`, `name`, `type`, `normal_balance`, `parent_id`, `is_active`) VALUES
('3105', 'Saldo Awal Modal', 'equity', 'credit', NULL, 1),
('6105', 'Beban Operasional Depo', 'expense', 'debit', NULL, 1),
('6106', 'Beban Gaji Karyawan', 'expense', 'debit', NULL, 1);

-- Jurnal (header)
INSERT INTO `acc_journals` (`journal_number`, `date`, `reference_type`, `reference_id`, `description`, `is_posted`, `created_at`, `created_by`)
SELECT v.n, v.d, 'IMPORT_XLSX', v.n, v.t, 1, NOW(), 'import'
FROM (
	SELECT 'IMP-AGU26-01' n, '2026-08-01' d, 'Saldo awal persediaan Agustus 2026' t
	UNION ALL SELECT 'IMP-AGU26-02', '2026-08-31', 'Pendapatan transaksi koperasi Agustus 2026'
	UNION ALL SELECT 'IMP-AGU26-03', '2026-08-31', 'Pembelian/pembayaran supplier Agustus 2026'
	UNION ALL SELECT 'IMP-AGU26-04', '2026-08-31', 'HPP Agustus 2026 (stok awal + pembelian - stok akhir)'
	UNION ALL SELECT 'IMP-AGU26-05', '2026-08-31', 'Pengeluaran & pemakaian sembako/ATK Agustus 2026'
	UNION ALL SELECT 'IMP-AGU26-06', '2026-08-31', 'Pengeluaran & pemakaian depo Agustus 2026'
	UNION ALL SELECT 'IMP-AGU26-07', '2026-08-31', 'Gaji karyawan Agustus 2026'
	UNION ALL SELECT 'IMP-AGU26-08', '2026-08-31', 'Kerugian stok rusak Agustus 2026'
) v
WHERE NOT EXISTS (SELECT 1 FROM `acc_journals` j WHERE j.`journal_number` = v.n);

-- Rincian (hanya untuk jurnal yang belum punya baris)
INSERT INTO `acc_journal_items` (`journal_id`, `account_id`, `debit`, `credit`, `memo`)
SELECT j.id, a.id, x.debit, x.credit, x.memo
FROM (
	SELECT 'IMP-AGU26-01' n, '1104' code, 595883043.36 debit, 0 credit, 'Stock awal' memo
	UNION ALL SELECT 'IMP-AGU26-01', '3105', 0, 595883043.36, 'Saldo awal'

	UNION ALL SELECT 'IMP-AGU26-02', '1101', 792754645.00, 0, 'Penerimaan transaksi koperasi'
	UNION ALL SELECT 'IMP-AGU26-02', '4101', 0, 792754645.00, 'Pendapatan transaksi koperasi'

	UNION ALL SELECT 'IMP-AGU26-03', '1104', 859854921.00, 0, 'Pembayaran supplier'
	UNION ALL SELECT 'IMP-AGU26-03', '2101', 0, 660804008.00, 'Tempo 2 minggu 58.566.000 + tempo 1 bulan 602.238.008'
	UNION ALL SELECT 'IMP-AGU26-03', '1101', 0, 27427000.00, 'Cash tunai kasir'
	UNION ALL SELECT 'IMP-AGU26-03', '1102', 0, 171623913.00, 'Transfer'

	UNION ALL SELECT 'IMP-AGU26-04', '5101', 731506398.80, 0, 'HPP'
	UNION ALL SELECT 'IMP-AGU26-04', '1104', 0, 731506398.80, 'Stock akhir 724.231.565,56'

	UNION ALL SELECT 'IMP-AGU26-05', '6103', 1000000, 0, 'Oprasional Sembako Periode Agustus 2026'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 95000, 0, 'Pembelian Bensin u/ Genset'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 150000, 0, 'Biaya Service AC'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 550000, 0, 'Biaya Belanja Borneo'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 80000, 0, 'Pembelian Bendera'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 300000, 0, 'Pembelian Solar'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 5000, 0, 'Biaya Parkir'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 60000, 0, 'Exfood Bongkaran'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 60000, 0, 'Exfood Meeting Pengurus'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 400000, 0, 'Biaya Belanja Borneo'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 326000, 0, 'Pembelian Catridge dan Kertas Kasir'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 10000, 0, 'Biaya Parkir'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 90000, 0, 'Exfood Bongkaran'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 500000, 0, 'Pembelian Solar'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 52000, 0, 'Pembelian Tali Rafia'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 45000, 0, 'Pembelian Engsel Pintu'
	UNION ALL SELECT 'IMP-AGU26-05', '6103', 850000, 0, 'Pembelian Plastik'
	UNION ALL SELECT 'IMP-AGU26-05', '1101', 0, 4573000, 'Pengeluaran sembako & ATK'

	UNION ALL SELECT 'IMP-AGU26-06', '6105', 500000, 0, 'Oprasional Depo Periode Agustus 2026'
	UNION ALL SELECT 'IMP-AGU26-06', '6105', 1320000, 0, 'Pembelian Tutup Galon dan Filter'
	UNION ALL SELECT 'IMP-AGU26-06', '1101', 0, 1820000, 'Pengeluaran depo'

	UNION ALL SELECT 'IMP-AGU26-07', '6106', 19710300, 0, 'Gaji karyawan'
	UNION ALL SELECT 'IMP-AGU26-07', '1101', 0, 19710300, 'Gaji karyawan'

	UNION ALL SELECT 'IMP-AGU26-08', '6102', 1180233.75, 0, 'Stok rusak'
	UNION ALL SELECT 'IMP-AGU26-08', '1104', 0, 1180233.75, 'Stok rusak'
) x
JOIN `acc_journals` j ON j.`journal_number` = x.n
JOIN `acc_accounts` a ON a.`code` = x.code
WHERE NOT EXISTS (SELECT 1 FROM `acc_journal_items` i WHERE i.`journal_id` = j.id);
