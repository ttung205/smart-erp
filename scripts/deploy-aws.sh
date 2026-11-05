#!/usr/bin/env bash
# Triển khai trên máy chủ AWS: kéo image dựng sẵn từ GHCR, chạy migration, bật toàn bộ hệ thống, smoke test.
# Chạy trong /opt/smart-erp, cần file .env (có COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml:docker-compose.aws.yml).
#   scripts/deploy-aws.sh                  triển khai phiên bản latest
#   IMAGE_TAG=sha-1a2b3c4 scripts/deploy-aws.sh   triển khai (hoặc quay lui) đúng một commit
#   DEMO_PASSWORD='...' SEED=1 scripts/deploy-aws.sh
#                                          lần dựng database đầu tiên: nạp tài khoản mẫu (chỉ identity) rồi ĐỔI MẬT KHẨU
#                                          của chúng thành DEMO_PASSWORD (>= 14 ký tự) trước khi bật hệ thống ra Internet.
#                                          Thiếu DEMO_PASSWORD thì từ chối nạp (seeder dùng mật khẩu công khai password123).
#   SEED_SERVICES="identity-service catalog-service" ... chọn thêm service khác để nạp dữ liệu mẫu (mặc định chỉ identity)
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "Chưa có .env. Tạo bằng scripts/aws/make-prod-env.ps1 rồi dán lên máy chủ." >&2; exit 1; }

# Kiểm tra sớm để thất bại ngay (không đợi kéo image mười mấy phút rồi mới báo thiếu mật khẩu).
# Seeder tạo 18 tài khoản (kể cả SUPER_ADMIN) với mật khẩu công khai password123: không bao giờ để chúng tồn tại
# trên máy chủ ra Internet, nên bắt buộc đổi mật khẩu NGAY sau khi nạp và TRƯỚC khi bật gateway.
if [ "${SEED:-0}" = 1 ]; then
  : "${DEMO_PASSWORD:?Đặt DEMO_PASSWORD (>= 14 ký tự) để đổi mật khẩu tài khoản mẫu ngay sau khi nạp}"
  [ "${#DEMO_PASSWORD}" -ge 14 ] || { echo "DEMO_PASSWORD quá ngắn (cần >= 14 ký tự)." >&2; exit 1; }
fi

export IMAGE_TAG="${IMAGE_TAG:-latest}"
echo "== Phiên bản image: $IMAGE_TAG"

echo "== Kiểm tra cấu hình"
docker compose config --quiet

echo "== Kéo image từ GHCR"
docker compose pull --quiet

echo "== Bật hạ tầng"
docker compose up -d --no-build --wait mysql rabbitmq mongo

echo "== Migration (Laravel)"
for svc in identity-service catalog-service sales-service eoffice-service finance-service; do
  echo "-- $svc"
  docker compose run --rm --no-deps "$svc" php artisan migrate --force
done

if [ "${SEED:-0}" = 1 ]; then
  # Chạy lại deploy khi hệ thống ĐÃ bật ra Internet: tắt cửa vào (Caddy, Kong) trong lúc nạp và đổi mật khẩu để không có
  # lúc nào password123 truy cập được từ ngoài. Bước "Bật toàn bộ hệ thống" bên dưới sẽ bật lại chúng.
  docker compose stop caddy gateway || true
  seed_services="${SEED_SERVICES:-identity-service}"
  echo "== Nạp dữ liệu mẫu: $seed_services"
  for svc in $seed_services; do
    docker compose run --rm --no-deps "$svc" php artisan db:seed --force
  done
  case " $seed_services " in
    *" identity-service "*)
      echo "== Đổi mật khẩu tài khoản mẫu"
      scripts/aws/set-demo-password.sh
      ;;
  esac
fi

echo "== Bật toàn bộ hệ thống"
docker compose up -d --no-build --wait

echo "== Smoke test"
scripts/smoke-test.sh
echo "== TRIỂN KHAI XONG ($IMAGE_TAG)"
