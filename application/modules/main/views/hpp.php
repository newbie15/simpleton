<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>

<?php if (!$product) { ?>
	<div class="box box-primary">
		<div class="box-header with-border"><h3 class="box-title">Daftar HPP Barang</h3></div>
		<div class="box-body table-responsive">
			<p class="help-block">Data dimuat bertahap dari server. Gunakan pencarian dan pagination; klik detail untuk membuka riwayat harga barang.</p>
			<table id="hpp-inventory-table" class="table table-bordered table-striped"
				data-source="<?php echo html_escape(site_url('main/hpp/inventory_data')); ?>"
				data-detail-url="<?php echo html_escape(site_url('main/hpp?barcode=')); ?>">
				<thead>
					<tr><th>Barcode</th><th>Nama Barang</th><th>Stok</th><th>HPP Saat Ini</th><th>Diperbarui</th><th>Aksi</th></tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
<?php } ?>

<?php if ($product) { ?>
	<div class="box box-info">
		<div class="box-header with-border">
			<h3 class="box-title">Detail HPP: <?php echo html_escape($product->nama); ?> — <?php echo html_escape($product->barcode); ?></h3>
			<div class="box-tools"><a class="btn btn-default btn-xs" href="<?php echo site_url('main/hpp'); ?>">Kembali ke daftar</a></div>
		</div>
		<div class="box-body">
			<p>
				<strong>Stok:</strong> <?php echo number_format((int) $product->jumlah); ?> &nbsp;
				<strong>HPP rata-rata saat ini:</strong>
				<?php echo $product->average_cost === NULL || (float) $product->average_cost <= 0
					? 'Belum tersedia'
					: 'Rp '.number_format((float) $product->average_cost, 2, ',', '.'); ?>
				<?php if (!empty($product->updated_at)) { ?><span class="text-muted">(diperbarui <?php echo html_escape($product->updated_at); ?>)</span><?php } ?>
			</p>
			<p class="help-block">Harga satuan pembelian, setelah diskon, dan HPP rata-rata setelah penerimaan ditampilkan berdasarkan data Purchasing. Riwayat sebelum fitur ini mungkin belum memiliki catatan HPP rata-rata.</p>
		</div>
	</div>

	<div class="box box-default">
		<div class="box-header with-border"><h3 class="box-title">Riwayat harga dari Purchasing</h3></div>
		<div class="box-body table-responsive">
			<table class="table table-bordered table-striped">
				<thead><tr><th>Tanggal</th><th>Supplier</th><th>Dokumen</th><th>Qty</th><th>Harga/unit</th><th>Diskon</th><th>Harga bersih/unit</th><th>HPP rata-rata setelah</th></tr></thead>
				<tbody>
					<?php if (count($purchase_history) === 0) { ?><tr><td colspan="8" class="text-center">Belum ada riwayat penerimaan Purchasing.</td></tr><?php } ?>
					<?php foreach ($purchase_history as $row) {
						$net_unit = (int) $row->qty_received > 0 ? (float) $row->subtotal / (int) $row->qty_received : NULL;
					?>
						<tr>
							<td><?php echo html_escape($row->received_at); ?></td>
							<td><?php echo html_escape($row->supplier_name); ?></td>
							<td><?php echo html_escape($row->receipt_number); ?><?php if ($row->supplier_document) { ?><br><small><?php echo html_escape($row->supplier_document); ?></small><?php } ?></td>
							<td><?php echo number_format((int) $row->qty_received); ?></td>
							<td><?php echo $row->unit_price === NULL ? 'Belum dicatat' : 'Rp '.number_format((float) $row->unit_price, 2, ',', '.'); ?></td>
							<td>
								<?php if ($row->discount_type === 'percent') {
									echo number_format((float) $row->discount_value, 2, ',', '.').'%';
								} elseif ($row->discount_type === 'nominal') {
									echo 'Rp '.number_format((float) $row->discount_amount, 2, ',', '.');
								} else { echo '-'; } ?>
							</td>
							<td><?php echo $net_unit === NULL ? 'Belum dicatat' : 'Rp '.number_format($net_unit, 2, ',', '.'); ?></td>
							<td><?php echo $row->average_cost_after === NULL
								? 'Belum tercatat'
								: 'Rp '.number_format((float) $row->average_cost_after, 2, ',', '.'); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php if ($can_manage_hpp) { ?>
		<div class="box box-warning">
			<div class="box-header with-border"><h3 class="box-title">Input HPP manual</h3></div>
			<div class="box-body">
				<div class="alert alert-warning">HPP manual bukan harga dari vendor/supplier. Input ini akan langsung menjadi HPP saat ini dan dicatat sebagai sumber Manual dalam riwayat.</div>
				<form method="post" action="<?php echo site_url('main/hpp/save_manual'); ?>">
					<input type="hidden" name="barcode" value="<?php echo html_escape($product->barcode); ?>">
					<div class="form-group">
						<label for="manual-unit-cost">HPP per unit (Rp)</label>
						<input type="number" class="form-control" id="manual-unit-cost" name="unit_cost" min="0.0001" step="0.0001" required>
					</div>
					<div class="form-group">
						<label for="manual-notes">Catatan (opsional)</label>
						<textarea class="form-control" id="manual-notes" name="notes" maxlength="500" rows="2" placeholder="Contoh: berdasarkan faktur lama / koreksi HPP"></textarea>
					</div>
					<button class="btn btn-warning" type="submit" onclick="return confirm('HPP saat ini akan diganti dan sumber dicatat sebagai Manual. Lanjutkan?');">Simpan HPP Manual</button>
				</form>
			</div>
		</div>
	<?php } else { ?>
		<div class="alert alert-info">Input HPP manual hanya tersedia untuk admin, management, manager, atau pengurus.</div>
	<?php } ?>

	<div class="box box-default">
		<div class="box-header with-border"><h3 class="box-title">Riwayat input manual</h3></div>
		<div class="box-body table-responsive">
			<table class="table table-bordered table-striped">
				<thead><tr><th>Tanggal</th><th>Sumber</th><th>HPP yang ditetapkan</th><th>HPP sebelumnya</th><th>Penginput</th><th>Catatan</th></tr></thead>
				<tbody>
					<?php if (count($manual_history) === 0) { ?><tr><td colspan="6" class="text-center">Belum ada input HPP manual.</td></tr><?php } ?>
					<?php foreach ($manual_history as $row) { ?>
						<tr>
							<td><?php echo html_escape($row->recorded_at); ?></td>
							<td><span class="label label-warning">Manual (bukan vendor/supplier)</span></td>
							<td>Rp <?php echo number_format((float) $row->unit_cost, 2, ',', '.'); ?></td>
							<td><?php echo $row->previous_average_cost === NULL
								? 'Belum diatur'
								: 'Rp '.number_format((float) $row->previous_average_cost, 2, ',', '.'); ?></td>
							<td><?php echo html_escape($row->created_by_name); ?></td>
							<td><?php echo $row->notes !== NULL && $row->notes !== '' ? html_escape($row->notes) : '-'; ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
<?php } ?>
