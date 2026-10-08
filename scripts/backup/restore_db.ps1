# =====================================================================
# Script Pemulihan (Restore) Database Koperasi Simpleton
# =====================================================================

param (
    [string]$BackupFile = "",
    [string]$TargetDatabase = "simpleton",
    [string]$ConfigFile = "$PSScriptRoot\config.ini"
)

$ErrorActionPreference = "Stop"

# 1. Parsing File Konfigurasi
if (!(Test-Path $ConfigFile)) {
    Write-Error "File konfigurasi tidak ditemukan: $ConfigFile"
    exit 1
}

$config = @{}
Get-Content $ConfigFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -and !$line.StartsWith(";") -and !$line.StartsWith("#") -and !$line.StartsWith("[")) {
        $parts = $line -split "=", 2
        if ($parts.Length -eq 2) {
            $config[$parts[0].Trim()] = $parts[1].Trim()
        }
    }
}

$dbUser = if ($config.ContainsKey("DB_USER")) { $config["DB_USER"] } else { "root" }
$dbPass = if ($config.ContainsKey("DB_PASS")) { $config["DB_PASS"] } else { "" }
$dbHost = if ($config.ContainsKey("DB_HOST")) { $config["DB_HOST"] } else { "localhost" }
$dbPort = if ($config.ContainsKey("DB_PORT")) { $config["DB_PORT"] } else { "3306" }
$mysqlExe = "G:\xampp\mysql\bin\mysql.exe"
$sevenZipExe = if ($config.ContainsKey("SEVENZIP_PATH")) { $config["SEVENZIP_PATH"] } else { "C:\Program Files\7-Zip\7z.exe" }
$archivePass = if ($config.ContainsKey("ARCHIVE_PASSWORD")) { $config["ARCHIVE_PASSWORD"] } else { "" }
$backupRoot = if ($config.ContainsKey("BACKUP_DIR")) { $config["BACKUP_DIR"] } else { "$PSScriptRoot\..\..\backups" }

if ($BackupFile -eq "") {
    # Ambil file backup terbaru dari folder daily
    $latest = Get-ChildItem -Path (Join-Path $backupRoot "daily") -File | 
              Where-Object { $_.Extension -in @(".7z", ".zip", ".sql") } | 
              Sort-Object LastWriteTime -Descending | 
              Select-Object -First 1

    if (!$latest) {
        Write-Host "Tidak ada file backup ditemukan di folder daily." -ForegroundColor Red
        exit 1
    }
    $BackupFile = $latest.FullName
}

if (!(Test-Path $BackupFile)) {
    Write-Host "File backup tidak ditemukan: $BackupFile" -ForegroundColor Red
    exit 1
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "PERINGATAN: PROSES RESTORE DATABASE" -ForegroundColor Yellow
Write-Host "File Backup     : $BackupFile"
Write-Host "Target Database : $TargetDatabase"
Write-Host "Host            : $dbHost:$dbPort"
Write-Host "==========================================================" -ForegroundColor Cyan

$tempSqlPath = ""
$isTemp = $false

if ($BackupFile.EndsWith(".7z")) {
    Write-Host "Mengekstrak arsip terenkripsi .7z..."
    $tempDir = [System.IO.Path]::GetTempPath()
    $tempSqlDir = Join-Path $tempDir "simpleton_restore_temp"
    if (!(Test-Path $tempSqlDir)) { New-Item -ItemType Directory -Path $tempSqlDir -Force | Out-Null }
    
    $args = @("x", "-p$archivePass", "-y", "-o$tempSqlDir", $BackupFile)
    $proc = Start-Process -FilePath $sevenZipExe -ArgumentList $args -Wait -NoNewWindow -PassThru
    if ($proc.ExitCode -ne 0) {
        Write-Host "Gagal mengekstrak arsip .7z (password salah atau file korup)." -ForegroundColor Red
        exit 1
    }
    
    $extractedFile = Get-ChildItem -Path $tempSqlDir -Filter "*.sql" | Select-Object -First 1
    if (!$extractedFile) {
        Write-Host "Tidak ada file .sql ditemukan dalam arsip." -ForegroundColor Red
        exit 1
    }
    $tempSqlPath = $extractedFile.FullName
    $isTemp = $true
} elseif ($BackupFile.EndsWith(".zip")) {
    Write-Host "Mengekstrak arsip .zip..."
    $tempDir = [System.IO.Path]::GetTempPath()
    $tempSqlDir = Join-Path $tempDir "simpleton_restore_temp"
    if (!(Test-Path $tempSqlDir)) { New-Item -ItemType Directory -Path $tempSqlDir -Force | Out-Null }
    Expand-Archive -Path $BackupFile -DestinationPath $tempSqlDir -Force
    $extractedFile = Get-ChildItem -Path $tempSqlDir -Filter "*.sql" | Select-Object -First 1
    $tempSqlPath = $extractedFile.FullName
    $isTemp = $true
} else {
    $tempSqlPath = $BackupFile
}

Write-Host "Mengimpor SQL ke database '$TargetDatabase'..." -ForegroundColor Green
$cmd = "& `"$mysqlExe`" --host=$dbHost --port=$dbPort --user=$dbUser "
if ($dbPass -ne "") { $cmd += "--password=$dbPass " }
$cmd += "$TargetDatabase < `"$tempSqlPath`""

powershell.exe -Command $cmd

if ($LASTEXITCODE -eq 0) {
    Write-Host "RESTORE BERHASIL SELESAI!" -ForegroundColor Green
} else {
    Write-Host "RESTORE GAGAL dengan exit code $LASTEXITCODE." -ForegroundColor Red
}

if ($isTemp -and (Test-Path $tempSqlPath)) {
    Remove-Item (Split-Path $tempSqlPath -Parent) -Recurse -Force -ErrorAction SilentlyContinue
}
