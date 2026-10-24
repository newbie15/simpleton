<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$json_accounts = json_encode($accounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>
<style>
	.grid-workspace .jexcel_content { max-width: 100%; }
	.grid-workspace .help-block { margin-top: 12px; }
</style>

<div class="alert alert-warning">
	<strong>Accounting V3:</strong> setiap baris adalah satu jurnal debit-kredit yang langsung masuk ke buku besar bersama. Semua baris disimpan sebagai satu batch; jika satu baris gagal, seluruh batch dibatalkan. Jurnal yang sudah diposting tidak dapat dihapus dari halaman ini.
</div>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<div class="box box-primary grid-workspace">
	<div class="box-header with-border">
		<h3 class="box-title">Input jurnal seperti spreadsheet</h3>
		<div class="box-tools pull-right">
			<a class="btn btn-xs btn-default" href="<?php echo site_url('main/accounting_new'); ?>">Accounting V2</a>
			<a class="btn btn-xs btn-default" href="<?php echo site_url('main/accounting'); ?>">Tampilan lama</a>
		</div>
	</div>
	<div class="box-body">
		<p class="help-block">Klik sel lalu ketik. Gunakan Enter atau tombol panah untuk berpindah; akun debit dan kredit dipilih dari dropdown. Baris kosong diabaikan; maksimal 50 jurnal per batch.</p>
		<form method="post" action="<?php echo site_url('main/accounting_grid/post_rows'); ?>" id="accounting-grid-form">
			<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
			<input type="hidden" name="rows" id="accounting-grid-rows">
			<div id="accounting-grid"></div>
			<div class="form-group" style="margin-top:15px;">
				<button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Simpan semua jurnal</button>
				<a class="btn btn-default" href="<?php echo site_url('main/accounting_new'); ?>">Kembali ke Accounting V2</a>
			</div>
		</form>
	</div>
</div>

<script>
(function () {
	var accountOptions = <?php echo $json_accounts ? $json_accounts : '[]'; ?>;
	var rows = [];
	for (var i = 0; i < 10; i++) {
		rows.push(['', '', '', '', '']);
	}
	var grid = jexcel(document.getElementById('accounting-grid'), {
		data: rows,
		columns: [
			{ type: 'text', title: 'Tanggal (YYYY-MM-DD)', width: 155 },
			{ type: 'text', title: 'Uraian', width: 250 },
			{ type: 'dropdown', title: 'Akun debit', width: 240, source: accountOptions },
			{ type: 'dropdown', title: 'Akun kredit', width: 240, source: accountOptions },
			{ type: 'numeric', title: 'Nominal', width: 140 }
		],
		minDimensions: [5, 10],
		allowInsertRow: true,
		allowManualInsertRow: true,
		allowDeleteRow: true,
		tableOverflow: true,
		tableHeight: '480px'
	});
	document.getElementById('accounting-grid-form').addEventListener('submit', function (event) {
		var data = grid.getData();
		var filled = data.filter(function (row) {
			return row.some(function (value) { return String(value == null ? '' : value).trim() !== ''; });
		});
		if (!filled.length) {
			event.preventDefault();
			window.alert('Isi sedikitnya satu baris jurnal.');
			return;
		}
		document.getElementById('accounting-grid-rows').value = JSON.stringify(filled);
	});
}());
</script>
