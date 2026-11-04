[CmdletBinding()]
param()

# Áp tài khoản MySQL riêng cho từng service lên volume mysql_data đã có sẵn.
# Script trong docker-entrypoint-initdb.d chỉ tự chạy khi volume còn trống; chạy lại script này sau khi
# đổi *_DB_PASSWORD trong .env hoặc khi nâng cấp từ bản cũ (mọi service dùng chung erp_user).
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    # Tạo lại container mysql (giữ volume) để nhận biến môi trường mới từ .env
    docker compose up -d --wait mysql
    if ($LASTEXITCODE -ne 0) { throw "MySQL did not become healthy." }

    docker compose exec -T mysql bash /docker-entrypoint-initdb.d/03-create-service-users.sh
    if ($LASTEXITCODE -ne 0) { throw "Creating service users failed." }

    Write-Host "Done. Restart the services to reconnect with the new accounts: docker compose up -d"
}
finally {
    Pop-Location
}
