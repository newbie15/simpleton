@echo off
REM =====================================================================
REM Install Cloudflare Tunnel sebagai Windows Service (Auto-Start)
REM Wajib dijalankan dengan "Run as Administrator"
REM =====================================================================
setlocal
set "CLOUDFLARED=%~dp0..\..\tools\cloudflared.exe"

echo =====================================================================
echo Memasang Cloudflare Tunnel sebagai Windows Service...
echo =====================================================================
echo.

"%CLOUDFLARED%" service install

if %ERRORLEVEL% EQU 0 (
    echo.
    echo Menjalankan service cloudflared...
    net start cloudflared
    echo [SUKSES] Cloudflare Tunnel sekarang aktif di latar belakang (Windows Service).
) else (
    echo.
    echo [PERHATIAN] Pemasangan service gagal atau memerlukan hak akses Administrator.
    echo Pastikan Anda mengklik kanan berkas ini ^> "Run as administrator".
)

pause
endlocal
