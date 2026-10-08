@echo off
REM =====================================================================
REM Jalankan Cloudflare Quick Tunnel untuk Uji Akses Luar (Sementara)
REM Tidak membutuhkan login / domain. Langsung membuat link HTTPS publik.
REM =====================================================================
setlocal
set "CLOUDFLARED=%~dp0..\..\tools\cloudflared.exe"

if not exist "%CLOUDFLARED%" (
    echo [ERROR] cloudflared.exe tidak ditemukan di folder tools!
    pause
    exit /b 1
)

echo =====================================================================
echo Memulai Cloudflare Quick Tunnel...
echo Local Target: http://localhost:80
echo Perhatikan URL https://xxxx.trycloudflare.com yang muncul di bawah ini.
echo Anda dapat membuka link tersebut dari HP/jaringan luar:
echo contoh: https://xxxx.trycloudflare.com/simpleton
echo Tekan Ctrl + C untuk menghentikan tunnel.
echo =====================================================================
echo.

"%CLOUDFLARED%" tunnel --url http://localhost:80

endlocal
