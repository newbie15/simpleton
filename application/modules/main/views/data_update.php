<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="container-fluid">
	<h3>Update Data Stok</h3>
	<p>Unggah file XLS, XLSX, CSV, atau TXT sesuai kolom pada contoh. File maksimal 100 MB.</p>
	<p class="text-warning">File stok akan mengganti seluruh data stok yang ada setelah lolos validasi.</p>
	<div class="form-group">
		<label for="stok">Pilih file stok</label>
		<input type="file" name="stok" id="stok" accept=".xls,.xlsx,.csv,.txt">
	</div>
	<div id="dropBox" class="update-status" role="status" aria-live="polite"></div>
	<div id="stok-progress" class="progress update-progress" style="display:none;">
		<div class="progress-bar progress-bar-success" role="progressbar"
			aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%">
			<span class="sr-only">0%</span>
		</div>
	</div>
	<br>
	<img class="img-responsive" src="<?php echo html_escape($sample_stock_url); ?>" alt="Contoh format file update stok">
	<hr>
	<h3>Update Data Karyawan</h3>
	<p class="text-warning">
		NPK karyawan aktif yang tidak tercantum di file akan ditandai resign, bukan dihapus.
		Konfirmasi akan diminta sebelum file diproses.
	</p>
	<div class="form-group">
		<label for="karyawan">Pilih file karyawan</label>
		<input type="file" name="karyawan" id="karyawan" accept=".xls,.xlsx,.csv,.txt">
	</div>
	<div id="dropBox1" class="update-status" role="status" aria-live="polite"></div>
	<div id="karyawan-progress" class="progress update-progress" style="display:none;">
		<div class="progress-bar progress-bar-success" role="progressbar"
			aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" style="width:0%">
			<span class="sr-only">0%</span>
		</div>
	</div>
	<br>
	<img class="img-responsive" src="<?php echo html_escape($sample_employee_url); ?>" alt="Contoh format file update karyawan">
</div>
<div id="data-update" data-upload-url="<?php echo html_escape($upload_url); ?>"></div>
