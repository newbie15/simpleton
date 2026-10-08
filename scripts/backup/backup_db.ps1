# =====================================================================
# Script Backup Otomatis Database Koperasi Simpleton
# =====================================================================

param (
    [string]$ConfigFile = "$PSScriptRoot\config.ini"
)

$ErrorActionPreference = "Stop"
$startTime = Get-Date

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    $timestamp = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    $logLine = "[$timestamp] [$Level] $Message"
    Write-Host $logLine
    if ($global:LogFilePath) {
        Add-Content -Path $global:LogFilePath -Value $logLine
    }
}

# 1. Parsing File Konfigurasi (INI)
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

$dbName = if ($config.ContainsKey("DB_NAME")) { $config["DB_NAME"] } else { "simpleton" }
$dbUser = if ($config.ContainsKey("DB_USER")) { $config["DB_USER"] } else { "root" }
$dbPass = if ($config.ContainsKey("DB_PASS")) { $config["DB_PASS"] } else { "" }
$dbHost = if ($config.ContainsKey("DB_HOST")) { $config["DB_HOST"] } else { "localhost" }
$dbPort = if ($config.ContainsKey("DB_PORT")) { $config["DB_PORT"] } else { "3306" }
$dumpExe = if ($config.ContainsKey("MYSQLDUMP_PATH")) { $config["MYSQLDUMP_PATH"] } else { "G:\xampp\mysql\bin\mysqldump.exe" }
$sevenZipExe = if ($config.ContainsKey("SEVENZIP_PATH")) { $config["SEVENZIP_PATH"] } else { "C:\Program Files\7-Zip\7z.exe" }
$backupRoot = if ($config.ContainsKey("BACKUP_DIR")) { $config["BACKUP_DIR"] } else { "$PSScriptRoot\..\..\backups" }
$archivePass = if ($config.ContainsKey("ARCHIVE_PASSWORD")) { $config["ARCHIVE_PASSWORD"] } else { "" }
$retentionDays = if ($config.ContainsKey("RETENTION_DAYS")) { [int]$config["RETENTION_DAYS"] } else { 14 }

# 2. Inisialisasi Direktori
$dailyDir = Join-Path $backupRoot "daily"
if (!(Test-Path $dailyDir)) {
    New-Item -ItemType Directory -Path $dailyDir -Force | Out-Null
}

$global:LogFilePath = Join-Path $backupRoot "backup.log"

Write-Log "Memulai proses backup database '$dbName'..." "INFO"

# 3. Validasi Tool mysqldump
if (!(Test-Path $dumpExe)) {
    Write-Log "mysqldump.exe tidak ditemukan di: $dumpExe" "ERROR"
    exit 1
}

$timeString = (Get-Date).ToString("yyyyMMdd_HHmmss")
$sqlFilename = "${dbName}_backup_${timeString}.sql"
$sqlPath = Join-Path $dailyDir $sqlFilename

# 4. Eksekusi mysqldump
Write-Log "Mengekspor struktur dan data tabel..." "INFO"

$dumpArgs = @(
    "--host=$dbHost",
    "--port=$dbPort",
    "--user=$dbUser",
    "--single-transaction",
    "--routines",
    "--triggers",
    "--quick",
    "--default-character-set=utf8mb4",
    "--result-file=$sqlPath"
)

if ($dbPass -ne "") {
    $dumpArgs += "--password=$dbPass"
}

$dumpArgs += $dbName

$process = Start-Process -FilePath $dumpExe -ArgumentList $dumpArgs -Wait -NoNewWindow -PassThru

if ($process.ExitCode -ne 0 -or !(Test-Path $sqlPath)) {
    Write-Log "mysqldump gagal dengan exit code $($process.ExitCode)" "ERROR"
    exit 1
}

$sqlSize = (Get-Item $sqlPath).Length
if ($sqlSize -le 100) {
    Write-Log "File SQL hasil dump terlalu kecil ($sqlSize bytes), kemungkinan terjadi kesalahan ekspor." "ERROR"
    Remove-Item $sqlPath -Force -ErrorAction SilentlyContinue
    exit 1
}

Write-Log "Ekspor SQL berhasil ($([Math]::Round($sqlSize / 1KB, 2)) KB)." "INFO"

# 5. Kompresi dan Enkripsi
$finalArchivePath = ""
if ((Test-Path $sevenZipExe) -and ($archivePass -ne "")) {
    $archiveName = "${dbName}_backup_${timeString}.7z"
    $archivePath = Join-Path $dailyDir $archiveName
    Write-Log "Mengompresi dan mengenkripsi dengan 7-Zip AES-256..." "INFO"

    $sevenZipArgs = @(
        "a",
        "-t7z",
        "-m0=lzma2",
        "-mx=9",
        "-p$archivePass",
        "-mhe=on",
        "$archivePath",
        "$sqlPath"
    )

    $zipProcess = Start-Process -FilePath $sevenZipExe -ArgumentList $sevenZipArgs -Wait -NoNewWindow -PassThru
    if ($zipProcess.ExitCode -eq 0 -and (Test-Path $archivePath)) {
        Remove-Item $sqlPath -Force
        $finalArchivePath = $archivePath
    } else {
        Write-Log "Gagal mengenkripsi dengan 7-Zip, beralih ke kompresi standar..." "WARN"
    }
}

if ($finalArchivePath -eq "") {
    $archiveName = "${dbName}_backup_${timeString}.zip"
    $archivePath = Join-Path $dailyDir $archiveName
    Write-Log "Mengompresi dengan zip standar..." "INFO"
    Compress-Archive -Path $sqlPath -DestinationPath $archivePath -Force
    Remove-Item $sqlPath -Force
    $finalArchivePath = $archivePath
}

$archiveSize = (Get-Item $finalArchivePath).Length
Write-Log "File arsip berhasil dibuat: $(Split-Path $finalArchivePath -Leaf) ($([Math]::Round($archiveSize / 1KB, 2)) KB)." "INFO"

# 6. Rotasi Retensi (Hapus backup yang lebih lama dari RETENTION_DAYS)
Write-Log "Menjalankan pembersihan retensi ($retentionDays hari)..." "INFO"
$cutoffDate = (Get-Date).AddDays(-$retentionDays)
$purgedCount = 0

Get-ChildItem -Path $dailyDir -File | Where-Object {
    ($_.Extension -in @(".7z", ".zip", ".sql")) -and ($_.LastWriteTime -lt $cutoffDate)
} | ForEach-Object {
    Write-Log "Menghapus backup usang: $($_.Name) (Tanggal: $($_.LastWriteTime))" "INFO"
    Remove-Item $_.FullName -Force
    $purgedCount++
}

$duration = [Math]::Round(((Get-Date) - $startTime).TotalSeconds, 2)
Write-Log "Proses backup selesai dengan sukses dalam $duration detik. (File dibersihkan: $purgedCount)." "INFO"
exit 0
