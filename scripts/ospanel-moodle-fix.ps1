# Fixes for Moodle install on OSPanel (PHP + composer). Run from repo root.
$ErrorActionPreference = 'Stop'
$Root = Split-Path $PSScriptRoot -Parent
$Moodle = Join-Path $Root 'moodle'

Write-Host '==> composer install (may take several minutes)...'
Push-Location $Moodle
composer install --no-dev --classmap-authoritative
Pop-Location

Write-Host '==> Done. Restart Apache in OSPanel, then refresh install.php'
Write-Host '    MySQL: create DB moodle with utf8mb4_unicode_ci in phpMyAdmin'
Write-Host '    Production server should use MySQL 8.4+ (OSPanel local uses 8.2 patch)'
