param([string]$XamppRoot = 'E:\XAMPP')
$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$runtimeRoot = Join-Path $projectRoot 'storage\runtime'
foreach ($name in @('web', 'mysql')) {
  $pidFile = Join-Path $runtimeRoot "$name.pid"
  if (!(Test-Path -LiteralPath $pidFile)) { continue }
  $savedId = [int](Get-Content -LiteralPath $pidFile)
  $process = Get-Process -Id $savedId -ErrorAction SilentlyContinue
  $expectedExecutable = if ($name -eq 'web') { Join-Path $XamppRoot 'php\php.exe' } else { Join-Path $XamppRoot 'mysql\bin\mysqld.exe' }
  if ($process) {
    $details = Get-CimInstance Win32_Process -Filter "ProcessId=$savedId"
    $expectedArgument = if ($name -eq 'web') { 'public/router.php' } else { Join-Path $runtimeRoot 'mysql\my.ini' }
    if ($process.Path -ne $expectedExecutable -or !$details.CommandLine.Contains($expectedArgument)) {
      throw "Process $savedId does not match this project's $name server; leaving it running."
    }
    if ($name -eq 'mysql') {
      $settings = Get-Content -LiteralPath (Join-Path $runtimeRoot 'local-settings.json') -Raw | ConvertFrom-Json
      $previousPassword = $env:MYSQL_PWD
      try {
        $env:MYSQL_PWD = $settings.rootPassword
        & (Join-Path $XamppRoot 'mysql\bin\mysqladmin.exe') --protocol=tcp --host=127.0.0.1 "--port=$($settings.databasePort)" --user=root shutdown
        if ($LASTEXITCODE -ne 0) { throw 'Database shutdown failed; leaving the PID file for inspection.' }
      } finally {
        if ($null -eq $previousPassword) { Remove-Item Env:\MYSQL_PWD -ErrorAction SilentlyContinue }
        else { $env:MYSQL_PWD = $previousPassword }
      }
    } else {
      Stop-Process -Id $savedId
    }
  }
  Remove-Item -LiteralPath $pidFile
}
Write-Output 'Project servers stopped. Database files and accounts are preserved.'
