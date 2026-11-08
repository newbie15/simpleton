# Dokumentasi Skema Database Fondasi Koperasi Simpleton (Fase 3)

Dokumen ini mencatat skema tabel baru dan perluasan tabel eksisting yang diterapkan melalui berkas migrasi **[application/sql/phase3_foundation.sql](file:///g:/xampp/htdocs/simpleton/application/sql/phase3_foundation.sql)**.

## Menjalankan migrasi lewat web

- Login sebagai **admin**, buka **Back End > Administrasi Sistem > Migrasi Database** (`main/migrate`).
- Database dasar Simpleton (`users`, `groups`, `users_groups`, `stok`, `karyawan`, `penjualan`) dan akun admin harus tersedia. Halaman bukan installer database kosong.
- Buat backup yang dapat dipulihkan dan hentikan transaksi selama maintenance. Konfirmasikan checkbox, lalu klik **Jalankan migrasi berikutnya** sampai semua file berstatus `applied`. Satu file dijalankan per request untuk membatasi durasi HTTP.
- Runner membaca otomatis file SQL pada [application/schema_migrations](../../application/schema_migrations) dan mengurutkannya secara leksikografis. Nama menggunakan format sequential `NNN_nama_perubahan.sql` dengan nomor tiga digit unik (001–999), nama huruf kecil/angka/underscore, dan ID maksimal 64 karakter. Purchasing `001` mendahului fondasi `002` karena fondasi memperluas tabel penerimaan.
- Untuk tambahan, cukup buat `023_nama_perubahan.sql` (berikutnya `024`, dst.) di folder tersebut lalu muat ulang halaman. Jangan menyisipkan nomor lebih kecil setelah migrasi berikutnya sudah diterapkan. File SQL bernama tidak valid atau nomor duplikat memblokir runner, bukan diabaikan diam-diam.
- ID 001–022 dipertahankan dari runner sebelumnya dan isi SQL tidak berubah saat dipindahkan, sehingga checksum/riwayat lama tetap cocok. Daftar konfigurasi manual tidak dipakai lagi. Folder `application/sql` tetap menyimpan skrip legacy untuk referensi/pemasangan manual; runner **tidak membaca** folder itu. Untuk perubahan baru, sumber yang dijalankan adalah folder migrasi khusus; jangan mengedit snapshot legacy dengan harapan runner menjalankannya.
- Riwayat disimpan dalam `schema_migrations`, terpisah dari migration library CodeIgniter yang lama: ID, checksum, status, waktu, admin pelaksana, dan error. Lock database mencegah dua runner berjalan bersamaan.
- File selesai dilewati. Database lama tanpa riwayat runner dilengkapi dengan SQL repeat-safe; status tidak di-baseline secara otomatis hanya dari keberadaan tabel.
- Kegagalan menghentikan urutan. Setelah penyebab diperbaiki, file gagal dapat dicoba ulang. Jika proses timeout, baris bisa tetap `running`; setelah request lama berakhir dan lock terlepas, jalankan ulang. DDL MySQL dapat tersimpan sebagian: **tidak ada rollback otomatis**.
- Jangan mengedit file yang sudah dicatat; checksum menolak file berubah (perbedaan CRLF/LF diabaikan). Tambahkan migrasi baru setelah urutan terakhir untuk update berikutnya.
- SQL demo, impor laporan dan migrasi PIN Telegram berisi data/default khusus sehingga tidak dimasukkan. Tidak ada CLI, SQL bebas, migrasi otomatis pada page load, atau tombol reset database.
- Seed akun inti ikut fondasi; seed tidak menimpa akun existing. Tetap rekonsiliasi data dan akun yang sudah dikustomisasi sebelum go-live.

### Backup dan restore dari browser

Kategori **Administrasi Sistem** memuat Setup Awal, Migrasi Database, dan **Backup & Restore Database** (`main/database_tools`), semuanya khusus admin. Halaman migrasi menautkan langsung ke backup; centang backup tetap merupakan konfirmasi operator, bukan pemeriksaan bahwa ada salinan offsite.

- Pengaturan tool `mysqldump`/`mysql` dan folder penyimpanan berada di [database_backup.php](../../application/config/database_backup.php). Default mengikuti tata letak XAMPP tanpa drive yang di-hardcode. Pada server lain, sesuaikan path binary dan folder **di luar semua document root/alias web**, serta batasi ACL folder ke akun layanan dan administrator.
- Backup menggunakan koneksi database aplikasi dan `mysqldump --lock-all-tables`, termasuk tabel, view, routine, event, dan trigger. Akun database memerlukan izin yang sesuai; kesalahan tool dilaporkan, bukan dianggap sukses. Password koneksi menggunakan file opsi sementara, bukan argumen command line. File sementara dihapus setelah proses.
- Hentikan transaksi pengguna selama backup/restore. Operasi ini sinkron melalui web; batas waktu PHP/Apache tetap berlaku. Untuk database besar, atur timeout server sebelum maintenance. Jangan mulai operasi kedua setelah timeout sebelum memastikan proses pertama sudah selesai.
- SQL dan metadata checksum disimpan di folder privat; unduhan hanya melalui POST bertoken admin. File tidak dienkripsi: amankan penyimpanan dan salinan offsite. Backup **tidak** menyertakan lampiran, foto, konfigurasi aplikasi, atau file sumber.
- Backup baru dikompres sebagai ZIP berisi satu SQL (memerlukan ekstensi PHP ZIP). SQL mentah dihapus setelah kompresi; metadata menyimpan ukuran asli, ukuran ZIP, checksum ZIP dan checksum SQL. Unduhan memakai ZIP, sedangkan backup SQL lama tetap dapat diunduh dan dipulihkan. Restore ZIP mengekstrak SQL secara streaming ke file privat sementara, memeriksa checksum, dan menghapusnya setelah proses.
- Setelah klik backup/restore, halaman menampilkan spinner, indikator proses tak tentu, waktu berjalan, dan menonaktifkan tombol untuk mencegah klik ganda. Tidak ada persentase buatan: proses dump/kompresi tetap sinkron dan hasil muncul saat server selesai. Jangan muat ulang/tutup halaman; timeout koneksi tidak membuktikan proses server berhenti.
- Restore tidak menerima upload SQL. Hanya backup tercatat milik database aktif dengan checksum yang cocok diterima. Nama database, password admin, serta persetujuan maintenance wajib dikonfirmasi.
- Daftar tabel/view/routine/event/trigger harus sama agar restore tidak meninggalkan objek tambahan yang tidak ada di snapshot. Restore lintas versi skema dengan objek berbeda diblokir; gunakan prosedur recovery terencana untuk kasus tersebut.
- Sebelum menimpa, aplikasi membuat backup pengaman baru. Restore gagal dapat meninggalkan data parsial; nama backup pengaman ditampilkan dan error dicatat. Tidak ada rollback otomatis. Restore berhasil mengeluarkan admin dan mengharuskan login ulang.
- Lock yang sama dengan runner migrasi mencegah backup/restore/migrasi bersamaan, tetapi bukan mode maintenance otomatis untuk seluruh pengguna. Tidak ada penghapusan/retensi otomatis; kelola salinan privat sesuai kebijakan organisasi.

---

## 1. Tabel-Tabel Baru

### 1.1 `product_conversions` (Konversi Kemasan Dus vs Pcs)
Menghubungkan barcode kemasan besar (Dus/Pack/Karton) dengan barcode satuan terkecil (Pcs).
* `id` (int, PK, auto_increment)
* `barcode_dus` (varchar 64, UNIQUE): Barcode yang tertera pada kemasan dus.
* `barcode_pcs` (varchar 64, INDEX): Barcode produk satuan terkecil di tabel `stok`.
* `nama_kemasan` (varchar 64): Nama label (misal: "Dus isi 24", "Pack isi 6").
* `rasio` (int): Jumlah pcs yang terdapat dalam 1 kemasan dus.
* `created_at`, `updated_at` (datetime)

### 1.2 `vouchers` & `voucher_usages` (Master & Penggunaan Voucher)
* **`vouchers`**:
  * `id` (int, PK, auto_increment)
  * `kode` (varchar 32, UNIQUE): Kode promo/voucher (misal: `HEMAT10K`).
  * `nama` (varchar 128): Deskripsi voucher.
  * `tipe` (enum): `diskon_nominal`, `diskon_persen`, `cashback`.
  * `nilai` (decimal 15,2): Nilai nominal potongan atau persentase diskon.
  * `min_belanja` (int): Minimal total belanja agar voucher dapat digunakan.
  * `max_diskon` (decimal 15,2): Batas maksimum diskon jika tipe adalah persen.
  * `kuota` (int): Batas kuota klaim voucher (0 = tidak terbatas).
  * `terpakai` (int): Jumlah voucher yang telah digunakan.
  * `tgl_mulai`, `tgl_kadaluarsa` (datetime): Masa berlaku.
  * `is_active` (tinyint 1): Status aktif/non-aktif.
* **`voucher_usages`**:
  * `id` (int, PK, auto_increment)
  * `voucher_id` (int, FK ke `vouchers.id`)
  * `nota` (varchar 64, INDEX): Nomor nota transaksi kasir.
  * `npk_anggota` (varchar 32): NPK anggota yang menggunakan voucher.
  * `nominal_diskon` (decimal 15,2): Nilai diskon yang dinikmati.
  * `used_at` (datetime): Waktu penggunaan.

Kupon fisik berserial disimpan terpisah dari voucher master. Pasang migrasi **[application/sql/voucher_coupons.sql](application/sql/voucher_coupons.sql)** untuk membuat `voucher_coupons` dan kolom opsional `voucher_usages.voucher_coupon_id`. Serial unik hanya dapat ditebus sekali; NPK penerima boleh ditautkan pada impor. XLS/XLSX menggunakan kolom `SERIAL_KUPON` dan `NPK` (opsional), ditinjau sebelum konfirmasi impor.

## Import Data Update

Menu **Update Data** menerima file sesuai contoh format stok dan karyawan yang ditampilkan pada halaman. Baris dan header divalidasi sebelum perubahan dimulai; seluruh penggantian stok berjalan dalam transaksi agar data lama tetap ada jika impor gagal.

Untuk impor karyawan dan pemeriksaan billing, jalankan **[application/sql/karyawan_employment_status.sql](../../application/sql/karyawan_employment_status.sql)** satu kali jika kolom belum tersedia. Migrasi ini menambahkan status kerja (`is_active`, `resign_date`) serta data payroll opsional (`nama_bank`, `nomor_rekening`). NPK aktif yang tidak ada pada file update akan ditandai `is_active = 0` dengan `resign_date` tanggal impor. Data karyawan tidak dihapus, dan karyawan yang kembali tercantum di file diaktifkan kembali. Kolom rekening tersedia untuk persiapan, tetapi belum ikut diekspor.

### 1.3 `penjualan_pembayaran` (Header Pembayaran Transaksi POS)
Mencatat informasi menyeluruh mengenai metode pembayaran, uang tunai, kembalian, referensi EDC/transfer, dan voucher per nomor nota:
* `id` (int, PK, auto_increment)
* `nota` (varchar 64, UNIQUE): Nomor nota transaksi (relasi ke `penjualan.nota`).
* `metode_pembayaran` (varchar 32): `tunai`, `edc`, `transfer`, `qris`, `potong_gaji`.
* `total_tagihan` (decimal 15,2): Total bersih yang harus dibayar.
* `nominal_bayar` (decimal 15,2): Jumlah uang tunai yang diserahkan atau nominal transfer/gesek.
* `kembalian` (decimal 15,2): Nilai kembalian tunai yang diserahkan kasir.
* `kode_voucher`, `diskon_voucher` (varchar/decimal): Rincian voucher jika ada.
* `nomor_referensi` (varchar 64): Nomor approval EDC atau bukti transfer bank.
* `bank_tujuan` (varchar 64): Nama bank penampung (untuk transfer).
* `id_user`, `id_karyawan` (varchar 32): ID kasir dan NPK pembeli.
* `waktu_transaksi` (datetime)

Pasang migrasi **[application/sql/pos_receipt_sequence.sql](application/sql/pos_receipt_sequence.sql)** untuk nomor nota POS `NPK-YYMMDD-###` dan kunci request checkout. Nomor urut dihitung per NPK per tanggal secara transaksional; unique index pada `nota` mencegah nomor kembar. `checkout_request_id` terpisah memastikan retry checkout mengembalikan nota yang sama.

Status retur penjualan dan audit keputusan manager dipasang melalui migrasi **[application/sql/sales_return_approval.sql](application/sql/sales_return_approval.sql)**. Status baru adalah `pending`, `approved`, dan `rejected`; data lama `completed` dipertahankan maknanya dan dinormalisasi menjadi `approved`. Pengajuan pending memesan qty retur, tetapi belum mengubah stok atau jurnal.

Riwayat retur pada halaman Retur Penjualan memakai DataTables server-side: pencarian, pengurutan, dan pagination mengambil maksimal 100 baris per permintaan. Indeks tanggal/ID dan status/ID dibuat melalui `phase3_foundation.sql` untuk instalasi baru dan `sales_return_approval.sql` untuk database yang sudah ada.

### 1.4 `retur_penjualan` & `retur_penjualan_items` (Retur Pelanggan POS)
* **`retur_penjualan`**:
  * `id` (int, PK, auto_increment)
  * `nomor_retur` (varchar 64, UNIQUE): Format `RET-YYYYMMDD-XXXX`.
  * `nota_penjualan` (varchar 64, INDEX): Referensi nota transaksi asli.
  * `id_user`, `id_karyawan` (varchar 32): Kasir pemroses dan NPK pembeli.
  * `tgl_retur` (datetime)
  * `total_retur` (decimal 15,2): Total nilai pengembalian uang.
  * `alasan` (text), `status` (enum expanded by `sales_return_approval.sql`: `pending`, `approved`, `rejected`, legacy `completed`, `cancelled`).
* **`retur_penjualan_items`**:
  * `id` (int, PK, auto_increment)
  * `retur_id`, `penjualan_id` (int): Relasi ke header retur dan baris penjualan asli.
  * `barcode`, `qty_retur`, `harga_satuan`, `subtotal`.
  * `kondisi` (enum: `layak_jual`, `rusak`).
  * `kembali_ke_stok` (tinyint 1): `1` jika menambah stok aktif kembali, `0` jika afkir.

### 1.5 Periode Tagihan dan Validasi Penjualan
Pasang **[application/sql/sales_billing.sql](application/sql/sales_billing.sql)** untuk membuat tabel periode custom, hasil validasi, dan snapshot final:
* `sales_billing_periods`: tanggal awal/akhir inklusif, nama, status open/finalized, dan audit pembuat/finalisasi. Controller menolak overlap dengan periode yang sudah terdaftar.
* `sales_validation_runs` dan `sales_validation_items`: mode validasi periode/mingguan/nota, hasil per nota, temuan, nilai penjualan, retur approved, dan nilai tertagih.
* `sales_billing_items`: snapshot per nota saat periode difinalisasi. Retur approved pada periode mengurangi tagihan; retur yang disetujui setelah periode final menjadi saldo kredit, bukan mengubah snapshot lama.

### 1.6 `retur_supplier` & `retur_supplier_items` (Retur ke Supplier)
* **`retur_supplier`**:
  * `id` (int, PK, auto_increment)
  * `nomor_retur` (varchar 64, UNIQUE): Format `RTS-YYYYMMDD-XXXX`.
  * `supplier_id` (int), `goods_receipt_id` (int, nullable).
  * `id_user` (varchar 32), `tgl_retur` (datetime), `total_nominal` (decimal 15,2).
  * `alasan` (text), `status` (enum: `draft`, `final`).
* **`retur_supplier_items`**:
  * `id` (int, PK, auto_increment)
  * `retur_supplier_id` (int), `barcode` (varchar 64), `qty_retur` (int).
  * `harga_beli`, `subtotal` (decimal 15,2), `alasan_detail` (varchar 255).

### 1.7 `simpanan` & `simpanan_mutasi` (Buku Mutasi Simpanan untuk RAT)
* **`simpanan`**:
  * `id` (int, PK, auto_increment)
  * `karyawan_id` (int, FK ke `karyawan.id`)
  * `jenis_simpanan` (enum: `pokok`, `wajib`, `sukarela`)
  * `saldo` (decimal 15,2), `updated_at` (datetime)
  * *Constraint*: UNIQUE(`karyawan_id`, `jenis_simpanan`).
* **`simpanan_mutasi`**:
  * `id` (int, PK, auto_increment)
  * `nomor_bukti` (varchar 64, UNIQUE): Nomor bukti transaksi kas/bank.
  * `karyawan_id` (int), `jenis_simpanan` (enum: `pokok`, `wajib`, `sukarela`).
  * `jenis_mutasi` (enum: `setor`, `tarik`, `bunga`, `shu`, `koreksi`).
  * `nominal`, `saldo_sebelum`, `saldo_setelah` (decimal 15,2).
  * `tgl_transaksi` (datetime), `keterangan` (text), `id_user` (varchar 32).

### 1.7 Modul Akuntansi Double-Entry
* **`acc_accounts`**: Bagan Akun / Chart of Accounts (COA) dengan kode akun (1101, 1102, dst), tipe (`asset`, `liability`, `equity`, `income`, `cogs`, `expense`), dan saldo normal (`debit`, `credit`).
* **`acc_journals`**: Header Jurnal Umum (nomor jurnal, tanggal, tipe referensi seperti `POS`, `PURCHASING`, `SALES_RETURN`, `SUPPLIER_RETURN`, `STOCK_OPNAME`, status `is_posted`).
* **`acc_journal_items`**: Baris Debit dan Kredit per akun.
* **`acc_periods`**: Periode akuntansi bulanan/tahunan dan status tutup buku (`is_closed`).
* **`acc_bank_statements`** dan **`acc_bank_statement_items`**: Draft rekening koran bank, mutasi bertanda positif/negatif, dan pencocokan unik ke baris jurnal bank. Tabel baru ini tidak mengubah tabel transaksi atau akuntansi yang sudah ada.
* Akun **6104 Beban Pemasaran - Voucher** menampung biaya diskon voucher ketika transaksi POS dipakai.

### 1.8 Posting dan Laporan Akuntansi (Fase 5)
Library `application/libraries/Accounting.php` memvalidasi akun aktif dan keseimbangan debit/kredit sebelum menulis header serta rincian jurnal. Pemanggil menjalankannya dalam transaksi bisnis yang sama, sehingga transaksi sumber ikut dibatalkan bila periode sudah ditutup atau jurnal gagal disimpan. Reference type/id digunakan untuk mengenali posting yang sudah ada.

Posting otomatis untuk transaksi baru:
* POS: debit kas (1101), bank (1102 untuk EDC/transfer/QRIS), atau piutang belanja (1103); debit beban pemasaran voucher (6104) saat voucher digunakan; kredit penjualan (4101) dan PPN keluaran (2103); HPP dan persediaan (5101/1104) ikut dicatat dari biaya rata-rata.
* Penerimaan barang: debit persediaan bersih diskon dan PPN masukan (1104/1105), kredit hutang supplier (2101). HPP rata-rata tertimbang diperbarui pada penerimaan.
* Retur pelanggan: pembalikan pendapatan/PPN terhadap akun pembayaran asal; barang layak jual yang kembali ke stok membalik HPP.
* Retur supplier final: debit hutang dan kredit persediaan sebesar nilai retur yang disimpan.
* Stok opname: selisih lebih mendebit persediaan dan mengkredit pendapatan non-operasional; selisih kurang mendebit beban selisih dan mengkredit persediaan.

Halaman `main/accounting` menampilkan jurnal umum, laba-rugi periode, saldo akun neraca kumulatif sampai tanggal akhir, dan jumlah transaksi lama yang belum terposting. Laporan tambahan meliputi:
* Arus kas terklasifikasi dari akun lawan jurnal yang menyentuh kas/bank (1101, 1102, 1106, 1107); akun ekuitas serta kewajiban berkode 22xx diklasifikasikan sebagai pendanaan, aset 12xx sebagai investasi, dan akun operasi lain sebagai operasi. Transfer internal antar-akun kas tidak masuk arus masuk/keluar. Klasifikasi ini mengikuti COA yang ada dan perlu ditinjau jika kelompok akun baru ditambahkan.
* Buku besar untuk akun pilihan dengan saldo awal sebelum rentang tanggal, mutasi debit/kredit, saldo berjalan, dan saldo akhir.
* Ringkasan serta rincian mutasi akun PPN masukan (1105) dan PPN keluaran (2103). Laporan hanya mencerminkan jurnal terposting dan tidak menggantikan pelaporan pajak.
* Rekonsiliasi manual untuk akun bank 1102. Operator memasukkan saldo rekening koran dan mutasi, lalu setiap mutasi harus cocok satu-ke-satu dengan baris jurnal terposting pada akun 1102 sebelum draft dapat ditandai selesai. Sistem memeriksa perhitungan saldo awal + mutasi = saldo akhir serta menampilkan saldo buku, selisih, dan mutasi buku yang belum muncul di rekening koran; selisih buku tidak disembunyikan atau dihapus otomatis.
* Periode akuntansi dapat diberi nama dan rentang tanggal yang tidak tumpang tindih. Penutupan hanya mengunci jurnal dalam rentang itu (tanpa membuat jurnal penutupan SHU), hanya untuk periode yang telah berakhir, dan ditolak jika transaksi sumber dalam rentang belum terposting atau jurnal tidak seimbang/tidak memiliki minimal dua baris. Timestamp dan pengguna penutup disimpan di kolom `acc_periods` yang sudah ada; periode tertutup tidak dapat dibuka kembali melalui aplikasi.

Hak akses laporan dibatasi untuk admin, management, dan pengurus.

Transaksi sebelum posting otomatis diaktifkan tidak dibuatkan jurnal otomatis karena beberapa data lama tidak menyimpan metode pembayaran atau HPP yang cukup untuk rekonstruksi andal. Pengguna perlu memeriksa/menetapkan saldo awal dan melakukan migrasi akuntansi terkontrol sebelum mengandalkan saldo laporan sebagai laporan resmi. Retur supplier saat ini tidak menyimpan alokasi PPN per barang; jurnal retur mengurangi hutang dan persediaan sebesar nilai retur, tanpa pembalikan PPN masukan.

Rekonsiliasi manual rekening koran bank hanya mendukung akun 1102 karena COA saat ini belum membedakan beberapa rekening bank. Tutup kas/petty cash harian memakai form kas tersendiri untuk akun 1106 dan 1107.

---

### 1.9 Kas Utama, Petty Cash, dan Pembayaran Tunai Supplier
Skrip **`application/sql/petty_cash.sql`** dijalankan setelah `phase3_foundation.sql` dan `purchasing.sql`. Bagan akun standar inti (termasuk **1106 Kas Utama** dan **1107 Kas Kecil**) di-seed oleh `phase3_foundation.sql`; `petty_cash.sql` juga menyertakan `INSERT IGNORE` untuk kedua akun tersebut agar instalasi lama dapat dilengkapi. Skrip petty cash menambahkan:
* **`acc_cash_funds`**: master dana dengan kode, nama/lokasi, tipe kas utama atau petty cash, pemegang, dan batas dana tetap per petty cash.
* **`acc_cash_transactions`**: pengajuan penambahan dana, beban petty cash, dan pembayaran hutang supplier berdasarkan penerimaan barang. Pengajuan menyimpan tanggal, nominal, pihak terkait, referensi serta lampiran bukti, status, penginput, penyetuju, dan jurnal.
* **`acc_cash_closings`**: saldo buku, hitungan kas fisik, selisih, catatan, pengguna, dan waktu tutup per dana/tanggal.

Halaman Manage Payment memuat DataTables secara server-side agar hanya satu halaman data yang dikirim dan dirender browser setiap kali. Skrip `petty_cash.sql` dan `purchasing_workflow.sql` juga memastikan indeks transaksi pembayaran dan status PO tersedia; jalankan ulang kedua skrip tersebut pada instalasi lama untuk menerapkan indeks baru (operasi indeksnya idempotent).

POS tetap menambah kas kasir **1101**. Pemindahan hasil kasir ke kas utama **1106** dan pengisian petty cash **1107** dicatat sebagai transfer dan tidak menjadi pendapatan/beban. Pembayaran penerimaan supplier mendebit hutang **2101** dan mengkredit akun kas yang digunakan; pengeluaran operasional mendebit akun beban yang dipilih dan mengkredit kas kecil. Semua jurnal menggunakan reference type `PETTY_CASH`, diposting atomik saat disetujui, dan mengikuti validasi akun/periode dari library akunting.

Satu dana kas utama diperbolehkan; petty cash dapat dibuat per pemegang/lokasi dan dibatasi imprest. Refill petty cash tidak boleh melewati batas dana tetap atau melebihi pengeluaran kas kecil yang sudah diposting sejak refill sebelumnya. Persetujuan oleh pembuat sendiri tidak diperbolehkan. Pembayaran supplier menaut ke penerimaan ber-jurnal, mengurangi retur supplier terkait dan pembayaran yang sedang menunggu/past-post, sehingga saldo hutang per penerimaan tidak dapat dibayar melebihi sisa yang tercatat. Lampiran disimpan di `application/cache/petty_cash_proofs/` (bukan direktori publik) dan hanya dapat diunduh pengguna yang memiliki akses ke halaman kas.

Jalankan **`application/sql/purchasing_payment_status.sql`** setelah `purchasing_workflow.sql` dan `petty_cash.sql` untuk membuat `purchase_order_payment_details` dan `purchase_order_payment_list`. Daftar PO menampilkan nilai tagihan dari semua nota terima, retur final, pembayaran yang sudah `posted`, pengajuan `pending`, dan sisa hutang. Status `Lunas` hanya berdasarkan saldo setelah pembayaran diposting; pengajuan manual yang menunggu approval tampil sebagai `Menunggu approval` dan tidak dianggap lunas.

Hak akses halaman tersedia sebagai key `petty_cash`. Setelah skema akses grup tersedia, jalankan **`application/sql/petty_cash_access.sql`** untuk memberi akses halaman kepada grup kasir, management, dan pengurus; admin memiliki akses otomatis. Kasir dapat mengajukan transaksi; hanya admin, management, dan pengurus dapat membuat dana, menyetujui/menolak transaksi, dan mengesahkan tutup kas.

Saldo tunai lama tidak diimpor otomatis. Sebelum memakai kontrol saldo, cocokkan saldo awal aktual dengan saldo buku; isi kas utama dari akun kas/bank yang saldonya sudah benar, lalu isi petty cash dari kas utama. Tutup kas harian mensyaratkan seluruh pengajuan transaksi sampai tanggal tutup telah diproses. Tutup laci kasir memblokir checkout dan retur tunai berikutnya pada tanggal kalender yang sama, sehingga harus dilakukan setelah transaksi kasir hari itu berhenti. Selisih disimpan untuk tindak lanjut dan tidak membentuk jurnal otomatis.

### Riwayat HPP
Jalankan **`application/sql/inventory_cost_history.sql`** setelah `purchasing.sql` dan `stock_opname.sql`. Penerimaan Purchasing menyimpan HPP rata-rata setelah setiap penerimaan pada `goods_receipt_items.average_cost_after`; halaman `main/hpp` menampilkan tanggal, supplier, harga beli, diskon, dan HPP tersebut per barang. Input manual memperbarui HPP saat ini dan membuat catatan audit tersendiri di `inventory_cost_history`; sumbernya ditampilkan sebagai **Manual (bukan vendor/supplier)**. Riwayat penerimaan lama tetap menampilkan harga yang tersimpan sebelumnya dan HPP rata-rata setelahnya ditandai belum tercatat jika data historis itu tidak tersedia.

## 2. Perubahan pada Tabel Eksisting

* **`goods_receipts`**:
  * Ditambahkan kolom `ppn_nominal` (decimal 15,2) untuk mencatat angka PPN pasti sesuai faktur fisik supplier.
* **`goods_receipt_items`**:
  * Ditambahkan kolom:
    * `discount_type` (enum: `none`, `percent`, `nominal`)
    * `discount_value` (decimal 15,2)
    * `discount_amount` (decimal 15,2)
    * `subtotal` (decimal 15,2)
* **`groups`**:
  * Ditambahkan 3 grup baru: `kasir`, `management`, `pengurus` (melengkapi `admin` dan `members`).
