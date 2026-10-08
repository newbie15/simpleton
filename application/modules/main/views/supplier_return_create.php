<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>

<div class="box box-primary">
	<div class="box-header with-border">
		<h3 class="box-title">Penerimaan <?php echo html_escape($receipt->receipt_number); ?></h3>
		<div class="pull-right"><a class="btn btn-default btn-sm" href="<?php echo site_url('main/supplier_return'); ?>">Kembali</a></div>
	</div>
	<div class="box-body">
		<p><strong>Supplier:</strong> <?php echo html_escape($receipt->supplier_name); ?>
			&nbsp; <strong>Surat jalan:</strong>
			<?php echo $receipt->supplier_document !== NULL && $receipt->supplier_document !== '' ? html_escape($receipt->supplier_document) : '-'; ?>
			&nbsp; <strong>Tanggal:</strong> <?php echo html_escape($receipt->received_at); ?></p>
		<form method="post" action="<?php echo site_url('main/supplier_return/save_draft/'.(int) $receipt->id); ?>">
			<div class="table-responsive">
				<table class="table table-bordered table-striped">
					<thead><tr><th>Barang</th><th>Barcode</th><th>Diterima</th><th>Sudah final retur</th><th>Sisa dapat diretur</th><th>Harga beli/unit</th><th>Qty retur</th><th>Alasan detail</th></tr></thead>
					<tbody>
						<?php foreach ($items as $item) {
							$available = max(0, (int) $item->qty_received - (int) $item->qty_returned);
						?>
							<tr>
								<td><?php echo html_escape($item->product_name); ?></td>
								<td><?php echo html_escape($item->barcode); ?></td>
								<td><?php echo (int) $item->qty_received; ?></td>
								<td><?php echo (int) $item->qty_returned; ?></td>
								<td><?php echo $available; ?></td>
								<td>Rp <?php echo number_format((float) $item->unit_price, 2, ',', '.'); ?></td>
								<td><input class="form-control" type="number" name="qty_retur[<?php echo html_escape($item->barcode); ?>]" min="0" max="<?php echo $available; ?>" value="0"></td>
								<td><input class="form-control" type="text" name="alasan_detail[<?php echo html_escape($item->barcode); ?>]" maxlength="255"></td>
							</tr>
						<?php } ?>
						<?php if (count($items) === 0) { ?>
							<tr><td colspan="8" class="text-center">Penerimaan ini tidak memiliki detail barang.</td></tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
			<div class="form-group">
				<label for="return-reason">Alasan retur supplier</label>
				<textarea class="form-control" id="return-reason" name="alasan" rows="3" maxlength="2000" required></textarea>
			</div>
			<button class="btn btn-primary" type="submit" onclick="return window.confirm('Simpan sebagai draft? Stok belum berubah sampai admin atau pengurus menyetujui retur.');">Simpan Draft Retur</button>
		</form>
	</div>
</div>
