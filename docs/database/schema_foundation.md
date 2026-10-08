# Dokumentasi Skema Database Fondasi Koperasi Simpleton (Fase 3)

Dokumen ini mencatat skema tabel baru dan perluasan tabel eksisting yang diterapkan melalui berkas migrasi **[application/sql/phase3_foundation.sql](file:///g:/xampp/htdocs/simpleton/application/sql/phase3_foundation.sql)**.

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

### 1.4 `retur_penjualan` & `retur_penjualan_items` (Retur Pelanggan POS)
* **`retur_penjualan`**:
  * `id` (int, PK, auto_increment)
  * `nomor_retur` (varchar 64, UNIQUE): Format `RET-YYYYMMDD-XXXX`.
  * `nota_penjualan` (varchar 64, INDEX): Referensi nota transaksi asli.
  * `id_user`, `id_karyawan` (varchar 32): Kasir pemroses dan NPK pembeli.
  * `tgl_retur` (datetime)
  * `total_retur` (decimal 15,2): Total nilai pengembalian uang.
  * `alasan` (text), `status` (enum: `completed`, `cancelled`).
* **`retur_penjualan_items`**:
  * `id` (int, PK, auto_increment)
  * `retur_id`, `penjualan_id` (int): Relasi ke header retur dan baris penjualan asli.
  * `barcode`, `qty_retur`, `harga_satuan`, `subtotal`.
  * `kondisi` (enum: `layak_jual`, `rusak`).
  * `kembali_ke_stok` (tinyint 1): `1` jika menambah stok aktif kembali, `0` jika afkir.

### 1.5 `retur_supplier` & `retur_supplier_items` (Retur ke Supplier)
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

### 1.6 `simpanan` & `simpanan_mutasi` (Buku Mutasi Simpanan untuk RAT)
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

### 1.8 Posting dan Laporan Akuntansi (Fase 5)
Library `application/libraries/Accounting.php` memvalidasi akun aktif dan keseimbangan debit/kredit sebelum menulis header serta rincian jurnal. Pemanggil menjalankannya dalam transaksi bisnis yang sama, sehingga transaksi sumber ikut dibatalkan bila periode sudah ditutup atau jurnal gagal disimpan. Reference type/id digunakan untuk mengenali posting yang sudah ada.

Posting otomatis untuk transaksi baru:
* POS: debit kas (1101), bank (1102 untuk EDC/transfer/QRIS), atau piutang belanja (1103); kredit penjualan (4101) dan PPN keluaran (2103); voucher dicatat ke potongan penjualan (4202); HPP dan persediaan (5101/1104) ikut dicatat dari biaya rata-rata.
* Penerimaan barang: debit persediaan bersih diskon dan PPN masukan (1104/1105), kredit hutang supplier (2101). HPP rata-rata tertimbang diperbarui pada penerimaan.
* Retur pelanggan: pembalikan pendapatan/PPN terhadap akun pembayaran asal; barang layak jual yang kembali ke stok membalik HPP.
* Retur supplier final: debit hutang dan kredit persediaan sebesar nilai retur yang disimpan.
* Stok opname: selisih lebih mendebit persediaan dan mengkredit pendapatan non-operasional; selisih kurang mendebit beban selisih dan mengkredit persediaan.

Halaman `main/accounting` menampilkan jurnal umum, laba-rugi periode, saldo akun neraca kumulatif sampai tanggal akhir, dan jumlah transaksi lama yang belum terposting. Laporan tambahan meliputi:
* Arus kas terklasifikasi dari akun lawan jurnal yang menyentuh kas/bank; akun ekuitas serta kewajiban berkode 22xx diklasifikasikan sebagai pendanaan, aset 12xx sebagai investasi, dan akun operasi lain sebagai operasi. Transfer internal kas/bank tidak masuk arus masuk/keluar. Klasifikasi ini mengikuti COA yang ada dan perlu ditinjau jika kelompok akun baru ditambahkan.
* Buku besar untuk akun pilihan dengan saldo awal sebelum rentang tanggal, mutasi debit/kredit, saldo berjalan, dan saldo akhir.
* Ringkasan serta rincian mutasi akun PPN masukan (1105) dan PPN keluaran (2103). Laporan hanya mencerminkan jurnal terposting dan tidak menggantikan pelaporan pajak.
* Rekonsiliasi manual untuk akun bank 1102. Operator memasukkan saldo rekening koran dan mutasi, lalu setiap mutasi harus cocok satu-ke-satu dengan baris jurnal terposting pada akun 1102 sebelum draft dapat ditandai selesai. Sistem memeriksa perhitungan saldo awal + mutasi = saldo akhir serta menampilkan saldo buku, selisih, dan mutasi buku yang belum muncul di rekening koran; selisih buku tidak disembunyikan atau dihapus otomatis.
* Periode akuntansi dapat diberi nama dan rentang tanggal yang tidak tumpang tindih. Penutupan hanya mengunci jurnal dalam rentang itu (tanpa membuat jurnal penutupan SHU), hanya untuk periode yang telah berakhir, dan ditolak jika transaksi sumber dalam rentang belum terposting atau jurnal tidak seimbang/tidak memiliki minimal dua baris. Timestamp dan pengguna penutup disimpan di kolom `acc_periods` yang sudah ada; periode tertutup tidak dapat dibuka kembali melalui aplikasi.

Hak akses laporan dibatasi untuk admin, management, dan pengurus.

Transaksi sebelum posting otomatis diaktifkan tidak dibuatkan jurnal otomatis karena beberapa data lama tidak menyimpan metode pembayaran atau HPP yang cukup untuk rekonstruksi andal. Pengguna perlu memeriksa/menetapkan saldo awal dan melakukan migrasi akuntansi terkontrol sebelum mengandalkan saldo laporan sebagai laporan resmi. Retur supplier saat ini tidak menyimpan alokasi PPN per barang; jurnal retur mengurangi hutang dan persediaan sebesar nilai retur, tanpa pembalikan PPN masukan.

Rekonsiliasi manual hanya mendukung akun bank 1102 karena COA saat ini belum membedakan beberapa rekening bank.

---

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
