@echo off
chcp 65001 > nul
echo ===================================================================
echo   SMART DISTRIBUTED ERP — ONE-CLICK DEVELOPER ONBOARDING SETUP
echo ===================================================================
echo.

cd /d %~dp0

echo [1/3] Cài đặt Identity Service (Port 8001)...
cd services\identity-service
if not exist .env copy .env.example .env > nul
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [2/3] Cài đặt Catalog Service (Port 8002)...
cd services\catalog-service
if not exist .env copy .env.example .env > nul
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [3/3] Cài đặt Frontend Vue 3 + PrimeVue...
cd frontend
call npm install
cd ..

echo.
echo ===================================================================
echo   SETUP HOÀN TẤT!
echo   Để khởi động toàn bộ hệ thống, bạn chỉ cần chạy:
echo     start_dev.bat   (hoặc PowerShell: .\start_dev.ps1)
echo ===================================================================
pause