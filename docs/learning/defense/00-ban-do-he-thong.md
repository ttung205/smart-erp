# 00. Bản đồ hệ thống Smart ERP

> Mục tiêu buổi này: nhắm mắt vẽ lại được sơ đồ dưới đây, và với mỗi ô nói được "nó là gì, viết bằng gì, lưu dữ liệu ở đâu, nói chuyện với ai bằng cách nào".
> Khi thầy hỏi "em giới thiệu kiến trúc hệ thống", đây là câu trả lời 2 phút đầu tiên.

## 1. Một câu tóm tắt

Smart ERP là hệ thống ERP cho doanh nghiệp phân phối B2B, chia thành **10 microservice** viết bằng **4 ngôn ngữ** (PHP/Laravel, C#/.NET, Node.js, Python), mỗi service **sở hữu database riêng**, đi qua **một cổng API Gateway (Kong)**, giao tiếp **đồng bộ bằng REST** khi cần câu trả lời ngay và **bất đồng bộ bằng RabbitMQ** (kèm Transactional Outbox) khi chỉ cần báo cho service khác biết "đã có chuyện xảy ra". Toàn bộ chạy bằng Docker Compose.

## 2. Sơ đồ tổng thể

```
                          Trình duyệt (Vue 3 SPA)
                                   │  HTTP + WebSocket
                                   ▼
                    frontend (nginx :80 → host :5175)
                    - trả file tĩnh SPA
                    - /api/* → proxy sang gateway
                                   │
                                   ▼
                    Kong API Gateway :8000 (DB-less)
       rate-limit │ correlation-id │ CORS │ xoá X-Internal-Service-Key │ prometheus
                                   │
   ┌──────────┬──────────┬─────────┼──────────┬──────────┬──────────┬──────────┐
   ▼          ▼          ▼         ▼          ▼          ▼          ▼          ▼
identity   catalog    purchase  inventory   sales     eoffice    finance   ai-service
 :8001      :8002      :8003     :8004      :8005      :8007      :8008      :8010
 PHP        PHP        .NET      .NET       PHP        PHP        PHP       Python
   │          │          │         │          │          │          │          │
   └──────────┴──── MySQL 8 (mỗi service 1 schema riêng: erp_*_db) ──┴──────────┘

   notification :8006 (Node, Socket.IO)  ──► MongoDB (erp_notification_db)
   chat         :8009 (.NET, SignalR)    ──► MongoDB (erp_chat_db) + volume file

           ┌────────────────── RabbitMQ (exchange topic erp.events) ──────────────────┐
 Publisher: catalog, sales, eoffice (qua *-relay), purchase, inventory (worker nội bộ)
 Consumer : inventory (erp.inventory.events), notification (erp.notification.events, erp.email.send)
           └──────────────── lỗi → retry queue → erp.dlx → erp.dead_letters ───────────┘
```

## 3. Bảng các thành phần (nói được hết bảng này)

| Thành phần | Công nghệ | Cổng | Lưu trữ | Vai trò | Khai báo |
|---|---|---|---|---|---|
| mysql | MySQL 8.0 | 3306 | volume `mysql_data` | 9 schema, mỗi service 1 schema | [docker-compose.yml:7](../../../docker-compose.yml#L7) |
| rabbitmq | RabbitMQ 3.13 | 5672 / 15672 UI | volume | Message broker | [docker-compose.yml:29](../../../docker-compose.yml#L29) |
| mongo | MongoDB 7, replica set `rs0` 1 node | 27017 | volume | Notification + chat | [docker-compose.yml:58](../../../docker-compose.yml#L58) |
| mailpit | SMTP giả lập | 1025 / 8125 UI | — | Xem email gửi đi | [docker-compose.yml:77](../../../docker-compose.yml#L77) |
| identity-service | Laravel | 8001 | `erp_identity_db` | Người dùng, vai trò, quyền; **ký JWT** | [:93](../../../docker-compose.yml#L93) |
| catalog-service + catalog-relay | Laravel | 8002 | `erp_catalog_db` | Danh mục sản phẩm, khách hàng, NCC; relay đẩy outbox | [:132](../../../docker-compose.yml#L132), [:178](../../../docker-compose.yml#L178) |
| purchase-service | ASP.NET Core .NET 10 | 8003 | `erp_purchase_db` | Mua hàng, nhập kho, hỏi hàng NCC | [:208](../../../docker-compose.yml#L208) |
| inventory-service | .NET 10 | 8004 | `erp_inventory_db` | Tồn kho, sổ kho, **giữ hàng (reservation)** | [:248](../../../docker-compose.yml#L248) |
| sales-service + sales-relay + sales-scheduler | Laravel | 8005 | `erp_sales_db` | Báo giá, đơn bán, giao hàng, hoá đơn; job đối soát 5 phút | [:284](../../../docker-compose.yml#L284), [:336](../../../docker-compose.yml#L336), [:366](../../../docker-compose.yml#L366) |
| notification-service | Node 22, Express, Socket.IO | 8006 | Mongo | Hộp thư thông báo, đẩy realtime, gửi email | [:391](../../../docker-compose.yml#L391) |
| eoffice-service + eoffice-relay | Laravel | 8007 | `erp_eoffice_db` | Nghỉ phép, chấm công, lương, đề nghị | [:433](../../../docker-compose.yml#L433), [:481](../../../docker-compose.yml#L481) |
| finance-service | Laravel | 8008 | `erp_finance_db` | Kế toán, công nợ | [:511](../../../docker-compose.yml#L511) |
| chat-service | .NET 10, SignalR | 8009 | Mongo + volume file | Chat 1-1, nhóm | [:550](../../../docker-compose.yml#L550) |
| ai-service | Python FastAPI, Groq LLM | 8010 | `erp_analytics_db` | Trợ lý AI, phân tích | [:585](../../../docker-compose.yml#L585) |
| gateway | Kong 3.9.1 DB-less | 8000 | — | Điểm vào duy nhất cho API | [:627](../../../docker-compose.yml#L627) |
| frontend | Vue 3 + nginx | 5175 | — | Giao diện | [:715](../../../docker-compose.yml#L715) |
| loki, alloy, prometheus, grafana | profile `monitoring` | — | — | Giám sát (bật thêm khi cần) | [:735](../../../docker-compose.yml#L735)–[:794](../../../docker-compose.yml#L794) |

Đếm container: **20** mặc định (4 hạ tầng + 10 API + 3 relay + 1 scheduler + Kong + frontend), **24** khi bật monitoring.

**Vì sao một service lại có 2–3 container (sales, sales-relay, sales-scheduler)?** Cùng một image/codebase, nhưng chạy 3 *tiến trình* khác nhau:
- `sales-service`: web server nhận HTTP.
- `sales-relay`: `php artisan outbox:relay`, vòng lặp đọc bảng `outbox_events` đẩy lên RabbitMQ.
- `sales-scheduler`: `php artisan schedule:work`, chạy job định kỳ (đối soát giữ hàng 5 phút/lần).

Tách tiến trình để web chết thì relay vẫn chạy và ngược lại, và mỗi thứ restart độc lập. Đây là nguyên tắc "một container một tiến trình".

## 4. Ba kiểu giao tiếp trong hệ thống

| Kiểu | Dùng khi | Ví dụ trong project | Tài liệu chi tiết |
|---|---|---|---|
| **Đồng bộ REST** (request/response) | Cần câu trả lời ngay mới đi tiếp được | Sales gọi Inventory giữ hàng khi lập hoá đơn; Purchase gọi Catalog lấy sản phẩm | `03-giao-tiep-dong-bo.md` |
| **Bất đồng bộ qua RabbitMQ** (publish/subscribe event) | Chỉ cần báo "đã xảy ra", không cần chờ | Sales duyệt đơn → event → Notification báo cho người tạo; Purchase nhập kho → event → Inventory cộng tồn | `04-bat-dong-bo-outbox-rabbitmq.md` |
| **Realtime tới trình duyệt** (WebSocket) | Server chủ động đẩy cho người dùng | Socket.IO (thông báo), SignalR (chat) | `04`, `07` |

Câu "chốt" khi bảo vệ: *"Bọn em chọn đồng bộ chỉ khi nghiệp vụ không thể tiếp tục nếu thiếu câu trả lời (ví dụ không giữ được hàng thì không được lập hoá đơn). Còn lại dùng event để các service không phụ thuộc nhau về thời gian sống: Notification chết thì Sales vẫn duyệt đơn bình thường, thông báo sẽ đến sau."*

## 5. Bảy luồng nghiệp vụ xuyên service (chỉ cần nói lướt)

1. **Bán hàng:** báo giá → đơn bán (gửi duyệt, duyệt/từ chối) → mỗi bước ghi outbox → sales-relay đẩy event → notification báo realtime + email báo giá.
2. **Hoá đơn (luồng quan trọng nhất về phân tán):** lập hoá đơn → Sales gọi REST Inventory **giữ hàng** (có bù trừ nếu lỗi) → gửi kho → ghi sổ thì gọi `/stocks/issue` → Inventory phát `inventory.goods.issued` → notification. Huỷ thì nhả hàng; job đối soát dọn hàng giữ "treo".
3. **Nhập hàng mua:** Purchase ghi phiếu nhập + outbox `purchase.goods.received` → Inventory tiêu thụ event (idempotent) → cộng tồn.
4. **Sản phẩm mới:** Catalog phát `catalog.product.created` → Inventory tạo dòng tồn 0 ở mọi kho, notification báo.
5. **Hỏi hàng NCC:** Purchase phát `purchase.inquiry.*` → notification gửi email NCC; áp kết quả gọi route nội bộ của Catalog.
6. **Nhân sự (eoffice):** nghỉ phép, chấm công, đề nghị, lương → event `eoffice.*` → thông báo; chốt lương gọi REST Finance (có circuit breaker).
7. **Chat:** chat gọi Identity lấy danh bạ (Polly retry/breaker), đẩy tin bằng SignalR.

## 6. Thầy có thể hỏi

**H: Vì sao chọn microservice mà không làm monolith?**
Đ: ERP có nhiều miền nghiệp vụ tách bạch (bán, mua, kho, kế toán, nhân sự), mỗi nhóm thành viên phụ trách một miền, có thể chọn công nghệ phù hợp (kho dùng .NET vì xử lý tồn kho cần transaction chặt và typed; notification dùng Node vì I/O realtime). Mỗi service deploy và hỏng độc lập. Đổi lại, bọn em phải giải quyết các vấn đề của hệ phân tán: nhất quán dữ liệu giữa các DB, lỗi mạng, gửi trùng message, giám sát nhiều nơi. Phần lớn công sức của project nằm ở việc xử lý các vấn đề đó.

**H: Làm sao các service tìm thấy nhau?**
Đ: Dùng DNS nội bộ của Docker: các container chung mạng `erp-network` gọi nhau bằng tên service, ví dụ `http://inventory-service:8004`. Đây là service discovery đơn giản dựa trên DNS. Kong cũng trỏ tới upstream bằng tên. Kong đặt `KONG_DNS_VALID_TTL=10` ([docker-compose.yml](../../../docker-compose.yml) phần gateway) để khi container được tạo lại với IP mới thì 10 giây sau Kong đã phân giải lại, không bị 502 đến 10 phút.

**H: Nếu RabbitMQ chết thì sao? Nếu MySQL chết thì sao?**
Đ: RabbitMQ chết: nghiệp vụ vẫn chạy vì event được ghi vào bảng outbox trong cùng transaction, relay sẽ gửi lại khi broker sống. MySQL chết: service tương ứng không phục vụ được (đây là single point of failure, xem phần điểm yếu). Healthcheck báo unhealthy, `restart: unless-stopped` khởi động lại.

**H: Hệ thống có scale ngang được không?**
Đ: Các API stateless (JWT tự kiểm tra, không session) nên về nguyên tắc chạy nhiều bản sau Kong được. Ngoại lệ: relay chỉ chạy 1 bản (khoá `flock` để giữ thứ tự event), chat dùng SignalR chưa có Redis backplane nên chỉ 1 bản, MySQL/RabbitMQ đơn node. Bọn em biết và ghi là hướng phát triển.

## 7. Điểm yếu (nói thật khi bị hỏi)

- **Single point of failure:** MySQL, RabbitMQ, Mongo, Kong đều 1 bản; trên AWS cũng chỉ 1 máy EC2. Hướng phát triển: RDS Multi-AZ, RabbitMQ cluster/quorum queue, nhiều instance sau load balancer.
- **Một MySQL server chung** cho nhiều schema: tách ở mức logic (schema riêng), chưa tách vật lý (server riêng). Một service chạy query nặng có thể ảnh hưởng service khác.

## 8. Tự kiểm tra
- [ ] Vẽ lại sơ đồ mục 2 trên giấy trong 3 phút.
- [ ] Nói được service nào dùng ngôn ngữ gì, DB gì, cổng bao nhiêu.
- [ ] Giải thích vì sao sales có 3 container.
- [ ] Phân biệt 3 kiểu giao tiếp và cho mỗi kiểu một ví dụ có thật trong code.
