#!/usr/bin/env bash
# Kiểm tra các route /health đi qua toàn bộ chuỗi: Internet -> Caddy -> frontend (nginx) -> Kong -> service.
# Bản bash của scripts/smoke-test.ps1 để chạy ngay trên máy chủ Linux.
#   BASE_URL=https://xxx.sslip.io scripts/smoke-test.sh      (mặc định lấy từ SITE_ADDRESS trong .env)
set -uo pipefail
cd "$(dirname "$0")/.."

if [ -z "${BASE_URL:-}" ]; then
  site=$(grep -E '^SITE_ADDRESS=' .env 2> /dev/null | cut -d= -f2- || true)
  [ -n "$site" ] || { echo "Chưa có BASE_URL hoặc SITE_ADDRESS trong .env" >&2; exit 2; }
  BASE_URL="https://$site"
fi

routes=(
  /health
  /api/v1/auth/health /api/v1/catalog/health /api/v1/purchase/health /api/v1/inventory/health
  /api/v1/sales/health /api/v1/notifications/health /api/v1/eoffice/health /api/v1/finance/health
  /api/v1/chat/health /api/v1/ai/health
)

# SMOKE_SKIP="chat notifications": bỏ các tuyến chứa từ khóa này (máy B của chế độ HA không chạy chúng)
failed=0
for route in "${routes[@]}"; do
  skip=0
  for word in ${SMOKE_SKIP:-}; do [[ "$route" == *"$word"* ]] && skip=1; done
  [ "$skip" = 1 ] && { printf '%-34s bỏ qua
' "$route"; continue; }
  code=000
  # Lần đầu Caddy có thể còn đang xin chứng chỉ: thử lại tối đa 12 lần, mỗi lần cách 5 giây
  for attempt in $(seq 1 12); do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$BASE_URL$route" || true)
    [ "$code" = 200 ] && break
    sleep 5
  done
  if [ "$code" = 200 ]; then printf '%-34s %s OK\n' "$route" "$code"; else printf '%-34s %s FAIL\n' "$route" "$code"; failed=1; fi
done

[ "$failed" = 0 ] && echo "SMOKE TEST ĐẠT ($BASE_URL)" || { echo "SMOKE TEST THẤT BẠI ($BASE_URL)" >&2; exit 1; }
