Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   KHOI DONG SMART DISTRIBUTED ERP - DEV CONSOLE" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not $root) { $root = $PSScriptRoot }
if (-not $root) { $root = (Get-Location).Path }

Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\services\identity-service'; Write-Host 'Starting Identity Service :8001' -ForegroundColor Green; php artisan serve --port=8001"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\services\catalog-service'; Write-Host 'Starting Catalog Service :8002' -ForegroundColor Green; php artisan serve --port=8002"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\services\purchase-service\src\Purchase.Api'; Write-Host 'Starting Purchase Service (.NET 10) :8003' -ForegroundColor Green; dotnet run --urls=http://localhost:8003"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\services\inventory-service\src\Inventory.Api'; Write-Host 'Starting Inventory Service (.NET) :8004' -ForegroundColor Green; dotnet run --urls=http://localhost:8004"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\services\sales-service'; Write-Host 'Starting Sales Service :8005' -ForegroundColor Green; php artisan serve --port=8005"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\services\notification-service'; Write-Host 'Starting Notification Service (Node.js) :8006' -ForegroundColor Green; npm run dev"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd '$root\frontend'; Write-Host 'Starting Vue 3 PrimeVue Frontend :5173' -ForegroundColor Yellow; npm run dev"

Write-Host "`nDa khoi dong ca 7 services:" -ForegroundColor Green
Write-Host " - Identity Service     : http://127.0.0.1:8001" -ForegroundColor Gray
Write-Host " - Catalog Service      : http://127.0.0.1:8002" -ForegroundColor Gray
Write-Host " - Purchase Service     : http://127.0.0.1:8003 (Swagger: http://127.0.0.1:8003/swagger)" -ForegroundColor Gray
Write-Host " - Inventory Service    : http://127.0.0.1:8004 (Swagger: http://127.0.0.1:8004/swagger)" -ForegroundColor Gray
Write-Host " - Sales Service        : http://127.0.0.1:8005" -ForegroundColor Gray
Write-Host " - Notification Service : http://127.0.0.1:8006" -ForegroundColor Gray
Write-Host " - Frontend Web UI      : http://localhost:5173" -ForegroundColor Cyan
Write-Host " - Mailpit Email UI     : http://localhost:8025" -ForegroundColor Cyan
Write-Host "`nHay mo trinh duyet truy cap: http://localhost:5173" -ForegroundColor Yellow