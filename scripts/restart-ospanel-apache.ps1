# Restart OSPanel PHP-8.3 Apache (stop + start)
$httpd = 'C:\OSPanel\modules\PHP-8.3\Apache\bin\httpd.exe'
$conf = 'C:\OSPanel\modules\PHP-8.3\Apache\conf\httpd.conf'
Get-Process httpd -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep 2
Start-Process -FilePath $httpd -ArgumentList "-f `"$conf`"" -WindowStyle Hidden
Start-Sleep 2
Write-Host "Apache restarted. Check: http://moodle.local/check-php.php"
