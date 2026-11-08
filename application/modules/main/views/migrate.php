<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>
<div class="callout callout-warning">
	<h4>Backup dahulu, jalankan pada waktu maintenance</h4>
	<p><a class="btn btn-default" href="<?php echo site_url('main/database_tools'); ?>">Buat / unduh backup database</a></p>
	<p>Hentikan transaksi pengguna dan buat backup yang dapat dipulihkan. MySQL dapat menyimpan sebagian perubahan DDL sebelum error; runner tidak menyediakan rollback otomatis.</p>
	<p>Database dasar Simpleton dan akun admin harus sudah terpasang. Migrasi modul dan seed akun baku dijalankan berurutan. SQL demo, impor laporan, dan PIN Telegram tidak dijalankan.</p>
	<p>Database lama tanpa riwayat runner akan diperiksa/dilengkapi melalui SQL yang aman dijalankan ulang, bukan langsung dianggap selesai. Status selesai berarti SQL berhasil; bukan jaminan semua data lama sudah benar.</p>
</div>
<?php
$pending = 0;
$changed = FALSE;
foreach ($rows as $row) {
	if ($row['status'] !== 'applied') { $pending++; }
	$changed = $changed || $row['changed'];
}
?>
<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title"><?php echo $pending; ?> migrasi belum selesai</h3></div>
	<div class="box-body">
		<p>Setiap klik menjalankan satu file berikutnya agar request web tidak terlalu panjang. Jika gagal, perbaiki penyebabnya lalu ulangi file yang sama. File yang sudah selesai tidak dijalankan lagi.</p>
		<p>File SQL dibaca otomatis dari <code>application/schema_migrations</code> dan diurutkan berdasarkan nomor. Tambahkan <code>023_nama_perubahan.sql</code> untuk update berikutnya; tidak perlu mengedit daftar konfigurasi. Nomor tiga digit harus unik dan lebih besar dari migrasi sebelumnya.</p>
		<p>Jangan mengubah atau mengganti nama file migrasi yang sudah dicatat. Checksum mengabaikan perbedaan baris Windows/Linux.</p>
		<?php if ($changed) { ?><div class="alert alert-danger">Checksum SQL berubah. Eksekusi diblokir sampai file asli dipulihkan.</div><?php } ?>
		<form method="post" action="<?php echo site_url('main/migrate/run'); ?>">
			<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
			<div class="checkbox"><label><input type="checkbox" name="backup_confirmed" value="1" required> Backup sudah dibuat dan transaksi pengguna sudah dihentikan.</label></div>
			<button class="btn btn-primary" type="submit"<?php echo !$pending || $changed ? ' disabled' : ''; ?>>Jalankan migrasi berikutnya</button>
			<a class="btn btn-default" href="<?php echo site_url('main/migrate'); ?>">Muat ulang status</a>
		</form>
	</div>
	<div class="box-body table-responsive">
		<table class="table table-bordered">
			<thead><tr><th>ID / File</th><th>Status</th><th>Selesai</th><th>Error terakhir</th></tr></thead>
			<tbody><?php foreach ($rows as $row) { ?>
				<tr>
					<td><?php echo html_escape($row['migration_id']); ?><br><small><?php echo html_escape($row['filename']); ?></small></td>
					<td><?php echo html_escape($row['status']); ?></td>
					<td><?php echo html_escape($row['completed_at']); ?></td>
					<td><?php echo html_escape($row['error_message']); ?></td>
				</tr>
			<?php } ?></tbody>
		</table>
	</div>
</div>
