<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$json_accounts = json_encode($accounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<style>
	.grid-workspace .jexcel_content { max-width: 100%; }
	.grid-workspace .help-block { margin-top: 12px; }
</style>

<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>
<div class="alert alert-info">
	<strong>Input ringkas:</strong> satu baris untuk satu transaksi. Pemasukan berarti isi ulang dari Kas Utama (1106); pengeluaran memilih akun beban. Transaksi dikirim untuk persetujuan seperti biasa—belum mengubah saldo atau membuat jurnal. Lampiran bukti tidak diunggah di grid ini; isi kolom referensi bukti dan gunakan tampilan transaksi untuk meninjau/menyetujui.
</div>

<div class="box box-primary grid-workspace">
	<div class="box-header with-border">
		<h3 class="box-title">Petty cash seperti spreadsheet</h3>
		<div class="box-tools pull-right">
			<a class="btn btn-xs btn-default" href="<?php echo site_url('main/petty_cash_new?tab=requests'); ?>">Petty Cash V2 · persetujuan</a>
			<a class="btn btn-xs btn-default" href="<?php echo site_url('main/petty_cash'); ?>">Tampilan lama</a>
		</div>
	</div>
	<div class="box-body">
		<?php if (empty($funds)) { ?>
			<div class="alert alert-warning">Belum ada dana petty cash aktif. Pengurus dapat menyiapkan dana pada <a href="<?php echo site_url('main/petty_cash_new?tab=requests'); ?>">Petty Cash V2</a>.</div>
		<?php } else { ?>
			<form method="post" action="<?php echo site_url('main/petty_cash_grid/submit_rows'); ?>" id="petty-cash-grid-form">
				<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
				<input type="hidden" name="rows" id="petty-cash-grid-rows">
				<div class="form-group">
					<label for="petty-cash-fund">Dana</label>
					<select class="form-control" id="petty-cash-fund" name="fund_id" required style="max-width:460px;">
						<?php foreach ($funds as $fund) { ?>
							<option value="<?php echo (int) $fund->id; ?>"<?php echo $selected_fund && (int) $selected_fund->id === (int) $fund->id ? ' selected' : ''; ?>>
								<?php echo html_escape($fund->fund_code.' — '.$fund->name.' (saldo Rp '.number_format($fund->balance, 2, ',', '.').')'); ?>
							</option>
						<?php } ?>
					</select>
					<?php if ($selected_fund) { ?>
						<p class="help-block">Pemegang: <?php echo html_escape($selected_fund->custodian_username ? $selected_fund->custodian_username : 'belum ditetapkan'); ?> · Batas imprest: Rp <?php echo number_format((float) $selected_fund->imprest_limit, 2, ',', '.'); ?></p>
					<?php } ?>
				</div>
				<p class="help-block">Klik sel lalu ketik. Enter atau tombol panah untuk berpindah; kolom akun berisi akun beban dan sumber isi dana yang sesuai. Kosongkan baris yang tidak dipakai; maksimal 50 transaksi per batch.</p>
				<div id="petty-cash-grid"></div>
				<div class="form-group" style="margin-top:15px;">
					<button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane"></i> Kirim transaksi</button>
					<a class="btn btn-default" href="<?php echo site_url('main/petty_cash_new?tab=requests'); ?>">Buka transaksi & persetujuan</a>
				</div>
			</form>
		<?php } ?>
	</div>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Menunggu persetujuan (8 terbaru)</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-striped">
			<thead><tr><th>No. Transaksi</th><th>Tanggal</th><th>Dana</th><th>Jenis</th><th>Keterangan</th><th class="text-right">Nominal</th></tr></thead>
			<tbody>
			<?php foreach ($pending as $transaction) { ?>
				<tr>
					<td><?php echo html_escape($transaction->transaction_number); ?></td>
					<td><?php echo html_escape($transaction->transaction_date); ?></td>
					<td><?php echo html_escape($transaction->fund_code); ?></td>
					<td><?php echo $transaction->transaction_type === 'funding' ? 'Pemasukan / isi dana' : 'Pengeluaran'; ?></td>
					<td><?php echo html_escape($transaction->description); ?></td>
					<td class="text-right">Rp <?php echo number_format((float) $transaction->amount, 2, ',', '.'); ?></td>
				</tr>
			<?php } ?>
			<?php if (empty($pending)) { ?><tr><td colspan="6" class="text-muted">Belum ada transaksi pending.</td></tr><?php } ?>
			</tbody>
		</table>
	</div>
</div>

<?php if (!empty($funds)) { ?>
<script>
(function () {
	var accountOptions = <?php echo $json_accounts ? $json_accounts : '[]'; ?>;
	var rows = [];
	for (var i = 0; i < 10; i++) {
		rows.push(['', '', '', '', '', '']);
	}
	var grid = jexcel(document.getElementById('petty-cash-grid'), {
		data: rows,
		columns: [
			{ type: 'text', title: 'Tanggal (YYYY-MM-DD)', width: 155 },
			{ type: 'dropdown', title: 'Jenis', width: 170, source: [
				{ id: 'expense', name: 'Pengeluaran' },
				{ id: 'funding', name: 'Pemasukan / isi dana' }
			] },
			{ type: 'dropdown', title: 'Kategori / akun', width: 280, source: accountOptions },
			{ type: 'text', title: 'Keterangan', width: 260 },
			{ type: 'numeric', title: 'Nominal', width: 140 },
			{ type: 'text', title: 'Referensi bukti', width: 180 }
		],
		minDimensions: [6, 10],
		allowInsertRow: true,
		allowManualInsertRow: true,
		allowDeleteRow: true,
		tableOverflow: true,
		tableHeight: '480px'
	});
	document.getElementById('petty-cash-grid-form').addEventListener('submit', function (event) {
		var data = grid.getData();
		var filled = data.filter(function (row) {
			return row.some(function (value) { return String(value == null ? '' : value).trim() !== ''; });
		});
		if (!filled.length) {
			event.preventDefault();
			window.alert('Isi sedikitnya satu baris transaksi.');
			return;
		}
		document.getElementById('petty-cash-grid-rows').value = JSON.stringify(filled);
	});
}());
</script>
<?php } ?>
