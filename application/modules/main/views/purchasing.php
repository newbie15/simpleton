<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>

<style>
	.po-product-search {
		position: relative;
		z-index: 2001;
	}
	#product-search-results {
		position: absolute;
		top: 100%;
		left: 0;
		z-index: 2002;
		width: 100%;
		display: none;
		max-height: 260px;
		overflow-y: auto;
	}
</style>

<?php if ($page === 'create') { ?>
	<div class="row">
		<div class="col-md-12">
			<div class="box box-primary">
				<div class="box-header with-border">
					<h3 class="box-title"><?php echo isset($order) ? 'Edit Purchase Order '.$order->order_number : 'Buat Purchase Order'; ?></h3>
					<div class="pull-right">
						<button class="btn btn-primary btn-sm" type="submit" form="po-form"><?php echo isset($order) ? 'Simpan Perubahan' : 'Simpan Draft PO'; ?></button>
						<a class="btn btn-default btn-sm" id="back-to-orders" href="<?php echo site_url('main/purchasing'); ?>">Kembali ke daftar</a>
					</div>
				</div>
				<form id="po-form" method="post" action="<?php echo isset($order) ? site_url('main/purchasing/update/'.$order->id) : site_url('main/purchasing/save'); ?>">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="supplier_id">Supplier</label>
									<select class="form-control" id="supplier_id" name="supplier_id" required>
										<option value="">Pilih supplier</option>
										<?php foreach ($suppliers as $supplier) { ?>
											<option value="<?php echo (int) $supplier->id; ?>" <?php echo isset($order) && (int) $order->supplier_id === (int) $supplier->id ? 'selected' : ''; ?>><?php echo html_escape($supplier->name); ?></option>
										<?php } ?>
									</select>
									<?php if (count($suppliers) === 0) { ?>
										<p class="help-block">Belum ada supplier. <a href="<?php echo site_url('main/purchasing/suppliers'); ?>">Tambah supplier terlebih dahulu</a>.</p>
									<?php } ?>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="notes">Catatan PO</label>
									<input class="form-control" type="text" id="notes" name="notes" value="<?php echo isset($order) ? html_escape($order->notes) : ''; ?>">
								</div>
							</div>
						</div>

						<input type="hidden" id="items_json" name="items_json">
						<div class="form-group po-product-search">
							<label for="product-search">Tambah Barang (barcode / nama)</label>
							<input class="form-control" type="text" id="product-search" autocomplete="off" placeholder="Scan barcode atau cari nama barang lalu tekan Enter">
							<div id="product-search-results" class="list-group"></div>
							<p class="help-block">Scan barcode langsung menambahkan barang. Pencarian nama menampilkan pilihan; gunakan panah atas/bawah dan Enter.</p>
						</div>
						<div class="form-group">
							<label for="excel-file">Upload Excel/CSV</label>
							<input type="file" id="excel-file" accept=".xls,.xlsx,.csv">
							<p class="help-block">Susunan kolom: Barcode, Nama Barang, Qty.</p>
							<div id="excel-import-message"></div>
						</div>
						<div id="po-items-grid"></div>
						<p class="help-block">Ini tahap permintaan pembelian, jadi cukup tentukan barang dan jumlah. Harga tidak dicatat di tahap ini. Daftar mendukung copy-paste dari Excel.</p>
					</div>
				</form>
			</div>
		</div>
	</div>

	<script>
	(function () {
		function safeText(value) {
			var element = document.createElement('span');
			element.textContent = value == null ? '' : String(value);
			return element.innerHTML;
		}
		var initialData = <?php
			$grid_rows = array();
			foreach ($items as $item) {
				$grid_rows[] = array($item->barcode, $item->product_name, $item->qty_ordered);
			}
			if (count($grid_rows) === 0) {
				$grid_rows[] = array('', '', '');
			}
			echo json_encode($grid_rows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
		?>;
		initialData = initialData.map(function (row) {
			return row.map(safeText);
		});
		var grid = jexcel(document.getElementById('po-items-grid'), {
			data: initialData,
			columns: [
				{ type: 'text', title: 'Barcode', width: 170, readOnly: true },
				{ type: 'text', title: 'Nama Barang', width: 240 },
				{ type: 'numeric', title: 'Qty Diminta', width: 110 }
			],
			minDimensions: [3, 1],
			allowInsertRow: true,
			allowManualInsertRow: true,
			allowDeleteRow: true,
			tableOverflow: true,
			tableHeight: '360px',
			onbeforechange: function (instance, cell, column, row, value) {
				return safeText(value);
			}
		});
		var searchInput = document.getElementById('product-search');
		var resultsBox = document.getElementById('product-search-results');
		var searchTimer;
		var searchRequest = null;
		var searchResults = [];
		var activeResult = -1;
		var searchSequence = 0;

		function hideResults() {
			window.clearTimeout(searchTimer);
			searchSequence++;
			if (searchRequest) {
				searchRequest.abort();
				searchRequest = null;
			}
			resultsBox.style.display = 'none';
			resultsBox.innerHTML = '';
			searchResults = [];
			activeResult = -1;
		}

		function renderResults(products) {
			searchResults = products;
			activeResult = products.length ? 0 : -1;
			resultsBox.innerHTML = '';
			if (!products.length) {
				resultsBox.style.display = 'none';
				return;
			}
			products.forEach(function (product, index) {
				var option = document.createElement('button');
				option.type = 'button';
				option.className = 'list-group-item';
				option.style.width = '100%';
				option.style.textAlign = 'left';
				option.textContent = product.barcode + ' - ' + product.nama;
				option.addEventListener('mousedown', function (event) {
					event.preventDefault();
					addProduct(product, 1);
				});
				option.addEventListener('mouseenter', function () {
					activeResult = index;
					updateActiveResult();
				});
				resultsBox.appendChild(option);
			});
			resultsBox.style.display = 'block';
			updateActiveResult();
		}

		function updateActiveResult() {
			Array.prototype.forEach.call(resultsBox.children, function (option, index) {
				option.classList.toggle('active', index === activeResult);
			});
		}

		function addProduct(product, quantity) {
			var data = grid.getData();
			var emptyRow = data.length === 1 && data[0].every(function (value) {
				return value === '';
			});
			if (emptyRow) {
				grid.setData([[safeText(product.barcode), safeText(product.nama), quantity, '', '']]);
			} else {
				var existingRow = -1;
				data.some(function (row, index) {
					if (String(row[0]) === String(product.barcode)) {
						existingRow = index;
						return true;
					}
					return false;
				});
				if (existingRow >= 0) {
					var oldQuantity = parseInt(data[existingRow][2], 10) || 0;
					grid.setValueFromCoords(2, existingRow, oldQuantity + quantity, true);
				} else {
					grid.insertRow([safeText(product.barcode), safeText(product.nama), quantity, '', '']);
				}
			}
			searchInput.value = '';
			hideResults();
			searchInput.focus();
		}

		function searchProducts(query, callback) {
			var sequence = ++searchSequence;
			if (searchRequest) {
				searchRequest.abort();
			}
			var request = new XMLHttpRequest();
			searchRequest = request;
			request.open('GET', <?php echo json_encode(site_url('main/purchasing/product_search?q=')); ?> + encodeURIComponent(query), true);
			request.onreadystatechange = function () {
				if (request.readyState !== 4 || sequence !== searchSequence ||
					request.status < 200 || request.status >= 300) {
					return;
				}
				try {
					var products = JSON.parse(request.responseText).products || [];
					if (callback) {
						callback(products);
					} else {
						renderResults(products);
					}
				} catch (error) {
					hideResults();
				}
			};
			request.send();
		}

		searchInput.addEventListener('input', function () {
			window.clearTimeout(searchTimer);
			var query = this.value.trim();
			if (!query) {
				hideResults();
				return;
			}
			hideResults();
			searchTimer = window.setTimeout(function () {
				searchProducts(query);
			}, 180);
		});
		searchInput.addEventListener('keydown', function (event) {
			if (event.key === 'ArrowDown' && searchResults.length) {
				event.preventDefault();
				activeResult = (activeResult + 1) % searchResults.length;
				updateActiveResult();
			} else if (event.key === 'ArrowUp' && searchResults.length) {
				event.preventDefault();
				activeResult = activeResult <= 0 ? searchResults.length - 1 : activeResult - 1;
				updateActiveResult();
			} else if (event.key === 'Enter') {
				event.preventDefault();
				event.stopPropagation();
				window.clearTimeout(searchTimer);
				if (searchResults.length && activeResult >= 0) {
					addProduct(searchResults[activeResult], 1);
					return;
				}
				var value = this.value.trim();
				if (!value) {
					return;
				}
				var match = value.match(/^(\d+)\*(.+)$/);
				var quantity = match ? parseInt(match[1], 10) : 1;
				var barcode = match ? match[2].trim() : value;
				if (!barcode || /\s/.test(barcode) || quantity < 1) {
					return;
				}
				searchProducts(barcode, function (products) {
					var exactProduct = products.filter(function (product) {
						return String(product.barcode) === barcode;
					})[0];
					if (exactProduct) {
						addProduct(exactProduct, quantity);
					} else if (products.length) {
						renderResults(products);
					} else if (barcode && !/\s/.test(barcode)) {
						addProduct({ barcode: barcode, nama: '', harga: 0 }, quantity);
					} else {
						hideResults();
					}
				});
			} else if (event.key === 'Escape') {
				hideResults();
			}
		});
		document.getElementById('po-form').addEventListener('submit', function () {
			document.getElementById('items_json').value = JSON.stringify(grid.getData());
		});
		document.getElementById('back-to-orders').addEventListener('click', function (event) {
			var hasItems = grid.getData().some(function (row) {
				return row.some(function (value) {
					return String(value == null ? '' : value).trim() !== '';
				});
			});
			var hasHeaderData = document.getElementById('supplier_id').value !== '' ||
				document.getElementById('notes').value.trim() !== '';
			if ((hasItems || hasHeaderData) &&
				!window.confirm('Draft PO berisi data yang belum tentu tersimpan. Yakin kembali ke daftar tanpa menyimpan?')) {
				event.preventDefault();
			}
		});
		document.getElementById('excel-file').addEventListener('change', function () {
			if (!this.files.length) {
				return;
			}
			var message = document.getElementById('excel-import-message');
			var formData = new FormData();
			formData.append('file', this.files[0]);
			var request = new XMLHttpRequest();
			request.open('POST', <?php echo json_encode(site_url('main/purchasing/import_excel')); ?>, true);
			request.onreadystatechange = function () {
				if (request.readyState !== 4) {
					return;
				}
				var response;
				try {
					response = JSON.parse(request.responseText);
				} catch (error) {
					message.innerHTML = '<div class="text-danger">File gagal dibaca oleh server.</div>';
					return;
				}
				if (request.status < 200 || request.status >= 300) {
					message.innerHTML = '<div class="text-danger"></div>';
					message.firstChild.textContent = response.error || 'Upload gagal.';
					return;
				}
				grid.setData(response.rows.map(function (row) {
					return row.map(safeText);
				}));
				message.innerHTML = '<div class="text-success">Berhasil memuat ' + response.rows.length + ' baris. Simpan PO untuk menyimpan perubahan ke database.</div>';
			};
			request.send(formData);
		});
	}());
	</script>
<?php } else { ?>
	<div class="box box-primary">
		<div class="box-header with-border">
			<h3 class="box-title"><?php echo html_escape($order->order_number); ?></h3>
			<div class="pull-right">
				<button class="btn btn-primary btn-sm" type="submit" form="receipt-form">Simpan Penerimaan</button>
				<a class="btn btn-default btn-sm" href="<?php echo site_url('main/purchasing'); ?>">Kembali ke Purchasing</a>
			</div>
		</div>
		<div class="box-body">
			<p><strong>Supplier:</strong> <?php echo html_escape($order->supplier_name); ?>
				&nbsp; <strong>Tanggal PO:</strong> <?php echo html_escape($order->ordered_at); ?></p>
			<?php if ($order->notes !== '') { ?><p><strong>Catatan PO:</strong> <?php echo html_escape($order->notes); ?></p><?php } ?>

			<form id="receipt-form" method="post" novalidate action="<?php echo site_url('main/purchasing/save_receipt/'.$order->id); ?>">
				<div class="row">
					<div class="col-md-6">
						<div class="form-group">
							<label for="supplier_document">No. surat jalan / dokumen supplier</label>
							<input class="form-control" id="supplier_document" name="supplier_document" maxlength="64" required>
						</div>
					</div>
					<div class="col-md-6">
						<div class="form-group">
							<label for="receipt_notes">Catatan penerimaan</label>
							<input class="form-control" id="receipt_notes" name="receipt_notes">
						</div>
					</div>
				</div>
				<div class="table-responsive">
					<table class="table table-bordered">
						<thead>
							<tr><th>Barcode</th><th>Barang</th><th>Qty diminta</th><th>Sudah diterima</th><th>Sisa permintaan</th><th>Datang sekarang</th><th>Harga satuan</th><th>Subtotal</th><th>Perbandingan</th></tr>
						</thead>
						<tbody>
							<?php foreach ($items as $item) {
								$remaining = (int) $item->qty_ordered - (int) $item->qty_received;
								$comparison = (int) $item->qty_received > (int) $item->qty_ordered ? 'Lebih diterima' :
									($remaining === 0 ? 'Sesuai' : 'Menunggu');
							?>
								<tr>
									<td><?php echo html_escape($item->barcode); ?></td>
									<td><?php echo html_escape($item->product_name); ?></td>
									<td><?php echo number_format((int) $item->qty_ordered); ?></td>
									<td><?php echo number_format((int) $item->qty_received); ?></td>
									<td><?php echo number_format(max(0, $remaining)); ?></td>
									<td><input class="form-control receipt-quantity" type="number" min="0" step="1" name="qty_received[<?php echo (int) $item->id; ?>]" value="0"></td>
									<td><input class="form-control receipt-unit-price" type="number" min="0" step="1" name="unit_price[<?php echo (int) $item->id; ?>]" value="" placeholder="Harga aktual"></td>
									<td class="receipt-line-total">-</td>
									<td><?php echo html_escape($comparison); ?></td>
								</tr>
							<?php } ?>
						</tbody>
						<tfoot>
							<tr><th colspan="7" class="text-right">Total penerimaan ini</th><th id="receipt-total">Rp 0</th><th></th></tr>
						</tfoot>
					</table>
				</div>
				<p class="help-block">Isi harga beli aktual per unit untuk setiap barang yang datang. Subtotal dan total tagihan dihitung dari jumlah diterima x harga satuan. Jumlah lebih dari sisa PO tetap menambah stok sesuai barang yang benar-benar diterima.</p>
			</form>
		</div>
	</div>

	<div class="box box-default">
		<div class="box-header with-border"><h3 class="box-title">Riwayat Penerimaan</h3></div>
		<div class="box-body">
			<p class="help-block">Rincian menunjukkan jumlah sebelum penerimaan, jumlah transaksi, akumulasi, harga satuan, dan subtotal. Riwayat lama yang belum memiliki harga akan ditandai.</p>
			<table class="table table-striped table-bordered">
				<thead><tr><th>No. penerimaan</th><th>Tanggal</th><th>Surat jalan</th><th>Total qty diterima</th><th>Total tagihan</th><th>Catatan</th><th>Rincian barang</th></tr></thead>
				<tbody>
					<?php if (count($receipts) === 0) { ?><tr><td colspan="7" class="text-center">Belum ada penerimaan.</td></tr><?php } ?>
					<?php foreach ($receipts as $receipt) { ?>
						<tr>
							<td><?php echo html_escape($receipt->receipt_number); ?></td>
							<td><?php echo html_escape($receipt->received_at); ?></td>
							<td><?php echo $receipt->supplier_document !== NULL && $receipt->supplier_document !== '' ? html_escape($receipt->supplier_document) : '-'; ?></td>
							<td><?php echo number_format((int) $receipt->total_qty); ?></td>
							<td><?php echo $receipt->has_unpriced_items ? 'Belum lengkap' : 'Rp '.number_format((float) $receipt->total_amount, 0, ',', '.'); ?></td>
							<td><?php echo $receipt->notes !== NULL && $receipt->notes !== '' ? html_escape($receipt->notes) : '-'; ?></td>
							<td>
								<div class="table-responsive">
									<table class="table table-condensed table-bordered">
										<thead>
											<tr><th>Barcode</th><th>Barang</th><th>Qty PO</th><th>Sebelumnya</th><th>Diterima kali ini</th><th>Akumulasi</th><th>Harga satuan</th><th>Subtotal</th></tr>
										</thead>
										<tbody>
											<?php foreach ($receipt->items as $receipt_item) { ?>
												<tr>
													<td><?php echo html_escape($receipt_item->barcode); ?></td>
													<td><?php echo html_escape($receipt_item->product_name); ?></td>
													<td><?php echo number_format($receipt_item->qty_ordered); ?></td>
													<td><?php echo number_format($receipt_item->qty_before); ?></td>
													<td><?php echo number_format($receipt_item->qty_received); ?></td>
													<td><?php echo number_format($receipt_item->qty_after); ?></td>
													<td><?php echo $receipt_item->unit_price === NULL ? 'Belum dicatat' : 'Rp '.number_format($receipt_item->unit_price, 0, ',', '.'); ?></td>
													<td><?php echo $receipt_item->line_total === NULL ? '-' : 'Rp '.number_format((float) $receipt_item->line_total, 0, ',', '.'); ?></td>
												</tr>
											<?php } ?>
										</tbody>
									</table>
								</div>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
	<script>
	(function () {
		var form = document.getElementById('receipt-form');
		if (!form) {
			return;
		}
		var supplierDocument = document.getElementById('supplier_document');
		var quantityInputs = form.querySelectorAll('.receipt-quantity');
		var priceInputs = form.querySelectorAll('.receipt-unit-price');
		var totalElement = document.getElementById('receipt-total');

		function formatRupiah(value) {
			return 'Rp ' + Math.round(value).toLocaleString('id-ID');
		}

		function updateReceiptTotal() {
			var total = 0;
			for (var i = 0; i < quantityInputs.length; i++) {
				var quantity = parseInt(quantityInputs[i].value, 10) || 0;
				var price = parseInt(priceInputs[i].value, 10);
				var hasPrice = !isNaN(price) && price >= 0;
				var subtotal = quantity > 0 && hasPrice ? quantity * price : 0;
				quantityInputs[i].closest('tr').querySelector('.receipt-line-total').textContent =
					quantity > 0 && hasPrice ? formatRupiah(subtotal) : '-';
				total += subtotal;
				priceInputs[i].required = quantity > 0;
			}
			totalElement.textContent = formatRupiah(total);
		}

		form.addEventListener('input', updateReceiptTotal);
		form.addEventListener('submit', function (event) {
			if (!supplierDocument.value.trim()) {
				event.preventDefault();
				supplierDocument.setCustomValidity('Nomor surat jalan wajib diisi.');
				supplierDocument.reportValidity();
				supplierDocument.focus();
				return;
			}
			supplierDocument.setCustomValidity('');
			for (var i = 0; i < quantityInputs.length; i++) {
				var quantity = parseInt(quantityInputs[i].value, 10) || 0;
				if (quantity > 0 && !priceInputs[i].value.trim()) {
					event.preventDefault();
					priceInputs[i].setCustomValidity('Harga satuan wajib diisi untuk barang yang diterima.');
					priceInputs[i].reportValidity();
					priceInputs[i].focus();
					return;
				}
				priceInputs[i].setCustomValidity('');
			}
		});
		supplierDocument.addEventListener('input', function () {
			supplierDocument.setCustomValidity('');
		});
		for (var i = 0; i < priceInputs.length; i++) {
			priceInputs[i].addEventListener('input', function () {
				this.setCustomValidity('');
			});
		}
		updateReceiptTotal();
	}());
	</script>
<?php } ?>
