<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$format_rupiah = function ($amount) { return 'Rp '.number_format((float) $amount, 0, ',', '.'); };
$payment_labels = array(
	'potong_gaji' => 'Potong gaji',
	'tunai' => 'Tunai / Cash',
	'cash' => 'Tunai / Cash',
	'qris' => 'QRIS',
	'edc' => 'EDC',
	'transfer' => 'Transfer bank'
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo $receipt ? 'Nota '.$receipt->nota : 'Cetak ulang nota'; ?></title>
	<style>
		* { box-sizing: border-box; }
		body { color: #111; font: 13px Arial, sans-serif; margin: 20px auto; max-width: 420px; padding: 0 12px; }
		.lookup { border-bottom: 1px solid #ccc; margin-bottom: 18px; padding-bottom: 14px; }
		.lookup input { min-width: 0; padding: 8px; width: 72%; }
		button { cursor: pointer; padding: 8px 12px; }
		.error { color: #a00; }
		.receipt { width: 100%; }
		.receipt-barcode { display: block; height: 64px; margin: 12px auto 0; max-width: 100%; }
		.center { text-align: center; }
		.meta { border-bottom: 1px dashed #333; border-top: 1px dashed #333; margin: 10px 0; padding: 7px 0; }
		.meta div { display: flex; justify-content: space-between; gap: 8px; }
		table { border-collapse: collapse; width: 100%; }
		th, td { padding: 4px 1px; vertical-align: top; }
		th { border-bottom: 1px solid #333; text-align: left; }
		.numeric { text-align: right; white-space: nowrap; }
		.total { border-top: 1px dashed #333; font-weight: bold; padding-top: 8px; }
		.actions { margin-top: 18px; }
		@media print {
			@page { size: 80mm auto; margin: 3mm; }
			body { margin: 0; max-width: none; padding: 0; }
			.lookup, .actions { display: none; }
		}
	</style>
</head>
<body>
	<div class="lookup">
		<h2>Cetak ulang nota</h2>
		<?php if ($error) { ?><p class="error" role="alert"><?php echo html_escape($error); ?></p><?php } ?>
		<form method="get" action="<?php echo site_url('main/kasir/reprint'); ?>">
			<label for="nota">Nomor nota</label><br>
			<input id="nota" name="nota" maxlength="64" required value="<?php echo html_escape($nota); ?>" placeholder="Masukkan nomor nota">
			<button type="submit">Cari</button>
		</form>
	</div>

	<?php if ($receipt) { ?>
	<div class="receipt">
		<div class="center"><strong>Koperasi Makmur Indah Sejahtera</strong><br>PT GSDI-GSYM</div>
		<div class="meta">
			<div><span>No. nota</span><strong><?php echo html_escape($receipt->nota); ?></strong></div>
			<div><span>Tanggal</span><span><?php echo html_escape($receipt->transaction_date); ?></span></div>
			<div><span>Kasir</span><span><?php echo html_escape($receipt->cashier_name); ?></span></div>
			<div><span>Pembeli</span><span><?php echo html_escape($receipt->employee_name); ?></span></div>
			<div><span>NPK</span><span><?php echo html_escape($receipt->npk); ?></span></div>
			<div><span>Pembayaran</span><span><?php echo html_escape(isset($payment_labels[$receipt->payment_method]) ? $payment_labels[$receipt->payment_method] : $receipt->payment_method); ?></span></div>
		</div>
		<table>
			<thead><tr><th>Barang</th><th class="numeric">Qty</th><th class="numeric">Harga</th><th class="numeric">Jumlah</th></tr></thead>
			<tbody>
			<?php $tax_total = 0; foreach ($receipt->items as $item) { $tax_total += (float) $item->jumlah * (float) $item->ppn; ?>
				<tr>
					<td><?php echo html_escape($item->product_name); ?><br><small><?php echo html_escape($item->barcode); ?></small></td>
					<td class="numeric"><?php echo number_format((int) $item->qty); ?></td>
					<td class="numeric"><?php echo $format_rupiah($item->harga); ?></td>
					<td class="numeric"><?php echo $format_rupiah($item->jumlah); ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
		<div class="meta">
			<div><span>Subtotal</span><span><?php echo $format_rupiah((float) $receipt->total_paid + (float) $receipt->voucher_discount - $tax_total); ?></span></div>
			<?php if ($tax_total > 0) { ?><div><span>PPN</span><span><?php echo $format_rupiah($tax_total); ?></span></div><?php } ?>
			<div class="total"><span>Total belanja</span><span><?php echo $format_rupiah((float) $receipt->total_paid + (float) $receipt->voucher_discount); ?></span></div>
			<?php if ((float) $receipt->voucher_discount > 0) { ?><div><span>Voucher <?php echo html_escape($receipt->voucher_code); ?></span><span>- <?php echo $format_rupiah($receipt->voucher_discount); ?></span></div><div class="total"><span>Sisa bayar</span><span><?php echo $format_rupiah($receipt->total_paid); ?></span></div><?php } ?>
			<?php if (in_array($receipt->payment_method, array('tunai', 'cash'), TRUE)) { ?><div><span>Tunai diterima</span><span><?php echo $format_rupiah($receipt->amount_paid); ?></span></div><div><span>Kembalian</span><span><?php echo $format_rupiah($receipt->change_amount); ?></span></div><?php } ?>
		</div>
		<p class="center">Terima kasih telah berbelanja</p>
		<svg id="receipt-barcode" class="receipt-barcode"></svg>
	</div>
	<div class="actions"><button type="button" onclick="window.print()">Cetak nota</button></div>
	<?php } ?>
</body>
<script src="<?php echo base_url('JsBarcode.all.js'); ?>"></script>
<?php if ($receipt) { ?>
<script>
window.addEventListener('load', function () {
	JsBarcode('#receipt-barcode', <?php echo json_encode((string) $receipt->nota); ?>, {
		format: 'CODE128', displayValue: true, fontSize: 12, height: 42, margin: 2
	});
});
</script>
<?php } ?>
</html>