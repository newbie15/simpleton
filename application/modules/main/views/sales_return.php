<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Cari Nota Penjualan atau Riwayat Anggota</h3></div>
	<form method="get" action="<?php echo site_url('main/sales_return'); ?>">
		<div class="box-body">
			<div class="row">
				<div class="col-sm-3">
					<div class="form-group">
						<label for="search-type">Cari berdasarkan</label>
						<select class="form-control" id="search-type" name="search_type">
							<option value="nota"<?php echo $search_type === 'nota' ? ' selected' : ''; ?>>Nomor nota</option>
							<option value="npk"<?php echo $search_type === 'npk' ? ' selected' : ''; ?>>NPK anggota</option>
						</select>
					</div>
				</div>
				<div class="col-sm-5">
					<div class="form-group">
						<div id="nota-search-container"<?php echo $search_type === 'npk' ? ' style="display:none"' : ''; ?>>
							<label for="nota-search-value">Nomor nota</label>
							<input class="form-control" id="nota-search-value" name="search_value" maxlength="64" value="<?php echo html_escape($search_type === 'nota' ? $search_value : ''); ?>" placeholder="Masukkan nomor nota"<?php echo $search_type === 'nota' ? ' required' : ' disabled'; ?>>
						</div>
						<div id="npk-search-container"<?php echo $search_type === 'nota' ? ' style="display:none"' : ''; ?>>
							<label for="npk-search-value">NPK / Nama karyawan</label>
							<input class="form-control" id="npk-search-value" name="search_value" maxlength="64" value="<?php echo html_escape($search_type === 'npk' ? $search_value : ''); ?>" data-npk-source="<?php echo html_escape(site_url('main/ajax/karyawan_list')); ?>" placeholder="Cari nama atau masukkan NPK" autocomplete="off"<?php echo $search_type === 'npk' ? ' required' : ' disabled'; ?>>
						</div>
					</div>
				</div>
				<div class="col-sm-4">
					<div class="form-group">
						<label>&nbsp;</label>
						<div style="display:flex;gap:8px">
							<button class="btn btn-primary" type="submit">Cari</button>
							<a class="btn btn-default" href="<?php echo html_escape(site_url('main/sales_return')); ?>">Clear</a>
						</div>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>

<?php if ($search_value !== '' && $search_type === 'nota' && !$sale) { ?>
	<div class="alert alert-warning">Nota tidak ditemukan. Periksa nomor nota lalu coba kembali.</div>
<?php } ?>

<?php if ($search_type === 'npk' && $npk !== '' && !$npk_search_performed) { ?>
	<div class="alert alert-warning">NPK atau nama karyawan tidak ditemukan. Pilih karyawan dari saran pencarian atau periksa kembali NPK.</div>
<?php } ?>

<?php if ($search_type === 'npk' && $npk_search_performed) { ?>
	<div class="box box-info">
		<div class="box-header with-border">
			<h3 class="box-title">Riwayat Pembelian NPK <?php echo html_escape($npk); ?>
				<?php if ($history_employee) { ?>
					— <?php echo html_escape($history_employee->nama); ?>
				<?php } ?>
			</h3>
		</div>
		<div class="box-body">
			<?php if (empty($purchase_history)) { ?>
				<div class="alert alert-warning">Riwayat pembelian untuk NPK ini tidak ditemukan.</div>
			<?php } else { ?>
				<p class="text-muted">Menampilkan maksimal 50 nota terbaru. Buka isi nota untuk memeriksa barang dan sisa qty yang dapat diretur.</p>
				<?php foreach ($purchase_history as $purchase) { ?>
					<div class="panel panel-default">
						<div class="panel-heading">
							<strong><?php echo html_escape($purchase->nota); ?></strong>
							<span class="text-muted"> — <?php echo html_escape($purchase->purchased_at); ?></span>
							<span class="pull-right">Total: Rp <?php echo number_format((float) $purchase->amount, 0, ',', '.'); ?></span>
						</div>
						<div class="panel-body">
							<p><?php echo (int) $purchase->item_count; ?> baris barang |
								Pembayaran: <?php echo html_escape($purchase->payment_method); ?></p>
							<?php $items_panel_id = 'purchase-items-'.substr(sha1($purchase->nota), 0, 12); ?>
							<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
								<button class="btn btn-primary btn-sm" type="button" data-toggle="collapse" data-target="#<?php echo $items_panel_id; ?>" aria-expanded="false" aria-controls="<?php echo $items_panel_id; ?>">
									<i class="fa fa-eye" aria-hidden="true"></i> Lihat isi belanja nota ini
								</button>
								<a class="btn btn-sm btn-warning" href="<?php echo html_escape(site_url('main/sales_return?search_type=nota&search_value='.rawurlencode($purchase->nota))); ?>">Pilih nota ini untuk retur</a>
							</div>
							<div class="collapse" id="<?php echo $items_panel_id; ?>">
								<div class="table-responsive" style="margin-top:10px">
									<table class="table table-condensed table-bordered">
										<thead><tr><th>Barang</th><th>Barcode</th><th>Qty beli</th><th>Qty retur tercatat</th><th>Sisa retur</th><th>Harga</th><th>Subtotal</th></tr></thead>
										<tbody>
											<?php foreach ($purchase->items as $item) {
												$remaining = max(0, (int) $item->quantity - (int) $item->qty_returned);
											?>
												<tr>
													<td><?php echo html_escape($item->product_name ? $item->product_name : $item->barcode); ?></td>
													<td><?php echo html_escape($item->barcode); ?></td>
													<td><?php echo (int) $item->quantity; ?></td>
													<td><?php echo (int) $item->qty_returned; ?></td>
													<td><?php echo $remaining; ?></td>
													<td>Rp <?php echo number_format((float) $item->unit_price, 0, ',', '.'); ?></td>
													<td>Rp <?php echo number_format((float) $item->line_total, 0, ',', '.'); ?></td>
												</tr>
											<?php } ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				<?php } ?>
			<?php } ?>
		</div>
	</div>
<?php } ?>

<?php if ($sale && $search_type === 'nota' && $search_value !== '' &&
	(string) $sale->nota === $search_value) { ?>
	<div class="box box-info">
		<div class="box-header with-border"><h3 class="box-title">Detail Nota <?php echo html_escape($sale->nota); ?></h3></div>
		<div class="box-body">
			<p>
				Tanggal: <?php echo html_escape($sale->tgl); ?> |
				NPK: <?php echo html_escape($sale->id_karyawan); ?> |
				Pembayaran: <?php echo html_escape($sale->metode_pembayaran); ?>
			</p>
			<div class="alert alert-info">Qty retur yang masih menunggu persetujuan maupun yang sudah disetujui mengurangi sisa yang dapat diajukan. Nilai tagihan baru berkurang setelah manager menyetujui retur.</div>
			<form method="post" action="<?php echo site_url('main/sales_return/save'); ?>">
				<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
				<input type="hidden" name="nota_penjualan" value="<?php echo html_escape($sale->nota); ?>">
				<div class="table-responsive">
					<table class="table table-bordered table-striped">
						<thead>
							<tr>
								<th>Barang</th>
								<th>Barcode</th>
								<th>Terjual</th>
								<th>Qty retur tercatat</th>
								<th>Sisa retur</th>
								<th>Harga</th>
								<th>Qty retur</th>
								<th>Kondisi</th>
								<th>Kembalikan ke stok</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($items as $item) {
								$remaining = max(0, (int) $item->qty_terjual - (int) $item->qty_sudah_diretur);
								$unit_price = $item->dpp_satuan !== NULL
									? (float) $item->harga
									: (float) $item->harga * (1 + (float) $item->ppn);
							?>
								<tr>
									<td><?php echo html_escape($item->nama ? $item->nama : $item->barcode); ?></td>
									<td><?php echo html_escape($item->barcode); ?></td>
									<td><?php echo (int) $item->qty_terjual; ?></td>
									<td><?php echo (int) $item->qty_sudah_diretur; ?></td>
									<td><?php echo $remaining; ?></td>
									<td>Rp <?php echo number_format($unit_price, 0, ',', '.'); ?></td>
									<td>
										<input class="form-control" type="number" name="qty_retur[<?php echo (int) $item->id; ?>]" min="0" max="<?php echo $remaining; ?>" value="0">
									</td>
									<td>
										<select class="form-control" name="kondisi[<?php echo (int) $item->id; ?>]">
											<option value="layak_jual">Layak jual</option>
											<option value="rusak">Rusak</option>
										</select>
									</td>
									<td class="text-center">
										<input type="checkbox" name="kembali_ke_stok[<?php echo (int) $item->id; ?>]" value="1" checked>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
				<div class="form-group">
					<label for="alasan-retur">Alasan retur</label>
					<textarea class="form-control" id="alasan-retur" name="alasan" rows="3" maxlength="2000" required></textarea>
				</div>
				<button class="btn btn-warning" type="submit" onclick="return window.confirm('Ajukan retur ini? Stok dan jurnal baru berubah setelah manager menyetujui.');">Ajukan Retur</button>
			</form>
		</div>
	</div>
<?php } ?>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Riwayat Retur Penjualan</h3></div>
	<div class="box-body table-responsive">
		<table id="sales-return-history" class="table table-bordered table-striped"
			data-source="<?php echo html_escape(site_url('main/sales_return/returns_data')); ?>">
			<thead><tr><th>No. Retur</th><th>No. Nota</th><th>Tanggal</th><th>Item</th><th>Total</th><th>Pengaju</th><th>Status / keputusan</th><th>Aksi</th></tr></thead>
			<tbody></tbody>
		</table>
	</div>
</div>
