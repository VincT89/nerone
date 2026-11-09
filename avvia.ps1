param([string]$PhpPath = $env:PHP_BIN, [int]$Port = 8741)
$ErrorActionPreference = 'Stop'
Set-Location -LiteralPath $PSScriptRoot
if (-not $PhpPath) {
    $projectPortable = Join-Path $env:USERPROFILE '.codex\visualizations\2026\11\02\01a19bcb-4666-7a50-af3c-0618940bb512\work\nerone\runtime\php\php.exe'
    if (Test-Path -LiteralPath $projectPortable) { $PhpPath = $projectPortable }
    else { $PhpPath = (Get-Command php -ErrorAction SilentlyContinue).Source }
}
if (-not $PhpPath) { throw 'PHP non trovato. Specifica il percorso con -PhpPath.' }
& $PhpPath -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);'
if ($LASTEXITCODE -ne 0) { throw 'Il progetto richiede PHP 8.4.1 o successivo. Specifica un runtime aggiornato con -PhpPath.' }
$projectIni = Join-Path (Split-Path -Parent $PhpPath) 'php.ini'
$projectOptions = @()
if (Test-Path -LiteralPath $projectIni) { $projectOptions = @('-c', $projectIni) }
& $PhpPath @projectOptions artisan serve --host=127.0.0.1 --port=$Port
