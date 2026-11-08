<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $rp = function ($value) { return 'Rp '.number_format((float) $value, 0, ',', '.'); }; ?>
<?php $is_open = $period->status === 'open'; ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>

<p><a href="<?php echo site_url('main/sales_billing/detail/'.(int) $period->id); ?>"><i class="fa fa-arrow-left"></i> Kembali ke detail periode</a></p>

<?php if (!$reference) { ?>
<div class="box box-warning">
	<div class="box-header with-border"><h3 class="box-title">Cek nota · <?php echo html_escape($period->period_name); ?></h3></div>
	<div class="box-body">
		<p>Daftar nota belum tersedia. Jalankan validasi <strong>Seluruh periode</strong> terlebih dahulu dari halaman detail periode.</p>
		<a class="btn btn-warning" href="<?php echo site_url('main/sales_billing/revisions/'.(int) $period->id); ?>">Revisi nota bermasalah</a>
		<a class="btn btn-primary" href="<?php echo site_url('main/sales_billing/detail/'.(int) $period->id); ?>">Ke halaman detail</a>
	</div>
</div>
<?php } else { ?>

<div class="row">
	<div class="col-sm-3 col-xs-6"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-file-text-o"></i></span><div class="info-box-content"><span class="info-box-text">Total nota</span><span class="info-box-number" id="count-total"><?php echo (int) $stats['total']; ?></span></div></div></div>
	<div class="col-sm-3 col-xs-6"><div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-check"></i></span><div class="info-box-content"><span class="info-box-text">Sudah cocok</span><span class="info-box-number" id="count-matched"><?php echo (int) $stats['matched']; ?></span></div></div></div>
	<div class="col-sm-3 col-xs-6"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span><div class="info-box-content"><span class="info-box-text">Belum di-scan</span><span class="info-box-number" id="count-pending"><?php echo (int) $stats['pending']; ?></span></div></div></div>
	<div class="col-sm-3 col-xs-6"><div class="info-box"><span class="info-box-icon bg-red"><i class="fa fa-warning"></i></span><div class="info-box-content"><span class="info-box-text">Bermasalah</span><span class="info-box-number" id="count-problems"><?php echo (int) $stats['problems']; ?></span></div></div></div>
</div>

<?php if ($is_open) { ?>
<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Scan nota · <?php echo html_escape($period->period_name); ?></h3></div>
	<div class="box-body">
		<div class="form-group">
			<label for="scan-input">Scan barcode nota (atau ketik nomor nota lalu Enter)</label>
			<input id="scan-input" class="form-control input-lg" type="text" autocomplete="off" autofocus placeholder="Arahkan kursor di sini lalu scan barcode nota">
		</div>
		<div id="scan-result" style="display:none;">
			<div id="scan-result-box" class="callout">
				<h4 id="scan-result-title"></h4>
				<p id="scan-result-message"></p>
				<div id="scan-result-detail" style="display:none;">
					<dl class="dl-horizontal" style="margin-bottom:8px;">
						<dt>Anggota</dt><dd id="result-member"></dd>
						<dt>Tanggal</dt><dd id="result-date"></dd>
						<dt>Nominal sistem</dt><dd><strong style="font-size:22px;" id="result-total"></strong></dd>
						<dt>Petugas scan</dt><dd id="result-operator"></dd>
					</dl>
					<form id="mismatch-form" class="form-inline">
						<label for="mismatch-total">Nominal di nota fisik berbeda?&nbsp;</label>
						<input id="mismatch-total" class="form-control" type="text" inputmode="decimal" placeholder="Nominal di nota (angka)">
						<button class="btn btn-danger" type="submit">Tandai tidak sama</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</div>
<?php } else { ?>
<div class="alert alert-info">Periode sudah final. Halaman ini hanya untuk melihat hasil pengecekan.</div>
<?php } ?>

<div class="nav-tabs-custom">
	<ul class="nav nav-tabs">
		<li class="active"><a href="#tab-pending" data-toggle="tab">Belum di-scan</a></li>
		<li><a href="#tab-scanned" data-toggle="tab">Sudah cocok</a></li>
		<li><a href="#tab-problems" data-toggle="tab">Bermasalah</a></li>
	</ul>
	<div class="tab-content">
		<div class="tab-pane active" id="tab-pending">
			<div class="table-responsive">
				<table class="table table-bordered table-striped" id="table-pending" style="width:100%">
					<thead><tr><th>No. nota</th><th>NPK</th><th>Nama</th><th>Tanggal</th><th class="text-right">Nominal sistem</th><th>Validasi</th></tr></thead>
					<tbody><?php foreach ($pending as $row) { ?><tr data-nota="<?php echo html_escape($row->nota); ?>">
						<td><?php echo html_escape($row->nota); ?></td><td><?php echo html_escape($row->npk); ?></td><td><?php echo html_escape($row->member_name); ?></td>
						<td><?php echo html_escape(substr($row->transaction_date, 0, 10)); ?></td>
						<td class="text-right" data-order="<?php echo (float) $row->billable_total; ?>"><?php echo $rp($row->billable_total); ?></td>
						<td><?php echo $row->validation_status === 'valid' ? '<span class="label label-success">Valid</span>' : '<span class="label label-danger" title="'.html_escape($row->issues).'">Perlu diperiksa</span>'; ?></td>
					</tr><?php } ?></tbody>
				</table>
			</div>
			<?php if (count($pending) === 1000) { ?><p class="text-warning">Menampilkan 1000 nota pertama yang belum di-scan.</p><?php } ?>
		</div>
		<div class="tab-pane" id="tab-scanned">
			<div class="table-responsive">
				<table class="table table-bordered table-striped" id="table-scanned" style="width:100%">
					<thead><tr><th>No. nota</th><th>NPK</th><th>Nama</th><th class="text-right">Nominal sistem</th><th>Waktu scan</th><th>Petugas</th></tr></thead>
					<tbody><?php foreach ($scanned as $row) { ?><tr data-nota="<?php echo html_escape($row->nota); ?>">
						<td><?php echo html_escape($row->nota); ?></td><td><?php echo html_escape($row->npk); ?></td><td><?php echo html_escape($row->member_name); ?></td>
						<td class="text-right" data-order="<?php echo (float) $row->billable_total; ?>"><?php echo $rp($row->billable_total); ?></td>
						<td><?php echo html_escape($row->scan_time); ?></td><td><?php echo html_escape($row->operator_name); ?></td>
					</tr><?php } ?></tbody>
				</table>
			</div>
		</div>
		<div class="tab-pane" id="tab-problems">
			<div class="table-responsive">
				<table class="table table-bordered table-striped" id="table-problems" style="width:100%">
					<thead><tr><th>No. nota</th><th>NPK</th><th>Nama</th><th class="text-right">Nominal sistem</th><th class="text-right">Nominal nota</th><th>Catatan</th><th>Waktu scan</th><th>Petugas</th></tr></thead>
					<tbody><?php foreach ($problems as $row) { ?><tr data-nota="<?php echo html_escape($row->nota); ?>">
						<td><?php echo html_escape($row->nota); ?></td><td><?php echo html_escape($row->npk); ?></td><td><?php echo html_escape($row->member_name); ?></td>
						<td class="text-right" data-order="<?php echo (float) $row->billable_total; ?>"><?php echo $rp($row->billable_total); ?></td>
						<td class="text-right"><?php echo $row->nota_total === NULL ? '—' : $rp($row->nota_total); ?></td>
						<td><?php echo $row->scan_status === 'mismatch' ? 'Nominal nota tidak sama' : 'Data nota berubah setelah scan, scan ulang'; ?></td>
						<td><?php echo html_escape($row->scan_time); ?></td><td><?php echo html_escape($row->operator_name); ?></td>
					</tr><?php } ?></tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<script>
(function () {
	var periodId = <?php echo (int) $period->id; ?>;
	var token = <?php echo json_encode($form_token); ?>;
	var scanUrl = <?php echo json_encode(site_url('main/sales_billing/scan_nota')); ?>;
	var tables = {};
	var lang = { search: 'Cari:', lengthMenu: 'Tampilkan _MENU_', info: '_START_ - _END_ dari _TOTAL_', infoEmpty: '0 nota', emptyTable: 'Tidak ada data', zeroRecords: 'Tidak ada data yang cocok', paginate: { next: 'Berikut', previous: 'Sebelum' } };

	function ready(callback) {
		if (typeof jQuery === 'undefined' || typeof jQuery.fn.DataTable === 'undefined') { setTimeout(function () { ready(callback); }, 100); return; }
		callback();
	}
	function money(value) { return 'Rp ' + Number(value).toLocaleString('id-ID'); }
	function setText(id, value) { var el = document.getElementById(id); if (el) { el.textContent = value; } }

	function removeRow(key, nota) {
		var table = tables[key];
		if (!table) { return; }
		table.rows(function (idx, data, node) {
			return node && node.getAttribute('data-nota') === nota;
		}).remove();
		table.draw(false);
	}
	function addRow(key, cells, nota) {
		var table = tables[key];
		if (!table) { return; }
		var tr = document.createElement('tr');
		tr.setAttribute('data-nota', nota);
		cells.forEach(function (cell) {
			var td = document.createElement('td');
			if (cell !== null && typeof cell === 'object') {
				td.textContent = money(cell.value);
				td.setAttribute('data-order', cell.value);
				td.className = 'text-right';
			} else {
				td.textContent = cell;
			}
			tr.appendChild(td);
		});
		table.row.add(tr).draw(false);
	}
	function updateStats(stats) {
		if (!stats) { return; }
		setText('count-total', stats.total);
		setText('count-matched', stats.matched);
		setText('count-pending', stats.pending);
		setText('count-problems', stats.problems);
	}

	function showResult(data) {
		var box = document.getElementById('scan-result-box');
		var colors = { ok: 'callout-success', duplicate: 'callout-warning', mismatch: 'callout-danger', error: 'callout-danger' };
		var titles = { ok: 'Nota tercatat: ', duplicate: 'Sudah pernah di-scan: ', mismatch: 'Nominal tidak sama: ', error: 'Gagal: ' };
		document.getElementById('scan-result').style.display = 'block';
		box.className = 'callout ' + (colors[data.kind] || 'callout-danger');
		setText('scan-result-title', (titles[data.kind] || '') + (data.nota || ''));
		setText('scan-result-message', data.message || '');
		var detail = document.getElementById('scan-result-detail');
		if (data.nota && data.system_total !== undefined) {
			detail.style.display = 'block';
			detail.setAttribute('data-nota', data.nota);
			setText('result-member', (data.member_name || '-') + ' (NPK ' + (data.npk || '-') + ')');
			setText('result-date', (data.transaction_date || '').substring(0, 10));
			setText('result-total', money(data.system_total));
			setText('result-operator', data.operator_name || '-');
		} else {
			detail.style.display = 'none';
			detail.removeAttribute('data-nota');
		}
	}

	function send(nota, notaTotal, done) {
		var body = new URLSearchParams();
		body.append('period_id', periodId);
		body.append('form_token', token);
		body.append('nota', nota);
		if (notaTotal) { body.append('nota_total', notaTotal); }
		fetch(scanUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (response) { return response.json(); })
			.then(function (data) { done(data); })
			.catch(function () { done({ kind: 'error', message: 'Koneksi gagal atau respons tidak valid.' }); });
	}

	function applyResult(data) {
		showResult(data);
		updateStats(data.stats);
		if (data.kind === 'ok' || data.kind === 'mismatch') {
			var nota = data.nota;
			removeRow('pending', nota);
			removeRow('scanned', nota);
			removeRow('problems', nota);
			if (data.kind === 'ok') {
				addRow('scanned', [nota, data.npk || '', data.member_name || '', { value: data.system_total }, data.scan_time, data.operator_name], nota);
			} else {
				addRow('problems', [nota, data.npk || '', data.member_name || '', { value: data.system_total }, { value: data.nota_total }, 'Nominal nota tidak sama', data.scan_time, data.operator_name], nota);
			}
		}
	}

	var input = document.getElementById('scan-input');
	if (input) {
		input.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter') { return; }
			event.preventDefault();
			var nota = input.value.trim();
			if (nota === '') { return; }
			input.disabled = true;
			send(nota, '', function (data) {
				applyResult(data);
				input.disabled = false;
				input.value = '';
				input.focus();
			});
		});
	}
	var mismatchForm = document.getElementById('mismatch-form');
	if (mismatchForm) {
		mismatchForm.addEventListener('submit', function (event) {
			event.preventDefault();
			var nota = document.getElementById('scan-result-detail').getAttribute('data-nota');
			var total = document.getElementById('mismatch-total').value.replace(/[^0-9.\-]/g, '');
			if (!nota || total === '') { return; }
			send(nota, total, function (data) {
				applyResult(data);
				document.getElementById('mismatch-total').value = '';
				if (input) { input.focus(); }
			});
		});
	}

	ready(function () {
		['pending', 'scanned', 'problems'].forEach(function (key) {
			var id = '#table-' + key;
			if (!jQuery.fn.DataTable.isDataTable(id)) {
				tables[key] = jQuery(id).DataTable({ pageLength: 25, order: [], language: lang });
			} else {
				tables[key] = jQuery(id).DataTable();
			}
		});
	});
}());
</script>
<?php } ?>
