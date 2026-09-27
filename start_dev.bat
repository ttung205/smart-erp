@echo off
chcp 65001 > nul
echo ========================================================
echo   KHOI DONG SMART DISTRIBUTED ERP - DEV CONSOLE
echo ========================================================

start "Identity Service :8001" cmd /k "cd /d %~dp0services\identity-service && php artisan serve --port=8001"
start "Catalog Service :8002" cmd /k "cd /d %~dp0services\catalog-service && php artisan serve --port=8002"
start "Inventory Service :8004" cmd /k "cd /d %~dp0services\inventory-service\src\Inventory.Api && dotnet run --urls=http://localhost:8004"
start "Vue 3 PrimeVue Frontend :5173" cmd /k "cd /d %~dp0frontend && npm run dev"

echo Da khoi dong ca 4 services thanh cong!
echo  - Identity Service  : http://127.0.0.1:8001
echo  - Catalog Service   : http://127.0.0.1:8002
echo  - Inventory Service : http://127.0.0.1:8004
echo  - Frontend Web UI   : http://localhost:5173
echo.
echo Hay mo trinh duyet truy cap: http://localhost:5173