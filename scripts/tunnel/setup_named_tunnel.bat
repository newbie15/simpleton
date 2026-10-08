@echo off
REM =====================================================================
REM Setup Named Cloudflare Tunnel & Windows Service
REM =====================================================================
setlocal
set "CLOUDFLARED=%~dp0..\..\tools\cloudflared.exe"

if not exist "%CLOUDFLARED%" (
    echo [ERROR] cloudflared.exe tidak ditemukan di folder tools!
    pause
    exit /b 1
)

echo =====================================================================
echo Setup Cloudflare Named Tunnel untuk Simpleton
echo =====================================================================
echo.
set /p TUNNEL_NAME="Masukkan nama tunnel (contoh: simpleton-koperasi): "
if "%TUNNEL_NAME%"=="" (
    echo Nama tunnel tidak boleh kosong.
    pause
    exit /b 1
)

echo.
echo Membuat tunnel '%TUNNEL_NAME%'...
"%CLOUDFLARED%" tunnel create "%TUNNEL_NAME%"

echo.
set /p DOMAIN_NAME="Masukkan domain/subdomain (contoh: toko.koperasiku.my.id): "
if "%DOMAIN_NAME%"=="" (
    echo Nama domain tidak boleh kosong.
    pause
    exit /b 1
)

echo.
echo Mendaftarkan DNS route ke Cloudflare...
"%CLOUDFLARED%" tunnel route dns "%TUNNEL_NAME%" "%DOMAIN_NAME%"

echo.
echo =====================================================================
echo Tunnel '%TUNNEL_NAME%' berhasil dibuat dan terhubung ke '%DOMAIN_NAME%'!
echo Untuk menjalankan tunnel sebagai Windows Service (otomatis 24/7):
echo Jalankan script: scripts\tunnel\install_service.bat (sebagai Administrator)
echo =====================================================================
pause
endlocal
