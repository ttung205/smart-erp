# scripts/

| Thư mục hoặc file | Dùng để | Chạy ở đâu |
|---|---|---|
| `powershell/` | `deploy.ps1`, `test.ps1`, `smoke-test.ps1`, `validate-prod-config.ps1`, `apply-db-users.ps1`, `replay-dead-letters.ps1`: công cụ chạy trên máy Windows của lập trình viên | máy dev |
| `data/` | Pipeline làm sạch dữ liệu, kiểm chứng mô hình và nạp dữ liệu phân tích cho ai-service | máy dev |
| `tests/` | Kiểm thử tích hợp chạy tay: đồng bộ, realtime, JWT của analytics API | máy dev |
| `aws/` | Dựng máy EC2, golden AMI, khởi động máy của Auto Scaling group, khởi tạo RDS | AWS |
| `deploy-aws.sh`, `smoke-test.sh` | Triển khai và kiểm tra nhanh trên máy chủ Linux | EC2 |

`deploy-aws.sh`, `smoke-test.sh` và `aws/` giữ nguyên ở gốc `scripts/` vì có nơi bên ngoài repo gọi tới đường dẫn này (user data của launch template, lệnh SSM trong `.github/workflows/deploy.yml`, golden AMI). Đổi đường dẫn các file đó thì máy mới của Auto Scaling group sẽ không khởi động được.
