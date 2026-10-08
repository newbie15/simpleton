# Panduan Konfigurasi Cloudflare Tunnel (`cloudflared`) untuk Simpleton

Panduan ini menjelaskan cara menghubungkan server lokal XAMPP (Apache) koperasi ke Cloudflare menggunakan **Cloudflare Tunnel (`cloudflared`)**. Dengan metode ini:
- Server lokal dapat diakses dari internet via domain HTTPS resmi (misal `https://toko.koperasiku.my.id`).
- **Tidak perlu IP publik statis**.
- **Tidak perlu port forwarding** pada router/modem kantor.
- Kasir di toko tetap mengakses via LAN (`http://localhost/simpleton` atau IP lokal) sehingga **100% jalan saat internet toko terputus**.

---

## 1. Status Instalasi di Proyek Ini

Binary resmi `cloudflared` telah terunduh dan siap digunakan:
* **Lokasi Binary**: `G:\xampp\htdocs\simpleton\tools\cloudflared.exe` (Versi: `2026.10.0`)
* **Skrip Otomatis**: Tersedia di folder **[scripts/tunnel/](file:///g:/xampp/htdocs/simpleton/scripts/tunnel/)**

---

## 2. Opsi A: Uji Coba Cepat (Quick Tunnel - Tanpa Login / Tanpa Domain)

Jika ingin langsung mencoba mengakses Simpleton dari handphone atau luar jaringan kantor tanpa mendaftar domain:

1. Klik ganda berkas **[scripts/tunnel/start_quick_tunnel.bat](file:///g:/xampp/htdocs/simpleton/scripts/tunnel/start_quick_tunnel.bat)**.
2. Perhatikan baris log yang muncul di terminal, akan ada tautan acak seperti:  
   `https://random-subdomain.trycloudflare.com`
3. Buka tautan tersebut dari browser ponsel dengan menambahkan path `/simpleton`:  
   `https://random-subdomain.trycloudflare.com/simpleton`
4. Tekan `Ctrl + C` di jendela terminal jika selesai menguji.

---

## 3. Opsi B: Setup Tunnel Resmi dengan Domain Sendiri (Produksi)

Untuk penggunaan permanen dengan nama domain koperasi Anda (misal `toko.koperasiku.my.id`):

### Langkah 1: Otorisasi Akun Cloudflare
1. Pastikan domain Anda sudah terdaftar di Cloudflare.
2. Klik ganda berkas **[scripts/tunnel/login_named_tunnel.bat](file:///g:/xampp/htdocs/simpleton/scripts/tunnel/login_named_tunnel.bat)**.
3. Halaman browser Cloudflare akan terbuka otomatis; login dan pilih nama domain Anda.
4. Sertifikat otorisasi (`cert.pem`) akan tersimpan di `%USERPROFILE%\.cloudflared\cert.pem`.

### Langkah 2: Buat Tunnel dan Hubungkan Subdomain
1. Klik ganda berkas **[scripts/tunnel/setup_named_tunnel.bat](file:///g:/xampp/htdocs/simpleton/scripts/tunnel/setup_named_tunnel.bat)**.
2. Masukkan nama tunnel yang diinginkan (misal: `simpleton-koperasi`).
3. Masukkan subdomain yang ingin digunakan (misal: `toko.koperasiku.my.id`).
4. Skrip akan membuat tunnel dan otomatis mendaftarkan DNS CNAME di akun Cloudflare Anda.

### Langkah 3: Pasang sebagai Windows Service (Aktif 24/7 Otomatis)
Agar tunnel tetap berjalan di latar belakang setiap kali komputer kasir/server dinyalakan:
1. Klik kanan pada **[scripts/tunnel/install_service.bat](file:///g:/xampp/htdocs/simpleton/scripts/tunnel/install_service.bat)**.
2. Pilih **Run as administrator**.
3. Tunnel akan terdaftar sebagai Windows Service bernama `cloudflared` dan aktif secara otomatis saat booting.

---

## 4. Keamanan dan Batasan

* **Apache Lokal Tetap Aman**: Apache hanya mendengarkan di `localhost:80` dan LAN toko. Port 80/443 tidak perlu dibuka di modem/router.
* **Firewall & Proteksi DDoS**: Seluruh traffic dari luar melewati firewall dan filter keamanan Cloudflare Edge sebelum diteruskan ke server lokal.
