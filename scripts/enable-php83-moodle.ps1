# Enable PHP extensions required by Moodle 5.2 in OSPanel PHP-8.3
$phpIni = 'C:\OSPanel\modules\PHP-8.3\PHP\php.ini'
$template = 'C:\OSPanel\modules\PHP-8.3\ospanel_data\default\templates\php.ini'

function Update-Ini($path) {
    $c = Get-Content $path -Raw
    $c = $c -replace ';zend\.exception_ignore_args\s*=\s*Off', 'zend.exception_ignore_args      = 1'
    $c = $c -replace ';extension\s*=\s*sodium', 'extension                       = sodium'
    $c = $c -replace ';zend_extension\s*=\s*opcache', 'zend_extension                  = "C:/OSPanel/modules/PHP-8.3/PHP/ext/php_opcache.dll"'
    $c = $c -replace ';opcache\.enable\s*=\s*On', 'opcache.enable                  = 1'
    Set-Content $path $c -NoNewline
}

Update-Ini $template
Update-Ini $phpIni

Copy-Item 'C:\OSPanel\modules\PHP-8.3\PHP\libsodium.dll' 'C:\OSPanel\modules\PHP-8.3\Apache\bin\' -Force

Write-Host 'Done. In OSPanel: restart PHP-8.3 (Apache), then open http://moodle.local/check-php.php'
Write-Host 'sodium=yes, opcache=yes, zend.exception_ignore_args=1 expected'
