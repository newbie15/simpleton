<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $status_labels = array('available' => 'Tersedia', 'used' => 'Terpakai', 'void' => 'Dibatalkan'); ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<p><a class="btn btn-default" href="<?php echo site_url('main/voucher'); ?>">Kembali ke Voucher</a>
	<a class="btn btn-primary" href="<?php echo site_url('main/voucher/print_coupons/'.(int) $voucher->id); ?>" target="_blank">Cetak kupon tersedia</a></p>

<div class="row">
	<div class="col-md-5">
		<div class="box box-primary">
			<div class="box-header with-border"><h3 class="box-title">Generate kupon</h3></div>
			<form method="post" action="<?php echo site_url('main/voucher/generate_coupons'); ?>">
				<input type="hidden" name="coupon_form_token" value="<?php echo html_escape($form_token); ?>">
				<input type="hidden" name="voucher_id" value="<?php echo (int) $voucher->id; ?>">
				<div class="box-body">
					<p><?php echo html_escape($voucher->kode.' — '.$voucher->nama); ?></p>
					<div class="form-group"><label for="coupon-prefix">Awalan serial</label><input id="coupon-prefix" class="form-control" name="prefix" maxlength="12" value="<?php echo html_escape(substr(strtoupper($voucher->kode), 0, 12)); ?>" required></div>
					<div class="form-group"><label for="coupon-quantity">Jumlah kupon (maksimal 500)</label><input id="coupon-quantity" class="form-control" name="quantity" type="number" min="1" max="500" required></div>
				</div>
				<div class="box-footer"><button class="btn btn-primary" type="submit">Generate serial</button></div>
			</form>
		</div>
	</div>
	<div class="col-md-7">
		<div class="box box-info">
			<div class="box-header with-border"><h3 class="box-title">Impor dari Excel</h3></div>
			<form method="post" enctype="multipart/form-data" action="<?php echo site_url('main/voucher/preview_coupon_import/'.(int) $voucher->id); ?>">
				<input type="hidden" name="coupon_form_token" value="<?php echo html_escape($form_token); ?>">
				<div class="box-body">
					<p>File XLS/XLSX, maksimal 5 MB dan 1000 kupon. Baris pertama harus berisi header <code>SERIAL_KUPON</code> dan <code>NPK</code>; NPK boleh kosong. Simpan serial sebagai teks agar nol di depan tidak hilang.</p>
					<div class="form-group"><label for="coupon-file">File daftar serial</label><input id="coupon-file" class="form-control" type="file" name="coupon_file" accept=".xls,.xlsx" required></div>
				</div>
				<div class="box-footer"><button class="btn btn-info" type="submit">Preview dan validasi</button></div>
			</form>
		</div>
	</div>
</div>

<?php if ($preview) { ?>
<div class="box box-<?php echo empty($preview['errors']) ? 'success' : 'danger'; ?>">
	<div class="box-header with-border"><h3 class="box-title">Preview impor (<?php echo count($preview['rows']); ?> baris)</h3></div>
	<div class="box-body">
		<?php if (!empty($preview['errors'])) { ?>
		<div class="alert alert-danger"><strong>Impor belum dapat dikonfirmasi:</strong><ul><?php foreach ($preview['errors'] as $message) { ?><li><?php echo html_escape($message); ?></li><?php } ?></ul></div>
		<?php } else { ?><p>Semua serial lolos validasi. Belum ada kupon yang disimpan.</p><?php } ?>
		<div class="table-responsive" style="max-height:360px;overflow:auto;">
			<table class="table table-condensed table-striped">
				<thead><tr><th>Baris</th><th>Serial</th><th>NPK</th></tr></thead>
				<tbody><?php foreach ($preview['rows'] as $row) { ?><tr><td><?php echo (int) $row['row']; ?></td><td><?php echo html_escape($row['serial_code']); ?></td><td><?php echo html_escape($row['assigned_npk'] !== '' ? $row['assigned_npk'] : '—'); ?></td></tr><?php } ?></tbody>
			</table>
		</div>
	</div>
	<?php if (empty($preview['errors'])) { ?>
	<div class="box-footer">
		<form method="post" action="<?php echo site_url('main/voucher/confirm_coupon_import/'.(int) $voucher->id); ?>">
			<input type="hidden" name="coupon_form_token" value="<?php echo html_escape($form_token); ?>">
			<button class="btn btn-success" type="submit">Konfirmasi dan simpan semua kupon</button>
		</form>
	</div>
	<?php } ?>
</div>
<?php } ?>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Daftar kupon (500 terbaru)</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th>Serial</th><th>NPK penerima</th><th>Status</th><th>No. nota</th><th>Dipakai</th></tr></thead>
			<tbody>
			<?php foreach ($coupons as $coupon) { ?>
			<tr><td><?php echo html_escape($coupon->serial_code); ?></td><td><?php echo html_escape($coupon->assigned_npk ? $coupon->assigned_npk : '—'); ?></td><td><?php echo html_escape(isset($status_labels[$coupon->status]) ? $status_labels[$coupon->status] : $coupon->status); ?></td><td><?php echo html_escape($coupon->used_nota ? $coupon->used_nota : '—'); ?></td><td><?php echo html_escape($coupon->used_at ? $coupon->used_at : '—'); ?></td></tr>
			<?php } ?>
			<?php if (!$coupons) { ?><tr><td colspan="5" class="text-muted">Belum ada serial kupon.</td></tr><?php } ?>
			</tbody>
		</table>
	</div>
</div>