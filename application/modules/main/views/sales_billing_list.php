<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<?php if ($is_manager) { ?>
<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Buat periode tagihan</h3></div>
	<form method="post" action="<?php echo site_url('main/sales_billing/create_period'); ?>">
		<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
		<div class="box-body">
			<div class="row">
				<div class="col-sm-4 form-group"><label for="billing-period-name">Nama periode</label><input id="billing-period-name" class="form-control" name="period_name" maxlength="64" placeholder="Tagihan 21 Sep - 20 Okt 2026" required></div>
				<div class="col-sm-2 form-group"><label for="billing-start">Tanggal awal</label><input id="billing-start" class="form-control" type="date" name="start_date" required></div>
				<div class="col-sm-2 form-group"><label for="billing-end">Tanggal akhir</label><input id="billing-end" class="form-control" type="date" name="end_date" required></div>
				<div class="col-sm-2 form-group"><label for="legacy-period">Isi cepat (bulan tagihan)</label><input id="legacy-period" class="form-control" type="month"></div>
				<div class="col-sm-2 form-group"><label>&nbsp;</label><button id="legacy-fill" class="btn btn-default btn-block" type="button">Isi tanggal</button></div>
			</div>
			<p class="help-block">Pilih bulan tagihan lalu klik "Isi tanggal" untuk mengisi tanggal otomatis mengikuti pembukuan lama (umumnya 21–20; Januari mulai 17 Desember, Desember berakhir 16 Desember). Tanggal tetap bisa diubah manual. Periode tidak boleh tumpang tindih.</p>
			<button class="btn btn-primary" type="submit">Buat periode</button>
		</div>
	</form>
</div>
<?php } ?>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Periode tagihan</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-striped table-bordered" id="periods-table">
			<thead><tr><th>Periode</th><th>Tanggal awal</th><th>Tanggal akhir</th><th>Status</th><th>Dibuat oleh</th><th></th></tr></thead>
			<tbody>
			<?php foreach ($periods as $period) { ?>
			<tr>
				<td><?php echo html_escape($period->period_name); ?></td>
				<td><?php echo html_escape($period->start_date); ?></td>
				<td><?php echo html_escape($period->end_date); ?></td>
				<td><?php echo $period->status === 'finalized' ? '<span class="label label-success">Final</span>' : '<span class="label label-warning">Terbuka</span>'; ?></td>
				<td><?php echo html_escape($period->created_name); ?></td>
				<td><a class="btn btn-xs btn-primary" href="<?php echo site_url('main/sales_billing/detail/'.(int) $period->id); ?>">Buka</a></td>
			</tr>
			<?php } ?>
			<?php if (!$periods) { ?><tr><td colspan="6" class="text-muted">Belum ada periode tagihan.</td></tr><?php } ?>
			</tbody>
		</table>
	</div>
</div>

<script>
(function () {
	var legacyPeriod = document.getElementById('legacy-period');
	var legacyFill = document.getElementById('legacy-fill');
	var billingStart = document.getElementById('billing-start');
	var billingEnd = document.getElementById('billing-end');
	var billingName = document.getElementById('billing-period-name');
	
	if (!legacyPeriod || !legacyFill) { return; }
	
	function pad2(value) { return value < 10 ? '0' + value : String(value); }
	
	function getMonthName(month) {
		var months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
		return months[month] || '';
	}
	
	function generatePeriodName(startDate, endDate) {
		if (!startDate || !endDate) { return ''; }
		var start = new Date(startDate + 'T00:00:00');
		var end = new Date(endDate + 'T00:00:00');
		if (isNaN(start) || isNaN(end)) { return ''; }
		var startDay = start.getDate();
		var startMonth = getMonthName(start.getMonth() + 1);
		var endDay = end.getDate();
		var endMonth = getMonthName(end.getMonth() + 1);
		var year = end.getFullYear();
		return 'Tagihan ' + startDay + ' ' + startMonth + ' - ' + endDay + ' ' + endMonth + ' ' + year;
	}
	
	function updatePeriodName() {
		var name = generatePeriodName(billingStart.value, billingEnd.value);
		if (name) { billingName.value = name; }
	}
	
	legacyFill.addEventListener('click', function () {
		var parts = legacyPeriod.value.split('-');
		if (parts.length !== 2) {
			window.alert('Pilih bulan tagihan terlebih dahulu.');
			legacyPeriod.focus();
			return;
		}
		var year = Number(parts[0]), month = Number(parts[1]);
		var start, end;
		if (month === 1) {
			start = (year - 1) + '-12-17';
			end = year + '-01-20';
		} else if (month === 12) {
			start = year + '-11-21';
			end = year + '-12-16';
		} else {
			start = year + '-' + pad2(month - 1) + '-21';
			end = year + '-' + pad2(month) + '-20';
		}
		billingStart.value = start;
		billingEnd.value = end;
		updatePeriodName();
	});
	
	billingStart.addEventListener('change', updatePeriodName);
	billingEnd.addEventListener('change', updatePeriodName);
	
	function initPeriodsTable() {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
			setTimeout(initPeriodsTable, 100);
			return;
		}
		if (!jQuery.fn.DataTable.isDataTable('#periods-table')) {
			jQuery('#periods-table').DataTable({
				pageLength: 25,
				order: [[0, 'desc']],
				language: {
					search: 'Cari:',
					lengthMenu: 'Tampilkan _MENU_',
					info: '_START_ - _END_ dari _TOTAL_',
					zeroRecords: 'Tidak ada periode yang cocok',
					paginate: { next: 'Berikutnya', previous: 'Sebelumnya' }
				}
			});
		}
	}
	initPeriodsTable();
}());
</script>
