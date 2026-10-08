<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>

<?php if ($can_create) { ?>
	<div class="box box-primary">
		<div class="box-header with-border"><h3 class="box-title">Pilih penerimaan untuk retur</h3></div>
		<div class="box-body table-responsive">
			<table class="table table-bordered table-striped">
				<thead><tr><th>No. penerimaan</th><th>Tanggal</th><th>Surat jalan</th><th>Supplier</th><th>Qty diterima</th><th>Aksi</th></tr></thead>
				<tbody>
					<?php foreach ($receipts as $receipt) { ?>
						<tr>
							<td><?php echo html_escape($receipt->receipt_number); ?></td>
							<td><?php echo html_escape($receipt->received_at); ?></td>
							<td><?php echo $receipt->supplier_document !== NULL && $receipt->supplier_document !== '' ? html_escape($receipt->supplier_document) : '-'; ?></td>
							<td><?php echo html_escape($receipt->supplier_name); ?></td>
							<td><?php echo (int) $receipt->total_qty; ?></td>
							<td>
								<?php if ((int) $receipt->total_qty > 0) { ?>
									<a class="btn btn-xs btn-primary" href="<?php echo site_url('main/supplier_return/create/'.(int) $receipt->id); ?>">Buat draft retur</a>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
					<?php if (count($receipts) === 0) { ?>
						<tr><td colspan="6" class="text-center">Belum ada penerimaan barang.</td></tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
<?php } ?>

<div class="box box-warning">
	<div class="box-header with-border"><h3 class="box-title">Draft Menunggu Persetujuan</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>No. retur</th><th>Penerimaan</th><th>Supplier</th><th>Tanggal</th><th>Total</th><th>Diajukan oleh</th><th>Aksi</th></tr></thead>
			<tbody>
				<?php foreach ($drafts as $draft) { ?>
					<tr>
						<td><?php echo html_escape($draft->nomor_retur); ?></td>
						<td><?php echo html_escape($draft->receipt_number); ?></td>
						<td><?php echo html_escape($draft->supplier_name); ?></td>
						<td><?php echo html_escape($draft->tgl_retur); ?></td>
						<td>Rp <?php echo number_format((float) $draft->total_nominal, 0, ',', '.'); ?></td>
						<td><?php echo html_escape($draft->id_user); ?></td>
						<td>
							<?php if ($can_approve) { ?>
								<form method="post" action="<?php echo site_url('main/supplier_return/approve/'.(int) $draft->id); ?>" onsubmit="return window.confirm('Setujui retur ini? Stok akan dikurangi dan retur tidak dapat dibatalkan.');">
									<button class="btn btn-xs btn-danger" type="submit">Setujui &amp; Finalisasi</button>
								</form>
							<?php } else { ?>
								<span class="text-muted">Menunggu admin/pengurus</span>
							<?php } ?>
						</td>
					</tr>
				<?php } ?>
				<?php if (count($drafts) === 0) { ?>
					<tr><td colspan="7" class="text-center">Tidak ada draft yang menunggu persetujuan.</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Riwayat Retur Final</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>No. retur</th><th>Penerimaan</th><th>Supplier</th><th>Tanggal</th><th>Total</th><th>Petugas</th></tr></thead>
			<tbody>
				<?php foreach ($history as $return) { ?>
					<tr>
						<td><?php echo html_escape($return->nomor_retur); ?></td>
						<td><?php echo html_escape($return->receipt_number); ?></td>
						<td><?php echo html_escape($return->supplier_name); ?></td>
						<td><?php echo html_escape($return->tgl_retur); ?></td>
						<td>Rp <?php echo number_format((float) $return->total_nominal, 0, ',', '.'); ?></td>
						<td><?php echo html_escape($return->id_user); ?></td>
					</tr>
				<?php } ?>
				<?php if (count($history) === 0) { ?>
					<tr><td colspan="6" class="text-center">Belum ada retur supplier yang difinalisasi.</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
