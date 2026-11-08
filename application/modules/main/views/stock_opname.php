<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>

<?php if ($page === 'list') { ?>
	<div class="box box-default">
		<div class="box-header with-border">
			<h3 class="box-title">Daftar Stok Opname</h3>
			<div class="box-tools">
				<form method="post" action="<?php echo site_url('main/stock_opname/create'); ?>" style="display:inline">
					<button class="btn btn-primary btn-sm" type="submit"><span class="glyphicon glyphicon-plus"></span> Opname Semua Barang</button>
				</form>
			</div>
		</div>
		<div class="box-body table-responsive">
			<p class="help-block">Pilih opname satu barang untuk memperbarui stok barcode tertentu, atau opname semua barang untuk pemeriksaan menyeluruh. HPP mengikuti nilai di sistem.</p>
			<form method="post" action="<?php echo site_url('main/stock_opname/create'); ?>" class="form-inline" style="margin-bottom:15px;">
				<div class="form-group">
					<label for="opname-barcode">Opname satu barang:</label>
					<input type="text" class="form-control" id="opname-barcode" name="barcode" maxlength="64" required placeholder="Masukkan atau scan barcode">
				</div>
				<button class="btn btn-default" type="submit">Mulai Opname Barcode</button>
			</form>
			<p class="help-block">HPP pada opname mengikuti HPP sistem. Untuk menetapkan atau memperbarui HPP, gunakan halaman Riwayat HPP. Perubahan stok baru diterapkan setelah opname diselesaikan.</p>
			<table class="table table-striped table-bordered" id="opname-list-table">
				<thead>
					<tr><th>No. Opname</th><th>Dibuat</th><th>Status</th><th>Jumlah barang</th><th>Belum dihitung</th><th>Nilai persediaan</th><th>Aksi</th></tr>
				</thead>
				<tbody>
					<?php foreach ($opnames as $opname) { ?>
						<tr>
							<td><?php echo html_escape($opname->opname_number); ?></td>
							<td><?php echo html_escape($opname->created_at); ?></td>
							<td><?php echo $opname->status === 'finalized' ? 'Selesai' : ($opname->status === 'cancelled' ? 'Dibatalkan' : 'Draft'); ?></td>
							<td data-order="<?php echo (int) $opname->item_count; ?>"><?php echo number_format((int) $opname->item_count); ?></td>
							<td data-order="<?php echo (int) $opname->uncounted_items; ?>"><?php echo number_format((int) $opname->uncounted_items); ?></td>
							<td data-order="<?php echo (float) $opname->inventory_value; ?>"><?php echo 'Rp '.number_format((float) $opname->inventory_value, 0, ',', '.'); ?></td>
							<td>
								<a class="btn btn-xs btn-default" href="<?php echo site_url('main/stock_opname/edit/'.(int) $opname->id); ?>"><?php echo $opname->status === 'draft' ? 'Lanjutkan' : 'Lihat'; ?></a>
								<?php if ($opname->status === 'draft') { ?>
									<form method="post" action="<?php echo site_url('main/stock_opname/cancel/'.(int) $opname->id); ?>" style="display:inline" onsubmit="return confirm('Batalkan draft opname ini? Data hitungannya akan disimpan sebagai riwayat, tetapi tidak dapat dilanjutkan.');">
										<button class="btn btn-xs btn-danger" type="submit">Batalkan</button>
									</form>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
	<script>
	(function () {
		document.addEventListener('DOMContentLoaded', function () {
			$('#opname-list-table').DataTable({
				order: [[1, 'desc']],
				pageLength: 10,
				lengthMenu: [10, 25, 50, 100],
				columnDefs: [{ orderable: false, targets: 6 }],
				language: {
					emptyTable: 'Belum ada stok opname.',
					info: 'Menampilkan _START_ - _END_ dari _TOTAL_ opname',
					infoEmpty: 'Menampilkan 0 opname',
					infoFiltered: '(difilter dari _MAX_ total opname)',
					lengthMenu: 'Tampilkan _MENU_ opname',
					search: 'Cari:',
					paginate: { first: 'Awal', last: 'Akhir', next: 'Berikut', previous: 'Sebelum' },
					zeroRecords: 'Tidak ada opname yang cocok.'
				}
			});
		});
	}());
	</script>
<?php } else { ?>
	<div class="box box-primary">
		<div class="box-header with-border">
			<h3 class="box-title"><?php echo html_escape($opname->opname_number); ?><?php echo $opname->status === 'finalized' ? ' - Selesai' : ($opname->status === 'cancelled' ? ' - Dibatalkan' : ' - Draft'); ?></h3>
			<div class="pull-right">
				<?php if ($opname->status === 'draft') { ?>
					<button class="btn btn-primary btn-sm" type="submit" form="opname-save-form">Simpan Draft</button>
					<form id="opname-sync-form" method="post" action="<?php echo site_url('main/stock_opname/sync/'.(int) $opname->id); ?>" style="display:inline">
						<button class="btn btn-info btn-sm" type="submit">Sinkronkan Barang Baru</button>
					</form>
					<form id="opname-finalize-form" method="post" action="<?php echo site_url('main/stock_opname/finalize/'.(int) $opname->id); ?>" style="display:inline">
						<button class="btn btn-success btn-sm" type="submit">Selesaikan Opname</button>
					</form>
					<form method="post" action="<?php echo site_url('main/stock_opname/cancel/'.(int) $opname->id); ?>" style="display:inline" onsubmit="return confirm('Batalkan draft opname ini? Data hitungannya disimpan sebagai riwayat, tetapi tidak dapat dilanjutkan.');">
						<button class="btn btn-danger btn-sm" type="submit">Batalkan Draft</button>
					</form>
				<?php } ?>
				<a class="btn btn-default btn-sm" href="<?php echo site_url('main/stock_opname'); ?>">Kembali ke daftar</a>
			</div>
		</div>
		<div class="box-body">
			<p><strong>Dibuat:</strong> <?php echo html_escape($opname->created_at); ?>
				<?php if ($opname->finalized_at) { ?>&nbsp; <strong>Diselesaikan:</strong> <?php echo html_escape($opname->finalized_at); ?><?php } ?></p>
			<?php if ($opname->status === 'draft') { ?>
				<p class="help-block">Isi qty fisik setiap barang. HPP diambil dari sistem dan tidak dapat diubah dari halaman opname; perbarui HPP melalui halaman Riwayat HPP. Kosongkan qty fisik jika barang belum dihitung. Perubahan stok baru diterapkan saat opname diselesaikan. Selisih dihitung dari snapshot saat draft dibuat agar pergerakan stok berikutnya tidak tertimpa.</p>
				<div class="form-group">
					<label for="opname-search">Scan barcode / cari barang</label>
					<input type="text" class="form-control" id="opname-search" placeholder="Scan barcode atau ketik nama barang">
					<span class="help-block">Tekan Enter setelah scan barcode. Jika cocok, daftar difilter dan kursor berpindah ke Qty Fisik.</span>
				</div>
				<form id="opname-save-form" method="post" action="<?php echo site_url('main/stock_opname/save/'.(int) $opname->id); ?>">
					<input type="hidden" id="items-json" name="items_json">
				</form>
			<?php } elseif ($opname->status === 'finalized') { ?>
				<p class="help-block">Opname ini sudah diterapkan ke stok dan tidak dapat diubah.</p>
			<?php } else { ?>
				<p class="help-block">Draft ini dibatalkan. Data hitungannya disimpan sebagai riwayat dan tidak mengubah stok.</p>
			<?php } ?>
			<div class="row">
				<div class="col-sm-4"><strong>Barang terhitung:</strong> <span id="counted-items">0</span> / <?php echo number_format(count($items)); ?></div>
				<div class="col-sm-4"><strong>Nilai persediaan:</strong> <span id="inventory-value">Rp 0</span></div>
				<?php if ($can_see_system_stock) { ?><div class="col-sm-4"><strong>Selisih qty bersih:</strong> <span id="net-adjustment">0</span></div><?php } ?>
			</div>
			<br>
			<div id="opname-grid"></div>
		</div>
	</div>

	<script>
	(function () {
		var rows = <?php
			$grid_rows = array();
			foreach ($items as $item) {
				$cost = $opname->status === 'finalized' || $opname->status === 'cancelled'
					? $item->unit_cost
					: ($item->system_average_cost !== NULL && (float) $item->system_average_cost > 0
						? $item->system_average_cost
						: $item->unit_cost);
				$unit_cost = $cost === NULL || (float) $cost <= 0
					? ''
					: rtrim(rtrim((string) $cost, '0'), '.');
				$grid_row = array(
					(int) $item->id,
					$item->barcode,
					$item->product_name
				);
				if ($can_see_system_stock) {
					$grid_row[] = (int) $item->system_qty;
				}
				$grid_row[] = $item->counted_qty === NULL ? '' : (int) $item->counted_qty;
				$grid_row[] = $unit_cost;
				$grid_rows[] = $grid_row;
			}
			echo json_encode($grid_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		?>;
		var initialRows = JSON.parse(JSON.stringify(rows));
		var isDraft = <?php echo $opname->status === 'draft' ? 'true' : 'false'; ?>;
		var canSeeSystemStock = <?php echo $can_see_system_stock ? 'true' : 'false'; ?>;
		var quantityColumn = canSeeSystemStock ? 4 : 3;
		var costColumn = quantityColumn + 1;
		var columns = [
			{ type: 'numeric', title: 'ID', width: 65, readOnly: true },
			{ type: 'text', title: 'Barcode', width: 150, readOnly: true },
			{ type: 'text', title: 'Nama Barang', width: 240, readOnly: true }
		];
		if (canSeeSystemStock) {
			columns.push({ type: 'numeric', title: 'Qty Sistem (snapshot)', width: 140, readOnly: true });
		}
		columns.push({ type: 'numeric', title: 'Qty Fisik', width: 110, readOnly: !isDraft });
		columns.push({ type: 'numeric', title: 'HPP Sistem / Unit', width: 150, readOnly: true });
		var dirty = false;
		var grid = jexcel(document.getElementById('opname-grid'), {
			data: rows,
			columns: columns,
			minDimensions: [columns.length, 1],
			allowInsertRow: false,
			allowDeleteRow: false,
			tableOverflow: true,
			tableHeight: '520px',
			search: true,
			onchange: function () {
				dirty = true;
				updateSummary();
			}
		});
		if (grid.filter) {
			grid.filter.style.display = 'none';
		}

		function formatMoney(value) {
			return 'Rp ' + Math.round(value).toLocaleString('id-ID');
		}

		function updateSummary() {
			var data = grid.getData();
			var counted = 0;
			var value = 0;
			var adjustment = 0;
			for (var i = 0; i < data.length; i++) {
				var snapshotQty = canSeeSystemStock ? (parseInt(data[i][3], 10) || 0) : 0;
				var physicalQty = data[i][quantityColumn] === '' ? null : parseInt(data[i][quantityColumn], 10);
				var unitCost = data[i][costColumn] === '' ? null : parseFloat(String(data[i][costColumn]).replace(/,/g, ''));
				if (physicalQty !== null && !isNaN(physicalQty)) {
					counted++;
					if (canSeeSystemStock) {
						adjustment += physicalQty - snapshotQty;
					}
					if (unitCost !== null && !isNaN(unitCost)) {
						value += physicalQty * unitCost;
					}
				}
			}
			document.getElementById('counted-items').textContent = counted.toLocaleString('id-ID');
			document.getElementById('inventory-value').textContent = formatMoney(value);
			if (canSeeSystemStock) {
				document.getElementById('net-adjustment').textContent = adjustment.toLocaleString('id-ID');
			}
		}

		if (isDraft) {
			var searchInput = document.getElementById('opname-search');
			searchInput.addEventListener('input', function () {
				grid.search(this.value.trim());
			});
			searchInput.addEventListener('keydown', function (event) {
				if (event.key !== 'Enter') {
					return;
				}
				event.preventDefault();
				var barcode = this.value.trim();
				if (!barcode) {
					return;
				}
				for (var i = 0; i < rows.length; i++) {
					if (String(rows[i][1]).trim() === barcode) {
						grid.search(barcode);
						grid.updateSelectionFromCoords(quantityColumn, i, quantityColumn, i);
						grid.openEditor(grid.records[i][quantityColumn]);
						return;
					}
				}
				grid.search(barcode);
			});
		}

		if (isDraft) {
			document.getElementById('opname-save-form').addEventListener('submit', function (event) {
				var data = grid.getData();
				var payload = [];
				for (var i = 0; i < data.length; i++) {
					if (data[i][quantityColumn] !== initialRows[i][quantityColumn]) {
						payload.push([data[i][0], data[i][quantityColumn]]);
					}
				}
				document.getElementById('items-json').value = JSON.stringify(payload);
			});
			document.getElementById('opname-sync-form').addEventListener('submit', function (event) {
				if (dirty) {
					event.preventDefault();
					alert('Simpan perubahan qty terlebih dahulu sebelum menyinkronkan barang baru.');
				}
			});
			document.getElementById('opname-finalize-form').addEventListener('submit', function (event) {
				if (dirty) {
					event.preventDefault();
					alert('Ada perubahan yang belum disimpan. Klik Simpan Draft terlebih dahulu.');
					return;
				}
				if (!window.confirm('Selesaikan opname ini dan terapkan selisihnya ke stok? Tindakan ini tidak dapat dibatalkan.')) {
					event.preventDefault();
				}
			});
		}

		updateSummary();
	}());
	</script>
<?php } ?>
