[CmdletBinding()]
param(
    # Chạy với secret giả lập (không đọc .env thật) để kiểm tra chính sách của docker-compose.prod.yml
    [switch]$SelfTest
)

# Kiểm tra cấu hình Production trước khi deploy. Không bao giờ in giá trị secret, chỉ in tên biến.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root

$secretNames = @(
    "MYSQL_ROOT_PASSWORD", "MYSQL_PASSWORD", "RABBITMQ_PASSWORD", "JWT_SECRET", "INTERNAL_SERVICE_KEY",
    "IDENTITY_APP_KEY", "CATALOG_APP_KEY", "SALES_APP_KEY", "EOFFICE_APP_KEY", "FINANCE_APP_KEY"
)
$allowedPublishedPorts = @("8000", "5175")
$errors = New-Object System.Collections.Generic.List[string]
$saved = @{}
$emptyEnv = Join-Path ([IO.Path]::GetTempPath()) "smart-erp-empty.env"

function Compose-Json {
    # SelfTest dùng file env rỗng để .env thật (nếu có) không che mất việc thiếu biến
    $envArgs = if ($SelfTest) { @("--env-file", $script:emptyEnv) } else { @() }
    $previous = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try { $json = docker compose @envArgs -f docker-compose.yml -f docker-compose.prod.yml config --format json 2>$null }
    finally { $ErrorActionPreference = $previous }
    if ($LASTEXITCODE -ne 0) { return $null }
    return ($json -join "`n")
}

try {
    if ($SelfTest) {
        New-Item -ItemType File -Force -Path $emptyEnv | Out-Null
        foreach ($name in $secretNames) {
            $saved[$name] = [Environment]::GetEnvironmentVariable($name)
            $value = if ($name.EndsWith("_APP_KEY")) { "base64:" + [Convert]::ToBase64String((1..32 | ForEach-Object { Get-Random -Maximum 256 } | ForEach-Object { [byte]$_ })) } else { -join ((1..40) | ForEach-Object { [char](Get-Random -Minimum 97 -Maximum 123) }) }
            [Environment]::SetEnvironmentVariable($name, $value)
        }
    }

    # 1) Thiếu bất kỳ secret nào thì compose phải từ chối (kiểm tra bằng cách bỏ từng biến)
    if ($SelfTest) {
        foreach ($name in $secretNames) {
            $original = [Environment]::GetEnvironmentVariable($name)
            [Environment]::SetEnvironmentVariable($name, $null)
            if ($null -ne (Compose-Json)) { $errors.Add("Thiếu $name nhưng compose vẫn hợp lệ (đang dùng giá trị mặc định).") }
            [Environment]::SetEnvironmentVariable($name, $original)
        }
    }

    $json = Compose-Json
    if ($null -eq $json) {
        $errors.Add("docker compose config (prod) không hợp lệ - thiếu secret bắt buộc hoặc YAML lỗi. Chạy `docker compose -f docker-compose.yml -f docker-compose.prod.yml config` để xem tên biến thiếu.")
    }
    else {
        $config = $json | ConvertFrom-Json

        # 2) Chỉ gateway/frontend được publish ra host
        foreach ($svc in $config.services.PSObject.Properties) {
            foreach ($p in @($svc.Value.ports)) {
                if ($p -and $allowedPublishedPorts -notcontains [string]$p.published) {
                    $errors.Add("Service '$($svc.Name)' publish port $($p.published) ra host trong Production.")
                }
            }
        }

        # 3) Secret (lấy từ cấu hình đã resolve, gồm cả .env) không được là placeholder/mặc định hoặc quá ngắn
        $known = @("erp_secret", "root_secret", "rabbit_secret", "smart_erp_super_secure_jwt_secret_key_2026", "smart_erp_internal_2026")
        $svc = $config.services
        $resolved = [ordered]@{
            MYSQL_ROOT_PASSWORD  = $svc.mysql.environment.MYSQL_ROOT_PASSWORD
            MYSQL_PASSWORD       = $svc.mysql.environment.MYSQL_PASSWORD
            RABBITMQ_PASSWORD    = $svc.rabbitmq.environment.RABBITMQ_DEFAULT_PASS
            JWT_SECRET           = $svc.'identity-service'.environment.JWT_SECRET
            INTERNAL_SERVICE_KEY = $svc.'identity-service'.environment.INTERNAL_SERVICE_KEY
            IDENTITY_APP_KEY     = $svc.'identity-service'.environment.APP_KEY
            CATALOG_APP_KEY      = $svc.'catalog-service'.environment.APP_KEY
            SALES_APP_KEY        = $svc.'sales-service'.environment.APP_KEY
            EOFFICE_APP_KEY      = $svc.'eoffice-service'.environment.APP_KEY
            FINANCE_APP_KEY      = $svc.'finance-service'.environment.APP_KEY
        }
        foreach ($name in $resolved.Keys) {
            $v = [string]$resolved[$name]
            if ([string]::IsNullOrWhiteSpace($v)) { $errors.Add("$name trống."); continue }
            if ($v -like "change-me*" -or $known -contains $v) { $errors.Add("$name vẫn là giá trị mẫu/mặc định công khai.") }
            $min = if ($name -in @("JWT_SECRET", "INTERNAL_SERVICE_KEY")) { 32 } else { 16 }
            if ($v.Length -lt $min) { $errors.Add("$name ngắn hơn $min ký tự.") }
        }
        $groq = $json
        foreach ($leak in $known) {
            if ($groq.Contains($leak)) { $errors.Add("Cấu hình Production đã resolve vẫn chứa một secret mặc định công khai.") ; break }
        }
    }
}
finally {
    if ($SelfTest) { foreach ($name in $saved.Keys) { [Environment]::SetEnvironmentVariable($name, $saved[$name]) } }
    Pop-Location
}

if ($errors.Count -gt 0) {
    $errors | ForEach-Object { Write-Error $_ -ErrorAction Continue }
    exit 1
}
Write-Host "Production config OK$(if ($SelfTest) { ' (self-test)' })."
