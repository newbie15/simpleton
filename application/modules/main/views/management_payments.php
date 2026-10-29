<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Filter pembayaran supplier</h3>
		<div class="box-tools pull-right"><a class="btn btn-success btn-sm" href="<?php echo site_url('main/management_payments/request'); ?>"><i class="fa fa-money" aria-hidden="true"></i> Ajukan pembayaran</a></div>
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
		<table id="management-payments-table" class="table table-bordered table-striped"
			data-source="<?php echo html_escape(site_url('main/management_payments/payments_data')); ?>"
			data-receive-url="<?php echo html_escape(site_url('main/purchasing/receive')); ?>"
			data-proof-url="<?php echo html_escape(site_url('main/petty_cash/proof')); ?>">
			<thead><tr><th>Status</th><th>Tanggal</th><th>Supplier / Nota terima</th><th>Referensi</th><th>Uraian</th><th class="text-right">Pembayaran</th><th class="text-right">Sisa setelah pengajuan</th><th>Pengaju / approver</th><th>Aksi</th></tr></thead>
			<tbody></tbody>
		</table>
	</div>
</div>

<div class="box box-warning">
	<div class="box-header with-border">
		<h3 class="box-title">PO ditutup yang masih memiliki hutang</h3>
	</div>
	<div class="box-body">
		<p class="help-block">PO yang sudah ditutup tetap dapat dibayar untuk nota penerimaan yang sudah tercatat. Penerimaan barang baru tetap terkunci.</p>
		<div class="table-responsive">
			<table id="closed-po-payments-table" class="table table-bordered table-striped"
				data-source="<?php echo html_escape(site_url('main/management_payments/closed_po_data')); ?>"
				data-receive-url="<?php echo html_escape(site_url('main/purchasing/receive')); ?>"
				data-request-url="<?php echo html_escape(site_url('main/management_payments/request')); ?>">
				<thead><tr><th>No. PO / Nota terima</th><th>Supplier</th><th>Tanggal terima</th><th class="text-right">Sisa hutang</th><th>Aksi</th></tr></thead>
				<tbody></tbody>
			</table>
		</div>
	</div>
</div>