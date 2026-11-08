<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<div class="box box-primary">
	<div class="box-header with-border">
		<h3 class="box-title"><i class="fa fa-user"></i> Buku Rekening Simpanan Anggota</h3>
		<div class="box-tools pull-right">
			<a class="btn btn-default btn-sm" href="<?= base_url('index.php/main/simpanan') ?>">
				<i class="fa fa-arrow-left"></i> Kembali ke Daftar
			</a>
			<a class="btn btn-success btn-sm" href="<?= base_url('index.php/main/simpanan/export_history/'.$member->id.'?jenis='.$jenis.'&start_date='.$start_date.'&end_date='.$end_date) ?>">
				<i class="fa fa-download"></i> Export CSV / Excel
			</a>
			<button class="btn btn-info btn-sm" onclick="window.print();">
				<i class="fa fa-print"></i> Cetak Rekening Koran
			</button>
		</div>
	</div>
	<div class="box-body">
		<div class="row">
			<div class="col-md-6">
				<table class="table table-condensed table-borderless">
					<tr>
						<th style="width: 140px;">Nama Anggota</th>
						<td>: <strong><?= htmlspecialchars($member->nama) ?></strong></td>
					</tr>
					<tr>
						<th>NPK</th>
						<td>: <?= htmlspecialchars($member->npk) ?></td>
					</tr>
					<tr>
						<th>Bagian / Divisi</th>
						<td>: <?= htmlspecialchars($member->bagian ? $member->bagian : '-') ?></td>
					</tr>
					<tr>
						<th>Status Keanggotaan</th>
						<td>: 
							<?php if ((int) $member->is_active === 1): ?>
								<span class="label label-success"><i class="fa fa-check"></i> Aktif</span>
							<?php else: ?>
								<span class="label label-danger"><i class="fa fa-times"></i> Resign (<?= htmlspecialchars($member->resign_date ? $member->resign_date : '') ?>)</span>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</div>
			<div class="col-md-6">
				<!-- Filter Box -->
				<div class="well well-sm" style="margin-bottom: 0;">
					<form method="get" action="" class="form-inline">
						<div class="form-group" style="margin-right: 8px;">
							<label style="font-size: 12px;">Jenis:</label><br>
							<select name="jenis" class="form-control input-sm">
								<option value="semua" <?= ($jenis === 'semua') ? 'selected' : '' ?>>Semua Simpanan</option>
								<option value="pokok" <?= ($jenis === 'pokok') ? 'selected' : '' ?>>Simpanan Pokok</option>
								<option value="wajib" <?= ($jenis === 'wajib') ? 'selected' : '' ?>>Simpanan Wajib</option>
								<option value="sukarela" <?= ($jenis === 'sukarela') ? 'selected' : '' ?>>Simpanan Sukarela</option>
							</select>
						</div>
						<div class="form-group" style="margin-right: 8px;">
							<label style="font-size: 12px;">Dari Tanggal:</label><br>
							<input type="date" name="start_date" class="form-control input-sm" value="<?= htmlspecialchars($start_date) ?>">
						</div>
						<div class="form-group" style="margin-right: 8px;">
							<label style="font-size: 12px;">Sampai Tanggal:</label><br>
							<input type="date" name="end_date" class="form-control input-sm" value="<?= htmlspecialchars($end_date) ?>">
						</div>
						<div class="form-group">
							<label>&nbsp;</label><br>
							<button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Stat Boxes Saldo Akumulasi -->
<div class="row" style="margin-bottom: 15px;">
	<div class="col-md-3">
		<div class="info-box bg-green">
			<span class="info-box-icon"><i class="fa fa-bank"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Saldo Simpanan Pokok</span>
				<span class="info-box-number">Rp <?= number_format($saldos['pokok'], 0, ',', '.') ?></span>
				<span class="progress-description">Akun 3101</span>
			</div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="info-box bg-yellow">
			<span class="info-box-icon"><i class="fa fa-calendar"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Saldo Simpanan Wajib</span>
				<span class="info-box-number">Rp <?= number_format($saldos['wajib'], 0, ',', '.') ?></span>
				<span class="progress-description">Akun 3102</span>
			</div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="info-box bg-purple">
			<span class="info-box-icon"><i class="fa fa-money"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Saldo Simpanan Sukarela</span>
				<span class="info-box-number">Rp <?= number_format($saldos['sukarela'], 0, ',', '.') ?></span>
				<span class="progress-description">Akun 2102</span>
			</div>
		</div>
	</div>
	<div class="col-md-3">
		<div class="info-box bg-blue">
			<span class="info-box-icon"><i class="fa fa-calculator"></i></span>
			<div class="info-box-content">
				<span class="info-box-text">Total Hak Simpanan</span>
				<span class="info-box-number">Rp <?= number_format($saldos['pokok'] + $saldos['wajib'] + $saldos['sukarela'], 0, ',', '.') ?></span>
				<span class="progress-description">Semua Simpanan</span>
			</div>
		</div>
	</div>
</div>

<div class="box box-info">
	<div class="box-header with-border">
		<h3 class="box-title"><i class="fa fa-list"></i> Riwayat Rincian Mutasi Transaksi</h3>
	</div>
	<div class="box-body table-responsive">
		<?php if (count($transactions) > 0): ?>
			<table class="table table-bordered table-striped table-hover data-table-history">
				<thead>
					<tr class="bg-gray-light">
						<th style="width: 130px;">Tanggal</th>
						<th>Nomor Bukti</th>
						<th>No. Jurnal Akuntansi</th>
						<th>Jenis</th>
						<th>Mutasi</th>
						<th class="text-right">Nominal</th>
						<th class="text-right">Saldo Sebelum</th>
						<th class="text-right">Saldo Sesudah</th>
						<th>Keterangan</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($transactions as $tx): 
						$mutasi_label = ucfirst($tx->jenis_mutasi);
						$badge_class = 'label-default';
						if ($tx->jenis_mutasi === 'setor') $badge_class = 'label-success';
						elseif ($tx->jenis_mutasi === 'tarik') $badge_class = 'label-danger';
						elseif ($tx->jenis_mutasi === 'bunga' || $tx->jenis_mutasi === 'shu') $badge_class = 'label-info';
						elseif ($tx->jenis_mutasi === 'koreksi') $badge_class = 'label-warning';
					?>
						<tr>
							<td><?= date('d-m-Y H:i', strtotime($tx->tgl_transaksi)) ?></td>
							<td><code><?= htmlspecialchars($tx->nomor_bukti) ?></code></td>
							<td>
								<?php if (!empty($tx->journal_number)): ?>
									<span class="label label-success" title="Terposting ke Akuntansi"><i class="fa fa-check"></i> <?= htmlspecialchars($tx->journal_number) ?></span>
								<?php else: ?>
									<span class="text-muted small">-</span>
								<?php endif; ?>
							</td>
							<td><span class="badge bg-gray"><?= ucfirst($tx->jenis_simpanan) ?></span></td>
							<td><span class="label <?= $badge_class ?>"><?= $mutasi_label ?></span></td>
							<td class="text-right">
								<span style="font-weight: bold; color: <?= ($tx->jenis_mutasi === 'tarik') ? '#dc3545' : '#28a745' ?>;">
									<?= ($tx->jenis_mutasi === 'tarik') ? '-' : '+' ?>
									Rp <?= number_format($tx->nominal, 0, ',', '.') ?>
								</span>
							</td>
							<td class="text-right">Rp <?= number_format($tx->saldo_sebelum, 0, ',', '.') ?></td>
							<td class="text-right" style="font-weight: bold;">Rp <?= number_format($tx->saldo_setelah, 0, ',', '.') ?></td>
							<td><?= $tx->keterangan ? htmlspecialchars($tx->keterangan) : '-' ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else: ?>
			<p class="text-center text-muted" style="padding: 30px;">Tidak ada transaksi simpanan yang cocok dengan kriteria filter.</p>
		<?php endif; ?>
	</div>
</div>

<script>
(function() {
	function initTable() {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') {
			setTimeout(initTable, 100);
			return;
		}
		jQuery('.data-table-history').each(function() {
			if (!jQuery.fn.DataTable.isDataTable(this)) {
				jQuery(this).DataTable({
					order: [[0, 'desc']],
					pageLength: 25,
					lengthMenu: [10, 25, 50, 100],
					language: {
						emptyTable: 'Tidak ada data transaksi',
						info: 'Menampilkan _START_ - _END_ dari _TOTAL_ transaksi',
						infoEmpty: 'Menampilkan 0 transaksi',
						infoFiltered: '(difilter dari _MAX_ total)',
						lengthMenu: 'Tampilkan _MENU_ baris',
						search: 'Cari transaksi:',
						paginate: { first: 'Awal', last: 'Akhir', next: 'Berikut', previous: 'Sebelum' },
						zeroRecords: 'Tidak ada data mutasi yang cocok.'
					}
				});
			}
		});
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initTable);
	} else {
		initTable();
	}
}());
</script>
