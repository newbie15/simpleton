<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $rp = function ($value) { return 'Rp '.number_format((float) $value, 0, ',', '.'); }; ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<?php if ($is_manager) { ?>
<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Buat periode tagihan custom</h3></div>
	<form method="post" action="<?php echo site_url('main/sales_billing/create_period'); ?>">
		<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
		<div class="box-body">
			<div class="row">
				<div class="col-sm-4 form-group"><label for="billing-period-name">Nama periode</label><input id="billing-period-name" class="form-control" name="period_name" maxlength="64" placeholder="Tagihan 21 Sep - 20 Okt 2026" required></div>
				<div class="col-sm-3 form-group"><label for="billing-start">Tanggal awal</label><input id="billing-start" class="form-control" type="date" name="start_date" required></div>
				<div class="col-sm-3 form-group"><label for="billing-end">Tanggal akhir</label><input id="billing-end" class="form-control" type="date" name="end_date" required></div>
				<div class="col-sm-2 form-group"><label for="legacy-period">Siklus legacy (opsional)</label><input id="legacy-period" class="form-control" type="month"></div>
				<div class="col-sm-2 form-group"><label>&nbsp;</label><button id="legacy-fill" class="btn btn-default btn-block" type="button">Isi 21–20</button></div>
			</div>
			<p class="help-block">Rentang tanggal inklusif. Sistem menolak overlap dengan periode lain. Preset legacy mengikuti pembukuan lama: Januari 17 Desember–20 Januari, Desember 21 November–16 Desember, bulan lain 21–20.</p>
			<button class="btn btn-primary" type="submit">Buat periode</button>
		</div>
	</form>
</div>
<?php } ?>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Periode tagihan</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th>Periode</th><th>Tanggal awal</th><th>Tanggal akhir</th><th>Status</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($periods as $period) { ?>
			<tr>
				<td><?php echo html_escape($period->period_name); ?></td>
				<td><?php echo html_escape($period->start_date); ?></td>
				<td><?php echo html_escape($period->end_date); ?></td>
				<td><?php echo $period->status === 'finalized' ? '<span class="label label-success">Final</span>' : '<span class="label label-warning">Terbuka</span>'; ?></td>
				<td><a class="btn btn-xs btn-default" href="<?php echo site_url('main/sales_billing?period_id='.(int) $period->id); ?>">Buka</a></td>
			</tr>
			<?php } ?>
			<?php if (!$periods) { ?><tr><td colspan="5" class="text-muted">Belum ada periode tagihan. Buat rentang custom untuk memulai.</td></tr><?php } ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($selected) { ?>
<div class="box box-info">
	<div class="box-header with-border"><h3 class="box-title"><?php echo html_escape($selected->period_name); ?> · <?php echo html_escape($selected->start_date.' s.d. '.$selected->end_date); ?></h3></div>
	<div class="box-body">
		<p>Status: <strong><?php echo html_escape(ucfirst($selected->status)); ?></strong></p>
		<?php if ($selected->status === 'open' && $is_manager) { ?>
		<form method="post" action="<?php echo site_url('main/sales_billing/validate_sales/'.(int) $selected->id); ?>" class="form-inline" style="margin-bottom:12px;">
			<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
			<div class="form-group"><label for="validation-mode">Validasi&nbsp;</label><select id="validation-mode" class="form-control" name="validation_mode"><option value="period">Seluruh periode</option><option value="week">Rentang mingguan</option><option value="nota">Satu nomor nota</option></select></div>
			<div class="form-group validation-week-field" style="display:none;"><label for="validation-start">&nbsp;Dari&nbsp;</label><input id="validation-start" class="form-control" type="date" name="start_date" min="<?php echo html_escape($selected->start_date); ?>" max="<?php echo html_escape($selected->end_date); ?>"></div>
			<div class="form-group validation-week-field" style="display:none;"><label for="validation-end">&nbsp;Sampai&nbsp;</label><input id="validation-end" class="form-control" type="date" name="end_date" min="<?php echo html_escape($selected->start_date); ?>" max="<?php echo html_escape($selected->end_date); ?>"></div>
			<div class="form-group validation-nota-field" style="display:none;"><label for="validation-nota">&nbsp;No. nota&nbsp;</label><input id="validation-nota" class="form-control" name="nota" maxlength="64"></div>
			<button class="btn btn-info" type="submit">Jalankan validasi</button>
		</form>
		<form method="post" action="<?php echo site_url('main/sales_billing/finalize_period/'.(int) $selected->id); ?>" onsubmit="return window.confirm('Validasi seluruh periode dan buat snapshot tagihan final? Periode tidak dapat diedit setelah final.');">
			<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
			<button class="btn btn-success" type="submit">Generate &amp; finalisasi tagihan</button>
		</form>
		<?php } ?>
	</div>
</div>

<?php if ($runs) { ?>
<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Riwayat validasi terbaru</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>Waktu</th><th>Mode</th><th>Rentang</th><th>Nota</th><th>Hasil</th><th>Dicek</th><th>Petugas</th></tr></thead>
			<tbody><?php foreach ($runs as $run) { ?><tr>
				<td><?php echo html_escape($run->created_at); ?></td><td><?php echo html_escape(ucfirst($run->validation_mode)); ?></td>
				<td><?php echo html_escape($run->start_date.' – '.$run->end_date); ?></td><td><?php echo html_escape($run->nota_filter ? $run->nota_filter : '—'); ?></td>
				<td><?php echo $run->result === 'passed' ? '<span class="label label-success">Lolos</span>' : '<span class="label label-danger">Perlu diperiksa</span>'; ?></td>
				<td><?php echo (int) $run->checked_count; ?></td><td><?php echo html_escape($run->created_by); ?></td>
			</tr><?php } ?></tbody>
		</table>
	</div>
</div>
<?php } ?>

<?php if ($validation_items) { ?>
<div class="box box-warning">
	<div class="box-header with-border"><h3 class="box-title">Rincian validasi terakhir</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>Status</th><th>No. nota</th><th>NPK</th><th>Tanggal</th><th class="text-right">Penjualan</th><th class="text-right">Retur approved</th><th class="text-right">Tertagih</th><th>Temuan</th></tr></thead>
			<tbody><?php foreach ($validation_items as $validation_item) { ?><tr>
				<td><?php echo $validation_item->status === 'valid' ? '<span class="label label-success">Valid</span>' : '<span class="label label-danger">Perlu diperiksa</span>'; ?></td>
				<td><?php echo html_escape($validation_item->nota); ?></td><td><?php echo html_escape($validation_item->npk); ?></td>
				<td><?php echo html_escape($validation_item->transaction_date); ?></td>
				<td class="text-right"><?php echo $rp($validation_item->total_sales); ?></td>
				<td class="text-right"><?php echo $rp($validation_item->approved_returns); ?></td>
				<td class="text-right"><?php echo $rp($validation_item->billable_total); ?></td>
				<td><?php echo html_escape($validation_item->issues ? $validation_item->issues : '—'); ?></td>
			</tr><?php } ?></tbody>
		</table>
		<?php if (count($validation_items) === 500) { ?><p class="text-warning">Menampilkan 500 nota pertama pada hasil validasi terbaru.</p><?php } ?>
	</div>
</div>
<?php } ?>

<?php if ($selected->status === 'finalized') { ?>
<div class="box box-success">
	<div class="box-header with-border"><h3 class="box-title">Snapshot tagihan final</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>No. nota</th><th>NPK</th><th>Nama</th><th>Tanggal</th><th class="text-right">Penjualan</th><th class="text-right">Retur disetujui</th><th class="text-right">Tertagih</th></tr></thead>
			<tbody><?php foreach ($items as $item) { ?><tr>
				<td><?php echo html_escape($item->nota); ?></td><td><?php echo html_escape($item->npk); ?></td>
				<td><?php echo html_escape($item->employee_name ? $item->employee_name : $item->current_employee_name); ?></td>
				<td><?php echo html_escape($item->transaction_date); ?></td><td class="text-right"><?php echo $rp($item->total_sales); ?></td>
				<td class="text-right"><?php echo $rp($item->approved_returns); ?></td><td class="text-right"><?php echo $rp($item->billable_total); ?></td>
			</tr><?php } ?>
			<?php if (!$items) { ?><tr><td colspan="7" class="text-muted">Tidak ada transaksi untuk periode ini.</td></tr><?php } ?></tbody>
			<tfoot><tr><th colspan="6" class="text-right">Total tertagih</th><th class="text-right"><?php $grand_total = 0; foreach ($items as $item) { $grand_total += (float) $item->billable_total; } echo $rp($grand_total); ?></th></tr></tfoot>
		</table>
	</div>
</div>
<?php } ?>
<?php } ?>

<script>
(function () {
	function pad2(value) { return value < 10 ? '0' + value : String(value); }
	var legacyPeriod = document.getElementById('legacy-period');
	var legacyFill = document.getElementById('legacy-fill');
	if (legacyPeriod && legacyFill) {
		legacyFill.addEventListener('click', function () {
			var parts = legacyPeriod.value.split('-');
			if (parts.length !== 2) { return; }
			var year = Number(parts[0]), month = Number(parts[1]);
			var start, end;
			if (month === 1) {
				start = (year - 1) + '-12-17';
				end = year + '-01-20';
			} else if (month === 12) {
				start = year + '-11-21';
				end = year + '-12-16';
			} else {
				var previous = month === 1 ? 12 : month - 1;
				var previousYear = month === 1 ? year - 1 : year;
				start = previousYear + '-' + pad2(previous) + '-21';
				end = year + '-' + pad2(month) + '-20';
			}
			document.getElementById('billing-start').value = start;
			document.getElementById('billing-end').value = end;
		});
	}
	var mode = document.getElementById('validation-mode');
	if (!mode) { return; }
	function updateFields() {
		var week = mode.value === 'week';
		var nota = mode.value === 'nota';
		Array.prototype.forEach.call(document.querySelectorAll('.validation-week-field'), function (field) {
			field.style.display = week ? 'inline-block' : 'none';
			field.querySelector('input').required = week;
		});
		var notaField = document.querySelector('.validation-nota-field');
		notaField.style.display = nota ? 'inline-block' : 'none';
		notaField.querySelector('input').required = nota;
	}
	mode.addEventListener('change', updateFields);
	updateFields();
}());
</script>