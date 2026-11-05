#!/usr/bin/env bash
# Đặt mật khẩu cho các TÀI KHOẢN MẪU (email đuôi @erp.local, do seeder tạo, vốn dùng mật khẩu công khai password123).
# Người dùng có email khác không bị đụng tới. Chạy ngay sau khi nạp tài khoản mẫu, trước khi mở hệ thống ra Internet.
#
#   scripts/aws/set-demo-password.sh                      hỏi mật khẩu (gõ không hiện lên màn hình)
#   DEMO_PASSWORD='...' scripts/aws/set-demo-password.sh  lấy từ biến môi trường (dùng khi chạy không tương tác)
#
# Mật khẩu tối thiểu 14 ký tự. Truyền vào container bằng biến môi trường, không nằm trên dòng lệnh và không vào lịch sử lệnh.
# Dùng chung một mật khẩu cho mọi tài khoản mẫu là chủ ý (để trình diễn nhiều vai trò); đây KHÔNG phải cách quản lý
# người dùng thật.
set -euo pipefail
cd "$(dirname "$0")/../.."

if [ -z "${DEMO_PASSWORD:-}" ]; then
  [ -t 0 ] || { echo "Không có DEMO_PASSWORD và không có bàn phím để hỏi." >&2; exit 1; }
  read -rsp "Mật khẩu mới cho các tài khoản mẫu @erp.local (tối thiểu 14 ký tự): " DEMO_PASSWORD
  echo
fi
if [ "${#DEMO_PASSWORD}" -lt 14 ]; then
  echo "Mật khẩu quá ngắn: cần tối thiểu 14 ký tự." >&2
  exit 1
fi

docker compose run --rm --no-deps -e DEMO_PW="$DEMO_PASSWORD" identity-service php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$count = Illuminate\Support\Facades\DB::table("users")->where("email", "like", "%@erp.local")->update(["password_hash" => Illuminate\Support\Facades\Hash::make(getenv("DEMO_PW"))]);
echo "Da dat mat khau cho $count tai khoan\n";
'
