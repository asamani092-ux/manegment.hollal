# تشغيل Hollal Platform — استخدم هذا الملف إذا لم يعمل `php` من PATH
$php = "C:\Users\Admin\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

if (-not (Test-Path $php)) {
    Write-Error "PHP غير موجود. ثبّت PHP 8.3 عبر: winget install PHP.PHP.8.3"
    exit 1
}

Set-Location $PSScriptRoot

Write-Host ""
Write-Host "  Hollal Platform" -ForegroundColor Cyan
Write-Host "  افتح المتصفح: http://127.0.0.1:8000/login" -ForegroundColor Green
Write-Host "  0500000000 / password" -ForegroundColor Yellow
Write-Host ""

& $php artisan serve --host=127.0.0.1 --port=8000
