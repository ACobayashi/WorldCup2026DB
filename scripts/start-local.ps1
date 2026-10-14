param(
  [string]$XamppRoot = 'E:\XAMPP',
  [int]$WebPort = 8088,
  [int]$DatabasePort = 3307
)
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$runtimeRoot = Join-Path $projectRoot 'storage\runtime'
$dbData = Join-Path $runtimeRoot 'mysql'
$phpExe = Join-Path $XamppRoot 'php\php.exe'
$mysqlExe = Join-Path $XamppRoot 'mysql\bin\mysqld.exe'
$installer = Join-Path $XamppRoot 'mysql\bin\mysql_install_db.exe'
foreach ($binary in @($phpExe, $mysqlExe, $installer)) {
  if (!(Test-Path -LiteralPath $binary)) { throw "Missing XAMPP executable: $binary. Pass -XamppRoot with your installation path." }
}
function New-LocalSecret {
  $bytes = New-Object byte[] 24
  $random = [Security.Cryptography.RandomNumberGenerator]::Create()
  try { $random.GetBytes($bytes) } finally { $random.Dispose() }
  return [Convert]::ToBase64String($bytes)
}
function Test-LocalPort([int]$Port) {
  $client = New-Object Net.Sockets.TcpClient
  try { $client.Connect('127.0.0.1', $Port); return $true } catch { return $false } finally { $client.Dispose() }
}
function Get-OwnedProcess([string]$PidFile, [string]$Executable, [string]$ExpectedArgument) {
  if (!(Test-Path -LiteralPath $PidFile)) { return $null }
  $savedId = [int](Get-Content -LiteralPath $PidFile)
  $process = Get-Process -Id $savedId -ErrorAction SilentlyContinue
  if ($process -and $process.Path -eq $Executable) {
    $details = Get-CimInstance Win32_Process -Filter "ProcessId=$savedId"
    if ($details.CommandLine.Contains($ExpectedArgument)) { return $process }
  }
  return $null
}
New-Item -ItemType Directory -Path $runtimeRoot -Force | Out-Null
$settingsFile = Join-Path $runtimeRoot 'local-settings.json'
$envFile = Join-Path $projectRoot '.env'
if (!(Test-Path -LiteralPath $settingsFile)) {
  if (Test-Path -LiteralPath $envFile) { throw 'An existing .env was found. Use your configured database and the manual README commands, or preserve it before initializing local development.' }
  $settings = @{ rootPassword = (New-LocalSecret); appPassword = (New-LocalSecret); appKey = (New-LocalSecret); databasePort = $DatabasePort }
  $settings | ConvertTo-Json | Set-Content -LiteralPath $settingsFile -Encoding UTF8
  $lines = @("DB_DSN=mysql:host=127.0.0.1;port=$DatabasePort;dbname=worldcup2026;charset=utf8mb4", 'DB_USER=worldcup_app', "DB_PASSWORD=$($settings.appPassword)", "APP_KEY=$($settings.appKey)")
  # UTF8 without BOM works in both Windows PowerShell and PowerShell 7.
  [IO.File]::WriteAllLines($envFile, $lines, (New-Object Text.UTF8Encoding($false)))
} else {
  $settings = Get-Content -LiteralPath $settingsFile -Raw | ConvertFrom-Json
  if ($settings.databasePort -ne $DatabasePort) { throw 'Use the same DatabasePort as the existing local installation.' }
}
$env:LOCAL_DB_ROOT_PASSWORD = $settings.rootPassword
$dbPidFile = Join-Path $runtimeRoot 'mysql.pid'
$dbProcess = Get-OwnedProcess $dbPidFile $mysqlExe (Join-Path $dbData 'my.ini')
if (!$dbProcess) {
  if (Test-LocalPort $DatabasePort) { throw "Port $DatabasePort is occupied; the existing service will not be touched." }
  if (!(Test-Path -LiteralPath (Join-Path $dbData 'mysql'))) {
    & $installer "--datadir=$dbData" "--password=$($settings.rootPassword)" "--port=$DatabasePort"
    if ($LASTEXITCODE -ne 0) { throw 'Isolated MariaDB initialization failed.' }
  }
  $dbConfig = Join-Path $dbData 'my.ini'
  $dbProcess = Start-Process -FilePath $mysqlExe -ArgumentList @("--defaults-file=" + '"' + $dbConfig + '"', '--bind-address=127.0.0.1', '--skip-name-resolve', "--port=$DatabasePort", '--console') -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeRoot 'mysql.out.log') -RedirectStandardError (Join-Path $runtimeRoot 'mysql.err.log') -PassThru
  $dbProcess.Id | Set-Content -LiteralPath $dbPidFile
}
$ready = $false
for ($attempt = 0; $attempt -lt 40; $attempt++) {
  if (Test-LocalPort $DatabasePort) { $ready = $true; break }
  if ($dbProcess.HasExited) { throw 'Local database exited; inspect storage/runtime/mysql.err.log.' }
  Start-Sleep -Milliseconds 250
}
if (!$ready) { throw 'Local database did not become ready.' }
& $phpExe (Join-Path $PSScriptRoot 'setup-local.php')
if ($LASTEXITCODE -ne 0) { throw 'Local schema setup failed.' }
Remove-Item Env:\LOCAL_DB_ROOT_PASSWORD -ErrorAction SilentlyContinue
$webPidFile = Join-Path $runtimeRoot 'web.pid'
$webProcess = Get-OwnedProcess $webPidFile $phpExe 'public/router.php'
if (!$webProcess) {
  if (Test-LocalPort $WebPort) { throw "Port $WebPort is occupied; the existing service will not be touched." }
  $webProcess = Start-Process -FilePath $phpExe -ArgumentList @('-S', "127.0.0.1:$WebPort", '-t', 'public', 'public/router.php') -WorkingDirectory $projectRoot -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtimeRoot 'web.out.log') -RedirectStandardError (Join-Path $runtimeRoot 'web.err.log') -PassThru
  $webProcess.Id | Set-Content -LiteralPath $webPidFile
}
$ready = $false
for ($attempt = 0; $attempt -lt 40; $attempt++) {
  if (Test-LocalPort $WebPort) { $ready = $true; break }
  Start-Sleep -Milliseconds 250
}
if (!$ready) { throw 'Local PHP server did not become ready.' }
$health = Invoke-RestMethod -Uri "http://127.0.0.1:$WebPort/api.php?action=bootstrap"
if (!$health.ok) { throw 'Guestbook startup check failed.' }
Write-Output "Homepage: http://127.0.0.1:$WebPort/"
Write-Output "Workspace: http://127.0.0.1:$WebPort/admin.php"
Write-Output "Random local accounts: $projectRoot\storage\local-accounts.txt"
Write-Output 'Existing XAMPP data is not used. Re-running keeps this project database.'
