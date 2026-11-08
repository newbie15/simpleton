<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $rp = function ($value) { return 'Rp '.number_format((float) $value, 0, ',', '.'); }; ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<p><a href="<?php echo site_url('main/sales_billing'); ?>"><i class="fa fa-arrow-left"></i> Kembali ke daftar periode</a></p>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title"><?php echo html_escape($period->period_name); ?></h3></div>
	<div class="box-body">
		<dl class="dl-horizontal">
			<dt>Rentang tanggal</dt><dd><?php echo html_escape($period->start_date.' s.d. '.$period->end_date); ?></dd>
			<dt>Status</dt><dd><?php echo $period->status === 'finalized' ? '<span class="label label-success">Final</span>' : '<span class="label label-warning">Terbuka</span>'; ?></dd>
			<dt>Dibuat oleh</dt><dd><?php echo html_escape($period->created_name); ?> · <?php echo html_escape($period->created_at); ?></dd>
			<?php if ($period->status === 'finalized') { ?>
			<dt>Difinalisasi oleh</dt><dd><?php echo html_escape($period->finalized_name); ?> · <?php echo html_escape($period->finalized_at); ?></dd>
			<?php } ?>
		</dl>
	</div>
</div>

<?php if ($period->status === 'open') { ?>
<div class="row">
	<div class="col-sm-3 col-xs-6"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-file-text-o"></i></span><div class="info-box-content"><span class="info-box-text">Total nota</span><span class="info-box-number"><?php echo $stats ? $stats['total'] : '-'; ?></span></div></div></div>
	<div class="col-sm-3 col-xs-6"><div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-check"></i></span><div class="info-box-content"><span class="info-box-text">Sudah cocok</span><span class="info-box-number"><?php echo $stats ? $stats['matched'] : '-'; ?></span></div></div></div>
	<div class="col-sm-3 col-xs-6"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span><div class="info-box-content"><span class="info-box-text">Belum di-scan</span><span class="info-box-number"><?php echo $stats ? $stats['pending'] : '-'; ?></span></div></div></div>
	<div class="col-sm-3 col-xs-6"><div class="info-box"><span class="info-box-icon bg-red"><i class="fa fa-warning"></i></span><div class="info-box-content"><span class="info-box-text">Bermasalah</span><span class="info-box-number"><?php echo $stats ? $stats['problems'] : '-'; ?></span></div></div></div>
</div>

<div class="box box-info">
	<div class="box-header with-border"><h3 class="box-title">Langkah penagihan</h3></div>
	<div class="box-body">
		<ol>
			<li><strong>Validasi sistem</strong> — periksa seluruh nota pada periode (detail, PPN, voucher, jurnal, retur).
				<?php if ($reference) { ?>Terakhir: <?php echo html_escape($reference->created_at); ?> oleh <?php echo html_escape($reference->operator_name); ?>.<?php } else { ?><em>Belum pernah dijalankan.</em><?php } ?></li>
			<li><strong>Cek nota</strong> — scan barcode nota fisik dan cocokkan nominalnya dengan sistem.</li>
			<li><strong>Finalisasi</strong> — setelah semua nota cocok, tagihan dikunci sebagai snapshot.</li>
		</ol>
		<a class="btn btn-info" href="<?php echo site_url('main/sales_billing/scan/'.(int) $period->id); ?>"><i class="fa fa-barcode"></i> Buka halaman cek nota</a>
		<a class="btn btn-default" href="<?php echo site_url('main/sales_billing/validation/'.(int) $period->id); ?>"><i class="fa fa-list"></i> Hasil validasi</a>
		<a class="btn btn-warning" href="<?php echo site_url('main/sales_billing/revisions/'.(int) $period->id); ?>"><i class="fa fa-pencil"></i> Revisi &amp; approval nota bermasalah</a>
	</div>
</div>

<?php if ($is_manager) { ?>
<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Tindakan manajer</h3></div>
	<div class="box-body">
		<form method="post" action="<?php echo site_url('main/sales_billing/validate_sales/'.(int) $period->id); ?>" class="form-inline" style="margin-bottom:12px;">
			<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
			<input type="hidden" name="confirm_repeat" value="0">
			<div class="form-group"><label for="validation-mode">Validasi&nbsp;</label><select id="validation-mode" class="form-control" name="validation_mode"><option value="period">Seluruh periode</option><option value="cutoff">Sampai tanggal tertentu (tengah periode)</option><option value="week">Rentang mingguan</option><option value="nota">Satu nomor nota</option></select></div>
			<div class="form-group validation-mode-field" data-modes="week" style="display:none;"><label for="validation-start">&nbsp;Dari&nbsp;</label><input id="validation-start" class="form-control" type="date" name="start_date" min="<?php echo html_escape($period->start_date); ?>" max="<?php echo html_escape($period->end_date); ?>"></div>
			<div class="form-group validation-mode-field" data-modes="week cutoff" style="display:none;"><label for="validation-end">&nbsp;Sampai&nbsp;</label><input id="validation-end" class="form-control" type="date" name="end_date" min="<?php echo html_escape($period->start_date); ?>" max="<?php echo html_escape($period->end_date); ?>"></div>
			<div class="form-group validation-mode-field" data-modes="nota" style="display:none;"><label for="validation-nota">&nbsp;No. nota&nbsp;</label><input id="validation-nota" class="form-control" name="nota" maxlength="64"></div>
			<button class="btn btn-info" type="submit">Jalankan validasi</button>
		</form>
		<p class="help-block">Daftar nota untuk dicek diambil dari validasi "Seluruh periode" atau "Sampai tanggal" terakhir. Dengan "Sampai tanggal", nota setelah tanggal tersebut belum masuk daftar cek; saat generate akhir seluruh periode divalidasi ulang.</p>
		<form method="post" action="<?php echo site_url('main/sales_billing/finalize_period/'.(int) $period->id); ?>" onsubmit="return window.confirm('Validasi ulang seluruh periode dan buat snapshot tagihan final? Periode tidak dapat diedit setelah final.');">
			<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
			<button class="btn btn-success" type="submit">Generate &amp; finalisasi tagihan</button>
			<span class="help-block" style="display:inline;margin-left:8px;">Ditolak bila masih ada nota belum di-scan, atau nota bermasalah/beda yang belum direvisi dan disetujui.</span>
		</form>
	</div>
</div>
<?php } ?>
<?php } ?>

<?php if ($runs) { ?>
<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Riwayat validasi terbaru</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>Waktu</th><th>Mode</th><th>Rentang</th><th>Nota</th><th>Hasil</th><th>Dicek</th><th>Petugas</th><th></th></tr></thead>
			<tbody><?php foreach ($runs as $run) { ?><tr>
				<td><?php echo html_escape($run->created_at); ?></td><td><?php echo html_escape(ucfirst($run->validation_mode)); ?></td>
				<td><?php echo html_escape($run->start_date.' – '.$run->end_date); ?></td><td><?php echo html_escape($run->nota_filter ? $run->nota_filter : '—'); ?></td>
				<td><?php echo $run->result === 'passed' ? '<span class="label label-success">Lolos</span>' : '<span class="label label-danger">Perlu diperiksa</span>'; ?></td>
				<td><?php echo (int) $run->checked_count; ?></td><td><?php echo html_escape($run->operator_name); ?></td>
				<td><a class="btn btn-xs btn-default" href="<?php echo site_url('main/sales_billing/validation/'.(int) $period->id.'?run_id='.(int) $run->id); ?>">Rincian</a></td>
			</tr><?php } ?></tbody>
		</table>
	</div>
</div>
<?php } ?>

<?php if ($period->status === 'finalized') { ?>
<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Rekap untuk keuangan (Excel)</h3></div>
	<div class="box-body">
		<p class="help-block">Sheet 1: total per NPK · Sheet 2: detail penjualan · Sheet 3: ringkasan nota. Tiap metode pembayaran dibuat file terpisah.</p>
		<?php foreach ($methods as $method) { ?>
		<a class="btn <?php echo $method->slug === 'potong_gaji' ? 'btn-success' : 'btn-default'; ?>" style="margin:0 6px 6px 0;" href="<?php echo site_url('main/sales_billing/export_recap/'.(int) $period->id.'/'.rawurlencode($method->slug)); ?>"><i class="fa fa-file-excel-o"></i> <?php echo html_escape($method->label); ?> (<?php echo (int) $method->nota_count; ?> nota · <?php echo $rp($method->billable); ?>)</a>
		<?php } ?>
	</div>
</div>
<?php if ($employee_summaries) { ?>
<div class="box box-warning">
	<div class="box-header with-border"><h3 class="box-title">Pemeriksaan potong gaji per karyawan</h3></div>
	<div class="box-body">
		<p class="help-block">Over-limit membandingkan total bersih potong gaji pada snapshot dengan limit belanja yang tercatat saat ini. Status resign dibaca pada akhir periode tagihan. Ini hanya peringatan, tidak memotong simpanan.</p>
		<div class="table-responsive">
			<table class="table table-bordered table-striped">
				<thead><tr><th>NPK</th><th>Nama</th><th class="text-right">Total potong gaji</th><th class="text-right">Limit belanja saat ini</th><th>Status periode</th><th>Peringatan</th></tr></thead>
				<tbody>
				<?php foreach ($employee_summaries as $employee) { ?>
				<tr class="<?php echo ($employee->over_limit || strpos($employee->resign_status, 'Resign') === 0) ? 'danger' : ($employee->resign_status === 'Status karyawan tidak ditemukan' ? 'warning' : ''); ?>">
					<td><?php echo html_escape($employee->npk); ?></td>
					<td><?php echo html_escape($employee->employee_name ? $employee->employee_name : 'Karyawan tidak ditemukan'); ?></td>
					<td class="text-right"><?php echo $rp($employee->total_potong_gaji); ?></td>
					<td class="text-right"><?php echo $employee->limit_belanja === NULL ? '—' : $rp($employee->limit_belanja); ?></td>
					<td><?php echo html_escape($employee->resign_status); ?></td>
					<td>
						<?php if ($employee->over_limit) { ?><span class="label label-danger">Melebihi limit</span><?php } ?>
						<?php if (strpos($employee->resign_status, 'Resign') === 0) { ?><span class="label label-warning">Periksa status resign</span><?php } ?>
						<?php if ($employee->resign_status === 'Status karyawan tidak ditemukan') { ?><span class="label label-warning">Periksa data karyawan</span><?php } ?>
						<?php if ($employee->limit_belanja === NULL) { ?><span class="label label-default">Limit tidak tersedia</span><?php } ?>
						<?php if (!$employee->over_limit && strpos($employee->resign_status, 'Resign') !== 0 && $employee->limit_belanja !== NULL) { ?>—<?php } ?>
					</td>
				</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php } ?>
<div class="box box-success">
	<div class="box-header with-border"><h3 class="box-title">Snapshot tagihan final</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped" id="billing-snapshot-table">
			<thead><tr><th>No. nota</th><th>NPK</th><th>Nama</th><th>Tanggal</th><th class="text-right">Penjualan</th><th class="text-right">Retur disetujui</th><th class="text-right">Tertagih</th></tr></thead>
			<tbody><?php foreach ($items as $item) { ?><tr>
				<td><?php echo html_escape($item->nota); ?></td><td><?php echo html_escape($item->npk); ?></td>
				<td><?php echo html_escape($item->employee_name ? $item->employee_name : $item->current_employee_name); ?></td>
				<td><?php echo html_escape($item->transaction_date); ?></td>
				<td class="text-right" data-order="<?php echo (float) $item->total_sales; ?>"><?php echo $rp($item->total_sales); ?></td>
				<td class="text-right" data-order="<?php echo (float) $item->approved_returns; ?>"><?php echo $rp($item->approved_returns); ?></td>
				<td class="text-right" data-order="<?php echo (float) $item->billable_total; ?>"><?php echo $rp($item->billable_total); ?></td>
			</tr><?php } ?></tbody>
			<tfoot><tr><th colspan="6" class="text-right">Total tertagih</th><th class="text-right"><?php $grand_total = 0; foreach ($items as $item) { $grand_total += (float) $item->billable_total; } echo $rp($grand_total); ?></th></tr></tfoot>
		</table>
	</div>
</div>
<?php } ?>

<script>
(function () {
	var mode = document.getElementById('validation-mode');
	if (mode) {
		var updateFields = function () {
			Array.prototype.forEach.call(document.querySelectorAll('.validation-mode-field'), function (field) {
				var show = field.getAttribute('data-modes').split(' ').indexOf(mode.value) !== -1;
				field.style.display = show ? 'inline-block' : 'none';
				field.querySelector('input').required = show;
			});
		};
		mode.addEventListener('change', updateFields);
		updateFields();
		var form = mode.form;
		var periodStart = <?php echo json_encode($period->start_date); ?>;
		var periodEnd = <?php echo json_encode($period->end_date); ?>;
		var previous = <?php echo json_encode(array_map(function ($run) { return implode('|', array($run->validation_mode, $run->start_date, $run->end_date, (string) $run->nota_filter)); }, $runs)); ?>;
		form.addEventListener('submit', function (event) {
			var value = function (name) { var el = form.querySelector('[name="' + name + '"]'); return el ? el.value : ''; };
			var m = mode.value;
			var start = m === 'week' ? value('start_date') : periodStart;
			var end = m === 'period' ? periodEnd : value('end_date');
			var sig = [m, start, end, m === 'nota' ? value('nota') : ''].join('|');
			if (previous.indexOf(sig) !== -1) {
				if (!window.confirm('Validasi dengan pengaturan yang sama SUDAH pernah dilakukan. Ulangi hanya jika data nota sudah diperbaiki. Lanjutkan?')) {
					event.preventDefault();
					return;
				}
				form.querySelector('[name="confirm_repeat"]').value = '1';
			}
		});
	}
	function initTable() {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') { setTimeout(initTable, 100); return; }
		if (document.getElementById('billing-snapshot-table') && !jQuery.fn.DataTable.isDataTable('#billing-snapshot-table')) {
			jQuery('#billing-snapshot-table').DataTable({ pageLength: 25, language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: '_START_ - _END_ dari _TOTAL_', zeroRecords: 'Tidak ada data', paginate: { next: 'Berikut', previous: 'Sebelum' } } });
		}
	}
	initTable();
}());
</script>
