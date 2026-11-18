# Dựng lại ALB cho Smart ERP bằng Terraform

Terraform chỉ quản lý lớp cân bằng tải: 2 ALB (`erp-alb`, `erp-internal`), 18 target group, listener, rule, và việc gắn máy vào target group. VPC, security group, ASG, EC2, RDS, EFS, S3, SSM, IAM, chứng chỉ ACM **không** thuộc về nó và vẫn nằm trên AWS khi ALB bị xóa.

Cấu hình trong `main.tf` được chép từ hệ thống đã chạy thật ngày 18/11/2026 (đường dẫn kiểm tra sức khỏe, thông số health check, thứ tự rule, cổng nội bộ 8001-8008).
**Chưa chạy `terraform validate/plan` lần nào** (máy soạn không cài Terraform): lần đầu dựng lại hãy đọc kỹ kết quả `plan`.

## Tắt (không tốn tiền)
1. Tắt RDS (`smart-erp-db`), EC2 `smart-erp-app-a`, `smart-erp-app-b`, đặt ASG `erp-app-asg` về 0. (Đã làm.)
2. Chạy `teardown.sh` trong CloudShell để xóa ALB và target group.
3. Còn lại chỉ là tiền lưu trữ nhỏ (EBS, RDS đã dừng, EFS, S3). RDS dừng sẽ **tự bật lại sau 7 ngày**: nếu chưa học lại thì dừng lại lần nữa.

## Bật lại
```bash
# 1. Bật nền dữ liệu và máy
aws rds start-db-instance --db-instance-identifier smart-erp-db --region ap-southeast-1
aws rds wait db-instance-available --db-instance-identifier smart-erp-db --region ap-southeast-1
aws ec2 start-instances --instance-ids i-0f3373bf8da54d26e --region ap-southeast-1      # máy A (RabbitMQ, Mongo, ...)

# 2. Dựng ALB (cần Terraform ≥ 1.6, đăng nhập quyền quản trị; CloudShell đã có sẵn bản cài thủ công theo hướng dẫn của HashiCorp)
cd infrastructure/terraform/aws-alb
terraform init
terraform plan          # phải thấy khoảng 60 resource cần tạo, không có thứ gì bị xóa
terraform apply
terraform output        # lấy 2 địa chỉ DNS mới

# 3. Cập nhật địa chỉ ALB nội bộ MỚI vào cấu hình (nó đổi mỗi lần dựng lại)
#    a) SSM: sửa dòng INTERNAL_LB_HOST=... trong tham số /smart-erp/prod/env-app
#    b) máy A: sửa INTERNAL_LB_HOST trong /opt/smart-erp/.env rồi `docker compose up -d`
# 4. DNS: ở pavietnam.vn sửa CNAME erp.transportbooking.xyz -> giá trị alb_dns_name
# 5. Đưa ASG về 1 máy: đặt min/desired = 1. Máy mới đọc env-app và image-tag từ SSM lúc khởi động
aws autoscaling update-auto-scaling-group --auto-scaling-group-name erp-app-asg --min-size 1 --desired-capacity 1 --region ap-southeast-1
```
Kiểm tra: `curl -s https://erp.transportbooking.xyz/ | grep -o 'assets/index-[^"]*\.js'` chạy nhiều lần phải ra cùng một bản, rồi đăng nhập thử.

## Nếu không dùng Terraform
ID máy A, VPC, security group, subnet, chứng chỉ ACM là biến có giá trị mặc định ở đầu `main.tf`. Nếu bạn tạo lại VPC hay xin lại chứng chỉ thì phải sửa các biến đó.
