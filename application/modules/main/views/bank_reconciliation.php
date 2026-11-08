<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (isset($error) && $error) { ?><div class="alert alert-danger alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-ban"></i> <?php echo html_escape($error); ?></div><?php } ?>
<?php if (isset($notice) && $notice) { ?><div class="alert alert-success alert-dismissible"><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><i class="icon fa fa-check"></i> <?php echo html_escape($notice); ?></div><?php } ?>

<!-- Info Boxes -->
<div class="row">
	<div class="col-md-4 col-sm-6 col-xs-12">
		<div class="info-box">
			<span class="info-box-icon bg-aqua"><i class="fa fa-university"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Kas Bank (1102) Buku Besar</span>
				<span class="info-box-number">Rp <?php echo number_format(isset($current_bank_balance) ? $current_bank_balance : 0, 2, ',', '.'); ?></span>
				<span class="text-muted" style="font-size:12px;">Akun: <?php echo isset($bank_account) && $bank_account ? html_escape($bank_account->code.' '.$bank_account->name) : '1102 Kas Bank'; ?></span>
			</div>
		</div>
	</div>
	<div class="col-md-4 col-sm-6 col-xs-12">
		<div class="info-box">
			<span class="info-box-icon bg-green"><i class="fa fa-files-o"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Rekening Koran Tersimpan</span>
				<span class="info-box-number"><?php echo isset($statement_counts) ? (int)$statement_counts['total'] : count($statements); ?> Dokumen</span>
				<span class="text-muted" style="font-size:12px;">
					<?php echo isset($statement_counts) ? (int)$statement_counts['reconciled'].' Selesai, '.(int)$statement_counts['draft'].' Draft' : ''; ?>
				</span>
			</div>
		</div>
	</div>
	<div class="col-md-4 col-sm-6 col-xs-12">
		<div class="info-box">
			<span class="info-box-icon <?php echo ($statement && $statement->status === 'reconciled') ? 'bg-green' : ($statement ? 'bg-yellow' : 'bg-gray'); ?>">
				<i class="fa <?php echo $statement ? ($statement->status === 'reconciled' ? 'fa-check-circle' : 'fa-pencil-square-o') : 'fa-folder-open-o'; ?>"></i>
			</span>
			<div class="info-box-content">
				<span class="info-box-text">Dokumen Aktif</span>
				<span class="info-box-number" style="font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
					<?php echo $statement ? html_escape($statement->statement_number) : 'Belum Dipilih'; ?>
				</span>
				<div style="margin-top:4px;">
					<?php if ($statement) { ?>
						<span class="label <?php echo $statement->status === 'reconciled' ? 'label-success' : 'label-warning'; ?>">
							<?php echo $statement->status === 'reconciled' ? 'Selesai' : 'Draft'; ?>
						</span>
						<a class="btn btn-xs btn-default pull-right" href="<?php echo site_url('main/bank_reconciliation?statement_id=0'); ?>" title="Kembali ke Daftar">
							<i class="fa fa-list"></i> Ganti
						</a>
					<?php } else { ?>
						<a class="btn btn-xs btn-primary" href="#form-buat-statement">
							<i class="fa fa-plus"></i> Buat Baru
						</a>
					<?php } ?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php if ($statement) { ?>
	<!-- STATEMENT WORKSPACE -->
	<div class="box box-info">
		<div class="box-header with-border">
			<h3 class="box-title">
				<i class="fa fa-file-text-o"></i> <?php echo html_escape($statement->statement_number); ?> — <?php echo html_escape($statement->account_code.' '.$statement->account_name); ?>
			</h3>
			<span class="label <?php echo $statement->status === 'reconciled' ? 'label-success' : 'label-warning'; ?>" style="margin-left:8px;font-size:12px;">
				<?php echo $statement->status === 'reconciled' ? 'Selesai Direkonsiliasi' : 'Draft / Sedang Dikerjakan'; ?>
			</span>
			<div class="box-tools pull-right">
				<a class="btn btn-sm btn-default" href="<?php echo site_url('main/bank_reconciliation?statement_id=0'); ?>">
					<i class="fa fa-arrow-left"></i> Kembali ke Daftar & Ikhtisar
				</a>
			</div>
		</div>
		<div class="box-body">
			<div class="row">
				<div class="col-md-6">
					<table class="table table-condensed table-bordered">
						<tr>
							<th style="width:40%;">Periode Rekening Koran</th>
							<td><strong><?php echo html_escape($statement->start_date.' s.d. '.$statement->end_date); ?></strong></td>
						</tr>
						<tr>
							<th>Saldo Awal Rekening Koran</th>
							<td>Rp <?php echo number_format((float) $statement->opening_balance, 2, ',', '.'); ?></td>
						</tr>
						<tr>
							<th>Total Mutasi Rekening Koran</th>
							<td>Rp <?php echo number_format($statement_total, 2, ',', '.'); ?> (<?php echo count($items); ?> baris)</td>
						</tr>
						<tr>
							<th>Saldo Akhir Rekening Koran</th>
							<td><strong>Rp <?php echo number_format((float) $statement->closing_balance, 2, ',', '.'); ?></strong></td>
						</tr>
					</table>
				</div>
				<div class="col-md-6">
					<table class="table table-condensed table-bordered">
						<tr>
							<th style="width:50%;">Saldo Buku s.d. Tanggal Akhir</th>
							<td>Rp <?php echo number_format($book_balance, 2, ',', '.'); ?></td>
						</tr>
						<tr>
							<th>Saldo Koran + Mutasi Buku Tertunda</th>
							<td>Rp <?php echo number_format($adjusted_statement_balance, 2, ',', '.'); ?></td>
						</tr>
						<tr class="<?php echo abs($balance_difference) > 0.009 ? 'danger' : 'success'; ?>">
							<th>Selisih Tersisa (Koran vs Buku)</th>
							<td><strong>Rp <?php echo number_format($balance_difference, 2, ',', '.'); ?></strong></td>
						</tr>
						<tr>
							<th>Mutasi Koran Belum Cocok</th>
							<td>
								<?php if ($unmatched_statement_count > 0) { ?>
									<span class="badge bg-yellow"><?php echo (int) $unmatched_statement_count; ?> belum cocok</span>
								<?php } else { ?>
									<span class="badge bg-green"><i class="fa fa-check"></i> Semua cocok</span>
								<?php } ?>
							</td>
						</tr>
					</table>
				</div>
			</div>

			<?php if (abs(round((float) $statement->opening_balance + $statement_total - (float) $statement->closing_balance, 2)) > 0.009) { ?>
				<div class="alert alert-danger" style="margin-top:10px;margin-bottom:0;">
					<i class="fa fa-exclamation-triangle"></i> <strong>Perhatian:</strong> Saldo awal (Rp <?php echo number_format((float) $statement->opening_balance, 2, ',', '.'); ?>) ditambah seluruh mutasi (Rp <?php echo number_format($statement_total, 2, ',', '.'); ?>) belum sama dengan saldo akhir rekening koran (Rp <?php echo number_format((float) $statement->closing_balance, 2, ',', '.'); ?>). Selisih kalkulasi: Rp <?php echo number_format(round((float) $statement->opening_balance + $statement_total - (float) $statement->closing_balance, 2), 2, ',', '.'); ?>.
				</div>
			<?php } ?>
		</div>
	</div>

	<?php if ($can_manage && $statement->status === 'draft') { ?>
		<div class="box box-default">
			<div class="box-header with-border">
				<h3 class="box-title"><i class="fa fa-plus-circle"></i> Tambah Mutasi Baris Rekening Koran</h3>
			</div>
			<div class="box-body">
				<form class="form-inline" method="post" action="<?php echo site_url('main/bank_reconciliation/add_item'); ?>">
					<input type="hidden" name="statement_id" value="<?php echo (int) $statement->id; ?>">
					<div class="form-group">
						<label class="sr-only" for="transaction_date">Tanggal transaksi</label>
						<input class="form-control" type="date" id="transaction_date" name="transaction_date" value="<?php echo html_escape($statement->start_date); ?>" min="<?php echo html_escape($statement->start_date); ?>" max="<?php echo html_escape($statement->end_date); ?>" required>
					</div>
					<div class="form-group" style="margin-left:6px;">
						<label class="sr-only" for="description">Keterangan</label>
						<input class="form-control" type="text" id="description" name="description" maxlength="255" placeholder="Keterangan rekening koran" style="min-width:260px;" required>
					</div>
					<div class="form-group" style="margin-left:6px;">
						<label class="sr-only" for="external_reference">Referensi</label>
						<input class="form-control" type="text" id="external_reference" name="external_reference" maxlength="128" placeholder="No. ref / cek / transfer">
					</div>
					<div class="form-group" style="margin-left:6px;">
						<label class="sr-only" for="amount">Nominal</label>
						<input class="form-control" type="number" id="amount" name="amount" step="0.01" placeholder="Nominal (+masuk / -keluar)" required>
					</div>
					<button class="btn btn-primary" type="submit" style="margin-left:6px;">
						<i class="fa fa-plus"></i> Tambah Mutasi
					</button>
				</form>
				<p class="help-block" style="margin-bottom:0;margin-top:6px;">
					Gunakan nilai positif (+) untuk dana masuk/kredit di rekening koran, dan nilai negatif (-) untuk dana keluar/debit di rekening koran.
				</p>
			</div>
		</div>
	<?php } ?>

	<div class="box box-default">
		<div class="box-header with-border">
			<h3 class="box-title"><i class="fa fa-list-alt"></i> Daftar Mutasi Rekening Koran Terinput (<?php echo count($items); ?>)</h3>
		</div>
		<div class="box-body table-responsive">
			<?php if (count($items) === 0) { ?>
				<p class="text-muted">Belum ada mutasi baris rekening koran yang ditambahkan pada dokumen ini.</p>
			<?php } else { ?>
				<table class="table table-striped table-hover">
					<thead>
						<tr>
							<th>Tanggal</th>
							<th>Keterangan / Referensi</th>
							<th class="text-right">Nominal</th>
							<th>Pencocokan Jurnal Buku Kas Bank</th>
							<th style="width:90px;" class="text-center">Aksi</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($items as $item) { ?>
							<tr>
								<td><?php echo html_escape($item->transaction_date); ?></td>
								<td>
									<strong><?php echo html_escape($item->description); ?></strong>
									<?php if ($item->external_reference) { ?>
										<br><small class="text-muted"><i class="fa fa-tag"></i> Ref: <?php echo html_escape($item->external_reference); ?></small>
									<?php } ?>
								</td>
								<td class="text-right" style="font-weight:bold; color:<?php echo (float)$item->amount >= 0 ? '#00a65a' : '#dd4b39'; ?>">
									<?php echo (float)$item->amount >= 0 ? '+ ' : '- '; ?>Rp <?php echo number_format(abs((float) $item->amount), 2, ',', '.'); ?>
								</td>
								<td>
									<?php if ($item->matched_journal_item_id) { ?>
										<span class="label label-success"><i class="fa fa-check"></i> Cocok</span>
										<span style="margin-left:6px;"><?php echo html_escape($item->journal_number.' — '.$item->journal_date.' — '.$item->reference_type.':'.$item->reference_id); ?></span>
										<?php if ($item->journal_memo) { ?><br><small class="text-muted"><?php echo html_escape($item->journal_memo); ?></small><?php } ?>
									<?php } else { ?>
										<span class="label label-warning"><i class="fa fa-clock-o"></i> Belum cocok</span>
									<?php } ?>
								</td>
								<td class="text-center">
									<?php if ($can_manage && $statement->status === 'draft') { ?>
										<?php if ($item->matched_journal_item_id) { ?>
											<form method="post" action="<?php echo site_url('main/bank_reconciliation/unmatch_item'); ?>" style="display:inline;">
												<input type="hidden" name="statement_item_id" value="<?php echo (int) $item->id; ?>">
												<button class="btn btn-xs btn-warning" type="submit" title="Lepas pencocokan"><i class="fa fa-chain-broken"></i> Lepas</button>
											</form>
										<?php } else { ?>
											<form method="post" action="<?php echo site_url('main/bank_reconciliation/remove_item'); ?>" style="display:inline;" onsubmit="return confirm('Hapus baris mutasi rekening koran ini?');">
												<input type="hidden" name="item_id" value="<?php echo (int) $item->id; ?>">
												<button class="btn btn-xs btn-danger" type="submit" title="Hapus baris mutasi"><i class="fa fa-trash"></i> Hapus</button>
											</form>
										<?php } ?>
									<?php } else { ?>
										<span class="text-muted">—</span>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
			<?php } ?>
		</div>
	</div>

	<?php if ($can_manage && $statement->status === 'draft') { ?>
		<div class="box box-primary">
			<div class="box-header with-border">
				<h3 class="box-title"><i class="fa fa-link"></i> Cocokkan Mutasi Rekening Koran dengan Jurnal Kas Bank</h3>
			</div>
			<div class="box-body">
				<?php if (count($candidates) === 0) { ?>
					<p class="text-muted">Tidak ada jurnal bank terposting yang belum dipakai untuk pencocokan pada periode s.d. <?php echo html_escape($statement->end_date); ?>.</p>
				<?php } else { ?>
					<form class="form-inline" method="post" action="<?php echo site_url('main/bank_reconciliation/match_item'); ?>">
						<div class="form-group" style="margin-right:8px; margin-bottom:8px;">
							<label for="statement_item_id">Baris Rekening Koran Belum Cocok:</label><br>
							<select class="form-control" id="statement_item_id" name="statement_item_id" style="min-width:320px;" required>
								<option value="">— Pilih mutasi rekening koran —</option>
								<?php foreach ($items as $item) {
									if ($item->matched_journal_item_id) { continue; }
								?>
									<option value="<?php echo (int) $item->id; ?>">
										<?php echo html_escape($item->transaction_date.' — '.$item->description.' — Rp '.number_format((float) $item->amount, 2, ',', '.')); ?>
									</option>
								<?php } ?>
							</select>
						</div>
						<div class="form-group" style="margin-right:8px; margin-bottom:8px;">
							<label for="journal_item_id">Jurnal Buku Besar Akun 1102:</label><br>
							<select class="form-control" id="journal_item_id" name="journal_item_id" style="min-width:360px;" required>
								<option value="">— Pilih transaksi jurnal bank —</option>
								<?php foreach ($candidates as $candidate) { ?>
									<option value="<?php echo (int) $candidate->journal_item_id; ?>">
										<?php echo html_escape($candidate->date.' — '.$candidate->journal_number.' — Rp '.number_format((float) $candidate->amount, 2, ',', '.').' — '.($candidate->memo ? $candidate->memo : $candidate->description)); ?>
									</option>
								<?php } ?>
							</select>
						</div>
						<div class="form-group" style="margin-bottom:8px;">
							<label>&nbsp;</label><br>
							<button class="btn btn-primary" type="submit">
								<i class="fa fa-link"></i> Cocokkan Baris Ini
							</button>
						</div>
					</form>
				<?php } ?>
			</div>
		</div>

		<div class="box box-warning">
			<div class="box-header with-border">
				<h3 class="box-title"><i class="fa fa-hourglass-half"></i> Jurnal Kas Bank Belum Muncul di Rekening Koran Periode Ini (<?php echo count($unmatched_book_items); ?>)</h3>
			</div>
			<div class="box-body table-responsive">
				<?php if (count($unmatched_book_items) === 0) { ?>
					<p class="text-muted">Seluruh transaksi buku kas bank pada rentang tanggal ini sudah berhasil dicocokkan.</p>
				<?php } else { ?>
					<p class="help-block">Transaksi kas bank di bawah ini sudah tercatat di pembukuan koperasi tetapi belum dicocokkan dengan mutasi rekening koran (misal setoran dalam perjalanan atau cek beredar):</p>
					<table class="table table-striped table-hover">
						<thead>
							<tr>
								<th>Tanggal</th>
								<th>No. Jurnal / Referensi</th>
								<th>Keterangan / Memo</th>
								<th class="text-right">Mutasi Buku</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($unmatched_book_items as $item) { ?>
								<tr>
									<td><?php echo html_escape($item->date); ?></td>
									<td><?php echo html_escape($item->journal_number.' — '.$item->reference_type.':'.$item->reference_id); ?></td>
									<td><?php echo html_escape($item->memo ? $item->memo : $item->description); ?></td>
									<td class="text-right" style="font-weight:bold; color:<?php echo (float)$item->amount >= 0 ? '#00a65a' : '#dd4b39'; ?>">
										<?php echo (float)$item->amount >= 0 ? '+ ' : '- '; ?>Rp <?php echo number_format(abs((float) $item->amount), 2, ',', '.'); ?>
									</td>
								</tr>
							<?php } ?>
						</tbody>
					</table>
					<?php if (count($unmatched_book_items) === 500) { ?><p class="text-warning">Daftar dibatasi 500 baris terbaru.</p><?php } ?>
				<?php } ?>
			</div>
		</div>

		<div class="box box-success">
			<div class="box-body">
				<div class="row">
					<div class="col-sm-8">
						<h4 style="margin-top:0;"><strong>Penyelesaian Rekonsiliasi</strong></h4>
						<p>
							Mutasi rekening koran yang belum cocok: <strong><?php echo (int) $unmatched_statement_count; ?></strong> baris.<br>
							Kondisi penyelesaian: seluruh mutasi rekening koran harus cocok dan kalkulasi saldo awal + mutasi harus seimbang dengan saldo akhir.
						</p>
					</div>
					<div class="col-sm-4 text-right" style="padding-top:10px;">
						<form method="post" action="<?php echo site_url('main/bank_reconciliation/reconcile'); ?>" onsubmit="return confirm('Tandai rekening koran ini selesai direkonsiliasi? Setelah selesai, pencocokan tidak dapat diubah.');">
							<input type="hidden" name="statement_id" value="<?php echo (int) $statement->id; ?>">
							<button class="btn btn-success btn-lg" type="submit" <?php echo (count($items) === 0 || $unmatched_statement_count > 0 || abs(round((float) $statement->opening_balance + $statement_total - (float) $statement->closing_balance, 2)) > 0.009) ? 'disabled' : ''; ?>>
								<i class="fa fa-check-square-o"></i> Tandai Rekonsiliasi Selesai
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>
	<?php } ?>

<?php } else { ?>
	<!-- OVERVIEW & CREATE NEW STATEMENT (WHEN NO STATEMENT SELECTED) -->
	<div class="row">
		<div class="col-md-5" id="form-buat-statement">
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-plus-circle"></i> Buat Dokumen Rekening Koran Baru</h3>
				</div>
				<div class="box-body">
					<form method="post" action="<?php echo site_url('main/bank_reconciliation/create_statement'); ?>">
						<p class="text-muted">Akun yang direkonsiliasi: <strong>1102 — Kas Bank Koperasi</strong></p>
						<div class="form-group">
							<label for="start_date">Tanggal mulai periode</label>
							<input class="form-control" type="date" id="start_date" name="start_date" value="<?php echo html_escape(date('Y-m-01')); ?>" required>
						</div>
						<div class="form-group">
							<label for="end_date">Tanggal akhir periode</label>
							<input class="form-control" type="date" id="end_date" name="end_date" value="<?php echo html_escape(date('Y-m-d')); ?>" required>
						</div>
						<div class="form-group">
							<label for="opening_balance">Saldo awal rekening koran (Rp)</label>
							<input class="form-control" type="number" id="opening_balance" name="opening_balance" step="0.01" placeholder="0.00" required>
						</div>
						<div class="form-group">
							<label for="closing_balance">Saldo akhir rekening koran (Rp)</label>
							<input class="form-control" type="number" id="closing_balance" name="closing_balance" step="0.01" placeholder="0.00" required>
						</div>
						<button class="btn btn-primary" type="submit">
							<i class="fa fa-save"></i> Buat Draft Rekening Koran
						</button>
					</form>
				</div>
			</div>
		</div>

		<div class="col-md-7">
			<div class="box box-default">
				<div class="box-header with-border">
					<h3 class="box-title"><i class="fa fa-history"></i> Rekening Koran Tersimpan</h3>
				</div>
				<div class="box-body table-responsive">
					<?php if (count($statements) === 0) { ?>
						<p class="text-muted">Belum ada rekening koran yang dicatat. Silakan buat draft rekening koran melalui form di samping.</p>
					<?php } else { ?>
						<p class="text-muted">Menampilkan maksimal 100 rekening koran terbaru. Ringkasan di atas menghitung seluruh dokumen.</p>
						<table class="table table-striped table-hover">
							<thead>
								<tr>
									<th>Nomor Dokumen</th>
									<th>Rentang Tanggal</th>
									<th class="text-center">Mutasi</th>
									<th>Status</th>
									<th class="text-center">Aksi</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($statements as $row) { ?>
									<tr>
										<td><strong><?php echo html_escape($row->statement_number); ?></strong></td>
										<td><?php echo html_escape($row->start_date.' s.d. '.$row->end_date); ?></td>
										<td class="text-center"><span class="badge bg-aqua"><?php echo number_format((int) $row->item_count); ?></span></td>
										<td>
											<span class="label <?php echo $row->status === 'reconciled' ? 'label-success' : 'label-warning'; ?>">
												<?php echo $row->status === 'reconciled' ? 'Selesai' : 'Draft'; ?>
											</span>
										</td>
										<td class="text-center">
											<a class="btn btn-xs btn-primary" href="<?php echo site_url('main/bank_reconciliation?statement_id='.(int) $row->id); ?>">
												<i class="fa fa-folder-open"></i> Buka Rekonsiliasi
											</a>
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

	<!-- RECENT BANK JOURNAL TRANSACTIONS FROM GENERAL LEDGER -->
	<div class="box box-info">
		<div class="box-header with-border">
			<h3 class="box-title"><i class="fa fa-book"></i> Mutasi Jurnal Kas Bank (Akun 1102) di Buku Besar Terbaru</h3>
			<div class="box-tools pull-right">
				<a class="btn btn-xs btn-default" href="<?php echo site_url('main/accounting_new?tab=register&account_id='.(isset($bank_account) && $bank_account ? (int)$bank_account->id : 2)); ?>">
					<i class="fa fa-external-link"></i> Buka Buku Besar Akun 1102
				</a>
			</div>
		</div>
		<div class="box-body table-responsive">
			<?php if (empty($recent_bank_journals)) { ?>
				<p class="text-muted">Belum ada transaksi jurnal kas bank (akun 1102) yang diposting di sistem akuntansi.</p>
			<?php } else { ?>
				<table class="table table-striped table-hover">
					<thead>
						<tr>
							<th>Tanggal</th>
							<th>No. Jurnal / Referensi</th>
							<th>Keterangan / Memo</th>
							<th class="text-right">Debit (Masuk)</th>
							<th class="text-right">Kredit (Keluar)</th>
							<th class="text-right">Net</th>
							<th class="text-center">Status Rekonsiliasi</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($recent_bank_journals as $j) { ?>
							<tr>
								<td><?php echo html_escape($j->date); ?></td>
								<td><strong><?php echo html_escape($j->journal_number); ?></strong><br><small class="text-muted"><?php echo html_escape($j->reference_type.':'.$j->reference_id); ?></small></td>
								<td>
									<?php echo html_escape($j->description); ?>
									<?php if ($j->memo) { ?><br><small class="text-muted"><?php echo html_escape($j->memo); ?></small><?php } ?>
								</td>
								<td class="text-right text-green"><?php echo (float)$j->debit > 0 ? 'Rp '.number_format((float)$j->debit, 2, ',', '.') : '—'; ?></td>
								<td class="text-right text-red"><?php echo (float)$j->credit > 0 ? 'Rp '.number_format((float)$j->credit, 2, ',', '.') : '—'; ?></td>
								<td class="text-right" style="font-weight:bold; color:<?php echo (float)$j->amount >= 0 ? '#00a65a' : '#dd4b39'; ?>">
									<?php echo (float)$j->amount >= 0 ? '+ ' : '- '; ?>Rp <?php echo number_format(abs((float)$j->amount), 2, ',', '.'); ?>
								</td>
								<td class="text-center">
									<?php if ($j->matched_statement_item_id) { ?>
										<span class="label label-success" title="Direkonsiliasi pada: <?php echo html_escape($j->matched_statement_number); ?>">
											<i class="fa fa-check"></i> Sudah Cocok
										</span>
									<?php } else { ?>
										<span class="label label-default">Belum Cocok</span>
									<?php } ?>
								</td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
			<?php } ?>
		</div>
	</div>

	<!-- PANDUAN REKONSILIASI -->
	<div class="callout callout-info">
		<h4><i class="fa fa-info-circle"></i> Panduan Rekonsiliasi Bank</h4>
		<ol style="padding-left:20px; margin-bottom:0;">
			<li><strong>Buat Dokumen Rekening Koran:</strong> Tentukan periode (misal 1 bulan) serta masukkan saldo awal dan saldo akhir sesuai rekening koran resmi dari bank.</li>
			<li><strong>Input Mutasi Koran:</strong> Buka dokumen rekonsiliasi yang baru dibuat, lalu tambahkan baris mutasi rekening koran (nominal + untuk penerimaan, nominal - untuk pengeluaran).</li>
			<li><strong>Pencocokan:</strong> Cocokkan setiap baris mutasi rekening koran dengan jurnal kas bank (akun 1102) yang telah diposting di sistem akuntansi.</li>
			<li><strong>Penyelesaian:</strong> Setelah semua baris cocok dan saldo awal + total mutasi tepat sama dengan saldo akhir rekening koran, klik tombol <em>Tandai Rekonsiliasi Selesai</em>.</li>
		</ol>
	</div>
<?php } ?>
