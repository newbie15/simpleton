<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Periode laporan</h3></div>
	<div class="box-body">
		<form class="form-inline" method="get" action="<?php echo site_url('main/accounting'); ?>">
			<div class="form-group">
				<label for="start_date">Dari</label>
				<input class="form-control" type="date" id="start_date" name="start_date" value="<?php echo html_escape($start_date); ?>" required>
			</div>
			<div class="form-group" style="margin-left:8px;">
				<label for="end_date">Sampai</label>
				<input class="form-control" type="date" id="end_date" name="end_date" value="<?php echo html_escape($end_date); ?>" required>
			</div>
			<div class="form-group" style="margin-left:8px;">
				<label for="account_id">Akun buku besar</label>
				<select class="form-control" id="account_id" name="account_id">
					<?php foreach ($ledger_accounts as $account) { ?>
						<option value="<?php echo (int) $account->id; ?>" <?php echo $ledger_account && (int) $ledger_account->id === (int) $account->id ? 'selected' : ''; ?>>
							<?php echo html_escape($account->code.' '.$account->name); ?>
						</option>
					<?php } ?>
				</select>
			</div>
			<button class="btn btn-primary" type="submit" style="margin-left:8px;">Tampilkan</button>
			<a class="btn btn-default" href="<?php echo site_url('main/bank_reconciliation'); ?>" style="margin-left:8px;">Rekonsiliasi Bank</a>
		</form>
	</div>
</div>

<?php $unposted_total = array_sum($unposted); ?>
<?php if ($unposted_total > 0) { ?>
	<div class="alert alert-warning">
		<strong>Perhatian:</strong> terdapat <?php echo number_format($unposted_total); ?> transaksi historis yang belum memiliki jurnal otomatis.
		<?php foreach ($unposted as $source => $count) { ?>
			<?php if ($count > 0) { ?><span><?php echo html_escape($source); ?>: <?php echo number_format($count); ?>; </span><?php } ?>
		<?php } ?>
		<br>Jurnal otomatis berlaku untuk transaksi baru. Data historis tidak diposting otomatis karena informasi lama seperti metode pembayaran atau HPP dapat tidak lengkap.
	</div>
<?php } ?>

<div class="box box-warning">
	<div class="box-header with-border"><h3 class="box-title">Periode Akuntansi</h3></div>
	<div class="box-body">
		<?php if ($period_error) { ?><div class="alert alert-danger"><?php echo html_escape($period_error); ?></div><?php } ?>
		<?php if ($period_notice) { ?><div class="alert alert-success"><?php echo html_escape($period_notice); ?></div><?php } ?>
		<p>Menutup periode hanya mengunci posting jurnal untuk tanggal pada rentang tersebut. Penutupan tidak membuat jurnal SHU. Periode hanya dapat ditutup setelah berakhir, seluruh transaksi sumber dalam rentang sudah memiliki jurnal, dan semua jurnal seimbang.</p>
		<div class="row">
			<div class="col-md-4">
				<form method="post" action="<?php echo site_url('main/accounting/create_period'); ?>">
					<div class="form-group">
						<label for="period_name">Nama periode</label>
						<input class="form-control" type="text" id="period_name" name="period_name" maxlength="32" placeholder="Contoh: Oktober 2026" required>
					</div>
					<div class="form-group">
						<label for="period_start_date">Tanggal mulai</label>
						<input class="form-control" type="date" id="period_start_date" name="period_start_date" required>
					</div>
					<div class="form-group">
						<label for="period_end_date">Tanggal akhir</label>
						<input class="form-control" type="date" id="period_end_date" name="period_end_date" required>
					</div>
					<button class="btn btn-primary" type="submit">Buat periode</button>
				</form>
			</div>
			<div class="col-md-8 table-responsive">
				<?php if (count($accounting_periods) === 0) { ?><p class="text-muted">Belum ada periode akuntansi.</p><?php } ?>
				<table class="table table-striped">
					<thead><tr><th>Periode</th><th>Rentang</th><th>Status</th><th>Ditutup</th><th>Aksi</th></tr></thead>
					<tbody>
						<?php foreach ($accounting_periods as $period) { ?>
							<tr>
								<td><?php echo html_escape($period->period_name); ?></td>
								<td><?php echo html_escape($period->start_date.' s.d. '.$period->end_date); ?></td>
								<td><?php echo (int) $period->is_closed === 1 ? 'Tertutup' : 'Terbuka'; ?></td>
								<td><?php echo $period->closed_at ? html_escape($period->closed_at) : '-'; ?></td>
								<td>
									<a class="btn btn-xs btn-default" href="<?php echo site_url('main/accounting?start_date='.rawurlencode($period->start_date).'&end_date='.rawurlencode($period->end_date)); ?>">Lihat laporan</a>
									<?php if ((int) $period->is_closed !== 1 && $period->end_date < date('Y-m-d')) { ?>
										<form method="post" action="<?php echo site_url('main/accounting/close_period'); ?>" style="display:inline-block;">
											<input type="hidden" name="period_id" value="<?php echo (int) $period->id; ?>">
											<button class="btn btn-xs btn-warning" type="submit" onclick="return confirm('Tutup periode ini dan kunci posting jurnal? Penutupan tidak dapat dibatalkan melalui aplikasi.');">Tutup periode</button>
										</form>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
				<?php if (count($accounting_periods) === 100) { ?><p class="text-warning">Daftar menampilkan 100 periode terakhir.</p><?php } ?>
			</div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-4">
		<div class="small-box bg-green">
			<div class="inner"><h3>Rp <?php echo number_format($net_income, 2, ',', '.'); ?></h3><p>Laba (rugi) periode</p></div>
			<div class="icon"><i class="fa fa-line-chart"></i></div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="small-box bg-aqua">
			<div class="inner"><h3>Rp <?php echo number_format($cash_movement, 2, ',', '.'); ?></h3><p>Perubahan bersih kas dan bank</p></div>
			<div class="icon"><i class="fa fa-money"></i></div>
		</div>
	</div>
	<div class="col-md-4">
		<div class="small-box bg-blue">
			<div class="inner"><h3><?php echo number_format(count($journals)); ?></h3><p>Jurnal pada periode ini</p></div>
			<div class="icon"><i class="fa fa-book"></i></div>
		</div>
	</div>
</div>

<div class="row">
	<div class="col-md-6">
		<div class="box box-info">
			<div class="box-header with-border"><h3 class="box-title">Arus Kas Terklasifikasi</h3></div>
			<div class="box-body table-responsive">
				<table class="table table-striped">
					<thead><tr><th>Aktivitas</th><th class="text-right">Arus kas</th></tr></thead>
					<tbody>
						<tr><td>Operasi</td><td class="text-right">Rp <?php echo number_format($cashflow['operating'], 2, ',', '.'); ?></td></tr>
						<tr><td>Investasi</td><td class="text-right">Rp <?php echo number_format($cashflow['investing'], 2, ',', '.'); ?></td></tr>
						<tr><td>Pendanaan</td><td class="text-right">Rp <?php echo number_format($cashflow['financing'], 2, ',', '.'); ?></td></tr>
						<tr><td>Tidak terklasifikasi / transfer internal</td><td class="text-right">Rp <?php echo number_format($cashflow['unclassified'], 2, ',', '.'); ?></td></tr>
					</tbody>
					<tfoot><tr><th>Perubahan kas dan bank</th><th class="text-right">Rp <?php echo number_format($cash_movement, 2, ',', '.'); ?></th></tr></tfoot>
				</table>
				<p class="help-block">Klasifikasi diturunkan dari akun lawan pada jurnal yang menyentuh kas atau bank; pergerakan internal kas-bank tidak dihitung sebagai arus masuk/keluar.</p>
				<?php if (count($cashflow_lines) > 0) { ?>
					<table class="table table-condensed">
						<thead><tr><th>Tanggal</th><th>Jurnal / akun lawan</th><th>Aktivitas</th><th class="text-right">Arus kas</th></tr></thead>
						<tbody>
							<?php foreach ($cashflow_lines as $line) { ?>
								<tr>
									<td><?php echo html_escape($line->date); ?></td>
									<td><?php echo html_escape($line->journal_number.' — '.$line->code.' '.$line->name); ?></td>
									<td><?php echo html_escape(ucfirst($line->cashflow_category)); ?></td>
									<td class="text-right">Rp <?php echo number_format($line->movement, 2, ',', '.'); ?></td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				<?php } ?>
			</div>
		</div>
	</div>
	<div class="col-md-6">
		<div class="box box-warning">
			<div class="box-header with-border"><h3 class="box-title">Ringkasan PPN dari Jurnal</h3></div>
			<div class="box-body table-responsive">
				<table class="table table-striped">
					<thead><tr><th>Akun</th><th class="text-right">Mutasi bersih</th></tr></thead>
					<tbody>
						<?php foreach ($vat_accounts as $account) { ?>
							<tr>
								<td><?php echo html_escape($account->code.' '.$account->name); ?></td>
								<td class="text-right">Rp <?php echo number_format($account->movement, 2, ',', '.'); ?></td>
							</tr>
						<?php } ?>
					</tbody>
					<tfoot><tr><th>PPN keluaran dikurangi PPN masukan</th><th class="text-right">Rp <?php echo number_format($vat_summary['output'] - $vat_summary['input'], 2, ',', '.'); ?></th></tr></tfoot>
				</table>
				<p class="help-block">Ringkasan ini berasal dari jurnal terposting, bukan pengganti pelaporan pajak. PPN retur supplier belum dapat dibalik karena data alokasi PPN per item retur belum tersedia.</p>
				<?php if (count($vat_rows) > 0) { ?>
					<table class="table table-condensed">
						<thead><tr><th>Tanggal</th><th>Akun / referensi</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr></thead>
						<tbody>
							<?php foreach ($vat_rows as $line) { ?>
								<tr>
									<td><?php echo html_escape($line->date); ?></td>
									<td><?php echo html_escape($line->code.' '.$line->name.' — '.$line->reference_type.':'.$line->reference_id); ?></td>
									<td class="text-right"><?php echo (float) $line->debit > 0 ? 'Rp '.number_format((float) $line->debit, 2, ',', '.') : '-'; ?></td>
									<td class="text-right"><?php echo (float) $line->credit > 0 ? 'Rp '.number_format((float) $line->credit, 2, ',', '.') : '-'; ?></td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
				<?php } ?>
			</div>
		</div>
	</div>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Buku Besar: <?php echo $ledger_account ? html_escape($ledger_account->code.' '.$ledger_account->name) : 'Tidak ada akun aktif'; ?></h3></div>
	<div class="box-body table-responsive">
		<?php if ($ledger_account) { ?>
			<p>Saldo awal sebelum <?php echo html_escape($start_date); ?>:
				<strong>Rp <?php echo number_format($ledger_opening, 2, ',', '.'); ?></strong>
				— saldo akhir <?php echo html_escape($end_date); ?>:
				<strong>Rp <?php echo number_format($ledger_closing, 2, ',', '.'); ?></strong>
			</p>
			<table class="table table-striped">
				<thead><tr><th>Tanggal</th><th>Nomor jurnal</th><th>Referensi</th><th>Keterangan</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Saldo berjalan</th></tr></thead>
				<tbody>
					<?php foreach ($ledger_rows as $line) { ?>
						<tr>
							<td><?php echo html_escape($line->date); ?></td>
							<td><?php echo html_escape($line->journal_number); ?></td>
							<td><?php echo html_escape($line->reference_type.':'.$line->reference_id); ?></td>
							<td><?php echo html_escape($line->memo ? $line->memo : $line->description); ?></td>
							<td class="text-right"><?php echo (float) $line->debit > 0 ? 'Rp '.number_format((float) $line->debit, 2, ',', '.') : '-'; ?></td>
							<td class="text-right"><?php echo (float) $line->credit > 0 ? 'Rp '.number_format((float) $line->credit, 2, ',', '.') : '-'; ?></td>
							<td class="text-right">Rp <?php echo number_format($line->running_balance, 2, ',', '.'); ?></td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
			<?php if (count($ledger_rows) === 0) { ?><p class="text-muted">Tidak ada mutasi akun pada periode ini.</p><?php } ?>
			<?php if (count($ledger_rows) === 1000) { ?><p class="text-warning">Rincian dibatasi 1.000 baris; saldo awal dan akhir tetap menghitung seluruh jurnal.</p><?php } ?>
		<?php } ?>
	</div>
</div>

<div class="row">
	<div class="col-md-6">
		<div class="box box-success">
			<div class="box-header with-border"><h3 class="box-title">Laba Rugi</h3></div>
			<div class="box-body table-responsive">
				<table class="table table-striped">
					<thead><tr><th>Kode</th><th>Akun</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Saldo laporan</th></tr></thead>
					<tbody>
						<?php foreach ($profit_loss as $account) {
							$report_balance = $account->type === 'income'
								? $account->credit - $account->debit
								: $account->debit - $account->credit;
						?>
							<tr>
								<td><?php echo html_escape($account->code); ?></td>
								<td><?php echo html_escape($account->name); ?></td>
								<td class="text-right">Rp <?php echo number_format($account->debit, 2, ',', '.'); ?></td>
								<td class="text-right">Rp <?php echo number_format($account->credit, 2, ',', '.'); ?></td>
								<td class="text-right">Rp <?php echo number_format($report_balance, 2, ',', '.'); ?></td>
							</tr>
						<?php } ?>
					</tbody>
					<tfoot><tr><th colspan="4">Laba (rugi) bersih</th><th class="text-right">Rp <?php echo number_format($net_income, 2, ',', '.'); ?></th></tr></tfoot>
				</table>
			</div>
		</div>
	</div>
	<div class="col-md-6">
		<div class="box box-info">
			<div class="box-header with-border"><h3 class="box-title">Neraca Saldo Akun Neraca</h3></div>
			<div class="box-body table-responsive">
				<table class="table table-striped">
					<thead><tr><th>Kode</th><th>Akun</th><th>Tipe</th><th class="text-right">Saldo</th></tr></thead>
					<tbody>
						<?php foreach ($balance_sheet as $account) { ?>
							<tr>
								<td><?php echo html_escape($account->code); ?></td>
								<td><?php echo html_escape($account->name); ?></td>
								<td><?php echo html_escape(ucfirst($account->type)); ?></td>
								<td class="text-right">Rp <?php echo number_format($account->balance, 2, ',', '.'); ?></td>
							</tr>
						<?php } ?>
						<tr><td>-</td><td>Laba (rugi) berjalan s.d. <?php echo html_escape($end_date); ?></td><td>Equity</td><td class="text-right">Rp <?php echo number_format($earnings_as_of, 2, ',', '.'); ?></td></tr>
					</tbody>
				</table>
				<p class="help-block">Neraca saldo dihitung secara kumulatif sampai tanggal akhir; laba berjalan terpisah dan belum ditutup ke akun SHU.</p>
			</div>
		</div>
	</div>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Jurnal Umum</h3></div>
	<div class="box-body">
		<?php if (count($journals) === 0) { ?>
			<p class="text-muted">Belum ada jurnal terposting pada rentang tanggal ini.</p>
		<?php } ?>
		<?php foreach ($journals as $journal) { ?>
			<div class="table-responsive">
				<table class="table table-bordered">
					<thead>
						<tr>
							<th colspan="4">
								<?php echo html_escape($journal->journal_number); ?> —
								<?php echo html_escape($journal->date); ?> —
								<?php echo html_escape($journal->reference_type); ?>:
								<?php echo html_escape($journal->reference_id); ?>
								<br><?php echo html_escape($journal->description); ?>
							</th>
						</tr>
						<tr><th>Akun</th><th>Keterangan</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr>
					</thead>
					<tbody>
						<?php if (isset($journal_items[(int) $journal->id])) { ?>
							<?php foreach ($journal_items[(int) $journal->id] as $line) { ?>
								<tr>
									<td><?php echo html_escape($line->code.' '.$line->name); ?></td>
									<td><?php echo html_escape($line->memo); ?></td>
									<td class="text-right"><?php echo (float) $line->debit > 0 ? 'Rp '.number_format((float) $line->debit, 2, ',', '.') : '-'; ?></td>
									<td class="text-right"><?php echo (float) $line->credit > 0 ? 'Rp '.number_format((float) $line->credit, 2, ',', '.') : '-'; ?></td>
								</tr>
							<?php } ?>
						<?php } ?>
					</tbody>
					<tfoot><tr><th colspan="2">Total</th><th class="text-right">Rp <?php echo number_format((float) $journal->total_debit, 2, ',', '.'); ?></th><th class="text-right">Rp <?php echo number_format((float) $journal->total_credit, 2, ',', '.'); ?></th></tr></tfoot>
				</table>
			</div>
		<?php } ?>
	</div>
</div>
