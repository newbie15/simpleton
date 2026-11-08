<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$format_rupiah = function ($amount) {
	return 'Rp '.number_format((float) $amount, 0, ',', '.');
};
$status_labels = array(
	'pending' => array('Menunggu persetujuan', 'warning'),
	'posted' => array('Disetujui', 'success'),
	'rejected' => array('Perlu diperbaiki', 'danger')
);

// Hitung total limit plafon untuk perbandingan
$total_limit = 0;
foreach ($funds as $f) {
	$total_limit += (float) $f->imprest_limit;
}
$is_low_balance = ($total_limit > 0 && ($total_balance / $total_limit) <= 0.25);
?>
<style>
	.cashier-page { max-width: 1080px; margin: 0 auto; }
	.cashier-page .cashier-hero { border: 0; border-radius: 8px; color: #fff; background: linear-gradient(120deg, #167d73, #20a486); }
	.cashier-page .cashier-hero .box-body { padding: 22px 26px; }
	.cashier-page .cashier-hero h2 { margin: 0 0 6px; font-size: 22px; font-weight: 600; }
	.cashier-page .cashier-hero p { margin: 0; opacity: .9; }
	.cashier-page .cashier-balance { margin-top: 10px; font-size: 32px; font-weight: 700; }
	.cashier-page .cashier-card { border-radius: 7px; }
	.cashier-page .cashier-card .box-body { padding: 22px; }
	.cashier-page .cashier-field { margin-bottom: 20px; }
	.cashier-page .cashier-field label { font-size: 15px; }
	.cashier-page .cashier-field .form-control { min-height: 46px; border-radius: 5px; font-size: 16px; }
	.cashier-page .cashier-field textarea.form-control { min-height: 84px; }
	.cashier-page .cashier-submit { min-height: 50px; padding: 10px 24px; font-size: 17px; font-weight: 600; }
	.cashier-page .cashier-proof { padding: 14px; border: 1px dashed #9dbbb4; border-radius: 5px; background: #f7fbfa; }
	.cashier-page .cashier-proof input { height: auto; padding: 7px 0; }
	.cashier-page .cashier-help { color: #667780; }
	.cashier-page .cashier-empty { padding: 34px 20px; text-align: center; }
	.cashier-page .cashier-empty .fa { margin-bottom: 12px; color: #aab8be; font-size: 38px; }
	.cashier-page .table > tbody > tr > td { vertical-align: middle; }
	.cashier-count-input { max-width: 90px; }
	.cashier-expected-balance { font-size: 30px; font-weight: 700; color: #008d4c; }
	.cashier-count-total { font-size: 24px; font-weight: 700; }
	.rupiah-display { font-size: 20px; font-weight: bold; color: #00a65a; }
	.notice-banner { background: #fff3cd; color: #856404; border-left: 5px solid #ffeeba; padding: 12px; border-radius: 4px; margin-bottom: 15px; }
	@media (max-width: 767px) {
		.cashier-page .cashier-hero .box-body, .cashier-page .cashier-card .box-body { padding: 17px; }
		.cashier-page .cashier-balance { font-size: 26px; }
		.cashier-page .cashier-submit { width: 100%; }
	}
</style>

<div class="cashier-page">
	<?php if ($notice) { ?><div class="alert alert-success" role="status"><i class="fa fa-check-circle"></i> <?php echo html_escape($notice); ?></div><?php } ?>
	<?php if ($error) { ?><div class="alert alert-danger" role="alert"><i class="fa fa-exclamation-circle"></i> <?php echo html_escape($error); ?></div><?php } ?>

	<div class="box box-primary cashier-card">
		<div class="box-header with-border">
			<h3 class="box-title"><i class="fa fa-calculator"></i> Preview & Hitung Uang Laci Kas POS</h3>
			<div class="box-tools pull-right">
				<span class="label label-primary">Kasir: <?php echo html_escape(isset($cashier_name) ? $cashier_name : 'Kasir POS'); ?></span>
				<span class="label label-default"><?php echo html_escape(date('d/m/Y', strtotime($cashier_cash['date']))); ?></span>
			</div>
		</div>
		<div class="box-body">
			<?php if ($cashier_closing) { ?>
				<div class="alert alert-info"><i class="fa fa-lock"></i> Tutup kas resmi hari ini sudah disahkan pukul <?php echo html_escape(date('H:i', strtotime($cashier_closing->closed_at))); ?>. Preview berikut menampilkan saldo sistem dan transaksi hari ini.</div>
			<?php } ?>
			<p class="cashier-help">Gunakan kalkulator ini untuk mengecek kesesuaian uang fisik di laci kasir POS saat ini. Perhitungan ini hanya preview dan tidak menyimpan hitung fisik atau menutup kas.</p>

			<div class="well well-sm" style="background: #fdfdfd; border-left: 4px solid #3c8dbc; margin-bottom: 15px;">
				<div class="row">
					<div class="col-sm-6">
						<label style="margin-bottom: 4px;"><i class="fa fa-money"></i> Modal Awal Laci Kasir (Float / Uang Kembalian):</label>
						<div class="input-group" style="max-width: 260px;">
							<span class="input-group-addon">Rp</span>
							<input type="text" class="form-control rupiah-display input-sm" id="cashier-float" value="0" placeholder="0" autocomplete="off" style="font-weight: bold; color: #3c8dbc;">
						</div>
						<small class="text-muted">Masukkan modal awal kasir di pagi hari (jika ada).</small>
					</div>
					<div class="col-sm-6">
						<label style="margin-bottom: 4px;"><i class="fa fa-crosshairs"></i> Basis Pembanding Target Kas:</label>
						<div>
							<label class="radio-inline" style="font-weight: 600;">
								<input type="radio" name="target_mode" id="mode-laci" value="laci" checked> Kas Laci Hari Ini (Rekomendasi)
							</label>
							<label class="radio-inline" style="font-weight: normal; color: #777;">
								<input type="radio" name="target_mode" id="mode-ledger" value="ledger"> Akun 1101 (Buku Besar)
							</label>
						</div>
						<small class="text-muted" id="target-mode-desc">Menghitung: Modal Awal + Penjualan Tunai Bersih Hari Ini.</small>
					</div>
				</div>
			</div>

			<div class="row text-center" style="margin-bottom: 15px;">
				<div class="col-xs-6 col-sm-3" style="border-right: 1px solid #f0f0f0;">
					<span style="font-size: 12px; color: #777;">MODAL AWAL LACI</span>
					<h4 style="margin: 4px 0 0; color: #3c8dbc; font-weight: bold;" id="display-float"><?php echo $format_rupiah(0); ?></h4>
				</div>
				<div class="col-xs-6 col-sm-3" style="border-right: 1px solid #f0f0f0;">
					<span style="font-size: 12px; color: #777;">KAS MASUK HARI INI</span>
					<h4 style="margin: 4px 0 0; color: #00a65a; font-weight: bold;"><?php echo $format_rupiah($cashier_cash['debit_total']); ?></h4>
				</div>
				<div class="col-xs-6 col-sm-3" style="border-right: 1px solid #f0f0f0;">
					<span style="font-size: 12px; color: #777;">KAS KELUAR / RETUR</span>
					<h4 style="margin: 4px 0 0; color: #dd4b39; font-weight: bold;"><?php echo $format_rupiah($cashier_cash['credit_total']); ?></h4>
				</div>
				<div class="col-xs-6 col-sm-3">
					<span style="font-size: 12px; color: #777;" id="label-expected-target">TARGET UANG DI LACI</span>
					<h3 style="margin: 2px 0 0; font-weight: bold;" class="cashier-expected-balance" id="cashier-target-display"
						data-today-net="<?php echo (float) $cashier_cash['today_net']; ?>"
						data-ledger="<?php echo (float) $cashier_cash['expected_balance']; ?>">
						<?php echo $format_rupiah($cashier_cash['today_net']); ?>
					</h3>
				</div>
			</div>

			<div class="row">
				<div class="col-md-7">
					<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
						<h4 style="margin: 0;"><i class="fa fa-list-ol"></i> Hitung Uang Fisik Laci</h4>
						<button type="button" class="btn btn-default btn-xs" id="btn-reset-cash-count">
							<i class="fa fa-refresh"></i> Reset Hitungan
						</button>
					</div>
					<div class="table-responsive">
						<table class="table table-condensed table-bordered">
							<thead>
								<tr class="bg-gray-light">
									<th>Pecahan</th>
									<th class="text-center" style="width: 120px;">Jumlah Lembar / Koin</th>
									<th class="text-right" style="width: 160px;">Subtotal</th>
								</tr>
							</thead>
							<tbody>
							<?php foreach (array(100000, 50000, 20000, 10000, 5000, 2000, 1000, 500, 200, 100) as $denomination) { ?>
								<tr>
									<td><strong><?php echo $format_rupiah($denomination); ?></strong></td>
									<td class="text-center">
										<input type="number" min="0" step="1" inputmode="numeric" class="form-control input-sm cashier-count-input" data-denomination="<?php echo $denomination; ?>" aria-label="Jumlah pecahan <?php echo $denomination; ?>" value="0" style="text-align: center; font-weight: bold;">
									</td>
									<td class="text-right cashier-denomination-subtotal"><?php echo $format_rupiah(0); ?></td>
								</tr>
							<?php } ?>
							</tbody>
							<tfoot>
								<tr class="bg-gray-light" style="font-size: 14px;">
									<th colspan="2">TOTAL UANG FISIK TERHITUNG</th>
									<th class="text-right cashier-count-total" id="cashier-count-total" style="color: #00a65a; font-weight: bold;"><?php echo $format_rupiah(0); ?></th>
								</tr>
							</tfoot>
						</table>
					</div>
				</div>
				<div class="col-md-5">
					<div class="box box-solid box-default" style="border: 1px solid #d2d6de; background: #fafafa;">
						<div class="box-body text-center" style="padding: 20px 15px;">
							<span style="font-size: 13px; color: #777; font-weight: 600;">SELISIH KAS (FISIK − TARGET)</span>
							<div class="cashier-count-total" id="cashier-count-variance" data-expected="<?php echo (float) $cashier_cash['today_net']; ?>" style="font-size: 28px; margin: 8px 0; font-weight: bold;">
								Rp 0
							</div>
							<div id="cashier-count-status" class="help-block" style="font-size: 13px; margin-bottom: 12px;">
								Isi jumlah lembar / koin pada tabel di sebelah kiri.
							</div>

							<form method="post" action="<?php echo site_url('main/petty_cash/save_cash_count'); ?>" id="form-save-cash-count" style="margin-bottom: 10px;">
								<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
								<input type="hidden" name="return_to" value="main/petty_cash/cashier">
								<input type="hidden" name="initial_float" id="save-initial-float" value="0">
								<input type="hidden" name="system_target" id="save-system-target" value="<?php echo (float) $cashier_cash['today_net']; ?>">
								<input type="hidden" name="counted_total" id="save-counted-total" value="0">
								<input type="hidden" name="variance" id="save-variance" value="0">
								<input type="hidden" name="target_mode" id="save-target-mode" value="laci">
								<div id="save-denominations-container"></div>
								<div class="form-group text-left" style="margin-bottom: 8px;">
									<label for="save-cash-notes" style="font-size: 12px; font-weight: 600; color: #555;">Catatan Opname (Opsional):</label>
									<textarea class="form-control input-sm" id="save-cash-notes" name="notes" rows="2" placeholder="Contoh: Kas fisik cocok, serah terima shift kasir"></textarea>
								</div>
								<button type="submit" class="btn btn-success btn-block" id="btn-submit-save-cash">
									<i class="fa fa-save"></i> <strong>Simpan Hasil Opname Kas</strong>
								</button>
							</form>

							<button type="button" class="btn btn-default btn-block" id="btn-print-cash-count">
								<i class="fa fa-print"></i> Cetak Slip Opname Kasir
							</button>
							<small class="text-muted" style="display: block; margin-top: 6px;">Format struk termal / tanda terima rekap kasir</small>
						</div>
					</div>

					<div class="panel panel-default" style="margin-top: 10px;">
						<div class="panel-heading" style="padding: 8px 12px; font-size: 12px;">
							<i class="fa fa-info-circle text-blue"></i> <strong>Catatan Akuntansi:</strong>
						</div>
						<div class="panel-body" style="padding: 10px 12px; font-size: 12px; color: #666;">
							Saldo akun 1101 (Buku Besar): <strong><?php echo $format_rupiah($cashier_cash['expected_balance']); ?></strong>.
							Jika toko belum rutin menjurnal setoran kasir ke kas utama (1106) atau bank (1102), gunakan <em>Kas Laci Hari Ini</em> sebagai acuan uang fisik laci.
						</div>
					</div>
				</div>
			</div>

			<?php if (!empty($today_cash_counts)) { ?>
			<div style="margin-top: 25px;">
				<h4 style="margin-bottom: 8px;"><i class="fa fa-check-square-o text-green"></i> Riwayat Opname Kas Laci Hari Ini <small>(<?php echo count($today_cash_counts); ?> kali hitung tersimpan)</small></h4>
				<div class="table-responsive">
					<table class="table table-bordered table-striped table-condensed" style="font-size: 13px;">
						<thead>
							<tr class="bg-gray-light">
								<th>Waktu Simpan</th>
								<th>Kasir</th>
								<th class="text-right">Modal Awal</th>
								<th class="text-right">Target Laci</th>
								<th class="text-right">Fisik Terhitung</th>
								<th class="text-right">Selisih</th>
								<th>Catatan</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($today_cash_counts as $cc) {
								$var_amount = (float) $cc->variance;
								$var_class = abs($var_amount) < 1 ? 'text-green' : ($var_amount < 0 ? 'text-red' : 'text-yellow');
								$var_text = abs($var_amount) < 1 ? 'PAS' : ($var_amount < 0 ? '-Rp '.number_format(abs($var_amount), 0, ',', '.') : '+Rp '.number_format($var_amount, 0, ',', '.'));
							?>
							<tr>
								<td><?php echo html_escape(date('H:i', strtotime($cc->count_time))); ?> WIB</td>
								<td><strong><?php echo html_escape($cc->cashier_name); ?></strong></td>
								<td class="text-right"><?php echo $format_rupiah($cc->initial_float); ?></td>
								<td class="text-right"><?php echo $format_rupiah($cc->system_target); ?></td>
								<td class="text-right" style="font-weight: bold;"><?php echo $format_rupiah($cc->counted_total); ?></td>
								<td class="text-right <?php echo $var_class; ?>" style="font-weight: bold;"><?php echo $var_text; ?></td>
								<td><?php echo html_escape($cc->notes ? $cc->notes : '-'); ?></td>
							</tr>
							<?php } ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php } ?>

			<h4 style="margin-top: 20px;"><i class="fa fa-history"></i> Pergerakan kas yang tercatat hari ini <small>(<?php echo count($cashier_cash['movements']); ?> baris jurnal)</small></h4>
			<div class="table-responsive">
				<table class="table table-bordered table-striped table-condensed">
					<thead>
						<tr class="bg-gray-light">
							<th>Jurnal / Referensi</th>
							<th>Uraian</th>
							<th class="text-right">Masuk</th>
							<th class="text-right">Keluar</th>
							<th class="text-right">Saldo berjalan</th>
						</tr>
					</thead>
					<tbody>
					<?php if (empty($cashier_cash['movements'])) { ?><tr><td colspan="5" class="text-center text-muted">Belum ada pergerakan kas yang diposting hari ini.</td></tr><?php } ?>
					<?php foreach ($cashier_cash['movements'] as $movement) { ?>
						<tr>
							<td><?php echo html_escape($movement->journal_number); ?><br><small><?php echo html_escape($movement->reference_type.':'.$movement->reference_id); ?></small></td>
							<td><?php echo html_escape($movement->memo ? $movement->memo : $movement->description); ?></td>
							<td class="text-right"><?php echo $movement->debit > 0 ? $format_rupiah($movement->debit) : '—'; ?></td>
							<td class="text-right"><?php echo $movement->credit > 0 ? $format_rupiah($movement->credit) : '—'; ?></td>
							<td class="text-right"><?php echo $format_rupiah($movement->running_balance); ?></td>
						</tr>
					<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Peringatan Kas Kecil Terpisah dari Laci Kasir POS -->
	<div class="notice-banner">
		<i class="fa fa-info-circle"></i> <strong>Pemberitahuan Kasir:</strong> Dana Kas Kecil ini khusus digunakan untuk keperluan operasional toko (misal: beli galon, ATK, kebersihan). <strong>Jangan mencampuradukkan uang kas kecil dengan uang fisik laci kasir penjualan (POS).</strong>
	</div>

	<!-- RINGKASAN REKAP SPENDING / PENGELUARAN HARI INI -->
	<div class="box box-solid box-default" style="border: 1px solid #e1e4e8; margin-bottom: 20px;">
		<div class="box-header with-border" style="background: #fafafa;">
			<h3 class="box-title" style="font-size: 16px;"><i class="fa fa-shopping-cart text-red"></i> <strong>Rekap Spending Hari Ini (<?= date('d/m/Y', strtotime($today_date)) ?>)</strong></h3>
			<div class="box-tools pull-right">
				<span class="label label-danger" style="font-size: 13px; padding: 4px 8px;">
					Total Keluar: <?= $format_rupiah($today_spending_total) ?>
				</span>
			</div>
		</div>
		<div class="box-body" style="padding: 15px;">
			<div class="row" style="margin-bottom: 12px;">
				<div class="col-sm-4">
					<div style="background: #fdf2f2; border-left: 4px solid #dd4b39; padding: 10px; border-radius: 3px;">
						<span style="font-size: 12px; color: #777;">TOTAL SPENDING HARI INI</span>
						<h4 style="margin: 3px 0 0; color: #dd4b39; font-weight: bold;"><?= $format_rupiah($today_spending_total) ?></h4>
						<small class="text-muted"><?= count($today_spending) ?> transaksi pengeluaran</small>
					</div>
				</div>
				<div class="col-sm-4">
					<div style="background: #fffbf0; border-left: 4px solid #f39c12; padding: 10px; border-radius: 3px;">
						<span style="font-size: 12px; color: #777;">MENUNGGU APPROVAL (PENDING)</span>
						<h4 style="margin: 3px 0 0; color: #f39c12; font-weight: bold;"><?= $format_rupiah($today_spending_pending) ?></h4>
						<small class="text-muted">Belum disetujui pengurus</small>
					</div>
				</div>
				<div class="col-sm-4">
					<div style="background: #f0fbf4; border-left: 4px solid #00a65a; padding: 10px; border-radius: 3px;">
						<span style="font-size: 12px; color: #777;">DISETUJUI (POSTED KE BUKU)</span>
						<h4 style="margin: 3px 0 0; color: #00a65a; font-weight: bold;"><?= $format_rupiah($today_spending_posted) ?></h4>
						<small class="text-muted">Sudah mengurangi saldo buku</small>
					</div>
				</div>
			</div>

			<!-- Rincian Item Spending Hari Ini -->
			<div class="table-responsive">
				<table class="table table-bordered table-striped table-condensed" style="font-size: 13px; margin-bottom: 0;">
					<thead>
						<tr class="bg-gray-light">
							<th style="width: 35px;">#</th>
							<th style="width: 140px;">No. Transaksi</th>
							<th>Keperluan / Belanja Apa</th>
							<th>Kategori Beban</th>
							<th>Penerima / Vendor</th>
							<th style="text-align: right; width: 130px;">Jumlah Keluar</th>
							<th style="text-align: center; width: 100px;">Status</th>
							<th style="text-align: center; width: 70px;">Slip</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($today_spending)): ?>
							<?php $no = 1; foreach ($today_spending as $sp): 
								$st = isset($status_labels[$sp->status]) ? $status_labels[$sp->status] : array($sp->status, 'default');
							?>
								<tr>
									<td><?= $no++ ?></td>
									<td><code><?= htmlspecialchars($sp->transaction_number) ?></code></td>
									<td><strong><?= htmlspecialchars($sp->description) ?></strong></td>
									<td><?= htmlspecialchars($sp->expense_name) ?></td>
									<td><?= htmlspecialchars($sp->counterparty ? $sp->counterparty : '-') ?></td>
									<td style="text-align: right; font-weight: bold; color: #dd4b39;"><?= $format_rupiah($sp->amount) ?></td>
									<td style="text-align: center;">
										<span class="label label-<?= $st[1] ?>"><?= $st[0] ?></span>
									</td>
									<td style="text-align: center;">
										<a href="<?= site_url('main/petty_cash/print_voucher/'.$sp->id) ?>" target="_blank" class="btn btn-xs btn-default" title="Cetak Slip">
											<i class="fa fa-print"></i>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr>
								<td colspan="8" class="text-center text-muted" style="padding: 15px;">
									<i class="fa fa-check-circle text-green"></i> Belum ada pengeluaran kas kecil yang dicatat untuk hari ini.
								</td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<div class="box cashier-hero">
		<div class="box-body">
			<div class="row">
				<div class="col-sm-8">
					<h2><i class="fa fa-money"></i> Saldo Kas Kecil Kasir</h2>
					<p>Total saldo kas kecil aktif yang dapat Anda gunakan saat ini</p>
					<div class="cashier-balance"><?php echo $format_rupiah($total_balance); ?></div>
					
					<?php if ($total_limit > 0): ?>
						<div style="margin-top: 8px; max-width: 380px;">
							<div class="progress" style="height: 10px; margin-bottom: 5px; background: rgba(255,255,255,0.3);">
								<div class="progress-bar <?= ($is_low_balance ? 'progress-bar-danger' : 'progress-bar-success') ?>" 
								     style="width: <?= min(100, round(($total_balance / $total_limit) * 100)) ?>%;"></div>
							</div>
							<small style="opacity: 0.9;">Plafon Dana Tetap: <?= $format_rupiah($total_limit) ?> (Tersedia <?= round(($total_balance / $total_limit) * 100) ?>%)</small>
						</div>
					<?php endif; ?>

					<?php if ($has_balance_issue) { ?>
						<p class="text-warning"><i class="fa fa-warning"></i> Ada saldo yang belum dapat ditampilkan. Hubungi pengurus sebelum mencatat transaksi.</p>
					<?php } ?>
				</div>
				<div class="col-sm-4 text-right hidden-xs">
					<i class="fa fa-credit-card" style="font-size:58px;opacity:.22;margin-top:12px;"></i>
					<?php if (!empty($funds)): ?>
						<div style="margin-top: 15px;">
							<button type="button" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#modal-request-topup">
								<i class="fa fa-refresh"></i> Ajukan Pengisian Kas Kecil
							</button>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>

	<?php if (empty($funds)) { ?>
		<div class="box box-warning cashier-card">
			<div class="box-body cashier-empty">
				<i class="fa fa-inbox"></i>
				<h3>Dana kas kecil belum tersedia</h3>
				<p class="cashier-help">Minta pengurus mengaktifkan dana kas kecil dan menetapkan pemegang dana untuk akun Anda.</p>
			</div>
		</div>
	<?php } else { ?>
		<div class="row">
			<div class="col-md-7">
				<div class="box box-success cashier-card">
					<div class="box-header with-border">
						<h3 class="box-title"><i class="fa fa-plus-circle"></i> Catat Pengeluaran Kasir</h3>
					</div>
					<div class="box-body">
						<p class="cashier-help">Isi beberapa informasi berikut. Transaksi akan dikirim ke pengurus untuk diperiksa dan disetujui.</p>
						<form method="post" enctype="multipart/form-data" action="<?php echo site_url('main/petty_cash/submit_transaction'); ?>" id="cashier-petty-form">
							<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
							<input type="hidden" name="return_to" value="main/petty_cash/cashier">
							<input type="hidden" name="transaction_type" value="expense">
							<input type="hidden" name="transaction_date" value="<?php echo date('Y-m-d'); ?>">
							<input type="hidden" name="evidence_reference" value="Bukti struk terlampir">

							<div class="form-group cashier-field">
								<label for="cashier-fund">Dana Kas Kecil</label>
								<select class="form-control" id="cashier-fund" name="fund_id" required>
									<?php foreach ($funds as $fund) { ?>
										<option value="<?php echo (int) $fund->id; ?>" data-balance="<?php echo (float) $fund->balance; ?>" data-limit="<?php echo (float) $fund->imprest_limit; ?>">
											<?php echo html_escape($fund->name); ?>
											<?php if ($fund->balance !== NULL) { ?> — Saldo <?php echo $format_rupiah($fund->balance); ?><?php } ?>
										</option>
									<?php } ?>
								</select>
							</div>

							<div class="form-group cashier-field">
								<label for="cashier-amount">Jumlah Pengeluaran (Rp) <span class="text-danger">*</span></label>
								<input class="form-control rupiah-display" type="text" id="cashier-amount" name="amount"
									placeholder="Contoh: 50.000" autocomplete="off" required>
								<div id="amount-warning" style="display: none; margin-top: 5px;" class="text-warning small">
									<i class="fa fa-exclamation-triangle"></i> <strong>Peringatan Nominal Besar:</strong> Jumlah di atas Rp 500.000. Pastikan ini adalah belanja kas kecil yang sah.
								</div>
							</div>

							<div class="form-group cashier-field">
								<label for="cashier-category">Kategori Pengeluaran <span class="text-danger">*</span></label>
								<select class="form-control" id="cashier-category" name="expense_account_id" required>
									<option value="">-- Pilih Kategori Pengeluaran --</option>
									<?php foreach ($expense_accounts as $account) { ?>
										<option value="<?php echo (int) $account->id; ?>"><?php echo html_escape($account->cashier_label); ?></option>
									<?php } ?>
								</select>
							</div>

							<div class="form-group cashier-field">
								<label for="cashier-counterparty">Dibayarkan Kepada (Penerima Uang)</label>
								<input class="form-control" type="text" id="cashier-counterparty" name="counterparty"
									maxlength="128" placeholder="Contoh: Toko ATK Sejahtera, Depot Air Galon, dsb.">
							</div>

							<div class="form-group cashier-field">
								<label for="cashier-description">Keperluan / Keterangan <span class="text-danger">*</span></label>
								<textarea class="form-control" id="cashier-description" name="description"
									maxlength="500" placeholder="Contoh: Beli 2 galon air minum & plastik sampah kasir" required></textarea>
							</div>

							<div class="form-group cashier-field">
								<label for="cashier-proof">Foto Struk / Nota Bukti Fisik <span class="text-danger">*</span></label>
								<div class="cashier-proof">
									<input type="file" id="cashier-proof" name="proof_file"
										accept=".pdf,.jpg,.jpeg,.png" capture="environment" required>
									<p class="help-block" id="cashier-proof-name">Ambil foto struk langsung dengan kamera HP atau unggah file JPG/PNG/PDF (Maks 5 MB).</p>
								</div>
							</div>

							<button class="btn btn-success cashier-submit" type="submit" id="cashier-submit">
								<i class="fa fa-paper-plane"></i> Kirim untuk Diperiksa Pengurus
							</button>
						</form>
					</div>
				</div>
			</div>

			<div class="col-md-5">
				<div class="box box-info cashier-card">
					<div class="box-header with-border">
						<h3 class="box-title"><i class="fa fa-info-circle"></i> Petunjuk Kasir</h3>
					</div>
					<div class="box-body">
						<ol style="padding-left:18px;line-height:1.8;">
							<li>Ketik nominal pengeluaran (format ribuan otomatis).</li>
							<li>Pilih kategori dan tuliskan nama toko/penerima uang.</li>
							<li>Foto struk belanja asli dengan kamera HP/tablet kasir.</li>
							<li>Setelah kirim, cetak slip kas kecil untuk tanda tangan penerima uang (jika diperlukan).</li>
						</ol>
						<button type="button" class="btn btn-warning btn-block btn-sm" data-toggle="modal" data-target="#modal-request-topup">
							<i class="fa fa-refresh"></i> Butuh Tambahan Dana? Ajukan Top Up
						</button>
					</div>
				</div>

				<div class="box box-default cashier-card">
					<div class="box-header with-border">
						<h3 class="box-title"><i class="fa fa-history"></i> Transaksi Terakhir</h3>
					</div>
					<div class="box-body table-responsive">
						<?php if (empty($transactions)) { ?>
							<p class="text-muted text-center" style="padding: 20px;">Belum ada transaksi yang Anda catat.</p>
						<?php } else { ?>
							<table class="table table-hover">
								<tbody>
								<?php foreach ($transactions as $transaction) { ?>
									<?php $status = isset($status_labels[$transaction->status]) ? $status_labels[$transaction->status] : array($transaction->status, 'default'); ?>
									<tr>
										<td>
											<strong><?php echo html_escape($transaction->description); ?></strong><br>
											<small class="text-muted"><?php echo html_escape($transaction->transaction_date.' · '.$transaction->fund_name); ?></small>
											<?php if ($transaction->status === 'rejected' && $transaction->rejection_reason) { ?>
												<br><small class="text-danger"><i class="fa fa-times"></i> <?php echo html_escape($transaction->rejection_reason); ?></small>
											<?php } ?>
											<div style="margin-top: 4px;">
												<a href="<?php echo site_url('main/petty_cash/print_voucher/'.$transaction->id); ?>" target="_blank" class="btn btn-xs btn-default" title="Cetak Voucher Slip Kas Kecil">
													<i class="fa fa-print"></i> Cetak Slip
												</a>
											</div>
										</td>
										<td class="text-right">
											<strong style="color: #dc3545;"><?php echo $format_rupiah($transaction->amount); ?></strong><br>
											<span class="label label-<?php echo html_escape($status[1]); ?>"><?php echo html_escape($status[0]); ?></span>
										</td>
									</tr>
								<?php } ?>
								</tbody>
							</table>
						<?php } ?>
					</div>
				</div>
			</div>
		</div>
	<?php } ?>
</div>

<!-- Modal Ajukan Pengisian Kembali Kas Kecil (Reimbursement / Top Up) -->
<div class="modal fade" id="modal-request-topup" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-sm" role="document">
		<div class="modal-content">
			<form method="post" action="<?php echo site_url('main/petty_cash/request_topup'); ?>">
				<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
				<input type="hidden" name="return_to" value="main/petty_cash/cashier">
				<div class="modal-header bg-yellow text-white">
					<button type="button" class="close text-white" data-dismiss="modal">&times;</button>
					<h4 class="modal-title"><i class="fa fa-refresh"></i> Pengisian Kas Kecil</h4>
				</div>
				<div class="modal-body">
					<p>Ajukan permintaan pengisian kembali saldo kas kecil ke pengurus koperasi.</p>
					
					<div class="form-group">
						<label>Pilih Dana Kas Kecil</label>
						<select class="form-control" name="fund_id" required>
							<?php foreach ($funds as $fund) { ?>
								<option value="<?php echo (int) $fund->id; ?>">
									<?php echo html_escape($fund->name); ?>
								</option>
							<?php } ?>
						</select>
					</div>

					<div class="form-group">
						<label>Catatan Tambahan (Opsional)</label>
						<textarea class="form-control" name="notes" rows="2" placeholder="Contoh: Saldo sisa Rp 20.000, perlu dana untuk operasional akhir pekan"></textarea>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
					<button type="submit" class="btn btn-warning">
						<i class="fa fa-paper-plane"></i> Ajukan Sekarang
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
(function () {
	var countInputs = document.querySelectorAll('.cashier-count-input');
	var countTotal = document.getElementById('cashier-count-total');
	var varianceDisplay = document.getElementById('cashier-count-variance');
	var countStatus = document.getElementById('cashier-count-status');
	var floatInput = document.getElementById('cashier-float');
	var displayFloat = document.getElementById('display-float');
	var targetDisplay = document.getElementById('cashier-target-display');
	var labelExpected = document.getElementById('label-expected-target');
	var targetModeDesc = document.getElementById('target-mode-desc');
	var radioLaci = document.getElementById('mode-laci');
	var radioLedger = document.getElementById('mode-ledger');
	var btnReset = document.getElementById('btn-reset-cash-count');
	var btnPrint = document.getElementById('btn-print-cash-count');

	var formatCountRupiah = function (amount) {
		var isNeg = amount < 0;
		var abs = Math.abs(Math.round(amount));
		return (isNeg ? '-Rp ' : 'Rp ') + abs.toLocaleString('id-ID');
	};

	var parseMoneyVal = function (val) {
		if (!val) return 0;
		return parseFloat(String(val).replace(/\./g, '').replace(/,/g, '.')) || 0;
	};

	var getTargetAmount = function () {
		if (radioLedger && radioLedger.checked) {
			return parseFloat(targetDisplay.getAttribute('data-ledger')) || 0;
		}
		var floatVal = parseMoneyVal(floatInput ? floatInput.value : 0);
		var todayNet = parseFloat(targetDisplay.getAttribute('data-today-net')) || 0;
		return floatVal + todayNet;
	};

	var updateCashCount = function () {
		var isLedger = radioLedger && radioLedger.checked;
		var floatVal = parseMoneyVal(floatInput ? floatInput.value : 0);
		var targetAmount = getTargetAmount();

		if (displayFloat) {
			displayFloat.textContent = formatCountRupiah(floatVal);
		}
		if (targetDisplay) {
			targetDisplay.textContent = formatCountRupiah(targetAmount);
		}
		if (labelExpected) {
			labelExpected.textContent = isLedger ? 'TARGET BUKU BESAR (1101)' : 'TARGET UANG DI LACI';
		}
		if (targetModeDesc) {
			targetModeDesc.textContent = isLedger
				? 'Saldo buku besar akun 1101 akumulatif sejak awal sistem.'
				: 'Menghitung: Modal Awal + Penjualan Tunai Bersih Hari Ini.';
		}

		var total = 0;
		Array.prototype.forEach.call(countInputs, function (input) {
			var count = parseInt(input.value, 10);
			if (isNaN(count) || count < 0) {
				count = 0;
			}
			var subtotal = count * parseInt(input.getAttribute('data-denomination'), 10);
			total += subtotal;
			var row = input.closest('tr');
			if (row) {
				var subEl = row.querySelector('.cashier-denomination-subtotal');
				if (subEl) subEl.textContent = formatCountRupiah(subtotal);
			}
		});

		if (countTotal) {
			countTotal.textContent = formatCountRupiah(total);
		}
		if (varianceDisplay) {
			var variance = total - targetAmount;
			varianceDisplay.textContent = formatCountRupiah(variance);
			if (Math.abs(variance) < 1) {
				varianceDisplay.className = 'cashier-count-total text-green';
				varianceDisplay.textContent = 'Rp 0 (PAS)';
				if (countStatus) countStatus.innerHTML = '<span class="text-green"><i class="fa fa-check-circle"></i> Uang fisik laci cocok dengan target sistem.</span>';
			} else if (variance < 0) {
				varianceDisplay.className = 'cashier-count-total text-red';
				if (countStatus) countStatus.innerHTML = '<span class="text-red"><i class="fa fa-exclamation-triangle"></i> Uang fisik KURANG ' + formatCountRupiah(Math.abs(variance)) + ' dari target.</span>';
			} else {
				varianceDisplay.className = 'cashier-count-total text-yellow';
				if (countStatus) countStatus.innerHTML = '<span class="text-yellow"><i class="fa fa-info-circle"></i> Uang fisik LEBIH ' + formatCountRupiah(variance) + ' dari target.</span>';
			}
		}
	};

	Array.prototype.forEach.call(countInputs, function (input) {
		input.addEventListener('input', updateCashCount);
		input.addEventListener('change', updateCashCount);
	});

	if (floatInput) {
		floatInput.addEventListener('keyup', function () {
			this.value = formatRupiahInput(this.value);
			updateCashCount();
		});
	}

	if (radioLaci) radioLaci.addEventListener('change', updateCashCount);
	if (radioLedger) radioLedger.addEventListener('change', updateCashCount);

	if (btnReset) {
		btnReset.addEventListener('click', function () {
			Array.prototype.forEach.call(countInputs, function (input) {
				input.value = 0;
			});
			if (floatInput) floatInput.value = '0';
			updateCashCount();
		});
	}

	// Cetak Slip Opname Kasir
	if (btnPrint) {
		btnPrint.addEventListener('click', function () {
			var cashierName = <?php echo json_encode(isset($cashier_name) ? $cashier_name : 'Kasir POS'); ?>;
			var now = new Date();
			var timeStr = now.toLocaleDateString('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
				now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
			var targetAmount = getTargetAmount();
			var isLedger = radioLedger && radioLedger.checked;
			var floatVal = parseMoneyVal(floatInput ? floatInput.value : 0);
			var totalFisik = 0;
			var rowsHtml = '';

			Array.prototype.forEach.call(countInputs, function (input) {
				var count = parseInt(input.value, 10) || 0;
				var denom = parseInt(input.getAttribute('data-denomination'), 10);
				var sub = count * denom;
				totalFisik += sub;
				if (count > 0) {
					rowsHtml += '<tr>' +
						'<td style="padding: 2px 0;">Rp ' + denom.toLocaleString('id-ID') + ' x ' + count + '</td>' +
						'<td style="text-align: right; padding: 2px 0;">' + formatCountRupiah(sub) + '</td>' +
					'</tr>';
				}
			});

			var variance = totalFisik - targetAmount;
			var varianceLabel = Math.abs(variance) < 1 ? 'PAS / SESUAI' : (variance < 0 ? 'KURANG ' + formatCountRupiah(Math.abs(variance)) : 'LEBIH ' + formatCountRupiah(variance));

			var printHtml = '<!DOCTYPE html><html><head><title>Slip Opname Kasir</title>' +
				'<style>' +
				'body { font-family: "Courier New", Courier, monospace; font-size: 13px; margin: 0; padding: 15px; width: 300px; color: #000; }' +
				'h3, h4, p { margin: 0 0 4px 0; text-align: center; }' +
				'.line { border-top: 1px dashed #000; margin: 8px 0; }' +
				'table { width: 100%; font-size: 13px; border-collapse: collapse; }' +
				'th, td { vertical-align: top; }' +
				'.text-right { text-align: right; }' +
				'.ttd { margin-top: 25px; text-align: center; }' +
				'.ttd-col { width: 50%; float: left; }' +
				'@media print { body { padding: 0; width: 100%; } }' +
				'</style></head><body>' +
				'<h3>KOPERASI MAKMUR INDAH</h3>' +
				'<h4>SLIP OPNAME FISIK KASIR POS</h4>' +
				'<p style="font-size: 11px;">' + timeStr + ' | Kasir: ' + cashierName + '</p>' +
				'<div class="line"></div>' +
				'<table>' +
				(isLedger ? '' : '<tr><td>Modal Awal (Float)</td><td class="text-right">' + formatCountRupiah(floatVal) + '</td></tr>') +
				'<tr><td>Kas Masuk Hari Ini</td><td class="text-right"><?php echo $format_rupiah($cashier_cash['debit_total']); ?></td></tr>' +
				'<tr><td>Kas Keluar/Retur</td><td class="text-right"><?php echo $format_rupiah($cashier_cash['credit_total']); ?></td></tr>' +
				'<tr style="font-weight: bold; border-top: 1px solid #000;">' +
				'<td>' + (isLedger ? 'Target Buku Besar' : 'Target Uang Laci') + '</td><td class="text-right">' + formatCountRupiah(targetAmount) + '</td>' +
				'</tr></table>' +
				'<div class="line"></div>' +
				'<p style="text-align: left; font-weight: bold; margin-bottom: 4px;">Rincian Fisik Terhitung:</p>' +
				'<table>' + (rowsHtml || '<tr><td colspan="2" style="font-style: italic;">Tidak ada lembar kas diinput.</td></tr>') +
				'<tr style="font-weight: bold; border-top: 1px solid #000;">' +
				'<td>TOTAL FISIK</td><td class="text-right">' + formatCountRupiah(totalFisik) + '</td>' +
				'</tr></table>' +
				'<div class="line"></div>' +
				'<table><tr style="font-weight: bold; font-size: 14px;">' +
				'<td>SELISIH:</td><td class="text-right">' + varianceLabel + '</td>' +
				'</tr></table>' +
				'<div class="line"></div>' +
				'<div class="ttd">' +
				'<div class="ttd-col">Kasir Bertugas,<br><br><br><br>(' + cashierName + ')</div>' +
				'<div class="ttd-col">Supervisor,<br><br><br><br>( ____________ )</div>' +
				'<div style="clear: both;"></div>' +
				'</div>' +
				'<script>window.onload = function() { window.print(); };<\/script>' +
				'</body></html>';

			var printWin = window.open('', '_blank', 'width=350,height=550,top=50,left=50');
			if (printWin) {
				printWin.document.open();
				printWin.document.write(printHtml);
				printWin.document.close();
			} else {
				alert('Harap izinkan pop-up browser untuk mencetak slip opname kasir.');
			}
		});
	}

	// Auto format Rupiah ribuan pada input nominal
	var amountInput = document.getElementById('cashier-amount');
	var warningBox = document.getElementById('amount-warning');

	function formatRupiahInput(value) {
		var number_string = String(value || '').replace(/[^,\d]/g, '').toString(),
			split = number_string.split(','),
			sisa = split[0].length % 3,
			rupiah = split[0].substr(0, sisa),
			ribuan = split[0].substr(sisa).match(/\d{3}/gi);

		if (ribuan) {
			var separator = sisa ? '.' : '';
			rupiah += separator + ribuan.join('.');
		}
		return split[1] != undefined ? rupiah + ',' + split[1] : rupiah;
	}

	if (amountInput) {
		amountInput.addEventListener('keyup', function () {
			this.value = formatRupiahInput(this.value);
			var rawVal = parseFloat(this.value.replace(/\./g, '').replace(/,/g, '.')) || 0;
			if (warningBox) {
				warningBox.style.display = (rawVal > 500000) ? 'block' : 'none';
			}
		});
	}

	var proofInput = document.getElementById('cashier-proof');
	var proofName = document.getElementById('cashier-proof-name');
	if (proofInput && proofName) {
		proofInput.addEventListener('change', function () {
			proofName.textContent = proofInput.files && proofInput.files.length
				? proofInput.files[0].name
				: 'PDF, JPG, atau PNG. Maksimal 5 MB.';
		});
	}

	var form = document.getElementById('cashier-petty-form');
	var submitButton = document.getElementById('cashier-submit');
	if (form && submitButton) {
		form.addEventListener('submit', function (e) {
			var rawVal = amountInput.value.replace(/\./g, '').replace(/,/g, '.');
			var rawAmount = parseFloat(rawVal) || 0;
			if (rawAmount <= 0) {
				alert('Nominal pengeluaran harus lebih besar dari Rp 0.');
				e.preventDefault();
				return;
			}
			if (rawAmount > 1000000) {
				if (!confirm('Jumlah pengeluaran sebesar Rp ' + rawAmount.toLocaleString('id-ID') + ' cukup besar untuk kas kecil kasir. Apakah Anda yakin data ini sudah benar?')) {
					e.preventDefault();
					return;
				}
			}
			amountInput.value = rawAmount.toString();
			submitButton.disabled = true;
			submitButton.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Mengirim...';
		});
	}

	var formSaveCash = document.getElementById('form-save-cash-count');
	if (formSaveCash) {
		formSaveCash.addEventListener('submit', function (e) {
			var isLedger = radioLedger && radioLedger.checked;
			var floatVal = parseMoneyVal(floatInput ? floatInput.value : 0);
			var targetAmount = getTargetAmount();
			var totalFisik = 0;
			var denomContainer = document.getElementById('save-denominations-container');
			denomContainer.innerHTML = '';

			Array.prototype.forEach.call(countInputs, function (input) {
				var count = parseInt(input.value, 10) || 0;
				var denom = parseInt(input.getAttribute('data-denomination'), 10);
				totalFisik += count * denom;
				var hidden = document.createElement('input');
				hidden.type = 'hidden';
				hidden.name = 'denominations[' + denom + ']';
				hidden.value = count;
				denomContainer.appendChild(hidden);
			});

			var variance = totalFisik - targetAmount;

			document.getElementById('save-initial-float').value = floatVal;
			document.getElementById('save-system-target').value = targetAmount;
			document.getElementById('save-counted-total').value = totalFisik;
			document.getElementById('save-variance').value = variance;
			document.getElementById('save-target-mode').value = isLedger ? 'ledger' : 'laci';

			if (totalFisik <= 0) {
				if (!confirm('Total uang fisik yang dihitung masih Rp 0. Apakah Anda yakin ingin menyimpan hasil opname ini?')) {
					e.preventDefault();
					return;
				}
			}

			var btnSubmitSave = document.getElementById('btn-submit-save-cash');
			if (btnSubmitSave) {
				btnSubmitSave.disabled = true;
				btnSubmitSave.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Menyimpan...';
			}
		});
	}
}());
</script>
