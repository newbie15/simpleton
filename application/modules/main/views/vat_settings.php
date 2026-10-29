<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div>
<?php } ?>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Tarif PPN POS</h3></div>
	<div class="box-body">
		<p>Tarif ini berlaku untuk semua barang POS. PPN selalu aktif dan harga barang dianggap sudah termasuk pajak. DPP dihitung dari harga inklusif, lalu PPN menjadi selisihnya. Perubahan hanya memengaruhi transaksi baru; nota lama tetap menggunakan nilai yang tercatat saat transaksi.</p>
		<p>Voucher tetap mengurangi jumlah yang dibayar, bukan DPP atau PPN.</p>
		<form method="post" action="<?php echo html_escape(site_url('main/vat_settings/save')); ?>">
			<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
			<div class="form-group">
				<label for="rate-percent">Tarif PPN (%)</label>
				<div class="input-group" style="max-width:260px;">
					<input class="form-control" id="rate-percent" name="rate_percent" type="number" min="0.01" max="100" step="0.01" value="<?php echo html_escape(number_format((float) $rate, 2, '.', '')); ?>" required>
					<span class="input-group-addon">%</span>
				</div>
			</div>
			<button class="btn btn-primary" type="submit" onclick="return window.confirm('Simpan tarif ini untuk transaksi POS baru?');">Simpan tarif</button>
		</form>
		<?php if ($updated_at) { ?>
			<p class="text-muted" style="margin-top:15px;">Terakhir diubah: <?php echo html_escape($updated_at); ?> oleh <?php echo html_escape($updated_by); ?></p>
		<?php } ?>
	</div>
</div>
