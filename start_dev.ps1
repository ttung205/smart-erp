Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   KHOI DONG SMART DISTRIBUTED ERP - DEV CONSOLE" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not $root) { $root = $PSScriptRoot }
if (-not $root) { $root = (Get-Location).Path }

function Test-PortInUse([int]$Port) {
    return $null -ne (Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1)
}

function Start-DevService([string]$Name, [int]$Port, [string]$WorkingDirectory, [string]$Command) {
    if (Test-PortInUse $Port) {
        Write-Host " [OK] $Name da chay tai cong $Port - bo qua khoi dong trung." -ForegroundColor DarkYellow
        return
    }

    $serviceCommand = "Set-Location -LiteralPath '$WorkingDirectory'; Write-Host 'Starting $Name :$Port' -ForegroundColor Green; $Command"
    Start-Process powershell -ArgumentList '-NoProfile', '-NoExit', '-Command', $serviceCommand
}

Start-DevService 'Identity Service' 8001 "$root\services\identity-service" 'php artisan serve --host=127.0.0.1 --port=8001'
Start-DevService 'Catalog Service' 8002 "$root\services\catalog-service" 'php artisan serve --host=127.0.0.1 --port=8002'
Start-DevService 'Purchase Service (.NET 10)' 8003 "$root\services\purchase-service\src\Purchase.Api" 'dotnet run --urls=http://127.0.0.1:8003'
Start-DevService 'Inventory Service (.NET)' 8004 "$root\services\inventory-service\src\Inventory.Api" 'dotnet run --urls=http://127.0.0.1:8004'
Start-DevService 'Sales Service' 8005 "$root\services\sales-service" 'php artisan serve --host=127.0.0.1 --port=8005'
Start-DevService 'Notification Service (Node.js)' 8006 "$root\services\notification-service" 'npm run dev'
Start-DevService 'Vue 3 PrimeVue Frontend' 5175 "$root\frontend" 'npm run dev -- --host 127.0.0.1'

if (-not (Test-PortInUse 5672)) {
    Write-Host " [WARN] RabbitMQ chua chay. Inventory van khoi dong, nhung se ghi canh bao ket noi lai." -ForegroundColor Yellow
    Write-Host "        Neu can message broker, mo Docker Desktop va chay: docker compose up -d rabbitmq" -ForegroundColor Gray
}

Write-Host "`nDa khoi dong ca 7 services:" -ForegroundColor Green
Write-Host " - Identity Service     : http://127.0.0.1:8001" -ForegroundColor Gray
Write-Host " - Catalog Service      : http://127.0.0.1:8002" -ForegroundColor Gray
Write-Host " - Purchase Service     : http://127.0.0.1:8003 (Swagger: http://127.0.0.1:8003/swagger)" -ForegroundColor Gray
Write-Host " - Inventory Service    : http://127.0.0.1:8004 (Swagger: http://127.0.0.1:8004/swagger)" -ForegroundColor Gray
Write-Host " - Sales Service        : http://127.0.0.1:8005" -ForegroundColor Gray
Write-Host " - Notification Service : http://127.0.0.1:8006" -ForegroundColor Gray
Write-Host " - Frontend Web UI      : http://localhost:5175" -ForegroundColor Cyan
Write-Host " - Mailpit Email UI     : http://localhost:8025" -ForegroundColor Cyan
Write-Host "`nHay mo trinh duyet truy cap: http://localhost:5175" -ForegroundColor Yellow
