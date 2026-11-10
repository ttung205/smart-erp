# 02. Database trong hệ phân tán

> Mục tiêu: giải thích được **Database-per-service** là gì, project cài đặt nó ở mức nào, dữ liệu "của service khác" được lấy bằng cách nào khi không được JOIN, và vì sao chọn MySQL cho nghiệp vụ còn MongoDB cho notification/chat.

## 1. Nguyên tắc: mỗi service sở hữu dữ liệu của mình

- Chỉ service chủ sở hữu được **đọc/ghi trực tiếp** bảng của mình.
- Service khác muốn dữ liệu đó phải **hỏi qua API** (đồng bộ) hoặc **nghe event** rồi giữ bản sao (bất đồng bộ).
- **Không JOIN chéo database, không transaction chéo database.**

Vì sao? Nếu sales JOIN thẳng vào bảng `products` của catalog thì catalog không đổi được cấu trúc bảng mà không làm vỡ sales; hai service bị "dính" nhau qua database, mất lợi ích deploy độc lập. Database dùng chung là kiểu ghép nối (coupling) chặt nhất.

## 2. Cài đặt trong project

| Service | Kho dữ liệu | Khai báo |
|---|---|---|
| identity | MySQL `erp_identity_db` | [01-create-databases.sql:5](../../../infrastructure/mysql/init/01-create-databases.sql#L5) |
| catalog | MySQL `erp_catalog_db` | [:8](../../../infrastructure/mysql/init/01-create-databases.sql#L8) |
| purchase | MySQL `erp_purchase_db` | [:11](../../../infrastructure/mysql/init/01-create-databases.sql#L11) |
| inventory | MySQL `erp_inventory_db` | [:14](../../../infrastructure/mysql/init/01-create-databases.sql#L14) |
| sales | MySQL `erp_sales_db` | [:17](../../../infrastructure/mysql/init/01-create-databases.sql#L17) |
| eoffice | MySQL `erp_eoffice_db` | [:20](../../../infrastructure/mysql/init/01-create-databases.sql#L20) |
| ai-service | MySQL `erp_analytics_db` | [:23](../../../infrastructure/mysql/init/01-create-databases.sql#L23) |
| finance | MySQL `erp_finance_db` | [:26](../../../infrastructure/mysql/init/01-create-databases.sql#L26) |
| notification | MongoDB `erp_notification_db` | [docker-compose.yml:413](../../../docker-compose.yml#L413) |
| chat | MongoDB `erp_chat_db` + volume file | [docker-compose.yml:562-563](../../../docker-compose.yml#L562-L563) |

- Script trong `infrastructure/mysql/init/` được MySQL **tự chạy một lần khi volume còn trống** (mount vào `/docker-entrypoint-initdb.d`, [docker-compose.yml:18](../../../docker-compose.yml#L18)).
- Database test tách riêng `erp_*_test` ([02-create-test-databases.sql](../../../infrastructure/mysql/init/02-create-test-databases.sql)) để test `RefreshDatabase` không bao giờ xoá dữ liệu dev.
- Mỗi service chỉ được cấu hình **một** schema: ví dụ sales `DB_DATABASE: erp_sales_db` ([docker-compose.yml:304](../../../docker-compose.yml#L304)), inventory `Database=erp_inventory_db` trong connection string ([:260](../../../docker-compose.yml#L260)).

### Ba mức tách database (cần nói được)

| Mức | Nghĩa | Project |
|---|---|---|
| 1. Bảng riêng (table-per-service) | Chung DB, mỗi service một nhóm bảng | — |
| 2. **Schema riêng (schema-per-service)** | Chung MySQL server, mỗi service một schema | ✅ đang dùng |
| 3. Server riêng (database-server-per-service) | Mỗi service một MySQL/instance riêng | Hướng phát triển (RDS riêng) |

Cả 3 đều được xem là cách cài đặt mẫu Database-per-service (Chris Richardson, *microservices.io*). Điều cốt lõi là **ranh giới sở hữu**: không service nào chạm vào schema của service khác. Mức 2 và 3 khác nhau ở **cô lập tài nguyên** (CPU, RAM, I/O, sự cố), không khác ở thiết kế phần mềm.

## 3. Không JOIN được thì lấy dữ liệu của service khác thế nào?

### Cách 1: Gọi API khi cần (đồng bộ)
Sales cần thông tin sản phẩm/khách hàng → gọi catalog qua `ServiceClient.php` (có timeout, retry, circuit breaker; xem `03`).

### Cách 2: Chụp bản sao lúc tạo chứng từ (snapshot / denormalization)
Chứng từ lưu luôn **tên, giá tại thời điểm lập**, ví dụ hoá đơn VAT lưu `customer_id` + `customer_name`, `product_id` + `product_name` ([create_vat_invoices_table.php:27-54](../../../services/sales-service/database/migrations/2026_10_08_000002_create_vat_invoices_table.php#L27-L54)).
Lợi ích kép: (1) không cần gọi catalog mỗi lần xem hoá đơn, catalog chết vẫn xem được; (2) **đúng nghiệp vụ** — hoá đơn đã xuất thì tên/giá phải giữ nguyên dù sau này catalog đổi.

### Cách 3: Nghe event, giữ bản sao cục bộ (bất đồng bộ)
Catalog tạo sản phẩm → phát `catalog.product.created` → inventory tạo dòng tồn kho 0 ở mọi kho. Inventory có "bản sao" danh sách sản phẩm của riêng mình, nhất quán sau (eventual consistency) vài giây.

### Cách 4 (lệch chuẩn, đã biết): ETL đọc thẳng schema khác
ai-service đọc trực tiếp 6 schema nghiệp vụ để dựng kho phân tích ([ai-service/app/config.py:54-61](../../../services/ai-service/app/config.py#L54-L61), hàm `sync()` ở [warehouse.py:177](../../../services/ai-service/app/warehouse.py#L177)). **Mặc định tắt** (`WAREHOUSE_SYNC_ENABLED: ${AI_WAREHOUSE_SYNC_ENABLED:-false}`, [docker-compose.yml:601](../../../docker-compose.yml#L601)). Code tự ghi nhận đây là điểm lệch chuẩn; đích đúng là consume event qua queue `erp.analytics.events` (đã khai báo trong RabbitMQ) hoặc API export của từng service.
→ Khi bị hỏi, **chủ động nói**: "Đây là ngoại lệ có chủ đích cho phân tích báo cáo, chỉ đọc, tắt mặc định, và em biết cách sửa đúng là…". Thầy đánh giá cao việc biết mình lệch chuẩn ở đâu.

## 4. Không có transaction chéo database thì giữ nhất quán thế nào?

Đây là câu hỏi lớn nhất của phần DB. Trả lời theo 3 tầng:

1. **Trong một service:** transaction ACID bình thường của MySQL. Ví dụ ghi đơn hàng + ghi event vào `outbox_events` trong **cùng một transaction** → hoặc cả hai, hoặc không gì cả (Transactional Outbox, xem `04`).
2. **Giữa các service:** **Saga** — chuỗi transaction cục bộ, mỗi bước có **hành động bù trừ** khi bước sau thất bại. Ví dụ lập hoá đơn: sales giữ hàng ở inventory; nếu lưu hoá đơn lỗi thì gọi nhả hàng (xem `05`).
3. **Lưới an toàn:** job **đối soát** (reconciliation) chạy 5 phút/lần nhả các khoản giữ hàng "mồ côi" (`ReconcileStockReservations.php`).

Vì sao không dùng **2PC (two-phase commit / XA)**? Khoá tài nguyên ở mọi bên trong lúc chờ coordinator, một bên chết là cả giao dịch treo, giảm availability, và không phải công nghệ nào cũng hỗ trợ (MongoDB, RabbitMQ không tham gia XA với MySQL). Saga đổi "nhất quán tức thì" lấy "nhất quán sau + sẵn sàng cao" — đúng tinh thần CAP/BASE.

## 5. Ràng buộc trong DB dùng làm cơ chế phân tán

Không phải chỉ code mới chống lỗi, **index UNIQUE** là tuyến phòng thủ cuối cùng khi có trùng lặp/đồng thời:

| Ràng buộc | Chống lỗi gì | Chỗ |
|---|---|---|
| `outbox_events.event_id` UNIQUE | Một event không bị ghi 2 lần | [InventoryDbContext.cs:154](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L154), sales migration `create_outbox_events_table.php` |
| `processed_events` khoá chính `(event_id, consumer_name)` | Consumer xử lý cùng một message 2 lần (RabbitMQ giao lặp) | [InventoryDbContext.cs:162](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L162) |
| Sổ kho UNIQUE `(document_type, document_id, warehouse, product)` | Một chứng từ trừ kho 2 lần khi sales gọi lặp (retry) | [InventoryDbContext.cs:102](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L102), [:128](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L128) |
| Tồn kho UNIQUE `(warehouse, product)` | Hai dòng tồn cho cùng một sản phẩm/kho | [InventoryDbContext.cs:55](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L55) |
| Mongo `notifications` UNIQUE `(event_id, recipient_id)` | Một event tạo thông báo trùng cho cùng người | [notification db.js:23](../../../services/notification-service/src/db.js#L23) |
| Mongo `processed_events` TTL 30 ngày | Bảng chống trùng không phình mãi | [db.js:4](../../../services/notification-service/src/db.js#L4), [:26](../../../services/notification-service/src/db.js#L26) |

Câu chốt: *"Kiểm tra trong code (if đã xử lý thì bỏ qua) có thể thua race condition khi 2 bản sao message đến cùng lúc; index UNIQUE trong DB thì không bao giờ thua. Bọn em dùng cả hai."*

## 6. Vì sao MySQL cho nghiệp vụ, MongoDB cho notification/chat?

- **MySQL:** tiền, hàng, kế toán cần **ACID**, ràng buộc, JOIN trong phạm vi service, báo cáo tổng hợp.
- **MongoDB:** thông báo và tin nhắn là dữ liệu dạng tài liệu, **ghi nhiều, đọc theo người nhận**, cấu trúc linh hoạt (mỗi loại thông báo payload khác nhau), có **TTL index** tự xoá dữ liệu cũ. Đây là **polyglot persistence** — mỗi service chọn kho phù hợp với mình, chỉ làm được vì đã tách database.
- Mongo chạy **replica set 1 node** `rs0` ([docker-compose.yml:58-75](../../../docker-compose.yml#L58-L75)) vì chat-service dùng **multi-document transaction**, mà Mongo chỉ hỗ trợ transaction trên replica set. Healthcheck tự `rs.initiate()` lần đầu.

## 7. Migration (thay đổi cấu trúc DB)

- **Laravel:** migration có phiên bản (identity 14, catalog 9, sales 41, eoffice 9, finance 16 file). **Không** tự chạy khi container khởi động (`RUN_MIGRATIONS` mặc định false, [sales docker-entrypoint.sh:47](../../../services/sales-service/docker-entrypoint.sh#L47)); chạy có chủ đích bằng container một lần trong [scripts/powershell/deploy.ps1:31-44](../../../scripts/powershell/deploy.ps1#L31-L44) trước khi bật app.
  Vì sao không chạy lúc khởi động? Nếu sau này chạy 2 bản sales cùng lúc thì 2 bản cùng migrate → xung đột.
- **.NET (inventory, purchase):** `EnsureCreatedAsync` + `CREATE TABLE IF NOT EXISTS` ([purchase DbInitializer.cs:12](../../../services/purchase-service/src/Purchase.Infrastructure/Persistence/DbInitializer.cs#L12), [:168-177](../../../services/purchase-service/src/Purchase.Infrastructure/Persistence/DbInitializer.cs#L168)). Đơn giản nhưng **không có lịch sử phiên bản**, không sửa được cột đã có. Hướng cải tiến: EF Core Migrations.

## 8. Câu hỏi: có nên tách mỗi service một MySQL server riêng không?

**Quyết định của nhóm: giữ schema-per-service trên một MySQL server, nhưng tách tài khoản DB theo service.**

Lý do không tách server:
- Về thiết kế, ranh giới sở hữu đã đầy đủ (mục 2). Tách server chỉ thêm **cô lập tài nguyên**.
- Chi phí: 8–9 MySQL instance × ~400 MB RAM ≈ 3–4 GB chỉ cho DB, không vừa máy EC2 8 GB đang chạy 20 container; trên AWS nếu dùng RDS thì mỗi instance tính tiền riêng.
- Vận hành: 9 chỗ backup, 9 chỗ giám sát, 9 chỗ cập nhật — không tương xứng với quy mô đồ án.

Đã tách **tài khoản** (trước đây cả 9 schema cấp cho chung `erp_user`, ranh giới chỉ giữ bằng kỷ luật):
- Mỗi service một user chỉ có quyền trên schema của mình → MySQL **tự cưỡng chế** ranh giới; một service bị lộ mật khẩu không lộ dữ liệu service khác (least privilege).
- ai-service (`erp_analytics`) toàn quyền schema của mình, chỉ `SELECT` trên 6 schema nguồn của ETL.
- `erp_user` không service nào dùng; chỉ cho test (`erp_*_test`) và công cụ dev. Production đặt `MYSQL_DEV_TOOLS_ACCESS=false` → mất quyền trên dữ liệu thật.

| Chỉ vào code | |
|---|---|
| Tạo user + GRANT, chạy lại được (CREATE IF NOT EXISTS + ALTER) | [03-create-service-users.sh](../../../infrastructure/mysql/init/03-create-service-users.sh) |
| Mỗi service dùng user riêng | `DB_USERNAME: erp_sales` … trong [docker-compose.yml](../../../docker-compose.yml) |
| Production bắt buộc đủ 8 mật khẩu, không dùng giá trị mặc định | [docker-compose.prod.yml](../../../docker-compose.prod.yml), [validate-prod-config.ps1](../../../scripts/powershell/validate-prod-config.ps1) |
| Áp lên volume đã có | [scripts/powershell/apply-db-users.ps1](../../../scripts/powershell/apply-db-users.ps1) |

**Demo trước thầy** (bằng chứng ranh giới do DB cưỡng chế):
```bash
docker compose exec -e MYSQL_PWD=<SALES_DB_PASSWORD> mysql mysql -uerp_sales -e "SELECT * FROM erp_inventory_db.inventory_stocks"
# ERROR 1142: SELECT command denied to user 'erp_sales' for table 'inventory_stocks'
```

Câu trả lời mẫu khi thầy hỏi: *"Bọn em tách theo schema trên một MySQL server, mỗi service có tài khoản chỉ được truy cập schema của mình, nên ranh giới sở hữu dữ liệu được chính MySQL cưỡng chế. Tách server vật lý chỉ thêm cô lập tài nguyên; với quy mô hiện tại bọn em đánh đổi điều đó lấy chi phí và vận hành. Khi cần, đổi `DB_HOST` của một service sang một RDS riêng là xong, không phải sửa code, vì vốn dĩ không có truy vấn chéo schema."*

## 9. Thầy có thể hỏi

**H: Báo cáo cần dữ liệu từ nhiều service thì làm sao?**
Đ: Không JOIN chéo. Hoặc gọi API từng service rồi gộp ở tầng ứng dụng (API composition), hoặc dựng kho phân tích riêng được nạp từ event (CQRS / data warehouse). ai-service đang là kho phân tích nhưng nạp bằng ETL đọc trực tiếp — điểm lệch chuẩn đã biết.

**H: Dữ liệu bị trùng lặp (tên khách hàng ở cả catalog và sales) có sai không?**
Đ: Là có chủ đích. Trong microservice, nhân bản dữ liệu là chuyện bình thường; quan trọng là xác định rõ **nguồn sự thật** (catalog) và bản sao chỉ để đọc. Với chứng từ thì bản chụp còn là yêu cầu nghiệp vụ.

**H: Một MySQL chết thì sao?**
Đ: Mọi service MySQL cùng chết — single point of failure. Hướng phát triển: RDS Multi-AZ (bản dự phòng ở AZ khác, tự failover). Notification và chat (Mongo) vẫn sống.

**H: Một MySQL server mà nhiều tài khoản thì một service có thể làm chậm service khác không?**
Đ: Có — tài khoản riêng cô lập *quyền*, không cô lập *tài nguyên*. Đó là phần đánh đổi khi không tách server.

## 10. Tự kiểm tra
- [ ] Kể 3 mức tách database và project đang ở mức nào.
- [ ] Kể 3 cách lấy dữ liệu service khác mà không JOIN, mỗi cách một ví dụ có thật.
- [ ] Giải thích vì sao không dùng 2PC.
- [ ] Chỉ ra 3 index UNIQUE dùng để chống trùng và mỗi cái chống trường hợp nào.
- [ ] Giải thích vì sao Mongo phải chạy replica set dù chỉ 1 node.
- [ ] Trả lời câu "có nên tách server riêng không" trong 30 giây.
