<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php if ($notice) { ?><div class="alert alert-success" role="status"><?php echo html_escape($notice); ?></div><?php } ?>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></div><?php } ?>
<div class="callout callout-warning">
	<h4>Database: <?php echo html_escape($database); ?></h4>
	<p>Hentikan transaksi dan lakukan pada waktu maintenance. Backup mencakup database, bukan file lampiran/foto. File SQL berisi data sensitif: simpan salinan unduhan secara aman di luar server.</p>
	<p>Restore hanya menerima backup yang dibuat aplikasi untuk database ini dan checksum yang cocok. Daftar tabel, view, routine, event, dan trigger harus sama; perubahan daftar objek memerlukan pemulihan terencana. Restore membuat backup pengaman terlebih dahulu, tetapi tidak memiliki rollback otomatis bila proses gagal/timeout. Setelah berhasil, login ulang.</p>
</div>
<div id="database-operation-status" class="alert alert-info" role="status" aria-live="polite" tabindex="-1" style="display:none;">
	<p><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> <strong id="database-operation-label"></strong></p>
	<p>Permintaan sudah dikirim. Waktu berjalan: <span id="database-operation-seconds">0</span> detik. Jangan tutup atau muat ulang halaman, dan jangan klik ulang. Hasil akan tampil setelah server selesai.</p>
	<div class="progress"><div class="progress-bar progress-bar-striped active" role="progressbar" aria-label="Proses database sedang berjalan" style="width:100%"></div></div>
	<p>Indikator ini menunjukkan proses berjalan, bukan persentase selesai. Jika koneksi terputus/timeout, proses server mungkin masih berjalan; periksa status sebelum mengulang.</p>
</div>
<div class="box box-primary">
	<div class="box-header with-border"><h3 class="box-title">Buat backup sebelum migrasi</h3></div>
	<div class="box-body">
		<form method="post" data-database-operation="Backup dan kompresi ZIP sedang diproses..." action="<?php echo site_url('main/database_tools/backup'); ?>">
			<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
			<label><input type="checkbox" name="maintenance_confirmed" value="1" required> Transaksi pengguna sudah dihentikan.</label>
			<button class="btn btn-primary" type="submit">Buat backup ZIP</button>
		</form>
	</div>
</div>
<div class="box box-default"><div class="box-body table-responsive">
	<table class="table table-bordered"><thead><tr><th>Backup</th><th>Ukuran</th><th>Unduh / Restore</th></tr></thead><tbody>
	<?php foreach ($records as $record) { ?>
	<tr><td><?php echo html_escape($record['id']); ?><br><?php echo html_escape($record['created_at']); ?></td>
		<td><?php echo number_format($record['bytes'] / 1048576, 2); ?> MB
			<?php if (isset($record['sql_bytes'])) { ?><br><small>SQL sebelum kompresi: <?php echo number_format($record['sql_bytes'] / 1048576, 2); ?> MB</small><?php } ?>
		</td><td>
			<form method="post" action="<?php echo site_url('main/database_tools/download'); ?>">
				<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
				<input type="hidden" name="backup_id" value="<?php echo html_escape($record['id']); ?>">
				<button class="btn btn-default btn-sm" type="submit">Unduh <?php echo isset($record['format']) && $record['format'] === 'zip' ? 'ZIP' : 'SQL'; ?></button>
			</form>
			<details><summary>Restore backup ini (menimpa data)</summary>
				<form method="post" data-database-operation="Backup pengaman dan restore database sedang diproses..." action="<?php echo site_url('main/database_tools/restore'); ?>">
					<input type="hidden" name="form_token" value="<?php echo html_escape($form_token); ?>">
					<input type="hidden" name="backup_id" value="<?php echo html_escape($record['id']); ?>">
					<div class="form-group"><label>Konfirmasi nama database<input class="form-control" name="database_confirmation" required autocomplete="off"></label></div>
					<div class="form-group"><label>Password admin saat ini<input class="form-control" type="password" name="admin_password" required autocomplete="current-password"></label></div>
					<label><input type="checkbox" name="maintenance_confirmed" value="1" required> Transaksi dihentikan; saya setuju menimpa data saat ini.</label>
					<button class="btn btn-danger btn-sm" type="submit">Restore database</button>
				</form>
			</details>
		</td></tr>
	<?php } ?>
	</tbody></table>
</div></div>
