#!/usr/bin/env bash
# User data của launch template trong Auto Scaling Group. Chạy bằng root ở lần khởi động đầu của mỗi máy mới sinh ra
# từ golden AMI (đã có Docker, AWS CLI, image kéo sẵn, mã nguồn, dòng EFS trong fstab; KHÔNG có .env).
#
# Việc làm: mount EFS, lấy .env và thẻ image từ SSM Parameter Store bằng IAM role của máy, cập nhật mã nguồn,
# rồi chạy scripts/deploy-aws.sh với vai trò máy ứng dụng (HA_ROLE=b trong .env). Log: /var/log/asg-boot.log.
# Máy chỉ được ALB nhận khi /health đạt, nên nếu script này lỗi thì máy không nhận traffic và ASG sẽ thay nó.
set -euo pipefail
exec > >(tee -a /var/log/asg-boot.log) 2>&1

REGION="${AWS_REGION:-ap-southeast-1}"
PREFIX="${SSM_PREFIX:-/smart-erp/prod}"
APP_DIR=/opt/smart-erp
log() { echo "[asg-boot] $(date -u +%H:%M:%S) $*"; }

retry() { # retry <lần> <giây> lệnh...
  local n=$1 s=$2; shift 2
  local i
  for i in $(seq 1 "$n"); do
    "$@" && return 0
    log "lần $i/$n thất bại: $*"
    sleep "$s"
  done
  return 1
}

log "Mount EFS"
mkdir -p /mnt/efs
mountpoint -q /mnt/efs || retry 12 10 mount /mnt/efs
mkdir -p /mnt/efs/sales /mnt/efs/eoffice /mnt/efs/purchase
mountpoint -q /mnt/efs || { log "EFS chưa mount được"; exit 1; }

log "Lấy .env và thẻ image từ SSM ($PREFIX)"
umask 077
retry 6 10 aws ssm get-parameter --region "$REGION" --name "$PREFIX/env-app" --with-decryption \
  --query Parameter.Value --output text > "$APP_DIR/.env.tmp"
mv "$APP_DIR/.env.tmp" "$APP_DIR/.env"
chown ubuntu:ubuntu "$APP_DIR/.env"
chmod 600 "$APP_DIR/.env"
grep -q '^HA_ROLE=b' "$APP_DIR/.env" || { log "Tham số env-app không phải .env của vai trò máy ứng dụng (thiếu HA_ROLE=b)"; exit 1; }

IMAGE_TAG=$(aws ssm get-parameter --region "$REGION" --name "$PREFIX/image-tag" --query Parameter.Value --output text)
[[ "$IMAGE_TAG" =~ ^(latest|sha-[0-9a-f]{7,40})$ ]] || { log "Thẻ image không hợp lệ: $IMAGE_TAG"; exit 1; }
log "Phiên bản triển khai: $IMAGE_TAG"

log "Cập nhật mã nguồn (file compose và script)"
retry 4 10 sudo -u ubuntu git -C "$APP_DIR" pull -q --ff-only

log "Deploy (kéo phần image chênh lệch, bật dịch vụ, smoke test)"
sudo -u ubuntu -H bash -lc "cd $APP_DIR && IMAGE_TAG=$IMAGE_TAG scripts/deploy-aws.sh"

touch /var/lib/asg-boot.done
log "XONG: máy sẵn sàng nhận traffic từ ALB."
