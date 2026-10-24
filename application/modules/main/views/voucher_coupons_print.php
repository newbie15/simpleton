<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="id">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Kupon <?php echo html_escape($voucher->kode); ?></title>
	<style>
		* { box-sizing: border-box; }
		body { color: #222; font: 12px Arial, sans-serif; margin: 20px; }
		.actions { margin-bottom: 16px; }
		button { padding: 8px 14px; }
		.sheet { display: grid; gap: 8mm; grid-template-columns: repeat(2, minmax(0, 1fr)); }
		.coupon { border: 1px dashed #555; break-inside: avoid; min-height: 47mm; padding: 7mm; text-align: center; }
		.coupon h2 { font-size: 15px; margin: 0 0 4px; }
		.serial { font-size: 14px; font-weight: bold; letter-spacing: 1px; }
		.barcode { height: 18mm; max-width: 100%; }
		.empty { border: 1px solid #ddd; padding: 20px; }
		@media print {
			@page { size: A4; margin: 10mm; }
			body { margin: 0; }
			.actions { display: none; }
		}
	</style>
</head>
<body>
	<div class="actions"><button type="button" onclick="window.print()">Cetak kupon</button></div>
	<?php if (!$coupons) { ?><p class="empty">Tidak ada kupon berstatus tersedia untuk dicetak.</p><?php } ?>
	<div class="sheet">
		<?php foreach ($coupons as $index => $coupon) { ?>
		<div class="coupon">
			<h2><?php echo html_escape($voucher->nama); ?></h2>
			<div><?php echo html_escape($voucher->kode); ?> · Berlaku <?php echo html_escape(date('d/m/Y', strtotime($voucher->tgl_mulai))); ?> s.d. <?php echo html_escape(date('d/m/Y', strtotime($voucher->tgl_kadaluarsa))); ?></div>
			<svg class="barcode" id="coupon-barcode-<?php echo (int) $index; ?>"></svg>
			<div class="serial"><?php echo html_escape($coupon->serial_code); ?></div>
			<?php if ($coupon->assigned_npk) { ?><div>NPK: <?php echo html_escape($coupon->assigned_npk); ?></div><?php } ?>
		</div>
		<?php } ?>
	</div>
	<script src="<?php echo base_url('JsBarcode.all.js'); ?>"></script>
	<script>
	window.addEventListener('load', function () {
		<?php foreach ($coupons as $index => $coupon) { ?>
		JsBarcode('#coupon-barcode-<?php echo (int) $index; ?>', <?php echo json_encode($coupon->serial_code); ?>, {
			format: 'CODE128', displayValue: false, margin: 0, height: 48
		});
		<?php } ?>
	});
	</script>
</body>
</html>