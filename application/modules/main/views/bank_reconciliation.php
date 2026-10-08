<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if ($error) { ?><div class="alert alert-danger"><?php echo html_escape($error); ?></div><?php } ?>
<?php if ($notice) { ?><div class="alert alert-success"><?php echo html_escape($notice); ?></div><?php } ?>

<div class="row">
	<div class="col-md-5">
		<div class="box box-primary">
			<div class="box-header with-border"><h3 class="box-title">Buat Rekening Koran</h3></div>
			<div class="box-body">
				<form method="post" action="<?php echo site_url('main/bank_reconciliation/create_statement'); ?>">
					<p>Akun yang direkonsiliasi: <strong>1102 — Kas Bank Koperasi</strong></p>
					<div class="form-group">
						<label for="start_date">Tanggal mulai</label>
						<input class="form-control" type="date" id="start_date" name="start_date" value="<?php echo html_escape(date('Y-m-01')); ?>" required>
					</div>
					<div class="form-group">
						<label for="end_date">Tanggal akhir</label>
						<input class="form-control" type="date" id="end_date" name="end_date" value="<?php echo html_escape(date('Y-m-d')); ?>" required>
					</div>
					<div class="form-group">
						<label for="opening_balance">Saldo awal rekening koran</label>
						<input class="form-control" type="number" id="opening_balance" name="opening_balance" step="0.01" required>
					</div>
					<div class="form-group">
						<label for="closing_balance">Saldo akhir rekening koran</label>
						<input class="form-control" type="number" id="closing_balance" name="closing_balance" step="0.01" required>
					</div>
					<button class="btn btn-primary" type="submit">Buat draft</button>
				</form>
			</div>
		</div>
	</div>
	<div class="col-md-7">
		<div class="box box-default">
			<div class="box-header with-border"><h3 class="box-title">Rekening Koran Tersimpan</h3></div>
			<div class="box-body table-responsive">
				<?php if (count($statements) === 0) { ?><p class="text-muted">Belum ada rekening koran yang dicatat.</p><?php } ?>
				<table class="table table-striped">
					<thead><tr><th>Nomor / rentang</th><th>Mutasi</th><th>Status</th><th></th></tr></thead>
					<tbody>
						<?php foreach ($statements as $row) { ?>
							<tr>
								<td><?php echo html_escape($row->statement_number); ?><br><?php echo html_escape($row->start_date.' s.d. '.$row->end_date); ?></td>
								<td><?php echo number_format((int) $row->item_count); ?></td>
								<td><?php echo $row->status === 'reconciled' ? 'Selesai' : 'Draft'; ?></td>
								<td><a class="btn btn-xs btn-default" href="<?php echo site_url('main/bank_reconciliation?statement_id='.(int) $row->id); ?>">Buka</a></td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<?php if ($statement) { ?>
	<div class="box box-info">
		<div class="box-header with-border">
			<h3 class="box-title"><?php echo html_escape($statement->statement_number); ?> — <?php echo html_escape($statement->account_code.' '.$statement->account_name); ?></h3>
			<span class="label <?php echo $statement->status === 'reconciled' ? 'label-success' : 'label-warning'; ?>" style="margin-left:8px;">
				<?php echo $statement->status === 'reconciled' ? 'Selesai' : 'Draft'; ?>
			</span>
		</div>
		<div class="box-body">
			<p>
				Periode <?php echo html_escape($statement->start_date.' s.d. '.$statement->end_date); ?>.
				Saldo awal: <strong>Rp <?php echo number_format((float) $statement->opening_balance, 2, ',', '.'); ?></strong>;
				saldo akhir rekening koran: <strong>Rp <?php echo number_format((float) $statement->closing_balance, 2, ',', '.'); ?></strong>;
				jumlah mutasi: <strong>Rp <?php echo number_format($statement_total, 2, ',', '.'); ?></strong>.
			</p>
			<?php if (abs(round((float) $statement->opening_balance + $statement_total - (float) $statement->closing_balance, 2)) > 0.009) { ?>
				<div class="alert alert-danger">Saldo awal ditambah seluruh mutasi belum sama dengan saldo akhir rekening koran.</div>
			<?php } ?>
			<div class="row">
				<div class="col-md-4">Saldo buku sampai tanggal akhir: <strong>Rp <?php echo number_format($book_balance, 2, ',', '.'); ?></strong></div>
				<div class="col-md-4">Saldo rekening koran + mutasi buku belum muncul: <strong>Rp <?php echo number_format($adjusted_statement_balance, 2, ',', '.'); ?></strong></div>
				<div class="col-md-4">Selisih tersisa: <strong>Rp <?php echo number_format($balance_difference, 2, ',', '.'); ?></strong></div>
			</div>
			<p class="help-block">
				Mutasi positif adalah dana masuk dan mutasi negatif adalah dana keluar. Rekening koran dianggap selesai setelah semua barisnya cocok dan saldo awal + mutasi sama dengan saldo akhir; selisih terhadap buku tetap ditampilkan untuk ditinjau bersama transaksi buku yang belum muncul di rekening koran.
			</p>
		</div>
	</div>

	<?php if ($can_manage && $statement->status === 'draft') { ?>
		<div class="box box-default">
			<div class="box-header with-border"><h3 class="box-title">Tambah Mutasi Rekening Koran</h3></div>
			<div class="box-body">
				<form class="form-inline" method="post" action="<?php echo site_url('main/bank_reconciliation/add_item'); ?>">
					<input type="hidden" name="statement_id" value="<?php echo (int) $statement->id; ?>">
					<div class="form-group">
						<label class="sr-only" for="transaction_date">Tanggal transaksi</label>
						<input class="form-control" type="date" id="transaction_date" name="transaction_date" value="<?php echo html_escape($statement->start_date); ?>" required>
					</div>
					<div class="form-group" style="margin-left:6px;">
						<label class="sr-only" for="description">Keterangan</label>
						<input class="form-control" type="text" id="description" name="description" maxlength="255" placeholder="Keterangan rekening koran" required>
					</div>
					<div class="form-group" style="margin-left:6px;">
						<label class="sr-only" for="external_reference">Referensi</label>
						<input class="form-control" type="text" id="external_reference" name="external_reference" maxlength="128" placeholder="No. referensi">
					</div>
					<div class="form-group" style="margin-left:6px;">
						<label class="sr-only" for="amount">Nominal</label>
						<input class="form-control" type="number" id="amount" name="amount" step="0.01" placeholder="Masuk + / keluar -" required>
					</div>
					<button class="btn btn-primary" type="submit" style="margin-left:6px;">Tambah</button>
				</form>
			</div>
		</div>
	<?php } ?>

	<div class="box box-default">
		<div class="box-header with-border"><h3 class="box-title">Mutasi Rekening Koran</h3></div>
		<div class="box-body table-responsive">
			<?php if (count($items) === 0) { ?><p class="text-muted">Belum ada mutasi rekening koran.</p><?php } ?>
			<table class="table table-striped">
				<thead><tr><th>Tanggal</th><th>Keterangan / referensi</th><th class="text-right">Nominal</th><th>Pencocokan jurnal</th><th></th></tr></thead>
				<tbody>
					<?php foreach ($items as $item) { ?>
						<tr>
							<td><?php echo html_escape($item->transaction_date); ?></td>
							<td><?php echo html_escape($item->description); ?><?php if ($item->external_reference) { ?><br><small><?php echo html_escape($item->external_reference); ?></small><?php } ?></td>
							<td class="text-right">Rp <?php echo number_format((float) $item->amount, 2, ',', '.'); ?></td>
							<td>
								<?php if ($item->matched_journal_item_id) { ?>
									<?php echo html_escape($item->journal_number.' — '.$item->journal_date.' — '.$item->reference_type.':'.$item->reference_id); ?>
									<?php if ($item->journal_memo) { ?><br><small><?php echo html_escape($item->journal_memo); ?></small><?php } ?>
								<?php } else { ?><span class="text-warning">Belum cocok</span><?php } ?>
							</td>
							<td>
								<?php if ($can_manage && $statement->status === 'draft') { ?>
									<?php if ($item->matched_journal_item_id) { ?>
										<form method="post" action="<?php echo site_url('main/bank_reconciliation/unmatch_item'); ?>">
											<input type="hidden" name="statement_item_id" value="<?php echo (int) $item->id; ?>">
											<button class="btn btn-xs btn-warning" type="submit">Lepas</button>
										</form>
									<?php } else { ?>
										<form method="post" action="<?php echo site_url('main/bank_reconciliation/remove_item'); ?>">
											<input type="hidden" name="item_id" value="<?php echo (int) $item->id; ?>">
											<button class="btn btn-xs btn-danger" type="submit">Hapus</button>
										</form>
									<?php } ?>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>

	<?php if ($can_manage && $statement->status === 'draft') { ?>
		<div class="box box-primary">
			<div class="box-header with-border"><h3 class="box-title">Cocokkan Mutasi dengan Jurnal Bank</h3></div>
			<div class="box-body">
				<?php if (count($candidates) === 0) { ?>
					<p class="text-muted">Tidak ada jurnal bank terposting yang belum dipakai untuk pencocokan.</p>
				<?php } else { ?>
					<form class="form-inline" method="post" action="<?php echo site_url('main/bank_reconciliation/match_item'); ?>">
						<div class="form-group">
							<label for="statement_item_id">Mutasi rekening koran</label>
							<select class="form-control" id="statement_item_id" name="statement_item_id" required>
								<?php foreach ($items as $item) {
									if ($item->matched_journal_item_id) { continue; }
								?>
									<option value="<?php echo (int) $item->id; ?>">
										<?php echo html_escape($item->transaction_date.' — '.$item->description.' — Rp '.number_format((float) $item->amount, 2, ',', '.')); ?>
									</option>
								<?php } ?>
							</select>
						</div>
						<div class="form-group" style="margin-left:8px;">
							<label for="journal_item_id">Jurnal akun 1102</label>
							<select class="form-control" id="journal_item_id" name="journal_item_id" required>
								<?php foreach ($candidates as $candidate) { ?>
									<option value="<?php echo (int) $candidate->journal_item_id; ?>">
										<?php echo html_escape($candidate->date.' — '.$candidate->journal_number.' — Rp '.number_format((float) $candidate->amount, 2, ',', '.').' — '.($candidate->memo ? $candidate->memo : $candidate->description)); ?>
									</option>
								<?php } ?>
							</select>
						</div>
						<button class="btn btn-primary" type="submit" style="margin-left:8px;">Cocokkan</button>
					</form>
				<?php } ?>
			</div>
		</div>

		<div class="box box-warning">
			<div class="box-header with-border"><h3 class="box-title">Jurnal Bank Belum Muncul di Rekening Koran Periode Ini</h3></div>
			<div class="box-body table-responsive">
				<?php if (count($unmatched_book_items) === 0) { ?><p class="text-muted">Tidak ada mutasi buku yang belum dicocokkan pada rentang ini.</p><?php } ?>
				<table class="table table-striped">
					<thead><tr><th>Tanggal</th><th>Jurnal / keterangan</th><th class="text-right">Mutasi buku</th></tr></thead>
					<tbody>
						<?php foreach ($unmatched_book_items as $item) { ?>
							<tr>
								<td><?php echo html_escape($item->date); ?></td>
								<td><?php echo html_escape($item->journal_number.' — '.$item->reference_type.':'.$item->reference_id.' — '.($item->memo ? $item->memo : $item->description)); ?></td>
								<td class="text-right">Rp <?php echo number_format((float) $item->amount, 2, ',', '.'); ?></td>
							</tr>
						<?php } ?>
					</tbody>
				</table>
				<?php if (count($unmatched_book_items) === 500) { ?><p class="text-warning">Daftar dibatasi 500 baris.</p><?php } ?>
			</div>
		</div>

		<div class="box box-success">
			<div class="box-body">
				<p>Mutasi rekening koran yang belum cocok: <strong><?php echo (int) $unmatched_statement_count; ?></strong>.</p>
				<form method="post" action="<?php echo site_url('main/bank_reconciliation/reconcile'); ?>">
					<input type="hidden" name="statement_id" value="<?php echo (int) $statement->id; ?>">
					<button class="btn btn-success" type="submit" <?php echo (count($items) === 0 || $unmatched_statement_count > 0 || abs(round((float) $statement->opening_balance + $statement_total - (float) $statement->closing_balance, 2)) > 0.009) ? 'disabled' : ''; ?>>
						Tandai Rekening Koran Selesai
					</button>
				</form>
			</div>
		</div>
	<?php } ?>
<?php } ?>
