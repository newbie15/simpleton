<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $rp = function ($value) { return 'Rp '.number_format((float) $value, 0, ',', '.'); }; ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<p><a href="<?php echo site_url('main/sales_billing/detail/'.(int) $period->id); ?>"><i class="fa fa-arrow-left"></i> Kembali ke <?php echo html_escape($period->period_name); ?></a></p>

<div class="alert alert-info">
	Nota yang bermasalah (hasil validasi) atau nominal fisik berbeda dengan sistem dapat direvisi: pilih mana yang benar dan beri keterangan.
	Revisi harus <strong>disetujui manajer</strong> (bukan pembuat revisi) sebelum periode dapat digenerate. Jika data sistem berubah setelah disetujui, revisi perlu diajukan ulang.
</div>

<?php if (!$reference) { ?>
<div class="alert alert-warning">Belum ada validasi periode/sampai tanggal. Jalankan validasi terlebih dahulu.</div>
<?php } elseif (empty($items)) { ?>
<div class="alert alert-success">Tidak ada nota bermasalah.</div>
<?php } else { ?>
<div class="box box-warning">
	<div class="box-body table-responsive">
		<table class="table table-bordered table-striped">
			<thead><tr><th>Nota</th><th>NPK / Nama</th><th>Masalah</th><th class="text-right">Total sistem</th><th class="text-right">Total nota fisik</th><th>Revisi</th><th style="min-width:300px;">Ajukan revisi</th></tr></thead>
			<tbody>
			<?php foreach ($items as $item) {
				$stale = $item->adj_id && abs((float) $item->adj_system_total - (float) $item->billable_total) >= 0.01;
				$issue = $item->validation_status === 'invalid' ? $item->issues
					: ($item->scan_status !== NULL ? 'Nominal nota fisik berbeda dengan sistem (lihat rincian retur/voucher di bawah).' : '');
				$editable = $period->status === 'open'; ?>
			<tr>
				<td><?php echo html_escape($item->nota); ?></td>
				<td><?php echo html_escape($item->npk.' '.$item->member_name); ?></td>
				<td><?php echo html_escape($issue ? $issue : '—'); ?></td>
				<td class="text-right"><?php echo $rp($item->billable_total); ?></td>
				<td class="text-right"><?php echo $item->nota_total === NULL ? '—' : $rp($item->nota_total); ?></td>
				<td>
					<?php if ($item->adj_id) { ?>
						<?php $labels = array('pending' => 'label-warning', 'approved' => 'label-success', 'rejected' => 'label-danger'); ?>
						<span class="label <?php echo $labels[$item->adj_status]; ?>"><?php echo html_escape(ucfirst($item->adj_status)); ?></span>
						<?php if ($stale) { ?><span class="label label-default" title="Total sistem berubah sejak revisi">Kedaluwarsa</span><?php } ?>
						<div><small>Benar: <strong><?php echo $item->correct_source === 'physical' ? 'nota fisik' : 'sistem'; ?></strong> → <?php echo $rp($item->correct_total); ?></small></div>
						<div><small><?php echo html_escape($item->adj_note); ?></small></div>
						<div><small class="text-muted">Oleh <?php echo html_escape($item->reviser_name); ?>, <?php echo html_escape($item->revised_at); ?><?php if ($item->decided_at) { ?> · diputuskan <?php echo html_escape($item->decider_name.', '.$item->decided_at); ?><?php } ?></small></div>
						<?php if ($is_manager && $editable && $item->adj_status === 'pending') { ?>
						<form method="post" action="<?php echo site_url('main/sales_billing/decide_adjustment/'.(int) $period->id); ?>" style="margin-top:4px;">
							<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
							<input type="hidden" name="adjustment_id" value="<?php echo (int) $item->adj_id; ?>">
							<button class="btn btn-xs btn-success" name="decision" value="approved" type="submit">Setujui</button>
							<button class="btn btn-xs btn-danger" name="decision" value="rejected" type="submit">Tolak</button>
						</form>
						<?php } ?>
					<?php } else { ?>—<?php } ?>
				</td>
				<td>
					<?php if ($editable) { ?>
					<form method="post" action="<?php echo site_url('main/sales_billing/revise_nota/'.(int) $period->id); ?>">
						<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
						<input type="hidden" name="nota" value="<?php echo html_escape($item->nota); ?>">
						<div><label class="radio-inline"><input type="radio" name="correct_source" value="system" checked> Sistem benar</label>
						<label class="radio-inline"><input type="radio" name="correct_source" value="physical"> Nota fisik benar</label></div>
						<input class="form-control input-sm" style="margin:4px 0;" name="correct_total" placeholder="Nominal nota fisik (jika fisik benar)" value="<?php echo $item->nota_total === NULL ? '' : html_escape($item->nota_total); ?>">
						<input class="form-control input-sm" style="margin-bottom:4px;" name="note" maxlength="500" required placeholder="Keterangan / alasan">
						<button class="btn btn-xs btn-primary" type="submit">Ajukan revisi</button>
					</form>
					<?php } ?>
				</td>
			</tr>
			<?php $d = isset($details[$item->nota]) ? $details[$item->nota] : NULL; if ($d) { ?>
			<tr>
				<td colspan="7" style="background:#fafafa;">
					<div class="row">
						<div class="col-md-5">
							<strong>Cara sistem menghitung</strong>
							<table class="table table-condensed" style="margin:4px 0;">
								<tr><td>Penjualan barang (termasuk PPN)</td><td class="text-right"><?php echo $rp($d['gross']); ?></td></tr>
								<tr><td>Voucher<?php echo $d['voucher_code'] ? ' ('.html_escape($d['voucher_code']).')' : ''; ?></td><td class="text-right text-red">− <?php echo $rp($d['voucher']); ?></td></tr>
								<tr><td><em>Total bayar pelanggan (struk)</em></td><td class="text-right"><em><?php echo $rp($d['sales_due']); ?></em></td></tr>
								<tr><td>Retur disetujui &amp; dihitung periode ini</td><td class="text-right text-red">− <?php echo $rp($d['returns_counted']); ?></td></tr>
								<tr><th>Total sistem (tertagih)</th><th class="text-right"><?php echo $rp($item->billable_total); ?></th></tr>
							</table>
							<small class="text-muted">Metode bayar: <?php echo html_escape($d['payment_method'] ? $d['payment_method'] : 'tidak tercatat'); ?>.
							Nota fisik biasanya dicetak <u>sebelum</u> retur, jadi selisih sebesar retur wajar terjadi — periksa tabel retur di samping sebelum memilih mana yang benar.</small>
						</div>
						<div class="col-md-7">
							<strong>Retur untuk nota ini</strong>
							<?php if (empty($d['returns'])) { ?>
								<p class="text-muted" style="margin:4px 0;">Tidak ada retur.</p>
							<?php } else { foreach ($d['returns'] as $return) { ?>
								<div style="margin:4px 0;padding:6px;border:1px solid #ddd;background:#fff;">
									<span class="label <?php echo $return->counted ? 'label-success' : 'label-default'; ?>"><?php echo $return->counted ? 'Dihitung' : 'Tidak dihitung'; ?></span>
									<strong><?php echo html_escape($return->nomor_retur); ?></strong> · <?php echo html_escape($return->status); ?>
									· diajukan <?php echo html_escape($return->tgl_retur); ?><?php if ($return->approved_at) { ?> · disetujui <?php echo html_escape($return->approved_at); ?><?php } ?>
									· <?php echo $rp($return->total_retur); ?>
									<?php if ($return->alasan) { ?><div><small>Alasan: <?php echo html_escape($return->alasan); ?></small></div><?php } ?>
									<?php if ($return->rejection_reason) { ?><div><small>Ditolak: <?php echo html_escape($return->rejection_reason); ?></small></div><?php } ?>
									<?php if ($return->reason_not_counted) { ?><div class="text-orange"><small><?php echo html_escape($return->reason_not_counted); ?></small></div><?php } ?>
									<table class="table table-condensed" style="margin:4px 0 0;">
										<?php foreach ($return->items as $r_item) { ?><tr><td><?php echo html_escape($r_item->barang ? $r_item->barang : $r_item->barcode); ?></td><td class="text-right"><?php echo (int) $r_item->qty_retur; ?> × <?php echo $rp($r_item->harga_satuan); ?></td><td class="text-right"><?php echo $rp($r_item->subtotal); ?></td></tr><?php } ?>
									</table>
								</div>
							<?php } } ?>
						</div>
					</div>
					<details>
						<summary>Rincian barang nota (<?php echo count($d['lines']); ?> baris)</summary>
						<table class="table table-condensed" style="margin:4px 0;">
							<thead><tr><th>Barang</th><th class="text-right">Qty</th><th class="text-right">Harga</th><th class="text-right">Jumlah</th><th class="text-right">PPN</th></tr></thead>
							<tbody><?php foreach ($d['lines'] as $line) { ?><tr><td><?php echo html_escape($line->barang ? $line->barang : $line->kode); ?></td><td class="text-right"><?php echo (int) $line->qty; ?></td><td class="text-right"><?php echo $rp($line->harga); ?></td><td class="text-right"><?php echo $rp($line->jumlah); ?></td><td class="text-right"><?php echo $rp($line->ppn_total); ?></td></tr><?php } ?></tbody>
						</table>
					</details>
				</td>
			</tr>
			<?php } ?>
			<?php } ?>
			</tbody>
		</table>
	</div>
</div>
<?php } ?>
