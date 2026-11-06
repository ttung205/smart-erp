#!/usr/bin/env bash
# Khởi tạo MySQL ở RDS: tạo 8 schema (database-per-service), 8 tài khoản riêng cho từng service và tài khoản erp_backup chỉ đọc.
# Dùng đúng các script của chế độ một máy (infrastructure/mysql/init) nên quyền hạn giống hệt, chỉ khác là chạy qua mạng.
#
# Chạy MỘT LẦN trên EC2, trong /opt/smart-erp, sau khi RDS đã tạo xong và .env có MYSQL_HOST=<endpoint RDS>:
#   scripts/aws/rds-init.sh
# Mật khẩu quản trị RDS đọc từ MYSQL_ROOT_PASSWORD trong .env (hoặc hỏi nếu thiếu). Có thể chạy lại an toàn (CREATE ... IF NOT EXISTS,
# ALTER USER đặt lại mật khẩu theo .env).
set -euo pipefail
cd "$(dirname "$0")/../.."

[ -f .env ] || { echo "Chưa có .env trong $(pwd)." >&2; exit 1; }
host=$(grep -E '^MYSQL_HOST=' .env | cut -d= -f2- || true)
[ -n "$host" ] || { echo "Chưa có MYSQL_HOST=<endpoint RDS> trong .env." >&2; exit 1; }
# Tài khoản quản trị RDS lấy từ .env (MYSQL_ROOT_USER, MYSQL_ROOT_PASSWORD) để không phải gõ lại; đặt MYSQL_ADMIN_USER /
# MYSQL_ADMIN_PASSWORD trong môi trường để dùng giá trị khác. Không có cả hai nơi thì hỏi (nhập ẩn).
admin_user="${MYSQL_ADMIN_USER:-$(grep -E '^MYSQL_ROOT_USER=' .env | cut -d= -f2- || true)}"
admin_user="${admin_user:-admin}"
if [ -z "${MYSQL_ADMIN_PASSWORD:-}" ]; then
  MYSQL_ADMIN_PASSWORD=$(grep -E '^MYSQL_ROOT_PASSWORD=' .env | cut -d= -f2- || true)
fi
if [ -z "${MYSQL_ADMIN_PASSWORD:-}" ]; then
  read -rsp "Mật khẩu quản trị RDS (người dùng $admin_user): " MYSQL_ADMIN_PASSWORD; echo
fi
export MYSQL_ADMIN_PASSWORD

# Chạy trong container mysql:8.0 để có sẵn mysql client; mật khẩu truyền qua biến môi trường, không nằm trong tham số dòng lệnh
# (tránh lộ ra trong danh sách tiến trình). Production luôn tắt quyền erp_user: tài khoản đó không tồn tại ở RDS.
docker run --rm --env-file .env \
  -e MYSQL_ADMIN_HOST="$host" -e MYSQL_ADMIN_USER="$admin_user" -e MYSQL_ADMIN_PASSWORD \
  -e MYSQL_DEV_TOOLS_ACCESS=false \
  -v "$PWD/infrastructure/mysql/init:/init:ro" \
  mysql:8.0 bash -euo pipefail -c '
    echo "== Kiểm tra kết nối tới $MYSQL_ADMIN_HOST"
    MYSQL_PWD="$MYSQL_ADMIN_PASSWORD" mysql -h"$MYSQL_ADMIN_HOST" -u"$MYSQL_ADMIN_USER" -e "SELECT VERSION() AS version, @@read_only AS read_only"
    echo "== Tạo schema"
    MYSQL_PWD="$MYSQL_ADMIN_PASSWORD" mysql -h"$MYSQL_ADMIN_HOST" -u"$MYSQL_ADMIN_USER" < /init/01-create-databases.sql
    echo "== Tạo tài khoản và cấp quyền"
    bash /init/03-create-service-users.sh
    MYSQL_PWD="$MYSQL_ADMIN_PASSWORD" mysql -h"$MYSQL_ADMIN_HOST" -u"$MYSQL_ADMIN_USER" -e "SELECT user, host FROM mysql.user WHERE user LIKE \"erp\_%\" ORDER BY user; SHOW DATABASES LIKE \"erp\_%\""
  '
echo "== Xong. Bước tiếp: khôi phục dữ liệu (docker compose run --rm recovery restore ... --yes) hoặc chạy migration (scripts/deploy-aws.sh)."
