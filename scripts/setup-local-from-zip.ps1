# Extract Moodle 5.02 from official Windows installer zip (OSPanel / local dev).
# Usage: .\scripts\setup-local-from-zip.ps1

$ErrorActionPreference = 'Stop'
$Root = Split-Path $PSScriptRoot -Parent
$Zip = Join-Path $Root 'moodle\MoodleWindowsInstaller-latest-502.zip'

if (-not (Test-Path $Zip)) {
    Write-Error "Zip not found: $Zip"
}

$Temp = Join-Path $env:TEMP "moodle-kaznu-extract-$(Get-Random)"
New-Item -ItemType Directory -Path $Temp -Force | Out-Null

Write-Host "==> Extracting server/moodle from zip (may take a few minutes)..."
tar -xf $Zip -C $Temp server/moodle

$Target = Join-Path $Root 'moodle'
$Staging = Join-Path $Temp 'server\moodle'

if (Test-Path (Join-Path $Target 'index.php')) {
    Write-Host "==> Moodle already extracted at $Target - skipping"
} else {
    if (Test-Path (Join-Path $Target '.git')) {
        Remove-Item -Recurse -Force (Join-Path $Target '.git')
    }
    Get-ChildItem $Target -Force | Where-Object { $_.Name -ne 'MoodleWindowsInstaller-latest-502.zip' } | Remove-Item -Recurse -Force
    Copy-Item -Path (Join-Path $Staging '*') -Destination $Target -Recurse -Force
    Write-Host "==> Moodle extracted to $Target"
}

if (Test-Path (Join-Path $Root 'custom')) {
    Write-Host "==> Sync custom/ into moodle/"
    robocopy (Join-Path $Root 'custom') $Target /E /XO /NFL /NDL /NJH /NJS | Out-Null
}

Remove-Item -Recurse -Force $Temp
Write-Host "==> Done. Point OSPanel domain to: $Target"
Write-Host '    Install URL: http://YOUR-DOMAIN/admin/install.php'
