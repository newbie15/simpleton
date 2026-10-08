@echo off
REM =====================================================================
REM Registrasi Task Scheduler Backup Otomatis Database Koperasi
REM Menjalankan backup setiap hari pukul 23:00 WIB
REM =====================================================================
setlocal

set "TASK_NAME=Simpleton_Database_Backup"
set "SCRIPT_BAT=%~dp0backup_db.bat"

echo Mendaftarkan Task Scheduler: %TASK_NAME%
echo Lokasi Script: %SCRIPT_BAT%
echo Jadwal       : Harian pukul 23:00 WIB
echo.

schtasks /create /tn "%TASK_NAME%" /tr "\"%SCRIPT_BAT%\"" /sc daily /st 23:00 /f

if %ERRORLEVEL% EQU 0 (
    echo.
    echo =========================================================
    echo [SUKSES] Task Scheduler berhasil didaftarkan!
    echo Backup akan berjalan otomatis setiap hari pada pukul 23:00.
    echo Anda dapat memantau atau mengujinya di Task Scheduler Windows.
    echo =========================================================
) else (
    echo.
    echo =========================================================
    echo [CATATAN] Jika pendaftaran gagal karena izin akses,
    echo silakan jalankan file ini dengan cara:
    echo Klik kanan install_scheduler.bat ^> "Run as administrator"
    echo =========================================================
)

pause
endlocal
