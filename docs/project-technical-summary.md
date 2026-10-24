# Rangkuman Teknis Codebase Simpleton

> Snapshot dokumentasi: 9 Oktober 2026. Ringkasan ini berdasarkan source code dan skrip SQL yang ada di repository; keberhasilan fitur di server produksi dan status penerapan skrip SQL belum diverifikasi.

## 1. Ringkasan Eksekutif

- **Tujuan utama:** aplikasi koperasi/toko untuk penjualan POS, persediaan, pembelian/penerimaan barang, stok opname, retur, dan pencatatan akuntansi.
- **Arsitektur:** aplikasi web PHP dengan pola MVC CodeIgniter 3 dan modul HMVC `application/modules/main`; controller menangani HTTP/input dan aturan bisnis, view PHP merender halaman, library menyediakan layanan bersama, dan MySQL/MariaDB menyimpan data.
- **Pola desain yang terlihat:**
  - POS dan pembelian memakai controller khusus, query builder/raw parameterized SQL, serta transaksi database untuk perubahan stok dan jurnal.
  - Fitur yang memerlukan audit menggunakan header/detail transaksi dan merujuk dokumen sumber (nota, PO, penerimaan).
  - Autentikasi memakai Ion Auth; sebagian modul membatasi akses berdasarkan group/role.
  - Skema tambahan disediakan dalam skrip SQL bertahap di `application/sql/`, bukan tampak sebagai migrasi otomatis saat aplikasi berjalan.
- **Kondisi implementasi:** POS, inventory, purchasing, stok opname, konversi kemasan, retur, sebagian laporan akuntansi, dan rekonsiliasi bank memiliki controller serta alur aplikasi. Portal belanja online, sinkronisasi offline/cloud, dan portal informasi anggota belum tampak terimplementasi sebagai modul aplikasi.

## 2. Komponen & Struktur Utama

### Struktur utama

| Lokasi | Peran |
|---|---|
| `application/modules/main/controllers/` | Controller untuk halaman, endpoint AJAX, transaksi bisnis, dan laporan. |
| `application/modules/main/views/` | Halaman POS, purchasing, stok opname, konversi kemasan, retur, accounting, dan rekonsiliasi bank. |
| `application/libraries/` | Layanan lintas modul, termasuk posting jurnal akuntansi. |
| `application/sql/` | Skrip skema/penambahan tabel untuk purchasing, HPP, stok opname, dan fondasi fase 3. |
| `assets/kasir/` | JavaScript POS, format nota, dan skrip rekap/unggah data. |
| `assets/adminlte/`, `assets/grocery_crud/`, `application/third_party/` | Aset UI serta library frontend/backend yang dibundel. |

### Class, controller, dan layanan penting

| Komponen | Fungsi |
|---|---|
| `Examples::penjualan()` | Merender antarmuka POS, modal konfirmasi checkout, dan memuat `assets/kasir/kasir.js`. |
| `Kasir::checkout()` | Memvalidasi permintaan checkout, memastikan stok/HPP tersedia, menerapkan konversi dus ke pcs, voucher dan pembayaran, menulis detail penjualan/pembayaran, serta mem-posting jurnal dalam transaksi database. |
| `Kasir::reprint()` | Mencari nota tersimpan berdasarkan nomor dan merender ulang untuk thermal print; tidak membuat detail penjualan, pembayaran, atau jurnal baru. |
| `Kasir::_checkout_input()` / `_clean_cart_items()` | Memvalidasi field transaksi, metode pembayaran, dan detail barang dari request. |
| `Kasir::_resolve_checkout_items()` | Membaca/lock stok dan HPP; merencanakan pembukaan kemasan saat stok pcs tidak cukup. |
| `Kasir::_persist_sale_lines()` | Mengurangi stok pcs atau stok dus yang dibuka, kemudian menyimpan detail penjualan. |
| `Kasir::_apply_voucher()` / `validate_voucher()` | Memvalidasi voucher master atau serial kupon, status sekali pakai, NPK penerima, masa aktif, kuota, minimum belanja, dan nominal diskon/cashback. |
| `Voucher::coupons()` | Mengelola serial kupon, generate, preview/konfirmasi impor XLS/XLSX, serta halaman cetak barcode. Histori penggunaan memiliki filter NPK dan serial kupon. |
| `Kasir::_post_checkout_journal()` | Membentuk jurnal POS untuk kas/bank/piutang anggota, penjualan, PPN, biaya pemasaran voucher (6104), dan HPP/persediaan. |
| `Ajax` | Endpoint data pendukung POS dan laporan: pencarian barcode/karyawan, kasir, batas belanja, serta CSV harian/bulanan/tahunan/per anggota. |
| `Purchasing` | Mengelola supplier, PO, pencarian/import Excel, cetak dokumen, penerimaan aktual, diskon per baris, PPN nominal, pembaruan stok/HPP, dan jurnal pembelian. |
| `Purchasing::close_po()` / `save_receipt()` | Menutup PO parsial dengan alasan/audit, mengunci penerimaan sesudah close, dan menerima item ad-hoc yang ditandai serta diautorisasi manager/pengurus. Migrasi: `application/sql/purchasing_workflow.sql`. |
| `Management_payments` | Antrean pembayaran hutang per nota terima dengan filter supplier/nota/tanggal/status; approve/reject tetap memakai `Petty_cash` agar saldo hutang dan jurnal tidak diduplikasi. |
| `Stock_opname` | Membuat draft snapshot stok, menyimpan hitungan fisik/HPP, menerapkan selisih saat finalisasi, dan mem-posting jurnal selisih. |
| `Product_conversion` | Mengelola relasi barcode dus ke barcode pcs, label kemasan, dan rasio isi; endpoint dibatasi untuk group admin/management. |
| `Sales_return` | Mengajukan retur pending berdasarkan nota; manager/admin menyetujui atau menolak dengan audit. Hanya approval yang menambah stok sesuai kondisi dan mem-posting jurnal. |
| `Sales_billing` | Mengelola rentang tagihan custom tanpa overlap, validasi mingguan/per nota/periode, detail temuan, dan snapshot final yang hanya bisa dibuat setelah semua nota valid. |
| `Supplier_return` | Membuat draft retur dari penerimaan supplier, memvalidasi qty yang masih dapat diretur, meminta persetujuan, mengurangi stok, dan mem-posting jurnal. |
| `Supplier_return::preview()` | Menampilkan detail draft/final, jumlah diterima, retur sebelumnya, saldo qty dan subtotal tanpa mengubah status retur. |
| `Accounting` | Menyajikan laporan dari jurnal ter-posting: laba rugi, neraca, pergerakan kas, buku besar, arus kas, ringkasan PPN masukan/keluaran, serta pengelolaan periode. |
| `Bank_reconciliation` | Menginput rekening koran manual, mencocokkan/melepas pencocokan item rekening koran dengan jurnal, lalu menyelesaikan rekonsiliasi. |
| `Accounting::post_journal()` (library) | Memvalidasi akun aktif, format dan keseimbangan debit/kredit, mencegah jurnal duplikat berdasarkan tipe/referensi, dan menolak jurnal pada periode tertutup. |
| `Examples`, `Kartu`, `Kamera`, `Tiket` | Fitur master/administrasi lama untuk stok, karyawan, user, kartu anggota, foto karyawan, serta tiket pesawat. |

### Alur data / eksekusi

1. **POS**
   - Browser memuat halaman POS dari `Examples::penjualan()` dan skrip `kasir.js`.
   - Barcode dicari melalui endpoint AJAX; item dimasukkan ke grid keranjang, total dan informasi karyawan ditampilkan.
   - F9/tombol checkout membuka dialog konfirmasi. Setelah konfirmasi, browser mengirim nota, identitas, metode pembayaran, field pembayaran, voucher, dan daftar item ke `Kasir::checkout()`.
   - Server melakukan validasi; memulai transaksi DB; membaca serta mengunci stok/HPP; bila pcs tidak mencukupi, menghitung kebutuhan membuka dus dari relasi rasio.
   - Voucher dan nominal pembayaran tunai diverifikasi; perubahan stok, detail penjualan, header pembayaran, pemakaian voucher, dan jurnal akuntansi disimpan. Kegagalan membatalkan transaksi; keberhasilan mengembalikan hasil checkout dan UI menyiapkan nota.
   - Cetak ulang mencari detail dan header pembayaran yang sudah tersimpan melalui `Kasir::reprint()`; alur ini read-only.
2. **Purchasing**
   - PO menyimpan supplier, barang, barcode dan jumlah yang dipesan; penyimpanan PO sendiri belum menambah stok.
   - Penerimaan menyimpan jumlah aktual, harga, diskon persen/nominal tiap baris, PPN nominal pada faktur, dan catatan supplier.
   - Penerimaan memperbarui stok dan HPP rata-rata tertimbang, mencatat barang diterima, lalu membuat jurnal persediaan/PPN masukan/hutang supplier.
3. **Stok opname**
   - Draft mengambil snapshot jumlah sistem per barang.
   - Petugas menyimpan jumlah fisik dan harga pokok; finalisasi mengunci data, menerapkan selisih ke jumlah stok, memperbarui inventory cost, serta membuat jurnal selisih lebih/kurang.
4. **Retur penjualan**
   - Petugas mencari nota; server menghitung qty yang masih dapat diajukan berdasarkan detail asli dan retur pending/approved.
   - Saat retur diajukan, item, kondisi, alasan dan keputusan barang kembali ke stok dicatat sebagai pending; transaksi penjualan asli tetap utuh, dan stok/jurnal belum berubah.
   - Manager/admin menyetujui atau menolak. Approval mengunci retur, memvalidasi ulang sisa qty, memperbarui stok yang layak jual, lalu mem-posting jurnal secara atomik. Hanya retur approved yang mengurangi nilai tertagih pada rekap personal/bulanan; pending/rejected tidak mengurangi tagihan.
5. **Retur supplier**
   - Pengguna berwenang membuat draft berdasarkan barang dari penerimaan; admin/pengurus menyetujui.
   - Saat disetujui, server menghitung sisa qty yang masih dapat diretur, mengunci stok, mengurangi stok, memfinalisasi retur, dan mem-posting jurnal pengurangan hutang/persediaan.
6. **Accounting dan rekonsiliasi**
   - Transaksi POS, penerimaan, retur, dan selisih stok memanggil library accounting untuk jurnal double-entry.
   - Laporan menghitung saldo/pergerakan dari jurnal berstatus posted. Rekonsiliasi membandingkan item rekening koran yang diinput manual dengan item jurnal akun kas/bank.

## 3. Ketergantungan (Dependencies & Integrasi)

| Dependency/integrasi | Penggunaan |
|---|---|
| PHP dan CodeIgniter 3 | Runtime web dan pola MVC; README project menyebut CodeIgniter 3.0.3. |
| MySQL/MariaDB | Penyimpanan transaksi; beberapa proses mengandalkan transaksi, row locking `FOR UPDATE`, dan tabel InnoDB. |
| Ion Auth 2 | Login dan group/role; skrip fondasi menyediakan group `admin`, `members`, `kasir`, `management`, dan `pengurus`. |
| Grocery CRUD 1.5.2 | CRUD/admin grid pada modul lama serta daftar PO. |
| AdminLTE 2.3.0 / Bootstrap | Layout dashboard dan komponen UI. |
| jQuery | Interaksi browser dan AJAX pada halaman legacy/POS. |
| jExcel, DataTables, EasyAutocomplete | Grid POS/purchasing, tabel, dan autocomplete pencarian. |
| PHPExcel | Baca/tulis Excel pada fitur impor/ekspor legacy. |
| Ion Auth dan helper/library CodeIgniter | Session, autentikasi, URL/form, dan response controller. |
| CSV/browser print | Laporan penjualan diekspor sebagai CSV; nota/dokumen dicetak dari tampilan browser. |

- `composer.json` root hanya menyatakan kebutuhan PHP dan tidak mendaftarkan sebagian besar library aplikasi; beberapa library berada sebagai source yang dibundel di repository.
- Tidak ditemukan integrasi pembayaran EDC/bank eksternal, penyedia pengiriman, API e-commerce, atau API cloud pada modul yang ditinjau. Kolom referensi EDC/transfer disimpan untuk pencatatan, bukan bukti koneksi langsung ke terminal/bank.

## 4. Daftar Fitur / Modul yang Sudah Terimplementasi

| Modul | Status di codebase | Rincian untuk tracker |
|---|---|---|
| POS/penjualan | Ada alur aplikasi | Scan/cari barang, keranjang, checkout via tombol/F9 dan dialog konfirmasi, beberapa metode pembayaran, validasi server, nomor nota, pencatatan detail dan cetak nota. |
| Kontrol stok saat checkout | Ada | Backend menolak barang tidak ditemukan, HPP tidak valid, dan stok tidak mencukupi; pengurangan stok terjadi dalam transaksi DB. |
| Metode pembayaran | Ada | Potong gaji, tunai/cash, QRIS, EDC, transfer; validasi nominal tunai, kembalian, referensi EDC, serta bank tujuan transfer. Tidak tampak integrasi langsung ke gateway/terminal pembayaran. |
| Voucher | Ada | Master/riwayat penggunaan, pencarian NPK, serial kupon individual, generate dan cetak barcode, impor XLS/XLSX dengan preview, serta penebusan serial sekali pakai. Migration serial: `application/sql/voucher_coupons.sql`. |
| Inventory/stok | Ada | Master stok, perubahan stok dari penjualan/penerimaan/opname/retur, HPP rata-rata untuk barang yang didukung inventory cost. |
| Konversi dus–pcs | Ada | Relasi barcode kemasan ke barcode pcs dengan rasio dan label; checkout dapat menghitung pembukaan dus virtual saat stok pcs kurang. |
| Purchasing/supplier | Ada | Supplier, PO open/closed, close parsial dengan audit, penerimaan parsial, item ad-hoc authorized dari luar PO, update stok/HPP dan jurnal. |
| Pembayaran supplier | Ada | Antrean manager untuk pembayaran per nota terima; menggunakan approval supplier-payment Petty Cash yang sama dan menampilkan sisa hutang. |
| Diskon/PPN pembelian | Ada | Diskon per baris berupa persen/nominal; PPN dicatat sebagai nominal pada penerimaan; ringkasan nilai dan jurnal pembelian dibuat. |
| Stok opname | Ada | Snapshot sistem, hitung fisik, simpan HPP, finalisasi selisih stok, update inventory cost, dan jurnal selisih. |
| Retur pembeli | Ada | Cari nota, pengajuan pending, batas sisa qty termasuk pending lain, approval/rejection manager, audit keputusan, dan mutasi stok/jurnal hanya setelah approval. Rekap personal/bulanan memisahkan penjualan, retur approved, dan total tertagih bersih. |
| Retur supplier | Ada | Draft dari penerimaan, alasan per barang, sisa qty retur tervalidasi, persetujuan admin/pengurus, pengurangan stok dan jurnal. |
| Accounting/jurnal | Ada | Bagan akun, jurnal debit/kredit seimbang, posting transaksi POS/purchasing/retur/opname, buku besar dan pembatasan periode tertutup. |
| Laporan keuangan/tax | Ada sebagian | Laba rugi, neraca, arus kas, pergerakan kas, buku besar, jurnal, ringkasan PPN masukan/keluaran. Belum diverifikasi kelengkapan kepatuhan/regulasi atau validasi hasil oleh akuntan. |
| Rekonsiliasi bank manual | Ada | Input statement dan item, pencocokan/unmatch terhadap jurnal, hitung saldo pembanding, finalisasi statement. |
| Laporan penjualan | Ada sebagian | Ekspor CSV harian, bulanan, tahunan, dan per anggota/karyawan melalui endpoint Ajax; periode bulanan mengikuti rentang yang ditentukan implementasi saat ini. |
| Tagihan custom & validasi | Ada | Periode tanggal custom tanpa overlap; validasi nota dengan detail pembayaran/jurnal/retur; snapshot final per nota. Rekap legacy personal/bulanan menampilkan total transaksi, retur approved, dan nilai tertagih bersih. |
| Autentikasi/role | Ada sebagian | Ion Auth dan group dasar; beberapa controller baru memeriksa login/group. Penegakan otorisasi belum konsisten pada seluruh controller legacy/AJAX. |
| Data anggota | Ada sebagian | Master karyawan/NPK, data pembelian per anggota, kartu anggota/foto; skrip skema menyiapkan saldo/mutasi simpanan. Portal anggota terpadu dengan SHU dan RAT belum tampak. |
| Online shop anggota | Belum ditemukan | Tidak tampak modul pemesanan online, fulfillment pengiriman/ambil, atau integrasi status pesanan ke inventory. |
| Operasi offline dan sync cloud | Belum ditemukan | Tidak tampak antrean offline, mekanisme sinkronisasi/conflict resolution, atau service cloud. |
| Tagihan belanja anggota | Belum tampak sebagai laporan khusus | Data potong gaji/piutang dicatat dan akun piutang disediakan; laporan tagihan tersendiri tidak tampak pada modul yang ditinjau. |
| Laporan RAT/SHU | Skema sebagian | Tabel simpanan/mutasi dan akun SHU tersedia pada skrip fondasi; proses bisnis lengkap, pembagian SHU, dan laporan RAT belum tampak. |

## 5. Catatan Teknis & Outstanding

- **Penerapan database:** fitur fase 3 memerlukan skrip `application/sql/phase3_foundation.sql` serta skrip pendukung purchasing, sales accounting, dan stok opname. Pastikan urutan penerapan sesuai database target; source code saja tidak membuktikan skema sudah diterapkan.
- **Legacy dan keamanan akses:** sejumlah endpoint/controller lama (terutama AJAX/Examples/Purchasing/Stock Opname) perlu ditinjau untuk konsistensi autentikasi, otorisasi server-side, CSRF, dan pembatasan akses data. Modul baru yang melakukan pemeriksaan Ion Auth tidak otomatis melindungi endpoint lain.
- **Akurasi accounting:** struktur jurnal dan laporan sudah ada, tetapi pemetaan akun, perlakuan diskon/cashback, retur pajak, HPP, penutupan periode, tax, dan rekonsiliasi perlu diuji terhadap kebijakan akuntansi koperasi dan dikonfirmasi pihak akuntansi.
- **Integritas stok kemasan:** hubungan rasio ada, tetapi stok fisik dus dan pcs disimpan sebagai produk terpisah; pastikan hasil bongkar virtual, retur, opname, laporan stok, dan biaya persediaan tetap konsisten pada semua alur.
- **Idempotensi dan audit:** checkout melakukan pemeriksaan nota yang sudah tercatat; tinjau perlindungan retry untuk endpoint penerimaan, retur, opname, posting jurnal, dan operasi browser jika koneksi terputus.
- **Otorisasi retur supplier:** terdapat pemisahan pembuat draft dan pihak yang menyetujui. Pastikan group/akun operasional sudah dikonfigurasi benar sebelum dipakai.
- **Kualitas laporan:** validasi batas periode, definisi angka, data legacy tanpa metode pembayaran/HPP, dan ekspor CSV termasuk pembukaan di spreadsheet.
- **Struktur legacy:** sebagian fitur lama menaruh markup HTML langsung dalam controller dan memakai endpoint URL localhost absolut pada aset POS; pertimbangkan memindahkannya ke view/config URL agar dapat dipasang pada lingkungan selain localhost.
- **Versi dependency:** README mencatat versi framework/library lama; audit kompatibilitas PHP/runtime dan rencana upgrade sebelum menambah fitur besar.
- **Testing:** ada skrip uji terkait halaman penjualan dalam `tests/`, tetapi cakupan automated test untuk transaksi DB, accounting, retur, dan konkurensi perlu ditinjau. Rangkuman ini tidak menjalankan test suite.
- **Belum ada bukti pada codebase:** online shop, offline-first/sync, portal member mandiri, pemrosesan pembayaran elektronik langsung, dan implementasi lengkap laporan tagihan belanja/RAT/SHU.
