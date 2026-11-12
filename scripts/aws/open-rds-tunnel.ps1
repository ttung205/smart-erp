<#
.SYNOPSIS
  Mở tunnel SSM từ máy bạn tới RDS (qua máy A) để DBeaver/DBX/simulator kết nối vào 127.0.0.1.

.DESCRIPTION
  RDS nằm trong subnet riêng nên không nối thẳng từ ngoài được. Script này tự tìm id máy A và endpoint RDS
  theo tên, rồi mở phiên SSM Port Forwarding. Giữ cửa sổ này mở trong lúc làm việc, Ctrl+C để đóng tunnel.
  Không chứa mật khẩu: tài khoản admin RDS lấy từ .env trên máy A (MYSQL_ROOT_PASSWORD).

  Điều kiện: đã cài AWS CLI và Session Manager plugin, đã `aws configure` bằng user có quyền
  ssm:StartSession, ec2:DescribeInstances, rds:DescribeDBInstances (không dùng khóa của ses-smtp-user).

.EXAMPLE
  .\scripts\aws\open-rds-tunnel.ps1
  # rồi kết nối: host 127.0.0.1, port 3307, user admin

.EXAMPLE
  .\scripts\aws\open-rds-tunnel.ps1 -LocalPort 3308 -Profile dev-local
#>
param(
    [string]$Region = "ap-southeast-1",
    [string]$InstanceName = "smart-erp-app-a",
    [string]$DbIdentifier = "smart-erp-db",
    [int]$LocalPort = 3307,
    [string]$Profile = ""
)

$ErrorActionPreference = "Stop"

function Fail([string]$message) {
    Write-Host "LỖI: $message" -ForegroundColor Red
    exit 1
}

# Chạy aws, trả về dòng văn bản đầu ra; dừng nếu lệnh lỗi
function Invoke-Aws([string[]]$arguments) {
    $all = @($arguments) + @("--region", $Region)
    if ($Profile) { $all += @("--profile", $Profile) }
    $output = & aws @all
    if ($LASTEXITCODE -ne 0) { Fail "lệnh 'aws $($arguments[0]) $($arguments[1])' thất bại (mã $LASTEXITCODE)." }
    return ($output | Out-String).Trim()
}

if (-not (Get-Command aws -ErrorAction SilentlyContinue)) {
    Fail "chưa cài AWS CLI. Chạy: winget install -e --id Amazon.AWSCLI (rồi mở lại PowerShell)."
}
if (-not (Get-Command session-manager-plugin -ErrorAction SilentlyContinue)) {
    Fail "chưa cài Session Manager plugin. Chạy: winget install -e --id Amazon.SessionManagerPlugin (rồi mở lại PowerShell)."
}
if (Get-NetTCPConnection -LocalPort $LocalPort -State Listen -ErrorAction SilentlyContinue) {
    Fail "cổng $LocalPort trên máy bạn đang được dùng (có thể tunnel khác đang chạy). Đổi bằng -LocalPort."
}

$identity = Invoke-Aws @("sts", "get-caller-identity", "--query", "Arn", "--output", "text")
if ($identity -match "ses-smtp-user") {
    Fail "CLI đang dùng khóa của ses-smtp-user (chỉ có quyền gửi email). Chạy lại 'aws configure' bằng khóa của user dev."
}
Write-Host "Đăng nhập AWS: $identity"

$instanceId = Invoke-Aws @(
    "ec2", "describe-instances",
    "--filters", "Name=tag:Name,Values=$InstanceName", "Name=instance-state-name,Values=running",
    "--query", "Reservations[0].Instances[0].InstanceId", "--output", "text")
if (-not $instanceId -or $instanceId -eq "None") { Fail "không thấy máy '$InstanceName' đang chạy (máy A có đang bật không?)." }

$endpoint = Invoke-Aws @(
    "rds", "describe-db-instances", "--db-instance-identifier", $DbIdentifier,
    "--query", "DBInstances[0].Endpoint.Address", "--output", "text")
if (-not $endpoint -or $endpoint -eq "None") { Fail "không thấy RDS '$DbIdentifier' (hoặc chưa sẵn sàng)." }

Write-Host ""
Write-Host "Máy A : $instanceId ($InstanceName)"
Write-Host "RDS   : $endpoint"
Write-Host ""
Write-Host "Kết nối bằng công cụ DB: host 127.0.0.1, port $LocalPort, user admin (mật khẩu: MYSQL_ROOT_PASSWORD trong .env trên máy A)." -ForegroundColor Green
Write-Host "Giữ cửa sổ này mở. Nhấn Ctrl+C để đóng tunnel."
Write-Host ""

$sessionArgs = @(
    "ssm", "start-session", "--region", $Region, "--target", $instanceId,
    "--document-name", "AWS-StartPortForwardingSessionToRemoteHost",
    "--parameters", "host=$endpoint,portNumber=3306,localPortNumber=$LocalPort")
if ($Profile) { $sessionArgs += @("--profile", $Profile) }
& aws @sessionArgs
