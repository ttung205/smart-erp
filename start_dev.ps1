Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   KHOI DONG SMART DISTRIBUTED ERP - DEV CONSOLE" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan

Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd 'E:\smart-erp\services\identity-service'; Write-Host 'Starting Identity Service :8001' -ForegroundColor Green; php artisan serve --port=8001"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd 'E:\smart-erp\services\catalog-service'; Write-Host 'Starting Catalog Service :8002' -ForegroundColor Green; php artisan serve --port=8002"
Start-Process powershell -ArgumentList "-NoExit", "-Command", "cd 'E:\smart-erp\frontend'; Write-Host 'Starting Vue 3 PrimeVue Frontend :5173' -ForegroundColor Yellow; npm run dev"

Write-Host "`nDa khoi dong ca 3 services:" -ForegroundColor Green
Write-Host " - Identity Service : http://127.0.0.1:8001" -ForegroundColor Gray
Write-Host " - Catalog Service  : http://127.0.0.1:8002" -ForegroundColor Gray
Write-Host " - Frontend Web UI  : http://localhost:5173" -ForegroundColor Cyan
Write-Host "`nHay mo trinh duyet truy cap: http://localhost:5173" -ForegroundColor Yellow