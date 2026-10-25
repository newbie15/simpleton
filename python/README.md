# Bot Telegram Anggota Koperasi

Bot terpisah dari aplikasi PHP 5 (long polling, tidak membuka port). Membaca MySQL lewat user `bot_tg` yang hanya punya akses ke VIEW `v_bot_*` plus tabel `telegram_links`/`telegram_login_fail`.

## Setup
1. Jalankan `application/sql/telegram_pin.sql` (menambah kolom `users.telegram_pin`).
2. Edit password di `python/sql/bot_setup.sql`, lalu jalankan sebagai admin MySQL (sesuaikan nama DB `simpleton`).
3. `python -m venv .venv`, aktifkan, `pip install -r requirements.txt`.
4. Salin `.env.example` ke `.env`, isi token dari @BotFather dan kredensial DB.
5. Jalankan: `python bot.py`.

## Fitur menu keuangan dan pencarian stok
Untuk instalasi bot yang sudah berjalan, jalankan `python/sql/bot_features.sql` sebagai admin MySQL satu kali. Script ini membuat dua VIEW agregat/cari dan memberi hak `SELECT` ke user bot tanpa mengubah password DB bot. Instalasi baru sudah mendapat VIEW tersebut melalui `bot_setup.sql`.

Semua anggota terverifikasi dapat melihat ringkasan keuangan agregat (posisi per hari ini, pendapatan/beban tahun berjalan, dan perubahan kas/bank tahun berjalan), bukan jurnal rinci. Pencarian barang menerima nama atau barcode dan hanya menampilkan nama, barcode, serta jumlah stok.

## Login anggota
Admin mengisi **PIN Telegram** (6 digit) di halaman web Add/Edit User; NPK user harus sama dengan NPK di tabel `karyawan`. PIN disimpan plaintext sesuai permintaan, jadi jaga akses DB dan backup.
Anggota dapat menekan tombol **Masuk sebagai anggota**, lalu mengirim NPK dan PIN secara bergantian sesuai prompt bot. Gunakan tombol **Batal** atau `/batal` untuk menghentikan proses. Pesan yang berisi PIN dihapus bot setelah dikirim. Cara lama `/masuk <NPK> <PIN>` juga tetap tersedia. 5 kali percobaan PIN salah mengunci NPK selama 15 menit.

## Perintah
`/masuk` `/batal` `/saldo` `/belanja` `/tagihan` `/shu` `/laporan` `/cari` `/keluar`

Asumsi: tagihan = transaksi `potong_gaji` 60 hari terakhir; SHU = `simpanan_mutasi` jenis `shu`. Ubah di view bila definisi koperasi berbeda.

## Menjalankan permanen (Windows)
Gunakan Task Scheduler (At startup) atau NSSM: `python <path>\python\bot.py` dengan working directory folder `python`.
