# Matriks Hak Akses & Peran Pengguna (RBAC Matrix) Koperasi Simpleton

Sistem mengimplementasikan **Role-Based Access Control (RBAC)** dengan 5 peran resmi yang terdaftar pada tabel `groups` (Ion Auth):

1. **Superadmin (`admin`)**
2. **Pengurus (`pengurus`)**
3. **Management (`management`)**
4. **Kasir (`kasir`)**
5. **Anggota (`members`)**

---

## 1. Matriks Otorisasi per Modul

| Modul / Fitur | Superadmin | Pengurus | Management | Kasir | Anggota |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Point of Sales (Kasir)** | | | | | |
| - Buka Shift & Transaksi Kasir | Ya | Tidak | Ya | **Ya** | Tidak |
| - Terapkan Voucher Belanja | Ya | Tidak | Ya | **Ya** | Tidak |
| - Batalkan Keranjang (sebelum simpan) | Ya | Tidak | Ya | **Ya** | Tidak |
| - Cetak Ulang Struk Transaksi Shift | Ya | Ya | Ya | **Ya** | Tidak |
| - Retur Penjualan (Customer Return) | **Ya** | **Approval** | **Ya** | Input Saja | Tidak |
| **Inventory & Purchasing** | | | | | |
| - Master Data Barang & Harga | Ya | Lihat | **Ya** | Lihat Saja | Tidak |
| - Relasi Kemasan Dus vs Pcs | Ya | Lihat | **Ya** | Tidak | Tidak |
| - Buat Purchase Order (PO) | Ya | **Approval** | **Ya** | Tidak | Tidak |
| - Penerimaan Barang (Goods Receipt) | Ya | Tidak | **Ya** | Tidak | Tidak |
| - Retur Barang ke Supplier | Ya | **Approval** | **Ya** | Tidak | Tidak |
| - Stok Opname (Hitung & Finalisasi) | Ya | **Approval** | **Ya** | Petugas Hitung | Tidak |
| **Accounting & Keuangan** | | | | | |
| - Jurnal Umum & Buku Besar | Ya | **Ya** | Lihat | Tidak | Tidak |
| - Neraca & Laba Rugi | Ya | **Ya** | Ringkasan | Tidak | Tidak |
| - Laporan Arus Kas | Ya | **Ya** | Tidak | Tidak | Tidak |
| - Rekonsiliasi Bank Manual | Ya | **Ya** | Tidak | Tidak | Tidak |
| - Tutup Periode Akuntansi | **Ya** | **Ya** | Tidak | Tidak | Tidak |
| **Simpanan Anggota & RAT** | | | | | |
| - Input Mutasi Simpanan (Setor/Tarik) | Ya | **Ya** | Tidak | Tidak | Tidak |
| - Cetak Buku Mutasi Simpanan | Ya | **Ya** | Tidak | Tidak | Data Sendiri |
| - Perhitungan & Alokasi SHU | Ya | **Ya** | Tidak | Tidak | Data Sendiri |
| - Publikasi Dokumen RAT | Ya | **Ya** | Tidak | Tidak | Unduh |
| **Online Shop for Member** | | | | | |
| - Belanja & Checkout Online | Tidak | Tidak | Tidak | Tidak | **Ya** |
| - Histori Belanja Online Pribadi | Tidak | Tidak | Tidak | Tidak | **Ya** |
| - Pemrosesan Pesanan Masuk (Fulfillment)| Ya | Tidak | **Ya** | **Ya** | Tidak |
| **Sistem & Konfigurasi** | | | | | |
| - Manajemen Pengguna & Password | **Ya** | Tidak | Tidak | Tidak | Ganti Password |
| - Konfigurasi Sistem & Backup DB | **Ya** | Tidak | Tidak | Tidak | Tidak |
| - Audit Trail Log | **Ya** | **Ya** | Tidak | Tidak | Tidak |

---

## 2. Prinsip Penegakan Keamanan (Server-Side Enforcement)

1. **Pengecekan Berlapis**: Penegakan hak akses **wajib dilakukan di controller CodeIgniter** (menggunakan pengecekan method `ion_auth->in_group()`), bukan sekadar menyembunyikan link menu di file view/HTML.
2. **Proteksi Akses Data Anggota**: Anggota (`members`) hanya boleh membaca data yang berkorelasi dengan `karyawan_id` miliknya sendiri. Akses terhadap ID anggota lain langsung ditolak dengan `HTTP 403 Forbidden`.
