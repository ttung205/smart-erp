[CmdletBinding()]
param(
    # Chạy với secret giả lập (không đọc .env thật) để kiểm tra chính sách của docker-compose.prod.yml
    [switch]$SelfTest,
    # Kiểm tra thêm lớp docker-compose.aws.yml (Caddy + image GHCR): khi đó chỉ cổng 80 và 443 được publish
    [switch]$Aws,
    # Kiểm tra cấu hình HA (2 máy sau ALB, RDS): a = máy A (RabbitMQ, Mongo, relay...), b = máy B (chỉ tầng ứng dụng).
    # Bao gồm lớp -Aws; chỉ cổng 80 được publish (máy A thêm 5672 cho máy B và 3000 cho Grafana sau ALB)
    [ValidateSet("", "a", "b")][string]$Ha = ""
)

# Kiểm tra cấu hình Production trước khi deploy. Không bao giờ in giá trị secret, chỉ in tên biến.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Push-Location $root

$secretNames = @(
    "MYSQL_ROOT_PASSWORD", "MYSQL_PASSWORD", "RABBITMQ_PASSWORD", "JWT_SECRET", "INTERNAL_SERVICE_KEY",
    "IDENTITY_APP_KEY", "CATALOG_APP_KEY", "SALES_APP_KEY", "EOFFICE_APP_KEY", "FINANCE_APP_KEY",
    "IDENTITY_DB_PASSWORD", "CATALOG_DB_PASSWORD", "PURCHASE_DB_PASSWORD", "INVENTORY_DB_PASSWORD",
    "SALES_DB_PASSWORD", "EOFFICE_DB_PASSWORD", "FINANCE_DB_PASSWORD", "ANALYTICS_DB_PASSWORD",
    "GRAFANA_ADMIN_PASSWORD", "MONGO_EXPRESS_PASSWORD", "BACKUP_DB_PASSWORD"
)
if ($Ha) { $Aws = [switch]$true }
if ($Aws) { $secretNames += "SITE_ADDRESS" }
$allowedPublishedPorts = if ($Ha -eq "a") { @("80", "5672", "3000") } elseif ($Ha -eq "b") { @("80") } elseif ($Aws) { @("80", "443") } else { @("8000", "5175") }
$composeFiles = @("-f", "docker-compose.yml", "-f", "docker-compose.prod.yml")
if ($Aws) { $composeFiles += @("-f", "docker-compose.aws.yml") }
if ($Ha) { $composeFiles += @("-f", "docker-compose.ha.yml", "-f", "docker-compose.ha-$Ha.yml") }
$errors = New-Object System.Collections.Generic.List[string]
$saved = @{}
$emptyEnv = Join-Path ([IO.Path]::GetTempPath()) "smart-erp-empty.env"

function Compose-Json {
    # SelfTest dùng file env rỗng để .env thật (nếu có) không che mất việc thiếu biến
    $envArgs = if ($SelfTest) { @("--env-file", $script:emptyEnv) } else { @() }
    $previous = $ErrorActionPreference
    $ErrorActionPreference = "Continue"
    try { $json = docker compose @envArgs @composeFiles config --format json 2>$null }
    finally { $ErrorActionPreference = $previous }
    if ($LASTEXITCODE -ne 0) { return $null }
    return ($json -join "`n")
}

try {
    if ($SelfTest) {
        New-Item -ItemType File -Force -Path $emptyEnv | Out-Null
        if ($Ha) {
            foreach ($pair in @(@("MYSQL_HOST", "erp-db.example.ap-southeast-1.rds.amazonaws.com"), @("RABBITMQ_HOST", "10.0.0.10"))) {
                $saved[$pair[0]] = [Environment]::GetEnvironmentVariable($pair[0])
                [Environment]::SetEnvironmentVariable($pair[0], $pair[1])
            }
        }
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
        $known = @("erp_secret", "root_secret", "rabbit_secret", "smart_erp_super_secure_jwt_secret_key_2026", "smart_erp_internal_2026",
            "identity_db_secret", "catalog_db_secret", "purchase_db_secret", "inventory_db_secret",
            "sales_db_secret", "eoffice_db_secret", "finance_db_secret", "analytics_db_secret")
        $svc = $config.services
        $resolved = [ordered]@{
            JWT_SECRET           = $svc.'identity-service'.environment.JWT_SECRET
            INTERNAL_SERVICE_KEY = $svc.'identity-service'.environment.INTERNAL_SERVICE_KEY
            IDENTITY_APP_KEY     = $svc.'identity-service'.environment.APP_KEY
            CATALOG_APP_KEY      = $svc.'catalog-service'.environment.APP_KEY
            SALES_APP_KEY        = $svc.'sales-service'.environment.APP_KEY
            EOFFICE_APP_KEY      = $svc.'eoffice-service'.environment.APP_KEY
            FINANCE_APP_KEY      = $svc.'finance-service'.environment.APP_KEY
        }
        if ($Ha) {
            # HA: MySQL ở RDS nên container mysql và (ở máy B) rabbitmq không tồn tại trong cấu hình đã resolve
            $resolved["IDENTITY_DB_PASSWORD"] = $svc.'identity-service'.environment.DB_PASSWORD
            $resolved["CATALOG_DB_PASSWORD"] = $svc.'catalog-service'.environment.DB_PASSWORD
            $resolved["SALES_DB_PASSWORD"] = $svc.'sales-service'.environment.DB_PASSWORD
            $resolved["EOFFICE_DB_PASSWORD"] = $svc.'eoffice-service'.environment.DB_PASSWORD
            $resolved["FINANCE_DB_PASSWORD"] = $svc.'finance-service'.environment.DB_PASSWORD
            if ($svc.'identity-service'.environment.DB_HOST -eq "mysql") { $errors.Add("MYSQL_HOST chưa trỏ tới RDS (đang là mysql).") }
            if ($Ha -eq "b") {
                if ($svc.'catalog-service'.environment.RABBITMQ_HOST -eq "rabbitmq") { $errors.Add("RABBITMQ_HOST của máy B chưa trỏ tới IP private của máy A (đang là rabbitmq).") }
                if ($svc.'purchase-service'.environment.Outbox__RelayEnabled -ne "false") { $errors.Add("Máy B phải đặt Outbox__RelayEnabled=false cho purchase-service.") }
                if ($svc.'inventory-service'.environment.Outbox__RelayEnabled -ne "false") { $errors.Add("Máy B phải đặt Outbox__RelayEnabled=false cho inventory-service.") }
                foreach ($only in @("rabbitmq", "mongo", "catalog-relay", "sales-relay", "sales-scheduler", "eoffice-relay", "chat-service", "notification-service")) {
                    if ($svc.PSObject.Properties.Name -contains $only) { $errors.Add("Máy B không được chạy '$only' (chỉ chạy ở máy A).") }
                }
            }
            else {
                foreach ($need in @("rabbitmq", "mongo", "catalog-relay", "sales-relay", "sales-scheduler", "eoffice-relay", "chat-service", "notification-service")) {
                    if (-not ($svc.PSObject.Properties.Name -contains $need)) { $errors.Add("Máy A thiếu '$need'.") }
                }
            }
            if ($svc.PSObject.Properties.Name -contains "mysql") { $errors.Add("HA không được chạy container mysql (DB ở RDS).") }
            if ($svc.PSObject.Properties.Name -contains "caddy") { $errors.Add("HA không được chạy caddy (ALB đã lo HTTPS).") }
            if (-not $svc.'sales-service'.volumes) { $errors.Add("sales-service chưa mount EFS cho tệp đính kèm.") }
        }
        else {
            $resolved["MYSQL_ROOT_PASSWORD"] = $svc.mysql.environment.MYSQL_ROOT_PASSWORD
            $resolved["MYSQL_PASSWORD"] = $svc.mysql.environment.MYSQL_PASSWORD
            $resolved["RABBITMQ_PASSWORD"] = $svc.rabbitmq.environment.RABBITMQ_DEFAULT_PASS
            # Mật khẩu DB từng service đọc từ env của container mysql (03-create-service-users.sh tạo user bằng chính các giá trị này)
            foreach ($name in $secretNames | Where-Object { $_.EndsWith("_DB_PASSWORD") }) {
                $resolved[$name] = $svc.mysql.environment.$name
            }
            if ($svc.mysql.environment.MYSQL_DEV_TOOLS_ACCESS -ne "false") {
                $errors.Add("MYSQL_DEV_TOOLS_ACCESS phải là false ở Production (erp_user không được truy cập dữ liệu thật).")
            }
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
Write-Host "Production config OK$(if ($Ha) { " (HA máy $Ha)" })$(if ($SelfTest) { ' (self-test)' })."
