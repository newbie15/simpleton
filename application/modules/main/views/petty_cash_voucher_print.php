<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Bukti Pengeluaran Kas Kecil - <?= htmlspecialchars($tx->transaction_number) ?></title>
	<style>
		body {
			font-family: 'Courier New', Courier, monospace;
			font-size: 13px;
			color: #000;
			margin: 0;
			padding: 15px;
			background: #fff;
		}
		.voucher-box {
			max-width: 480px;
			margin: 0 auto;
			border: 1px dashed #000;
			padding: 15px;
		}
		.text-center { text-align: center; }
		.text-right { text-align: right; }
		.title { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
		.subtitle { font-size: 12px; margin-bottom: 12px; }
		hr { border: none; border-top: 1px dashed #000; margin: 8px 0; }
		table { width: 100%; border-collapse: collapse; }
		td, th { padding: 4px 2px; vertical-align: top; }
		.signatures { margin-top: 25px; }
		.sig-box { width: 50%; float: left; text-align: center; }
		.sig-space { height: 50px; }
		.clearfix { clear: both; }
		@media print {
			body { padding: 0; }
			.no-print { display: none; }
			.voucher-box { border: 1px dashed #444; }
		}
	</style>
</head>
<body>

<div class="no-print" style="max-width: 480px; margin: 0 auto 10px; text-align: right;">
	<button onclick="window.print();" style="padding: 6px 14px; font-weight: bold; cursor: pointer;">[ Cetak Slip ]</button>
	<button onclick="window.close();" style="padding: 6px 14px; cursor: pointer;">Tutup</button>
</div>

<div class="voucher-box">
	<div class="text-center">
		<div class="title">KOPERASI KARYAWAN</div>
		<div class="subtitle">BUKTI PENGELUARAN KAS KECIL (PETTY CASH VOUCHER)</div>
	</div>

	<hr>

	<table>
		<tr>
			<td style="width: 120px;">No. Bukti</td>
			<td>: <strong><?= htmlspecialchars($tx->transaction_number) ?></strong></td>
		</tr>
		<tr>
			<td>Tanggal</td>
			<td>: <?= date('d/m/Y', strtotime($tx->transaction_date)) ?></td>
		</tr>
		<tr>
			<td>Dana Kas</td>
			<td>: <?= htmlspecialchars($tx->fund_name) ?></td>
		</tr>
		<tr>
			<td>Kategori Beban</td>
			<td>: <?= htmlspecialchars($tx->expense_name ? $tx->expense_name : '-') ?></td>
		</tr>
		<tr>
			<td>Dibayarkan Ke</td>
			<td>: <?= htmlspecialchars($tx->counterparty ? $tx->counterparty : '(Pihak Ketiga / Vendor)') ?></td>
		</tr>
		<tr>
			<td>Keperluan</td>
			<td>: <?= htmlspecialchars($tx->description) ?></td>
		</tr>
		<tr>
			<td>Status</td>
			<td>: <strong><?= strtoupper($tx->status) ?></strong></td>
		</tr>
	</table>

	<hr>

	<table>
		<tr style="font-size: 15px;">
			<td><strong>JUMLAH KELUAR</strong></td>
			<td class="text-right"><strong>Rp <?= number_format($tx->amount, 0, ',', '.') ?></strong></td>
		</tr>
	</table>

	<hr>

	<div class="signatures">
		<div class="sig-box">
			Diserahkan Oleh (Kasir):
			<div class="sig-space"></div>
			( <?= htmlspecialchars($tx->creator_name) ?> )
		</div>
		<div class="sig-box">
			Diterima Oleh:
			<div class="sig-space"></div>
			( ............................ )
		</div>
		<div class="clearfix"></div>
	</div>

	<div style="margin-top: 15px; font-size: 10px; text-align: center; color: #555;">
		* Lampirkan nota/struk belanja asli bersama slip tanda terima ini.
	</div>
</div>

<script>
window.onload = function() {
	// otomatis fokus siap print jika diinginkan
};
</script>
</body>
</html>

