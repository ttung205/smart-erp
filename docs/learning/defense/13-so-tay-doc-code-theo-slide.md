# Bài 13. Sổ tay đọc code theo từng slide (demo trên AWS)

> Dùng cho buổi bảo vệ: **bất kỳ slide nào bị hỏi, bạn biết mở file nào, chạy lệnh nào trên AWS để chứng minh, và giải thích được vì sao.**
> Mỗi mục có 4 phần: **Cơ chế** (nó chạy thế nào) · **Code** (mở ở đâu) · **Demo** (lệnh chứng minh trên AWS) · **Hỏi hay gặp**.
> Số dòng lấy ngày 14/11/2026; nếu file đã đổi thì tìm lại bằng tên hàm. Demo trên máy dev (Docker Compose) xem [bài 12](12-demo-chiu-loi-4-kich-ban.md).

## 0. Chuẩn bị môi trường AWS (đọc trước khi chạy bất kỳ lệnh nào)

### 0.1. Ba nơi bạn gõ lệnh

| Nơi | Dùng cho | Cách mở |
|---|---|---|
| **Máy cá nhân / CloudShell** | Gọi site thật qua ALB (`https://erp.transportbooking.xyz`), lệnh `aws ...` | Terminal; CloudShell là biểu tượng `>_` trên thanh console |
| **Máy A** (`smart-erp-app-a`) | Lệnh `docker compose ...` cho RabbitMQ, relay, scheduler, backup, và các service ứng dụng | EC2 → chọn máy → Connect → **Session Manager**; rồi `sudo -iu ubuntu` và `cd /opt/smart-erp` |
| **Máy ASG** (tên `erp-app-asg`) | Lệnh `docker compose ...` cho các service ứng dụng ở máy kia | Cũng Session Manager (xem ID máy: `aws autoscaling describe-auto-scaling-groups --auto-scaling-group-names erp-app-asg --query 'AutoScalingGroups[0].Instances[].InstanceId' --output text`) |

Trên máy chủ chỉ cần gõ `docker compose ...` vì `COMPOSE_FILE` đã nằm trong `.env`. Không có container MySQL (database ở RDS).

### 0.2. Cái gì chạy ở đâu

| Thành phần | Máy A | Máy ASG |
|---|---|---|
| 8 service ứng dụng (identity, catalog, purchase, inventory, sales, eoffice, finance, ai) + nginx + Kong | có | có |
| RabbitMQ, Mongo, chat, notification | **chỉ A** | không |
| Relay outbox (sales, catalog, eoffice), `sales-scheduler` (đối soát giữ hàng) | **chỉ A** | không |
| Backup/recovery, Grafana, Loki, Prometheus | **chỉ A** | Alloy (đẩy log về A) |
| MySQL | RDS dùng chung | RDS dùng chung |

Hệ quả cho demo: request của người dùng qua ALB có thể vào **bất kỳ máy** nào; muốn kiểm soát máy nào xử lý, gọi thẳng cổng service từ trong máy (ví dụ `curl localhost:8005/api/health` trên máy A là sales của máy A).

### 0.3. Ba thứ cần chuẩn bị sẵn

```bash
# (a) Máy cá nhân: token đăng nhập. Đăng nhập web, mở DevTools > Network, chọn một request API, copy giá trị sau "Bearer ".
export TOKEN='eyJ...'          # hết hạn sau 24 giờ
export URL=https://erp.transportbooking.xyz

# (b) Máy A: hàm chạy SQL chỉ đọc trên RDS (dùng container backup, tài khoản erp_backup chỉ có quyền SELECT)
sql() { docker compose run --rm -T --no-deps --entrypoint bash backup -c 'MYSQL_PWD="$MYSQL_BACKUP_PASSWORD" mysql -h"$MYSQL_HOST" -u"$MYSQL_BACKUP_USER" -e "$0"' "$1"; }
# thử: sql "SELECT COUNT(*) FROM erp_sales_db.outbox_events"

# (c) Máy A: trạng thái ALB nội bộ đã bật chưa?
docker compose exec sales-service printenv INVENTORY_SERVICE_URL
#  -> http://inventory-service:8004          : CHƯA bật (lời gọi giữa service đi theo Docker DNS cùng máy)
#  -> http://internal-erp-internal-....:8004 : ĐÃ bật (đi qua ALB nội bộ)
```
Kết quả (c) quyết định demo ở slide 14 (circuit breaker) và slide 5 (failover nội bộ); mỗi mục ghi rõ "trường hợp A/B".

**Đã kiểm chứng ngày 14/11/2026:** hàm `sql`, lệnh `backup list`, `rabbitmqctl list_queues`, `curl localhost:8005/api/health` (có trường `circuit_breakers`) và đoạn `tinker` của slide 14 đều chạy đúng trên máy A. Cả hai máy đang có `INTERNAL_LB_HOST` (trường hợp B), máy A chạy `sha-1e7ffa9`. Sau khi gắn `tg-int-*` vào ASG (14/11), 7 nhóm nội bộ (trừ `notification`) có cả máy A và máy ASG ở trạng thái `healthy`, nên Demo B ở slide 5 có chỗ để chuyển sang. Nếu bạn chạy các lệnh trong một script bằng cách đưa qua đầu vào chuẩn (ví dụ SSM Run Command), thêm `</dev/null` sau mỗi lệnh `docker compose exec/run`, nếu không nó nuốt phần còn lại của script.

**Nếu bạn gõ lệnh `aws` trên Windows bằng Git Bash:** Git Bash tự đổi đối số bắt đầu bằng `/` thành đường dẫn Windows (ví dụ `/smart-erp/prod/image-tag` thành `C:/Program Files/Git/smart-erp/...`, gây lỗi `ParameterNotFound`). Đặt `export MSYS_NO_PATHCONV=1` trước, hoặc dùng CloudShell. Tài khoản `dev-local` trên máy bạn chỉ có quyền **đọc** (không gắn target group vào ASG, không liệt kê S3); các thao tác ghi dùng CloudShell hoặc console.

### 0.4. Quy tắc an toàn khi demo trên production

- **Không bao giờ chạy `restore --yes`** (nó xóa dữ liệu thật). Chỉ chạy `restore` không có `--yes` (chỉ in kế hoạch) và `verify`.
- Tắt service nào thì ghi lại để **bật lại ngay sau demo**; cuối buổi chạy `docker compose ps` ở cả hai máy, mọi thứ phải `(healthy)`.
- Đừng demo khi workflow **Deploy production** đang chạy (máy đang được khởi động lại từng cái).
- Thao tác ghi vào tồn kho (slide 11) dùng **một sản phẩm demo** và dọn lại bằng lệnh nhả hàng đã ghi sẵn.
- Test rate limit chỉ khóa IP của bạn khoảng 1 phút (Kong đọc IP thật từ `X-Forwarded-For`, cấu hình ở `KONG_REAL_IP_HEADER` trong [docker-compose.yml:795-797](../../../docker-compose.yml#L795-L797)); người khác không bị ảnh hưởng.

### 0.5. Cách học (90 phút)

1. 10 phút: đọc lướt phần "Cơ chế" của 18 mục.
2. 40 phút: chọn 5 cơ chế bạn sợ bị hỏi nhất (gợi ý: chống bán âm, outbox, circuit breaker, JWT, ALB health check), mở đúng file ở mục "Code" và đọc từng dòng.
3. 30 phút: chạy các lệnh "Demo" của 5 cơ chế đó trên AWS ít nhất một lần.
4. 10 phút: tự trả lời "Hỏi hay gặp" mà không nhìn đáp án.

---

## Slide 1-2. Bài toán và vì sao phân tán

**Cơ chế.** Chuỗi cung ứng: mua hàng → nhập kho → tồn kho → báo giá/đơn bán → giữ hàng → giao → xuất kho → công nợ. Mỗi miền là một service với database riêng. Cái giá của phân tán là 4 vấn đề: không có transaction chung, mạng đứt và message trùng, máy hoặc service chết, dữ liệu nằm nhiều nơi. Mỗi chương sau giải một vấn đề.

**Code.** Danh sách service và công nghệ: [docker-compose.yml](../../../docker-compose.yml) (mỗi service là một khối `services:`). Luồng nghiệp vụ chuẩn: mục 1 của [CLAUDE.md](../../../CLAUDE.md).

**Demo (AWS).** Mở `https://erp.transportbooking.xyz`, đi một vòng: đơn mua, nhập kho, xem tồn, báo giá, đơn bán, chứng từ bán (giữ hàng), xuất kho. Trên máy A chạy kiểm tra toàn chuỗi:
```bash
BASE_URL=http://localhost scripts/smoke-test.sh      # 10 đường /health qua nginx → Kong → service
```

**Hỏi hay gặp.**
- *Vì sao không monolith?* Mỗi miền độc lập, công nghệ phù hợp từng miền (kho dùng .NET vì transaction, notification dùng Node vì I/O), một service hỏng không kéo cả hệ thống. Đổi lại phải giải 4 vấn đề trên.
- *Có microservices thật không hay chỉ chia thư mục?* Mỗi service chạy container riêng, database riêng, tài khoản MySQL riêng, giao tiếp qua mạng.

---

## Slide 3. Kiến trúc tổng thể

**Cơ chế.** Trình duyệt chỉ nói chuyện với **Kong gateway**. Kong định tuyến theo tiền tố đường dẫn tới đúng service, sinh `X-Correlation-ID`, giới hạn tốc độ. Mỗi service theo Clean Architecture: Controller mỏng, logic ở Application/Domain, gọi service khác ở Infrastructure. Trên AWS, đường đi là ALB → nginx (cổng 80) → Kong → service.

**Code.**
- Định tuyến Kong: [kong.yml:4-121](../../../infrastructure/kong/kong.yml#L4-L121) (mỗi `service` có `url` + `paths`; `strip_path: true` với Laravel vì service nằm dưới `/api`).
- Plugin chung (rate limit, correlation id, CORS): [kong.yml:137-160](../../../infrastructure/kong/kong.yml#L137-L160).
- .NET bốn project: `services/inventory-service/src/Inventory.Api`, `.Application`, `.Domain`, `.Infrastructure`.
- Laravel: `app/Http/Controllers` (mỏng), `app/Services`, `app/Support`, `app/Infrastructure/Clients` (typed client gọi service khác, ví dụ [InternalHttp.php](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php)).
- Database riêng: mỗi user chỉ có quyền trên schema của mình, [03-create-service-users.sh:48-50](../../../infrastructure/mysql/init/03-create-service-users.sh#L48-L50).

**Demo (AWS).**
```bash
# máy cá nhân: Kong gắn X-Correlation-ID vào mọi phản hồi
curl -sI $URL/api/v1/catalog/health | grep -i x-correlation-id

# máy A: sales chỉ biết database của nó
docker compose exec sales-service printenv DB_DATABASE DB_USERNAME     # erp_sales_db / erp_sales
```
Mở file [03-create-service-users.sh](../../../infrastructure/mysql/init/03-create-service-users.sh) cho thầy xem: `GRANT ALL PRIVILEGES ON <schema>.*` chỉ trên schema của chính service.

**Hỏi hay gặp.**
- *Gateway chết thì sao?* Là điểm lỗi đơn trên mỗi máy; có hai máy sau ALB nên ALB chuyển sang máy kia (slide 13).
- *Vì sao không JOIN chéo database?* Phá tính độc lập, không scale riêng được. Thay bằng ID tham chiếu, snapshot vào chứng từ, hoặc gọi REST lúc thực thi ([02-database.md](02-database.md)).

---

## Slide 4. Giám sát, chịu lỗi, lưu trữ

**Cơ chế.** Alloy chạy trên mọi máy, đọc log container rồi đẩy về Loki (ở máy A); Prometheus thu số liệu; Grafana hiển thị. Nhờ `correlation_id` có trong mọi dòng log, tra được một request đã đi qua những service nào.

**Code.** [config.alloy](../../../infrastructure/monitoring/alloy/config.alloy) (nhãn `service` gắn ở dòng 20-21), [prometheus.yml](../../../infrastructure/monitoring/prometheus/prometheus.yml), [alerts.yml](../../../infrastructure/monitoring/prometheus/alerts.yml), dashboard ở `infrastructure/monitoring/grafana/dashboards`. Định dạng log Kong: `KONG_NGINX_HTTP_LOG_FORMAT` trong [docker-compose.yml](../../../docker-compose.yml).

**Demo (AWS).**
1. Máy cá nhân: `curl -si $URL/api/v1/catalog/health | grep -i x-correlation-id` → lấy `<id>`.
2. Mở `https://erp.transportbooking.xyz/grafana/` (đăng nhập bằng `GRAFANA_ADMIN_PASSWORD`; đường này ALB gửi vào máy A, nhóm `tg-erp-grafana`), vào Explore → nguồn Loki, chạy `{service=~".+"} |= "<id>"` để thấy dòng log của request đó ở Kong và service.
3. Mở dashboard để chỉ số request và độ trễ theo service.

**Vai trò từng thành phần (dữ liệu thật của hệ thống).**

| Thành phần | Làm gì | Cấu hình thật |
|---|---|---|
| **Alloy** (tác nhân thu thập) | Tìm container qua Docker socket (làm mới mỗi 10 s), đọc log stdout, gắn nhãn `service`, `container`, `instance` (máy nguồn), tách `correlation_id` thành *structured metadata*, bỏ log cũ hơn 1 giờ, rồi đẩy sang Loki. **Chạy ở mọi máy** (A và ASG) | [config.alloy](../../../infrastructure/monitoring/alloy/config.alloy); máy ASG đặt `LOKI_PUSH_URL` trỏ về Loki ở máy A |
| **Loki** (kho log) | Lưu log, chỉ đánh chỉ mục theo **nhãn** (không theo nội dung) nên nhẹ; truy vấn bằng LogQL; giữ 7 ngày. **Chỉ ở máy A** | [loki/config.yml](../../../infrastructure/monitoring/loki/config.yml) (`retention_period: 168h`) |
| **Prometheus** (kho số liệu) | **Kéo** (scrape) số liệu từ các điểm `/metrics` mỗi 15 s, lưu thành chuỗi theo thời gian, tính `rate`, `histogram_quantile` bằng PromQL, và đánh giá luật cảnh báo. Chỉ có 3 nguồn: **Kong** (số request, mã trạng thái, độ trễ theo service), **RabbitMQ** (số message từng hàng đợi), chính nó. **Chỉ ở máy A** | [prometheus.yml](../../../infrastructure/monitoring/prometheus/prometheus.yml), [alerts.yml](../../../infrastructure/monitoring/prometheus/alerts.yml) (3 luật: `DeadLetterNotEmpty`, `ServiceHigh5xx` khi lỗi 5xx vượt 5% trong 5 phút, `ScrapeTargetDown`) |
| **Grafana** (giao diện) | **Không lưu dữ liệu.** Chỉ kết nối tới hai nguồn (Prometheus và Loki) để vẽ biểu đồ, xem log (Explore) và gửi cảnh báo. Có 3 dashboard cài sẵn: *ERP Overview*, *Logs*, *RabbitMQ* | [grafana/provisioning](../../../infrastructure/monitoring/grafana/provisioning), [grafana/dashboards](../../../infrastructure/monitoring/grafana/dashboards) |

Hiểu ngắn: **Alloy đẩy log → Loki; Prometheus tự đi kéo số liệu; Grafana đọc cả hai để hiển thị.** Log trả lời "chuyện gì đã xảy ra với request này" (chi tiết từng sự kiện); số liệu trả lời "có bao nhiêu lỗi mỗi giây, chậm cỡ nào" (con số tổng hợp). Hệ thống **không có tracing** (Tempo/Jaeger): `correlation_id` là cách thay thế thô sơ.

**Hai giới hạn cần biết khi demo:**
- **Prometheus chỉ kéo Kong của máy A** (`gateway:8100`), nên biểu đồ chỉ phản ánh request đã đi qua máy A; ALB chia khoảng nửa số request sang máy ASG. **Log thì đủ cả hai máy** (Alloy ở mọi máy, lọc theo nhãn `instance`).
- **Dòng log của Laravel không mang `correlation_id`**, và [InternalHttp.php](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php) (đường sales gọi inventory) **không chuyển tiếp** `X-Correlation-ID` (chỉ [ServiceClient.php:49](../../../services/sales-service/app/Infrastructure/Clients/ServiceClient.php#L49) làm việc đó). Vì thế id truy được tới **Kong** (dòng log có `correlation_id=...`), nhưng không đi tiếp xuyên từ sales sang inventory; nối các dòng của sales bằng **thời gian và nhãn `instance`**.

**Kịch bản: tắt inventory ở mọi máy, lưu chứng từ bán rồi truy vết.**

Dự đoán từ code ([InvoiceController.php:283-312](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L283-L312)): giữ hàng nằm **trong transaction** lập chứng từ; không giữ được thì **rollback, chứng từ không được tạo** (số chứng từ đã cấp bị bỏ, không bao giờ cấp lại). Mã lỗi: khi ALB nội bộ đang bật và inventory chết ở cả hai máy, sales nhận phản hồi **HTTP 502** từ ALB nội bộ (đã đo) nên lỗi là `INVENTORY_ERROR` / HTTP **502**; mỗi lần Lưu còn gọi thêm lệnh nhả hàng (cũng lỗi), nên sau vài lần mạch của sales mở và lỗi đổi thành `DEPENDENCY_UNAVAILABLE` / HTTP **503** (mỗi máy tự đếm). Nội dung màn hình có thể khác một chút: xem chính xác ở tab Network.

1. **Chuẩn bị:** mở 3 tab: web, `https://erp.transportbooking.xyz/grafana/` (đăng nhập), và DevTools → Network ở tab web (bật *Preserve log*).
2. **Tắt inventory ở cả hai máy** (Session Manager vào từng máy, thư mục `/opt/smart-erp`): `docker compose stop inventory-service`. ID máy: máy A `i-0f3373bf8da54d26e`; máy ASG xem bằng `aws autoscaling describe-auto-scaling-groups --auto-scaling-group-names erp-app-asg --query 'AutoScalingGroups[0].Instances[].InstanceId' --output text`.
3. **Trên web:** vào Tạo chứng từ bán, điền đủ rồi bấm **Lưu**. Lặp lại 4 đến 6 lần (một phần vào máy ASG; mạch mở sau vài lần). Ở Network chọn request `POST /api/v1/sales/invoices`: xem **Status** (502 hoặc 503), phần **Response** (`error.code`, `error.message`) và **Response Headers → `X-Correlation-ID`** (copy giá trị này).
4. **Grafana, dashboard *ERP Overview*:** các biểu đồ *Lỗi 5xx/s theo service* (đường `sales-service` tăng), *Mã trạng thái HTTP* (xuất hiện 502/503), *Tỉ lệ lỗi 5xx*. Số liệu trễ khoảng 30 giây đến 1 phút (15 s scrape, cửa sổ 5 phút). Cảnh báo `ServiceHigh5xx` chỉ bắn sau 5 phút liên tục vượt 5%.
5. **Grafana, Explore, nguồn Loki:** tìm đúng request bằng id:
   ```logql
   {service="gateway"} |= "<X-Correlation-ID đã copy>"
   ```
   Dòng Kong cho biết: phương thức và đường dẫn, **mã trạng thái**, thời gian xử lý, địa chỉ upstream (`upstream=172.18.0.x:8005`) và nhãn **`instance`** (máy nào xử lý; `ip-172-31-40-5` là máy A). Đã thử lọc bằng văn bản (`|= "id"`) và bằng metadata (`| correlation_id="id"`), cả hai đều chạy.
6. **Log của sales trên đúng máy đó, quanh thời điểm ấy** (không có id nên lọc theo máy và từ khóa):
   ```logql
   {service="sales-service", instance="ip-172-31-40-5"} |~ "CircuitBreaker|Inventory"
   ```
   Chỉnh khoảng thời gian về vài phút gần đây. Sẽ thấy các dòng như `[CircuitBreaker] inventory-service: mở mạch 30s sau 5 lỗi liên tiếp`.
7. **Bằng chứng inventory đã chết:** `{service="inventory-service"}` không có dòng mới trong khoảng đó; `{service="gateway"} |~ "inventory/health"` ra các dòng `502`.
8. **Dashboard *Logs*:** chọn *Service* = `gateway` hoặc `sales-service`, dán id vào ô *Tìm (correlation id / từ khoá)*; biểu đồ *Số dòng log lỗi theo service* cho thấy tập trung lỗi.
9. **Xác nhận chứng từ không được tạo:** danh sách chứng từ không có chứng từ mới; hoặc `sql "SELECT invoice_number, created_at FROM erp_sales_db.invoices ORDER BY id DESC LIMIT 3"` (nếu tên bảng khác, xem `\dt`). Số chứng từ lần sau bị nhảy cách (số cũ đã bị "đốt").
10. **Bật lại ngay:** `docker compose start inventory-service` ở **cả hai** máy; đợi ~15 s healthy; bấm Lưu lần nữa thì thành công (sau tối đa 30 s mạch tự đóng, log hiện `đóng mạch, service đã phục hồi`).

Bạn nên **chạy thử một lần trước buổi bảo vệ**: tôi đã kiểm chứng từng câu truy vấn Loki, từng dashboard và đường đi của lỗi trong code, nhưng chưa bấm Lưu thật trên web với inventory tắt.

**Hỏi hay gặp.** *Vì sao Loki chứ không ELK?* Nhẹ hơn nhiều, chỉ cần đọc log theo nhãn, phù hợp cỡ đồ án. *Prometheus và Loki khác nhau thế nào?* Prometheus lưu **số liệu** (kéo định kỳ, tính tổng hợp), Loki lưu **log** (đẩy vào, đọc chi tiết). *Vì sao không thấy id ở dòng log của sales?* Laravel chưa ghi id vào dòng log và đường gọi inventory chưa chuyển tiếp id (xem giới hạn ở trên); hướng nâng cấp là thêm middleware `Log::withContext` và gắn header trong `InternalHttp`.

---

## Slide 5. Triển khai AWS (slide dễ bị hỏi sâu nhất)

**Cơ chế.** Hai load balancer cho hai con đường:
- **erp-alb (ngoài)**: người dùng vào. Mỗi service một target group (`tg-erp-inventory`, `tg-erp-sales`...) trỏ vào cổng 80 của máy (nginx → Kong), health check là `/api/v1/<service>/health` (riêng sales dùng `/health/live`). Service chết ở máy nào thì chỉ máy đó bị loại khỏi nhóm của service đó. Chat, thông báo, Grafana chỉ có máy A.
- **erp-internal (nội bộ)**: lời gọi giữa service. Mỗi service một listener trùng số cổng (8001 đến 8008), trỏ thẳng vào cổng service, không qua Kong (vì endpoint `/internal/*` không được đi qua gateway). Bật bằng biến `INTERNAL_LB_HOST`; không đặt thì giữ Docker DNS cùng máy.
- **ASG** dùng health check **EC2** (không phải ELB): với ELB check, ASG thay *cả máy* khi bất kỳ target group nào unhealthy (đã xảy ra thật hai lần, xem lịch sử ASG).
- Sales có `/health/live` riêng cho ALB vì `/health` của sales kiểm tra cả inventory và trả 503 khi inventory chết.

**Code.**
- URL nội bộ đổi theo biến: [docker-compose.ha.yml](../../../docker-compose.ha.yml) và [docker-compose.ha-a.yml](../../../docker-compose.ha-a.yml) (`${INTERNAL_LB_HOST:-inventory-service}`).
- Health: [HealthController.php](../../../services/sales-service/app/Http/Controllers/HealthController.php) (`live()` rồi `check()` trả 503 khi `$healthy` sai); inventory: [HealthController.cs](../../../services/inventory-service/src/Inventory.Api/Controllers/HealthController.cs).
- Health check ALB ngoài ở `tg-erp-web` chỉ tới Kong: [nginx.conf](../../../frontend/nginx.conf) (`location = /health`).
- Máy ASG mới tự dựng: [asg-boot.sh](../../../scripts/aws/asg-boot.sh) (lấy `.env` và thẻ image từ SSM).

**Demo A: một service chết ở một máy (đã chạy thật ngày 13/11/2026: lỗi 31 giây).**
1. Máy cá nhân, vòng lặp đo:
   ```bash
   while true; do echo "$(date +%T) $(curl -s -o /dev/null -w '%{http_code} %{time_total}s' $URL/api/v1/inventory/health)"; sleep 1; done
   ```
2. Máy A: `docker compose stop inventory-service`.
3. Kết quả đo ngày 13/11: 504 (15 s) và 502 (14 s), từ 17:28:43 trở đi 200 liên tục. Đo lại ngày 14/11: tắt lúc 03:17:00 → chỉ 1 request lỗi (504, 15 s), một request chậm 5 s, máy A bị đánh dấu `unhealthy` trước 03:17:19 (~20 s), 200 liên tục sau đó; bật lại 03:18:22 → `healthy` lúc 03:18:35. Mỗi request lỗi treo ~15 s vì Kong thử kết nối 3 lần × 5 s ([kong.yml:47-50](../../../infrastructure/kong/kong.yml#L47-L50)).
4. Chỉ cho thầy tab Targets: EC2 → Target Groups → `tg-erp-inventory` → máy A chuyển `unhealthy`. Hoặc CloudShell:
   ```bash
   aws elbv2 describe-target-health --target-group-arn $(aws elbv2 describe-target-groups --names tg-erp-inventory --query 'TargetGroups[0].TargetGroupArn' --output text) --query 'TargetHealthDescriptions[].[Target.Id,TargetHealth.State]' --output text
   ```
5. Bật lại: `docker compose start inventory-service` (khoảng 20 s sau máy A `healthy` trở lại).

**Điều kiện để Demo B chạy đúng:** các `tg-int-*` phải có **cả máy A lẫn máy ASG** là `healthy`. Kiểm tra: `aws elbv2 describe-target-health --target-group-arn $(aws elbv2 describe-target-groups --names tg-int-inventory --query 'TargetGroups[0].TargetGroupArn' --output text) --query 'TargetHealthDescriptions[].[Target.Id,TargetHealth.State]' --output text`. Nếu chỉ thấy máy A, ALB nội bộ không còn chỗ nào để chuyển sang và Demo B sẽ **thất bại** (503). Máy ASG mới do ASG dựng chỉ tự vào nhóm nếu `tg-int-*` đã được gắn vào ASG.

**Demo B: lời gọi giữa service chuyển sang máy khác (chỉ khi `INTERNAL_LB_HOST` đã bật, xem mục 0.3c). Đã chạy thật ngày 14/11, không cần token.** Máy A, một phiên Session Manager:
```bash
HOST=$(grep '^INTERNAL_LB_HOST=' .env | cut -d= -f2)
docker compose stop inventory-service
for i in $(seq 1 25); do echo "$(date -u +%T) noi_bo=$(curl -s -o /dev/null -w '%{http_code}' $HOST:8004/health)  sales_A=$(curl -s -o /dev/null -w '%{http_code}' localhost:8005/api/health)"; sleep 2; done
docker compose start inventory-service
```
Kết quả đo: lúc đầu xen kẽ `502` (đúng nửa số request trúng inventory máy A đã tắt) và `200` (trúng máy ASG); sau **~21 giây** ALB nội bộ loại máy A và chỉ còn `200`; `sales_A` (health của sales ở máy A, nó gọi inventory qua ALB nội bộ) cũng từ `503` chuyển sang `200`. Nghĩa là sales ở máy A vẫn gọi được inventory dù inventory của chính máy A đã tắt. Chứng minh thêm bằng chứng từ bán trên web (cần đăng nhập) là tùy chọn. Nếu `INTERNAL_LB_HOST` chưa bật thì không có chuyển máy (giới hạn đã thừa nhận).

**Hỏi hay gặp.**
- *Vì sao hai loại target group?* Khác cổng, khác đường đi (qua Kong hay thẳng service), và ALB ngoài ra Internet còn ALB nội bộ chỉ có trong VPC.
- *Vì sao ASG dùng health check EC2?* Nêu ở trên. Đổi lại máy mà nginx hoặc Kong chết sẽ bị ALB loại nhưng không được ASG thay.
- *Máy A chết thì sao?* Chat, thông báo, RabbitMQ, Mongo, Grafana ngừng (điểm lỗi đơn đã thừa nhận); các service ứng dụng còn máy ASG; event không mất vì nằm trong outbox.
- *ALB có tự thử lại request lỗi không?* Không. Request đã gửi vào máy chết là mất, đó là cửa sổ ~30 s.

---

## Slide 6. Nâng cấp hạ tầng AWS tiếp theo

**Cơ chế.** Mỗi nâng cấp có lý do và tác dụng rõ (bảng trên slide): Amazon MQ, DocumentDB (bỏ SPOF của máy A), Redis và khóa lãnh đạo (cho phép chat, notification, relay chạy nhiều bản), subnet riêng, rút máy khỏi ALB trước khi deploy, Terraform và CloudWatch alarm, sao chép S3 chéo vùng.

**Code.** Chưa làm, nên không có code. Các điểm hiện tại cần thay: relay chỉ chạy ở máy A ([docker-compose.ha-a.yml](../../../docker-compose.ha-a.yml)), `Outbox__RelayEnabled=false` ở máy B ([docker-compose.ha-b.yml](../../../docker-compose.ha-b.yml)).

**Hỏi hay gặp.** *Vì sao relay chỉ chạy một bản?* Relay Laravel không có khóa, chạy hai nơi sẽ gửi trùng và sai thứ tự sự kiện. Khóa lãnh đạo (ví dụ qua Redis) cho phép nhiều bản mà chỉ một bản hoạt động tại một thời điểm.

---

## Slide 7. Tiến trình và luồng (chương 3)

**Cơ chế.** Mỗi công nghệ một mô hình: PHP-FPM mỗi request một tiến trình (không chia sẻ bộ nhớ); .NET thread pool + async/await; Node.js một vòng lặp sự kiện; Python asyncio. Công việc nền chạy container riêng: relay outbox (quét mỗi 1,5 s), job đối soát (5 phút), sao lưu (mỗi giờ), tất cả ở máy A.

**Code.**
- PHP-FPM giới hạn: [php-fpm.conf:8-11](../../../services/sales-service/docker/php-fpm.conf#L8-L11) (`pm.max_children = 8`).
- Relay: [RelayOutboxEvents.php:23](../../../services/sales-service/app/Console/Commands/RelayOutboxEvents.php#L23) (`--sleep=1500`); container `sales-relay` chạy `php artisan outbox:relay` ([docker-compose.yml:445-449](../../../docker-compose.yml#L445-L449)).
- Lập lịch: [console.php:12](../../../services/sales-service/routes/console.php#L12) (`everyFiveMinutes()->withoutOverlapping()`), container `sales-scheduler` chạy `schedule:work` ([docker-compose.yml:475-479](../../../docker-compose.yml#L475-L479)).
- Quản lý tiến trình: `restart: unless-stopped` và `healthcheck` trong [docker-compose.yml](../../../docker-compose.yml) (mọi service).

**Demo (AWS, máy A).**
```bash
docker compose ps                                  # thấy (healthy) từng container
docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' erp-inventory     # unless-stopped
docker compose logs --tail 20 sales-scheduler      # job đối soát chạy 5 phút một lần ("Running sales:reconcile-reservations ... DONE")
docker compose logs --tail 20 sales-relay          # relay đã khởi động và đang quét outbox
```
**Đừng demo bằng `docker kill` hay `docker stop`.** Đã thử ngày 14/11: `docker kill erp-inventory` làm container `Exited (137)` và **không** tự khởi động lại (`RestartCount=0`), vì Docker coi đó là người dùng chủ động dừng nên bỏ qua `restart: unless-stopped`. Chính sách này chỉ có tác dụng khi tiến trình **tự chết** (crash, hết bộ nhớ, thoát lỗi) hoặc khi máy khởi động lại. Tôi không mô phỏng được một vụ crash từ phiên SSM (giết tiến trình chính từ bên ngoài bị từ chối quyền), nên đây là điều **chưa chứng minh bằng demo**; hãy trả lời bằng cơ chế và chỉ vào cấu hình. Muốn bật lại sau `docker kill`: `docker compose start inventory-service`.

**Hỏi hay gặp.**
- *Process khác thread thế nào?* Tiến trình có bộ nhớ riêng (an toàn, nặng); luồng dùng chung bộ nhớ (nhẹ, phải tránh tranh chấp).
- *Vì sao `withoutOverlapping`?* Nếu lần chạy trước chưa xong thì lần sau không chồng lên, tránh hai job cùng nhả một khoản giữ.
- *Vì sao dùng Docker thay PM2/Supervisor?* Docker vừa tự khởi động lại, vừa có health check và thứ tự khởi động, không cần thêm công cụ.
- *Container tự khởi động lại khi nào?* Khi tiến trình tự chết hoặc máy khởi động lại. Khi người dùng chạy `docker stop` hoặc `docker kill` thì **không** (đã quan sát), đó là chủ ý của Docker để không chống lại thao tác của người vận hành. Health check chỉ đánh dấu `unhealthy`, không tự khởi động lại.

---

## Slide 8. Trao đổi thông tin: ba kênh và outbox (chương 4)

**Cơ chế.**
- **REST** khi cần câu trả lời ngay (giữ hàng). **RabbitMQ** khi báo "việc đã xảy ra" (đơn được duyệt). **WebSocket** đẩy xuống trình duyệt (chat).
- **Outbox**: service ghi dữ liệu và một dòng `outbox_events` **trong cùng transaction**; relay quét dòng `PENDING`, gửi lên RabbitMQ, đánh `PUBLISHED`; lỗi thì `FAILED` và thử lại sau 2^n giây (tối đa 60 s). Consumer ghi `processed_events` cùng transaction xử lý, nhận trùng thì bỏ qua rồi ack. Message lỗi quá số lần thì sang `erp.dlx`.
- Trên AWS: dòng outbox nằm ở RDS dùng chung dù request vào máy nào; relay chỉ chạy ở máy A nên chính máy A gửi lên RabbitMQ.

**Code.**
- Ghi outbox: [Outbox.php:17-45](../../../services/sales-service/app/Support/Outbox.php#L17-L45) (`record`, gọi trong `DB::transaction`). Ví dụ gọi: `recordEvent('SalesOrderApproved', 'sales.order.approved', ...)` ở [SalesOrderController.php:605](../../../services/sales-service/app/Http/Controllers/SalesOrderController.php#L605).
- Relay: [RelayOutboxEvents.php:60-72](../../../services/sales-service/app/Console/Commands/RelayOutboxEvents.php#L60-L72) (backoff, `isDue`), dòng 92 (đánh `PUBLISHED`), dòng 115-119 (đánh `FAILED`, tăng `retry_count`).
- Relay phía .NET: [ConfirmedOutboxPublisher.cs:14-38](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/ConfirmedOutboxPublisher.cs#L14-L38) (publisher confirms, chờ xác nhận 5 s).
- Consumer idempotent: [EventConsumerHandler.cs:46-90](../../../services/inventory-service/src/Inventory.Infrastructure/Services/EventConsumerHandler.cs#L46-L90) (kiểm tra `ProcessedEvents` trước, thêm bản ghi cùng transaction, xử lý cả trường hợp hai luồng cùng nhận một event); phía Node: [db.js:19](../../../services/notification-service/src/db.js#L19) (collection `processed_events` có TTL).
- Ack/nack: [RabbitMqConsumerWorker.cs:72-78](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/RabbitMqConsumerWorker.cs#L72-L78) (`BasicNack(requeue: false)` để RabbitMQ chuyển sang `erp.dlx`).
- Topology (queue, exchange, DLX, retry TTL 10 s): [definitions.json](../../../infrastructure/rabbitmq/definitions.json) (dòng 19, 35, 90-95); email retry ở [email.queue.js](../../../services/notification-service/src/email/email.queue.js).

**Demo (AWS, máy A, dùng hàm `sql` ở mục 0.3b; kịch bản 1 trong [bài 12](12-demo-chiu-loi-4-kich-ban.md)).**
```bash
docker compose stop rabbitmq
# trên web production: gửi duyệt một báo giá -> vẫn thành công (201), dù request vào máy nào
sql "SELECT id,event_type,status,retry_count FROM erp_sales_db.outbox_events ORDER BY id DESC LIMIT 5"
#   -> dòng mới nhất PENDING hoặc FAILED, retry_count tăng dần (relay backoff gấp đôi)
docker compose start rabbitmq
sleep 30
sql "SELECT id,event_type,status,retry_count FROM erp_sales_db.outbox_events ORDER BY id DESC LIMIT 5"
#   -> PUBLISHED
```
**Đã chạy thật ngày 14/11 với sự kiện `ProductCreated` của catalog** (không cần duyệt báo giá; cùng cơ chế outbox):
1. Máy A: `docker compose stop rabbitmq`.
2. Tạo sản phẩm bằng API (ASCII, thay `<TOKEN>`): `curl -X POST $URL/api/v1/catalog/products -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"designation":"ZZ-DEMO-OUTBOX","brand_id":1,"unit":"Cai","gross_price":1000}'` → **`201`** dù RabbitMQ đang tắt (hoặc tạo sản phẩm trên web).
3. Xem outbox ngay: `SELECT id,event_type,aggregate_id,status,retry_count,LEFT(last_error,90) FROM erp_catalog_db.outbox_events ORDER BY id DESC LIMIT 3;` → dòng mới nhất **`FAILED`, `retry_count = 1`, lỗi "Unable to connect to tcp://rabbitmq:5672"**; kho **chưa** có dòng tồn nào của sản phẩm (`SELECT COUNT(*) FROM erp_inventory_db.inventory_stocks WHERE product_id=<id>` = 0).
4. Máy A: `docker compose start rabbitmq` (healthy sau ~15 s). Chạy lại câu SQL ở bước 3: sau chừng 10 đến 20 giây dòng đó thành **`PUBLISHED`** (lần này `retry_count = 3`, `published_at` 03:40:06); `inventory_stocks` có **4 dòng tồn = 0** (một dòng mỗi kho) và `erp_inventory_db.processed_events` có thêm một dòng của `inventory-service`.
5. Dọn: `curl -X DELETE $URL/api/v1/catalog/products/<id> -H "Authorization: Bearer $TOKEN"` (sản phẩm bị xóa khỏi bảng; 4 dòng tồn bằng 0 ở kho vẫn còn, vô hại).
Điều cần nói: người dùng không thấy lỗi; sự kiện không mất; kho "đến sau" mà không hỏng gì. Sales dùng cùng cơ chế (`erp_sales_db.outbox_events`).

Chứng minh idempotent và hàng đợi:
```bash
sql "SELECT event_id,consumer_name,processed_at FROM erp_inventory_db.processed_events ORDER BY processed_at DESC LIMIT 5"   # mỗi (event_id, consumer) chỉ một dòng
docker compose exec rabbitmq rabbitmqctl list_queues name messages                                                          # số message còn chờ ở từng hàng đợi
```

**Hỏi hay gặp.**
- *Vì sao không publish thẳng trong controller?* Ghi DB và gửi message là hai việc rời nhau, một trong hai có thể hỏng: mất event hoặc event ma. Outbox làm việc gửi thành một dòng trong cùng transaction.
- *Outbox cho exactly-once à?* Không: giao *ít nhất một lần* + consumer idempotent = hiệu quả đúng một lần.
- *Event có đảm bảo thứ tự không?* Không giả định thứ tự; chỉ một relay mỗi service để giữ thứ tự gửi, consumer không dựa vào thứ tự.
- *Vì sao không dùng REST thay queue cho thông báo?* Sales sẽ chết theo notification; REST để báo tin buộc bên gọi chờ bên không cần chờ.

---

## Slide 9-10. Định danh: tên, địa chỉ và phân giải tên (chương 5)

> Chương "Định danh" của môn học (bài giảng *DS_Lec 4 - Định danh*) là về **tên, thực thể, địa chỉ, điểm truy cập, định danh, các loại không gian tên và phân giải tên**. Đăng nhập và JWT là chuyện *xác thực*, nằm ở phụ lục cuối mục này.

**Cơ chế (khái niệm của bài giảng, áp vào hệ thống).**

| Khái niệm | Trong Smart ERP | Điều cần chỉ ra |
|---|---|---|
| **Thực thể** | service, máy EC2, sản phẩm, chứng từ, sự kiện | mọi thứ cần được tham chiếu |
| **Tên** | `inventory-service`, `erp-inventory`, `/api/v1/inventory`, `erp.transportbooking.xyz`, Internal Code | một thực thể có **nhiều tên** |
| **Địa chỉ** | `172.18.0.10` (container), `172.31.40.5` (EC2), `13.213.86.128` (ALB) | đổi khi tạo lại container hay thay máy; một tên có nhiều địa chỉ |
| **Điểm truy cập** | cổng `8004`: trong mạng Docker, trên máy chủ, listener ALB nội bộ | một thực thể có **nhiều điểm truy cập** |
| **Định danh** | `product_id`, `event_id` (UUID), `X-Correlation-ID` (UUID), số chứng từ, ID máy EC2 | chỉ một thực thể, không tái sử dụng |

*Ba tính chất của địa chỉ* (bài giảng) đều xảy ra thật: **(1)** một tên nhiều địa chỉ: tên ALB phân giải ra 2 IP, tên ALB nội bộ ra 2 IP; **(2)** thực thể đổi địa chỉ trong lúc tồn tại: container tạo lại, máy ASG bị thay (trong lúc kiểm thử máy ASG đổi `i-09817...` → `i-0a2aae...` → `i-03f14...` → `i-0e8bd...`); **(3)** một địa chỉ trỏ tới thực thể khác nhau theo thời điểm: IP của container đã dừng có thể được cấp lại cho container khác (mạng bridge của Docker), vì vậy không hardcode IP.

*Ba yêu cầu của định danh*: **(1)** một định danh chỉ tới nhiều nhất một thực thể; **(2)** mỗi thực thể chỉ có một định danh; **(3)** định danh mãi mãi chỉ trỏ tới một thực thể (không tái sử dụng). Ví dụ: `product_id` là *định danh* của sản phẩm, còn **Internal Code là tên** dễ đọc (sinh tự động từ designation cộng 3 chữ đầu thương hiệu, thêm hậu tố `-2`, `-3` nếu trùng) nên service khác chỉ lưu `product_id`; số chứng từ ghi vào `document_number_claims` nên **số đã thất bại không bao giờ cấp lại** (yêu cầu 3); `event_id` là UUID của mỗi sự kiện, dùng để loại trùng.

*Ba loại không gian tên* (bài giảng): **phẳng** (chuỗi không chứa vị trí: `event_id`, `product_id`, `i-0f33...`), **có cấu trúc** (phân cấp: tên miền `erp.transportbooking.xyz`, đường dẫn `/api/v1/inventory/stocks`, số chứng từ `INV-{mã khách}-{ddmmyy}`), **theo thuộc tính** (tìm sản phẩm theo thương hiệu, danh mục, xuất xứ; Alloy chọn container để gom log theo nhãn `com.docker.compose.service`).

*Phân giải tên* (tên → địa chỉ):
- **Từ ngoài vào (DNS phân cấp + ALB):** `erp.transportbooking.xyz` → máy chủ tên của miền (`ns1.pavietnam.vn`, tìm qua root và `.xyz`) → bản ghi **CNAME** (TTL 300 s) `erp-alb-516531883.ap-southeast-1.elb.amazonaws.com` → 2 bản ghi **A** (TTL 60 s) `13.213.86.128`, `52.77.93.124` → ALB chọn máy A hoặc máy ASG. Đây là bài tập 1 của môn (tra cứu DNS, phân giải, cache).
- **Trong một máy (DNS nhúng của Docker):** `inventory-service` → `127.0.0.11` → `172.18.0.10:8004`; Kong chỉ nhớ kết quả 10 giây để khi container đổi địa chỉ thì chậm tối đa 10 giây.
- **So với bài giảng:** ALB giống ý tưởng **home-based** (tên bền, ALB biết máy nào đang sống); chuyển hướng HTTP 301 từ cổng 80 sang HTTPS có tinh thần **forwarding pointer**; **không dùng Chord/DHT** vì ít service, tên cố định, một sổ đăng ký tập trung (DNS, ALB) là đủ.

**Code và cấu hình.**
- Định danh sự kiện: [Outbox.php:21](../../../services/sales-service/app/Support/Outbox.php#L21) (`Str::uuid()` làm `event_id`); `X-Correlation-ID` do Kong sinh dạng UUID: [kong.yml:147-151](../../../infrastructure/kong/kong.yml#L147-L151).
- Định danh không tái sử dụng: [DocumentNumberGenerator.php:48-64](../../../services/sales-service/app/Support/DocumentNumberGenerator.php#L48-L64) (`document_number_claims`).
- Tên Internal Code (tên, không phải định danh): [ProductController.php:407-418](../../../services/catalog-service/app/Http/Controllers/ProductController.php#L407-L418) (`generateSku`).
- Phân giải tên trong Docker và Kong: `KONG_DNS_VALID_TTL: "10"` ở [docker-compose.yml:799](../../../docker-compose.yml#L799); `url: http://inventory-service:8004` ở [kong.yml:46](../../../infrastructure/kong/kong.yml#L46).
- Tên service nội bộ đổi thành tên ALB nội bộ: [docker-compose.ha.yml](../../../docker-compose.ha.yml) (`${INTERNAL_LB_HOST:-inventory-service}`).
- Chọn thực thể theo thuộc tính (nhãn Docker): [config.alloy](../../../infrastructure/monitoring/alloy/config.alloy) dòng 14-21.

**Demo (AWS).** Các kết quả ghi trong chú thích đã chạy thật ngày 14/11/2026.
```bash
# Máy cá nhân: chuỗi DNS của tên miền thật (nếu nslookup bị chặn cổng 53 thì dùng DNS qua HTTPS, dòng cuối)
nslookup erp.transportbooking.xyz          # CNAME erp-alb-516531883... -> 13.213.86.128, 52.77.93.124
nslookup -type=NS transportbooking.xyz     # ns1.pavietnam.vn, ns2.pavietnam.vn, nsbak.pavietnam.net
nslookup -type=NS xyz                      # generationxyz.nic.xyz, x.nic.xyz, y.nic.xyz (máy chủ tên của TLD)
curl -s "https://dns.google/resolve?name=erp.transportbooking.xyz&type=A"   # JSON: CNAME TTL 300 và hai bản ghi A TTL 60

# Máy A (Session Manager, thư mục /opt/smart-erp): phân giải bên trong
docker compose exec sales-service cat /etc/resolv.conf                 # nameserver 127.0.0.11
docker compose exec sales-service getent hosts inventory-service       # 172.18.0.10
getent ahostsv4 $(grep '^INTERNAL_LB_HOST=' .env | cut -d= -f2)        # 172.31.21.162 và 172.31.34.229 (một tên, hai địa chỉ)
docker inspect -f 'ip={{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' erp-inventory ; docker port erp-inventory   # 172.18.0.10 ; 8004/tcp -> 0.0.0.0:8004

# Địa chỉ đổi, vai trò giữ: máy ASG bị thay nhiều lần (CloudShell hoặc dev-local)
aws autoscaling describe-scaling-activities --auto-scaling-group-name erp-app-asg --max-items 6 --query 'Activities[].[StartTime,Cause]' --output text

# Tên và định danh khác nhau (SQL chỉ đọc; dùng hàm sql ở mục 0.3 hoặc dbx)
sql "SELECT id AS dinh_danh, sku AS ten_internal_code FROM erp_catalog_db.products ORDER BY id DESC LIMIT 3"
curl -sI $URL/api/v1/catalog/health | grep -i x-correlation-id        # UUID do Kong sinh cho mỗi request
```
Điều nên nói khi chỉ vào kết quả: tên miền → CNAME → **hai** địa chỉ (một tên nhiều địa chỉ); `172.18.0.10` là địa chỉ của container nên đổi khi tạo lại, còn tên `inventory-service` giữ nguyên; `product_id` là định danh ổn định còn Internal Code chỉ là tên.

**Bài tập của bài giảng.**
- *Bài 1 (DNS khi duyệt một trang web):* trả lời bằng chuỗi thật ở trên, thêm các tầng cache (trình duyệt, hệ điều hành, máy chủ DNS đệ quy) và TTL riêng của từng bản ghi (300 s, 60 s). Nhớ trích dẫn nguồn như đề yêu cầu, và đề cấm dùng công cụ LLM để viết bài nộp.
- *Bài 2 (Chord):* **dự án chưa cài Chord** (hệ thống này không cần). Đây là bài tập riêng cần viết thuật toán, test case và báo cáo; đề cấm dùng công cụ LLM để làm bài nộp, nên phần tự viết là của bạn.

**Hỏi hay gặp.**
- *Địa chỉ khác định danh thế nào?* Địa chỉ là tên của một điểm truy cập và **đổi được** (container tạo lại, máy bị thay); định danh chỉ một thực thể và **không đổi, không tái sử dụng**. Vì vậy hệ thống tham chiếu chéo service bằng định danh (`product_id`) và truy cập bằng tên, không hardcode địa chỉ IP.
- *Tên khác định danh thế nào?* Tên (Internal Code, `inventory-service`) dễ đọc, và một thực thể có thể có nhiều tên; định danh phải thỏa ba yêu cầu. Internal Code là tên chứ không phải định danh nên service khác lưu `product_id`.
- *Vì sao Kong chỉ nhớ DNS 10 giây?* Địa chỉ container đổi khi tạo lại; nhớ lâu thì gọi nhầm chỗ cũ và lỗi 502 kéo dài. Đổi lại, mỗi 10 giây có thêm một lần phân giải.
- *DNS có tập trung không?* Không, DNS là hệ phân giải **phân cấp, phân tán** (root → TLD → máy chủ của miền), có cache nhiều tầng và TTL; đó là lý do nó chịu được quy mô toàn cầu. Docker DNS và ALB là sổ đăng ký tập trung nhưng chỉ phục vụ phạm vi nhỏ của hệ thống.
- *Khi nào cần Chord/DHT?* Khi có rất nhiều nút, thay đổi liên tục, và không thể có sổ đăng ký trung tâm (ví dụ mạng ngang hàng). Hệ thống của nhóm có vài chục thực thể với tên cố định nên không cần.

---

## Phụ lục. Xác thực người dùng bằng JWT (không thuộc chương Định danh, nhưng dễ bị hỏi)

**Cơ chế (JWT, 3 bước).**
1. Người dùng đăng nhập bằng mã nhân viên + OTP email; identity ký token: `HMAC-SHA256(header.payload, JWT_SECRET)`. Payload có `sub`, `permissions`, `iss`, `exp` (mặc định 24 giờ).
2. Mỗi service có cùng `JWT_SECRET`, **tự tính lại chữ ký** từ header và payload rồi so với chữ ký trong token (so bằng phép so sánh an toàn thời gian). Sửa payload thì chữ ký lệch, trả 401.
3. Chữ ký đúng mới kiểm `alg` = HS256, `iss` = identity-service, `exp` chưa qua; rồi đọc `permissions` để phân quyền (thiếu quyền trả 403).

Ba lớp còn lại: tìm service (tên container, ALB nội bộ, tên miền), `X-Correlation-ID` (Kong sinh, mọi service chuyển tiếp và ghi log), định danh máy (OIDC cho GitHub, IAM role cho EC2). Trên AWS, hai máy có cùng `JWT_SECRET` (sinh một lần cho cả hai file `.env`) nên token do máy này ký thì máy kia chấp nhận.

**Code.**
- Cấp token: [JwtIssuer.php](../../../services/identity-service/app/Auth/JwtIssuer.php) (hàm `issue`; TTL ở `config/services.php` dòng 46, biến `JWT_TTL`).
- Kiểm tra (PHP): [JwtVerifier.php:29-55](../../../services/sales-service/app/Auth/JwtVerifier.php#L29-L55): dòng 37 kiểm `alg`, dòng 42 `hash_equals` so chữ ký, dòng 47 kiểm `iss` và `sub`, dòng 50 kiểm `exp`.
- Kiểm tra (.NET): [HmacJwtTokenValidator.cs:44-66](../../../services/inventory-service/src/Inventory.Infrastructure/Security/HmacJwtTokenValidator.cs#L44-L66) (`HMACSHA256`, `FixedTimeEquals`, `exp`).
- Middleware gắn vào route: [AuthenticateJwt.php](../../../services/sales-service/app/Http/Middleware/AuthenticateJwt.php); phân quyền: [RequirePermission.php](../../../services/sales-service/app/Http/Middleware/RequirePermission.php) và `->middleware('permission:sale_return.create')` trong [routes/api.php](../../../services/sales-service/routes/api.php).
- Gọi nội bộ không có người dùng: header `X-Internal-Service-Key`, middleware [RequireInternalService.php](../../../services/sales-service/app/Http/Middleware/RequireInternalService.php).
- OTP: `requestOtp` ở [AuthController.php:103](../../../services/identity-service/app/Http/Controllers/AuthController.php#L103).
- `X-Correlation-ID`: [kong.yml:147-151](../../../infrastructure/kong/kong.yml#L147-L151); ghi vào event ở [Outbox.php](../../../services/sales-service/app/Support/Outbox.php) (`correlation_id`).

**Demo (AWS, máy cá nhân, đã `export TOKEN` và `URL` ở mục 0.3a).**
```bash
# 1) Không có token -> 401, mã lỗi UNAUTHORIZED (đã chạy thử 14/11)
curl -s $URL/api/v1/inventory/stocks/1
# 2) Token giả -> 401, mã lỗi INVALID_TOKEN "Không thể giải mã token xác thực" (đã chạy thử 14/11)
curl -s -H "Authorization: Bearer aaa.bbb.ccc" $URL/api/v1/inventory/stocks/1
# 3) Token thật -> 200 (hoặc 404 nếu sản phẩm 1 không có tồn)
curl -s -o /dev/null -w "%{http_code}\n" -H "Authorization: Bearer $TOKEN" $URL/api/v1/inventory/stocks/1
# 4) Sửa 1 ký tự trong payload (phần GIỮA của token) -> chữ ký lệch -> 401
H=${TOKEN%%.*}; R=${TOKEN#*.}; P=${R%%.*}; S=${R#*.}
curl -s -o /dev/null -w "%{http_code}\n" -H "Authorization: Bearer $H.X${P:1}.$S" $URL/api/v1/inventory/stocks/1
```
Rồi dán `$TOKEN` vào https://jwt.io để chỉ cho thầy thấy payload chỉ được "ký" chứ không được "mã hóa". Nếu một lệnh trả 404 thay vì 401, đổi sang một route `GET` có thật trong `routes/api.php` của service đó.

**Hỏi hay gặp.**
- *JWT có mã hóa không?* Không, chỉ ký: ai cũng đọc được payload nên không để mật khẩu hay dữ liệu nhạy cảm vào đó.
- *Token bị lộ thì sao?* Dùng được tới khi hết hạn (24 giờ). Đó là đánh đổi: không hỏi lại identity nên không có điểm nghẽn, đổi lại không thu hồi tức thì. Hướng: RS256 và refresh token ngắn hạn.
- *Vì sao HS256 mà không RS256?* HS256 dùng chung một khóa nên đơn giản, đủ cho đồ án; RS256 an toàn hơn vì service chỉ cần khóa công khai.
- *Identity chết thì người đã đăng nhập còn dùng được không?* Còn, vì mỗi service tự kiểm tra.

---

## Slide 11. Đồng bộ hóa: chống bán âm (chương 6)

**Cơ chế.** Không đọc số tồn lên rồi trừ rồi ghi lại (hai người cùng đọc thấy còn hàng, cùng trừ, tồn âm). Dùng **một câu UPDATE có điều kiện** `WHERE quantity >= :qty`; InnoDB khóa dòng nên hai câu UPDATE xếp hàng; người sau thấy không đủ, số dòng ảnh hưởng = 0, trả `409 INSUFFICIENT_STOCK`. Sổ cái có `UNIQUE (document_type, document_id, warehouse_id, product_id)` nên mỗi chứng từ chỉ ghi sổ một lần. Giữ hàng cả chứng từ trong **một transaction**: thiếu một dòng thì rollback hết. Không dùng Redis lock vì dữ liệu chỉ nằm trong một database ACID. Trên AWS, hai máy cùng ghi vào một RDS nên khóa dòng của MySQL chặn cả khi hai request vào hai máy khác nhau.

**Code.**
- UPDATE có điều kiện (tồn thực tế): [InventoryStockManager.cs:333-337](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L333-L337) (`Where(s => delta >= 0 || s.Quantity >= -delta)` rồi `ExecuteUpdateAsync`); dòng 340-342: `updated == 0` thì ném `InsufficientStockException`. Tồn khả dụng cùng cách ở dòng 414 và 420-422.
- Giữ hàng cả lô: `BatchReserveStockAsync` dòng 118 và `BatchReserveOnceAsync` dòng 145-179 (`BeginTransactionAsync` ở dòng 158); endpoint [StocksController.cs:409](../../../services/inventory-service/src/Inventory.Api/Controllers/StocksController.cs#L409).
- Idempotent theo chứng từ: cùng số, cùng số lượng thì trả lại kết quả cũ; khác số lượng thì `DOCUMENT_ALREADY_POSTED` ([InventoryStockManager.cs:458](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L458)).
- Số chứng từ `INV-{mã khách}-{ddmmyy}`: [DocumentNumberGenerator.php:48-64](../../../services/sales-service/app/Support/DocumentNumberGenerator.php#L48-L64) (ghi `document_number_claims` trong transaction riêng, số thất bại không cấp lại).
- Test (chạy ở máy dev): [InventoryConcurrencyTests.cs:40-96](../../../services/inventory-service/tests/Inventory.Infrastructure.Tests/InventoryConcurrencyTests.cs#L40-L96) (`ConcurrencyTest_20ParallelTasks_OnlyOneSucceeds_ZeroOverselling`); test chống deadlock ở dòng 125.

**Demo (AWS, máy cá nhân): 20 request song song vào site thật.**
Bước 1: chọn **một sản phẩm demo** và xem tồn khả dụng của nó (ví dụ tồn khả dụng `N` ở kho `WH`):
```bash
curl -s -H "Authorization: Bearer $TOKEN" $URL/api/v1/inventory/stocks/<product_id> | python -m json.tool
```
Bước 2: bắn 20 yêu cầu giữ hàng cùng lúc, mỗi yêu cầu xin hơn một nửa `N` (nên chỉ một cái đủ hàng):
```bash
PID=<product_id>; WH=<warehouse_id>; QTY=<N/2 làm tròn lên, cộng 1>
seq 1 20 | xargs -P 20 -I{} curl -s -o /dev/null -w "%{http_code}\n" -X POST $URL/api/v1/inventory/stocks/reserve/batch \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d "{\"order_id\":\"DEMO-{}\",\"items\":[{\"warehouse_id\":$WH,\"product_id\":$PID,\"quantity\":$QTY}]}" | sort | uniq -c
```
Kết quả mong đợi: `1 200` và `19 409`. Do ALB chia request vào hai máy nên bạn cũng chứng minh được khóa hoạt động xuyên máy.
Bước 3 (**bắt buộc**): nhả hàng đã giữ để trả tồn như cũ (nhả theo số chứng từ, gọi dư vô hại):
```bash
for i in $(seq 1 20); do curl -s -o /dev/null -X POST $URL/api/v1/inventory/stocks/release/document -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d "{\"order_id\":\"DEMO-$i\"}"; done
```
Chạy lại bước 1 để thấy tồn khả dụng về lại `N`.

**Đã chạy thật ngày 14/11** (sản phẩm 778, kho 1, tồn 207, mỗi yêu cầu xin 105): kết quả `1 × 200` và `19 × 409`. Bằng chứng trong database (chạy được bằng dbx hoặc hàm `sql`):
```sql
SELECT document_id, quantity_delta, balance_after, created_at
FROM erp_inventory_db.inventory_stocks_reservation_transaction
WHERE product_id = 778 AND document_id LIKE 'DEMO-%' ORDER BY id;
-- DEMO-1  -105  102   (người thắng: tồn khả dụng 207 -> 102)
-- DEMO-1  +105  207   (sau khi nhả: về đúng 207)
```
19 yêu cầu bị từ chối **không để lại dòng nào** trong sổ cái, và `inventory_stocks` / `inventory_stocks_reservation` đều về `207 / 207`. Người thắng là ngẫu nhiên (lần này là `DEMO-1`), đừng hứa trước là yêu cầu số mấy.
Lưu ý khi dùng `curl -d` trên Windows: **không đưa chữ có dấu vào JSON** (ví dụ `"Cái"`), JSON bị hỏng và server nhận dữ liệu rỗng (trả 422); dùng không dấu hoặc gửi từ CloudShell. (Tài khoản của token phải có quyền giữ hàng; dùng tài khoản quản trị.) Có thể thêm bằng chứng từ code: mở [InventoryConcurrencyTests.cs:40](../../../services/inventory-service/tests/Inventory.Infrastructure.Tests/InventoryConcurrencyTests.cs#L40) và nói "đây là bản tự động của đúng thí nghiệm này, chạy trong CI".

**Hỏi hay gặp.**
- *Tại sao UPDATE có điều kiện an toàn?* Vì kiểm tra và trừ nằm trong cùng một câu lệnh nguyên tử; khóa dòng của InnoDB đảm bảo hai câu không chạy song song trên một dòng.
- *Khác gì khóa bi quan (`SELECT ... FOR UPDATE`)?* Khóa bi quan giữ khóa lâu hơn; UPDATE có điều kiện đủ cho một dòng và nhanh hơn. Khi cần khóa nhiều dòng thì khóa theo thứ tự cố định để tránh deadlock (test dòng 125).
- *Sao không dùng Redis lock hay Redlock?* Dữ liệu chỉ ở một database ACID; thêm Redis chỉ thêm một điểm hỏng.
- *Cùng chứng từ gửi hai lần thì sao?* Idempotent: không trừ hai lần; khác số lượng thì 422.

### Về saga (không cần trình bày, nhưng nên biết đủ để trả lời)

Lập chứng từ bán chạm hai database (sales lưu chứng từ, inventory giữ hàng); không có transaction nào bao cả hai. Nhóm không dùng 2PC (khóa tài nguyên khi chờ điều phối; MySQL, Mongo, RabbitMQ không cùng tham gia XA). Thay vào đó là chuỗi bước cục bộ + bước bù:
- Cấp số → giữ hàng cả lô → lưu chứng từ → (sau giao) xuất kho.
- Giữ hàng timeout hoặc 5xx → không biết đã giữ chưa → **nhả hàng của số đó ngay** (idempotent theo số chứng từ).
- Thiếu hàng (409) → chắc chắn không giữ gì, không cần nhả.
- Nhả cũng lỗi → job đối soát 5 phút dọn khoản mồ côi.

Code: [InvoiceController.php:151](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L151) (cấp số), 289-291 (giữ hàng, ném `StockReservationFailedException` khi lỗi), 999-1054 (`reserveStockForInvoice`, một lần gọi `reserve/batch`, dòng 1026), 868 (hủy), [ReconcileStockReservations.php:93](../../../services/sales-service/app/Console/Commands/ReconcileStockReservations.php#L93) (gọi `release/document`). Nếu bị hỏi, nói: "mỗi bước có hành động bù, đổi lại là nhất quán cuối cùng". Chi tiết: [bài 06](06-dong-bo-hoa.md) mục 7.

---

## Slide 12. Sao lưu và phục hồi (chương 7)

**Cơ chế.** `docker compose run --rm backup` dựng một container tạm: image `mongo:7` (có `mongodump`) cài thêm `mysqldump`, AWS CLI, `jq`. Nó dùng tài khoản MySQL `erp_backup` **chỉ đọc**, dump từng schema bằng `--single-transaction` (ảnh nhất quán, không khóa bảng), dump Mongo, gói tệp chat và EFS (gắn chỉ đọc), nén, tính SHA256, ghi `manifest.json`, `aws s3 sync` lên S3 bằng IAM role của máy A (**manifest đẩy sau cùng** nên có manifest nghĩa là bản đã đủ), xóa bản cục bộ quá 7 ngày, rồi container tự xóa (`--rm`). Cron trên máy A chạy mỗi giờ.
Phục hồi dùng container `recovery` riêng (cần quyền quản trị RDS): `verify` thử phục hồi vào schema tạm rồi so số bảng với manifest; `restore --yes` xóa schema rồi nạp lại (thiếu `--yes` chỉ in kế hoạch).

**Code.**
- Script: [backup.sh](../../../infrastructure/backup/backup.sh): `cmd_backup` dòng 48, `mysqldump ... --single-transaction` dòng 74-76, `mongodump` dòng 88, checksum dòng 122, dọn bản cũ dòng 159, `aws s3 sync` + manifest cuối dòng 181-183, `cmd_verify` dòng 217, `cmd_restore` dòng 277 (kế hoạch ở dòng 338, `DROP DATABASE` rồi nạp lại ở dòng 354-355).
- Image: [Dockerfile](../../../infrastructure/backup/Dockerfile).
- Định nghĩa hai container: [docker-compose.yml:138-186](../../../docker-compose.yml#L138-L186); phần riêng của máy A (thư mục chat và EFS gắn chỉ đọc): [docker-compose.ha-a.yml](../../../docker-compose.ha-a.yml).
- Lịch cron: mô tả ở [bài 07](07-sao-luu.md) mục 5.

**Demo (AWS, máy A, thư mục `/opt/smart-erp`).**
```bash
docker compose run --rm backup                       # sao lưu ngay (~10 giây), tự đẩy lên S3
docker compose run --rm backup list                  # liệt kê các bản cục bộ
docker compose run --rm recovery verify latest       # kiểm chứng: phục hồi thử vào schema TẠM trên RDS rồi xóa (không đụng dữ liệu thật)
docker compose run --rm recovery restore latest --mysql=erp_catalog_db    # thiếu --yes: CHỈ IN KẾ HOẠCH, không làm gì
```
Xem bản trên S3 (CloudShell, quyền quản trị; máy A chỉ có quyền ghi nên có thể không liệt kê được):
```bash
aws s3 ls s3://smart-erp-backup/smart-erp/ --recursive | tail -5
```
**Đã chạy thật ngày 14/11 trên máy A:** `backup` tạo bản `database-backup-20261114-05` (41 MB) trong **12 giây** và đẩy lên S3 thành công; `recovery verify latest` phục hồi thử cả 8 schema MySQL, 2 database Mongo, tệp chat và EFS, thành công sau **111 giây** (chậm hơn dev vì chạy trên RDS; **hãy để thời gian dài khi demo**, hoặc chạy trước và cho thầy xem kết quả); `restore ... --mysql=erp_catalog_db` không có `--yes` chỉ in "KẾ HOẠCH PHỤC HỒI ... Chưa làm gì cả". Tài khoản `dev-local` **không có quyền** `s3:ListBucket`, nên liệt kê bản trên S3 phải dùng CloudShell (quyền quản trị).
`verify` cần biến `MYSQL_ROOT_USER` (`admin` của RDS) và `MYSQL_ROOT_PASSWORD` trong `.env` của máy A ([docker-compose.ha-a.yml](../../../docker-compose.ha-a.yml)); nếu thiếu, nó báo lỗi rõ ràng. **Không chạy `--yes`.** Diễn tập phục hồi thật đã làm trên máy dev (84 s cho schema lớn nhất; 12 s cho 8 schema vào RDS). **Chưa thử phục hồi từ bản trên S3 về EC2**: nói thật nếu bị hỏi.

**Hỏi hay gặp.**
- *Multi-AZ có thay được sao lưu không?* Không: Multi-AZ sao chép *cả lỗi của con người*, lỡ `DROP TABLE` thì bản chờ cũng mất ngay. Sao lưu là bản chụp quá khứ.
- *RTO, RPO là gì?* RTO = thời gian phục hồi (84 s đo trên dev); RPO = mất tối đa bao nhiêu dữ liệu = chu kỳ sao lưu (1 giờ).
- *Vì sao `--single-transaction`?* Mỗi schema là một ảnh nhất quán mà không khóa bảng. Nhưng các schema chụp cách nhau vài giây nên không nhất quán xuyên service; outbox, `processed_events` và job đối soát sửa phần lệch sau phục hồi.
- *Vì sao hai container backup và recovery?* Backup chỉ có quyền đọc (nếu bị lộ cũng không xóa được dữ liệu); recovery cần quyền ghi nên tách riêng và chỉ chạy khi cần.

---

## Slide 13. Chịu lỗi theo từng lớp (chương 8)

| Lớp | Sự cố | Cơ chế | Code/cấu hình |
|---|---|---|---|
| Tiến trình | tiến trình tự chết (crash) | Docker tự khởi động lại (không áp dụng khi `docker stop`/`kill`) | `restart: unless-stopped`, `healthcheck` trong [docker-compose.yml](../../../docker-compose.yml) |
| Một service ở một máy | inventory chết | target group riêng mỗi service, chỉ loại service đó | ALB `erp-alb`, `tg-erp-*` (slide 5) |
| Cả máy | EC2 chết | ALB loại máy khỏi mọi nhóm; ASG dựng máy thay | ASG `erp-app-asg`, [asg-boot.sh](../../../scripts/aws/asg-boot.sh) |
| Vùng khả dụng | mất AZ | máy ở vùng kia phục vụ; RDS chuyển sang bản chờ | RDS Multi-AZ |
| Message | RabbitMQ sập | outbox giữ event, relay gửi lại | slide 8 |
| Triển khai | bản lỗi | cuốn chiếu, smoke test, dừng khi lỗi | [deploy.yml](../../../.github/workflows/deploy.yml) |

**Demo (AWS).**
- *Một service chết:* slide 5 (31 s).
- *Mất cả một máy ASG:* chạy vòng lặp đo như slide 5. Trong console: EC2 → chọn máy ASG → Instance state → **Terminate**. Sẽ thấy ~30 đến 50 giây lỗi một phần rồi 200 liên tục (đo thật ngày 06 đến 09/11: 50 s). Mở tab Activity của ASG: khoảng 1 phút sau ASG tạo máy thay (ghi "an instance was launched in response to an unhealthy instance"), khoảng 2,5 phút sau máy mới healthy. Lưu ý: ASG chỉ còn tối thiểu 1 máy, nên **đừng terminate máy ASG duy nhất khi chưa chắc máy A đang khỏe**.
- *Trạng thái các nhóm:*
  ```bash
  aws elbv2 describe-target-health --target-group-arn $(aws elbv2 describe-target-groups --names tg-erp-web --query 'TargetGroups[0].TargetGroupArn' --output text) --query 'TargetHealthDescriptions[].[Target.Id,TargetHealth.State]' --output text
  ```

**Hỏi hay gặp.**
- *Máy chết thì người dùng có thấy lỗi không?* Có một cửa sổ ~30 đến 50 giây lỗi một phần, vì ALB cần hai lần health check hỏng mới loại máy (mỗi 10 s). Sau đó không lỗi.
- *Vì sao health check của ALB chỉ tới Kong ở tg-erp-web?* Cố ý: một service lẻ chết không nên loại cả máy. Đổi lại, việc phát hiện service chết riêng lẻ do các `tg-erp-*` lo.

---

## Slide 14. Cơ chế chịu lỗi trong code (câu hỏi dễ bị hỏi nhất)

| Cơ chế | Chạy thế nào | Code |
|---|---|---|
| **Timeout** | Mọi lời gọi REST có giới hạn thời gian: 3 s (inventory, identity, catalog), 5 s (finance); .NET 10 s mỗi lần, 30 s tổng | [config/services.php](../../../services/sales-service/config/services.php) (`INVENTORY_SERVICE_TIMEOUT` mặc định 3); [InternalHttp.php:21-23, 32-37](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L21-L37); [Program.cs:26-30](../../../services/purchase-service/src/Purchase.Api/Program.cs#L26-L30) |
| **Retry** | Tối đa 2 lần, cách 200 ms, **chỉ khi lỗi kết nối thật** và chỉ lệnh an toàn lặp (đọc). Finance (ghi công nợ) tuyệt đối không retry; .NET chỉ retry `GET` | [InternalHttp.php:32-46](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L32-L46) (`retry(2, 200, isRetryable)`), dòng 26-29 (finance `retry: false`); [Program.cs:30-35](../../../services/purchase-service/src/Purchase.Api/Program.cs#L30-L35) |
| **Circuit breaker** | 5 lỗi liên tiếp (lỗi kết nối hoặc HTTP 5xx; 4xx như 409 không tính) thì **mở mạch 30 s**: từ chối ngay, không chờ timeout. Hết 30 s cho đúng 1 request thử (half-open): thành công thì đóng, lỗi thì mở tiếp. Trạng thái lưu trong cache của từng container | [CircuitBreaker.php:25-27 (hằng số), 43-70 (middleware), 71-80 (state)](../../../services/sales-service/app/Infrastructure/Clients/CircuitBreaker.php#L25-L80); Polly: [Program.cs:36-38](../../../services/purchase-service/src/Purchase.Api/Program.cs#L36-L38); eoffice: `app/Support/CircuitBreaker.php`; test: [CircuitBreakerTest.php](../../../services/sales-service/tests/Feature/CircuitBreakerTest.php) |
| **Lỗi rõ ràng (503)** | Phụ thuộc không phản hồi thì trả `503 DEPENDENCY_UNAVAILABLE` trong envelope chung, **không** trả dữ liệu rỗng | [StockReservationFailedException.php:22](../../../services/sales-service/app/Exceptions/StockReservationFailedException.php#L22), [DependencyUnavailableException.php](../../../services/sales-service/app/Infrastructure/Clients/DependencyUnavailableException.php), [InvoiceController.php:404](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L404) |
| **Kết quả không chắc** | Timeout hoặc 5xx lúc giữ hàng → nhả hàng của số đó ngay; thiếu hàng (409) thì không cần nhả | [InvoiceController.php:999-1054](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L999-L1054) |
| **Outbox** | Event ghi cùng transaction với dữ liệu; relay backoff gấp đôi (tối đa 60 s) | slide 8 |
| **Idempotent consumer** | `processed_events (event_id, consumer_name)`; nhả hàng idempotent theo số chứng từ | [EventConsumerHandler.cs:46-90](../../../services/inventory-service/src/Inventory.Infrastructure/Services/EventConsumerHandler.cs#L46-L90) |
| **Dead letter** | `BasicNack(requeue:false)` đẩy message lỗi sang `erp.dlx` rồi `erp.dead_letters`; email có hàng chờ thử lại (10 s, tối đa 5 lần) | [RabbitMqConsumerWorker.cs:77-78](../../../services/inventory-service/src/Inventory.Infrastructure/Messaging/RabbitMqConsumerWorker.cs#L77-L78); [definitions.json:90-95](../../../infrastructure/rabbitmq/definitions.json#L90-L95); [config.js:26-28](../../../services/notification-service/src/config.js#L26-L28) |
| **Giới hạn tốc độ** | Mỗi IP 600 request/phút toàn gateway; đăng nhập 20/phút; AI 10/phút | [kong.yml:17-21, 117-121, 140-145](../../../infrastructure/kong/kong.yml#L140-L145) |

### Demo trên AWS

**Circuit breaker (hay bị hỏi nhất).** Kết quả phụ thuộc trạng thái ALB nội bộ (mục 0.3c).

*Trường hợp A, `INTERNAL_LB_HOST` chưa bật (sales ở máy A chỉ gọi inventory ở máy A).* Trên máy A:
```bash
docker compose stop inventory-service
# Gọi inventory 6 lần qua đúng lớp client có circuit breaker của sales:
docker compose exec sales-service php artisan tinker --execute '
for ($i = 1; $i <= 6; $i++) {
    try { App\Infrastructure\Clients\InternalHttp::inventory(null)->get(config("services.inventory.url")."/health"); echo "lan $i: goi duoc\n"; }
    catch (Throwable $e) { echo "lan $i: ".class_basename($e)."\n"; }
}
echo "Mach: ".App\Infrastructure\Clients\CircuitBreaker::for("inventory-service")->state()."\n";'
# -> vài lần đầu là ConnectionException (mỗi lần còn tự retry), rồi CircuitOpenException (từ chối NGAY), trạng thái OPEN
#    (khi ALB nội bộ đã bật thì vài lần đầu là "HTTP 502" thay vì ConnectionException, xem kết quả đo bên dưới)
curl -s localhost:8005/api/health | python3 -m json.tool       # sales trực tiếp ở máy A: "circuit_breakers": {"inventory-service": "OPEN", ...}, HTTP 503
# Đợi 30 s: trạng thái sang HALF_OPEN; bật lại rồi gọi một lần: đóng lại
docker compose start inventory-service
```
Trên web, tạo chứng từ bán trúng máy A lúc này trả `503 DEPENDENCY_UNAVAILABLE`, và hàng không bị giữ (kiểm tra `inventory_stocks_reservation` không bị trừ).

*Trường hợp B, `INTERNAL_LB_HOST` đã bật.* Tắt inventory ở máy A **không** làm sales lỗi (ALB nội bộ chuyển sang máy ASG): đây là demo failover (slide 5, Demo B). Muốn thấy circuit breaker thì tắt inventory ở **cả hai máy** (Session Manager vào máy ASG và chạy `docker compose stop inventory-service`), rồi chạy đoạn lệnh ở trường hợp A. Nhớ bật lại cả hai.

**Kết quả đo thật ngày 14/11 (trường hợp B, inventory tắt ở cả hai máy, chạy bằng đúng đoạn `tinker` ở trên với 8 lần gọi):**
```
lan 1..4: HTTP 502 (3-27 ms)  -> mach: CLOSED
[CircuitBreaker] inventory-service: mở mạch 30s sau 5 lỗi liên tiếp
lan 5:    HTTP 502 (37 ms)    -> mach: OPEN
lan 6..8: CircuitOpenException (0-1 ms) -> mach: OPEN        # từ chối NGAY, không gọi ra mạng
sales /api/health: status=DEGRADED, circuit_breakers.inventory-service=OPEN
(đợi 32 s) lan 1: HTTP 502 -> mở lại "sau 6 lỗi liên tiếp"   # nửa mở: cho đúng 1 request thử, lỗi nên mở tiếp 30 s
(bật lại inventory, đợi 40 s) [CircuitBreaker] đóng mạch, service đã phục hồi; 3 lần HTTP 200, mạch CLOSED
```
Hãy chỉ cho thầy: 4 lần đầu mạch còn `CLOSED` (đang đếm lỗi), lần thứ 5 `OPEN`; từ lần 6 trả `CircuitOpenException` trong 0 đến 1 ms. Ở môi trường thật khi service **treo** (không từ chối nhanh) thì mỗi lần gọi lẽ ra chờ hết timeout 3 s; mạch mở giúp bỏ khoản chờ đó, nhưng với một service đã tắt hẳn thì lỗi đã nhanh sẵn, nên khác biệt thời gian trong demo là vài mili giây.

Muốn tắt inventory ở máy ASG: Session Manager vào máy ASG (ID: `aws autoscaling describe-auto-scaling-groups ...` như mục 0.1), chạy `cd /opt/smart-erp && docker compose stop inventory-service`; nhớ `start` lại cả hai máy sau demo và đợi `tg-erp-inventory` hai máy `healthy` (~13 giây sau khi bật, theo ngưỡng 2 lần × 10 s). Ngoài ra `php artisan test --compact --filter=CircuitBreakerTest` (ở máy dev) là bằng chứng tự động.

**Timeout và 503.** Trường hợp A ở trên: lời gọi tới inventory đã tắt trả lỗi trong vài giây (không treo vô hạn), API trả 503 trong envelope chung.

**Retry.** Mở [InternalHttp.php:32-46](../../../services/sales-service/app/Infrastructure/Clients/InternalHttp.php#L32-L46) và chỉ ba điều: `retry(2, 200, ...)`, `isRetryable` chỉ nhận `ConnectionException`, finance dùng `retry: false`.

**Outbox, idempotent.** Slide 8.

**Dead letter.** Trên máy A: `docker compose exec rabbitmq rabbitmqctl list_queues name messages | grep -E "dead|retry"`. Hàng `erp.dead_letters` bình thường là 0; nếu có message, phát lại bằng [replay-dead-letters.ps1](../../../scripts/powershell/replay-dead-letters.ps1).

**Rate limit.** Máy cá nhân (chỉ khóa IP của bạn khoảng 1 phút):
```bash
for i in $(seq 1 70); do curl -s -o /dev/null -w "%{http_code}\n" -X POST $URL/api/v1/auth/login/otp/request -H 'Content-Type: application/json' -d '{}'; done | sort | uniq -c
# đã chạy thật ngày 14/11: 40 lần 422 (qua, do thiếu dữ liệu) rồi 30 lần 429; header khi bị chặn: X-RateLimit-Limit-Minute: 20, Retry-After: 27
```
Vì sao **40 chứ không phải 20**: Kong dùng `policy: local`, đếm trong bộ nhớ **từng máy**; có hai máy sau ALB nên giới hạn thực tế là 20 × số máy (25 request đầu không bị chặn). Đây là câu trả lời tốt nếu bị hỏi "rate limit có chính xác không": nó chính xác theo từng gateway, không chia sẻ giữa các máy; muốn chính xác toàn cục phải dùng Redis làm kho đếm chung. Test chỉ khóa IP của bạn khoảng 1 phút.

**Hỏi hay gặp.**
- *Retry mù có nguy hiểm không?* Có: lệnh ghi (trừ tồn, ghi công nợ) mà retry có thể ghi hai lần. Vì vậy chỉ retry lỗi kết nối và lệnh đọc; ghi dùng idempotent theo số chứng từ.
- *Khác nhau giữa timeout và circuit breaker?* Timeout giới hạn **một** lời gọi chậm; circuit breaker nhớ **nhiều** lần lỗi và ngừng gọi hẳn để không dồn tải vào service đang hỏng và để người dùng không phải chờ.
- *Vì sao 409 không tính vào circuit breaker?* Là câu trả lời hợp lệ của nghiệp vụ (hết hàng), không phải service hỏng.
- *Dead letter queue để làm gì?* Message xử lý hoài không được không chặn cả hàng đợi; nó được cách ly để kiểm tra và phát lại sau.
- *Trạng thái circuit breaker có chia sẻ giữa hai máy không?* Không: lưu trong cache của từng container (`CACHE_STORE=file`), nên mỗi máy tự đếm lỗi của mình. Đó là một giới hạn nhỏ, chấp nhận được vì mỗi máy tự bảo vệ chính nó.

---

## Slide 15. Triển khai liên tục

**Cơ chế.** Merge vào `main` → CI chạy test, `Build images` build 12 image lên GHCR, gắn thẻ `sha-<7 ký tự>`. Workflow `deploy.yml` kiểm tra CI của **đúng commit** đã xanh (đỏ thì không bao giờ tới bước duyệt), chờ một người duyệt (environment `production`), đăng nhập AWS bằng **OIDC** (token ngắn hạn, không lưu khóa), dùng **SSM Run Command** chạy `scripts/deploy-aws.sh` (không SSH, không mở cổng 22). Deploy **cuốn chiếu**: máy A trước, rồi từng máy ASG đang InService; mỗi máy chạy smoke test; máy nào lỗi thì dừng, các máy sau giữ bản cũ; chỉ khi tất cả xong mới ghi thẻ vào SSM `/smart-erp/prod/image-tag`.

**Code.**
- [deploy.yml](../../../.github/workflows/deploy.yml): `verify-ci` (dòng 43-71), `deploy` (73-177), vòng lặp từng máy dòng 139-173, ghi SSM dòng 176.
- [build-images.yml](../../../.github/workflows/build-images.yml): thẻ `sha-` và `latest`; stage `test` của Dockerfile không bao giờ được đẩy lên.
- [deploy-aws.sh](../../../scripts/deploy-aws.sh): migration (dòng 58-64, **chỉ máy A**), smoke test (dòng 92-94).
- [smoke-test.sh](../../../scripts/smoke-test.sh): gọi 10 đường `/health` qua toàn chuỗi.
- Quay lui: chạy lại workflow bằng tay với `image_tag` là thẻ cũ.

**Demo (AWS).** GitHub → Actions → **Deploy production** → Run workflow → nhập `sha-xxxxxxx` (thẻ đang chạy: `aws ssm get-parameter --name /smart-erp/prod/image-tag --query Parameter.Value --output text`). Xem log từng máy ("Deploy ... lên i-...") và dòng cuối "Đã ghi ... vào tham số". Chỉ nên **mở lịch sử một lần chạy cũ** để thầy xem, đừng chạy deploy mới ngay trước/trong lúc bảo vệ (mỗi máy sẽ có 20 đến 30 s lỗi một phần).

**Hỏi hay gặp.**
- *Vì sao OIDC?* Không lưu khóa AWS lâu dài trong GitHub; AWS cấp quyền tạm thời chỉ cho đúng repo và environment.
- *Zero-downtime không?* Chưa: mỗi máy có 20 đến 30 s lỗi một phần vì chưa rút máy khỏi ALB trước khi cập nhật.
- *Vì sao migration chỉ chạy ở máy A?* Hai máy migration cùng lúc có thể đụng nhau; schema dùng chung ở RDS nên một nơi lo là đủ. Luôn deploy máy A trước.
- *Điều nhóm đã gặp thật:* máy A có `IMAGE_TAG=latest` trong `.env` và trả 404 ở `/health/live`, còn máy ASG chạy `sha-1e7ffa9` và trả 200. Nhiều khả năng máy A đang giữ một bản `latest` cũ trong bộ nhớ đệm của Docker (Docker không tự kéo lại thẻ `latest`). Bài học: luôn deploy bằng thẻ `sha-` qua workflow để mọi máy cùng một phiên bản.

---

## Slide 16. Giới hạn đã biết

**Cơ chế.** Sáu giới hạn ở tầng ứng dụng, mỗi cái có cách xử lý (bảng trên slide). Các nâng cấp hạ tầng ở slide 6.

**Hỏi hay gặp (trả lời thật, không né).**
- *Điểm yếu lớn nhất?* Máy A: RabbitMQ, Mongo, relay, chat, notification chỉ có một bản. Máy A chết thì realtime ngừng, nhưng event nằm trong outbox, không mất.
- *Còn lỗi nào khi một service chết?* ~30 s lỗi trước khi ALB loại chỗ chết, và request lỗi treo ~15 s do Kong thử kết nối 3 lần.
- *Việc nào nhóm chưa làm?* Phục hồi từ bản trên S3 về EC2, cảnh báo CloudWatch, Terraform, và (nếu chưa bật) đưa lời gọi nội bộ qua ALB nội bộ.

---

## Slide 17-18. Demo và kết thúc

**Thứ tự demo gợi ý trên AWS (6 phút).**
1. 1 phút: chuỗi nghiệp vụ trên web (đơn bán → chứng từ bán có giữ hàng → xuất kho), chỉ tồn khả dụng và tồn thực tế.
2. 2 phút: chống bán âm: 20 request song song (slide 11, bước 1 đến 3). Chuẩn bị sẵn `PID`, `WH`, `QTY`.
3. 3 phút: tắt inventory ở máy A, chạy vòng lặp đo, chỉ tab Targets trong console (slide 5, Demo A); nếu đã bật ALB nội bộ thì thêm Demo B (tạo chứng từ bán vẫn thành công).

**Chuẩn bị trước 30 phút.** RDS bật, máy A bật, ASG có ít nhất 1 máy; `export TOKEN` và `URL`; mở sẵn `https://erp.transportbooking.xyz/health`, trang Targets của `tg-erp-inventory`, hai phiên Session Manager (máy A và máy ASG), terminal có vòng lặp đo, tab Actions của GitHub. Chạy thử toàn bộ lệnh demo một lần, đặc biệt `sql`, `tinker`, và 20 request song song. Kiểm tra `BASE_URL=http://localhost scripts/smoke-test.sh` trên máy A toàn xanh.

**Sau buổi demo.** Bật lại mọi thứ đã tắt, chạy `docker compose ps` ở cả hai máy, kiểm tra các `tg-*` đều `healthy`, và chạy bước nhả hàng `DEMO-*` nếu đã chạy demo chống bán âm.

---

## Bảng tra nhanh: "Hỏi về X thì mở file Y"

| Câu hỏi | Mở |
|---|---|
| Chống bán âm làm thế nào? | `InventoryStockManager.cs:333-342`, test `InventoryConcurrencyTests.cs:40` |
| Giữ hàng cả chứng từ? | `InvoiceController.php:999-1054` → `InventoryStockManager.cs:118-179` |
| Không mất event khi RabbitMQ chết? | `Outbox.php`, `RelayOutboxEvents.php:60-120` |
| Event trùng thì sao? | `EventConsumerHandler.cs:46-90`, `db.js:19` |
| Circuit breaker? | `CircuitBreaker.php:25-80`, `Program.cs:36-38` (purchase) |
| Retry? | `InternalHttp.php:32-46` |
| JWT kiểm tra chữ ký? | `JwtVerifier.php:29-55`, `HmacJwtTokenValidator.cs:44-66` |
| Phân quyền? | `RequirePermission.php`, `routes/api.php` (`permission:...`) |
| Rate limit, correlation id? | `kong.yml:137-160` |
| Sao lưu, phục hồi? | `infrastructure/backup/backup.sh` |
| Deploy cuốn chiếu? | `.github/workflows/deploy.yml:117-177` |
| ALB, health check, ASG? | `docker-compose.ha.yml`, `HealthController.php` (sales), console AWS |
| Job đối soát giữ hàng? | `console.php:12`, `ReconcileStockReservations.php` |

## Tự kiểm tra (trả lời không nhìn tài liệu)

1. Vì sao `UPDATE ... WHERE quantity >= :qty` không bán âm? Chỉ vào dòng code.
2. RabbitMQ chết lúc duyệt đơn: đơn có tạo được không, event ở đâu, khi nào gửi?
3. Sửa một ký tự trong payload JWT thì chuyện gì xảy ra, ở dòng nào?
4. Circuit breaker mở khi nào, đóng khi nào, và vì sao 409 không tính?
5. Inventory ở máy A chết: request người dùng đi đâu, mất bao lâu, vì sao lỗi treo 15 s?
6. Vì sao ASG dùng health check EC2? Chuyện gì đã xảy ra khi dùng ELB?
7. Bản sao lưu có dùng được không, làm sao biết trước khi cần? (`verify`)
8. Vì sao `latest` nguy hiểm khi deploy?
