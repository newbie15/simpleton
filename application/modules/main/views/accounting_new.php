<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$tab_labels = array(
	'overview' => 'Ringkasan',
	'accounts' => 'Bagan akun',
	'register' => 'Buku akun',
	'journals' => 'Jurnal',
	'new' => 'Transaksi baru'
);
$account_type_labels = array(
	'asset' => 'Aset',
	'liability' => 'Kewajiban',
	'equity' => 'Modal',
	'income' => 'Pendapatan',
	'cogs' => 'HPP',
	'expense' => 'Beban'
);
$format_rupiah = function ($amount) {
	return 'Rp '.number_format((float) $amount, 2, ',', '.');
};
?>

<div class="alert alert-warning">
	<strong>Catatan untuk peninjauan:</strong> halaman ini memakai akun dan jurnal yang sama dengan akuntansi lama. Jurnal yang disimpan di sini langsung masuk buku besar bersama dan tidak menyediakan tombol hapus atau pembatalan; lakukan uji input hanya jika memang ingin mencatat transaksi.
</div>
<?php if ($notice) { ?><div class="alert alert-success"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger"><?php echo html_escape($error); ?></div><?php } ?>

<div class="box box-primary">
	<div class="box-body">
		<form class="form-inline" method="get" action="<?php echo site_url('main/accounting_new'); ?>">
			<input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
			<div class="form-group">
				<label for="new_start_date">Dari</label>
				<input class="form-control" type="date" id="new_start_date" name="start_date" value="<?php echo html_escape($start_date); ?>" required>
			</div>
			<div class="form-group" style="margin-left:8px;">
				<label for="new_end_date">Sampai</label>
				<input class="form-control" type="date" id="new_end_date" name="end_date" value="<?php echo html_escape($end_date); ?>" required>
			</div>
			<?php if ($tab === 'register' && $selected_account) { ?>
				<input type="hidden" name="account_id" value="<?php echo (int) $selected_account->id; ?>">
			<?php } ?>
			<button type="submit" class="btn btn-primary" style="margin-left:8px;">Tampilkan</button>
			<a class="btn btn-default" href="<?php echo site_url('main/accounting'); ?>" style="margin-left:8px;">Buka tampilan lama</a>
		</form>
	</div>
</div>

<ul class="nav nav-tabs" style="margin-bottom:15px;">
	<?php foreach ($tab_labels as $tab_key => $label) { ?>
	<li<?php echo $tab === $tab_key ? ' class="active"' : ''; ?>>
		<a href="<?php echo site_url('main/accounting_new?tab='.$tab_key.'&start_date='.rawurlencode($start_date).'&end_date='.rawurlencode($end_date)); ?>"><?php echo html_escape($label); ?></a>
	</li>
	<?php } ?>
</ul>

<?php if ($tab === 'overview') { ?>
	<div class="row">
		<div class="col-md-3 col-sm-6"><div class="small-box bg-aqua"><div class="inner"><h3><?php echo $format_rupiah($totals['assets']); ?></h3><p>Aset per <?php echo html_escape($end_date); ?></p></div><div class="icon"><i class="fa fa-university"></i></div></div></div>
		<div class="col-md-3 col-sm-6"><div class="small-box bg-yellow"><div class="inner"><h3><?php echo $format_rupiah($totals['liabilities']); ?></h3><p>Kewajiban</p></div><div class="icon"><i class="fa fa-credit-card"></i></div></div></div>
		<div class="col-md-3 col-sm-6"><div class="small-box bg-green"><div class="inner"><h3><?php echo $format_rupiah($totals['income'] - $totals['expenses']); ?></h3><p>Surplus periode</p></div><div class="icon"><i class="fa fa-line-chart"></i></div></div></div>
		<div class="col-md-3 col-sm-6"><div class="small-box bg-blue"><div class="inner"><h3><?php echo number_format($totals['journal_count']); ?></h3><p>Jurnal pada rentang</p></div><div class="icon"><i class="fa fa-book"></i></div></div></div>
	</div>
	<div class="row">
		<div class="col-md-6">
			<div class="box box-info">
				<div class="box-header with-border"><h3 class="box-title">Posisi keuangan</h3></div>
				<div class="box-body table-responsive">
					<table class="table table-striped">
						<thead><tr><th>Kelompok</th><th class="text-right">Saldo</th></tr></thead>
						<tbody>
							<tr><td>Aset</td><td class="text-right"><?php echo $format_rupiah($totals['assets']); ?></td></tr>
							<tr><td>Kewajiban</td><td class="text-right"><?php echo $format_rupiah($totals['liabilities']); ?></td></tr>
							<tr><td>Modal</td><td class="text-right"><?php echo $format_rupiah($totals['equity']); ?></td></tr>
							<tr><td>Pendapatan bersih periode</td><td class="text-right"><?php echo $format_rupiah($totals['income'] - $totals['expenses']); ?></td></tr>
						</tbody>
					</table>
					<p class="help-block">Saldo aset, kewajiban, dan modal dihitung kumulatif sampai tanggal akhir. Pendapatan dan beban dihitung untuk rentang tanggal terpilih.</p>
				</div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="box box-default">
				<div class="box-header with-border"><h3 class="box-title">Jurnal terbaru pada rentang</h3></div>
				<div class="box-body table-responsive">
					<table class="table table-condensed">
						<thead><tr><th>Tanggal</th><th>Nomor jurnal</th><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
						<tbody>
						<?php foreach (array_slice($journals, 0, 12) as $journal) { ?>
							<tr><td><?php echo html_escape($journal->date); ?></td><td><?php echo html_escape($journal->journal_number); ?></td><td><?php echo html_escape($journal->description); ?></td><td class="text-right"><?php echo $format_rupiah($journal->total_debit); ?></td></tr>
						<?php } ?>
						<?php if (empty($journals)) { ?><tr><td colspan="4" class="text-muted">Belum ada jurnal di rentang ini.</td></tr><?php } ?>
						</tbody>
					</table>
					<a class="btn btn-default btn-sm" href="<?php echo site_url('main/accounting_new?tab=journals&start_date='.rawurlencode($start_date).'&end_date='.rawurlencode($end_date)); ?>">Lihat semua jurnal</a>
				</div>
			</div>
		</div>
	</div>
<?php } elseif ($tab === 'accounts') { ?>
	<div class="box box-info">
		<div class="box-header with-border"><h3 class="box-title">Bagan akun</h3></div>
		<div class="box-body table-responsive">
			<table class="table table-striped table-hover">
				<thead><tr><th>Kode</th><th>Nama akun</th><th>Kelompok</th><th>Saldo normal</th><th class="text-right">Saldo s.d. <?php echo html_escape($end_date); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ($accounts as $account) { ?>
					<tr>
						<td><?php echo html_escape($account->code); ?></td>
						<td><?php echo html_escape($account->name); ?></td>
						<td><?php echo html_escape(isset($account_type_labels[$account->type]) ? $account_type_labels[$account->type] : $account->type); ?></td>
						<td><?php echo html_escape(ucfirst($account->normal_balance)); ?></td>
						<td class="text-right"><?php echo $format_rupiah($account->balance); ?></td>
						<td><a class="btn btn-xs btn-default" href="<?php echo site_url('main/accounting_new?tab=register&account_id='.(int) $account->id.'&start_date='.rawurlencode($start_date).'&end_date='.rawurlencode($end_date)); ?>">Buka buku</a></td>
					</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
<?php } elseif ($tab === 'register') { ?>
	<div class="box box-info">
		<div class="box-header with-border">
			<h3 class="box-title">Buku besar per akun</h3>
			<div class="box-tools pull-right">
				<form class="form-inline" method="get" action="<?php echo site_url('main/accounting_new'); ?>">
					<input type="hidden" name="tab" value="register">
					<input type="hidden" name="start_date" value="<?php echo html_escape($start_date); ?>">
					<input type="hidden" name="end_date" value="<?php echo html_escape($end_date); ?>">
					<select class="form-control input-sm" name="account_id" onchange="this.form.submit()">
						<?php foreach ($accounts as $account) { ?>
							<option value="<?php echo (int) $account->id; ?>" <?php echo $selected_account && (int) $selected_account->id === (int) $account->id ? 'selected' : ''; ?>><?php echo html_escape($account->code.' — '.$account->name); ?></option>
						<?php } ?>
					</select>
				</form>
			</div>
		</div>
		<div class="box-body table-responsive">
			<?php if ($selected_account) { ?>
			<p><strong><?php echo html_escape($selected_account->code.' — '.$selected_account->name); ?></strong>
				<span class="text-muted">Saldo awal <?php echo html_escape($start_date); ?>:</span> <?php echo $format_rupiah($register_opening); ?></p>
			<table class="table table-bordered table-striped">
				<thead><tr><th>Tanggal</th><th>Jurnal</th><th>Keterangan</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Saldo</th></tr></thead>
				<tbody>
				<?php foreach ($register_rows as $row) { ?>
					<tr><td><?php echo html_escape($row->date); ?></td><td><?php echo html_escape($row->journal_number); ?></td><td><?php echo html_escape($row->memo ? $row->memo : $row->description); ?></td><td class="text-right"><?php echo (float) $row->debit > 0 ? $format_rupiah($row->debit) : '-'; ?></td><td class="text-right"><?php echo (float) $row->credit > 0 ? $format_rupiah($row->credit) : '-'; ?></td><td class="text-right"><?php echo $format_rupiah($row->running_balance); ?></td></tr>
				<?php } ?>
				<?php if (empty($register_rows)) { ?><tr><td colspan="6" class="text-muted">Tidak ada mutasi pada rentang ini.</td></tr><?php } ?>
				</tbody>
				<tfoot><tr><th colspan="5">Saldo akhir</th><th class="text-right"><?php echo $format_rupiah($register_closing); ?></th></tr></tfoot>
			</table>
			<?php if (count($register_rows) === 1000) { ?><p class="text-warning">Rincian dibatasi 1.000 mutasi.</p><?php } ?>
			<?php } else { ?><p class="text-muted">Belum ada akun aktif pada bagan akun.</p><?php } ?>
		</div>
	</div>
<?php } elseif ($tab === 'journals') { ?>
	<div class="box box-default">
		<div class="box-header with-border"><h3 class="box-title">Jurnal terposting</h3></div>
		<div class="box-body">
			<?php foreach ($journals as $journal) { ?>
			<div class="table-responsive">
				<table class="table table-bordered">
					<thead>
						<tr><th colspan="4"><?php echo html_escape($journal->date.' · '.$journal->journal_number.' · '.$journal->reference_type.':'.$journal->reference_id); ?><br><?php echo html_escape($journal->description); ?></th></tr>
						<tr><th>Akun</th><th>Memo</th><th class="text-right">Debit</th><th class="text-right">Kredit</th></tr>
					</thead>
					<tbody>
					<?php if (isset($journal_items[(int) $journal->id])) { foreach ($journal_items[(int) $journal->id] as $item) { ?>
						<tr><td><?php echo html_escape($item->code.' — '.$item->name); ?></td><td><?php echo html_escape($item->memo); ?></td><td class="text-right"><?php echo (float) $item->debit > 0 ? $format_rupiah($item->debit) : '-'; ?></td><td class="text-right"><?php echo (float) $item->credit > 0 ? $format_rupiah($item->credit) : '-'; ?></td></tr>
					<?php }} ?>
					</tbody>
					<tfoot><tr><th colspan="2">Jumlah seimbang</th><th class="text-right"><?php echo $format_rupiah($journal->total_debit); ?></th><th class="text-right"><?php echo $format_rupiah($journal->total_credit); ?></th></tr></tfoot>
				</table>
			</div>
			<?php } ?>
			<?php if (empty($journals)) { ?><p class="text-muted">Belum ada jurnal pada rentang ini.</p><?php } ?>
			<?php if (count($journals) === 100) { ?><p class="text-warning">Daftar dibatasi 100 jurnal terbaru.</p><?php } ?>
		</div>
	</div>
<?php } else { ?>
	<div class="box box-warning">
		<div class="box-header with-border"><h3 class="box-title">Catat transaksi berpasangan</h3></div>
		<div class="box-body">
			<p>Pilih satu akun pada setiap baris dan isi debit atau kredit. Jumlah debit dan kredit harus sama. Jurnal akan langsung diposting, bukan disimpan sebagai draft.</p>
			<form method="post" action="<?php echo site_url('main/accounting_new/post_journal'); ?>" id="new-journal-form">
				<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
				<div class="row">
					<div class="col-md-4 form-group"><label for="new_journal_date">Tanggal</label><input class="form-control" id="new_journal_date" type="date" name="date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" required></div>
					<div class="col-md-8 form-group"><label for="new_journal_description">Keterangan</label><input class="form-control" id="new_journal_description" type="text" name="description" maxlength="2000" required></div>
				</div>
				<div class="table-responsive">
					<table class="table table-bordered" id="new-journal-lines">
						<thead><tr><th>Akun</th><th>Memo baris (opsional)</th><th>Debit (Rp)</th><th>Kredit (Rp)</th><th></th></tr></thead>
						<tbody>
						<?php for ($line = 0; $line < 2; $line++) { ?>
							<tr>
								<td><select class="form-control" name="account_id[]" required><option value="">Pilih akun</option><?php foreach ($accounts as $account) { ?><option value="<?php echo (int) $account->id; ?>"><?php echo html_escape($account->code.' — '.$account->name); ?></option><?php } ?></select></td>
								<td><input class="form-control" type="text" name="memo[]" maxlength="255"></td>
								<td><input class="form-control journal-amount" type="number" name="debit[]" min="0" max="9999999999999.99" step="0.01" value="0.00" required></td>
								<td><input class="form-control journal-amount" type="number" name="credit[]" min="0" max="9999999999999.99" step="0.01" value="0.00" required></td>
								<td><button class="btn btn-default remove-journal-line" type="button" <?php echo $line < 2 ? 'disabled' : ''; ?>>Hapus</button></td>
							</tr>
						<?php } ?>
						</tbody>
						<tfoot><tr><th colspan="2" class="text-right">Total</th><th id="journal-total-debit">Rp 0,00</th><th id="journal-total-credit">Rp 0,00</th><th></th></tr></tfoot>
					</table>
				</div>
				<button class="btn btn-default" type="button" id="add-journal-line">Tambah baris</button>
				<button class="btn btn-primary" type="submit" onclick="return confirm('Posting jurnal ke buku besar bersama? Jurnal terposting tidak dapat dihapus dari layar ini.');">Posting jurnal</button>
				<span id="journal-balance-status" class="help-block"></span>
			</form>
			<template id="journal-line-template">
				<tr>
					<td><select class="form-control" name="account_id[]" required><option value="">Pilih akun</option><?php foreach ($accounts as $account) { ?><option value="<?php echo (int) $account->id; ?>"><?php echo html_escape($account->code.' — '.$account->name); ?></option><?php } ?></select></td>
					<td><input class="form-control" type="text" name="memo[]" maxlength="255"></td>
					<td><input class="form-control journal-amount" type="number" name="debit[]" min="0" max="9999999999999.99" step="0.01" value="0.00" required></td>
					<td><input class="form-control journal-amount" type="number" name="credit[]" min="0" max="9999999999999.99" step="0.01" value="0.00" required></td>
					<td><button class="btn btn-default remove-journal-line" type="button">Hapus</button></td>
				</tr>
			</template>
		</div>
	</div>
	<script>
	(function () {
		var tableBody = document.querySelector('#new-journal-lines tbody');
		var template = document.getElementById('journal-line-template');
		var addButton = document.getElementById('add-journal-line');
		var form = document.getElementById('new-journal-form');
		var money = function (cents) {
			return 'Rp ' + (cents / 100).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
		};
		var cents = function (input) {
			var amount = Number(input.value);
			return Number.isFinite(amount) && amount >= 0 ? Math.round(amount * 100) : 0;
		};
		var totals = function () {
			var debit = 0;
			var credit = 0;
			tableBody.querySelectorAll('tr').forEach(function (row) {
				debit += cents(row.querySelector('[name="debit[]"]'));
				credit += cents(row.querySelector('[name="credit[]"]'));
			});
			document.getElementById('journal-total-debit').textContent = money(debit);
			document.getElementById('journal-total-credit').textContent = money(credit);
			var balanced = debit > 0 && debit === credit;
			var status = document.getElementById('journal-balance-status');
			status.textContent = balanced ? 'Debit dan kredit seimbang.' : 'Debit harus sama dengan kredit sebelum jurnal diposting.';
			status.className = balanced ? 'help-block text-success' : 'help-block text-warning';
			return balanced;
		};
		addButton.addEventListener('click', function () {
			if (tableBody.children.length >= 20) {
				return;
			}
			tableBody.appendChild(template.content.cloneNode(true));
		});
		tableBody.addEventListener('click', function (event) {
			if (event.target.classList.contains('remove-journal-line') && tableBody.children.length > 2) {
				event.target.closest('tr').remove();
				totals();
			}
		});
		tableBody.addEventListener('input', totals);
		form.addEventListener('submit', function (event) {
			if (!totals()) {
				event.preventDefault();
			}
		});
		totals();
	}());
	</script>
<?php } ?>
