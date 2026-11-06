# Bài 05 — Chương 5: Định danh (Naming & Identity)

> Rubric Chương 5 (1 điểm), gợi ý: UUID/GUID, DNS & IP, service registry, token authentication (JWT), chứng chỉ SSL/TLS, URI tài nguyên.
> Mục tiêu bài này: chỉ được vào code/cấu hình và nói được **"cái gì đang được đặt tên, ai cấp tên, ai kiểm tra tên"** cho từng loại thực thể trong hệ thống.

## 1. Ý tưởng cốt lõi

Trong hệ phân tán, mọi thực thể (người dùng, service, container, máy chủ, thông điệp, yêu cầu) đều cần một **định danh** để:
1. **Tìm thấy** nó (name → địa chỉ): DNS, URI.
2. **Phân biệt** nó với cái khác (unique): UUID, mã nghiệp vụ, id tự tăng.
3. **Tin** rằng nó đúng là nó (xác thực): JWT, service key, chứng chỉ TLS, IAM role.

Ba câu hỏi lặp lại cho mọi định danh: **Ai cấp? Có duy nhất không? Ai kiểm tra, kiểm tra bằng gì?**

## 2. Bản đồ định danh trong smart-erp

| Thực thể | Định danh | Ai cấp | Ai kiểm tra |
|---|---|---|---|
| Người dùng | `sub` (id số) + `employee_code` (SE-9999) + email | identity-service (DB, `UNIQUE`) | JWT + OTP khi đăng nhập |
| Phiên đăng nhập | JWT HS256 (`iss, sub, roles, permissions, iat, exp`) | identity-service ký | **mỗi service tự verify** (không gọi lại identity) |
| Service gọi service (không có người dùng) | header `X-Internal-Service-Key` | cấu hình `INTERNAL_SERVICE_KEY` | service nhận, so sánh thời gian cố định |
| Service (để tìm nhau) | tên Docker DNS: `inventory-service` | Docker (tên service trong compose) | — |
| Container | IP trong `erp-network` (172.22.0.x) + hostname | Docker, **đổi mỗi lần recreate** | — |
| Một yêu cầu xuyên nhiều service | `X-Correlation-ID` | Kong sinh (UUID) hoặc do client đặt | mọi service chuyển tiếp + ghi log |
| Một sự kiện | `event_id` (UUID) | producer (outbox) | consumer ghi `processed_events` |
| Bên phát sự kiện | `app_id`/`PRODUCER` = `catalog-service`… | producer | consumer |
| Chứng từ bán | `INV-{mã khách}-{ddmmyy}[-A..]` | sales (ràng buộc UNIQUE) | inventory (idempotent theo số chứng từ) |
| Bản sao lưu | `database-backup-<yyyymmdd>-<NN>` | backup.sh | regex `SET_RE` |
| Tài khoản DB | 8 user MySQL + `erp_backup` | script init | MySQL |
| Máy chủ EC2 | Instance ID, Elastic IP 18.143.81.29, tên `18-143-81-29.sslip.io` | AWS / sslip.io | — |
| Quyền của EC2 | IAM role `smart-erp-ec2-role` (credential tạm qua IMDS) | AWS | S3/IAM |
| Quyền của GitHub Actions | OIDC token → assume role (không lưu secret) | GitHub ký, AWS kiểm tra trust policy | AWS STS |
| Tài nguyên | URI REST, S3 URI, ARN, image tag `sha-xxxxxxx` | — | — |

## 3. Từng mục theo gợi ý của rubric

### 3.1. UUID/GUID — định danh duy nhất không cần điều phối trung tâm
- Vì sao dùng UUID cho event/correlation: nhiều service cùng sinh id **độc lập**, không cần hỏi một nơi cấp số (id tự tăng của DB chỉ duy nhất **trong một DB**, mà ta database-per-service). Xác suất trùng UUID v4 (122 bit ngẫu nhiên) gần như bằng 0.
- Code: `Str::uuid()` trong `Outbox.php` (catalog, eoffice, sales) cho `event_id`; `.NET` dùng `Guid.NewGuid()` (dạng `N`) cho correlation id trong middleware; Kong plugin `correlation-id` ở [kong.yml:147](../../../infrastructure/kong/kong.yml#L147) sinh UUID khi client không gửi.
- `event_id` là **khóa idempotency**: consumer ghi `(event_id, consumer_name)` vào `processed_events` → nhận trùng thì bỏ qua (bài 04).
- Phân biệt: **id tự tăng** (đọc dễ, theo thứ tự, chỉ duy nhất trong 1 DB), **UUID** (duy nhất toàn cục, không đoán được, nhưng dài và index kém hơn), **mã nghiệp vụ** (`SE-9999`, `INV-…`: người đọc được, có quy tắc, bảo đảm duy nhất bằng UNIQUE INDEX).

### 3.2. DNS & IP — tìm service bằng tên
- Trong Docker network `erp-network`, Docker chạy DNS nội bộ: tên service trong compose phân giải thành IP container. Chạy thử (dev):
  ```
  docker compose exec -T sales-service nslookup inventory-service
  → Name: inventory-service   Address: 172.22.0.12
  ```
- Code gọi `INVENTORY_SERVICE_URL=http://inventory-service:8004`, **không bao giờ hardcode IP** (IP đổi khi container được tạo lại). Đây chính là lý do CLAUDE.md cấm `localhost:800x`/`127.0.0.1`.
- **Không có service registry riêng** (Consul/Eureka/etcd): Docker DNS đóng vai trò registry tĩnh — tên đăng ký tự động khi container chạy, cư dân mạng tìm theo tên. Hạn chế thật (nói thẳng khi bảo vệ): không có health-aware discovery, không có cân bằng tải nhiều bản sao qua DNS round-robin có kiểm tra sức khỏe, vì mọi service có `container_name` nên chưa scale được. Hướng nâng cấp: bỏ `container_name`, dùng `--scale` + Docker DNS round-robin, hoặc Kong upstream với active health check, hoặc Kubernetes Service.
- Ở biên ngoài: DNS công khai. Tên miền `18-143-81-29.sslip.io` là dịch vụ DNS "wildcard" trả về IP nằm trong chính tên (18.143.81.29). Elastic IP giữ IP không đổi khi stop/start EC2 (IP public thường sẽ đổi), nên tên miền và chứng chỉ không vỡ.
- Hostname cố định của RabbitMQ (`docker-compose.yml:46`): RabbitMQ lưu dữ liệu theo tên node `rabbit@<hostname>`; nếu hostname đổi khi recreate, node mới là node trống → mất queue. Ví dụ rất tốt về "định danh gắn với dữ liệu".

### 3.3. Token authentication — JWT
Luồng (chi tiết ở bài 01):
1. Client `POST /api/v1/auth/login` (hoặc `request-otp` → `verify-otp`) → [AuthController.php](../../../services/identity-service/app/Http/Controllers/AuthController.php) (login dòng 66, requestOtp 103, verifyOtp 157, session 226).
2. identity ký JWT bằng [JwtIssuer.php](../../../services/identity-service/app/Auth/JwtIssuer.php): header `{"typ":"JWT","alg":"HS256"}`, payload `iss, sub, name, email, employee_code, department_id, roles, permissions, iat, exp`.
3. Mỗi service **tự verify**, không hỏi lại identity: kiểm tra `alg == HS256` (chặn tấn công `alg: none`), chữ ký HMAC khớp `JWT_SECRET` (so sánh thời gian cố định: `FixedTimeEquals`/`hash_equals`), `iss == identity-service`, có `sub`, `exp` chưa quá hạn. Các bản cài đặt cùng một luật: `JwtVerifier.php` (PHP), `HmacJwtTokenValidator.cs` ×3 (.NET, dòng 44 và 53), `jwt.js` (Node), `auth.py` (Python).

Kết quả chạy thật trên dev (giải mã payload, **base64url — không phải mã hóa**):
```
header : {'typ': 'JWT', 'alg': 'HS256'}
payload: {'iss': 'identity-service', 'sub': '6', 'employee_code': 'SE-9999',
          'roles': ['SUPER_ADMIN'], 'permissions': [71 quyền], 'iat': 1793939293, 'exp': 1794025693}
chữ ký : 43 ký tự (HMAC-SHA256)
```
`exp - iat = 86400` giây → token sống 24 giờ.

Điểm phải nói được:
- **JWT ký, không mã hóa**: ai cũng đọc được payload → không nhét bí mật vào. Chữ ký chỉ bảo đảm **không bị sửa**.
- **Stateless**: không cần session store chung → dễ mở rộng, đây là lý do chọn JWT trong kiến trúc phân tán.
- **HS256 = khóa đối xứng**: mọi service giữ cùng `JWT_SECRET` ⇒ service nào bị lộ cũng có thể **giả mạo token**. Trade-off thật. Phương án tốt hơn: RS256/ES256 (identity giữ khóa riêng, service khác chỉ giữ khóa công khai, có thể công bố qua JWKS).
- **Không thu hồi được token trước khi hết hạn** (stateless). Giảm thiểu: `exp` ngắn, hoặc danh sách thu hồi/refresh token. Trong repo hiện chưa có refresh token (nói thẳng).
- **Quyền nằm trong token** (`permissions`): kiểm tra tại service owner theo CLAUDE.md §8, không tin ẩn nút ở frontend. Quyền đổi mà token cũ chưa hết hạn → vẫn hiệu lực đến khi hết hạn.

**Định danh service-to-service**: lời gọi không có người dùng (job đối soát của sales) dùng header `X-Internal-Service-Key` ([JwtAuthMiddleware.cs:63-66](../../../services/inventory-service/src/Inventory.Api/Middlewares/JwtAuthMiddleware.cs#L63), so bằng `FixedTimeEquals`). Đường `/internal/*` không đi qua gateway. Hạn chế: **một khóa chung** cho mọi service, không biết "ai" gọi. Nâng cấp: khóa/JWT riêng mỗi service (`iss=sales-service`), hoặc mTLS.

**Xác thực người dùng bằng OTP** ([LoginOtpService.php](../../../services/identity-service/app/Services/LoginOtpService.php)): mã 6 số, hết hạn 5 phút, dùng một lần, tối đa 5 lần thử, lưu **HMAC** chứ không lưu mã gốc, so bằng `hash_equals` (dòng 91), tiêu thụ bằng UPDATE có điều kiện nguyên tử (cùng tư duy atomic guard của tồn kho — hai request đồng thời không dùng được cùng một mã hai lần).

### 3.4. Chứng chỉ SSL/TLS
- Trên AWS, [Caddy](../../../infrastructure/caddy/Caddyfile) là điểm kết thúc TLS: tự xin và gia hạn chứng chỉ Let's Encrypt cho `SITE_ADDRESS` (giao thức ACME, thử thách HTTP-01 qua cổng 80), sau đó chuyển HTTP thuần vào mạng nội bộ (`reverse_proxy gateway:8000`).
- Chứng chỉ chứng minh **"máy chủ này sở hữu tên miền này"** (do CA tin cậy ký) và cung cấp khóa để mã hóa kênh. Chuỗi tin cậy: chứng chỉ lá → CA trung gian → CA gốc có sẵn trong trình duyệt.
- Mạng nội bộ Docker **không mã hóa** (HTTP thuần): chấp nhận được vì mạng cô lập, chỉ Caddy mở 80/443; nói thẳng là mTLS giữa service chưa làm.
- Kiểm tra nhanh: `curl -vI https://18-143-81-29.sslip.io/health` hiển thị issuer Let's Encrypt, thời hạn chứng chỉ.
- Hệ quả vận hành: sslip.io + Let's Encrypt có giới hạn số chứng chỉ theo tên miền; đổi IP = đổi tên = xin chứng chỉ mới.

### 3.5. Danh tính của máy và pipeline (AWS)
- **EC2 role** `smart-erp-ec2-role`: máy chủ có danh tính IAM, nhận credential **tạm thời** từ IMDS (IMDSv2, hop limit 2 để container trong Docker cũng lấy được) → ghi S3 không cần access key lưu trên đĩa.
- **GitHub OIDC**: workflow xin token OIDC do GitHub ký (claim `sub` chứa repo/branch/environment); AWS STS kiểm tra **trust policy** (đúng repo, đúng environment) rồi cấp credential tạm cho role deploy. Không có secret dài hạn trong GitHub. Đây là "định danh bằng chữ ký của bên thứ ba tin cậy" — cùng nguyên lý JWT/chứng chỉ.
- **SSM Session Manager/Run Command**: đăng nhập và chạy lệnh bằng danh tính IAM, không mở cổng 22.

### 3.6. URI/tài nguyên
- REST: `/api/v1/{prefix}/{danh-từ-số-nhiều}` — URI định danh **tài nguyên** (`/delivery-requests/42`), động từ HTTP chỉ hành động; hành động đặc biệt dùng `POST /orders/{id}/confirm`.
- Prefix gateway ánh xạ một–một vào service sở hữu (`/api/v1/inventory/` → inventory-service:8004) — URI cũng là **bản đồ quyền sở hữu dữ liệu**.
- Các loại định danh tài nguyên khác bạn gặp: `s3://smart-erp-backup/smart-erp`, ARN của IAM role, `ghcr.io/ttung205/smart-erp-sales-service:sha-xxxxxxx` (tag theo commit = định danh bất biến của bản build; `latest` thì **không** bất biến).

### 3.7. Định danh của một yêu cầu: `X-Correlation-ID`
Chạy thật:
```
curl -D - http://localhost:8000/health        → X-Correlation-ID: 58c0a47c-240d-4bf2-abd2-c86d2015e422  (Kong sinh)
curl -H 'X-Correlation-ID: toi-tu-dat-123' …  → X-Correlation-ID: toi-tu-dat-123                         (giữ nguyên)
```
Mọi service chuyển tiếp và ghi vào log; tìm trong Loki một id là thấy cả hành trình qua nhiều service. Lưu ý bảo mật: giá trị do client đặt được giữ nguyên ⇒ coi là **không đáng tin** (chỉ để truy vết, không dùng để phân quyền, và log phải chống chèn dòng).

## 4. Điểm yếu cần thừa nhận (trả lời thật khi bị hỏi)
1. HS256 dùng khóa chung: lộ khóa ở một service = giả mạo được mọi người dùng. Hướng: RS256 + JWKS.
2. Không refresh token, không thu hồi token trước hạn (24 giờ).
3. `INTERNAL_SERVICE_KEY` chung cho mọi service, không phân biệt người gọi.
4. Không có service registry thật; mạng nội bộ không mã hóa; chưa mTLS.
5. Container đổi IP khi recreate ⇒ ổn vì dùng tên DNS, nhưng không có health-aware discovery.
6. Correlation id từ client được tin nguyên văn (chỉ dùng truy vết).

## 5. Câu hỏi hay gặp
1. **Vì sao dùng UUID cho event_id mà không dùng id tự tăng?** Nhiều producer, mỗi DB riêng, không có nơi cấp số chung; UUID sinh cục bộ, không đụng nhau.
2. **JWT có mã hóa không?** Không, chỉ ký. Payload đọc được.
3. **Vì sao mỗi service tự verify JWT thay vì hỏi identity?** Tránh điểm nghẽn/điểm chết đơn (identity sập thì cả hệ thống không xác thực được), giảm độ trễ; đổi lại không thu hồi tức thì được.
4. **Token bị đánh cắp thì sao?** Dùng được đến khi hết hạn; giảm thiểu bằng HTTPS, `exp` ngắn, thu hồi/refresh token (chưa làm).
5. **Chặn tấn công `alg: none`/đổi thuật toán thế nào?** Code kiểm tra cứng `alg == HS256` trước khi tính chữ ký.
6. **Vì sao không hardcode IP service?** IP container đổi khi recreate; dùng tên DNS Docker.
7. **Hệ thống có service registry không?** Không riêng; Docker DNS là registry tĩnh. Nâng cấp: Consul, hoặc K8s Service.
8. **HTTPS kết thúc ở đâu? Trong mạng có mã hóa không?** Ở Caddy; bên trong HTTP thuần trong mạng cô lập.
9. **Làm sao chứng chỉ tự gia hạn?** Caddy dùng ACME tự xin lại trước hạn; cần cổng 80/443 mở và volume lưu chứng chỉ.
10. **GitHub deploy lên AWS không có access key, vì sao an toàn?** OIDC: token ngắn hạn do GitHub ký, trust policy giới hạn repo/environment, credential STS hết hạn sau ít phút/giờ.
11. **Truy vết một giao dịch qua nhiều service?** `X-Correlation-ID` do Kong sinh, mọi service chuyển tiếp và log; tra bằng Loki.
12. **Hai request cùng dùng một OTP?** UPDATE có điều kiện nguyên tử, chỉ một request đổi được trạng thái.

## 6. Bài tập thực hành (bài 1, 4 và phần nslookup của bài 2 đã chạy trên dev; sửa payload cho kết quả 401; phần recreate, Loki và chứng chỉ AWS bạn tự chạy để kiểm chứng)
1. Đăng nhập lấy token, giải mã payload (đoạn Python ở §3.3 – tách bằng dấu `.`, base64url decode). Sửa 1 ký tự trong payload rồi gọi API → phải `401`.
2. `docker compose exec -T sales-service nslookup inventory-service`; `docker compose up -d --force-recreate inventory-service` rồi tra lại: **IP đổi, tên không đổi**.
3. Gửi `X-Correlation-ID` tự đặt, tìm id đó trong Loki/Grafana (Explore → Loki → `{service=~".+"} |= "toi-tu-dat-123"`).
4. `docker network inspect smart-erp_erp-network` — xem bảng IP của các container.
5. Trên AWS: `curl -vI https://<tên>.sslip.io` đọc issuer, ngày hết hạn chứng chỉ.

## 7. Tự kiểm tra (trả lời bằng lời của bạn)
- Kể 5 loại định danh khác nhau trong hệ thống và ai cấp từng loại.
- Vì sao JWT stateless thuận lợi cho phân tán, và cái giá phải trả?
- Tên → IP được phân giải ở đâu khi sales gọi inventory? Ở biên ngoài thì sao?
- Nếu mai đổi sang RS256, service nào phải đổi gì?
