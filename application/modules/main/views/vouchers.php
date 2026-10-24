<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>


<?php if ($can_manage && $coupon_schema_available) { ?>
	<div class="box box-info">
		<div class="box-header with-border"><h3 class="box-title">Impor Voucher Anggota dari Excel</h3></div>
		<form method="post" enctype="multipart/form-data" action="<?php echo site_url('main/voucher/preview_voucher_import'); ?>">
			<input type="hidden" name="coupon_form_token" value="<?php echo html_escape($voucher_import_token); ?>">
			<div class="box-body">
				<p>Setiap baris membuat voucher nominal sekali pakai untuk satu NPK. Header kolom harus <code>NAMA_VOUCHER</code>, <code>NPK</code>, <code>NOMINAL</code>, <code>TGL_KADALUARSA</code>. Kode dibuat otomatis dari NPK dan nominal, misalnya <code>1544811-150000</code>. File XLS/XLSX maksimal 5 MB dan 10000 voucher.</p>
				<p>Format <code>TGL_KADALUARSA</code>: tanggal Excel, <code>YYYY-MM-DD</code> (contoh <code>2026-12-31</code>), <code>DD/MM/YYYY</code> (contoh <code>31/12/2026</code>), <code>YYYY-MM-DD HH:MM</code>, atau <code>YYYY-MM-DD HH:MM:SS</code>. Jika hanya tanggal, masa berlaku berakhir pukul 23:59:59.</p>
				<div class="form-group">
					<label for="voucher-file">File Excel</label>
					<input id="voucher-file" class="form-control" type="file" name="voucher_file" accept=".xls,.xlsx" required>
				</div>
			</div>
			<div class="box-footer"><button class="btn btn-info" type="submit">Preview dan validasi</button></div>
		</form>
	</div>
	<?php if (is_array($voucher_import_preview)) { ?>
	<div class="box box-<?php echo empty($voucher_import_preview['errors']) ? 'success' : 'danger'; ?>">
		<div class="box-header with-border"><h3 class="box-title">Preview impor (<?php echo count($voucher_import_preview['rows']); ?> baris)</h3></div>
		<div class="box-body">
			<?php if (!empty($voucher_import_preview['errors'])) { ?>
				<div class="alert alert-danger"><strong>Impor belum dapat dikonfirmasi:</strong><ul><?php foreach ($voucher_import_preview['errors'] as $message) { ?><li><?php echo html_escape($message); ?></li><?php } ?></ul></div>
			<?php } else { ?><p>Semua voucher lolos validasi. Belum ada data yang disimpan.</p><?php } ?>
			<div class="table-responsive" style="max-height:360px;overflow:auto;">
				<table class="table table-condensed table-striped">
					<thead><tr><th>Baris</th><th>Nama voucher</th><th>NPK</th><th>Kode</th><th>Nominal</th><th>Kedaluwarsa</th></tr></thead>
					<tbody><?php foreach ($voucher_import_preview['rows'] as $row) { ?><tr><td><?php echo (int) $row['row']; ?></td><td><?php echo html_escape($row['name']); ?></td><td><?php echo html_escape($row['assigned_npk']); ?></td><td><?php echo html_escape($row['serial_code']); ?></td><td>Rp <?php echo number_format((float) $row['amount'], 0, ',', '.'); ?></td><td><?php echo html_escape($row['expiry'] ? $row['expiry'] : 'Tidak valid'); ?></td></tr><?php } ?></tbody>
				</table>
			</div>
		</div>
		<?php if (empty($voucher_import_preview['errors'])) { ?>
		<div class="box-footer">
			<form method="post" action="<?php echo site_url('main/voucher/confirm_voucher_import'); ?>">
				<input type="hidden" name="coupon_form_token" value="<?php echo html_escape($voucher_import_token); ?>">
				<button class="btn btn-success" type="submit">Konfirmasi dan simpan semua voucher</button>
			</form>
		</div>
		<?php } ?>
	</div>
	<?php } ?>
<?php } ?>

<?php
$create_errors = $form_id === 0 ? $validation_errors : array();
$create_values = array_merge(array(
	'kode' => '', 'nama' => '', 'tipe' => 'diskon_nominal', 'nilai' => '',
	'min_belanja' => '0', 'max_diskon' => '', 'kuota' => '0',
	'tgl_mulai' => '', 'tgl_kadaluarsa' => ''
), $form_id === 0 ? $form_values : array());
?>

<style>
	.voucher-field-error:empty { display: none; }
	.voucher-form-error-summary { margin-bottom: 0; }
</style>

<?php if ($can_manage) { ?>
	<div class="box box-primary">
		<div class="box-header with-border"><h3 class="box-title">Tambah Voucher</h3></div>
		<form method="post" action="<?php echo site_url('main/voucher/save'); ?>" data-voucher-form novalidate>
			<div class="box-body">
				<div class="alert alert-danger voucher-form-error-summary" role="alert" style="display:none;"></div>
				<p class="help-block">Kuota 0 berarti tidak terbatas. Batas maksimum hanya berlaku untuk diskon persen.</p>
				<div class="row">
					<div class="col-sm-3">
						<div class="form-group<?php echo isset($create_errors['kode']) ? ' has-error' : ''; ?>">
							<label for="kode">Kode voucher</label>
							<input class="form-control" id="kode" name="kode" maxlength="32" pattern="(?:[A-Za-z0-9_.]|-)+" value="<?php echo html_escape($create_values['kode']); ?>" required>
							<span class="help-block voucher-field-error" data-error-for="kode" role="alert"><?php echo isset($create_errors['kode']) ? html_escape($create_errors['kode']) : ''; ?></span>
						</div>
					</div>
					<div class="col-sm-5">
						<div class="form-group<?php echo isset($create_errors['nama']) ? ' has-error' : ''; ?>">
							<label for="nama">Nama</label>
							<input class="form-control" id="nama" name="nama" maxlength="128" value="<?php echo html_escape($create_values['nama']); ?>" required>
							<span class="help-block voucher-field-error" data-error-for="nama" role="alert"><?php echo isset($create_errors['nama']) ? html_escape($create_errors['nama']) : ''; ?></span>
						</div>
					</div>
					<div class="col-sm-4">
						<div class="form-group<?php echo isset($create_errors['tipe']) ? ' has-error' : ''; ?>">
							<label for="tipe">Jenis</label>
							<select class="form-control" id="tipe" name="tipe" required>
								<option value="diskon_nominal"<?php echo $create_values['tipe'] === 'diskon_nominal' ? ' selected' : ''; ?>>Diskon nominal (Rp)</option>
								<option value="diskon_persen"<?php echo $create_values['tipe'] === 'diskon_persen' ? ' selected' : ''; ?>>Diskon persentase (%)</option>
								<option value="cashback"<?php echo $create_values['tipe'] === 'cashback' ? ' selected' : ''; ?>>Cashback (Rp)</option>
							</select>
							<span class="help-block voucher-field-error" data-error-for="tipe" role="alert"><?php echo isset($create_errors['tipe']) ? html_escape($create_errors['tipe']) : ''; ?></span>
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-sm-2">
						<div class="form-group<?php echo isset($create_errors['nilai']) ? ' has-error' : ''; ?>">
							<label for="nilai">Nilai</label>
							<input class="form-control" type="number" id="nilai" name="nilai" min="0.01" max="9999999999999.99" step="0.01" value="<?php echo html_escape($create_values['nilai']); ?>" required>
							<span class="help-block voucher-field-error" data-error-for="nilai" role="alert"><?php echo isset($create_errors['nilai']) ? html_escape($create_errors['nilai']) : ''; ?></span>
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group<?php echo isset($create_errors['min_belanja']) ? ' has-error' : ''; ?>">
							<label for="min_belanja">Minimum belanja (Rp)</label>
							<input class="form-control" type="number" id="min_belanja" name="min_belanja" min="0" max="2147483647" step="1" value="<?php echo html_escape($create_values['min_belanja']); ?>" required>
							<span class="help-block voucher-field-error" data-error-for="min_belanja" role="alert"><?php echo isset($create_errors['min_belanja']) ? html_escape($create_errors['min_belanja']) : ''; ?></span>
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group<?php echo isset($create_errors['max_diskon']) ? ' has-error' : ''; ?>">
							<label for="max_diskon">Maks. diskon (Rp)</label>
							<input class="form-control" type="number" id="max_diskon" name="max_diskon" min="0.01" max="9999999999999.99" step="0.01" value="<?php echo html_escape($create_values['max_diskon']); ?>">
							<span class="help-block voucher-field-error" data-error-for="max_diskon" role="alert"><?php echo isset($create_errors['max_diskon']) ? html_escape($create_errors['max_diskon']) : ''; ?></span>
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group<?php echo isset($create_errors['kuota']) ? ' has-error' : ''; ?>">
							<label for="kuota">Kuota (0 = tanpa batas)</label>
							<input class="form-control" type="number" id="kuota" name="kuota" min="0" max="2147483647" step="1" value="<?php echo html_escape($create_values['kuota']); ?>" required>
							<span class="help-block voucher-field-error" data-error-for="kuota" role="alert"><?php echo isset($create_errors['kuota']) ? html_escape($create_errors['kuota']) : ''; ?></span>
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group<?php echo isset($create_errors['tgl_mulai']) ? ' has-error' : ''; ?>">
							<label for="tgl_mulai">Mulai berlaku</label>
							<input class="form-control" type="datetime-local" id="tgl_mulai" name="tgl_mulai" value="<?php echo html_escape($create_values['tgl_mulai']); ?>" required>
							<span class="help-block voucher-field-error" data-error-for="tgl_mulai" role="alert"><?php echo isset($create_errors['tgl_mulai']) ? html_escape($create_errors['tgl_mulai']) : ''; ?></span>
						</div>
					</div>
					<div class="col-sm-2">
						<div class="form-group<?php echo isset($create_errors['tgl_kadaluarsa']) ? ' has-error' : ''; ?>">
							<label for="tgl_kadaluarsa">Kedaluwarsa</label>
							<input class="form-control" type="datetime-local" id="tgl_kadaluarsa" name="tgl_kadaluarsa" value="<?php echo html_escape($create_values['tgl_kadaluarsa']); ?>" required>
							<span class="help-block voucher-field-error" data-error-for="tgl_kadaluarsa" role="alert"><?php echo isset($create_errors['tgl_kadaluarsa']) ? html_escape($create_errors['tgl_kadaluarsa']) : ''; ?></span>
						</div>
					</div>
				</div>
			</div>
			<div class="box-footer"><button class="btn btn-primary" type="submit">Simpan voucher</button></div>
		</form>
	</div>
<?php } ?>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Daftar Voucher</h3></div>
	<div class="box-body table-responsive">
		<form method="get" action="<?php echo site_url('main/voucher'); ?>" class="form-inline" style="margin-bottom:12px;">
			<div class="form-group">
				<label for="voucher-search">Cari kode atau nama&nbsp;</label>
				<input id="voucher-search" class="form-control" type="search" name="q" maxlength="100" value="<?php echo html_escape($voucher_search); ?>" placeholder="Kode atau nama voucher">
			</div>
			<div class="form-group" style="margin-left:8px;">
				<label for="voucher-page-size">Per halaman&nbsp;</label>
				<select id="voucher-page-size" class="form-control" name="per_page">
					<?php foreach (array(25, 50, 100) as $page_size) { ?><option value="<?php echo $page_size; ?>"<?php echo $voucher_page_size === $page_size ? ' selected' : ''; ?>><?php echo $page_size; ?></option><?php } ?>
				</select>
			</div>
			<button class="btn btn-primary" type="submit">Cari</button>
			<a class="btn btn-default" href="<?php echo site_url('main/voucher'); ?>">Reset</a>
		</form>
		<p class="text-muted">Menampilkan <?php echo $voucher_total ? (($voucher_page - 1) * $voucher_page_size + 1) : 0; ?>–<?php echo min($voucher_page * $voucher_page_size, $voucher_total); ?> dari <?php echo number_format($voucher_total, 0, ',', '.'); ?> voucher</p>
		<table id="voucher-master-table" class="table table-striped table-bordered">
			<thead>
				<tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Minimum</th><th>Kuota</th><th>Masa berlaku</th><th>Status</th><?php if ($can_manage) { ?><th>Aksi</th><?php } ?></tr>
			</thead>
			<tbody>
				<?php
				$type_labels = array(
					'diskon_nominal' => 'Diskon nominal',
					'diskon_persen' => 'Diskon persen',
					'cashback' => 'Cashback',
				);
				foreach ($vouchers as $voucher) {
					$is_expired = $voucher->tgl_kadaluarsa < date('Y-m-d H:i:s');
					$is_upcoming = $voucher->tgl_mulai > date('Y-m-d H:i:s');
					$is_quota_full = (int) $voucher->kuota > 0 && (int) $voucher->terpakai >= (int) $voucher->kuota;
					$row_form_errors = $form_id === (int) $voucher->id ? $validation_errors : array();
					$row_form_values = array_merge(array(
						'kode' => $voucher->kode,
						'nama' => $voucher->nama,
						'tipe' => $voucher->tipe,
						'nilai' => $voucher->nilai,
						'min_belanja' => (string) (int) $voucher->min_belanja,
						'max_diskon' => $voucher->max_diskon === NULL ? '' : $voucher->max_diskon,
						'kuota' => (string) (int) $voucher->kuota,
						'tgl_mulai' => date('Y-m-d\TH:i', strtotime($voucher->tgl_mulai)),
						'tgl_kadaluarsa' => date('Y-m-d\TH:i', strtotime($voucher->tgl_kadaluarsa))
					), $row_form_errors ? $form_values : array());
				?>
					<tr>
						<td><?php echo html_escape($voucher->kode); ?></td>
						<td><?php echo html_escape($voucher->nama); ?></td>
						<td>
							<?php echo isset($type_labels[$voucher->tipe]) ? $type_labels[$voucher->tipe] : html_escape($voucher->tipe); ?>
							(<?php echo $voucher->tipe === 'diskon_persen'
								? rtrim(rtrim(number_format((float) $voucher->nilai, 2, ',', '.'), '0'), ',').'%' :
								'Rp '.number_format((float) $voucher->nilai, 0, ',', '.'); ?>)
							<?php if ($voucher->tipe === 'diskon_persen' && $voucher->max_diskon !== NULL) { ?>
								<br><small>Maks. Rp <?php echo number_format((float) $voucher->max_diskon, 0, ',', '.'); ?></small>
							<?php } ?>
						</td>
						<td>Rp <?php echo number_format((float) $voucher->min_belanja, 0, ',', '.'); ?></td>
						<td><?php echo (int) $voucher->terpakai; ?> / <?php echo (int) $voucher->kuota > 0 ? (int) $voucher->kuota : '&infin;'; ?></td>
						<td><?php echo html_escape($voucher->tgl_mulai); ?><br>s.d. <?php echo html_escape($voucher->tgl_kadaluarsa); ?></td>
						<td>
							<?php if (!(int) $voucher->is_active) { ?>
								<span class="label label-default">Nonaktif</span>
							<?php } elseif ($is_expired) { ?>
								<span class="label label-danger">Kedaluwarsa</span>
							<?php } elseif ($is_upcoming) { ?>
								<span class="label label-info">Belum berlaku</span>
							<?php } elseif ($is_quota_full) { ?>
								<span class="label label-warning">Kuota habis</span>
							<?php } else { ?>
								<span class="label label-success">Aktif</span>
							<?php } ?>
						</td>
						<?php if ($can_manage) { ?>
							<td>
								<a class="btn btn-xs btn-info" href="<?php echo site_url('main/voucher/coupons/'.(int) $voucher->id); ?>">Kupon serial</a>
								<details<?php echo count($row_form_errors) > 0 ? ' open' : ''; ?>>
									<summary>Edit</summary>
									<form method="post" action="<?php echo site_url('main/voucher/save/'.(int) $voucher->id); ?>" style="min-width:280px;padding-top:8px;" data-voucher-form novalidate>
										<div class="alert alert-danger voucher-form-error-summary" role="alert" style="display:none;"></div>
										<div class="form-group<?php echo isset($row_form_errors['kode']) ? ' has-error' : ''; ?>">
											<label>Kode voucher</label>
											<input class="form-control" name="kode" maxlength="32" pattern="(?:[A-Za-z0-9_.]|-)+" value="<?php echo html_escape($row_form_values['kode']); ?>" required>
											<span class="help-block voucher-field-error" data-error-for="kode" role="alert"><?php echo isset($row_form_errors['kode']) ? html_escape($row_form_errors['kode']) : ''; ?></span>
										</div>
										<div class="form-group<?php echo isset($row_form_errors['nama']) ? ' has-error' : ''; ?>">
											<label>Nama</label>
											<input class="form-control" name="nama" maxlength="128" value="<?php echo html_escape($row_form_values['nama']); ?>" required>
											<span class="help-block voucher-field-error" data-error-for="nama" role="alert"><?php echo isset($row_form_errors['nama']) ? html_escape($row_form_errors['nama']) : ''; ?></span>
										</div>
										<div class="form-group<?php echo isset($row_form_errors['tipe']) ? ' has-error' : ''; ?>">
											<label>Jenis</label>
											<select class="form-control" name="tipe" required>
												<?php foreach ($type_labels as $type => $label) { ?>
													<option value="<?php echo html_escape($type); ?>"<?php echo $row_form_values['tipe'] === $type ? ' selected' : ''; ?>><?php echo html_escape($label); ?></option>
												<?php } ?>
											</select>
											<span class="help-block voucher-field-error" data-error-for="tipe" role="alert"><?php echo isset($row_form_errors['tipe']) ? html_escape($row_form_errors['tipe']) : ''; ?></span>
										</div>
										<div class="form-group<?php echo isset($row_form_errors['nilai']) ? ' has-error' : ''; ?>">
											<label>Nilai</label>
											<input class="form-control" type="number" name="nilai" min="0.01" max="9999999999999.99" step="0.01" value="<?php echo html_escape($row_form_values['nilai']); ?>" required>
											<span class="help-block voucher-field-error" data-error-for="nilai" role="alert"><?php echo isset($row_form_errors['nilai']) ? html_escape($row_form_errors['nilai']) : ''; ?></span>
										</div>
										<div class="form-group<?php echo isset($row_form_errors['min_belanja']) ? ' has-error' : ''; ?>">
											<label>Minimum belanja (Rp)</label>
											<input class="form-control" type="number" name="min_belanja" min="0" max="2147483647" step="1" value="<?php echo html_escape($row_form_values['min_belanja']); ?>" required>
											<span class="help-block voucher-field-error" data-error-for="min_belanja" role="alert"><?php echo isset($row_form_errors['min_belanja']) ? html_escape($row_form_errors['min_belanja']) : ''; ?></span>
										</div>
										<div class="form-group<?php echo isset($row_form_errors['max_diskon']) ? ' has-error' : ''; ?>">
											<label>Maksimum diskon (Rp, opsional)</label>
											<input class="form-control" type="number" name="max_diskon" min="0.01" max="9999999999999.99" step="0.01" value="<?php echo html_escape($row_form_values['max_diskon']); ?>">
											<span class="help-block voucher-field-error" data-error-for="max_diskon" role="alert"><?php echo isset($row_form_errors['max_diskon']) ? html_escape($row_form_errors['max_diskon']) : ''; ?></span>
										</div>
										<div class="form-group<?php echo isset($row_form_errors['kuota']) ? ' has-error' : ''; ?>">
											<label>Kuota (0 = tanpa batas)</label>
											<input class="form-control" type="number" name="kuota" min="0" max="2147483647" step="1" value="<?php echo html_escape($row_form_values['kuota']); ?>" required>
											<span class="help-block voucher-field-error" data-error-for="kuota" role="alert"><?php echo isset($row_form_errors['kuota']) ? html_escape($row_form_errors['kuota']) : ''; ?></span>
										</div>
										<div class="form-group<?php echo isset($row_form_errors['tgl_mulai']) ? ' has-error' : ''; ?>">
											<label>Mulai berlaku</label>
											<input class="form-control" type="datetime-local" name="tgl_mulai" value="<?php echo html_escape($row_form_values['tgl_mulai']); ?>" required>
											<span class="help-block voucher-field-error" data-error-for="tgl_mulai" role="alert"><?php echo isset($row_form_errors['tgl_mulai']) ? html_escape($row_form_errors['tgl_mulai']) : ''; ?></span>
										</div>
										<div class="form-group<?php echo isset($row_form_errors['tgl_kadaluarsa']) ? ' has-error' : ''; ?>">
											<label>Kedaluwarsa</label>
											<input class="form-control" type="datetime-local" name="tgl_kadaluarsa" value="<?php echo html_escape($row_form_values['tgl_kadaluarsa']); ?>" required>
											<span class="help-block voucher-field-error" data-error-for="tgl_kadaluarsa" role="alert"><?php echo isset($row_form_errors['tgl_kadaluarsa']) ? html_escape($row_form_errors['tgl_kadaluarsa']) : ''; ?></span>
										</div>
										<button class="btn btn-primary btn-xs" type="submit">Simpan perubahan</button>
									</form>
								</details>
								<form method="post" action="<?php echo site_url('main/voucher/toggle/'.(int) $voucher->id); ?>" style="margin-top:8px;">
									<button class="btn btn-xs <?php echo (int) $voucher->is_active ? 'btn-warning' : 'btn-success'; ?>" type="submit">
										<?php echo (int) $voucher->is_active ? 'Nonaktifkan' : 'Aktifkan'; ?>
									</button>
								</form>
							</td>
						<?php } ?>
					</tr>
				<?php } ?>
				<?php if (count($vouchers) === 0) { ?>
					<tr><td colspan="<?php echo $can_manage ? 8 : 7; ?>" class="text-center">Belum ada voucher.</td></tr>
				<?php } ?>
			</tbody>
		</table>
		<?php if ($voucher_pages > 1) {
			$page_start = max(1, $voucher_page - 2);
			$page_end = min($voucher_pages, $page_start + 4);
			$page_start = max(1, $page_end - 4);
		?>
		<ul class="pagination pagination-sm" aria-label="Halaman daftar voucher">
			<?php if ($voucher_page > 1) { ?><li><a href="<?php echo site_url('main/voucher').'?'.http_build_query(array('q' => $voucher_search, 'per_page' => $voucher_page_size, 'page' => 1)); ?>">Pertama</a></li><li><a href="<?php echo site_url('main/voucher').'?'.http_build_query(array('q' => $voucher_search, 'per_page' => $voucher_page_size, 'page' => $voucher_page - 1)); ?>">Sebelumnya</a></li><?php } else { ?><li class="disabled"><span>Pertama</span></li><li class="disabled"><span>Sebelumnya</span></li><?php } ?>
			<?php for ($page_number = $page_start; $page_number <= $page_end; $page_number++) { ?>
				<li<?php echo $page_number === $voucher_page ? ' class="active"' : ''; ?>><a href="<?php echo site_url('main/voucher').'?'.http_build_query(array('q' => $voucher_search, 'per_page' => $voucher_page_size, 'page' => $page_number)); ?>"><?php echo $page_number; ?></a></li>
			<?php } ?>
			<?php if ($voucher_page < $voucher_pages) { ?><li><a href="<?php echo site_url('main/voucher').'?'.http_build_query(array('q' => $voucher_search, 'per_page' => $voucher_page_size, 'page' => $voucher_page + 1)); ?>">Berikutnya</a></li><li><a href="<?php echo site_url('main/voucher').'?'.http_build_query(array('q' => $voucher_search, 'per_page' => $voucher_page_size, 'page' => $voucher_pages)); ?>">Terakhir</a></li><?php } else { ?><li class="disabled"><span>Berikutnya</span></li><li class="disabled"><span>Terakhir</span></li><?php } ?>
		</ul>
		<?php } ?>
	</div>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Riwayat Penggunaan Voucher</h3></div>
	<div class="box-body table-responsive">
		<form id="voucher-npk-filter" class="form-inline" style="margin-bottom:12px;">
			<div class="form-group">
				<label for="voucher-npk">Cari berdasarkan NPK pembeli&nbsp;</label>
				<input id="voucher-npk" class="form-control" type="search" maxlength="32" placeholder="Masukkan NPK">
			</div>
			<button class="btn btn-primary" type="submit">Cari</button>
			<button class="btn btn-default" id="voucher-npk-reset" type="button">Reset</button>
		</form>
		<table id="voucher-usage-table" class="table table-striped table-bordered" data-source="<?php echo html_escape(site_url('main/voucher/usage_data')); ?>">
			<thead><tr><th>Waktu</th><th>Kode</th><th>Nama voucher</th><th>No. nota</th><th>NPK anggota</th><th>Serial kupon</th><th>Diskon</th></tr></thead>
			<tbody></tbody>
		</table>
	</div>
</div>
