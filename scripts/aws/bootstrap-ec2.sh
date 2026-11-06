#!/usr/bin/env bash
# Dựng một máy ứng dụng từ Ubuntu 24.04 trắng: cài Docker, mount EFS dùng chung, lấy mã nguồn về /opt/smart-erp.
# Chạy MỘT LẦN, bằng root. Cách dùng thông thường: dán toàn bộ file này vào ô "User data" khi tạo EC2 (cloud-init tự chạy ở
# lần khởi động đầu). Muốn lấy nhánh khác, thêm dòng `export GIT_BRANCH=feat/ha-aws` ngay sau dòng #!/usr/bin/env bash.
# Log: /var/log/cloud-init-output.log (tìm các dòng bắt đầu bằng [bootstrap]).
#
# Script CHỈ chuẩn bị máy. Việc còn lại làm tay qua Session Manager: dán .env, rồi chạy scripts/deploy-aws.sh.
# Chạy lại an toàn (mỗi bước kiểm tra trạng thái trước khi làm).
set -euo pipefail

EFS_ID="${EFS_ID:-fs-0d08f996b0e9f86e4}"
REGION="${REGION:-ap-southeast-1}"
REPO_URL="${REPO_URL:-https://github.com/ttung205/smart-erp.git}"
GIT_BRANCH="${GIT_BRANCH:-main}"
APP_DIR=/opt/smart-erp
export DEBIAN_FRONTEND=noninteractive

log() { echo "[bootstrap] $*"; }

log "Cài gói cơ bản (git, nfs-common cho EFS, jq, curl)"
apt-get update -qq
apt-get install -y -qq ca-certificates curl git jq nfs-common

if ! command -v docker > /dev/null; then
  log "Cài Docker Engine và Compose từ kho chính thức của Docker"
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" \
    > /etc/apt/sources.list.d/docker.list
  apt-get update -qq
  apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker
usermod -aG docker ubuntu

log "Mount EFS $EFS_ID tại /mnt/efs (tệp đính kèm của sales dùng chung giữa hai máy)"
mkdir -p /mnt/efs
grep -q ' /mnt/efs ' /etc/fstab \
  || echo "${EFS_ID}.efs.${REGION}.amazonaws.com:/ /mnt/efs nfs4 nfsvers=4.1,rsize=1048576,wsize=1048576,hard,timeo=600,retrans=2,noresvport,_netdev 0 0" >> /etc/fstab
systemctl daemon-reload
mountpoint -q /mnt/efs || mount /mnt/efs
# Trong container sales, PHP chạy bằng www-data (uid 33): thư mục phải thuộc uid này
mkdir -p /mnt/efs/sales
chown 33:33 /mnt/efs/sales

if [ ! -d "$APP_DIR/.git" ]; then
  log "Lấy mã nguồn, nhánh $GIT_BRANCH"
  git clone --branch "$GIT_BRANCH" "$REPO_URL" "$APP_DIR"
fi
chown -R ubuntu:ubuntu "$APP_DIR"

log "XONG. Việc tiếp theo (Session Manager, user ubuntu): cd $APP_DIR, dán .env của máy này, chạy scripts/deploy-aws.sh"
