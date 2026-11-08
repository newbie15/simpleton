<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row">
	<div class="col-md-12">
		<div class="callout callout-info">
			<h4>Sebelum mulai input transaksi</h4>
			<p>Administrator menjalankan pembaruan skema melalui <a href="<?php echo site_url('main/migrate'); ?>">Migrasi Database</a> sebelum mengikuti panduan ini.</p>
			<p>Tetapkan satu tanggal cutover, lakukan backup database lama dan baru, lalu rekonsiliasi saldo pada tanggal tersebut. Uji proses migrasi di salinan database terlebih dahulu; jangan mengimpor data langsung ke database produksi tanpa pemeriksaan.</p>
		</div>
	</div>
</div>

<div class="box box-primary">
	<div class="box-header with-border">
		<h3 class="box-title">Urutan setup yang disarankan</h3>
	</div>
	<div class="box-body">
		<ol class="setup-checklist">
			<li>
				<h4>Siapkan lingkungan, akses, dan tanggal cutover</h4>
				<p>Pastikan backup dapat dipulihkan, akun administrator tersedia, hak akses grup sudah dibagi, dan staf memahami tanggal terakhir pencatatan di sistem lama serta tanggal mulai di Simpleton.</p>
				<p><a class="btn btn-xs btn-default" href="<?php echo site_url('auth/index'); ?>">Periksa user</a></p>
			</li>
			<li>
				<h4>Rapikan master karyawan dan produk</h4>
				<p>Samakan NPK, nama, barcode, nama barang, satuan, kategori, harga beli, harga jual, dan status aktif. Barcode perlu konsisten dengan label yang akan dipindai. Lengkapi data konversi dus/kemasan ke satuan jual bila digunakan.</p>
				<p>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/data_update'); ?>">Impor stok &amp; karyawan</a>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/product_conversion'); ?>">Konversi kemasan</a>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/karyawan'); ?>">Data karyawan</a>
				</p>
				<div class="alert alert-warning">
					<strong>Perhatian impor stok:</strong> file stok di menu Update Data mengganti seluruh isi tabel stok, bukan menambahkan atau memperbarui sebagian barang. Pastikan file berisi semua stok yang memang akan dipakai dan lakukan backup terlebih dahulu. Format kolom: <code>barcode, nama, jumlah, harga, distributor, ppn</code>.
				</div>
				<p>Impor karyawan menggunakan kolom <code>npk, nama, bagian, limit belanja, join date</code>. NPK aktif yang tidak ada dalam file akan ditandai resign, jadi gunakan daftar lengkap karyawan aktif.</p>
			</li>
			<li>
				<h4>Masukkan supplier dan cek pengaturan pajak</h4>
				<p>Siapkan nama dan identitas supplier, lalu pastikan tarif PPN sesuai kebijakan usaha sebelum transaksi penjualan pertama.</p>
				<p>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/purchasing/suppliers'); ?>">Data supplier</a>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/vat_settings'); ?>">Pengaturan PPN</a>
				</p>
			</li>
			<li>
				<h4>Tetapkan saldo awal pada tanggal cutover</h4>
				<p>Siapkan neraca saldo dari sistem lama: kas dan bank per rekening, piutang, persediaan, hutang, simpanan/kewajiban, modal, serta akun lain yang masih memiliki saldo. Periksa bagan akun dan gunakan satu jurnal pembukaan yang seimbang; debit harus sama dengan kredit. Nilai persediaan dalam jurnal harus cocok dengan total kuantitas × harga pokok dari daftar stok.</p>
				<p>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/accounting_unified?tab=accounts'); ?>">Periksa bagan akun</a>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/accounting_unified?tab=input'); ?>">Input jurnal pembukaan</a>
				</p>
				<p class="text-muted">Bagan akun default disediakan melalui seed SQL <code>application/schema_migrations/002_foundation.sql</code>, bukan hardcode pada halaman ini. Administrator menjalankan migrasi melalui halaman Migrasi Database sesuai urutan file; <code>007_petty_cash.sql</code> tetap menambahkan akun 1106/1107 untuk instalasi lama dan membuat skema kas. Seed menggunakan <code>INSERT IGNORE</code>, jadi akun yang sudah ada tidak ditimpa—periksa tipe dan saldo normal bila database target pernah dikustomisasi. Impor stok hanya mengisi daftar stok, bukan membuat jurnal persediaan. Pastikan pemetaan akun sistem lama cocok sebelum membuat jurnal. Jangan mengimpor ulang transaksi historis sebagai jurnal baru tanpa rekonsiliasi dan rencana referensi yang jelas.</p>
			</li>
			<li>
				<h4>Verifikasi stok fisik dan hutang supplier</h4>
				<p>Lakukan stok opname pada tanggal cutover dan cocokkan jumlah serta harga pokok. Jika hutang supplier lama harus bisa dibayar per nota melalui antrean pembayaran, setiap hutang perlu ditautkan ke nota penerimaan dan jurnal pembelian yang sesuai—saldo pembukaan umum pada akun hutang saja tidak membuat rincian hutang per nota.</p>
				<p>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/stock_opname'); ?>">Stok opname</a>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/purchasing'); ?>">PO &amp; penerimaan barang</a>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/management_payments'); ?>">Pembayaran supplier</a>
				</p>
			</li>
			<li>
				<h4>Siapkan kas dan rekening bank</h4>
				<p>Periksa dana kasir, kas utama, dan petty cash; tetapkan pemegang serta batas petty cash. Masukkan saldo awal kas/bank sebagai bagian dari jurnal pembukaan. Untuk saldo petty cash, buat/setujui transaksi pendanaan awal melalui modul kas agar saldo sub-ledger dana ikut tercatat; jangan mencatat saldo yang sama dua kali di jurnal pembukaan. Siapkan juga saldo awal rekening koran untuk rekonsiliasi pertama.</p>
				<p>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/petty_cash_new'); ?>">Kas &amp; petty cash</a>
					<a class="btn btn-xs btn-default" href="<?php echo site_url('main/bank_reconciliation'); ?>">Rekonsiliasi bank</a>
				</p>
			</li>
			<li>
				<h4>Uji proses sebelum go-live</h4>
				<p>Dengan user dan data uji, coba satu penjualan, satu penerimaan pembelian, satu pembayaran, satu retur, dan satu opname. Cocokkan stok, saldo akun, jurnal, dan laporan terhadap sumbernya sebelum kasir mulai bertransaksi sungguhan.</p>
				<p><a class="btn btn-xs btn-primary" href="<?php echo site_url('main/accounting_unified'); ?>">Periksa laporan akuntansi</a></p>
			</li>
		</ol>
	</div>
</div>

<div class="box box-warning">
	<div class="box-header with-border">
		<h3 class="box-title">Batasan migrasi yang perlu diketahui</h3>
	</div>
	<div class="box-body">
		<ul>
			<li>Menu Update Data menyediakan impor stok dan karyawan; impor stok mengganti tabel stok secara keseluruhan.</li>
			<li>Belum tersedia impor umum untuk riwayat transaksi penjualan, simpanan anggota, atau hutang supplier per nota. Data tersebut memerlukan migrasi terencana dan validasi khusus; jangan memasukkannya sebagai transaksi operasional palsu.</li>
			<li>Jurnal pembukaan mengatur saldo buku besar, tetapi tidak otomatis membuat rincian barang, penerimaan supplier, saldo anggota, atau histori transaksi yang dapat ditelusuri di modul operasional.</li>
			<li>Simpan arsip sistem lama yang hanya-baca, file sumber, hasil rekonsiliasi, tanggal cutover, dan bukti persetujuan saldo pembukaan.</li>
		</ul>
	</div>
</div>

<style>
.setup-checklist { padding-left: 24px; }
.setup-checklist > li { padding: 0 0 16px 8px; }
.setup-checklist > li h4 { margin: 0 0 6px; font-weight: 600; }
.setup-checklist > li p { margin-bottom: 8px; }
</style>
