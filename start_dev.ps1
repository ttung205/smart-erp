[CmdletBinding()]
param(
    [switch]$Preview,
    [int]$RunPort
)

Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "   KHOI DONG SMART DISTRIBUTED ERP - DEV CONSOLE" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan

$projectRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
if (-not $projectRoot) { $projectRoot = $PSScriptRoot }
if (-not $projectRoot) { $projectRoot = (Get-Location).Path }

function Test-PortInUse([int]$Port) {
    return $null -ne (Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue | Select-Object -First 1)
}

$services = @(
    [pscustomobject]@{ Name = 'Identity Service'; Port = 8001; Directory = "$projectRoot\services\identity-service"; Command = { php artisan serve --host=127.0.0.1 --port=8001 } },
    [pscustomobject]@{ Name = 'Catalog Service'; Port = 8002; Directory = "$projectRoot\services\catalog-service"; Command = { php artisan serve --host=127.0.0.1 --port=8002 } },
    [pscustomobject]@{ Name = 'Purchase Service (.NET 10)'; Port = 8003; Directory = "$projectRoot\services\purchase-service\src\Purchase.Api"; Command = { dotnet run --urls=http://127.0.0.1:8003 } },
    [pscustomobject]@{ Name = 'Inventory Service (.NET)'; Port = 8004; Directory = "$projectRoot\services\inventory-service\src\Inventory.Api"; Command = { dotnet run --urls=http://127.0.0.1:8004 } },
    [pscustomobject]@{ Name = 'Sales Service'; Port = 8005; Directory = "$projectRoot\services\sales-service"; Command = { php artisan serve --host=127.0.0.1 --port=8005 } },
    [pscustomobject]@{ Name = 'Notification Service (Node.js)'; Port = 8006; Directory = "$projectRoot\services\notification-service"; Command = { npm run dev } },
    [pscustomobject]@{ Name = 'EOffice Service'; Port = 8007; Directory = "$projectRoot\services\eoffice-service"; Command = { php artisan serve --host=127.0.0.1 --port=8007 } },
    [pscustomobject]@{ Name = 'Finance Service'; Port = 8008; Directory = "$projectRoot\services\finance-service"; Command = { php artisan serve --host=127.0.0.1 --port=8008 } },
    [pscustomobject]@{ Name = 'Vue 3 PrimeVue Frontend'; Port = 5175; Directory = "$projectRoot\frontend"; Command = { npm run dev -- --host 127.0.0.1 } }
)

if ($RunPort) {
    $service = $services | Where-Object Port -eq $RunPort | Select-Object -First 1
    if (-not $service) { throw "Khong co service nao dung cong $RunPort." }

    Set-Location -LiteralPath $service.Directory
    Write-Host "Starting $($service.Name) :$($service.Port)" -ForegroundColor Green
    & $service.Command
    return
}

$pendingServices = @()
foreach ($service in $services) {
    if (Test-PortInUse $service.Port) {
        Write-Host " [OK] $($service.Name) da chay tai cong $($service.Port) - bo qua khoi dong trung." -ForegroundColor DarkYellow
        continue
    }

    $pendingServices += $service
}

if ($pendingServices.Count -gt 0) {
    $windowsTerminal = Get-Command wt.exe -ErrorAction SilentlyContinue
    if (-not $windowsTerminal) {
        throw 'Khong tim thay Windows Terminal (wt.exe). Hay cai Windows Terminal de khoi dong cac service theo nhieu tab.'
    }

    $terminalArguments = @('-w', 'SmartERP')
    for ($index = 0; $index -lt $pendingServices.Count; $index++) {
        $service = $pendingServices[$index]
        $terminalArguments += @(
            'new-tab',
            '--title', "$($service.Name) :$($service.Port)",
            '--suppressApplicationTitle',
            '--startingDirectory', $projectRoot,
            'powershell.exe', '-NoLogo', '-NoProfile', '-NoExit', '-ExecutionPolicy', 'Bypass', '-File', 'start_dev.ps1', '-RunPort', "$($service.Port)"
        )

        if ($index -lt $pendingServices.Count - 1) {
            $terminalArguments += ';'
        }
    }

    if ($Preview) {
        Write-Host "`n[PREVIEW] Windows Terminal se mo $($pendingServices.Count) tab trong mot cua so SmartERP:" -ForegroundColor Magenta
        foreach ($service in $pendingServices) {
            Write-Host " - $($service.Name) :$($service.Port)" -ForegroundColor Gray
        }
    } else {
        & $windowsTerminal.Source @terminalArguments
        if ($LASTEXITCODE -ne 0) {
            throw "Windows Terminal khoi dong that bai voi ma loi $LASTEXITCODE."
        }
    }
} else {
    Write-Host "`nTat ca service da chay, khong can mo them tab." -ForegroundColor Green
}

if (-not (Test-PortInUse 5672)) {
    Write-Host " [WARN] RabbitMQ chua chay. Inventory van khoi dong, nhung se ghi canh bao ket noi lai." -ForegroundColor Yellow
    Write-Host "        Neu can message broker, mo Docker Desktop va chay: docker compose up -d rabbitmq" -ForegroundColor Gray
}

Write-Host "`nDanh sach service:" -ForegroundColor Green
Write-Host " - Identity Service     : http://127.0.0.1:8001" -ForegroundColor Gray
Write-Host " - Catalog Service      : http://127.0.0.1:8002" -ForegroundColor Gray
Write-Host " - Purchase Service     : http://127.0.0.1:8003 (Swagger: http://127.0.0.1:8003/swagger)" -ForegroundColor Gray
Write-Host " - Inventory Service    : http://127.0.0.1:8004 (Swagger: http://127.0.0.1:8004/swagger)" -ForegroundColor Gray
Write-Host " - Sales Service        : http://127.0.0.1:8005" -ForegroundColor Gray
Write-Host " - Notification Service : http://127.0.0.1:8006" -ForegroundColor Gray
Write-Host " - EOffice Service      : http://127.0.0.1:8007" -ForegroundColor Gray
Write-Host " - Finance Service      : http://127.0.0.1:8008" -ForegroundColor Gray
Write-Host " - Frontend Web UI      : http://localhost:5175" -ForegroundColor Cyan
Write-Host " - Mailpit Email UI     : http://localhost:8025" -ForegroundColor Cyan
Write-Host "`nHay mo trinh duyet truy cap: http://localhost:5175" -ForegroundColor Yellow
