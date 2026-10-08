@echo off
REM =====================================================================
REM Otorisasi Akun Cloudflare (Langkah 1 untuk Domain Sendiri)
REM =====================================================================
setlocal
set "CLOUDFLARED=%~dp0..\..\tools\cloudflared.exe"

if not exist "%CLOUDFLARED%" (
    echo [ERROR] cloudflared.exe tidak ditemukan di folder tools!
    pause
    exit /b 1
)

echo =====================================================================
echo Membuka browser untuk login ke Cloudflare...
echo Silakan pilih domain yang akan dihubungkan dengan tunnel Simpleton.
echo =====================================================================
echo.

"%CLOUDFLARED%" tunnel login

echo.
echo Jika berhasil, sertifikat otorisasi (cert.pem) telah tersimpan.
pause
endlocal
