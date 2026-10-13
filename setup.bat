@echo off
chcp 65001 > nul
echo ===================================================================
echo   SMART DISTRIBUTED ERP — ONE-CLICK DEVELOPER ONBOARDING SETUP
echo ===================================================================
echo.

cd /d %~dp0

echo [1/7] Cài đặt Identity Service (Port 8001)...
cd services\identity-service
if not exist .env copy .env.example .env > nul
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [2/7] Cài đặt Catalog Service (Port 8002)...
cd services\catalog-service
if not exist .env copy .env.example .env > nul
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [3/7] Cài đặt Purchase Service (C# .NET 10 - Port 8003)...
cd services\purchase-service\src\Purchase.Api
call dotnet build
cd ..\..\..

echo.
echo [4/7] Cài đặt Inventory Service (C# .NET 10 - Port 8004)...
cd services\inventory-service\src\Inventory.Api
call dotnet build
cd ..\..\..

echo.
echo [5/7] Cài đặt Sales Service (Port 8005)...
cd services\sales-service
if not exist .env copy .env.example .env > nul
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [6/7] Cài đặt Notification Service (Node.js - Port 8006)...
cd services\notification-service
if not exist .env copy .env.example .env > nul
call npm install
cd ..\..

echo.
echo [7/7] Cài đặt Frontend Vue 3 + PrimeVue...
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