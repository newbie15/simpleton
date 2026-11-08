<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="row">
	<div class="col-md-12">
		<div class="box box-primary">
			<div class="box-header with-border">
				<h3 class="box-title"><i class="fa fa-book"></i> Manajemen Simpanan Koperasi</h3>
				<div class="box-tools pull-right">
					<button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modal-transaksi-simpanan">
						<i class="fa fa-plus-circle"></i> Input Transaksi Baru
					</button>
					<button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modal-upload-preview">
						<i class="fa fa-cloud-upload"></i> Upload Excel / CSV
					</button>
					<button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#modal-generate-wajib">
						<i class="fa fa-refresh"></i> Tagih Simpanan Wajib
					</button>
				</div>
			</div>
			<div class="box-body">
				<!-- Tabs Navigasi -->
				<ul class="nav nav-tabs" style="margin-bottom: 20px;">
					<li <?= ($tab_active === 'rekap') ? 'class="active"' : '' ?>>
						<a href="?tab=rekap"><i class="fa fa-list-alt text-blue"></i> <strong>Rekap Saldo Semua Anggota</strong></a>
					</li>
					<li <?= ($tab_active === 'pokok') ? 'class="active"' : '' ?>>
						<a href="?tab=pokok"><i class="fa fa-bank text-green"></i> Simpanan Pokok</a>
					</li>
					<li <?= ($tab_active === 'wajib') ? 'class="active"' : '' ?>>
						<a href="?tab=wajib"><i class="fa fa-calendar-check-o text-yellow"></i> Simpanan Wajib</a>
					</li>
					<li <?= ($tab_active === 'sukarela') ? 'class="active"' : '' ?>>
						<a href="?tab=sukarela"><i class="fa fa-money text-purple"></i> Simpanan Sukarela</a>
					</li>
					<li <?= ($tab_active === 'tagihan_wajib') ? 'class="active"' : '' ?>>
						<a href="?tab=tagihan_wajib"><i class="fa fa-clock-o text-red"></i> Monitoring Wajib Bulanan</a>
					</li>
				</ul>

				<!-- Stats Box -->
				<div class="row" style="margin-bottom: 15px;">
					<div class="col-md-3">
						<div class="info-box bg-green">
							<span class="info-box-icon"><i class="fa fa-bank"></i></span>
							<div class="info-box-content">
								<span class="info-box-text">Simpanan Pokok (3101)</span>
								<span class="info-box-number">Rp <?= number_format($rekap_totals->grand_pokok, 0, ',', '.') ?></span>
								<span class="progress-description">Ekuitas Anggota Tetap</span>
							</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="info-box bg-yellow">
							<span class="info-box-icon"><i class="fa fa-calendar"></i></span>
							<div class="info-box-content">
								<span class="info-box-text">Simpanan Wajib (3102)</span>
								<span class="info-box-number">Rp <?= number_format($rekap_totals->grand_wajib, 0, ',', '.') ?></span>
								<span class="progress-description">Iuran Bulanan Koperasi</span>
							</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="info-box bg-purple">
							<span class="info-box-icon"><i class="fa fa-money"></i></span>
							<div class="info-box-content">
								<span class="info-box-text">Simpanan Sukarela (2102)</span>
								<span class="info-box-number">Rp <?= number_format($rekap_totals->grand_sukarela, 0, ',', '.') ?></span>
								<span class="progress-description">Kewajiban / Tabungan</span>
							</div>
						</div>
					</div>
					<div class="col-md-3">
						<div class="info-box bg-blue">
							<span class="info-box-icon"><i class="fa fa-calculator"></i></span>
							<div class="info-box-content">
								<span class="info-box-text">Total Hak Simpanan</span>
								<span class="info-box-number">Rp <?= number_format($rekap_totals->grand_total, 0, ',', '.') ?></span>
								<span class="progress-description"><?= count($karyawan_all) ?> Anggota Terdaftar</span>
							</div>
						</div>
					</div>
				</div>

				<!-- TAB CONTENT 1: REKAP SALDO SEMUA ANGGOTA -->
				<?php if ($tab_active === 'rekap'): ?>
					<div class="box box-solid box-default" style="border: 1px solid #e1e4e8;">
						<div class="box-header with-border">
							<h3 class="box-title"><i class="fa fa-table"></i> Rekapitulasi Saldo Keseluruhan per Anggota</h3>
						</div>
						<div class="box-body table-responsive">
							<table class="table table-bordered table-striped table-hover data-table-simpanan" id="table-rekap-semua">
								<thead>
									<tr class="bg-gray-light">
										<th style="width: 80px;">NPK</th>
										<th>Nama Anggota</th>
										<th>Bagian</th>
										<th style="text-align: center; width: 80px;">Status</th>
										<th style="text-align: right; width: 130px;">Simp. Pokok</th>
										<th style="text-align: right; width: 130px;">Simp. Wajib</th>
										<th style="text-align: right; width: 130px;">Simp. Sukarela</th>
										<th style="text-align: right; width: 140px; background-color: #f4fbf5;">Total Simpanan</th>
										<th style="text-align: center; width: 160px;">Aksi</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($members_summary as $m): 
										$is_active = (int) $m->is_active;
									?>
										<tr>
											<td><strong><?= htmlspecialchars($m->npk) ?></strong></td>
											<td>
												<?= htmlspecialchars($m->nama) ?>
												<?php if (!$is_active): ?>
													<span class="label label-danger pull-right">Resign</span>
												<?php endif; ?>
											</td>
											<td><?= htmlspecialchars($m->bagian ? $m->bagian : '-') ?></td>
											<td style="text-align: center;">
												<?php if ($is_active === 1): ?>
													<span class="label label-success">Aktif</span>
												<?php else: ?>
													<span class="label label-default" title="Resign: <?= htmlspecialchars($m->resign_date ? $m->resign_date : '') ?>">Resign</span>
												<?php endif; ?>
											</td>
											<td class="text-right" data-order="<?= $m->saldo_pokok ?>">Rp <?= number_format($m->saldo_pokok, 0, ',', '.') ?></td>
											<td class="text-right" data-order="<?= $m->saldo_wajib ?>">Rp <?= number_format($m->saldo_wajib, 0, ',', '.') ?></td>
											<td class="text-right" data-order="<?= $m->saldo_sukarela ?>">Rp <?= number_format($m->saldo_sukarela, 0, ',', '.') ?></td>
											<td class="text-right" data-order="<?= $m->total_saldo ?>" style="font-weight: bold; color: #1b8a3e; background-color: #f4fbf5;">
												Rp <?= number_format($m->total_saldo, 0, ',', '.') ?>
											</td>
											<td style="text-align: center;">
												<button type="button" 
												        class="btn btn-xs btn-primary btn-quick-transaksi" 
												        data-id="<?= $m->id ?>"
												        data-npk="<?= htmlspecialchars($m->npk) ?>"
												        data-nama="<?= htmlspecialchars($m->nama) ?>"
												        data-active="<?= $is_active ?>"
												        data-jenis="sukarela"
												        title="Input Transaksi">
													<i class="fa fa-plus"></i> Transaksi
												</button>
												<a href="<?= base_url('index.php/main/simpanan/history/'.$m->id.'?jenis=semua') ?>" 
												   class="btn btn-xs btn-info" title="Lihat Rekening Koran Anggota">
													<i class="fa fa-history"></i> Rekening
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>

				<!-- TAB CONTENT 2: DETAIL PER JENIS SIMPANAN (POKOK / WAJIB / SUKARELA) -->
				<?php elseif (in_array($tab_active, array('pokok', 'wajib', 'sukarela'))): ?>
					<div class="box box-solid box-default" style="border: 1px solid #e1e4e8;">
						<div class="box-header with-border">
							<h3 class="box-title">Daftar Anggota - Simpanan <?= ucfirst($jenis_selected) ?></h3>
						</div>
						<div class="box-body table-responsive">
							<table class="table table-bordered table-striped table-hover data-table-simpanan" id="table-per-jenis">
								<thead>
									<tr class="bg-gray-light">
										<th style="width: 100px;">NPK</th>
										<th>Nama Anggota</th>
										<th>Bagian</th>
										<th style="text-align: center; width: 100px;">Status</th>
										<th style="text-align: right; width: 160px;">Saldo <?= ucfirst($jenis_selected) ?></th>
										<th style="text-align: center; width: 90px;">Mutasi</th>
										<th style="text-align: center; width: 170px;">Aksi</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($members_savings as $member): 
										$is_active = isset($member->is_active) ? (int) $member->is_active : 1;
										$saldo = isset($member->saldo) ? (float) $member->saldo : 0;
									?>
										<tr>
											<td><strong><?= htmlspecialchars($member->npk) ?></strong></td>
											<td>
												<?= htmlspecialchars($member->nama) ?>
												<?php if (!$is_active): ?>
													<span class="label label-danger pull-right">Resign</span>
												<?php endif; ?>
											</td>
											<td><?= htmlspecialchars($member->bagian ? $member->bagian : '-') ?></td>
											<td style="text-align: center;">
												<?php if ($is_active === 1): ?>
													<span class="label label-success">Aktif</span>
												<?php else: ?>
													<span class="label label-default" title="Resign: <?= htmlspecialchars($member->resign_date ? $member->resign_date : '') ?>">Resign</span>
												<?php endif; ?>
											</td>
											<td data-order="<?= $saldo ?>" style="text-align: right; color: #1b8a3e; font-weight: bold; font-size: 14px;">
												Rp <?= number_format($saldo, 0, ',', '.') ?>
											</td>
											<td data-order="<?= $member->mutasi_count ?>" style="text-align: center;">
												<span class="badge bg-gray"><?= $member->mutasi_count ?></span>
											</td>
											<td style="text-align: center;">
												<button type="button" 
												        class="btn btn-xs btn-primary btn-quick-transaksi" 
												        data-id="<?= $member->id ?>"
												        data-npk="<?= htmlspecialchars($member->npk) ?>"
												        data-nama="<?= htmlspecialchars($member->nama) ?>"
												        data-active="<?= $is_active ?>"
												        data-saldo="<?= $saldo ?>"
												        data-jenis="<?= $jenis_selected ?>"
												        title="Input Transaksi">
													<i class="fa fa-plus"></i> Transaksi
												</button>
												<a href="<?= base_url('index.php/main/simpanan/history/'.$member->id.'?jenis='.$jenis_selected) ?>" 
												   class="btn btn-xs btn-info" title="Lihat Riwayat">
													<i class="fa fa-history"></i> Riwayat
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>

				<!-- TAB CONTENT 3: MONITORING SIMPANAN WAJIB BULANAN -->
				<?php elseif ($tab_active === 'tagihan_wajib'): ?>
					<div class="row">
						<div class="col-md-4">
							<div class="box box-solid box-primary">
								<div class="box-header with-border">
									<h3 class="box-title"><i class="fa fa-filter"></i> Filter & Status Periode</h3>
								</div>
								<div class="box-body">
									<form method="get" action="">
										<input type="hidden" name="tab" value="tagihan_wajib">
										<div class="form-group">
											<label>Pilih Periode Bulan</label>
											<div class="input-group">
												<input type="month" class="form-control" name="periode" value="<?= htmlspecialchars($periode_ini) ?>">
												<span class="input-group-btn">
													<button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Cek</button>
												</span>
											</div>
										</div>
									</form>

									<hr>
									<h4>Status Pembayaran (<?= date('F Y', strtotime($periode_ini.'-01')) ?>)</h4>
									<ul class="list-group list-group-unbordered">
										<li class="list-group-item">
											<b>Total Anggota Aktif</b> <span class="pull-right badge bg-blue"><?= $total_anggota_aktif ?></span>
										</li>
										<li class="list-group-item">
											<b>Sudah Menyetor Wajib</b> <span class="pull-right badge bg-green"><?= $total_wajib_lunas ?></span>
										</li>
										<li class="list-group-item">
											<b>Belum / Menunggak</b> <span class="pull-right badge bg-red"><?= $total_wajib_belum ?></span>
										</li>
									</ul>

									<button type="button" class="btn btn-warning btn-block" data-toggle="modal" data-target="#modal-generate-wajib">
										<i class="fa fa-bolt"></i> Generate Tagihan / Potong Gaji Masal
									</button>
								</div>
							</div>
						</div>

						<div class="col-md-8">
							<div class="box box-solid box-default" style="border: 1px solid #ddd;">
								<div class="box-header with-border">
									<h3 class="box-title"><i class="fa fa-history"></i> Riwayat Generate Tagihan Simpanan Wajib</h3>
								</div>
								<div class="box-body table-responsive">
									<table class="table table-bordered table-striped">
										<thead>
											<tr>
												<th>Periode</th>
												<th>Nominal/Org</th>
												<th>Jumlah Anggota</th>
												<th>Total Diposting</th>
												<th>Status</th>
												<th>Waktu Generate</th>
											</tr>
										</thead>
										<tbody>
											<?php if (!empty($batch_tagihan)): ?>
												<?php foreach ($batch_tagihan as $bt): ?>
													<tr>
														<td><strong><?= htmlspecialchars($bt->periode) ?></strong></td>
														<td>Rp <?= number_format($bt->nominal, 0, ',', '.') ?></td>
														<td><?= $bt->total_anggota ?> anggota</td>
														<td style="font-weight: bold; color: #1b8a3e;">Rp <?= number_format($bt->total_nominal, 0, ',', '.') ?></td>
														<td><span class="label label-success"><i class="fa fa-check"></i> Posted</span></td>
														<td><?= $bt->posted_at ?></td>
													</tr>
												<?php endforeach; ?>
											<?php else: ?>
												<tr>
													<td colspan="6" class="text-center text-muted" style="padding: 20px;">Belum ada riwayat posting batch tagihan simpanan wajib.</td>
												</tr>
											<?php endif; ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
				<?php endif; ?>

			</div>
		</div>
	</div>
</div>

<!-- Modal 1: Input Transaksi Simpanan Langsung -->
<div class="modal fade" id="modal-transaksi-simpanan" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form id="form-transaksi-simpanan">
				<div class="modal-header bg-primary text-white">
					<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
					<h4 class="modal-title"><i class="fa fa-calculator"></i> Form Transaksi Simpanan Anggota</h4>
				</div>
				<div class="modal-body">
					<div id="modal-alert-box" style="display: none;"></div>

					<div class="form-group">
						<label for="trans_karyawan_id">Pilih Anggota / Karyawan <span class="text-danger">*</span></label>
						<select class="form-control select2" id="trans_karyawan_id" name="karyawan_id" style="width: 100%;" required>
							<option value="">-- Cari NPK / Nama Anggota --</option>
							<?php foreach ($karyawan_all as $k): 
								$active_lbl = ((int) $k->is_active === 1) ? '[Aktif]' : '[RESIGN]';
							?>
								<option value="<?= $k->id ?>" 
								        data-active="<?= (int) $k->is_active ?>"
								        data-npk="<?= htmlspecialchars($k->npk) ?>"
								        data-nama="<?= htmlspecialchars($k->nama) ?>"
								        data-bagian="<?= htmlspecialchars($k->bagian) ?>">
									<?= htmlspecialchars($k->npk.' - '.$k->nama.' ('.$k->bagian.') '.$active_lbl) ?>
								</option>
							<?php endforeach; ?>
						</select>
						<div id="info-status-karyawan" style="margin-top: 5px; font-weight: 500;"></div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="trans_jenis_simpanan">Jenis Simpanan <span class="text-danger">*</span></label>
								<select class="form-control" id="trans_jenis_simpanan" name="jenis_simpanan" required>
									<option value="pokok" <?= ($jenis_selected === 'pokok') ? 'selected' : '' ?>>Simpanan Pokok (Akun 3101)</option>
									<option value="wajib" <?= ($jenis_selected === 'wajib') ? 'selected' : '' ?>>Simpanan Wajib (Akun 3102)</option>
									<option value="sukarela" <?= ($jenis_selected === 'sukarela') ? 'selected' : '' ?>>Simpanan Sukarela (Akun 2102)</option>
								</select>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="trans_jenis_mutasi">Jenis Transaksi / Mutasi <span class="text-danger">*</span></label>
								<select class="form-control" id="trans_jenis_mutasi" name="jenis_mutasi" required>
									<option value="setor">Setor Simpanan (+)</option>
									<option value="tarik">Tarik Simpanan (-) [Khusus Resign]</option>
									<option value="bunga">Bunga Simpanan (+)</option>
									<option value="shu">Alokasi SHU (+)</option>
									<option value="koreksi">Koreksi Penyesuaian</option>
								</select>
							</div>
						</div>
					</div>

					<div id="warning-resign-block" class="alert alert-warning" style="display: none; padding: 10px; margin-bottom: 15px;">
						<i class="fa fa-lock"></i> <strong>Peringatan Kebijakan:</strong><br>
						Penarikan simpanan hanya diizinkan untuk anggota yang statusnya <strong>SUDAH RESIGN</strong>.
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="trans_nominal">Nominal (Rp) <span class="text-danger">*</span></label>
								<input type="text" class="form-control text-right rupiah-mask" id="trans_nominal" name="nominal" placeholder="Contoh: 100.000" required autocomplete="off" style="font-size: 16px; font-weight: bold; color: #1b8a3e;">
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="trans_tgl">Tanggal Transaksi <span class="text-danger">*</span></label>
								<input type="date" class="form-control" id="trans_tgl" name="tgl_transaksi" value="<?= date('Y-m-d') ?>" required>
							</div>
						</div>
					</div>

					<div class="form-group">
						<label for="trans_kas_account">Pilih Akun Kas / Bank (Posting Jurnal Otomatis)</label>
						<select class="form-control" id="trans_kas_account" name="kas_account_code">
							<option value="">-- Tanpa Jurnal Kas Otomatis --</option>
							<?php foreach ($cash_accounts as $cacc): ?>
								<option value="<?= $cacc->code ?>" <?= ($cacc->code === '1106' || $cacc->code === '1101') ? 'selected' : '' ?>>
									<?= htmlspecialchars($cacc->code.' - '.$cacc->name) ?>
								</option>
							<?php endforeach; ?>
						</select>
						<small class="text-muted"><i class="fa fa-check text-green"></i> Jika dipilih, sistem akan otomatis menjurnal Kas vs Akun Simpanan.</small>
					</div>

					<div class="form-group">
						<label for="trans_keterangan">Keterangan / Catatan</label>
						<textarea class="form-control" id="trans_keterangan" name="keterangan" rows="2" placeholder="Catatan transaksi..."></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-success" id="btn-submit-transaksi">
						<i class="fa fa-save"></i> Simpan Transaksi & Jurnal
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal 2: Upload Excel / CSV dengan Preview & Validasi -->
<div class="modal fade" id="modal-upload-preview" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header bg-info text-white">
				<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
				<h4 class="modal-title"><i class="fa fa-cloud-upload"></i> Upload & Validasi Mutasi Simpanan (Excel / CSV)</h4>
			</div>
			<div class="modal-body">
				<!-- Step 1: Upload File -->
				<div id="upload-step-1">
					<div id="drop-area-box" style="border: 2px dashed #3c8dbc; border-radius: 8px; padding: 30px; text-align: center; cursor: pointer; background: #fafafa;">
						<i class="fa fa-file-excel-o" style="font-size: 50px; color: #00a65a; margin-bottom: 12px;"></i>
						<h4>Pilih atau Drag File Excel (.xlsx, .xls) / CSV ke sini</h4>
						<p class="text-muted">Mendukung format titik-koma (;) atau koma (,) serta file Microsoft Excel</p>
						<input type="file" id="modalFileInput" accept=".xlsx,.xls,.csv" style="display: none;">
					</div>
					<div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
						<a href="<?= base_url('index.php/main/simpanan/download_template') ?>" class="btn btn-sm btn-default">
							<i class="fa fa-download"></i> Download Template CSV
						</a>
						<span class="text-muted small"><i class="fa fa-info-circle"></i> Sistem akan memeriksa validasi data dan status resign sebelum disimpan ke database.</span>
					</div>
					<div id="upload-loading-spinner" style="display: none; text-align: center; margin-top: 20px;">
						<i class="fa fa-spinner fa-spin fa-2x text-primary"></i>
						<p style="margin-top: 8px;">Membaca dan memvalidasi baris data file...</p>
					</div>
				</div>

				<!-- Step 2: Preview & Confirmation Table -->
				<div id="upload-step-2" style="display: none;">
					<div class="row">
						<div class="col-md-4">
							<div class="alert alert-info" style="margin-bottom: 10px; padding: 10px;">
								<strong>Total Baris:</strong> <span id="pv-total-rows">0</span><br>
								<strong>Total Nominal Valid:</strong> <span id="pv-total-nominal">Rp 0</span>
							</div>
						</div>
						<div class="col-md-4">
							<div class="alert alert-success" style="margin-bottom: 10px; padding: 10px;">
								<strong>Baris Valid:</strong> <span id="pv-valid-count">0</span><br>
								<small>Siap diproses dan dijurnal</small>
							</div>
						</div>
						<div class="col-md-4">
							<div class="alert alert-danger" style="margin-bottom: 10px; padding: 10px;">
								<strong>Baris Error / Ditolak:</strong> <span id="pv-error-count">0</span><br>
								<small>Akan otomatis dilewati saat import</small>
							</div>
						</div>
					</div>

					<div class="form-group" style="margin-top: 10px;">
						<label>Pilih Akun Kas / Bank untuk Penjurnalan Otomatis:</label>
						<select class="form-control" id="pv-kas-account">
							<?php foreach ($cash_accounts as $cacc): ?>
								<option value="<?= $cacc->code ?>" <?= ($cacc->code === '1106') ? 'selected' : '' ?>>
									<?= htmlspecialchars($cacc->code.' - '.$cacc->name) ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="table-responsive" style="max-height: 280px; overflow-y: auto; border: 1px solid #ddd; margin-top: 10px;">
						<table class="table table-bordered table-striped table-condensed" style="font-size: 13px;">
							<thead>
								<tr class="bg-gray">
									<th>No</th>
									<th>NPK</th>
									<th>Nama Anggota</th>
									<th>Jenis</th>
									<th>Mutasi</th>
									<th class="text-right">Nominal</th>
									<th>Status</th>
									<th>Catatan</th>
								</tr>
							</thead>
							<tbody id="pv-table-body"></tbody>
						</table>
					</div>
					<small class="text-muted">* Menampilkan sampel hingga 15 baris pertama data file.</small>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
				<button type="button" class="btn btn-default" id="btn-pv-back" style="display: none;">
					<i class="fa fa-arrow-left"></i> Ganti File
				</button>
				<button type="button" class="btn btn-success" id="btn-pv-confirm" style="display: none;">
					<i class="fa fa-check"></i> Konfirmasi & Simpan Semua
				</button>
			</div>
		</div>
	</div>
</div>

<!-- Modal 3: Generate Tagihan Simpanan Wajib Bulanan -->
<div class="modal fade" id="modal-generate-wajib" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form id="form-generate-wajib">
				<div class="modal-header bg-yellow text-white">
					<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
					<h4 class="modal-title"><i class="fa fa-bolt"></i> Generate Tagihan Simpanan Wajib Bulanan</h4>
				</div>
				<div class="modal-body">
					<div id="gen-alert-box" style="display: none;"></div>

					<p>Fitur ini akan secara massal membuat mutasi <strong>Setor Simpanan Wajib</strong> untuk seluruh anggota yang berstatus <strong>AKTIF</strong> pada periode bulan berjalan (potong gaji) yang belum menyetor simpanan wajib.</p>

					<div class="form-group">
						<label>Pilih Periode Bulan <span class="text-danger">*</span></label>
						<input type="month" class="form-control" name="periode" value="<?= date('Y-m') ?>" required>
					</div>

					<div class="form-group">
						<label>Nominal Simpanan Wajib per Anggota (Rp) <span class="text-danger">*</span></label>
						<input type="text" class="form-control rupiah-mask" name="nominal" value="50.000" required style="font-size: 16px; font-weight: bold; color: #1b8a3e;">
						<small class="text-muted">Standar koperasi Rp 50.000 per bulan per anggota.</small>
					</div>

					<div class="form-group">
						<label>Akun Sumber Penjurnalan (Debet)</label>
						<select class="form-control" name="kas_account_code">
							<option value="1103" selected>1103 - Piutang Belanja Anggota (Potong Gaji)</option>
							<option value="1106">1106 - Kas Utama</option>
							<option value="1102">1102 - Kas Bank Koperasi</option>
						</select>
						<small class="text-muted">Berpasangan dengan Akun <strong>3102 - Simpanan Wajib Anggota</strong> (Kredit).</small>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-warning" id="btn-submit-generate">
						<i class="fa fa-bolt"></i> Proses Sekarang
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
(function() {
	function initDataTable() {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
			setTimeout(initDataTable, 100);
			return;
		}
		jQuery('.data-table-simpanan').each(function() {
			if (!jQuery.fn.DataTable.isDataTable(this)) {
				jQuery(this).DataTable({
					pageLength: 25,
					lengthMenu: [10, 25, 50, 100],
					language: {
						emptyTable: 'Tidak ada data anggota',
						info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
						infoEmpty: 'Menampilkan 0 data',
						infoFiltered: '(difilter dari _MAX_ total)',
						lengthMenu: 'Tampilkan _MENU_ baris',
						search: 'Cari Anggota (Nama / NPK / Bagian):',
						paginate: { first: 'Awal', last: 'Akhir', next: 'Berikut', previous: 'Sebelum' },
						zeroRecords: 'Tidak ada data anggota yang cocok.'
					}
				});
			}
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initDataTable);
	} else {
		initDataTable();
	}
}());

// Rupiah mask helper
function formatRupiahStr(val) {
	var num = val.replace(/[^,\d]/g, '').toString(),
		split = num.split(','),
		sisa = split[0].length % 3,
		rupiah = split[0].substr(0, sisa),
		ribuan = split[0].substr(sisa).match(/\d{3}/gi);

	if (ribuan) {
		var sep = sisa ? '.' : '';
		rupiah += sep + ribuan.join('.');
	}
	return split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
}

document.querySelectorAll('.rupiah-mask').forEach(function(el) {
	el.addEventListener('keyup', function() {
		this.value = formatRupiahStr(this.value);
	});
});

// Resign check in quick form
function checkResignWithdrawalRule() {
	var selectMember = document.getElementById('trans_karyawan_id');
	var selectedOption = selectMember.options[selectMember.selectedIndex];
	var isActive = selectedOption ? parseInt(selectedOption.getAttribute('data-active') || '1', 10) : 1;
	var jenisMutasi = document.getElementById('trans_jenis_mutasi').value;
	var infoDiv = document.getElementById('info-status-karyawan');
	var warningBlock = document.getElementById('warning-resign-block');
	var btnSubmit = document.getElementById('btn-submit-transaksi');

	if (selectedOption && selectedOption.value) {
		if (isActive === 1) {
			infoDiv.innerHTML = '<span class="text-success"><i class="fa fa-check-circle"></i> Status: Anggota AKTIF</span>';
		} else {
			infoDiv.innerHTML = '<span class="text-danger"><i class="fa fa-exclamation-triangle"></i> Status: SUDAH RESIGN / KELUAR</span>';
		}
	} else {
		infoDiv.innerHTML = '';
	}

	if (jenisMutasi === 'tarik') {
		if (isActive === 1) {
			warningBlock.style.display = 'block';
			warningBlock.className = 'alert alert-danger';
			warningBlock.innerHTML = '<i class="fa fa-ban"></i> <strong>PENARIKAN DITOLAK:</strong> Anggota masih berstatus <strong>AKTIF</strong>. Penarikan simpanan hanya boleh diproses bila anggota sudah resign/keluar.';
			btnSubmit.disabled = true;
		} else {
			warningBlock.style.display = 'block';
			warningBlock.className = 'alert alert-info';
			warningBlock.innerHTML = '<i class="fa fa-info-circle"></i> Anggota berstatus <strong>RESIGN</strong>. Penutupan / penarikan saldo simpanan diizinkan.';
			btnSubmit.disabled = false;
		}
	} else {
		warningBlock.style.display = 'none';
		btnSubmit.disabled = false;
	}
}

document.getElementById('trans_karyawan_id').addEventListener('change', checkResignWithdrawalRule);
document.getElementById('trans_jenis_mutasi').addEventListener('change', checkResignWithdrawalRule);

// Quick Transaksi Button from Table
document.addEventListener('click', function(e) {
	var btn = e.target.closest('.btn-quick-transaksi');
	if (btn) {
		var memberId = btn.getAttribute('data-id');
		var jenis = btn.getAttribute('data-jenis');
		
		var selectMember = document.getElementById('trans_karyawan_id');
		selectMember.value = memberId;
		if (window.jQuery && jQuery.fn.select2) {
			jQuery('#trans_karyawan_id').trigger('change');
		}

		document.getElementById('trans_jenis_simpanan').value = jenis;
		document.getElementById('trans_nominal').value = '';
		document.getElementById('modal-alert-box').style.display = 'none';

		checkResignWithdrawalRule();
		jQuery('#modal-transaksi-simpanan').modal('show');
	}
});

// Submit Form Transaksi Langsung
document.getElementById('form-transaksi-simpanan').addEventListener('submit', function(e) {
	e.preventDefault();
	var btn = document.getElementById('btn-submit-transaksi');
	var alertBox = document.getElementById('modal-alert-box');
	
	btn.disabled = true;
	btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Menyimpan...';
	alertBox.style.display = 'none';

	var formData = new FormData(this);

	fetch('<?= base_url('index.php/main/simpanan/save_transaksi') ?>', {
		method: 'POST',
		body: formData
	})
	.then(response => response.json())
	.then(data => {
		if (data.success) {
			alertBox.className = 'alert alert-success';
			alertBox.innerHTML = '<i class="fa fa-check"></i> ' + data.message;
			alertBox.style.display = 'block';
			setTimeout(function() { location.reload(); }, 1200);
		} else {
			alertBox.className = 'alert alert-danger';
			alertBox.innerHTML = '<i class="fa fa-times-circle"></i> ' + data.message;
			alertBox.style.display = 'block';
			btn.disabled = false;
			btn.innerHTML = '<i class="fa fa-save"></i> Simpan Transaksi & Jurnal';
		}
	})
	.catch(err => {
		alertBox.className = 'alert alert-danger';
		alertBox.innerHTML = '<i class="fa fa-times-circle"></i> Terjadi kesalahan: ' + err;
		alertBox.style.display = 'block';
		btn.disabled = false;
		btn.innerHTML = '<i class="fa fa-save"></i> Simpan Transaksi & Jurnal';
	});
});

// Upload Modal & Preview Logic
var uploadToken = '';
var dropBox = document.getElementById('drop-area-box');
var fileInput = document.getElementById('modalFileInput');

dropBox.addEventListener('click', function() { fileInput.click(); });
dropBox.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor = '#00a65a'; });
dropBox.addEventListener('dragleave', function() { this.style.borderColor = '#3c8dbc'; });
dropBox.addEventListener('drop', function(e) {
	e.preventDefault();
	this.style.borderColor = '#3c8dbc';
	if (e.dataTransfer.files.length > 0) {
		fileInput.files = e.dataTransfer.files;
		processPreviewUpload(e.dataTransfer.files[0]);
	}
});
fileInput.addEventListener('change', function() {
	if (this.files.length > 0) {
		processPreviewUpload(this.files[0]);
	}
});

function processPreviewUpload(file) {
	var formData = new FormData();
	formData.append('file', file);

	document.getElementById('upload-loading-spinner').style.display = 'block';

	fetch('<?= base_url('index.php/main/simpanan/preview_upload') ?>', {
		method: 'POST',
		body: formData
	})
	.then(r => r.json())
	.then(res => {
		document.getElementById('upload-loading-spinner').style.display = 'none';
		if (res.success) {
			uploadToken = res.data.token;
			document.getElementById('pv-total-rows').innerText = res.data.total_rows;
			document.getElementById('pv-valid-count').innerText = res.data.valid_count;
			document.getElementById('pv-error-count').innerText = res.data.error_count;
			document.getElementById('pv-total-nominal').innerText = 'Rp ' + res.data.total_nominal.toLocaleString('id-ID');

			var tbody = document.getElementById('pv-table-body');
			tbody.innerHTML = '';

			res.data.preview.forEach(function(r) {
				var tr = document.createElement('tr');
				var statusBadge = r.valid ? '<span class="label label-success"><i class="fa fa-check"></i> Valid</span>' : '<span class="label label-danger"><i class="fa fa-times"></i> Error</span>';
				var errText = r.errors.length > 0 ? r.errors.join(', ') : '-';
				
				tr.innerHTML = '<td>' + r.row_num + '</td>' +
					'<td>' + r.npk + '</td>' +
					'<td>' + r.nama + '</td>' +
					'<td>' + r.jenis + '</td>' +
					'<td>' + r.mutasi + '</td>' +
					'<td class="text-right">Rp ' + r.nominal.toLocaleString('id-ID') + '</td>' +
					'<td>' + statusBadge + '</td>' +
					'<td><small class="text-danger">' + errText + '</small></td>';
				tbody.appendChild(tr);
			});

			document.getElementById('upload-step-1').style.display = 'none';
			document.getElementById('upload-step-2').style.display = 'block';
			document.getElementById('btn-pv-back').style.display = 'inline-block';
			document.getElementById('btn-pv-confirm').style.display = 'inline-block';
		} else {
			alert('Upload Gagal: ' + res.message);
		}
	})
	.catch(e => {
		document.getElementById('upload-loading-spinner').style.display = 'none';
		alert('Error komunikasi server: ' + e);
	});
}

document.getElementById('btn-pv-back').addEventListener('click', function() {
	document.getElementById('upload-step-1').style.display = 'block';
	document.getElementById('upload-step-2').style.display = 'none';
	this.style.display = 'none';
	document.getElementById('btn-pv-confirm').style.display = 'none';
	fileInput.value = '';
});

document.getElementById('btn-pv-confirm').addEventListener('click', function() {
	var btn = this;
	btn.disabled = true;
	btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses Import & Jurnal...';

	var kasAcc = document.getElementById('pv-kas-account').value;
	var fd = new FormData();
	fd.append('token', uploadToken);
	fd.append('kas_account_code', kasAcc);

	fetch('<?= base_url('index.php/main/simpanan/execute_upload') ?>', {
		method: 'POST',
		body: fd
	})
	.then(r => r.json())
	.then(res => {
		if (res.success) {
			alert(res.message);
			location.reload();
		} else {
			alert('Gagal: ' + res.message);
			btn.disabled = false;
			btn.innerHTML = '<i class="fa fa-check"></i> Konfirmasi & Simpan Semua';
		}
	})
	.catch(e => {
		alert('Error: ' + e);
		btn.disabled = false;
		btn.innerHTML = '<i class="fa fa-check"></i> Konfirmasi & Simpan Semua';
	});
});

// Submit Generate Simpanan Wajib
document.getElementById('form-generate-wajib').addEventListener('submit', function(e) {
	e.preventDefault();
	var btn = document.getElementById('btn-submit-generate');
	var alertBox = document.getElementById('gen-alert-box');

	if (!confirm('Apakah Anda yakin ingin men-generate tagihan simpanan wajib untuk seluruh anggota aktif pada periode ini?')) {
		return;
	}

	btn.disabled = true;
	btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Memproses Batch...';
	alertBox.style.display = 'none';

	var formData = new FormData(this);

	fetch('<?= base_url('index.php/main/simpanan/generate_tagihan_wajib') ?>', {
		method: 'POST',
		body: formData
	})
	.then(r => r.json())
	.then(res => {
		if (res.success) {
			alertBox.className = 'alert alert-success';
			alertBox.innerHTML = '<i class="fa fa-check"></i> ' + res.message;
			alertBox.style.display = 'block';
			setTimeout(function() { location.reload(); }, 1500);
		} else {
			alertBox.className = 'alert alert-danger';
			alertBox.innerHTML = '<i class="fa fa-times-circle"></i> ' + res.message;
			alertBox.style.display = 'block';
			btn.disabled = false;
			btn.innerHTML = '<i class="fa fa-bolt"></i> Proses Sekarang';
		}
	})
	.catch(e => {
		alertBox.className = 'alert alert-danger';
		alertBox.innerHTML = '<i class="fa fa-times-circle"></i> Error: ' + e;
		alertBox.style.display = 'block';
		btn.disabled = false;
		btn.innerHTML = '<i class="fa fa-bolt"></i> Proses Sekarang';
	});
});
</script>
