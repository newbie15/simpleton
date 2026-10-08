<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>

<?php if (!empty($notice)) { ?>
	<div class="alert alert-success"><?php echo html_escape($notice); ?></div>
<?php } ?>
<?php if (!empty($error)) { ?>
	<div class="alert alert-danger"><?php echo html_escape($error); ?></div>
<?php } ?>

<style>
	.conversion-product-search {
		position: relative;
	}
	.conversion-product-results {
		position: absolute;
		top: 100%;
		left: 0;
		z-index: 2002;
		display: none;
		width: 100%;
		max-height: 260px;
		overflow-y: auto;
	}
</style>

<div class="row">
	<div class="col-md-12">
		<div class="box box-primary">
			<div class="box-header with-border">
				<h3 class="box-title">Tambah Relasi Kemasan</h3>
			</div>
			<form method="post" action="<?php echo site_url('main/product_conversion/save'); ?>">
				<div class="box-body">
					<p class="help-block">Kedua barcode harus sudah terdaftar di master Stok. Rasio menunjukkan jumlah pcs dalam satu kemasan.</p>
					<div class="row">
						<div class="col-sm-3">
							<div class="form-group">
								<label for="barcode_dus">Barcode kemasan</label>
								<div class="conversion-product-search">
									<input class="form-control conversion-product-input" id="barcode_dus" name="barcode_dus" maxlength="64" autocomplete="off" required>
									<div class="conversion-product-results list-group"></div>
								</div>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-group">
								<label for="barcode_pcs">Barcode pcs</label>
								<div class="conversion-product-search">
									<input class="form-control conversion-product-input" id="barcode_pcs" name="barcode_pcs" maxlength="64" autocomplete="off" required>
									<div class="conversion-product-results list-group"></div>
								</div>
							</div>
						</div>
						<div class="col-sm-3">
							<div class="form-group">
								<label for="nama_kemasan">Nama kemasan</label>
								<input class="form-control" id="nama_kemasan" name="nama_kemasan" maxlength="64" placeholder="Dus isi 24" required>
							</div>
						</div>
						<div class="col-sm-2">
							<div class="form-group">
								<label for="rasio">Isi (pcs)</label>
								<input class="form-control" type="number" id="rasio" name="rasio" min="2" max="2147483647" step="1" required>
							</div>
						</div>
						<div class="col-sm-1">
							<div class="form-group">
								<label>&nbsp;</label>
								<button class="btn btn-primary btn-block" type="submit">Simpan</button>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
</div>

<div class="box box-default">
	<div class="box-header with-border"><h3 class="box-title">Relasi Kemasan Terdaftar</h3></div>
	<div class="box-body table-responsive">
		<table class="table table-striped table-bordered">
			<thead>
				<tr>
					<th>Barang pcs</th>
					<th>Barcode pcs</th>
					<th>Kemasan</th>
					<th>Barcode kemasan</th>
					<th>Isi</th>
					<th>Aksi</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($conversions as $conversion) { ?>
					<tr>
						<td><?php echo html_escape($conversion->nama_barang); ?></td>
						<td><?php echo html_escape($conversion->barcode_pcs); ?></td>
						<td><?php echo html_escape($conversion->nama_kemasan); ?></td>
						<td><?php echo html_escape($conversion->barcode_dus); ?></td>
						<td><?php echo (int) $conversion->rasio; ?> pcs</td>
						<td>
							<details>
								<summary>Edit</summary>
								<form method="post" action="<?php echo site_url('main/product_conversion/save/'.(int) $conversion->id); ?>">
									<div class="form-group">
										<label>Barcode kemasan</label>
										<div class="conversion-product-search">
											<input class="form-control conversion-product-input" name="barcode_dus" maxlength="64" value="<?php echo html_escape($conversion->barcode_dus); ?>" autocomplete="off" required>
											<div class="conversion-product-results list-group"></div>
										</div>
									</div>
									<div class="form-group">
										<label>Barcode pcs</label>
										<div class="conversion-product-search">
											<input class="form-control conversion-product-input" name="barcode_pcs" maxlength="64" value="<?php echo html_escape($conversion->barcode_pcs); ?>" autocomplete="off" required>
											<div class="conversion-product-results list-group"></div>
										</div>
									</div>
									<div class="form-group">
										<label>Nama kemasan</label>
										<input class="form-control" name="nama_kemasan" maxlength="64" value="<?php echo html_escape($conversion->nama_kemasan); ?>" required>
									</div>
									<div class="form-group">
										<label>Isi (pcs)</label>
										<input class="form-control" type="number" name="rasio" min="2" max="2147483647" step="1" value="<?php echo (int) $conversion->rasio; ?>" required>
									</div>
									<button class="btn btn-primary btn-xs" type="submit">Simpan perubahan</button>
								</form>
								<form method="post" action="<?php echo site_url('main/product_conversion/delete/'.(int) $conversion->id); ?>" onsubmit="return window.confirm('Hapus relasi konversi ini? Stok barang tidak akan berubah.');" style="margin-top:8px;">
									<button class="btn btn-danger btn-xs" type="submit">Hapus relasi</button>
								</form>
							</details>
						</td>
					</tr>
				<?php } ?>
				<?php if (count($conversions) === 0) { ?>
					<tr><td colspan="6" class="text-center">Belum ada relasi konversi kemasan.</td></tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
</div>

<script>
(function () {
	var searchUrl = <?php echo json_encode(site_url('main/product_conversion/product_search?q=')); ?>;
	var inputs = document.querySelectorAll('.conversion-product-input');

	function hideResults(container) {
		var results = container.querySelector('.conversion-product-results');
		results.style.display = 'none';
		results.innerHTML = '';
	}

	function showMessage(container, message) {
		var results = container.querySelector('.conversion-product-results');
		results.innerHTML = '';
		var option = document.createElement('div');
		option.className = 'list-group-item text-muted';
		option.textContent = message;
		results.appendChild(option);
		results.style.display = 'block';
	}

	function showResults(input, products) {
		var container = input.parentNode;
		var results = container.querySelector('.conversion-product-results');
		results.innerHTML = '';
		if (!products.length) {
			hideResults(container);
			return;
		}

		products.forEach(function (product) {
			var option = document.createElement('button');
			option.type = 'button';
			option.className = 'list-group-item';
			option.style.width = '100%';
			option.style.textAlign = 'left';
			option.textContent = product.barcode + ' - ' + product.nama;
			option.addEventListener('mousedown', function (event) {
				event.preventDefault();
			});
			option.addEventListener('click', function () {
				input.value = product.barcode;
				input.dispatchEvent(new Event('change', { bubbles: true }));
				var packageName = input.form.querySelector('[name="nama_kemasan"]');
				if (input.name === 'barcode_dus' && packageName && !packageName.value.trim()) {
					packageName.value = product.nama;
				}
				hideResults(container);
			});
			results.appendChild(option);
		});
		results.style.display = 'block';
	}

	Array.prototype.forEach.call(inputs, function (input) {
		var request = null;
		var timer = null;
		var sequence = 0;
		var container = input.parentNode;

		input.addEventListener('input', function () {
			var term = input.value.trim();
			sequence++;
			var currentSequence = sequence;
			window.clearTimeout(timer);
			if (request) {
				request.abort();
				request = null;
			}
			if (term.length < 1) {
				hideResults(container);
				return;
			}
			timer = window.setTimeout(function () {
				var xhr = new XMLHttpRequest();
				request = xhr;
				xhr.open('GET', searchUrl + encodeURIComponent(term), true);
				xhr.onreadystatechange = function () {
					if (xhr.readyState !== 4 || currentSequence !== sequence) {
						return;
					}
					request = null;
					if (xhr.status < 200 || xhr.status >= 300) {
						showMessage(container, 'Pencarian barang gagal. Coba lagi.');
						return;
					}
					try {
						var response = JSON.parse(xhr.responseText);
						showResults(input, response.products || []);
					} catch (error) {
						showMessage(container, 'Data pencarian barang tidak dapat dibaca.');
					}
				};
				xhr.send();
			}, 180);
		});

		input.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				hideResults(container);
			} else if (event.key === 'ArrowDown') {
				var firstOption = container.querySelector('.conversion-product-results button');
				if (firstOption) {
					event.preventDefault();
					firstOption.focus();
				}
			}
		});
		input.addEventListener('blur', function () {
			window.setTimeout(function () {
				hideResults(container);
			}, 150);
		});
	});
}());
</script>
