<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $format_rupiah = function ($amount) { return 'Rp '.number_format((float) $amount, 0, ',', '.'); }; ?>
<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Preview <?php echo html_escape($return->nomor_retur); ?></h3></div>
	<div class="box-body">
		<dl class="dl-horizontal">
			<dt>Status</dt><dd><?php echo html_escape(ucfirst($return->status)); ?></dd>
			<dt>No. penerimaan</dt><dd><?php echo html_escape($return->receipt_number); ?></dd>
			<dt>Tanggal diterima</dt><dd><?php echo html_escape($return->received_at); ?></dd>
			<dt>Surat jalan</dt><dd><?php echo html_escape($return->supplier_document ? $return->supplier_document : '—'); ?></dd>
			<dt>Supplier</dt><dd><?php echo html_escape($return->supplier_name); ?></dd>
			<dt>Tanggal retur</dt><dd><?php echo html_escape($return->tgl_retur); ?></dd>
			<dt>Alasan</dt><dd><?php echo nl2br(html_escape($return->alasan)); ?></dd>
		</dl>
		<div class="table-responsive">
			<table class="table table-bordered table-striped">
				<thead><tr><th>Barcode</th><th>Barang</th><th class="text-right">Diterima</th><th class="text-right">Retur sebelumnya</th><th class="text-right">Qty draft</th><th class="text-right">Sisa setelah retur</th><th class="text-right">Harga beli</th><th class="text-right">Subtotal</th><th>Alasan detail</th></tr></thead>
				<tbody>
				<?php foreach ($items as $item) { ?>
				<tr>
					<td><?php echo html_escape($item->barcode); ?></td>
					<td><?php echo html_escape($item->product_name); ?></td>
					<td class="text-right"><?php echo number_format($item->qty_received); ?></td>
					<td class="text-right"><?php echo number_format($item->qty_returned_before); ?></td>
					<td class="text-right"><?php echo number_format($item->qty_retur); ?></td>
					<td class="text-right"><?php echo number_format($item->qty_remaining_after); ?></td>
					<td class="text-right"><?php echo $format_rupiah($item->harga_beli); ?></td>
					<td class="text-right"><?php echo $format_rupiah($item->subtotal); ?></td>
					<td><?php echo html_escape($item->alasan_detail ? $item->alasan_detail : '—'); ?></td>
				</tr>
				<?php } ?>
				<?php if (!$items) { ?><tr><td colspan="9" class="text-muted">Draft belum memiliki rincian barang.</td></tr><?php } ?>
				</tbody>
				<tfoot><tr><th colspan="7" class="text-right">Total retur</th><th class="text-right"><?php echo $format_rupiah($return->total_nominal); ?></th><th></th></tr></tfoot>
			</table>
		</div>
	</div>
	<div class="box-footer">
		<a class="btn btn-default" href="<?php echo site_url('main/supplier_return'); ?>">Kembali</a>
		<?php if ($return->status === 'draft' && $can_approve) { ?>
		<form method="post" action="<?php echo site_url('main/supplier_return/approve/'.(int) $return->id); ?>" style="display:inline-block;margin-left:6px;" onsubmit="return window.confirm('Setujui retur ini? Stok akan dikurangi dan retur tidak dapat dibatalkan.');">
			<button class="btn btn-danger" type="submit">Setujui &amp; Finalisasi</button>
		</form>
		<?php } ?>
	</div>
</div>