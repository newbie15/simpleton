# Modul Backup & Restore Database Koperasi Simpleton

Modul ini mengotomatiskan pencadangan database MySQL (`simpleton`) dengan kompresi dan enkripsi militer AES-256 menggunakan **7-Zip**, serta pembersihan otomatis (*retention pruning*).

---

## Struktur Berkas

* **[config.ini](file:///g:/xampp/htdocs/simpleton/scripts/backup/config.ini)**: Berkas konfigurasi utama (kredensial database, path dump, password enkripsi arsip, dan masa retensi).
* **[backup_db.ps1](file:///g:/xampp/htdocs/simpleton/scripts/backup/backup_db.ps1)**: Script inti PowerShell untuk dump, kompresi, enkripsi, dan rotasi file.
* **[backup_db.bat](file:///g:/xampp/htdocs/simpleton/scripts/backup/backup_db.bat)**: Launcher berkas `.bat` (dapat diklik ganda atau dipanggil Task Scheduler).
* **[install_scheduler.bat](file:///g:/xampp/htdocs/simpleton/scripts/backup/install_scheduler.bat)**: Script pendaftaran otomatis jadwal harian (pukul 23:00 WIB) ke Windows Task Scheduler.
* **[restore_db.ps1](file:///g:/xampp/htdocs/simpleton/scripts/backup/restore_db.ps1)**: Script pemulihan (*disaster recovery*) untuk menguji dan memulihkan data dari arsip `.7z` terenkripsi.

---

## Cara Menjalankan Backup Manual

Cukup klik ganda berkas **[backup_db.bat](file:///g:/xampp/htdocs/simpleton/scripts/backup/backup_db.bat)** atau jalankan via PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File "g:\xampp\htdocs\simpleton\scripts\backup\backup_db.ps1"
```

Hasil backup tersimpan di:  
`G:\xampp\htdocs\simpleton\backups\daily\simpleton_backup_YYYYMMDD_HHMMSS.7z`  
Log riwayat backup tercatat di:  
`G:\xampp\htdocs\simpleton\backups\backup.log`

---

## Cara Menjadwalkan Otomatis (Harian Pukul 23:00)

1. Buka folder `scripts\backup\`.
2. Klik kanan pada **[install_scheduler.bat](file:///g:/xampp/htdocs/simpleton/scripts/backup/install_scheduler.bat)** -> pilih **Run as administrator**.
3. Tugas otomatis bernama **`Simpleton_Database_Backup`** akan dibuat pada Windows Task Scheduler.

---

## Cara Menjalankan Restore Database

Untuk mengembalikan database dari file backup terbaru:

```powershell
powershell -ExecutionPolicy Bypass -File "g:\xampp\htdocs\simpleton\scripts\backup\restore_db.ps1"
```

Atau tentukan file backup spesifik:

```powershell
powershell -ExecutionPolicy Bypass -File "g:\xampp\htdocs\simpleton\scripts\backup\restore_db.ps1" -BackupFile "G:\xampp\htdocs\simpleton\backups\daily\simpleton_backup_20261007_003523.7z"
```
