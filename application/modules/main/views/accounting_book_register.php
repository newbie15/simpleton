<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $rp = function ($value) { return 'Rp '.number_format((float) $value, 0, ',', '.'); }; ?>
<form class="form-inline" method="get" style="margin-bottom:15px;">
	<label>Bulan&nbsp;</label>
	<input type="month" name="month" class="form-control" value="<?php echo html_escape($month); ?>">
	<button class="btn btn-default" type="submit">Tampilkan</button>
	<a class="btn btn-link" href="<?php echo site_url('main/accounting_book?tab=dashboard&month='.rawurlencode($month)); ?>">&larr; Kembali ke Dashboard Buku Akun</a>
</form>
<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Register <?php echo html_escape($account->code.' '.$account->name); ?> (<?php echo html_escape($account->type); ?>)</h3></div>
	<div class="box-body no-padding">
		<table class="table table-condensed table-striped">
			<thead><tr><th>Tanggal</th><th>No. jurnal</th><th>Keterangan</th><th>Transfer dengan</th><th class="text-right">Debit</th><th class="text-right">Kredit</th><th class="text-right">Saldo</th></tr></thead>
			<tbody>
				<tr><td colspan="6"><em>Saldo awal</em></td><td class="text-right"><?php echo $rp($opening); ?></td></tr>
				<?php foreach ($entries as $e) { ?>
				<tr>
					<td><?php echo html_escape($e->date); ?></td>
					<td><?php echo html_escape($e->journal_number); ?></td>
					<td><?php echo html_escape($e->description); ?></td>
					<td><?php echo html_escape($e->counter); ?></td>
					<td class="text-right"><?php echo (float) $e->debit > 0 ? $rp($e->debit) : ''; ?></td>
					<td class="text-right"><?php echo (float) $e->credit > 0 ? $rp($e->credit) : ''; ?></td>
					<td class="text-right"><?php echo $rp($e->running); ?></td>
				</tr>
				<?php } ?>
				<?php if (!$entries) { ?><tr><td colspan="7" class="text-muted">Tidak ada transaksi pada bulan ini.</td></tr><?php } ?>
			</tbody>
			<tfoot><tr><th colspan="6">Saldo akhir</th><th class="text-right"><?php echo $rp($closing); ?></th></tr></tfoot>
		</table>
	</div>
</div>
