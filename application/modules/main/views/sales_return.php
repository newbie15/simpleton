<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Cari Nota Penjualan</h3></div>
	<form method="get" action="<?php echo site_url('main/sales_return'); ?>">
		<div class="box-body">
			<div class="row">
				<div class="col-sm-6">
					<div class="form-group">
						<label for="nota-penjualan">Nomor nota</label>
						<input class="form-control" id="nota-penjualan" name="nota" maxlength="64" value="<?php echo html_escape($nota); ?>" required>
					</div>
				</div>
				<div class="col-sm-2">
					<div class="form-group">
						<label>&nbsp;</label>
						<button class="btn btn-primary btn-block" type="submit">Cari Nota</button>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>

<?php if ($nota !== '' && !$sale) { ?>
	<div class="alert alert-warning">Nota tidak ditemukan. Periksa nomor nota lalu coba kembali.</div>
<?php } ?>

<?php if ($sale) { ?>
	<div class="box box-info">
		<div class="box-header with-border"><h3 class="box-title">Detail Nota <?php echo html_escape($sale->nota); ?></h3></div>
		<div class="box-body">
			<p>
				Tanggal: <?php echo html_escape($sale->tgl); ?> |
				NPK: <?php echo html_escape($sale->id_karyawan); ?> |
				Pembayaran: <?php echo html_escape($sale->metode_pembayaran); ?>
			</p>
			<div class="alert alert-info">Qty retur pending menahan sisa barang sementara. Nilai tagihan baru berkurang setelah manager menyetujui retur.</div>
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
								<th>Qty tertahan (pending/approved)</th>
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
								$unit_price = (float) $item->harga * (1 + (float) $item->ppn);
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
	<div class="box-header with-border"><h3 class="box-title">Riwayat Retur Terbaru</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>No. Retur</th><th>No. Nota</th><th>Tanggal</th><th>Item</th><th>Total</th><th>Pengaju</th><th>Status / keputusan</th><th>Aksi</th></tr></thead>
			<tbody>
				<?php foreach ($returns as $return) { ?>
					<tr>
						<td><?php echo html_escape($return->nomor_retur); ?></td>
						<td><?php echo html_escape($return->nota_penjualan); ?></td>
						<td><?php echo html_escape($return->tgl_retur); ?></td>
						<td><?php echo (int) $return->item_count; ?></td>
						<td>Rp <?php echo number_format((float) $return->total_retur, 0, ',', '.'); ?></td>
						<td><?php echo html_escape($return->id_user); ?></td>
						<td>
							<?php echo html_escape(ucfirst($return->status === 'completed' ? 'approved' : $return->status)); ?>
							<?php if ($return->status === 'rejected' && $return->rejection_reason) { ?><br><small><?php echo html_escape($return->rejection_reason); ?></small><?php } ?>
						</td>
						<td>
							<?php if ($return->status === 'pending' && $is_manager) { ?>
								<form method="post" action="<?php echo site_url('main/sales_return/approve/'.(int) $return->id); ?>" style="margin-bottom:5px;" onsubmit="return window.confirm('Setujui retur ini? Stok dan jurnal akan diperbarui.');">
									<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
									<button class="btn btn-xs btn-success" type="submit">Setujui</button>
								</form>
								<form method="post" action="<?php echo site_url('main/sales_return/reject/'.(int) $return->id); ?>">
									<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
									<div class="input-group input-group-sm"><input class="form-control" name="rejection_reason" maxlength="500" placeholder="Alasan penolakan" required><span class="input-group-btn"><button class="btn btn-danger" type="submit">Tolak</button></span></div>
								</form>
							<?php } elseif ($return->status === 'pending') { ?>
								<span class="text-muted">Menunggu manager</span>
							<?php } elseif ($return->approved_by) { ?>
								<small>Disetujui oleh <?php echo html_escape($return->approved_by); ?></small>
							<?php } elseif ($return->rejected_by) { ?>
								<small>Ditolak oleh <?php echo html_escape($return->rejected_by); ?></small>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
				<?php if (count($returns) === 0) { ?>
					<tr><td colspan="8" class="text-center">Belum ada retur penjualan.</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
