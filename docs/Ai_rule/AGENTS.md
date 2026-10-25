# AGENTS.md — Master Architecture Guidelines

> **Vai trò của AI Coding Agent**: Bạn đang làm việc với vai trò là một **Senior Software Architect**. Nhiệm vụ tối cao của bạn là bảo vệ tính toàn vẹn của kiến trúc hệ thống, hướng dẫn code sạch, bảo mật và hiệu năng cao. Tuyệt đối không thỏa hiệp cấu trúc để lấy sự tiện lợi nhất thời.

---

## 🎯 TẦM NHÌN & ĐỊNH HƯỚNG DỰ ÁN

**Smart Distributed ERP** hướng tới xây dựng một nền tảng ERP tinh gọn cho doanh nghiệp thương mại và phân phối B2B, trong đó trọng tâm không chỉ là số hóa nghiệp vụ mà còn là giải quyết các bài toán kỹ thuật phức tạp của hệ thống phân tán.

Điểm nhấn của đồ án là sự kết hợp chặt chẽ giữa:
$$\textbf{Business Process} + \textbf{Microservices} + \textbf{Distributed Data} + \textbf{Event-Driven Architecture} + \textbf{Concurrency Control} + \textbf{AI}$$
tạo thành một hệ thống vừa có **giá trị nghiệp vụ thực tế** (quản trị chuỗi cung ứng SCM khép kín), vừa có **chiều sâu kỹ thuật** (khóa đồng thời, phân tán, saga, transactional outbox) phù hợp hoàn hảo với một đồ án liên ngành CNTT & QTKD/SCM.

---

## 🔄 LUỒNG NGHIỆP VỤ TRUNG TÂM (END-TO-END B2B SCM PIPELINE)

Mọi quy trình trong hệ thống đều xoay quanh trục nghiệp vụ xuyên suốt sau:

```text
       [ PROCUREMENT ] (Đơn mua hàng PO - purchase-service)
              ↓
     [ GOODS RECEIPT ] (Nhập kho hàng hóa - purchase / inventory-service)
              ↓
        [ INVENTORY ] (Cập nhật tồn kho On-Hand - inventory-service)
              ↓
  [ QUOTATION → SALES ORDER ] (Báo giá → Đơn bán hàng, duyệt - sales-service)
              ↓
   [ DELIVERY REQUEST ] (Yêu cầu giao hàng, chọn kho bán - sales-service)
              ↓
   [ SALES DOCUMENT + RESERVATION ] (Chứng từ bán hàng → giữ hàng nguyên tử cả lô - sales → inventory)
              ↓
         [ DELIVERY ] (Điều phối kho: soạn, đóng gói, giao - sales-service)
              ↓
      [ GOODS ISSUE ] (Kho ghi sổ xuất: giảm tồn thực tế - inventory-service)
              ↓
     [ REPORTING / AI ] (Phân tích biên lợi nhuận, Min-Stock, Copilot - ai-service)
```

---

## 🤖 NGUYÊN TẮC TỰ VẤN CHO AI TRƯỚC KHI VIẾT CODE

Trước khi viết hoặc chỉnh sửa bất kỳ đoạn code nào, AI **BẮT BUỘC** phải tự kiểm tra và trả lời 7 câu hỏi:

1. **Logic này thuộc về tầng nào là chuẩn nhất theo Clean Architecture?**
   - Không viết logic tính toán kho/tiền bạc trong Controller hay Infrastructure. Phải đặt ở Domain Entity hoặc Application Service/Handler.
2. **Có file nào vi phạm hướng phụ thuộc một chiều không?**
   - Domain là trung tâm, không phụ thuộc vào tầng nào. Presentation và Infrastructure phụ thuộc vào Application/Domain, không có chiều ngược lại.
3. **Việc triển khai này có thể viết Unit Test dễ dàng không?**
   - Đã tách các phụ thuộc bên ngoài (DB, RabbitMQ, Clock) qua Interface/Dependency Injection chưa?
4. **Có đang đưa trực tiếp thực thể Domain ra ngoài API Controller hay không?**
   - **CẤM TUYỆT ĐỐI**. Bắt buộc dùng DTO (Data Transfer Object) hoặc Request/Response ViewModel cho cả PHP và .NET.
5. **Múi giờ đang được dùng có đúng là `DateTime.UtcNow` (hoặc UTC trong PHP) không?**
   - Toàn bộ backend C# dùng `DateTime.UtcNow`. PHP dùng `now()->toIso8601String()`. Cấm dùng local server time.
6. **Truy vấn lấy dữ liệu này đã cấu hình `.AsNoTracking()` chưa?**
   - Trong .NET (EF Core): Mọi truy vấn chỉ đọc (Read-only query) **phải có `.AsNoTracking()`**.
   - Trong PHP: Chọn rõ cột qua `select(...)` hoặc Query Builder, tránh hydrate model nặng nề.
7. **Đây có phải giải pháp đơn giản nhất chưa hay đang bị phức tạp hóa (KISS)?**
   - Ưu tiên giải pháp rõ ràng, dễ bảo trì, tránh over-engineering tạo 5-7 layer trung gian vô nghĩa nếu nghiệp vụ chỉ cần 1 thao tác rõ ràng.

---

## 📚 BẢN ĐỒ TÀI LIỆU QUY CHUẨN CHI TIẾT (SUB-RULES INDEX)

Để đảm bảo tính nhất quán tuyệt đối giữa **PHP (Laravel)** và **C# (.NET 10)**, mã nguồn phải tuân theo 4 tài liệu con (cùng hợp đồng tại [../contracts/api-spec.md](../contracts/api-spec.md) và [../contracts/events-spec.md](../contracts/events-spec.md)):

1. **[architecture.md](architecture.md)**: Bounded Contexts & quyền sở hữu dữ liệu, **khi nào dùng REST / khi nào dùng Queue**, cách chia sẻ dữ liệu qua ranh giới (Snapshot / REST / Composition / Replica), Outbox, Saga, Concurrency, và bảng hiện trạng lệch chuẩn.
2. **[api-guidelines.md](api-guidelines.md)**: Chuẩn REST API Envelope, HTTP Status, định tuyến NGINX Gateway, `X-Correlation-ID` và Stateless RBAC JWT.
3. **[coding-style.md](coding-style.md)**: Quy chuẩn đặt tên hàm, biến, DTO, JSON naming (`snake_case`), cấu hình UTC, async/await và thực hành Clean Code cho C# & PHP.
4. **[database-guidelines.md](database-guidelines.md)**: Nguyên tắc Database-per-Service, cấm Join/FK chéo DB, Data Snapshotting, Atomic SQL Predicate Guard và Outbox schema.

---

## 📋 CHECKLIST KIỂM THỬ TRƯỚC KHI COMMIT MÃ NGUỒN

- [ ] Nghiệp vụ nằm đúng bước trong luồng trung tâm SCM (Procurement $\rightarrow$ Goods Issue $\rightarrow$ AI).
- [ ] Không có câu lệnh `JOIN`, Foreign Key, **hay connection** tới database của microservice khác (kể cả chỉ đọc).
- [ ] Dữ liệu của service khác dùng trong chứng từ đã được **snapshot**; lời gọi REST nội bộ có timeout, URL từ cấu hình, nằm trong client ở Infrastructure.
- [ ] Đã chọn đúng REST hay Queue theo [architecture.md §4](architecture.md#4-chọn-cách-giao-tiếp-rest-đồng-bộ-hay-queue-bất-đồng-bộ); event mới phải có consumer thật.
- [ ] Mọi thay đổi dữ liệu có phát sinh event đều ghi qua **Transactional Outbox** (cấm publish thẳng trong controller).
- [ ] Consumer đã kiểm tra `processed_events` để đảm bảo **Idempotency**.
- [ ] Logic trừ kho đã dùng câu lệnh SQL nguyên tử (Atomic Update Guard).
- [ ] Đã chuyển đổi Domain Entity sang DTO trước khi trả về Controller.
- [ ] Query chỉ đọc trong C# đã có `.AsNoTracking()`.
- [ ] API trả về đúng chuẩn Envelope `{ success, data, error, meta }`.
