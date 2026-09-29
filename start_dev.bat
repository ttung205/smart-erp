@echo off
chcp 65001 > nul
setlocal enabledelayedexpansion

echo ========================================================
echo   KHOI DONG SMART DISTRIBUTED ERP - DEV CONSOLE
echo ========================================================

set "ROOT=%~dp0"

where wt >nul 2>nul
if errorlevel 1 goto fallback_cmd

echo Phat hien Windows Terminal (wt.exe) : Dang mo 1 cua so voi 5 tabs...
start wt -w 0 --title "Identity :8001" -d "%ROOT%services\identity-service" cmd /k "php artisan serve --port=8001" ; new-tab --title "Catalog :8002" -d "%ROOT%services\catalog-service" cmd /k "php artisan serve --port=8002" ; new-tab --title "Sales :8003" -d "%ROOT%services\sales-service" cmd /k "php artisan serve --port=8003" ; new-tab --title "Inventory :8004" -d "%ROOT%services\inventory-service\src\Inventory.Api" cmd /k "dotnet run --urls=http://localhost:8004" ; new-tab --title "Frontend :5173/5174" -d "%ROOT%frontend" cmd /k "npm run dev"
goto done

:fallback_cmd
echo Khong tim thay Windows Terminal : Mo 5 cua so CMD rieng biet...
start "Identity Service :8001" cmd /k "cd /d %ROOT%services\identity-service && php artisan serve --port=8001"
start "Catalog Service :8002" cmd /k "cd /d %ROOT%services\catalog-service && php artisan serve --port=8002"
start "Sales Service :8003" cmd /k "cd /d %ROOT%services\sales-service && php artisan serve --port=8003"
start "Inventory Service :8004" cmd /k "cd /d %ROOT%services\inventory-service\src\Inventory.Api && dotnet run --urls=http://localhost:8004"
start "Vue 3 PrimeVue Frontend :5173/5174" cmd /k "cd /d %ROOT%frontend && npm run dev"

:done
echo.
echo ========================================================
echo   TAT CA 5 SERVICES DA DUOC KHOI DONG!
echo ========================================================
echo  [Tab 1] Identity Service  : http://127.0.0.1:8001
echo  [Tab 2] Catalog Service   : http://127.0.0.1:8002
echo  [Tab 3] Sales Service     : http://127.0.0.1:8003
echo  [Tab 4] Inventory Service : http://127.0.0.1:8004
echo  [Tab 5] Frontend Web UI   : http://localhost:5173 (hoac :5174 neu 5173 bi chiem)
echo.
echo Truy cap ung dung tai: http://localhost:5173 hoac http://localhost:5174
echo ========================================================
echo.
echo Nhan phim bat ky de thoat cua so khoi dong nay...
pause >nul