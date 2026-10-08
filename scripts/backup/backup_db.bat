@echo off
REM =====================================================================
REM Launcher Backup Database Koperasi Simpleton
REM =====================================================================
setlocal
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0backup_db.ps1"
if %ERRORLEVEL% NEQ 0 (
    echo [ERROR] Backup gagal! Periksa file log di folder backups.
    exit /b %ERRORLEVEL%
)
echo [SUCCESS] Backup selesai.
endlocal
