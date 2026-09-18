@echo off
set "PATH=C:\tools\nodejs;C:\tools\php;C:\tools\composer;%PATH%"
cd /d "%~dp0"
echo Starting OpenKos Backend on http://localhost:8000...
php artisan serve --port=8000
