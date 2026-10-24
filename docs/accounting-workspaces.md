# Panduan Accounting dan Petty Cash

Panduan ini menjelaskan tampilan lama, ruang kerja V2, dan input spreadsheet V3. Ketiganya sengaja tersedia bersamaan agar alur kerja dapat dibandingkan sebelum memutuskan tampilan mana yang dipakai.

## Hal penting sebelum mencoba

- Tampilan lama dan baru membaca data yang sama: bagan akun `acc_accounts`, jurnal `acc_journals`/`acc_journal_items`, periode, dan dana kas.
- Tidak ada salinan database khusus untuk demo. Perubahan yang disimpan dari ruang kerja baru adalah transaksi sungguhan di buku besar aplikasi.
- Jurnal manual pada Accounting baru langsung diposting dan tidak dapat dihapus dari tampilan. Periksa tanggal, akun, dan jumlah sebelum menekan **Posting jurnal**.
- Permintaan petty cash belum menjadi jurnal sampai disetujui. Penginput tidak dapat menyetujui atau menolak permintaannya sendiri.
- Formulir pencatatan Accounting baru dan operasi kas di kedua tampilan menggunakan token sesi untuk mencegah kiriman lintas situs.
- Accounting V3 memposting seluruh baris jurnal dalam satu batch atomik. Petty Cash V3 membuat transaksi berstatus pending; saldo dan jurnal baru berubah setelah approver lain menyetujuinya.
- Jangan menjalankan SQL penginstalan ulang pada database yang berisi data. Skema lama perlu tersedia dari `phase3_foundation.sql`, `purchasing.sql`, dan `petty_cash.sql`.

## Cara membuka versi lama dan baru

Gunakan grup dengan hak akses Accounting atau Kas & Petty Cash yang sesuai. Menu berada di **Back End**:

| Fitur | Lama | V2 | V3 |
|---|---|---|---|
| Accounting | `main/accounting` — laporan, jurnal, buku akun, periode, dan integritas transaksi dalam satu halaman panjang | `main/accounting_new` — ringkasan, bagan akun, buku per akun, daftar jurnal, dan input jurnal dipisah ke tab | `main/accounting_grid` — input debit/kredit per baris seperti spreadsheet |
| Kas & Petty Cash | `main/petty_cash` — dana, buku kas, pengajuan, persetujuan, dan tutup kas pada satu halaman | `main/petty_cash_new` — ringkasan dana, buku kas, transaksi/persetujuan, dan tutup kas dipisah ke tab | `main/petty_cash_grid` — input pemasukan/pengeluaran per baris seperti spreadsheet; transaksi tetap menunggu persetujuan |

Menu **Back End** menampilkan tautan terpisah untuk versi lama, V2, dan V3. Semua versi memakai buku besar dan dana yang sama; tidak ada data khusus demo.

## Accounting lama

1. Pilih rentang tanggal, kemudian tekan **Tampilkan**.
2. Tinjau periode akuntansi, jumlah transaksi yang belum terposting, laba rugi, neraca, arus kas, PPN, dan jurnal.
3. Pilih akun untuk melihat buku besar beserta saldo awal dan saldo berjalan.
4. Buat periode dan tutup setelah rentangnya berakhir serta pengecekan transaksi/jurnal selesai. Periode tertutup mengunci posting pada tanggal dalam rentang itu.

Halaman lama terutama berfungsi sebagai laporan gabungan. Jurnal operasional biasanya dibuat otomatis dari penjualan, pembelian, retur, opname, dan transaksi kas.

## Accounting baru: model buku besar

1. Mulai di tab **Ringkasan** untuk melihat posisi aset, kewajiban, modal, hasil periode, dan jurnal terakhir.
2. Buka **Bagan akun**, lalu tekan **Buka buku** di akun yang ingin ditelusuri.
3. Atur rentang tanggal dan tinjau tab **Buku akun**. Saldo awal adalah saldo sebelum tanggal mulai; mutasi disusun berdasarkan tanggal dan saldo normal akun.
4. Buka tab **Jurnal** untuk membaca rincian debit/kredit per jurnal.
5. Untuk pencatatan langsung, buka **Transaksi baru**, pilih tanggal dan keterangan, lalu isi minimal dua baris. Setiap baris hanya boleh berisi debit atau kredit. Total harus seimbang sebelum tombol posting bisa dipakai.

Contoh pengeluaran tunai Rp50.000:

| Akun | Debit | Kredit |
|---|---:|---:|
| Beban yang sesuai | Rp50.000 | — |
| Kas atau bank yang dibayar | — | Rp50.000 |

Jurnal manual memakai reference type `MANUAL_NEW`, diperiksa terhadap periode tertutup, dan diposting dalam transaksi database. Jurnal dari modul operasional tetap masuk ke buku besar yang sama. Untuk koreksi jurnal terposting, gunakan proses koreksi/reversal yang disetujui organisasi; halaman baru tidak mengedit atau menghapus jurnal.

## Accounting V3: input seperti spreadsheet

1. Buka menu **Accounting V3 — Spreadsheet**.
2. Isi satu baris per jurnal: tanggal `YYYY-MM-DD`, uraian, akun debit, akun kredit, dan nominal. Akun dipilih dari dropdown.
3. Gunakan Enter atau tombol panah untuk berpindah sel; baris kosong boleh dibiarkan. Tambahkan/hapus baris memakai kontrol grid. Maksimal 50 jurnal per batch.
4. Tekan **Simpan semua jurnal**. Seluruh baris yang terisi divalidasi di server dan disimpan dalam satu transaksi database. Jika satu baris tidak valid atau tanggal masuk periode tertutup, seluruh batch dibatalkan.

Jurnal V3 menggunakan reference type `MANUAL_GRID`, langsung masuk buku besar bersama, dan tidak dapat dibatalkan dari grid. Untuk koreksi, gunakan proses koreksi/reversal organisasi.

## Petty cash lama

1. Pengurus membuat dana petty cash, mengatur batas imprest, dan memilih pemegang dana.
2. Catat setoran awal sebagai transaksi **Penambahan / transfer masuk**. Dana baru tidak otomatis memiliki saldo jurnal.
3. Pemegang dana mengajukan pengeluaran, memilih akun beban, memasukkan referensi bukti, dan melampirkan PDF/JPG/PNG.
4. Approver lain meninjau lampiran dan memilih **Setujui & posting** atau mengisi alasan penolakan. Pembayaran supplier perlu ditautkan ke penerimaan/hutang yang benar.
5. Gunakan **Buku kas harian** untuk membandingkan saldo sistem, saldo awal, dan mutasi tanggal tersebut.
6. Approver mengesahkan tutup kas setelah seluruh permintaan sampai tanggal tersebut disetujui atau ditolak. Catatan wajib diberikan jika saldo fisik berbeda.

## Petty cash baru

Tab memisahkan tugas, namun menggunakan proses dan data kas lama:

- **Ringkasan dana** menampilkan saldo, pemegang, batas, dan status dana.
- **Buku kas** menampilkan saldo awal, mutasi hari itu, dan saldo akhir untuk satu dana.
- **Transaksi & persetujuan** menyediakan pengajuan, unggahan bukti, tinjauan, persetujuan, penolakan, dan pembuatan dana (khusus approver).
- **Tutup kas** digunakan untuk menghitung kas fisik dan melihat riwayat rekonsiliasi.

Jika pengajuan dari halaman baru berhasil atau gagal, hasilnya kembali ke ruang kerja baru. Bukti yang sudah diunggah dapat dibuka dari detail transaksi. Status **Ditolak** tidak membentuk jurnal; status **Sudah diposting** menandakan jurnal sudah tercatat.

## Alur yang disarankan dan data demo

- Untuk satu transaksi yang perlu unggah bukti, gunakan **Petty Cash V2 → Transaksi & persetujuan**. Formulir dan daftar permintaan ada di satu tab; pemegang mengajukan, approver lain memeriksa bukti lalu menyetujui atau menolak.
- Untuk memasukkan banyak transaksi dari Excel, gunakan **Petty Cash V3 — Spreadsheet**. Grid menerima sampai 50 baris dan satu batch menjadi pending bersama-sama. V3 hanya menyimpan referensi bukti, bukan file lampirannya.
- Buka **Ringkasan dana** untuk memilih dana, **Buku kas** untuk saldo/mutasi tanggal tertentu, dan **Tutup kas** setelah semua permintaan sampai tanggal itu selesai diputuskan.
- Pengajuan isi dana petty cash pertama kali boleh sampai batas imprest. Refill berikutnya dibatasi jumlah pengeluaran yang sudah diposting sejak refill sebelumnya. Sumber dana petty cash harus Kas Utama `1106`.

Database lokal sudah diisi contoh dari sheet **PENGELUARAN** Agustus 2026. Untuk memasang ulang bila data demo dihapus, jalankan `application/sql/demo_petty_cash_agustus_2026.sql`; skrip aman dijalankan berulang.

| Dana demo | Batas | Contoh pengeluaran | Status awal |
|---|---:|---:|---|
| `DEMO-SMBK26` — Sembako & ATK | Rp4.573.000 | 17 rincian | Pending |
| `DEMO-DEPO26` — Depo | Rp1.820.000 | 2 rincian | Pending |
| `DEMO-MAIN26` — Kas Utama demo | — | Sumber refill petty cash | Pending |

Untuk uji approval, buka **Petty Cash V2 → Transaksi & persetujuan** dan setujui berurutan: setoran demo ke Kas Utama dari laci POS `1101`, setoran Kas Utama ke masing-masing petty cash, lalu pengeluarannya. Semua contoh dibuat oleh akun non-approver sehingga akun admin dapat menyetujuinya. Data pending belum mengubah saldo ataupun jurnal; setelah disetujui, jurnal benar-benar masuk buku besar. Jangan menyetujui jika hanya ingin melihat tampilan demo.

## Petty Cash V3: input seperti spreadsheet

1. Buka menu **Petty Cash V3 — Spreadsheet**, pilih dana petty cash aktif, lalu isi baris transaksi: tanggal, jenis, akun/kategori, keterangan, nominal, dan referensi bukti.
2. Pilih **Pengeluaran** untuk beban atau **Pemasukan / isi dana** untuk transfer isi dana dari akun kas utama `1106`. Grid hanya menampilkan akun yang dapat dipilih, dan server tetap memeriksa kecocokan jenis akun.
3. Tekan **Kirim transaksi**. Maksimal 50 baris diproses dalam satu batch atomik. Baris yang valid tersimpan sekaligus sebagai permintaan pending; saldo belum berubah dan jurnal belum dibuat.
4. Buka **Petty Cash V2 · persetujuan** untuk memeriksa serta menyetujui/menolak transaksi. Penginput tidak dapat menyetujui permintaannya sendiri. Pengeluaran juga mengikuti pembatasan pemegang petty cash dan tanggal tutup kas.

Grid V3 tidak mengunggah lampiran. Kolom **Referensi bukti** wajib diisi; bukti fisik tetap harus disimpan dan dapat ditinjau melalui prosedur organisasi. Gunakan formulir V2/lama apabila perlu melampirkan file bukti.

## Akun kas yang dipakai

| Jenis dana | Akun buku besar |
|---|---|
| Laci kasir POS | `1101` |
| Kas utama | `1106` |
| Petty cash | `1107` |

Penambahan dana dan pengeluaran membentuk jurnal berpasangan. Pembayaran hutang supplier mendebit hutang (`2101`) dan mengkredit kas; pembayaran tersebut bukan beban kedua kali.

## Jika halaman baru tidak tersedia

1. Pastikan pengguna memiliki hak akses `accounting` atau `petty_cash` pada group-nya.
2. Untuk petty cash, pastikan skema tahap dasar, purchasing, dan kas sudah dipasang sesuai urutan yang disebutkan pada pesan kesalahan.
3. Jika tidak ingin memakai halaman baru, pilih tautan/menu versi lama. Tidak ada migrasi atau konversi data yang diperlukan.
