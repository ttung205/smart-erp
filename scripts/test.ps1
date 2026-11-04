[CmdletBinding()]
param(
    # Một hoặc nhiều service (vd. sales hoặc chat,inventory), để trống để chạy tất cả
    [string[]]$Service
)

# Chạy test của từng service trong container (stage `test` của Dockerfile), không cần cài PHP/.NET/Node trên máy.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root

$all = @("identity", "catalog", "sales", "eoffice", "finance", "purchase", "inventory", "chat", "notification", "ai", "frontend")
# `powershell -File test.ps1 -Service a,b` truyền "a,b" thành một chuỗi nên tách theo dấu phẩy
$Service = @($Service | ForEach-Object { $_ -split "," } | Where-Object { $_ })
if (-not $Service) { $Service = $all }
$unknown = $Service | Where-Object { $all -notcontains $_ }
if ($unknown) { throw "Service không hợp lệ: $($unknown -join ', '). Chọn trong: $($all -join ', ')" }
$compose = @("compose", "-f", "docker-compose.yml", "-f", "docker-compose.test.yml")
$results = [ordered]@{}

try {
    # Database cho test (mysql, mongo); các service ứng dụng không cần chạy
    & docker @compose up -d --wait mysql mongo
    if ($LASTEXITCODE -ne 0) { throw "mysql/mongo không healthy." }

    foreach ($name in $Service) {
        Write-Host "`n=== Test $name ===" -ForegroundColor Cyan
        # --build: luôn test đúng mã nguồn hiện tại (layer cache giúp lần sau nhanh)
        & docker @compose run --rm --build "$name-test"
        $results[$name] = $LASTEXITCODE
    }
}
finally {
    Pop-Location
}

Write-Host "`n=== Kết quả ===" -ForegroundColor Cyan
foreach ($name in $results.Keys) {
    if ($results[$name] -eq 0) { Write-Host ("  PASS  {0}" -f $name) -ForegroundColor Green }
    else { Write-Host ("  FAIL  {0} (exit {1})" -f $name, $results[$name]) -ForegroundColor Red }
}
if ($results.Values | Where-Object { $_ -ne 0 }) { exit 1 }
