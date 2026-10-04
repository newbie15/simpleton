<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Purchase Order <?php echo html_escape($order->order_number); ?></title>
	<style>
		body { color: #222; font: 14px Arial, sans-serif; margin: 30px auto; max-width: 900px; }
		h1 { margin-bottom: 4px; }
		table { border-collapse: collapse; margin-top: 24px; width: 100%; }
		th, td { border: 1px solid #777; padding: 8px; text-align: left; }
		th { background: #eee; }
		.numeric { text-align: right; }
		.summary { margin-top: 24px; text-align: right; }
		.actions { margin-bottom: 20px; }
		@media print {
			body { margin: 0; max-width: none; }
			.actions { display: none; }
			th { background: #eee !important; print-color-adjust: exact; }
		}
	</style>
</head>
<body>
	<div class="actions">
		<button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
		<a href="<?php echo site_url('main/purchasing/edit/'.$order->id); ?>">Kembali ke edit PO</a>
	</div>
	<h1>Purchase Order</h1>
	<p><strong>No. PO:</strong> <?php echo html_escape($order->order_number); ?><br>
	<strong>Tanggal:</strong> <?php echo html_escape($order->ordered_at); ?></p>

	<h3>Supplier: <?php echo html_escape($order->supplier_name); ?></h3>
	<?php if (!empty($order->supplier_phone)) { ?>
		<div>Telepon: <?php echo html_escape($order->supplier_phone); ?></div>
	<?php } ?>
	<?php if (!empty($order->supplier_address)) { ?>
		<div>Alamat: <?php echo nl2br(html_escape($order->supplier_address)); ?></div>
	<?php } ?>

	<?php if (!empty($order->notes)) { ?><p><strong>Catatan:</strong> <?php echo html_escape($order->notes); ?></p><?php } ?>
	<table>
		<thead>
			<tr><th>No.</th><th>Barcode</th><th>Nama Barang</th><th class="numeric">Qty Diminta</th></tr>
		</thead>
		<tbody>
			<?php foreach ($items as $index => $item) { ?>
				<tr>
					<td><?php echo $index + 1; ?></td>
					<td><?php echo html_escape($item->barcode); ?></td>
					<td><?php echo html_escape($item->product_name); ?></td>
					<td class="numeric"><?php echo number_format((int) $item->qty_ordered); ?></td>
				</tr>
			<?php } ?>
		</tbody>
	</table>
</body>
</html>
