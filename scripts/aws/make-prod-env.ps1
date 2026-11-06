[CmdletBinding()]
param(
    # Tên miền trỏ về máy chủ (xem docs/learning/defense/08-devops-aws.md), ví dụ 13-250-1-2.sslip.io
    [string]$SiteAddress = "CHUA-DAT.sslip.io",
    # Nơi đẩy bản sao lưu; để trống nếu chưa dùng S3
    [string]$S3Uri = "s3://smart-erp-backup/smart-erp",
    # File đầu ra. Tên .env.* đã nằm trong .gitignore nên không bị commit nhầm
    [string]$OutFile = ".env.production",
    [switch]$Force,
    # Chế độ HA: sinh HAI file (.env.production-a và .env.production-b) dùng CHUNG một bộ secret, vì hai máy phải có
    # cùng JWT_SECRET, INTERNAL_SERVICE_KEY, APP_KEY, mật khẩu DB... Secret khác nhau giữa hai máy là lỗi nghiêm trọng.
    [switch]$Ha,
    # Endpoint RDS (có sau khi tạo RDS). Chưa có thì để trống rồi sửa dòng MYSQL_HOST trong file.
    [string]$RdsEndpoint = "",
    # IP private của EC2-A (máy B cần để gọi RabbitMQ). Chưa có thì để trống rồi sửa dòng RABBITMQ_HOST trong file B.
    [string]$PrivateIpA = ""
)

# Sinh file .env cho máy chủ production với MỌI secret mới hoàn toàn, ngẫu nhiên (RNG mật mã).
# Không bao giờ dùng lại secret của máy dev. Không in giá trị secret ra màn hình.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$path = if ([IO.Path]::IsPathRooted($OutFile)) { $OutFile } else { Join-Path $root $OutFile }
if (-not $Ha -and (Test-Path $path) -and -not $Force) { throw "$path đã tồn tại. Thêm -Force để ghi đè (sẽ đổi toàn bộ secret)." }

$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
function New-Bytes([int]$n) { $b = New-Object byte[] $n; $rng.GetBytes($b); return $b }
function New-Hex([int]$n) { return (New-Bytes $n | ForEach-Object { $_.ToString("x2") }) -join "" }
function New-AppKey { return "base64:" + [Convert]::ToBase64String((New-Bytes 32)) }

$lines = @(
    "# Môi trường PRODUCTION trên AWS. Sinh bằng scripts/aws/make-prod-env.ps1, mọi secret là ngẫu nhiên và MỚI.",
    "# Không commit, không gửi qua chat. Sau khi dán lên máy chủ, xóa bản trên máy cá nhân.",
    "",
    "# Chọn đúng bộ file compose trên máy chủ (dấu : phân cách trên Linux)",
    "COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml:docker-compose.aws.yml",
    "# Tên miền trỏ về máy chủ; Caddy tự xin chứng chỉ HTTPS cho tên này",
    "SITE_ADDRESS=$SiteAddress",
    "# Phiên bản image: latest hoặc sha-<7 ký tự> để quay lui đúng một commit",
    "IMAGE_TAG=latest",
    "# Bật giám sát (Grafana tại /grafana/, Prometheus, Loki, Alloy). Bỏ dòng này nếu cần tiết kiệm RAM (~330 MiB)",
    "COMPOSE_PROFILES=monitoring",
    "",
    "# ---- MySQL (root và erp_user chỉ được tạo ở lần khởi động đầu của volume mysql_data) ----",
    "MYSQL_ROOT_PASSWORD=$(New-Hex 24)",
    "MYSQL_PASSWORD=$(New-Hex 24)",
    "IDENTITY_DB_PASSWORD=$(New-Hex 24)",
    "CATALOG_DB_PASSWORD=$(New-Hex 24)",
    "PURCHASE_DB_PASSWORD=$(New-Hex 24)",
    "INVENTORY_DB_PASSWORD=$(New-Hex 24)",
    "SALES_DB_PASSWORD=$(New-Hex 24)",
    "EOFFICE_DB_PASSWORD=$(New-Hex 24)",
    "FINANCE_DB_PASSWORD=$(New-Hex 24)",
    "ANALYTICS_DB_PASSWORD=$(New-Hex 24)",
    "BACKUP_DB_PASSWORD=$(New-Hex 24)",
    "",
    "# ---- RabbitMQ (chỉ được tạo ở lần khởi động đầu của volume rabbitmq_data) ----",
    "RABBITMQ_USER=erp_rabbit",
    "RABBITMQ_PASSWORD=$(New-Hex 24)",
    "",
    "# ---- Bảo mật giữa các service ----",
    "JWT_SECRET=$(New-Hex 32)",
    "INTERNAL_SERVICE_KEY=$(New-Hex 32)",
    "",
    "# ---- Laravel APP_KEY ----",
    "IDENTITY_APP_KEY=$(New-AppKey)",
    "CATALOG_APP_KEY=$(New-AppKey)",
    "SALES_APP_KEY=$(New-AppKey)",
    "EOFFICE_APP_KEY=$(New-AppKey)",
    "FINANCE_APP_KEY=$(New-AppKey)",
    "",
    "# ---- Công cụ quản lý và giám sát (chỉ bật khi cần, chỉ nghe 127.0.0.1) ----",
    "MONGO_EXPRESS_USER=admin",
    "MONGO_EXPRESS_PASSWORD=$(New-Hex 24)",
    "GRAFANA_ADMIN_PASSWORD=$(New-Hex 24)",
    "",
    "# ---- Sao lưu ----",
    "BACKUP_S3_URI=$S3Uri",
    "BACKUP_RETENTION_DAYS=7",
    "AWS_DEFAULT_REGION=ap-southeast-1",
    "",
    "# ---- AI (tùy chọn, điền khóa Groq của bạn nếu muốn dùng trợ lý AI) ----",
    "GROQ_API_KEY=",
    "AI_WAREHOUSE_SYNC_ENABLED=false"
)

if ($Ha) {
    # RDS giới hạn mật khẩu quản trị tối đa 41 ký tự (hex 24 byte = 48 ký tự là quá dài): dùng 20 byte = 40 ký tự hex
    $rdsAdminPassword = New-Hex 20
    $common = $lines | ForEach-Object {
        if ($_ -like "SITE_ADDRESS=*") { "SITE_ADDRESS=" + $(if ($SiteAddress -like "CHUA-DAT*") { "erp.transportbooking.xyz" } else { $SiteAddress }) }
        elseif ($_ -like "MYSQL_ROOT_PASSWORD=*") { "MYSQL_ROOT_PASSWORD=$rdsAdminPassword" }
        elseif ($_ -like "COMPOSE_FILE=*") { "COMPOSE_FILE=__COMPOSE__" }
        else { $_ }
    }
    $haBlock = @(
        "",
        "# ---- HA (2 máy sau ALB) ----",
        "HA_ROLE=__ROLE__",
        "# Endpoint RDS MySQL (container mysql không còn chạy ở HA)",
        "MYSQL_HOST=$RdsEndpoint",
        "# RDS không có tài khoản root: tài khoản quản trị là admin; mật khẩu của nó chính là MYSQL_ROOT_PASSWORD ở trên",
        "# (nhập đúng giá trị đó vào ô Master password khi tạo RDS)",
        "MYSQL_ROOT_USER=admin",
        "# IP private của EC2-A: máy B gọi RabbitMQ tại đây; máy A dùng chính container rabbitmq cục bộ",
        "RABBITMQ_HOST=__RABBIT__",
        "# Thư mục EFS đã mount trên máy chủ (tệp đính kèm của sales)",
        "SALES_FILES_DIR=/mnt/efs/sales"
    )
    $base = "docker-compose.yml:docker-compose.prod.yml:docker-compose.aws.yml:docker-compose.ha.yml"
    foreach ($role in @("a", "b")) {
        $roleLines = ($common + $haBlock) | ForEach-Object {
            $_.Replace("__COMPOSE__", "${base}:docker-compose.ha-${role}.yml").Replace("__ROLE__", $role).Replace("__RABBIT__", $(if ($role -eq "a") { "rabbitmq" } else { $PrivateIpA }))
        }
        if ($role -eq "b") {
            # Máy B không chạy giám sát, sao lưu, AI key không cần thiết lập lại
            $roleLines = $roleLines | Where-Object { $_ -notlike "COMPOSE_PROFILES=*" -and $_ -notlike "# Bật giám sát*" }
        }
        $target = "$path-$role"
        if ((Test-Path $target) -and -not $Force) { throw "$target đã tồn tại. Thêm -Force để ghi đè (sẽ đổi toàn bộ secret)." }
        [IO.File]::WriteAllText($target, (($roleLines -join "`n") + "`n"), (New-Object System.Text.UTF8Encoding $false))
        Write-Host "Đã tạo $target ($($roleLines.Count) dòng). Hai file dùng chung secret." -ForegroundColor Green
    }
    if (-not $RdsEndpoint) { Write-Host "Nhớ điền MYSQL_HOST (endpoint RDS) vào cả hai file khi RDS đã tạo xong." -ForegroundColor Yellow }
    if (-not $PrivateIpA) { Write-Host "Nhớ điền RABBITMQ_HOST (IP private EC2-A) vào file máy B." -ForegroundColor Yellow }
    return
}

# UTF-8 không BOM, xuống dòng kiểu Linux (file này chạy trên máy chủ Ubuntu)
[IO.File]::WriteAllText($path, (($lines -join "`n") + "`n"), (New-Object System.Text.UTF8Encoding $false))
Write-Host "Đã tạo $path ($($lines.Count) dòng, secret ngẫu nhiên)." -ForegroundColor Green
if ($SiteAddress -like "CHUA-DAT*") { Write-Host "Nhớ sửa SITE_ADDRESS khi đã có IP/tên miền của máy chủ." -ForegroundColor Yellow }
