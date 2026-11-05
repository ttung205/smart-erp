# 11. Đóng gói bằng Docker: từ source code đến container chạy

> Mục tiêu: giải thích được "code của em thành thứ chạy trên server bằng cách nào", chỉ ra từng bước trong Dockerfile và compose, và vì sao dev, test, deploy đều dùng chung một cách.
> Toàn bộ project chỉ có **một cách chạy: Docker**. Không còn script chạy native (`start_dev`, `setup`).

## 1. Chuỗi đóng gói

```
Source code ──docker build──► Image ──docker run / compose up──► Container
 (Dockerfile)                (bản đóng gói bất biến)              (tiến trình đang chạy từ image)
```

- **Image:** gói chỉ-đọc gồm hệ điều hành tối giản + runtime + thư viện + code của service. Build một lần, chạy ở đâu cũng giống nhau ("trên máy em chạy được" biến mất).
- **Container:** một tiến trình chạy từ image, có mạng, biến môi trường, volume riêng. Xoá container không mất image; xoá volume mới mất dữ liệu.
- **Layer:** mỗi lệnh `RUN/COPY` tạo một lớp, Docker cache theo lớp. Vì vậy `COPY composer.json` + `composer install` đặt **trước** `COPY . .`: sửa code không đổi `composer.json` thì bước cài thư viện (chậm) được lấy từ cache.

## 2. Multi-stage build: một Dockerfile, nhiều giai đoạn

Ví dụ Laravel, [sales-service/Dockerfile](../../../services/sales-service/Dockerfile) (5 service Laravel giống hệt nhau, chỉ khác cổng):

```
php-base ─────────┬──► test      (thư viện dev + tests/, chạy php artisan test)
 PHP-FPM + Nginx  │
 + extension      └──► runtime   ◄── vendor (composer install --no-dev, đã xoá tests/)
                       stage CUỐI = image chạy thật, user www-data
```

| Stage | Vai trò | Có trong image chạy thật? |
|---|---|---|
| `php-base` | PHP 8.4-FPM + Nginx + extension (`pdo_mysql`, `bcmath`, `opcache`…) + file cấu hình. Dùng chung để test và runtime **cùng môi trường** | Có (là nền) |
| `vendor` | Image `composer:2` cài thư viện production (`--no-dev`). Composer **không** vào image cuối | Chỉ thư mục `vendor/` được copy sang |
| `test` | Cài thêm thư viện dev (PHPUnit…), giữ `tests/`, `CMD php artisan test` | **Không** |
| `runtime` | Stage cuối: copy `/app` từ `vendor`, chạy bằng `www-data` (không phải root) | Đây là image chạy thật |

Vì sao **stage cuối phải là `runtime`**: `docker compose build` và `docker build` không có `--target` thì build **stage cuối cùng**. Đặt `test` ở giữa nên không bao giờ vô tình đóng gói code test lên server.

Các stack khác cùng ý tưởng:

| Stack | Stage | Điểm đáng nói |
|---|---|---|
| .NET ([inventory-service/Dockerfile](../../../services/inventory-service/Dockerfile)) | `build` (SDK ~800 MB) → `test` → `runtime` (chỉ ASP.NET runtime + file publish) | Copy `*.csproj` + `dotnet restore` trước khi copy `src/` để cache restore |
| Node ([notification-service/Dockerfile](../../../services/notification-service/Dockerfile)) | `deps` → `test` → `runtime` (`npm ci --omit=dev`, `rm -rf test`) | Image chạy thật không có devDependencies |
| Frontend ([frontend/Dockerfile](../../../frontend/Dockerfile)) | `build` (Vite) → `test` (eslint) → `runtime` (nginx + file tĩnh) | Node và `node_modules` (hàng trăm MB) không có trong image cuối |
| ai-service ([ai-service/Dockerfile](../../../services/ai-service/Dockerfile)) | `base` → `test` → `runtime` | Test = compile + import, giống job `ai-check` của CI |

So sánh để nói với thầy: image Laravel chạy thật ~200 MB (Alpine + PHP-FPM) so với image có composer + dev tools lớn hơn; image frontend ~119 MB vì chỉ có nginx và file tĩnh.

## 3. Những file đi kèm Dockerfile

| File | Làm gì |
|---|---|
| `.dockerignore` | Loại `vendor/`, `node_modules/`, `.env`, `*.sqlite`, log khỏi **build context** → build nhanh, không lọt secret vào image |
| `docker/nginx.conf`, `docker/php-fpm.conf`, `docker/opcache.ini` | Cấu hình Nginx + PHP-FPM. `opcache.validate_timestamps=0`: code trong image không đổi nên khỏi kiểm tra thời gian sửa file |
| `docker/start-web` | Chạy PHP-FPM và Nginx **trong cùng container**; một trong hai chết thì container thoát để Docker khởi động lại, thay vì sống dở với lỗi 502 |
| `docker-entrypoint.sh` | `ENTRYPOINT`: kiểm tra `APP_KEY`, chờ MySQL mở cổng (tối đa 45 giây), rồi `exec "$@"` chạy `CMD` |

**ENTRYPOINT và CMD:** `ENTRYPOINT` là bước chuẩn bị luôn chạy; `CMD` là lệnh mặc định, ghi đè được. Nhờ vậy cùng một image chạy 3 vai trò khác nhau, chỉ đổi `command` trong compose:

| Container | Image | Lệnh |
|---|---|---|
| `sales-service` | `smart-erp-sales-service` | `start-web` (mặc định) |
| `sales-relay` | cùng image | `php artisan outbox:relay` |
| `sales-scheduler` | cùng image | `php artisan schedule:work` |

## 4. Compose: ghép các container thành hệ thống

[docker-compose.yml](../../../docker-compose.yml) mô tả cả hệ thống:

- **`build` + `image`:** build từ Dockerfile nào, đặt tên image là gì.
- **`environment`:** truyền cấu hình (DB, JWT, RabbitMQ) từ `.env` → code không chứa secret. Tạo một image duy nhất, chạy ở dev/prod bằng cách đổi biến.
- **`depends_on: condition: service_healthy` + `healthcheck`:** service chỉ khởi động khi phụ thuộc đã **sẵn sàng** (không chỉ "đã start"). MySQL "started" chưa nhận kết nối ngay.
- **`restart: unless-stopped`:** container chết tự khởi động lại.
- **`networks: erp-network`:** mạng bridge riêng; container gọi nhau bằng tên service (DNS nội bộ của Docker).
- **`volumes`:** dữ liệu MySQL, Mongo, RabbitMQ sống ngoài container → `down` không mất dữ liệu, `down -v` mới mất.
- **`profiles: [monitoring]`:** nhóm Grafana/Prometheus/Loki/Alloy chỉ bật khi cần.

Ba lớp file compose, mỗi lớp **chồng** lên lớp trước (`-f a -f b`):

| File | Mục đích |
|---|---|
| `docker-compose.yml` | Nền: dev. Secret bắt buộc lấy từ `.env` (`${VAR:?}`), không có giá trị mặc định công khai; chỉ cổng, tên host... mới có mặc định |
| `docker-compose.prod.yml` | Production: mọi secret **bắt buộc** (`${VAR:?}`), `ports: !reset []` để chỉ gateway và frontend ra ngoài |
| `docker-compose.test.yml` | Test: thêm `<svc>-test` build `target: test` |

## 5. Quy trình dev, test, deploy là một

```
sửa code ─► docker compose up -d --build sales-service ...    (dev: image = image production)
        ─► scripts/test.ps1 -Service sales                      (build target `test`, chạy trong container)
        ─► git push ─► CI build/test ─► (giai đoạn AWS) pull image, up -d   (deploy)
```

Lợi ích để trình bày: **không còn "dev khác prod"**. Lỗi thiếu extension PHP, sai phiên bản runtime, thiếu biến môi trường lộ ra ngay lúc dev, không đợi tới lúc deploy.

Đánh đổi (nói thật): mỗi lần sửa code phải build lại image (20–60 giây nhờ layer cache), chậm hơn hot-reload. Bù lại đơn giản và đồng nhất.

## 6. Chỉ vào đâu khi bị hỏi

| Câu hỏi | Mở file |
|---|---|
| Image khác container thế nào, layer cache ra sao | [sales Dockerfile](../../../services/sales-service/Dockerfile): thứ tự `COPY composer.json` → `composer install` → `COPY . .` |
| Vì sao image nhỏ | multi-stage: `vendor`/`build` không vào `runtime` |
| Test chạy ở đâu, vì sao không lọt lên server | stage `test` + `rm -rf tests` ở stage `vendor` |
| Vì sao container không chạy bằng root | `USER www-data` ở stage `runtime` |
| Cùng image chạy web/relay/scheduler | `command:` của 3 service sales trong compose |
| Phụ thuộc khởi động | `depends_on`/`healthcheck` trong compose |
| Production khác dev ở đâu | [docker-compose.prod.yml](../../../docker-compose.prod.yml) |

## 7. Thầy có thể hỏi

**H: Vì sao dùng Docker mà không cài trực tiếp lên server?**
Đ: Môi trường đồng nhất và tái lập được: cùng image chạy ở máy dev, CI và AWS. Cài trực tiếp phải lo phiên bản PHP/.NET/Node, extension, xung đột giữa các service. Một server chạy 4 ngôn ngữ sẽ rất khó quản lý nếu không có container.

**H: Container khác máy ảo thế nào?**
Đ: Container chia sẻ kernel của máy chủ, chỉ cô lập tiến trình, mạng, file system nên khởi động trong vài giây và nhẹ (20 container chạy trên một máy 8 GB). Máy ảo mang theo cả hệ điều hành đầy đủ.

**H: Dữ liệu trong container có mất không?**
Đ: Lớp ghi của container thì mất khi xoá container. Dữ liệu quan trọng đặt ở **volume** (`mysql_data`, `mongo_data`, `rabbitmq_data`, `chat_files`) nên sống độc lập với container.

**H: Secret để ở đâu, có nằm trong image không?**
Đ: Không. Image không chứa secret (`.env` bị `.dockerignore` loại). Secret truyền lúc chạy qua biến môi trường từ file `.env` (không commit). Cả dev lẫn production đều dùng `${VAR:?}` nên thiếu secret là compose từ chối chạy; riêng production còn có `validate-prod-config.ps1` kiểm tra độ dài và giá trị mẫu.

**H: Scale một service lên nhiều bản được không?**
Đ: API stateless thì `docker compose up --scale` được (cần đặt gateway/load balancer phía trước). Relay outbox và chat (SignalR) thì không, xem bài 04.

## 8. Tự kiểm tra
- [ ] Giải thích vì sao `COPY composer.json` đặt trước `COPY . .`.
- [ ] Vẽ lại sơ đồ stage của một Dockerfile Laravel và nói stage nào vào image chạy thật.
- [ ] Giải thích vì sao stage cuối phải là `runtime`.
- [ ] Chạy `docker build --target test` và `docker build` (mặc định), so sánh kích thước.
- [ ] Giải thích cùng một image chạy 3 vai trò (web/relay/scheduler) bằng cách nào.
- [ ] Phân biệt `down` và `down -v`.
