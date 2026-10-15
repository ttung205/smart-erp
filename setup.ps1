Write-Host "===================================================================" -ForegroundColor Cyan
Write-Host "  SMART DISTRIBUTED ERP - ONE-CLICK DEVELOPER ONBOARDING SETUP" -ForegroundColor Cyan
Write-Host "===================================================================" -ForegroundColor Cyan

$root = Split-Path -Parent $MyInvocation.MyCommand.Path

# 1. Identity Service
Write-Host "`n[1/9] Cai dat Identity Service (:8001)..." -ForegroundColor Yellow
Set-Location "$root\services\identity-service"
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
composer install --no-interaction
php artisan key:generate
php artisan migrate:fresh --seed

# 2. Catalog Service
Write-Host "`n[2/9] Cai dat Catalog Service (:8002)..." -ForegroundColor Yellow
Set-Location "$root\services\catalog-service"
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
composer install --no-interaction
php artisan key:generate
php artisan migrate:fresh --seed

# 3. Purchase Service
Write-Host "`n[3/9] Cai dat Purchase Service (C# .NET 10 :8003)..." -ForegroundColor Yellow
Set-Location "$root\services\purchase-service\src\Purchase.Api"
dotnet build

# 4. Inventory Service
Write-Host "`n[4/9] Cai dat Inventory Service (.NET Core :8004)..." -ForegroundColor Yellow
Set-Location "$root\services\inventory-service\src\Inventory.Api"
dotnet build

# 5. Sales Service
Write-Host "`n[5/9] Cai dat Sales Service (:8005)..." -ForegroundColor Yellow
Set-Location "$root\services\sales-service"
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
composer install --no-interaction
php artisan key:generate
php artisan migrate:fresh --seed

# 6. Notification Service
Write-Host "`n[6/9] Cai dat Notification Service (Node.js :8006)..." -ForegroundColor Yellow
Set-Location "$root\services\notification-service"
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
npm install

# 7. Finance Service
Write-Host "`n[7/9] Cai dat Finance Service (:8008)..." -ForegroundColor Yellow
Set-Location "$root\services\finance-service"
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
if (-not (Test-Path "database\database.sqlite")) { New-Item -ItemType File "database\database.sqlite" | Out-Null }
composer install --no-interaction
php artisan key:generate
php artisan migrate:fresh --seed

# 8. EOffice Service
Write-Host "`n[8/9] Cai dat EOffice Service (:8007)..." -ForegroundColor Yellow
Set-Location "$root\services\eoffice-service"
if (-not (Test-Path ".env")) { Copy-Item ".env.example" ".env" }
if (-not (Test-Path "database\database.sqlite")) { New-Item -ItemType File "database\database.sqlite" | Out-Null }
composer install --no-interaction
php artisan key:generate
php artisan migrate:fresh --seed

# 9. Frontend
Write-Host "`n[9/9] Cai dat Frontend Vue 3 + PrimeVue..." -ForegroundColor Yellow
Set-Location "$root\frontend"
npm install

Set-Location $root
Write-Host "`n===================================================================" -ForegroundColor Green
Write-Host "  SETUP HOAN TAT! DE KHOI DONG HE THONG, GO LENH:" -ForegroundColor Green
Write-Host "    .\start_dev.ps1   (hoac double-click start_dev.bat)" -ForegroundColor White
Write-Host "===================================================================" -ForegroundColor Green
