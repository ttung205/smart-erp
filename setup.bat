@echo off
chcp 65001 > nul
echo ===================================================================
echo   SMART DISTRIBUTED ERP — ONE-CLICK DEVELOPER ONBOARDING SETUP
echo ===================================================================
echo.

cd /d %~dp0

echo [1/10] Cài đặt Identity Service (Port 8001)...
cd services\identity-service
if not exist .env copy .env.example .env > nul
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [2/10] Cài đặt Catalog Service (Port 8002)...
cd services\catalog-service
if not exist .env copy .env.example .env > nul
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [3/10] Cài đặt Purchase Service (C# .NET 10 - Port 8003)...
cd services\purchase-service\src\Purchase.Api
call dotnet build
cd ..\..\..

echo.
echo [4/10] Cài đặt Inventory Service (C# .NET 10 - Port 8004)...
cd services\inventory-service\src\Inventory.Api
call dotnet build
cd ..\..\..

echo.
echo [5/10] Cài đặt Sales Service (Port 8005)...
cd services\sales-service
if not exist .env copy .env.example .env > nul
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [6/10] Cài đặt Notification Service (Node.js - Port 8006)...
cd services\notification-service
if not exist .env copy .env.example .env > nul
call npm install
cd ..\..

echo.
echo [7/10] Cài đặt EOffice Service (Port 8007)...
cd services\eoffice-service
if not exist .env copy .env.example .env > nul
if not exist database\database.sqlite type nul > database\database.sqlite
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [8/10] Cài đặt Finance Service (Port 8008)...
cd services\finance-service
if not exist .env copy .env.example .env > nul
if not exist database\database.sqlite type nul > database\database.sqlite
call composer install --no-interaction
call php artisan key:generate
call php artisan migrate:fresh --seed
cd ..\..

echo.
echo [9/10] Cài đặt Chat Service (C# .NET 10 - Port 8009)...
cd services\chat-service\src\Chat.Api
call dotnet build
cd ..\..\..

echo.
echo [10/10] Cài đặt Frontend Vue 3 + PrimeVue...
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
