#!/usr/bin/env bash
# Triển khai trên máy chủ AWS: kéo image dựng sẵn từ GHCR, chạy migration, bật toàn bộ hệ thống, smoke test.
# Chạy trong /opt/smart-erp, cần file .env (có COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml:docker-compose.aws.yml).
#   scripts/deploy-aws.sh                  triển khai phiên bản latest
#   IMAGE_TAG=sha-1a2b3c4 scripts/deploy-aws.sh   triển khai (hoặc quay lui) đúng một commit
#   SEED=1 IDENTITY_ADMIN_EMAIL=... IDENTITY_ADMIN_NAME=... IDENTITY_ADMIN_PASSWORD='...' EMPLOYEE_CODE_PREFIX=AC scripts/deploy-aws.sh
#                                          lần dựng database đầu tiên: nạp phòng ban, vai trò, quyền (KHÔNG có tài khoản mẫu) rồi
#                                          tạo Super Admin đầu tiên. Mật khẩu >= 14 ký tự. Đăng nhập bằng mã nhân viên in ra ở cuối bước này
#                                          (OTP gửi về IDENTITY_ADMIN_EMAIL, cần SMTP thật trong .env).
#   PURGE_DEMO_USERS=1 scripts/deploy-aws.sh   xóa các tài khoản mẫu @erp.local còn sót từ lần triển khai cũ
#   SEED_SERVICES="identity-service catalog-service" ... chọn thêm service khác để nạp dữ liệu mẫu (mặc định chỉ identity)
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "Chưa có .env. Tạo bằng scripts/aws/make-prod-env.ps1 rồi dán lên máy chủ." >&2; exit 1; }

# Kiểm tra sớm để thất bại ngay (không đợi kéo image mười mấy phút rồi mới báo thiếu thông tin).
if [ "${SEED:-0}" = 1 ] && [ -n "${IDENTITY_ADMIN_EMAIL:-}" ]; then
  : "${IDENTITY_ADMIN_NAME:?Đặt IDENTITY_ADMIN_NAME (họ tên Super Admin)}"
  : "${IDENTITY_ADMIN_PASSWORD:?Đặt IDENTITY_ADMIN_PASSWORD (>= 14 ký tự)}"
  [ "${#IDENTITY_ADMIN_PASSWORD}" -ge 14 ] || { echo "IDENTITY_ADMIN_PASSWORD quá ngắn (cần >= 14 ký tự)." >&2; exit 1; }
fi

export IMAGE_TAG="${IMAGE_TAG:-latest}"
echo "== Phiên bản image: $IMAGE_TAG"

echo "== Kiểm tra cấu hình"
docker compose config --quiet

echo "== Kéo image từ GHCR"
# Kéo image qua Internet thỉnh thoảng bị ngắt giữa chừng (vd "connection reset by peer"). Docker giữ lại các lớp đã tải
# nên thử lại chỉ tải phần còn thiếu. Thử tối đa 4 lần rồi mới dừng.
for attempt in 1 2 3 4; do
  docker compose pull --quiet && break
  [ "$attempt" = 4 ] && { echo "Kéo image thất bại sau 4 lần thử." >&2; exit 1; }
  echo "Kéo image lỗi (lần $attempt/4), chờ 15 giây rồi thử lại..."
  sleep 15
done

echo "== Bật hạ tầng"
docker compose up -d --no-build --wait mysql rabbitmq mongo

echo "== Migration (Laravel)"
for svc in identity-service catalog-service sales-service eoffice-service finance-service; do
  echo "-- $svc"
  docker compose run --rm --no-deps "$svc" php artisan migrate --force
done

if [ "${SEED:-0}" = 1 ]; then
  seed_services="${SEED_SERVICES:-identity-service}"
  echo "== Nạp dữ liệu nền: $seed_services"
  for svc in $seed_services; do
    docker compose run --rm --no-deps "$svc" php artisan db:seed --force
  done
  if [ -n "${IDENTITY_ADMIN_EMAIL:-}" ]; then
    echo "== Tạo Super Admin đầu tiên"
    docker compose run --rm --no-deps -e IDENTITY_ADMIN_PASSWORD="$IDENTITY_ADMIN_PASSWORD" identity-service       php artisan identity:bootstrap-admin --email="$IDENTITY_ADMIN_EMAIL" --name="$IDENTITY_ADMIN_NAME" --prefix="${EMPLOYEE_CODE_PREFIX:-}"
  fi
fi

if [ "${PURGE_DEMO_USERS:-0}" = 1 ]; then
  echo "== Xóa tài khoản mẫu @erp.local"
  docker compose run --rm --no-deps identity-service php artisan identity:purge-demo-users --force
fi

echo "== Bật toàn bộ hệ thống"
docker compose up -d --no-build --wait

echo "== Smoke test"
scripts/smoke-test.sh
echo "== TRIỂN KHAI XONG ($IMAGE_TAG)"
