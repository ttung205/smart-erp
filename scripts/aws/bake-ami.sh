#!/usr/bin/env bash
# Chuẩn bị một máy sạch để tạo golden AMI cho Auto Scaling Group (tầng ứng dụng, vai trò máy B).
# Chạy bằng root sau khi máy đã được dựng bởi scripts/aws/bootstrap-ec2.sh (Docker, EFS, mã nguồn):
#   sudo IMAGE_TAG=sha-xxxxxxx scripts/aws/bake-ami.sh
#
# Việc làm: cài AWS CLI v2 (máy ASG dùng nó để đọc bí mật từ SSM), kéo sẵn image của tầng ứng dụng để máy mới
# khởi động nhanh. KHÔNG tạo .env và KHÔNG chạy container: AMI không bao giờ được chứa bí mật.
# Sau khi chạy xong: Stop máy, rồi EC2 → Actions → Image and templates → Create image.
set -euo pipefail

# Script này chuyển nhánh git của thư mục dự án (checkout main) nên có thể xóa chính file đang chạy; bash đọc script
# dần từng dòng, nên phải chạy bản sao ở /tmp để không bị hỏng giữa chừng.
if [ -z "${BAKE_REEXEC:-}" ]; then
  cp "$0" /tmp/bake-ami.sh
  BAKE_REEXEC=1 exec bash /tmp/bake-ami.sh "$@"
fi

IMAGE_TAG="${IMAGE_TAG:?Đặt IMAGE_TAG=sha-xxxxxxx (thẻ image hiện đang chạy ở production)}"
REGISTRY="${REGISTRY:-ghcr.io/ttung205}"
APP_DIR=/opt/smart-erp
# Các image của máy ứng dụng (không có RabbitMQ, Mongo, chat, notification, giám sát: chỉ chạy ở máy A)
SERVICES="identity-service catalog-service purchase-service inventory-service sales-service eoffice-service finance-service ai-service frontend"
KONG_IMAGE="${KONG_IMAGE:-kong:3.9.1}"

log() { echo "[bake] $*"; }
[ "$(id -u)" = 0 ] || { echo "Chạy bằng root (sudo)." >&2; exit 1; }
[[ "$IMAGE_TAG" =~ ^(latest|sha-[0-9a-f]{7,40})$ ]] || { echo "IMAGE_TAG không hợp lệ: $IMAGE_TAG" >&2; exit 1; }

if ! command -v aws > /dev/null; then
  log "Cài AWS CLI v2"
  apt-get update -qq
  apt-get install -y -qq unzip curl
  tmp=$(mktemp -d)
  curl -fsSL "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "$tmp/awscliv2.zip"
  unzip -q "$tmp/awscliv2.zip" -d "$tmp"
  "$tmp/aws/install" --update
  rm -rf "$tmp"
fi
aws --version

log "Cập nhật mã nguồn (file compose và script) lên nhánh main"
sudo -u ubuntu git -C "$APP_DIR" fetch -q origin main
sudo -u ubuntu git -C "$APP_DIR" checkout -q main
sudo -u ubuntu git -C "$APP_DIR" pull -q --ff-only

log "Kéo image thẻ $IMAGE_TAG (có thể mất vài phút)"
for svc in $SERVICES; do
  for attempt in 1 2 3 4; do
    docker pull -q "$REGISTRY/smart-erp-$svc:$IMAGE_TAG" > /dev/null && break
    [ "$attempt" = 4 ] && { echo "Kéo $svc thất bại sau 4 lần" >&2; exit 1; }
    sleep 15
  done
  log "  đã kéo smart-erp-$svc:$IMAGE_TAG"
done
docker pull -q "$KONG_IMAGE" > /dev/null
log "  đã kéo $KONG_IMAGE"

log "Kiểm tra AMI sẽ không chứa bí mật"
if [ -e "$APP_DIR/.env" ]; then
  echo "LỖI: $APP_DIR/.env tồn tại. Xóa nó rồi chạy lại (AMI không được chứa bí mật)." >&2
  exit 1
fi
if [ -n "$(docker ps -q)" ]; then
  echo "LỖI: đang có container chạy. Dừng và xóa chúng trước khi tạo AMI." >&2
  exit 1
fi

log "Dọn nhật ký và lịch sử lệnh"
cloud-init clean --logs > /dev/null 2>&1 || true
rm -f /home/ubuntu/.bash_history /root/.bash_history

log "XONG. Stop máy này rồi tạo AMI. Image đã kéo sẵn: $IMAGE_TAG (máy mới sẽ kéo phần chênh lệch theo tham số /smart-erp/prod/image-tag)."
