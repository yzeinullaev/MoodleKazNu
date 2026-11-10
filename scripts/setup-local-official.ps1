# Install Moodle 5.02 with correct layout (moodle/public + moodle/lib/components.json).
# Usage: .\scripts\setup-local-official.ps1

$ErrorActionPreference = 'Stop'
$Root = Split-Path $PSScriptRoot -Parent
$MoodleRoot = Join-Path $Root 'moodle'
$Temp = Join-Path $env:TEMP "moodle-official-$(Get-Random)"
$CloneDir = Join-Path $Temp 'moodle'

Write-Host '==> Clone Moodle MOODLE_502_STABLE from GitHub (shallow)...'
New-Item -ItemType Directory -Path $Temp -Force | Out-Null
git clone --depth 1 --single-branch --branch MOODLE_502_STABLE https://github.com/moodle/moodle.git $CloneDir
if ($LASTEXITCODE -ne 0) { Write-Error 'git clone failed' }

$Source = $CloneDir
if (-not (Test-Path (Join-Path $Source 'public\index.php'))) {
    Write-Error 'Unexpected layout: public/index.php not found'
}

if (Test-Path $MoodleRoot) {
    $Backup = Join-Path $Root ("moodle_backup_" + (Get-Date -Format 'yyyyMMdd_HHmmss'))
    Write-Host "==> Backup old moodle to $Backup"
    Rename-Item $MoodleRoot $Backup
}

Write-Host "==> Install Moodle to $MoodleRoot"
Move-Item $Source $MoodleRoot
Remove-Item -Recurse -Force $Temp -ErrorAction SilentlyContinue

if (Test-Path (Join-Path $Root 'custom')) {
    Write-Host '==> Sync custom/ into moodle/public/local (if needed)'
    $local = Join-Path $MoodleRoot 'public\local'
    if (-not (Test-Path $local)) { New-Item -ItemType Directory -Path $local -Force | Out-Null }
    robocopy (Join-Path $Root 'custom') $local /E /XO /NFL /NDL /NJH /NJS | Out-Null
}

Write-Host '==> Done'
Write-Host '    OSPanel public_dir must be: {base_dir}\moodle\public'
Write-Host '    Install URL: http://moodle.local/install.php'
