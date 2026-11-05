# 06. Chương 6: Đồng bộ hóa

> Rubric: "Các cơ chế đồng bộ hóa tiến trình trong hệ thống phân tán" (1 điểm).
> Gợi ý của rubric: Distributed Locking (Redlock, Zookeeper), Message Queues, State Management (CQRS, Event Sourcing), Concurrency Control (optimistic/pessimistic locking).
> Project **không dùng khoá phân tán**; thay vào đó dùng **thao tác nguyên tử của database + ràng buộc UNIQUE + saga**. Bài này giải thích vì sao đó là lựa chọn đúng cho bài toán này, và nói thật các giới hạn.

## 1. Bài toán

Trong hệ phân tán không có bộ nhớ chung và không có đồng hồ chung. Nhiều tiến trình (nhiều request, nhiều service, nhiều bản sao message) cùng chạm vào một dữ liệu. Ba loại sự cố cụ thể trong ERP:

| Sự cố | Ví dụ trong project |
|---|---|
| **Tranh chấp** (race): hai bên cùng sửa một dữ liệu | Hai sale cùng giữ 10 cái hàng khi kho chỉ còn 15 |
| **Lặp** (duplicate): cùng một việc bị làm hai lần | Sales gọi lại "giữ hàng" vì chưa nhận được phản hồi; message RabbitMQ bị giao hai lần |
| **Lệch trạng thái** giữa các service | Inventory đã giữ hàng nhưng Sales rollback chứng từ |

## 2. Bản đồ cơ chế (đối chiếu với rubric)

| Nhóm rubric | Project có? | Cơ chế thật | Code |
|---|---|---|---|
| Concurrency control **kiểu lạc quan** | Có (dạng UPDATE có điều kiện) | `UPDATE ... WHERE quantity >= :qty`, số dòng bị ảnh hưởng = 0 thì từ chối | [InventoryStockManager.cs:333-337](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L333-L337), [:413-417](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L413-L417), [PurchaseOrderService.cs:97-105](../../../services/purchase-service/src/Purchase.Application/Services/PurchaseOrderService.cs#L97-L105) |
| Concurrency control **kiểu bi quan** | Có | `SELECT ... FOR UPDATE` (`lockForUpdate`) | [LeaveBalanceService.php:65](../../../services/eoffice-service/app/Services/LeaveBalanceService.php#L65), [ApprovalService.php:55](../../../services/eoffice-service/app/Services/ApprovalService.php#L55), [DocumentNumberGenerator.php:60-62](../../../services/sales-service/app/Support/DocumentNumberGenerator.php#L60-L62) |
| Phòng tránh **deadlock** | Có | Luôn khoá theo thứ tự cố định (kho, rồi sản phẩm) | [ResourceLockExtensions.cs:10-20](../../../services/inventory-service/src/Inventory.Domain/Extensions/ResourceLockExtensions.cs#L10-L20), dùng ở [InventoryStockManager.cs:148-151](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L148-L151) |
| Ràng buộc làm "trọng tài" | Có | UNIQUE index, khoá chính ghép | xem mục 4 |
| **Idempotency** (lặp) | Có | Kiểm tra sổ cái, cùng chứng từ thì trả kết quả cũ | [InventoryStockManager.cs:377-398](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L377-L398), [:454-461](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L454-L461) |
| **Message queue** (tuần tự hoá, tách rời) | Có | Outbox + RabbitMQ, mỗi service một relay | bài 04 |
| State management / **Saga** | Có | Saga điều phối bởi Sales, có hành động bù trừ + đối soát | [InvoiceController.php:288-306](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L288-L306) |
| **CQRS / Event Sourcing** | Không đầy đủ | Sổ cái bất biến (append-only) là dạng "nhẹ" của event sourcing | mục 7 |
| **Khoá phân tán** (Redlock, ZooKeeper) | **Không** | Không cần, xem mục 6 | — |

## 3. Cơ chế 1: UPDATE có điều kiện (không đọc rồi ghi)

**Sai (read-modify-write):**
```
A: đọc tồn = 15          B: đọc tồn = 15
A: 15 - 10 = 5, ghi 5     B: 15 - 10 = 5, ghi 5     ← bán 20 cái từ kho chỉ có 15
```
**Đúng (project dùng):** biến "kiểm tra" và "trừ" thành MỘT câu lệnh nguyên tử do database thực hiện.
```sql
UPDATE inventory_stocks_reservation
SET quantity = quantity - :qty
WHERE warehouse_id = :w AND product_id = :p AND quantity >= :qty;
-- số dòng ảnh hưởng = 0  →  không đủ hàng  →  409 INSUFFICIENT_STOCK
```
Trong code EF Core: `Where(s => delta >= 0 || s.Quantity >= -delta).ExecuteUpdateAsync(... Quantity + delta ...)` ([:333-337](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L333-L337)); sau đó `updated == 0` thì ném `InsufficientStockException` ([:340-343](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L340-L343)).

Vì sao đúng? InnoDB khoá hàng khi UPDATE; hai UPDATE cùng hàng chạy lần lượt, UPDATE sau thấy giá trị đã trừ và điều kiện `quantity >= :qty` quyết định có thành công hay không. Không cần khoá ở tầng ứng dụng, không cần cột version.

**Đây thuộc nhóm "optimistic"** vì không giữ khoá trước, cứ thử rồi xem có xung đột không (so sánh với optimistic locking dùng cột `version`: ở đây "version" chính là điều kiện `quantity >= :qty`). Cùng cách ở Purchase: hai nhân viên cùng đặt hàng từ một PR chỉ một người thành công, người còn lại nhận `CONCURRENT_CONFLICT` ([PurchaseOrderService.cs:97-105](../../../services/purchase-service/src/Purchase.Application/Services/PurchaseOrderService.cs#L97-L105)).

### Bằng chứng chạy được
Test [InventoryConcurrencyTests.cs:40-102](../../../services/inventory-service/tests/Inventory.Infrastructure.Tests/InventoryConcurrencyTests.cs#L40-L102): **20 luồng song song** cùng xin giữ 10 món khi kho còn 15. Kết quả bắt buộc: đúng 1 thành công, 19 bị `INSUFFICIENT_STOCK`, tồn không bao giờ âm. Chạy lại để chụp ảnh:
```powershell
.\scripts\test.ps1 -Service inventory
```

## 4. Cơ chế 2: UNIQUE làm trọng tài cuối cùng

Code có thể thua race condition ("kiểm tra rồi mới ghi" có khe hở); ràng buộc trong database thì không.

| Ràng buộc | Chặn gì | Code |
|---|---|---|
| Sổ cái UNIQUE `(document_type, document_id, warehouse_id, product_id)` | Một chứng từ ghi sổ hai lần cho cùng sản phẩm/kho | [InventoryDbContext.cs:102](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L102), [:128](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L128) |
| `processed_events` khoá chính `(event_id, consumer_name)` | Cùng event xử lý hai lần | [InventoryDbContext.cs:162](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L162) |
| `outbox_events.event_id` UNIQUE | Cùng event ghi hai lần | [InventoryDbContext.cs:154](../../../services/inventory-service/src/Inventory.Infrastructure/Persistence/InventoryDbContext.cs#L154) |
| `document_number_claims.number` UNIQUE | Hai chứng từ cùng một số | [DocumentNumberGenerator.php:64](../../../services/sales-service/app/Support/DocumentNumberGenerator.php#L64) |

Mẫu xử lý khi đụng UNIQUE: **bắt lỗi rồi coi như thành công/hoặc thử lại**, không coi là lỗi nghiệp vụ.
- Giữ hàng: hai request cùng chứng từ chạy song song, request sau đụng UNIQUE → hệ thống đọc lại và trả kết quả idempotent ([InventoryStockManager.cs:126-137](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L126-L137)).
- Cấp số chứng từ: hai người cùng lấy số đầu tiên trong ngày, người sau bắt `UniqueConstraintViolationException`, thử lại tối đa 5 lần và nhận hậu tố kế tiếp ([DocumentNumberGenerator.php:68-73](../../../services/sales-service/app/Support/DocumentNumberGenerator.php#L68-L73)).

## 5. Cơ chế 3: Khoá bi quan và chống deadlock

**Khoá bi quan** (`lockForUpdate`) dùng khi logic cần **đọc rồi tính nhiều bước** mà không thể gói gọn trong một UPDATE. Ví dụ kiểm tra phép năm: đọc số ngày đã nghỉ, tính còn lại, mới cho gửi đơn. Hai đơn cùng gửi một lúc có thể vượt quỹ phép nếu không khoá, nên [LeaveBalanceService.php:65](../../../services/eoffice-service/app/Services/LeaveBalanceService.php#L65) khoá dòng quỹ phép của người đó trước khi tính.

| | Lạc quan (UPDATE có điều kiện) | Bi quan (`FOR UPDATE`) |
|---|---|---|
| Khi nào dùng | Phép tính gói được trong một câu lệnh (trừ tồn) | Cần đọc rồi tính nhiều bước (quỹ phép) |
| Giữ khoá | Không, chỉ trong chính câu UPDATE | Giữ đến hết transaction |
| Hiệu năng khi ít xung đột | Tốt hơn | Kém hơn |
| Rủi ro | Phải xử lý trường hợp thất bại | Có thể deadlock, chờ lâu |

**Deadlock** xảy ra khi A giữ khoá X chờ Y, B giữ Y chờ X. Giữ hàng nhiều dòng rất dễ gặp. Cách phòng của project: **mọi giao dịch khoá theo cùng một thứ tự** (kho tăng dần, rồi sản phẩm tăng dần) nên không thể tạo vòng chờ ([ResourceLockExtensions.cs:10-20](../../../services/inventory-service/src/Inventory.Domain/Extensions/ResourceLockExtensions.cs#L10-L20)). Cũng dùng khi nhả hàng ([InventoryStockManager.cs:207](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L207)).

## 6. Vì sao KHÔNG dùng khoá phân tán (Redlock, ZooKeeper)

Khoá phân tán giải bài toán "nhiều node cần loại trừ lẫn nhau trên một tài nguyên **không có nơi lưu trạng thái duy nhất**". Trong project, mỗi dữ liệu **có đúng một chủ sở hữu** (tồn kho thuộc Inventory) và nằm trong **một database có giao dịch ACID**. Khi đó, khoá của database (UPDATE có điều kiện, `FOR UPDATE`, UNIQUE) đã là cơ chế loại trừ tốt nhất, và khoá phân tán chỉ thêm một thành phần có thể hỏng (Redis chết thì sao?), thêm độ trễ, và phức tạp (Redlock còn nhiều tranh cãi về độ an toàn).

> **Câu chốt:** "Nguyên tắc của bọn em là *đưa tranh chấp về nơi có khoá mạnh nhất*: mỗi dữ liệu một chủ, mọi thay đổi đi qua một database ACID. Nhờ thiết kế database-per-service nên không cần khoá phân tán."

**Chỗ duy nhất có khoá cục bộ:** relay outbox dùng `flock` trên file ([RelayOutboxEvents.php:32-37](../../../services/sales-service/app/Console/Commands/RelayOutboxEvents.php#L32-L37)) để chỉ một relay chạy mỗi service. Đây **không phải khoá phân tán**: `flock` chỉ có tác dụng trong một máy. Hai relay ở hai máy khác nhau sẽ cùng chạy, gửi trùng và đảo thứ tự. Với 1 máy thì đủ; khi mở rộng phải thay bằng khoá phân tán (Redis `SET NX` kèm hạn, hoặc khoá bằng `GET_LOCK` của MySQL) hoặc bầu leader.

## 7. Cơ chế 4: Saga, nhất quán cuối cùng và đối soát

Giữ hàng chạm **hai database** (Sales lưu chứng từ, Inventory lưu tồn). Không có transaction chung, và project cố ý không dùng 2PC. Thay vào đó là **saga điều phối** bởi Sales (orchestration): chuỗi bước, mỗi bước là một transaction cục bộ, có hành động **bù trừ** khi sai.

```
Sales (InvoiceController::store)                         Inventory
  1. claim số chứng từ INV-... (ngoài transaction)  [DocumentNumberGenerator:53]
  2. BEGIN; tạo chứng từ
  3. ── POST /stocks/reserve/batch ─────────────────────► giữ CẢ LÔ trong 1 transaction
        │                                                  thiếu 1 dòng → rollback cả lô → 409
        ├─ OK ──────► COMMIT chứng từ                      
        ├─ 409 (thiếu hàng, câu trả lời CHẮC CHẮN) ─► rollback chứng từ; KHÔNG cần nhả
        └─ timeout / 5xx (KHÔNG CHẮC: Inventory có thể đã giữ xong)
                    ─► rollback chứng từ; gọi BÙ TRỪ  POST /stocks/release/document ─► nhả (idempotent)
  4. nếu nhả cũng lỗi → job đối soát dọn sau (mỗi 5 phút)
```

| Bước | Code |
|---|---|
| Giữ hàng ngay khi lập chứng từ, không giữ được thì rollback | [InvoiceController.php:286-293](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L286-L293) |
| Bù trừ khi kết quả không chắc chắn | [InvoiceController.php:297-306](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L297-L306) |
| Phân biệt lỗi "chắc chắn" (không cần nhả) với "không chắc" | [InvoiceController.php:1057-1064](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L1057-L1064) |
| Gọi giữ hàng / nhả hàng | [:999-1050](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L999-L1050), [:1071-1089](../../../services/sales-service/app/Http/Controllers/InvoiceController.php#L1071-L1089) |
| Giữ cả lô trong 1 transaction, gộp dòng trùng, khoá theo thứ tự | [InventoryStockManager.cs:118-176](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L118-L176) |
| Nhả idempotent theo số chứng từ | [InventoryStockManager.cs:199-227](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L199-L227) |
| Đối soát hàng giữ "mồ côi" | [ReconcileStockReservations.php](../../../services/sales-service/app/Console/Commands/ReconcileStockReservations.php), lịch 5 phút ở [console.php:12](../../../services/sales-service/routes/console.php#L12) |

### Vì sao số chứng từ phải "claim" ngoài transaction?
Inventory nhớ việc giữ hàng **theo số chứng từ**. Nếu chứng từ bị rollback rồi số cũ được cấp lại cho chứng từ khác, Inventory sẽ nhầm là "số này đã giữ hàng" và bỏ qua. Ghi số vào `document_number_claims` bằng transaction riêng thì số đã thất bại **không bao giờ được cấp lại** ([DocumentNumberGenerator.php:47-52](../../../services/sales-service/app/Support/DocumentNumberGenerator.php#L47-L52)).

### Bảng các kịch bản lỗi (nên thuộc)
| Kịch bản | Điều gì xảy ra | Cơ chế |
|---|---|---|
| Đủ hàng | Giữ hàng, commit chứng từ | bình thường |
| Thiếu hàng (409) | Rollback chứng từ, không nhả | lỗi chắc chắn |
| Inventory timeout, nhưng thực ra đã giữ xong | Rollback chứng từ, gọi nhả | bù trừ + idempotent |
| Gọi nhả cũng lỗi | Hàng treo tạm thời | đối soát 5 phút/lần |
| Sales crash giữa chừng (đã giữ, chưa commit) | Hàng treo | đối soát (bỏ qua chứng từ mới hơn 5 phút vì có thể đang lập dở) |
| Sales gọi lại "giữ hàng" cùng số chứng từ | Trả kết quả cũ, không giữ thêm | idempotent theo ledger |
| Gọi lại cùng số nhưng khác số lượng | `422 DOCUMENT_ALREADY_POSTED` | [EnsureSameDelta :454-461](../../../services/inventory-service/src/Inventory.Infrastructure/Services/InventoryStockManager.cs#L454-L461) |

### Nhất quán cuối cùng (eventual consistency)
Có một khoảng thời gian ngắn (tối đa vài phút) mà hai hệ thống lệch nhau (Inventory giữ, Sales không có chứng từ). Hệ thống **chấp nhận lệch tạm thời** nhưng **bảo đảm hội tụ** nhờ nhả idempotent + đối soát định kỳ. Đây là đánh đổi theo định lý CAP: khi mạng/ dịch vụ phân vùng, project chọn *tiếp tục hoạt động và hội tụ sau* (với thông báo) hoặc *từ chối* (với giữ hàng, vì bán âm hàng là không chấp nhận được).

### CQRS và Event Sourcing: có đến đâu?
- **Không có CQRS** (không tách mô hình ghi và đọc).
- **Sổ cái bất biến là event sourcing "nhẹ":** hai bảng `inventory_stocks_transaction` và `inventory_stocks_reservation_transaction` chỉ ghi thêm (không sửa/xoá), mỗi dòng có `balance_before`, `quantity_delta`, `balance_after`. Nhưng **số dư vẫn được lưu riêng** (`inventory_stocks.quantity`) thay vì tính lại từ sổ cái, nên đây chưa phải event sourcing đúng nghĩa. Sổ cái dùng để: kiểm toán, chống ghi trùng (UNIQUE), và đối soát.

## 8. Thầy có thể hỏi

**H: Hai người cùng đặt hàng khi chỉ còn đủ cho một người thì sao?**
Đ: Câu `UPDATE ... WHERE quantity >= :qty` chạy nguyên tử: InnoDB khoá hàng nên hai UPDATE xếp hàng; người đầu trừ được, người sau thấy tồn không đủ, số dòng ảnh hưởng bằng 0 nên nhận `409 INSUFFICIENT_STOCK`. Bọn em có test 20 luồng song song chứng minh: đúng 1 thành công, 19 bị từ chối, tồn không âm.

**H: Sao không dùng distributed lock với Redis?**
Đ: Khoá phân tán dành cho tài nguyên không có nơi lưu trạng thái duy nhất. Ở đây mỗi dữ liệu thuộc một service và một database ACID, nên khoá của database đã đủ mạnh, thêm Redis chỉ thêm điểm hỏng. Nơi duy nhất cần là relay outbox khi chạy nhiều máy, và bọn em biết giới hạn của `flock`.

**H: Saga là gì? Khác 2PC chỗ nào?**
Đ: 2PC khoá tài nguyên ở mọi bên trong lúc chờ coordinator, một bên chết là treo cả giao dịch, và MySQL với Mongo/RabbitMQ không cùng tham gia XA. Saga chia thành chuỗi transaction cục bộ, bên nào lỗi thì chạy hành động bù trừ. Đổi lại là nhất quán cuối cùng thay vì tức thì.

**H: Nếu cả bước bù trừ cũng lỗi?**
Đ: Hàng treo tạm thời nhưng không mất dữ liệu. Job đối soát chạy mỗi 5 phút liệt kê hàng đang giữ ở Inventory, đối chiếu với chứng từ ở Sales, rồi nhả những khoản mồ côi. Nhả là idempotent nên chạy bao nhiêu lần cũng an toàn.

**H: Timeout thì Sales không biết Inventory đã giữ hay chưa. Xử lý thế nào?**
Đ: Coi là *không chắc* và chủ động nhả. Vì nhả idempotent theo số chứng từ và số đó không bao giờ được cấp lại, nhả một thứ chưa giữ cũng không sao.

**H: Làm sao đảm bảo thứ tự xử lý?**
Đ: Với tồn kho, thứ tự không quan trọng vì mỗi thao tác là một UPDATE độc lập và giao hoán (cộng/trừ), được bảo vệ bằng điều kiện. Với event, mỗi service có một relay gửi theo `id` tăng dần, nhưng consumer không được giả định thứ tự tuyệt đối.

## 9. Điểm yếu (nói thật khi bị hỏi)

- **`flock` chỉ hiệu lực trên một máy:** relay không scale ngang; ở nhiều máy cần khoá phân tán hoặc leader election.
- **Saga không có nhật ký bền:** trạng thái saga nằm trong chính request HTTP của Sales. Sales chết giữa chừng thì không "tiếp tục" được, chỉ dựa vào đối soát để dọn. Hệ thống saga trưởng thành sẽ có bảng trạng thái saga (hoặc dùng công cụ như Temporal).
- **Đối soát có độ trễ** (5 phút + tuổi tối thiểu 5 phút): trong lúc đó hàng bị giữ nhầm, ảnh hưởng khả năng bán.
- **Mongo replica set chỉ 1 node:** dùng để chạy transaction của chat, không phải nhân bản dữ liệu thật.
- **`IssueStock` (xuất kho) và event `inventory.goods.issued` ghi hai bước tách** ([StocksController.cs:566-595](../../../services/inventory-service/src/Inventory.Api/Controllers/StocksController.cs#L566-L595)): crash ở giữa thì kho đã trừ nhưng thiếu event. Dữ liệu kho vẫn đúng.
- **Không có CQRS và số dư không tính lại từ sổ cái** (xem mục 7).

## 10. Thực hành (để nhớ lâu và có ảnh cho báo cáo)

**Bài 1. Chứng minh không bán âm.** Chạy test 20 luồng và chụp kết quả:
```powershell
.\scripts\test.ps1 -Service inventory
```

**Bài 2. Tận mắt thấy khoá hàng (pessimistic lock).** Mở hai cửa sổ PowerShell, mỗi cái chạy `docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" erp_inventory_db'`.
- Cửa sổ 1: `START TRANSACTION; SELECT * FROM inventory_stocks ORDER BY id LIMIT 1 FOR UPDATE;` (chưa COMMIT).
- Cửa sổ 2: `UPDATE inventory_stocks SET quantity = quantity WHERE id = (SELECT id FROM (SELECT MIN(id) id FROM inventory_stocks) t);` → **bị treo**.
- Quay lại cửa sổ 1 gõ `COMMIT;` → cửa sổ 2 chạy tiếp ngay. Đó là InnoDB khoá hàng, nền tảng của mọi bảo đảm ở trên.

**Bài 3. Thấy UNIQUE chặn ghi trùng.** Trong Adminer (đăng nhập `erp_inventory`), chạy `SHOW CREATE TABLE inventory_stocks_transaction;` và tìm `UNIQUE KEY` trên bốn cột chứng từ.

**Bài 4. Saga bù trừ và đối soát.**
1. `docker compose exec sales-scheduler php artisan sales:reconcile-reservations --dry-run` → xem kết quả "Đã xét N chứng từ ...".
2. Dừng inventory (`docker compose stop inventory-service`), thử lập hoá đơn → nhận `503 DEPENDENCY_UNAVAILABLE`, chứng từ **không** được tạo.
3. Bật lại, hệ thống tự phục hồi.

## 11. Tự kiểm tra
- [ ] Giải thích vì sao "đọc rồi ghi" sai và UPDATE có điều kiện đúng, bằng sơ đồ hai luồng.
- [ ] Phân biệt khoá lạc quan và bi quan, mỗi loại cho một ví dụ có thật trong code.
- [ ] Giải thích deadlock và cách project phòng tránh.
- [ ] Vẽ saga giữ hàng, chỉ rõ bước bù trừ và trường hợp nào không cần nhả.
- [ ] Giải thích vì sao project không cần Redlock và nơi nào sẽ cần nếu mở rộng.
- [ ] Nói được 3 điểm yếu của phần đồng bộ hóa mà không nhìn tài liệu.
