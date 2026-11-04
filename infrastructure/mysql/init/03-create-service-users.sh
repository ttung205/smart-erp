#!/bin/bash
# Mỗi service một tài khoản MySQL, chỉ có quyền trên schema của chính nó: MySQL tự cưỡng chế ranh giới database-per-service.
# - Tự chạy một lần khi volume MySQL còn trống (docker-entrypoint-initdb.d).
# - Chạy lại được trên volume đã có (đổi mật khẩu, thêm quyền): scripts/apply-db-users.ps1
# Mật khẩu lấy từ biến môi trường của container mysql (docker-compose.yml / .env).
set -euo pipefail

sql_escape() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"
}

# user | biến mật khẩu | schema sở hữu
SERVICE_USERS="
erp_identity|IDENTITY_DB_PASSWORD|erp_identity_db
erp_catalog|CATALOG_DB_PASSWORD|erp_catalog_db
erp_purchase|PURCHASE_DB_PASSWORD|erp_purchase_db
erp_inventory|INVENTORY_DB_PASSWORD|erp_inventory_db
erp_sales|SALES_DB_PASSWORD|erp_sales_db
erp_eoffice|EOFFICE_DB_PASSWORD|erp_eoffice_db
erp_finance|FINANCE_DB_PASSWORD|erp_finance_db
erp_analytics|ANALYTICS_DB_PASSWORD|erp_analytics_db
"

# ai-service dựng kho phân tích bằng ETL chỉ đọc từ các schema này (điểm lệch chuẩn đã biết, xem docs/deployment.md)
ANALYTICS_SOURCES="erp_identity_db erp_catalog_db erp_sales_db erp_inventory_db erp_purchase_db erp_finance_db"

# erp_user không dùng cho service nào; chỉ phục vụ test (erp_*_test) và công cụ dev chạy trên máy (seed, simulator).
# Production đặt MYSQL_DEV_TOOLS_ACCESS=false để erp_user mất quyền trên dữ liệu thật.
DEV_TOOLS_SCHEMAS="erp_identity_db erp_catalog_db erp_purchase_db erp_inventory_db erp_sales_db erp_eoffice_db erp_analytics_db erp_finance_db erp_chat_db"

sql=""
while IFS='|' read -r user password_var schema; do
    [ -z "$user" ] && continue
    password="${!password_var:-}"
    if [ -z "$password" ]; then
        echo "03-create-service-users: thiếu biến $password_var" >&2
        exit 1
    fi
    escaped="$(sql_escape "$password")"
    sql+="CREATE USER IF NOT EXISTS '${user}'@'%' IDENTIFIED BY '${escaped}';"
    sql+="ALTER USER '${user}'@'%' IDENTIFIED BY '${escaped}';"
    sql+="GRANT ALL PRIVILEGES ON \`${schema}\`.* TO '${user}'@'%';"
done <<< "$SERVICE_USERS"

for schema in $ANALYTICS_SOURCES; do
    sql+="GRANT SELECT ON \`${schema}\`.* TO 'erp_analytics'@'%';"
done

for schema in $DEV_TOOLS_SCHEMAS; do
    if [ "${MYSQL_DEV_TOOLS_ACCESS:-true}" = "true" ]; then
        sql+="GRANT ALL PRIVILEGES ON \`${schema}\`.* TO 'erp_user'@'%';"
    else
        sql+="REVOKE IF EXISTS ALL PRIVILEGES ON \`${schema}\`.* FROM 'erp_user'@'%' IGNORE UNKNOWN USER;"
    fi
done

sql+="FLUSH PRIVILEGES;"

MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql --protocol=socket -uroot -e "$sql"
echo "03-create-service-users: đã cấp tài khoản cho 8 service (dev tools access: ${MYSQL_DEV_TOOLS_ACCESS:-true})."
