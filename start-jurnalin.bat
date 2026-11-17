@echo off
cd /d "%~dp0"
echo === Menyiapkan Database Jurnalin ===
php artisan migrate:fresh --seed --ansi
if errorlevel 1 goto :fail

echo.
echo === Menjalankan Pengujian Otomatis ===
php artisan test
if errorlevel 1 goto :fail

echo.
echo === Mem-build Asset Frontend (Vite) ===
call npm run build
if errorlevel 1 goto :fail

echo.
echo === Menjalankan Server Lokal Jurnalin di http://127.0.0.1:8000 ===
php artisan serve --host=127.0.0.1 --port=8000
goto :eof

:fail
echo.
echo Terjadi kesalahan saat menyiapkan Jurnalin. Periksa pesan di atas.
pause
exit /b 1
