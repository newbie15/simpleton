<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $rp = function ($amount) { return 'Rp '.number_format((float) $amount, 0, ',', '.'); }; ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Filter pembayaran supplier</h3>
		<div class="box-tools pull-right"><a class="btn btn-xs btn-default" href="<?php echo site_url('main/petty_cash_new?tab=requests'); ?>">Ajukan pembayaran / transaksi</a></div>
	</div>
	<form method="get" action="<?php echo site_url('main/management_payments'); ?>">
		<div class="box-body"><div class="row">
			<div class="col-md-2 form-group"><label for="payment-status">Status</label><select id="payment-status" class="form-control" name="status"><option value="">Semua</option><?php foreach (array('pending' => 'Menunggu', 'posted' => 'Sudah dibayar', 'rejected' => 'Ditolak') as $key => $label) { ?><option value="<?php echo $key; ?>"<?php echo $filters['status'] === $key ? ' selected' : ''; ?>><?php echo $label; ?></option><?php } ?></select></div>
			<div class="col-md-3 form-group"><label for="payment-supplier">Supplier</label><input id="payment-supplier" class="form-control" name="supplier" maxlength="64" value="<?php echo html_escape($filters['supplier']); ?>"></div>
			<div class="col-md-2 form-group"><label for="payment-receipt">No. nota terima</label><input id="payment-receipt" class="form-control" name="receipt" maxlength="32" value="<?php echo html_escape($filters['receipt']); ?>"></div>
			<div class="col-md-2 form-group"><label for="payment-start">Dari tanggal</label><input id="payment-start" class="form-control" type="date" name="start_date" value="<?php echo html_escape($filters['start_date']); ?>"></div>
			<div class="col-md-2 form-group"><label for="payment-end">Sampai tanggal</label><input id="payment-end" class="form-control" type="date" name="end_date" value="<?php echo html_escape($filters['end_date']); ?>"></div>
			<div class="col-md-1 form-group"><label>&nbsp;</label><button class="btn btn-primary btn-block" type="submit">Cari</button></div>
		</div></div>
	</form>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Pembayaran berbasis nota penerimaan</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>Status</th><th>Tanggal</th><th>Supplier / Nota terima</th><th>Referensi</th><th>Uraian</th><th class="text-right">Pembayaran</th><th class="text-right">Sisa setelah pengajuan</th><th>Pengaju / approver</th><th>Aksi</th></tr></thead>
			<tbody>
			<?php foreach ($payments as $payment) { ?>
			<tr>
				<td><?php echo html_escape($payment->status === 'pending' ? 'Menunggu approval' : ($payment->status === 'posted' ? 'Sudah dibayar' : 'Ditolak')); ?></td>
				<td><?php echo html_escape($payment->transaction_date); ?></td>
				<td><strong><?php echo html_escape($payment->supplier_name); ?></strong><br><a href="<?php echo site_url('main/purchasing/receive/'.(int) $payment->purchase_order_id); ?>"><?php echo html_escape($payment->receipt_number); ?></a><?php if ($payment->supplier_document) { ?><br><small>Surat jalan: <?php echo html_escape($payment->supplier_document); ?></small><?php } ?></td>
				<td><?php echo html_escape($payment->evidence_reference); ?><?php if ($payment->proof_filename) { ?><br><a href="<?php echo site_url('main/petty_cash/proof/'.(int) $payment->id); ?>">Lihat bukti</a><?php } ?></td>
				<td><?php echo html_escape($payment->description); ?></td>
				<td class="text-right"><?php echo $rp($payment->amount); ?></td>
				<td class="text-right"><?php echo $rp($payment->outstanding_after_requests); ?></td>
				<td><?php echo html_escape($payment->creator_name); ?><?php if ($payment->approver_name) { ?><br><small>Oleh <?php echo html_escape($payment->approver_name); ?> · <?php echo html_escape($payment->approved_at); ?></small><?php } ?></td>
				<td>
				<?php if ($payment->status === 'pending' && (string) $payment->created_by !== (string) $this->ion_auth->get_user_id()) { ?>
					<form method="post" action="<?php echo site_url('main/petty_cash/approve/'.(int) $payment->id); ?>" style="margin-bottom:5px;" onsubmit="return window.confirm('Setujui pembayaran hutang supplier ini? Jurnal akan diposting dan saldo hutang berkurang.');">
						<input type="hidden" name="return_to" value="main/management_payments"><input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
						<button class="btn btn-xs btn-success" type="submit">Setujui &amp; posting</button>
					</form>
					<form method="post" action="<?php echo site_url('main/petty_cash/reject/'.(int) $payment->id); ?>">
						<input type="hidden" name="return_to" value="main/management_payments"><input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
						<div class="input-group input-group-sm"><input class="form-control" name="rejection_reason" maxlength="500" placeholder="Alasan penolakan" required><span class="input-group-btn"><button class="btn btn-danger" type="submit">Tolak</button></span></div>
					</form>
				<?php } elseif ($payment->status === 'pending') { ?><span class="text-muted">Pengaju tidak dapat menyetujui sendiri.</span>
				<?php } elseif ($payment->status === 'rejected' && $payment->rejection_reason) { ?><span class="text-danger"><?php echo html_escape($payment->rejection_reason); ?></span>
				<?php } else { ?><span class="text-muted">—</span><?php } ?>
				</td>
			</tr>
			<?php } ?>
			<?php if (!$payments) { ?><tr><td colspan="9" class="text-muted">Tidak ada pembayaran supplier untuk filter ini.</td></tr><?php } ?>
			</tbody>
		</table>
		<?php if (count($payments) === 500) { ?><p class="text-warning">Menampilkan 500 pembayaran terbaru.</p><?php } ?>
	</div>
</div>