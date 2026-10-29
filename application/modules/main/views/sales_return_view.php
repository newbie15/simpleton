<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$status = $return->status === 'completed' ? 'approved' : $return->status;
$status_labels = array(
	'pending' => 'Menunggu persetujuan',
	'approved' => 'Disetujui',
	'rejected' => 'Ditolak',
	'cancelled' => 'Dibatalkan'
);
?>

<div class="box box-info">
	<div class="box-header with-border">
		<h3 class="box-title">Informasi pengajuan</h3>
		<div class="box-tools pull-right">
			<a class="btn btn-default btn-sm" href="<?php echo site_url('main/sales_return'); ?>">
				<i class="fa fa-arrow-left" aria-hidden="true"></i> Kembali
			</a>
		</div>
	</div>
	<div class="box-body">
		<dl class="dl-horizontal">
			<dt>No. retur</dt><dd><?php echo html_escape($return->nomor_retur); ?></dd>
			<dt>No. nota</dt><dd><?php echo html_escape($return->nota_penjualan); ?></dd>
			<dt>Tanggal</dt><dd><?php echo html_escape($return->tgl_retur); ?></dd>
			<dt>Pengaju</dt><dd><?php echo html_escape($return->id_user); ?></dd>
			<dt>Status</dt><dd><?php echo html_escape(isset($status_labels[$status]) ? $status_labels[$status] : $status); ?></dd>
			<dt>Total retur</dt><dd>Rp <?php echo number_format((float) $return->total_retur, 0, ',', '.'); ?></dd>
			<dt>Alasan</dt><dd><?php echo nl2br(html_escape($return->alasan)); ?></dd>
			<?php if ($return->approved_by) { ?>
				<dt>Disetujui oleh</dt><dd><?php echo html_escape($return->approved_by); ?></dd>
			<?php } ?>
			<?php if ($return->rejected_by) { ?>
				<dt>Ditolak oleh</dt><dd><?php echo html_escape($return->rejected_by); ?></dd>
				<dt>Alasan penolakan</dt><dd><?php echo nl2br(html_escape($return->rejection_reason)); ?></dd>
			<?php } ?>
		</dl>
	</div>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Barang yang diretur</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead>
				<tr>
					<th>Barang</th>
					<th>Barcode</th>
					<th class="text-right">Qty retur</th>
					<th>Kondisi</th>
					<th>Kembali ke stok</th>
					<th class="text-right">Harga satuan</th>
					<th class="text-right">Subtotal</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($items as $item) { ?>
					<tr>
						<td><?php echo html_escape($item->product_name ? $item->product_name : $item->barcode); ?></td>
						<td><?php echo html_escape($item->barcode); ?></td>
						<td class="text-right"><?php echo (int) $item->qty_retur; ?></td>
						<td><?php echo html_escape($item->kondisi === 'layak_jual' ? 'Layak jual' : 'Rusak'); ?></td>
						<td><?php echo (int) $item->kembali_ke_stok === 1 ? 'Ya' : 'Tidak'; ?></td>
						<td class="text-right">Rp <?php echo number_format((float) $item->harga_satuan, 0, ',', '.'); ?></td>
						<td class="text-right">Rp <?php echo number_format((float) $item->subtotal, 0, ',', '.'); ?></td>
					</tr>
				<?php } ?>
				<?php if (empty($items)) { ?>
					<tr><td colspan="7" class="text-center">Detail barang retur tidak ditemukan.</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>
