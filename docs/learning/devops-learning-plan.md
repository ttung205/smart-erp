# Lộ trình học AWS & DevOps qua project Smart ERP

> **Mục tiêu:** Tự tay đưa `smart-erp` từ máy local lên AWS một cách chuyên nghiệp: có CI/CD, hạ tầng dạng code, giám sát, backup. Qua đó **hiểu vì sao** mỗi thứ tồn tại, không chỉ biết gõ lệnh.
>
> **Ngân sách:** 100$ credit AWS (free plan, 6 tháng) + các công cụ miễn phí (GitHub Actions, GHCR, Terraform, k3s).
>
> **Thời lượng gợi ý:** 10–12 tuần, mỗi tuần khoảng 8–10 giờ. Có thể nhanh hoặc chậm hơn, quan trọng là làm xong phần "Tự kiểm tra" của mỗi giai đoạn rồi mới đi tiếp.

---

## Cách dùng file này

Mỗi giai đoạn gồm 5 phần:

| Phần | Ý nghĩa |
|---|---|
| 🎯 **Mục tiêu** | Kết thúc giai đoạn bạn làm được gì |
| 📚 **Hiểu khái niệm** | Lý thuyết cần nắm, kèm câu hỏi "vì sao" |
| 🛠 **Thực hành trên project** | Bài tập cụ thể trên chính code `smart-erp` |
| ✅ **Tự kiểm tra** | Trả lời được hết thì mới qua giai đoạn sau |
| 📦 **Sản phẩm** | Thứ còn lại sau giai đoạn (file, PR, ghi chú). Đây cũng là bằng chứng cho CV và báo cáo đồ án |

**Ba nguyên tắc:**

1. **Local trước, cloud sau.** Thứ gì chạy được trên máy mình thì làm ở máy mình (miễn phí, sửa sai nhanh).
2. **Làm hỏng có chủ đích.** Mỗi giai đoạn đều có bài "phá thử" để hiểu hệ thống phản ứng thế nào khi lỗi. Đây là phần học được nhiều nhất.
3. **Ghi nhật ký.** Mỗi lỗi gặp phải ghi lại theo mẫu ở [Phụ lục B](#phụ-lục-b-mẫu-nhật-ký-sự-cố). Sau 3 tháng, đây là tài liệu quý nhất bạn có.

**Cách làm với Git:** tạo branch riêng cho mỗi giai đoạn (`devops/phase-1-ci`, `devops/phase-4-terraform`…), mở PR và tự review trước khi merge. Đây là thói quen làm việc thật.

---

## Tổng quan

```
Giai đoạn 0  Docker & hiểu hệ thống             local      0$     1–2 tuần
Giai đoạn 1  CI: GitHub Actions + GHCR           GitHub     0$     1–2 tuần
Giai đoạn 2  AWS nền tảng + deploy thủ công      AWS       ~10$    2 tuần
Giai đoạn 3  CD: tự động deploy, quản lý secret  AWS       ~10$    1 tuần
Giai đoạn 4  Terraform (Infrastructure as Code)  AWS       ~10$    2 tuần
Giai đoạn 5  Dịch vụ managed: S3, RDS, CloudWatch AWS      ~15$    1–2 tuần
Giai đoạn 6  Vận hành: monitoring, backup, chaos AWS       ~15$    1–2 tuần
Giai đoạn 7  Kubernetes (tùy chọn)               local/OCI  0$     2–3 tuần
                                                 Tổng      ~60$ (dư ~40$ phòng sự cố)
```

---

## Giai đoạn 0: Docker & hiểu hệ thống (local, 0$)

### 🎯 Mục tiêu
Chạy được toàn bộ 10 service bằng một lệnh, đo được hệ thống tốn bao nhiêu tài nguyên, và vẽ lại được sơ đồ hệ thống theo cách hiểu của mình.

### 📚 Hiểu khái niệm
- **Image và container:** khác nhau thế nào? Layer là gì? Vì sao thứ tự lệnh `COPY` trong Dockerfile ảnh hưởng tốc độ build?
- **Multi-stage build:** so sánh `inventory-service/Dockerfile` (có stage `build` và stage `runtime`) với `sales-service/Dockerfile` (một stage). Image nào nhỏ hơn, vì sao?
- **Docker network:** vì sao `sales-service` gọi được `http://inventory-service:8004` mà không cần IP?
- **Volume:** dữ liệu MySQL nằm ở đâu? `docker compose down` và `docker compose down -v` khác nhau thế nào?
- **Healthcheck và `depends_on: condition: service_healthy`:** vì sao service phải chờ MySQL "healthy" chứ không chỉ chờ "started"?
- **Biến môi trường:** cách `${VAR:?message}` trong `docker-compose.prod.yml` buộc phải đặt secret.

### 🛠 Thực hành trên project
1. Chạy toàn bộ stack:
   ```bash
   cp .env.example .env
   docker compose up -d --build
   docker compose ps          # tất cả phải "healthy"
   ```
2. **Đo tài nguyên thật**, ghi kết quả vào nhật ký:
   ```bash
   docker stats --no-stream --format "table {{.Name}}\t{{.MemUsage}}\t{{.CPUPerc}}"
   docker images | grep smart-erp   # kích thước từng image
   ```
   Con số này quyết định chọn cỡ máy EC2 ở giai đoạn 2.
3. **Tối ưu một Dockerfile Laravel** (ví dụ `sales-service`):
   - Thêm stage build riêng cho `composer install`, stage runtime chỉ copy `vendor/`.
   - Thay `chmod -R 777` bằng chạy dưới user không phải root (`USER www-data`).
   - So sánh kích thước image trước và sau.
4. **Tìm hiểu cách phục vụ PHP:** các service Laravel chạy PHP-FPM + Nginx (xem `docker/start-web`, `docker/php-fpm.conf`). `php artisan serve` chỉ xử lý **một request** mỗi lúc nên không dùng cho production. So sánh với FrankenPHP / Laravel Octane và đo bằng `ab` hoặc `k6`.
5. Thêm file `.dockerignore` cho service nào còn thiếu, kiểm tra không copy `vendor/`, `node_modules/`, `.env` vào image.
6. Vẽ lại sơ đồ: request từ trình duyệt → Kong → service → MySQL/RabbitMQ → notification → Socket.IO.

### 💥 Phá thử
- `docker compose stop rabbitmq`, tạo một báo giá, xem bảng `outbox_events` (trạng thái `PENDING`/`FAILED`), rồi `start` lại RabbitMQ và xem event được gửi đi.
- `docker compose kill mysql` khi đang dùng web. Service nào báo lỗi gì? Healthcheck phản ứng ra sao?

### ✅ Tự kiểm tra
- [ ] Giải thích được vì sao `COPY composer.json` được đặt trước `COPY . .`
- [ ] Biết cách xem log của một container và vào shell của nó
- [ ] Nói được tổng RAM hệ thống cần (con số đo thật)
- [ ] Giải thích được vì sao PHP-FPM + Nginx hợp với production hơn `php artisan serve`

### 📦 Sản phẩm
PR tối ưu Dockerfile; bảng đo tài nguyên; sơ đồ kiến trúc tự vẽ.

---

## Giai đoạn 1: CI với GitHub Actions + GHCR (0$)

### 🎯 Mục tiêu
Mỗi lần push, GitHub tự chạy test của các service bị thay đổi; khi merge vào `main` thì tự build và push image lên GitHub Container Registry.

### 📚 Hiểu khái niệm
- **CI là gì, giải quyết vấn đề gì?** ("trên máy em chạy được mà")
- Workflow, job, step, runner, artifact, cache.
- **Path filter:** monorepo 10 service, vì sao không nên build lại cả 10 khi chỉ sửa `sales-service`?
- **Matrix build:** một job định nghĩa, chạy cho nhiều service.
- **Service containers** trong Actions: chạy MongoDB cho test của `chat-service` và `notification-service`.
- **Image tag:** vì sao dùng `git SHA` thay vì chỉ `latest`? (để rollback được)
- **Secrets** trong GitHub: `GITHUB_TOKEN` và secret do mình tự tạo khác nhau thế nào?

### 🛠 Thực hành trên project
1. Tạo `.github/workflows/ci.yml`:
   - Dùng `dorny/paths-filter` để phát hiện service nào thay đổi.
   - Job PHP: `composer install` → `php artisan test` (identity, sales, eoffice, finance; catalog nếu có test).
   - Job .NET: `dotnet test` (purchase, inventory, chat).
   - Job Node: `npm ci && npm test` (notification) và `npm run lint && npm run build` (frontend).
   - Job Python: `pip install` → chạy `tests/smoke_tools.py` (ai-service).
2. Bật **cache** cho Composer, NuGet, npm, pip. So sánh thời gian chạy trước và sau.
3. Tạo `.github/workflows/build-images.yml`: khi push lên `main` thì build image và push `ghcr.io/ttung205/smart-erp-<service>:<sha>` và `:latest`. Dùng `docker/build-push-action` với cache `type=gha`.
4. Bật **branch protection** cho `main`: bắt buộc CI xanh mới được merge.
5. (Nâng cao) Thêm bước quét lỗ hổng image bằng **Trivy**.

### 💥 Phá thử
- Cố tình làm một test fail, mở PR, xác nhận không merge được.
- Xóa cache rồi chạy lại, so sánh thời gian.

### ✅ Tự kiểm tra
- [ ] Sửa 1 file trong `sales-service` thì chỉ job sales chạy
- [ ] Image trên GHCR có tag theo commit SHA
- [ ] Giải thích được vì sao build trên CI tốt hơn build trên server

### 📦 Sản phẩm
2 workflow; badge CI trong README; image của 10 service + frontend trên GHCR.

---

## Giai đoạn 2: AWS nền tảng + deploy thủ công (~10$)

### 🎯 Mục tiêu
Hệ thống chạy trên EC2, truy cập được qua domain có HTTPS. Mọi bước làm bằng tay trên Console để **hiểu từng mảnh**.

### 📚 Hiểu khái niệm
- **Region và Availability Zone:** vì sao chọn Singapore (`ap-southeast-1`)?
- **IAM:** root user, IAM user, role, policy. Nguyên tắc *least privilege*. Vì sao **không bao giờ** dùng root để làm việc hằng ngày?
- **VPC, subnet public/private, Internet Gateway, route table.**
- **Security Group** (stateful) và **NACL** (stateless).
- **EC2:** instance type, AMI, EBS (gp3), Elastic IP. Stop và Terminate khác nhau thế nào? Khi stop, bạn còn trả tiền cho thứ gì?
- **SSM Session Manager:** vào máy không cần SSH và không cần mở cổng 22.
- **Reverse proxy + TLS:** Let's Encrypt cấp chứng chỉ thế nào? Caddy/Nginx đứng trước Kong để làm gì?
- **Billing:** free plan, credit, AWS Budgets, Cost Explorer.

### 🛠 Thực hành trên project
1. **Bảo vệ tài khoản (làm đầu tiên):**
   - Bật MFA cho root, tạo IAM user cho bản thân, đăng nhập bằng user đó.
   - Tạo **AWS Budget** 20$/tháng, gửi cảnh báo email ở 50% và 80%. (Hoạt động này còn được thưởng credit.)
2. Tạo EC2 `m7i-flex.large` (2 vCPU, 8 GB) chạy Ubuntu 24.04, EBS gp3 30 GB, gắn IAM role `AmazonSSMManagedInstanceCore`.
3. Security Group: chỉ mở 80 và 443. **Không mở 22**, vào máy bằng Session Manager.
4. Cài Docker, kéo image từ GHCR (đã có từ giai đoạn 1), chạy:
   ```bash
   docker compose -f docker-compose.yml -f docker-compose.prod.yml pull
   docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
   ```
   Nếu muốn dùng image từ GHCR thay cho `build:`, viết thêm file override `docker-compose.ghcr.yml` để ghi đè `image:`.
5. Gắn Elastic IP; trỏ domain (domain miễn phí hoặc subdomain có sẵn) về IP đó.
6. Đặt Caddy trước gateway để tự động có HTTPS.
7. Chạy `scripts/powershell/smoke-test.ps1` (hoặc viết bản bash tương đương) để kiểm tra các route.
8. Học thói quen **stop máy sau mỗi buổi học**.

### 💥 Phá thử
- Xóa rule cổng 443 trong Security Group, xem trình duyệt báo lỗi gì, rồi thêm lại.
- Stop rồi start EC2: IP public có đổi không khi không dùng Elastic IP? Container có tự chạy lại không (`restart: unless-stopped`)?

### ✅ Tự kiểm tra
- [ ] Vẽ được sơ đồ VPC/subnet/SG của mình
- [ ] Giải thích được Security Group stateful nghĩa là gì
- [ ] Biết mỗi giờ mình đang tốn bao nhiêu tiền và vì sao
- [ ] Vào được máy mà không cần cổng 22

### 📦 Sản phẩm
Link HTTPS chạy được; tài liệu `docs/devops/aws-manual-setup.md` ghi lại từng bước bằng lời của mình.

---

## Giai đoạn 3: CD, tự động deploy + quản lý secret (~10$)

### 🎯 Mục tiêu
Merge vào `main` thì sau vài phút server tự chạy phiên bản mới. Không còn secret nằm trong file trên máy.

### 📚 Hiểu khái niệm
- **CI và CD** (Continuous Delivery so với Continuous Deployment).
- **OIDC giữa GitHub và AWS:** vì sao tốt hơn lưu `AWS_ACCESS_KEY_ID` trong GitHub Secrets? (không có khóa dài hạn để lộ)
- **SSM Parameter Store** (SecureString) và **Secrets Manager:** khác nhau thế nào, giá ra sao?
- **Chiến lược deploy:** recreate, rolling, blue/green. Với 1 máy và docker compose thì làm được kiểu nào?
- **Rollback:** nhờ tag theo SHA ở giai đoạn 1.
- **Database migration** khi deploy: chạy lúc nào, khi migrate lỗi thì làm gì?

### 🛠 Thực hành trên project
1. Tạo **OIDC identity provider** trên AWS và IAM role chỉ cho phép repo `ttung205/smart-erp`, branch `main` assume.
2. Đưa toàn bộ secret (`JWT_SECRET`, `INTERNAL_SERVICE_KEY`, `MYSQL_PASSWORD`, các `*_APP_KEY`…) vào **Parameter Store** dưới đường dẫn `/smart-erp/prod/`.
3. Viết script `scripts/deploy.sh` chạy trên EC2:
   - Lấy secret từ Parameter Store và tạo `.env`.
   - `docker compose pull` theo tag SHA mới.
   - Chạy migration.
   - `up -d`, chờ healthcheck, chạy smoke test.
   - Nếu smoke test fail thì tự quay về tag cũ.
4. Workflow `deploy.yml`: sau khi build image xong, gọi `aws ssm send-command` để chạy script trên EC2.
5. Thêm **environment `production`** trong GitHub, bắt buộc bấm duyệt tay trước khi deploy.

### 💥 Phá thử
- Deploy một phiên bản cố tình hỏng (ví dụ route health trả về 500), xác nhận rollback tự động chạy.
- Thu hồi quyền của IAM role, xem workflow báo lỗi gì.

### ✅ Tự kiểm tra
- [ ] Trong GitHub không còn AWS access key nào
- [ ] Rollback về phiên bản trước trong dưới 5 phút
- [ ] Giải thích được luồng: push → test → build → push image → deploy → smoke test

### 📦 Sản phẩm
Pipeline CI/CD hoàn chỉnh; tài liệu runbook "cách deploy và rollback".

---

## Giai đoạn 4: Terraform, Infrastructure as Code (~10$) ⭐

### 🎯 Mục tiêu
Toàn bộ hạ tầng AWS ở giai đoạn 2–3 được mô tả bằng code. `terraform apply` dựng lại từ đầu trong khoảng 10 phút, `terraform destroy` xóa sạch.

### 📚 Hiểu khái niệm
- **IaC giải quyết vấn đề gì?** (click tay không lặp lại được, không review được, quên mất mình đã tạo gì)
- Provider, resource, data source, variable, output, module.
- **State:** là gì, vì sao không commit vào Git, vì sao lưu ở **S3 + khóa** (S3 native locking hoặc DynamoDB)?
- `plan` và `apply`; **drift** (ai đó sửa tay trên Console).
- **user_data / cloud-init:** máy tự cài Docker khi khởi động.

### 🛠 Thực hành trên project
1. Tạo thư mục `infra/terraform/` với cấu trúc:
   ```
   infra/terraform/
   ├── backend.tf        # state lưu ở S3
   ├── providers.tf
   ├── variables.tf
   ├── network.tf        # VPC, subnet, IGW, route table
   ├── security.tf       # Security Group
   ├── iam.tf            # role cho EC2 (SSM), role OIDC cho GitHub
   ├── compute.tf        # EC2 + EBS + Elastic IP + user_data
   ├── ssm.tf            # Parameter Store (giá trị lấy từ biến, không ghi cứng)
   ├── budget.tf         # AWS Budget
   └── outputs.tf
   ```
2. **Xóa toàn bộ thứ đã tạo tay** ở giai đoạn 2, rồi dựng lại hoàn toàn bằng Terraform.
3. `user_data` tự cài Docker, kéo repo hoặc compose file, chạy `deploy.sh`.
4. Thêm job `terraform fmt -check` và `terraform plan` vào CI cho PR có sửa `infra/`.
5. Tập thói quen: học xong thì `terraform destroy`, buổi sau `apply` lại. **Đây là cách giữ credit tốt nhất.**

### 💥 Phá thử
- Sửa tay một rule Security Group trên Console, chạy `terraform plan` và quan sát drift.
- Xóa EC2 trên Console rồi `apply`: Terraform tự tạo lại.

### ✅ Tự kiểm tra
- [ ] `destroy` rồi `apply`, hệ thống chạy lại không cần thao tác tay
- [ ] Giải thích được state file chứa gì và vì sao phải bảo vệ nó
- [ ] Đọc `terraform plan` và biết thay đổi nào sẽ **xóa và tạo lại** tài nguyên

### 📦 Sản phẩm
Thư mục `infra/terraform/`; CI chạy `plan` cho PR hạ tầng.

---

## Giai đoạn 5: Dịch vụ managed của AWS (~15$)

### 🎯 Mục tiêu
Dùng thử các dịch vụ managed phổ biến, và **tự đánh giá được** khi nào nên dùng, khi nào nên tự host.

### 📚 Hiểu khái niệm
- **Managed và self-hosted:** trade-off về tiền, công vận hành, khả năng khôi phục, vendor lock-in.
- **S3:** bucket, object, storage class, lifecycle rule, presigned URL, Block Public Access.
- **RDS:** automated backup, snapshot, Multi-AZ, parameter group. Đắt hơn MySQL trong container bao nhiêu?
- **CloudWatch:** metrics, logs, alarms, agent.
- **ECR và GHCR:** khác nhau thế nào?

### 🛠 Thực hành trên project
1. **S3 cho file đính kèm:** `sales-service` có `AttachmentController` và bảng `sales_attachments`, chat cũng có tệp đính kèm. Chuyển disk Laravel sang `s3` (Flysystem), dùng presigned URL để tải xuống. EC2 truy cập S3 bằng IAM role, không dùng access key.
2. **S3 cho backup:** cron trên EC2 chạy `mysqldump` và `mongodump`, nén lại, đẩy lên S3. Thêm lifecycle rule tự xóa backup cũ hơn 14 ngày.
3. **RDS (thử nghiệm):** tạo `db.t4g.micro`, chuyển **một** schema (ví dụ `erp_catalog_db`) sang RDS. Đo độ trễ, thử restore từ snapshot, rồi **xóa** RDS.
4. **CloudWatch:** cài CloudWatch Agent, đẩy log Docker lên. Tạo alarm khi CPU > 80% hoặc ổ đĩa > 85%.
5. Viết bảng so sánh chi phí: tự host MySQL/RabbitMQ/Mongo và RDS/Amazon MQ/DocumentDB.

### 💥 Phá thử
- Xóa hẳn một bảng trong MySQL, rồi khôi phục từ bản backup trên S3. **Ghi lại thời gian khôi phục (RTO) và lượng dữ liệu mất (RPO).**

### ✅ Tự kiểm tra
- [ ] Bucket không public, ứng dụng vẫn đọc ghi được nhờ IAM role
- [ ] Đã restore thành công ít nhất 1 lần
- [ ] Trình bày được bảng chi phí managed và self-hosted kèm lập luận

### 📦 Sản phẩm
Backup tự động lên S3; tài liệu so sánh chi phí; runbook khôi phục dữ liệu.

---

## Giai đoạn 6: Vận hành, giám sát, chịu lỗi (~15$)

### 🎯 Mục tiêu
Biết hệ thống đang khỏe hay không mà không cần mở web; khi có sự cố thì tìm ra nguyên nhân nhanh bằng log, metric và correlation ID.

### 📚 Hiểu khái niệm
- **Ba trụ quan sát:** logs, metrics, traces.
- **SLI / SLO:** ví dụ "99% request API trả về trong dưới 500ms".
- **Golden signals:** latency, traffic, errors, saturation.
- **Alert tốt và alert gây nhiễu.**
- **Các cơ chế chịu lỗi có sẵn trong project:** outbox, retry queue, DLX, circuit breaker, idempotent consumer. Mỗi cơ chế bảo vệ trước loại lỗi nào?

### 🛠 Thực hành trên project
1. Bật profile `monitoring` (Grafana, Prometheus, Loki, Alloy). Nếu máy 8 GB thiếu RAM, cân nhắc chạy monitoring trên máy riêng hoặc thay bằng CloudWatch.
2. Đọc `infrastructure/monitoring/prometheus/alerts.yml`, hiểu từng rule, rồi thêm rule mới:
   - Số event `FAILED` trong outbox tăng.
   - Queue `erp.dead_letters` có message.
   - Service health check fail quá 2 phút.
3. Cấu hình Grafana gửi cảnh báo về email hoặc Telegram/Discord.
4. **Lần theo một request:** lấy `X-Correlation-ID` từ response, tìm trên Loki, đi qua Kong → sales → outbox → RabbitMQ → notification.
5. **Load test** bằng `k6`: tạo báo giá đồng thời, tìm nút cổ chai (gợi ý: số worker PHP-FPM trong `docker/php-fpm.conf`).
6. Viết **runbook sự cố** cho 3 tình huống: RabbitMQ sập, MySQL đầy ổ, một service bị OOM.

### 💥 Phá thử (chaos test)
| Thí nghiệm | Kỳ vọng | Quan sát gì |
|---|---|---|
| Stop RabbitMQ 5 phút khi đang tạo báo giá | Không mất event, gửi lại khi broker sống | `outbox_events`, log relay |
| Kill `notification-service` | Message nằm chờ trong queue, xử lý khi service sống lại | RabbitMQ UI |
| Cho consumer lỗi liên tục | Retry 5 lần rồi chuyển vào `erp.dead_letters` | Queue retry/DLX, `replay-dead-letters` |
| Stop `inventory-service` khi đang lập hóa đơn | Circuit breaker mở, Sales báo lỗi rõ ràng | `GET /api/v1/sales/health` |
| Gửi lặp cùng một event | Consumer bỏ qua, không tạo thông báo trùng | MongoDB, `processed_events` |
| Đầy ổ đĩa (`fallocate`) | Alert bắn ra trước khi DB chết | Grafana/CloudWatch |

Phần này rất đáng đưa vào báo cáo đồ án: mỗi thí nghiệm ghi rõ giả thuyết, cách làm, kết quả và ảnh chụp.

### ✅ Tự kiểm tra
- [ ] Nhận cảnh báo khi một service chết, trước khi tự phát hiện
- [ ] Tìm được log của một request cụ thể trong dưới 2 phút
- [ ] Giải thích được mỗi cơ chế chịu lỗi bảo vệ trước loại lỗi nào, có bằng chứng thí nghiệm

### 📦 Sản phẩm
Dashboard và alert; 3 runbook sự cố; báo cáo chaos test.

---

## Giai đoạn 7 (tùy chọn): Kubernetes (local hoặc Oracle Free, 0$)

> Không dùng EKS: phí control plane khoảng 73$/tháng, sẽ đốt hết credit.

### 🎯 Mục tiêu
Chạy `smart-erp` trên Kubernetes, hiểu K8s giải quyết vấn đề gì mà docker compose không làm được.

### 📚 Hiểu khái niệm
- Pod, Deployment, Service, Ingress, ConfigMap, Secret, PersistentVolume.
- Liveness probe và readiness probe (so với healthcheck trong compose).
- Scale ngang và HPA; vì sao relay outbox **không được** scale lên 2 bản (khóa `flock` chỉ hoạt động trong một máy).
- Helm chart; GitOps (Argo CD).

### 🛠 Thực hành
1. Cài `k3d` hoặc `kind` ở local.
2. Viết manifest cho 2–3 service trước (identity, catalog, sales), sau đó mới làm hết.
3. Gom lại thành **Helm chart**, mỗi môi trường một file values.
4. (Nâng cao) Argo CD tự đồng bộ từ Git.

### ✅ Tự kiểm tra
- [ ] Kill một pod, K8s tự tạo lại
- [ ] Giải thích được vì sao relay chỉ nên chạy 1 replica và cách K8s đảm bảo điều đó

---

## Phụ lục A: Quản lý chi phí

| Hạng mục | Giá ước lượng (Singapore) | Ghi chú |
|---|---|---|
| EC2 `m7i-flex.large` | ~0,12$/giờ | Chỉ tính khi máy đang chạy |
| EBS gp3 30 GB | ~3$/tháng | Tính cả khi máy stop |
| Public IPv4 / Elastic IP | ~0,005$/giờ (~3,6$/tháng) | Tính cả khi máy stop |
| S3 (vài GB) | dưới 1$/tháng | |
| RDS `db.t4g.micro` | ~0,02–0,03$/giờ | Chỉ bật khi thử nghiệm, xong thì xóa |
| Data transfer ra internet | có phí theo GB | Demo nhỏ thì không đáng kể |

*Giá thay đổi theo thời gian, kiểm tra lại trên trang AWS Pricing trước khi dùng.*

**Quy tắc giữ tiền:**
- [ ] Đã tạo AWS Budget ngay ngày đầu
- [ ] Cuối mỗi buổi học: stop EC2 hoặc `terraform destroy`
- [ ] Mỗi tuần mở **Cost Explorer** 1 lần, xem tiền đi đâu
- [ ] Kiểm tra mọi region bằng **EC2 Global View** (hay quên tài nguyên ở region khác)
- [ ] Trước khi hết 6 tháng free plan: backup dữ liệu ra ngoài

**Mẹo:** tài khoản free plan được thưởng thêm 20$ mỗi hoạt động (EC2, RDS, Lambda, Bedrock, AWS Budgets), tối đa thêm 100$. Lộ trình này đã đi qua EC2, RDS và Budgets; thử thêm một hàm Lambda nhỏ là nhận thêm credit.

---

## Phụ lục B: Mẫu nhật ký sự cố

Lưu mỗi sự cố thành một mục trong `docs/devops/journal.md`:

```markdown
### [Ngày] Tiêu đề ngắn của lỗi

- **Hiện tượng:** thấy gì (thông báo lỗi, log, ảnh chụp)
- **Bối cảnh:** đang làm gì, ở giai đoạn nào, thay đổi gì gần nhất
- **Giả thuyết:** đã nghĩ nguyên nhân là gì (kể cả giả thuyết sai)
- **Cách tìm ra:** lệnh và log đã dùng để thu hẹp vấn đề
- **Nguyên nhân gốc:** thật sự là do đâu
- **Cách sửa:**
- **Bài học / cách phòng tránh lần sau:**
```

Cách ghi này tập cho bạn tư duy **debug có hệ thống**, kỹ năng quan trọng nhất của DevOps. Nó còn cho bạn sẵn câu trả lời khi phỏng vấn hỏi "kể một lần bạn xử lý sự cố".

---

## Phụ lục C: Quy trình tìm lỗi chung

Khi có gì đó không chạy, đi lần lượt từ ngoài vào trong:

```
1. DNS        → nslookup domain có ra đúng IP không?
2. Mạng       → Security Group mở cổng chưa? curl từ ngoài vào được không?
3. TLS        → chứng chỉ còn hạn, đúng domain không?
4. Gateway    → Kong có route này không? log Kong báo gì?
5. Container  → docker compose ps: có healthy không? có restart liên tục không?
6. Ứng dụng   → docker compose logs <service>: lỗi gì?
7. Phụ thuộc  → DB, RabbitMQ, service khác có sống không?
8. Tài nguyên → docker stats / df -h / free -m: hết RAM, hết ổ đĩa?
```

Lệnh hay dùng:
```bash
docker compose ps
docker compose logs -f --tail=100 sales-service
docker compose exec sales-service sh
docker stats --no-stream
df -h && free -m
curl -I https://<domain>/api/v1/sales/health
sudo journalctl -u docker --since "10 min ago"
```

---

## Phụ lục D: Tài liệu học

| Chủ đề | Nguồn |
|---|---|
| Docker | Tài liệu chính thức `docs.docker.com` (mục Build, Compose) |
| GitHub Actions | `docs.github.com/actions` |
| AWS cơ bản | AWS Skill Builder (miễn phí), khóa *AWS Cloud Practitioner Essentials* |
| Terraform | `developer.hashicorp.com/terraform/tutorials/aws-get-started` |
| Quan sát hệ thống | Google SRE Book, chương về Monitoring (đọc miễn phí online) |
| Kubernetes | `kubernetes.io/docs/tutorials`, sau đó Helm docs |
| Chứng chỉ (nếu muốn) | AWS Cloud Practitioner → AWS Solutions Architect Associate; HashiCorp Terraform Associate |

---

## Bảng theo dõi tiến độ

| Giai đoạn | Bắt đầu | Hoàn thành | Chi phí thực tế | Ghi chú |
|---|---|---|---|---|
| 0. Docker & hiểu hệ thống | | | 0$ | |
| 1. CI: GitHub Actions + GHCR | | | 0$ | |
| 2. AWS nền tảng + deploy thủ công | | | | |
| 3. CD + secret | | | | |
| 4. Terraform | | | | |
| 5. Dịch vụ managed | | | | |
| 6. Vận hành & chaos test | | | | |
| 7. Kubernetes (tùy chọn) | | | 0$ | |

**Đích đến:** khi xong giai đoạn 6, bạn có thể nói trong CV hoặc khi bảo vệ đồ án:
> "Tôi thiết kế và vận hành hệ thống ERP 10 microservice trên AWS, hạ tầng quản lý bằng Terraform, CI/CD bằng GitHub Actions với OIDC, có giám sát, cảnh báo, backup tự động, và đã kiểm chứng khả năng chịu lỗi bằng các thí nghiệm chaos."
