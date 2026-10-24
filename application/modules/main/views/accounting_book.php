<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$rp = function ($value) { return 'Rp '.number_format((float) $value, 0, ',', '.'); };
$net_income = $sums['income']['balance'] - $sums['expense']['balance'];
$net_income_period = $sums['income']['movement'] - $sums['expense']['movement'];
$render = function ($nodes, $depth) use (&$render, $rp, $month) {
	foreach ($nodes as $node) {
		echo '<tr><td style="padding-left:'.(8 + $depth * 22).'px">'.html_escape($node->code).' &nbsp;'
			.'<a href="'.site_url('main/accounting_book/register/'.(int) $node->id.'?month='.$month).'">'.html_escape($node->name).'</a></td>'
			.'<td class="text-right">'.$rp($node->total_movement).'</td>'
			.'<td class="text-right">'.$rp($node->total).'</td></tr>';
		$render($node->children, $depth + 1);
	}
};
?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<form id="accounting-month-filter" class="form-inline" method="get" action="<?php echo site_url('main/accounting_book'); ?>" style="margin-bottom:15px;">
	<input type="hidden" name="tab" value="<?php echo html_escape($tab); ?>">
	<label>Bulan laporan&nbsp;</label>
	<input type="month" name="month" class="form-control" value="<?php echo html_escape($month); ?>">
	<button class="btn btn-default" type="submit">Tampilkan</button>
</form>

<ul class="nav nav-tabs" style="margin-bottom:15px;">
	<?php foreach (array('dashboard' => 'Dashboard', 'reporting' => 'Reporting', 'input' => 'Input Data', 'accounts' => 'Akun & Pengaturan') as $tab_key => $tab_label) { ?>
	<li<?php echo $tab === $tab_key ? ' class="active"' : ''; ?>><a href="<?php echo site_url('main/accounting_book?tab='.$tab_key.'&month='.rawurlencode($month)); ?>"><?php echo $tab_label; ?></a></li>
	<?php } ?>
</ul>

<?php if ($tab === 'dashboard') { ?>
<div class="row">
	<div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-arrow-down"></i></span><div class="info-box-content"><span class="info-box-text">Income bulan ini</span><span class="info-box-number"><?php echo $rp($sums['income']['movement']); ?></span></div></div></div>
	<div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-red"><i class="fa fa-arrow-up"></i></span><div class="info-box-content"><span class="info-box-text">Expense bulan ini</span><span class="info-box-number"><?php echo $rp($sums['expense']['movement']); ?></span></div></div></div>
	<div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-balance-scale"></i></span><div class="info-box-content"><span class="info-box-text">Laba/rugi bulan ini</span><span class="info-box-number"><?php echo $rp($net_income_period); ?></span></div></div></div>
	<div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-bank"></i></span><div class="info-box-content"><span class="info-box-text">Total aset</span><span class="info-box-number"><?php echo $rp($sums['asset']['balance']); ?></span></div></div></div>
</div>
<div id="chart-slot"></div>
<?php } ?>

<?php if ($tab === 'reporting') { ?>
<div class="row">
	<div class="col-md-6">
		<?php foreach (array('income', 'expense') as $key) { ?>
		<div class="box box-<?php echo $key === 'income' ? 'success' : 'danger'; ?>">
			<div class="box-header with-border"><h3 class="box-title">Laporan Laba Rugi — <?php echo html_escape($groups[$key][0]); ?></h3></div>
			<div class="box-body no-padding">
				<table class="table table-condensed table-hover">
					<thead><tr><th>Akun</th><th class="text-right">Bulan ini</th><th class="text-right">Sampai <?php echo html_escape($end); ?></th></tr></thead>
					<tbody><?php $render($tree[$key], 0); ?></tbody>
					<tfoot><tr><th>Total</th><th class="text-right"><?php echo $rp($sums[$key]['movement']); ?></th><th class="text-right"><?php echo $rp($sums[$key]['balance']); ?></th></tr></tfoot>
				</table>
			</div>
		</div>
		<?php } ?>
		<div class="alert alert-info">Laba/rugi bersih: <strong><?php echo $rp($net_income_period); ?></strong> bulan ini, <strong><?php echo $rp($net_income); ?></strong> kumulatif.</div>
	</div>

	<div class="col-md-6">
		<?php foreach (array('asset', 'liability', 'equity') as $key) { ?>
		<div class="box box-primary">
			<div class="box-header with-border"><h3 class="box-title">Neraca — <?php echo html_escape($groups[$key][0]); ?></h3></div>
			<div class="box-body no-padding">
				<table class="table table-condensed table-hover">
					<thead><tr><th>Akun</th><th class="text-right">Mutasi bulan ini</th><th class="text-right">Saldo <?php echo html_escape($end); ?></th></tr></thead>
					<tbody>
						<?php $render($tree[$key], 0); ?>
						<?php if ($key === 'equity') { ?>
						<tr><td style="padding-left:8px"><em>Laba berjalan (Income − Expense)</em></td><td class="text-right"><?php echo $rp($net_income_period); ?></td><td class="text-right"><?php echo $rp($net_income); ?></td></tr>
						<?php } ?>
					</tbody>
					<tfoot><tr><th>Total</th><th class="text-right"><?php echo $rp($sums[$key]['movement'] + ($key === 'equity' ? $net_income_period : 0)); ?></th><th class="text-right"><?php echo $rp($sums[$key]['balance'] + ($key === 'equity' ? $net_income : 0)); ?></th></tr></tfoot>
				</table>
			</div>
		</div>
		<?php } ?>
		<?php $diff = round($sums['asset']['balance'] - $sums['liability']['balance'] - $sums['equity']['balance'] - $net_income, 2); ?>
		<div class="alert alert-<?php echo $diff == 0 ? 'success' : 'warning'; ?>">
			<?php echo $diff == 0 ? 'Neraca seimbang: Aset = Kewajiban + Modal.' : 'Neraca tidak seimbang, selisih '.$rp($diff).'. Periksa jurnal.'; ?>
		</div>
	</div>
</div>
<?php } ?>

<?php if ($tab === 'dashboard') { ?>
<div class="box box-info" id="chart-box">
	<div class="box-header with-border"><h3 class="box-title">Grafik kustom</h3></div>
	<div class="box-body">
		<div class="row">
			<div class="col-sm-2 form-group"><label>Tampilan data</label>
				<select id="ch-mode" class="form-control"><option value="trend">Tren per bulan</option><option value="breakdown">Rincian per akun (bulan terpilih)</option></select></div>
			<div class="col-sm-2 form-group"><label>Kelompok</label>
				<select id="ch-group" class="form-control">
					<option value="income">Income</option><option value="expense">Expense</option><option value="net">Laba/rugi bersih</option>
					<option value="asset">Asset</option><option value="liability">Liability</option><option value="equity">Equity</option>
				</select></div>
			<div class="col-sm-2 form-group"><label>Jenis grafik</label>
				<select id="ch-type" class="form-control"><option value="bar">Batang</option><option value="line">Garis</option><option value="pie">Pai</option></select></div>
			<div class="col-sm-2 form-group"><label>Jumlah bulan</label>
				<input type="number" id="ch-months" class="form-control" min="2" max="24" value="6"></div>
			<div class="col-sm-2 form-group"><label>Warna</label>
				<input type="color" id="ch-color" class="form-control" value="#3c8dbc"></div>
			<div class="col-sm-2 form-group"><label>&nbsp;</label>
				<div class="checkbox"><label><input type="checkbox" id="ch-values" checked> Tampilkan nilai</label></div></div>
		</div>
		<canvas id="ch-canvas" width="1000" height="340" style="width:100%;max-width:1000px;height:auto;"></canvas>
		<p class="text-muted" id="ch-note"></p>
	</div>
</div>
<script>
(function () {
	var chartBox = document.getElementById('chart-box');
	var chartSlot = document.getElementById('chart-slot');
	if (chartSlot) { chartSlot.appendChild(chartBox); }
	var month = <?php echo json_encode($month); ?>;
	var url = <?php echo json_encode(site_url('main/accounting_book/chart_data')); ?>;
	var palette = ['#3c8dbc', '#00a65a', '#f39c12', '#dd4b39', '#605ca8', '#00c0ef', '#d81b60', '#39cccc', '#ff851b', '#001f3f', '#85144b', '#3d9970'];
	var canvas = document.getElementById('ch-canvas'), ctx = canvas.getContext('2d');
	var ids = ['ch-mode', 'ch-group', 'ch-type', 'ch-months', 'ch-color', 'ch-values'];
	var el = {};
	ids.forEach(function (id) { el[id] = document.getElementById(id); });
	var last = null;

	function fmt(v) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(v); }

	function draw() {
		if (!last) { return; }
		var W = canvas.width, H = canvas.height, type = el['ch-type'].value, color = el['ch-color'].value;
		var labels = last.labels, values = last.values, showVals = el['ch-values'].checked;
		ctx.clearRect(0, 0, W, H);
		ctx.font = '12px sans-serif';
		if (!values.length) { ctx.fillStyle = '#777'; ctx.fillText('Tidak ada data untuk pilihan ini.', 20, 30); return; }
		if (type === 'pie') {
			var pos = values.map(function (v) { return Math.max(v, 0); });
			var total = pos.reduce(function (a, b) { return a + b; }, 0);
			if (!total) { ctx.fillStyle = '#777'; ctx.fillText('Nilai nol atau negatif tidak dapat digambar sebagai pai.', 20, 30); return; }
			var cx = 200, cy = H / 2, r = 140, angle = -Math.PI / 2;
			pos.forEach(function (v, i) {
				var a = v / total * Math.PI * 2;
				ctx.beginPath(); ctx.moveTo(cx, cy); ctx.arc(cx, cy, r, angle, angle + a); ctx.closePath();
				ctx.fillStyle = palette[i % palette.length]; ctx.fill(); angle += a;
				ctx.fillRect(420, 20 + i * 24, 14, 14);
				ctx.fillStyle = '#333';
				ctx.fillText(labels[i] + (showVals ? ' — ' + fmt(values[i]) + ' (' + Math.round(v / total * 100) + '%)' : ''), 442, 32 + i * 24);
			});
			return;
		}
		var L = 90, R = 20, T = 20, B = 50, pw = W - L - R, ph = H - T - B;
		var max = Math.max.apply(null, values.concat([0])), min = Math.min.apply(null, values.concat([0]));
		if (max === min) { max = min + 1; }
		function y(v) { return T + ph - (v - min) / (max - min) * ph; }
		ctx.strokeStyle = '#ddd'; ctx.fillStyle = '#666'; ctx.textAlign = 'right';
		for (var g = 0; g <= 4; g++) {
			var gv = min + (max - min) * g / 4;
			ctx.beginPath(); ctx.moveTo(L, y(gv)); ctx.lineTo(W - R, y(gv)); ctx.stroke();
			ctx.fillText(fmt(gv), L - 6, y(gv) + 4);
		}
		var step = pw / values.length;
		ctx.textAlign = 'center';
		values.forEach(function (v, i) {
			var x = L + step * i + step / 2;
			ctx.fillStyle = '#666';
			var lab = labels[i].length > 14 ? labels[i].slice(0, 13) + '…' : labels[i];
			ctx.fillText(lab, x, H - B + 18);
			if (type === 'bar') {
				ctx.fillStyle = color;
				var bw = Math.min(step * 0.6, 70);
				ctx.fillRect(x - bw / 2, Math.min(y(v), y(0)), bw, Math.abs(y(v) - y(0)));
			}
			if (showVals) { ctx.fillStyle = '#333'; ctx.fillText(fmt(v), x, y(v) + (v >= 0 ? -6 : 14)); }
		});
		if (type === 'line') {
			ctx.strokeStyle = color; ctx.lineWidth = 2; ctx.beginPath();
			values.forEach(function (v, i) {
				var x = L + step * i + step / 2;
				if (i) { ctx.lineTo(x, y(v)); } else { ctx.moveTo(x, y(v)); }
			});
			ctx.stroke(); ctx.lineWidth = 1; ctx.fillStyle = color;
			values.forEach(function (v, i) { ctx.beginPath(); ctx.arc(L + step * i + step / 2, y(v), 4, 0, 7); ctx.fill(); });
		}
		ctx.textAlign = 'left';
	}

	function load() {
		var q = '?month=' + encodeURIComponent(month) + '&mode=' + el['ch-mode'].value +
			'&group=' + el['ch-group'].value + '&months=' + encodeURIComponent(el['ch-months'].value || 6);
		fetch(url + q, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (d) {
			last = d;
			document.getElementById('ch-note').textContent = el['ch-mode'].value === 'trend'
				? 'Saldo/mutasi per bulan sampai ' + month + ' (Asset, Liability, Equity = saldo akhir bulan; Income, Expense, Laba = mutasi bulan).'
				: 'Maksimal 12 akun terbesar pada ' + month + '.';
			draw();
		}).catch(function () { document.getElementById('ch-note').textContent = 'Gagal memuat data grafik.'; });
	}

	['ch-mode', 'ch-group', 'ch-months'].forEach(function (id) { el[id].addEventListener('change', load); });
	['ch-type', 'ch-color', 'ch-values'].forEach(function (id) { el[id].addEventListener('input', draw); });
	el['ch-mode'].addEventListener('change', function () { el['ch-months'].disabled = el['ch-mode'].value === 'breakdown'; });
	load();
}());
</script>
<?php } ?>

<?php if ($tab === 'input') { ?>
<div class="row">
	<div class="col-md-7">
		<div class="box box-primary">
			<div class="box-header with-border"><h3 class="box-title">Transaksi baru (transfer)</h3></div>
			<form method="post" action="<?php echo site_url('main/accounting_book/post_transaction'); ?>">
				<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
				<input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
				<div class="box-body">
					<p class="help-block">Seperti GnuCash: dana keluar dari akun asal (dikredit) dan masuk ke akun tujuan (didebit). Penjualan tunai: asal Income, tujuan Kas. Bayar biaya: asal Kas, tujuan Expense.</p>
					<div class="row">
						<div class="col-sm-4 form-group"><label>Tanggal</label><input type="date" name="date" class="form-control" required max="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>"></div>
						<div class="col-sm-8 form-group"><label>Keterangan</label><input type="text" name="description" class="form-control" required maxlength="255"></div>
					</div>
					<div class="row">
						<div class="col-sm-4 form-group"><label>Dari akun</label>
							<select name="from_account" class="form-control" required><option value="">— pilih —</option>
							<?php foreach ($accounts as $a) { ?><option value="<?php echo (int) $a['id']; ?>"><?php echo html_escape($a['code'].' '.$a['name']); ?></option><?php } ?></select></div>
						<div class="col-sm-4 form-group"><label>Ke akun</label>
							<select name="to_account" class="form-control" required><option value="">— pilih —</option>
							<?php foreach ($accounts as $a) { ?><option value="<?php echo (int) $a['id']; ?>"><?php echo html_escape($a['code'].' '.$a['name']); ?></option><?php } ?></select></div>
						<div class="col-sm-4 form-group"><label>Nominal</label><input type="text" name="amount" class="form-control" required inputmode="decimal" pattern="\d{1,13}(\.\d{1,2})?" placeholder="1500000"></div>
					</div>
				</div>
				<div class="box-footer"><button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> Posting transaksi</button></div>
			</form>
		</div>
	</div>
</div>
<?php } ?>

<?php if ($tab === 'accounts') { ?>
	<div class="box box-primary">
		<div class="box-header with-border"><h3 class="box-title">Bagan akun</h3></div>
		<div class="box-body no-padding">
			<div class="table-responsive">
			<table class="table table-condensed table-hover">
				<thead><tr><th>Kode</th><th>Nama akun</th><th>Kelompok</th><th>Saldo normal</th><th>Akun induk</th><th></th></tr></thead>
				<tbody>
				<?php $account_map = array(); foreach ($accounts as $a) { $account_map[$a['id']] = $a; } ?>
				<?php foreach ($accounts as $a) { ?>
				<tr>
					<td><?php echo html_escape($a['code']); ?></td>
					<td><?php echo html_escape($a['name']); ?></td>
					<td><?php echo html_escape(ucfirst($a['type'])); ?></td>
					<td><?php echo html_escape(ucfirst($a['normal_balance'])); ?></td>
					<td><?php echo $a['parent_id'] && isset($account_map[$a['parent_id']]) ? html_escape($account_map[$a['parent_id']]['code'].' '.$account_map[$a['parent_id']]['name']) : '—'; ?></td>
					<td><a class="btn btn-xs btn-default" href="<?php echo site_url('main/accounting_book/register/'.$a['id'].'?month='.$month); ?>">Buka buku</a></td>
				</tr>
				<?php } ?>
				</tbody>
			</table>
			</div>
		</div>
	</div>
	<div class="row">
	<div class="col-md-5">
		<div class="box box-default">
			<div class="box-header with-border"><h3 class="box-title">Akun baru</h3></div>
			<form method="post" action="<?php echo site_url('main/accounting_book/create_account'); ?>">
				<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
				<input type="hidden" name="month" value="<?php echo html_escape($month); ?>">
				<div class="box-body">
					<div class="row">
						<div class="col-sm-4 form-group"><label>Kode</label><input type="text" name="code" class="form-control" required maxlength="32"></div>
						<div class="col-sm-8 form-group"><label>Nama</label><input type="text" name="name" class="form-control" required maxlength="128"></div>
					</div>
					<div class="form-group"><label>Kelompok</label>
						<select name="group" class="form-control" required>
							<?php foreach ($groups as $key => $group) { ?><option value="<?php echo $key; ?>"><?php echo html_escape($group[0]); ?></option><?php } ?>
						</select></div>
					<div class="form-group"><label>Akun induk (opsional, harus satu kelompok)</label>
						<select name="parent_id" class="form-control"><option value="0">— tanpa induk —</option>
						<?php foreach ($accounts as $a) { ?><option value="<?php echo (int) $a['id']; ?>"><?php echo html_escape($a['code'].' '.$a['name'].' ['.$a['type'].']'); ?></option><?php } ?></select></div>
				</div>
				<div class="box-footer"><button class="btn btn-default" type="submit"><i class="fa fa-plus"></i> Buat akun</button></div>
			</form>
		</div>
	</div>
</div>
<?php } ?>
