#!/bin/bash
# "User data" của EC2 (Ubuntu 24.04): chạy MỘT LẦN, bằng root, ở lần khởi động đầu tiên.
# Việc làm: cài Docker, lấy mã nguồn về /opt/smart-erp, đặt lịch sao lưu hằng giờ.
# KHÔNG tạo file .env (chứa secret): bước đó làm tay qua Session Manager, xem docs/learning/defense/08-devops-aws.md.
# Log: /var/log/smart-erp-bootstrap.log
set -euxo pipefail
exec > >(tee -a /var/log/smart-erp-bootstrap.log) 2>&1

APP_DIR=/opt/smart-erp
REPO_URL=https://github.com/ttung205/smart-erp.git
APP_USER=ubuntu

export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y git curl ca-certificates cron

# Docker Engine + plugin Compose (script cài đặt chính thức của Docker)
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker
usermod -aG docker "$APP_USER"

# Mã nguồn (repo công khai nên không cần token)
if [ ! -d "$APP_DIR/.git" ]; then
  git clone "$REPO_URL" "$APP_DIR"
fi
chown -R "$APP_USER:$APP_USER" "$APP_DIR"

# Sao lưu mỗi giờ (RPO tối đa 1 giờ). Cần file .env có BACKUP_DB_PASSWORD, BACKUP_S3_URI...
touch /var/log/smart-erp-backup.log
chown "$APP_USER:$APP_USER" /var/log/smart-erp-backup.log
cat > /etc/cron.d/smart-erp-backup << 'CRON'
0 * * * * ubuntu cd /opt/smart-erp && docker compose run --rm backup >> /var/log/smart-erp-backup.log 2>&1
CRON
chmod 644 /etc/cron.d/smart-erp-backup

echo "BOOTSTRAP XONG $(date -u +%FT%TZ)"
