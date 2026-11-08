<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $rp = function ($value) { return 'Rp '.number_format((float) $value, 0, ',', '.'); }; ?>

<p><a href="<?php echo site_url('main/sales_billing/detail/'.(int) $period->id); ?>"><i class="fa fa-arrow-left"></i> Kembali ke detail periode</a></p>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Hasil validasi · <?php echo html_escape($period->period_name); ?></h3></div>
	<div class="box-body">
		<?php if (!$run) { ?>
			<p class="text-muted">Belum ada validasi untuk periode ini.</p>
		<?php } else { ?>
		<dl class="dl-horizontal">
			<dt>Waktu</dt><dd><?php echo html_escape($run->created_at); ?></dd>
			<dt>Petugas</dt><dd><?php echo html_escape($run->operator_name); ?></dd>
			<dt>Mode</dt><dd><?php echo html_escape(ucfirst($run->validation_mode)); ?> (<?php echo html_escape($run->start_date.' – '.$run->end_date); ?>)</dd>
			<dt>Hasil</dt><dd><?php echo $run->result === 'passed' ? '<span class="label label-success">Lolos</span>' : '<span class="label label-danger">Perlu diperiksa</span>'; ?> · <?php echo (int) $run->checked_count; ?> nota dicek</dd>
		</dl>
		<?php $base = 'main/sales_billing/validation/'.(int) $period->id.'?run_id='.(int) $run->id; ?>
		<div class="btn-group">
			<a class="btn btn-sm <?php echo $only_issues ? 'btn-primary' : 'btn-default'; ?>" href="<?php echo site_url($base); ?>">Hanya bermasalah</a>
			<a class="btn btn-sm <?php echo $only_issues ? 'btn-default' : 'btn-primary'; ?>" href="<?php echo site_url($base.'&all=1'); ?>">Semua nota</a>
		</div>
		<?php } ?>
	</div>
</div>

<?php if ($run) { ?>
<div class="box box-warning">
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped" id="validation-table">
			<thead><tr><th>Status</th><th>No. nota</th><th>NPK</th><th>Tanggal</th><th class="text-right">Penjualan</th><th class="text-right">Retur approved</th><th class="text-right">Tertagih</th><th>Temuan</th></tr></thead>
			<tbody><?php foreach ($items as $item) { ?><tr>
				<td><?php echo $item->status === 'valid' ? '<span class="label label-success">Valid</span>' : '<span class="label label-danger">Perlu diperiksa</span>'; ?></td>
				<td><?php echo html_escape($item->nota); ?></td><td><?php echo html_escape($item->npk); ?></td>
				<td><?php echo html_escape($item->transaction_date); ?></td>
				<td class="text-right" data-order="<?php echo (float) $item->total_sales; ?>"><?php echo $rp($item->total_sales); ?></td>
				<td class="text-right" data-order="<?php echo (float) $item->approved_returns; ?>"><?php echo $rp($item->approved_returns); ?></td>
				<td class="text-right" data-order="<?php echo (float) $item->billable_total; ?>"><?php echo $rp($item->billable_total); ?></td>
				<td><?php echo html_escape($item->issues ? $item->issues : '—'); ?></td>
			</tr><?php } ?></tbody>
		</table>
		<?php if (count($items) === 2000) { ?><p class="text-warning">Menampilkan 2000 nota pertama.</p><?php } ?>
	</div>
</div>
<?php } ?>

<script>
(function () {
	function initTable() {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') { setTimeout(initTable, 100); return; }
		if (document.getElementById('validation-table') && !jQuery.fn.DataTable.isDataTable('#validation-table')) {
			jQuery('#validation-table').DataTable({ pageLength: 25, order: [], language: { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: '_START_ - _END_ dari _TOTAL_', emptyTable: 'Tidak ada nota bermasalah', zeroRecords: 'Tidak ada data', paginate: { next: 'Berikut', previous: 'Sebelum' } } });
		}
	}
	initTable();
}());
</script>
