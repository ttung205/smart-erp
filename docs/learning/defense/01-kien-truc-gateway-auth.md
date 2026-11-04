# 01. API Gateway (Kong) và xác thực phân tán (JWT + Internal Service Key)

> Mục tiêu: theo được một request từ trình duyệt tới service, nói được Kong làm gì ở giữa, và giải thích vì sao **service không cần gọi lại identity** mỗi lần kiểm tra token.

## 1. Đường đi của một request

```
Trình duyệt ──GET /api/v1/sales/orders, Authorization: Bearer <JWT>──►
  frontend nginx  (frontend/nginx.conf:21-33)  proxy /api/ → gateway:8000, ghi đè X-Forwarded-For
    ► Kong :8000
        1. rate-limiting  : 600 req/phút/IP (auth 20, ai 10)
        2. correlation-id : gắn X-Correlation-ID (uuid) nếu chưa có, trả lại cho client
        3. request-transformer: XOÁ header X-Internal-Service-Key người ngoài gửi vào
        4. route /api/v1/sales → http://sales-service:8005/api, strip_path → /api/orders
        5. timeout connect 5s / read 30s, retries 2
      ► sales-service
          middleware 'jwt'        : kiểm tra chữ ký, iss, exp → gắn "actor" vào request
          middleware 'permission' : actor có quyền order.view không?
          controller
```

## 2. Kong: chỉ vào code

File duy nhất: [infrastructure/kong/kong.yml](../../../infrastructure/kong/kong.yml). Kong chạy **DB-less** (`KONG_DATABASE: "off"`), cấu hình nạp từ file YAML lúc khởi động, không có database riêng, Admin API tắt (`KONG_ADMIN_LISTEN: "off"`) → không ai sửa route lúc chạy được.

| Tính năng | Dòng | Giải thích |
|---|---|---|
| Khai báo service + route | [kong.yml:4-121](../../../infrastructure/kong/kong.yml#L4-L121) | Mỗi service: `url` upstream + `paths` |
| `strip_path: true` | [:14](../../../infrastructure/kong/kong.yml#L14) | Laravel có prefix `/api` riêng nên Kong bỏ `/api/v1/sales` rồi nối vào `/api`. .NET dùng `strip_path: false` vì route trong code đã chứa `/api/v1/inventory` |
| Timeout + retries | [:7-10](../../../infrastructure/kong/kong.yml#L7-L10) | Không để request treo vô hạn; Kong thử lại 2 lần khi **không kết nối được** upstream |
| Timeout 1 giờ cho notification/chat | [:69-70](../../../infrastructure/kong/kong.yml#L69-L70), [:100-101](../../../infrastructure/kong/kong.yml#L100-L101) | WebSocket (Socket.IO, SignalR) là kết nối sống lâu |
| Rate limit riêng cho auth | [:15-21](../../../infrastructure/kong/kong.yml#L15-L21) | 20/phút/IP chống dò mật khẩu |
| Rate limit riêng cho AI | [:115-121](../../../infrastructure/kong/kong.yml#L115-L121) | Bảo vệ hạn mức API Groq miễn phí |
| `/health` do Kong tự trả | [:123-135](../../../infrastructure/kong/kong.yml#L123-L135) | `request-termination`, kiểm tra gateway sống mà không chạm service nào |
| Rate limit toàn cục | [:140-145](../../../infrastructure/kong/kong.yml#L140-L145) | `policy: local` = đếm trong RAM của Kong, đủ cho 1 instance |
| Correlation ID | [:147-151](../../../infrastructure/kong/kong.yml#L147-L151) | Mã theo dõi xuyên suốt request (log, outbox, event) |
| CORS | [:153-160](../../../infrastructure/kong/kong.yml#L153-L160) | `origins: ['*']` |
| **Xoá internal key từ ngoài** | [:162-165](../../../infrastructure/kong/kong.yml#L162-L165) | Chặn giả mạo lời gọi nội bộ (xem mục 4) |
| Prometheus metrics | [:168-174](../../../infrastructure/kong/kong.yml#L168-L174) | Đọc qua cổng status 8100, không lộ ra cổng 8000 |

Cấu hình container Kong: [docker-compose.yml:627-685](../../../docker-compose.yml#L627-L685). Đáng nói:
- `KONG_TRUSTED_IPS` + `KONG_REAL_IP_HEADER: X-Forwarded-For`: Kong đứng sau nginx nên chỉ thấy IP của nginx; tin header X-Forwarded-For từ dải mạng nội bộ để rate limit theo IP người dùng thật.
- `KONG_DNS_VALID_TTL: "10"`: phân giải lại tên service sau 10s.
- `KONG_NGINX_HTTP_LOG_FORMAT`: access log có `correlation_id=` để tra trên Loki.
- `depends_on ... condition: service_healthy` cho cả 10 API: Kong chỉ lên khi các service đã healthy.

**Gateway cũ (nginx):** đã thay hoàn toàn bằng Kong và xoá khỏi repo; muốn lùi thì deploy lại commit/tag image trước đó (tag theo SHA, xem bài DevOps).

### Vì sao cần API Gateway?
- **Một điểm vào duy nhất:** frontend chỉ biết `/api/v1/...`, không biết 10 địa chỉ service. Đổi vị trí service không ảnh hưởng client.
- **Cross-cutting concerns tập trung:** rate limit, CORS, correlation ID, metrics, chặn header nội bộ — làm một lần ở gateway thay vì 10 lần trong 4 ngôn ngữ.
- **Ẩn mạng nội bộ:** ở production chỉ Kong (8000) và frontend (5175) được publish ([docker-compose.prod.yml](../../../docker-compose.prod.yml), `ports: !reset []`).

## 3. Xác thực phân tán bằng JWT

### Ý tưởng
Identity là nơi **duy nhất** biết mật khẩu và **ký** token. Các service khác chỉ **kiểm tra** token bằng cùng một khoá bí mật (`JWT_SECRET`, thuật toán HMAC-SHA256). Token tự mang thông tin (`sub`, `roles`, `permissions`, `exp`) nên service **không cần gọi identity** → không phụ thuộc identity lúc chạy, không có session ở server (stateless).

```
Đăng nhập: POST /api/v1/auth/login → identity kiểm tra mật khẩu
          → ký JWT {iss:"identity-service", sub, name, email, roles, permissions, iat, exp}
Các lần sau: mỗi service tự tính lại HMAC(header.payload, JWT_SECRET) và so với chữ ký
```

### Chỉ vào code

| Bước | File |
|---|---|
| Đăng nhập, gom roles/permissions vào claims | [AuthController.php:40-57](../../../services/identity-service/app/Http/Controllers/AuthController.php#L40-L57) |
| Ký token HS256, đặt `iss`, `iat`, `exp` | [JwtIssuer.php:23-36](../../../services/identity-service/app/Auth/JwtIssuer.php#L23-L36) |
| Kiểm tra (PHP): đúng 3 phần, `alg=HS256`, **so chữ ký trước khi tin claim**, `iss`, `exp` | [sales-service JwtVerifier.php:29-55](../../../services/sales-service/app/Auth/JwtVerifier.php#L29-L55) |
| So chữ ký bằng `hash_equals` (thời gian hằng, chống timing attack) | [JwtVerifier.php:42](../../../services/sales-service/app/Auth/JwtVerifier.php#L42) |
| Middleware gắn `actor` vào request | [AuthenticateJwt.php:16-38](../../../services/sales-service/app/Http/Middleware/AuthenticateJwt.php#L16-L38) |
| Phân quyền: SUPER_ADMIN, quyền cụ thể, hoặc `<resource>.manage` | [RequirePermission.php:16-37](../../../services/sales-service/app/Http/Middleware/RequirePermission.php) |
| Gắn middleware vào route | [sales routes/api.php:23](../../../services/sales-service/routes/api.php#L23) (`jwt`), từng route `permission:...` |
| .NET | `services/inventory-service/src/Inventory.Api/Middlewares/JwtAuthMiddleware.cs` + `HmacJwtTokenValidator.cs` |
| Node | [notification-service/src/auth/jwt.js:9-50](../../../services/notification-service/src/auth/jwt.js#L9) |
| Python | [ai-service/app/auth.py:34-57](../../../services/ai-service/app/auth.py#L34) (`hmac.compare_digest`) |

→ Cùng một "hợp đồng" token được cài đặt ở 4 ngôn ngữ. Đó là lý do có [docs/contracts/](../../contracts/) mô tả chuẩn chung.

## 4. Gọi service-service: Internal Service Key

Có những lời gọi **không có người dùng** đứng sau: relay, consumer, job định kỳ, notification tải PDF báo giá từ sales để đính kèm email. Lúc đó dùng header `X-Internal-Service-Key` mang khoá bí mật chung `INTERNAL_SERVICE_KEY`.

| | File |
|---|---|
| So khoá thời gian hằng, khoá rỗng thì từ chối hết | [InternalServiceKey.php:13-16](../../../services/sales-service/app/Auth/InternalServiceKey.php#L13-L16) |
| Middleware `internal` chỉ cho service nội bộ | [RequireInternalService.php](../../../services/sales-service/app/Http/Middleware/RequireInternalService.php) |
| Route ví dụ: notification tải tệp đính kèm | [sales routes/api.php:21](../../../services/sales-service/routes/api.php#L21) |
| .NET dùng `CryptographicOperations.FixedTimeEquals` | `inventory-service/src/Inventory.Api/Middlewares/JwtAuthMiddleware.cs:64-73` |
| **Kong xoá header này từ request bên ngoài** | [kong.yml:162-165](../../../infrastructure/kong/kong.yml#L162-L165) |

Vì sao an toàn? Người ngoài chỉ vào được qua Kong; Kong xoá header → không thể giả mạo. Các service nói chuyện trực tiếp trong mạng Docker nên header đi nguyên.

Khi có người dùng, sales **chuyển tiếp chính JWT của người dùng** khi gọi inventory/finance (xem `03-giao-tiep-dong-bo.md`), để service đích vẫn kiểm tra quyền theo đúng người đó.

## 5. Thầy có thể hỏi

**H: JWT bị lộ thì sao? Thu hồi token thế nào?**
Đ: Token có hạn (`JWT_TTL`, mặc định 86400s = 24h). Vì stateless nên không thu hồi tức thì được trước hạn; đổi `JWT_SECRET` thì vô hiệu toàn bộ token. Hướng cải tiến: access token ngắn (15 phút) + refresh token, hoặc danh sách token bị thu hồi (cần store chung như Redis, mất một phần tính stateless).

**H: Vì sao HS256 mà không RS256?**
Đ: HS256 dùng một khoá đối xứng, đơn giản, nhanh. Nhược điểm: mọi service giữ khoá nên đều có thể *ký* token, một service bị lộ khoá là lộ toàn hệ thống. RS256 (khoá bí mật chỉ ở identity, các service giữ public key) an toàn hơn; đó là hướng cải tiến.

**H: Sao không kiểm tra JWT ở Kong luôn?**
Đ: Kong có plugin JWT nhưng bọn em vẫn cần kiểm tra ở service để lấy `permissions` phân quyền chi tiết và để service an toàn ngay cả khi bị gọi trực tiếp trong mạng nội bộ (defense in depth). Có thể thêm kiểm tra ở Kong để chặn sớm request rác.

**H: Đổi quyền của user thì token cũ còn quyền cũ?**
Đ: Đúng, quyền được "chụp" vào token lúc đăng nhập, có hiệu lực đến khi token hết hạn hoặc đăng nhập lại. Đây là đánh đổi của stateless (nhất quán sau / eventual consistency về quyền).

**H: Rate limit `policy: local` có vấn đề gì khi chạy 2 Kong?**
Đ: Mỗi Kong đếm riêng trong RAM → giới hạn thực tế nhân đôi. Muốn chính xác khi scale phải dùng `policy: redis`.

## 6. Điểm yếu
- Mọi service giữ `JWT_SECRET` (HS256) — xem câu hỏi trên.
- CORS `origins: ['*']` — chấp nhận được vì frontend cùng origin qua nginx, nhưng production nên giới hạn domain.
- Token 24h khá dài.

## 7. Tự kiểm tra
- [ ] Mở `kong.yml`, giải thích từng plugin trong 1 câu.
- [ ] Giải thích `strip_path` true/false và vì sao Laravel khác .NET.
- [ ] Vẽ luồng đăng nhập → gọi API, chỉ đúng file ký và file kiểm tra.
- [ ] Giải thích vì sao người ngoài không giả mạo được `X-Internal-Service-Key`.
- [ ] Thử: `curl -i http://localhost:8000/api/v1/sales/health` và tìm header `X-Correlation-ID` trong response.
