[CmdletBinding()]
param(
    # Tên miền trỏ về máy chủ (xem docs/learning/defense/08-devops-aws.md), ví dụ 13-250-1-2.sslip.io
    [string]$SiteAddress = "CHUA-DAT.sslip.io",
    # Nơi đẩy bản sao lưu; để trống nếu chưa dùng S3
    [string]$S3Uri = "s3://smart-erp-backup/smart-erp",
    # File đầu ra. Tên .env.* đã nằm trong .gitignore nên không bị commit nhầm
    [string]$OutFile = ".env.production",
    [switch]$Force
)

# Sinh file .env cho máy chủ production với MỌI secret mới hoàn toàn, ngẫu nhiên (RNG mật mã).
# Không bao giờ dùng lại secret của máy dev. Không in giá trị secret ra màn hình.
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$path = if ([IO.Path]::IsPathRooted($OutFile)) { $OutFile } else { Join-Path $root $OutFile }
if ((Test-Path $path) -and -not $Force) { throw "$path đã tồn tại. Thêm -Force để ghi đè (sẽ đổi toàn bộ secret)." }

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

# UTF-8 không BOM, xuống dòng kiểu Linux (file này chạy trên máy chủ Ubuntu)
[IO.File]::WriteAllText($path, (($lines -join "`n") + "`n"), (New-Object System.Text.UTF8Encoding $false))
Write-Host "Đã tạo $path ($($lines.Count) dòng, secret ngẫu nhiên)." -ForegroundColor Green
if ($SiteAddress -like "CHUA-DAT*") { Write-Host "Nhớ sửa SITE_ADDRESS khi đã có IP/tên miền của máy chủ." -ForegroundColor Yellow }
