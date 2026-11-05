#!/usr/bin/env bash
# Triển khai trên máy chủ AWS: kéo image dựng sẵn từ GHCR, chạy migration, bật toàn bộ hệ thống, smoke test.
# Chạy trong /opt/smart-erp, cần file .env (có COMPOSE_FILE=docker-compose.yml:docker-compose.prod.yml:docker-compose.aws.yml).
#   scripts/deploy-aws.sh                  triển khai phiên bản latest
#   IMAGE_TAG=sha-1a2b3c4 scripts/deploy-aws.sh   triển khai (hoặc quay lui) đúng một commit
#   SEED=1 scripts/deploy-aws.sh           nạp dữ liệu mẫu (chỉ lần dựng database đầu tiên)
set -euo pipefail
cd "$(dirname "$0")/.."

[ -f .env ] || { echo "Chưa có .env. Tạo bằng scripts/aws/make-prod-env.ps1 rồi dán lên máy chủ." >&2; exit 1; }
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
  echo "== Nạp dữ liệu mẫu"
  for svc in identity-service catalog-service eoffice-service finance-service; do
    docker compose run --rm --no-deps "$svc" php artisan db:seed --force
  done
fi

echo "== Bật toàn bộ hệ thống"
docker compose up -d --no-build --wait

echo "== Smoke test"
scripts/smoke-test.sh
echo "== TRIỂN KHAI XONG ($IMAGE_TAG)"
