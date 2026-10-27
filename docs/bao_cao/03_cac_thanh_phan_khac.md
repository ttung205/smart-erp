# CHƯƠNG 3: MỘT SỐ THÀNH PHẦN KHÁC CỦA ĐỒ ÁN

---

## 3.1. KẾ HOẠCH DỰ ÁN (PROJECT SCHEDULE & GANTT CHART)

### 3.1.1. Phương pháp luận triển khai dự án (Agile / Scrum Framework)

Nhằm đảm bảo tiến độ và chất lượng cho một hệ sinh thái phân tán phức tạp gồm **10 microservices đa ngôn ngữ** (C# .NET 10, PHP Laravel 13, Python FastAPI, Node.js Express, Vue 3 SPA) cùng hạ tầng đa cơ sở dữ liệu (**MySQL 8, MongoDB 7, RabbitMQ, Kong API Gateway**), nhóm đã lựa chọn áp dụng phương pháp luận phát triển phần mềm linh hoạt **Agile / Scrum**.

Dự án được thực hiện trong tổng thời gian **10 tuần** (gồm **6 Sprint**: Sprint 1 kéo dài 1 tuần, Sprint 2 kéo dài 1 tuần, và 4 Sprint tiếp theo từ Sprint 3 đến Sprint 6 mỗi Sprint kéo dài 2 tuần). Toàn bộ khối lượng công việc được lượng hóa thông qua đơn vị **Story Points** (ước lượng theo dãy số Fibonacci: 1, 2, 3, 5, 8, 13, 21), với tổng dung lượng ước tính là **240 Story Points**.

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                    VÒNG ĐỜI PHÁT TRIỂN 6 SPRINT CỦA SMART DISTRIBUTED ERP (10 TUẦN)             │
├──────────────┬──────────────┬──────────────┬──────────────┬──────────────┬──────────────────────┤
│   Sprint 1   │   Sprint 2   │   Sprint 3   │   Sprint 4   │   Sprint 5   │       Sprint 6       │
│   (Tuần 1)   │   (Tuần 2)   │  (Tuần 3-4)  │  (Tuần 5-6)  │  (Tuần 7-8)  │     (Tuần 9-10)      │
├──────────────┼──────────────┼──────────────┼──────────────┼──────────────┼──────────────────────┤
│ Khảo sát &   │ Core Master  │ SCM Invariant│ Realtime     │ Data Mining, │ Kiểm thử tải E2E,    │
│ Thiết kế     │ Data, RBAC & │ Đặt chỗ kho  │ SignalR Chat │ ML Models &  │ Tối ưu hóa, Hardening│
│ Kiến trúc    │ Outbox Relay │ nguyên tử    │ & MongoDB    │ Dashboard BI │ & Hoàn thiện báo cáo │
│ (35 SP)      │ (45 SP)      │ (45 SP)      │ (40 SP)      │ (45 SP)      │ (30 SP)              │
└──────────────┴──────────────┴──────────────┴──────────────┴──────────────┴──────────────────────┘
```

---

### 3.1.2. Phân bổ chi tiết các Sprint và Sản phẩm bàn giao (Sprint Deliverables)

Dưới đây là bảng phân rã chi tiết mục tiêu, nhiệm vụ kỹ thuật của từng thành viên, sản phẩm bàn giao cụ thể và công tác quản trị rủi ro trong từng Sprint:

| Sprint / Thời gian | Mục tiêu trọng tâm | Nhiệm vụ cụ thể của từng thành viên | Sản phẩm bàn giao (Deliverables) | Rủi ro & Giải pháp ứng phó |
| :--- | :--- | :--- | :--- | :--- |
| **Sprint 1**<br>*(Tuần 1)*<br>**35 Story Points** | **Khảo sát nghiệp vụ, phân rã Bounded Contexts & Thiết lập hạ tầng** | **Trưởng nhóm (SV1):**<br>- Khảo sát chuỗi cung ứng B2B thiết bị gia dụng.<br>- Thiết kế kiến trúc tổng thể [architecture.md](../Ai_rule/architecture.md) & [api-guidelines.md](../Ai_rule/api-guidelines.md).<br>- Phác thảo Star Schema cho `erp_analytics_db`.<br>**Thành viên (SV2):**<br>- Thiết lập Docker Compose hạ tầng cơ sở: MySQL 8, MongoDB 7, RabbitMQ 3.13, Kong Gateway 3.9.1.<br>- Cấu hình khởi tạo 8 schema MySQL và 2 database MongoDB.<br>- Thiết lập mẫu dự án chuẩn Clean Architecture. | - Tài liệu kiến trúc hệ thống phân tán.<br>- File `docker-compose.yml` khởi chạy hạ tầng trung gian.<br>- Script khởi tạo database `01-create-databases.sql`. | **Rủi ro:** Xung đột cổng mạng cục bộ giữa các services.<br>**Giải pháp:** Quy hoạch dải cổng nội bộ cố định (8001 → 8010), chỉ mở cổng 8000 (Kong) và 5175 (Frontend) ra bên ngoài. |
| **Sprint 2**<br>*(Tuần 2)*<br>**45 Story Points** | **Xây dựng Identity, Master Catalog Data & Transactional Outbox** | **Trưởng nhóm (SV1):**<br>- Thiết kế cấu trúc bảng sản phẩm và đối tác chuẩn hóa.<br>- Xây dựng bộ sinh sự kiện tên miền (Domain Events) theo đặc tả CloudEvents v1.0.2.<br>- Triển khai skeleton service C# .NET 10 cho Inventory & Purchase.<br>**Thành viên (SV2):**<br>- Phát triển `identity-service` (PHP Laravel 13): Xác thực JWT, Bcrypt, Dynamic RBAC 11 Roles.<br>- Phát triển `catalog-service` (PHP Laravel 13): Quản lý 916 sản phẩm gia dụng thật và 2.591 đối tác.<br>- Xây dựng Transactional Outbox Pattern kết hợp Background Relay Worker (`catalog-relay`). | - REST API `/api/v1/auth/*` với phân quyền hạt mịn.<br>- REST API `/api/v1/catalog/*` Master Data.<br>- Sự kiện `catalog.product.*` được publish lên RabbitMQ Exchange `erp.events`. | **Rủi ro:** Trùng lặp sự kiện khi relay worker quét bảng outbox.<br>**Giải pháp:** Thêm trạng thái `PUBLISHED`, đánh chỉ mục `(published_at, created_at)` và cơ chế Idempotent Consumer. |
| **Sprint 3**<br>*(Tuần 3 - 4)*<br>**45 Story Points** | **Động cơ Tồn kho nguyên tử, Ràng buộc SCM Invariant & Bán hàng** | **Trưởng nhóm (SV1):**<br>- Phát triển `inventory-service` (C# .NET 10 Web API): Động cơ kiểm soát 3 trạng thái ($\text{OnHand}$, $\text{Reserved}$, $\text{Available}$).<br>- Cài đặt Pessimistic Concurrency Lock (`SELECT ... FOR UPDATE`) và kiểm soát phiên bản trong `/stocks/reserve/batch` và `/stocks/release/batch`.<br>- Phát triển `purchase-service` (C# .NET 10): Luồng PR, PO, Đối soát 3 bên (Three-Way Matching) và GRN.<br>**Thành viên (SV2):**<br>- Phát triển `sales-service` (PHP Laravel 13): Báo giá (Quotation), Đơn bán hàng (SO) với kỹ thuật Reference Snapshotting.<br>- Xử lý luồng Hóa đơn/Xuất kho (`Invoice/Dispatch`) gọi đồng bộ giữ chỗ tồn kho sang Inventory kèm mã chứng từ gốc.<br>- Xây dựng `sales-relay` và tích hợp Dead-Letter Exchange (`erp.dlx`). | - Microservice Inventory và Purchase biên dịch thành công trên .NET 10.<br>- Đảm bảo bất biến $\text{Available} \ge 0$ không bị phá vỡ dưới tải đồng thời.<br>- Bộ test kiểm thử ràng buộc tồn kho tự động. | **Rủi ro:** Deadlock cơ sở dữ liệu khi hai giao dịch giữ chỗ cùng nhiều mặt hàng theo thứ tự ngược nhau.<br>**Giải pháp:** Sắp xếp danh sách `product_id` theo thứ tự tăng dần trước khi thực hiện câu lệnh `SELECT ... FOR UPDATE`. |
| **Sprint 4**<br>*(Tuần 5 - 6)*<br>**40 Story Points** | **Microservice Chat SignalR, Work Thread gắn chứng từ & Notifications** | **Trưởng nhóm (SV1):**<br>- Phát triển `chat-service` (C# .NET 10): ASP.NET Core SignalR Hub tại `/hub/chat`.<br>- Thiết kế mô hình dữ liệu hội thoại trên MongoDB (`erp_chat_db`): `conversations`, `chat_messages`, `attachments`, `reactions`.<br>- Triển khai mô hình Work Thread gắn chứng từ ERP (`Sale`, `Quotation`, `Purchase`, `Invoice`).<br>- Viết bộ kiểm thử tích hợp xUnit [ChatApiTests.cs](../../services/chat-service/tests/Chat.Tests/ChatApiTests.cs) (21 tests).<br>**Thành viên (SV2):**<br>- Phát triển `notification-service` (Node.js 22): Động cơ thông báo đa kênh Socket.IO và Email queue (Mailpit).<br>- Phát triển `finance-service` (PHP Laravel 13): Quản lý công nợ, hóa đơn VAT, kiểm soát hạn mức tín dụng.<br>- Kết nối thông báo sự kiện chuỗi cung ứng từ RabbitMQ. | - Microservice Chat hoạt động tại cổng 8009 với độ trễ truyền tin $< 50ms$.<br>- 100% bài kiểm thử xUnit của Chat Service Passed.<br>- Work Thread nhúng trực tiếp chứng từ lưu vết kiểm toán minh bạch. | **Rủi ro:** Rò rỉ bộ nhớ WebSocket và gián đoạn kết nối mạng bất thường.<br>**Giải pháp:** Cơ chế Heartbeat ping/pong định kỳ và thuật toán Catch-up Sync tải bù tin nhắn theo cursor `after_id`. |
| **Sprint 5**<br>*(Tuần 7 - 8)*<br>**45 Story Points** | **Kho dữ liệu phân tích, Machine Learning, E-Office & Dashboard BI** | **Trưởng nhóm (SV1):**<br>- Chuẩn hóa và làm giàu kho dữ liệu 24 tháng (61.982 đơn hàng, 765.4 tỷ VNĐ) từ `kaggle_dataset/` vào MySQL `erp_analytics_db`.<br>- Huấn luyện mô hình K-Means Clustering phân cụm RFM ($k=4$, Silhouette = 0.477).<br>- Xây dựng mô hình Holt-Winters kết hợp Regression dự báo doanh số 30 ngày kèm dải tin cậy 95% ($\text{MAPE} < 8\%$).<br>- Xây dựng REST API phân tích `/api/v1/ai/analytics/*` trên FastAPI kèm In-memory Caching.<br>**Thành viên (SV2):**<br>- Phát triển phân hệ Chấm công & Bảng lương trong `eoffice-service`: `Shift`, `PunchLog`, `AttendanceDayCalculator`, `PayrollRule`, `PayrollPeriod`, `PayrollSlip`.<br>- Tích hợp hạch toán Sổ cái tự động sang `finance-service` (`PayrollJournalController`).<br>- Xây dựng giao diện Dashboard Quản trị BI tương tác trên Vue 3 (`/analytics`) với Chart.js. | - Script làm sạch `data_cleaning_pipeline.py` và kiểm định `ml_validation_pipeline.py` (21/21 PASS).<br>- Dashboard BI trực quan hóa KPI, dự báo và phân cụm.<br>- Toàn bộ quy trình Chấm công & Bảng lương hoạt động trơn tru. | **Rủi ro:** Dữ liệu ngoại lai (Outliers) làm lệch tâm cụm K-Means và dải dự báo.<br>**Giải pháp:** Áp dụng phương pháp phân vị IQR, phép biến đổi logarit $\ln(x+1)$ và chuẩn hóa `StandardScaler`. |
| **Sprint 6**<br>*(Tuần 9 - 10)*<br>**30 Story Points** | **Kiểm thử E2E, Tối ưu hóa hiệu năng, Hardening & Đóng gói báo cáo** | **Trưởng nhóm (SV1):**<br>- Kiểm thử tải đồng thời (Concurrency & Stress Testing) cho Inventory và Chat Service.<br>- Rà soát tính toàn vẹn dữ liệu, kiểm tra Correlation ID xuyên suốt qua Kong Gateway.<br>- Viết Chương 1, Chương 2, hoàn thiện báo cáo kỹ thuật.<br>**Thành viên (SV2):**<br>- Xây dựng script kiểm thử toàn diện `smoke-test.ps1`, `validate-prod-config.ps1`.<br>- Rà soát bảo mật OWASP Top 10, kiểm duyệt MIME-type file đính kèm, kiểm tra XSS/CSRF.<br>- Tối ưu hóa bundle Vue 3 (Code splitting, Vite build 0 lỗi).<br>- Viết Chương 3, Chương 4 và chuẩn bị Slide thuyết trình. | - Script `smoke-test.ps1` kiểm tra 11/11 endpoints thành công.<br>- Báo cáo đồ án hoàn chỉnh 5 chương theo chuẩn học thuật Phenikaa.<br>- Hệ thống đóng gói hoàn chỉnh bằng Docker Compose sẵn sàng demo. | **Rủi ro:** Thời gian biên dịch và đóng gói Docker container quá lâu.<br>**Giải pháp:** Tối ưu Dockerfile nhiều giai đoạn (Multi-stage builds) và tận dụng Docker Layer Caching. |

---

### 3.1.3. Biểu đồ tiến độ dự án (PlantUML Gantt Chart)

Dưới đây là biểu đồ Gantt thể hiện sự liên kết, tính gối đầu và phụ thuộc giữa các giai đoạn công việc trong suốt 10 tuần triển khai đồ án:

```plantuml
@startgantt
skinparam roundcorner 6
skinparam defaultFontName "Segoe UI", Arial, sans-serif
skinparam defaultFontSize 11
skinparam ArrowColor #2563EB
skinparam ActivityBorderColor #1E293B

title TIẾN ĐỘ THỰC HIỆN ĐỒ ÁN LIÊN NGÀNH SMART DISTRIBUTED ERP (10 TUẦN)

Project starts 2026-08-03

-- Sprint 1: Khảo sát & Thiết kế Kiến trúc (Tuần 1) --
[Khảo sát chuỗi cung ứng & Thiết kế Kiến trúc] as [S1_ARCH] lasts 7 days
[Thiết lập Docker hạ tầng & CSDL phân tán] as [S1_INFRA] lasts 7 days
[S1_ARCH] is colored in LightSkyBlue
[S1_INFRA] is colored in LightSkyBlue

-- Sprint 2: Core Master Data & Outbox Relay (Tuần 2) --
[Phát triển Identity & Phân quyền động 11 Roles] as [S2_ID] lasts 7 days
[Phát triển Catalog Master Data & Transactional Outbox] as [S2_CAT] lasts 7 days
[Khung sườn C# .NET 10 cho Inventory & Purchase] as [S2_SKEL] lasts 7 days
[S2_ID] starts at [S1_ARCH]'s end
[S2_CAT] starts at [S1_INFRA]'s end
[S2_SKEL] starts at [S1_ARCH]'s end
[S2_ID] is colored in LightGreen
[S2_CAT] is colored in LightGreen
[S2_SKEL] is colored in LightGreen

-- Sprint 3: Động cơ Tồn kho & Ràng buộc SCM (Tuần 3 - 4) --
[Động cơ Tồn kho nguyên tử C# & Khóa bi quan] as [S3_INV] lasts 14 days
[Phân hệ Mua hàng Purchase & Three-Way Matching] as [S3_PUR] lasts 14 days
[Phân hệ Bán hàng Sales & Snapshotting tham chiếu] as [S3_SAL] lasts 14 days
[S3_INV] starts at [S2_SKEL]'s end
[S3_PUR] starts at [S2_SKEL]'s end
[S3_SAL] starts at [S2_CAT]'s end
[S3_INV] is colored in SandyBrown
[S3_PUR] is colored in SandyBrown
[S3_SAL] is colored in SandyBrown

-- Sprint 4: Realtime Chat Service & Work Thread (Tuần 5 - 6) --
[Xây dựng Chat Service C# SignalR trên MongoDB] as [S4_CHAT] lasts 14 days
[Mô hình Work Thread gắn chứng từ & Catch-up Sync] as [S4_THREAD] lasts 14 days
[Xây dựng Notification Service & Finance Service] as [S4_NOTI] lasts 14 days
[S4_CHAT] starts at [S3_INV]'s end
[S4_THREAD] starts at [S4_CHAT]'s end - 7 days
[S4_NOTI] starts at [S3_SAL]'s end
[S4_CHAT] is colored in LightPink
[S4_THREAD] is colored in LightPink
[S4_NOTI] is colored in LightPink

-- Sprint 5: Machine Learning, E-Office & Dashboard BI (Tuần 7 - 8) --
[Kho dữ liệu 24 tháng & Huấn luyện K-Means, Holt-Winters] as [S5_ML] lasts 14 days
[Phân hệ E-Office: Chấm công & Bảng lương & Hạch toán] as [S5_EO] lasts 14 days
[Giao diện SPA Vue 3 & Dashboard BI tương tác] as [S5_FE] lasts 14 days
[S5_ML] starts at [S4_CHAT]'s end
[S5_EO] starts at [S4_NOTI]'s end
[S5_FE] starts at [S5_ML]'s end - 7 days
[S5_ML] is colored in MediumPurple
[S5_EO] is colored in MediumPurple
[S5_FE] is colored in MediumPurple

-- Sprint 6: Kiểm thử Toàn diện, Hardening & Báo cáo (Tuần 9 - 10) --
[Kiểm thử tải đồng thời, Smoke Test & OWASP Audit] as [S6_TEST] lasts 14 days
[Hoàn thiện Báo cáo Đồ án & Slide bảo vệ] as [S6_DOC] lasts 14 days
[S6_TEST] starts at [S5_ML]'s end
[S6_DOC] starts at [S6_TEST]'s start
[S6_TEST] is colored in Coral
[S6_DOC] is colored in Coral

@endgantt
```

---

## 3.2. ĐẢM BẢO THỰC HIỆN ĐÚNG LÀM VIỆC NHÓM (TEAMWORK ASSURANCE)

### 3.2.1. Quy chế vận hành và Nghi thức Agile / Scrum

Để đảm bảo hai thành viên phối hợp nhịp nhàng, kiểm soát tốt các điểm giao tiếp liên dịch vụ (Cross-service integration points), nhóm đã thiết lập bản **Quy chế Vận hành Nhóm (Team Working Agreement)** với các nghi thức bắt buộc:

1. **Daily Standup Meeting (15 phút mỗi sáng từ 08:30 - 08:45 qua Discord)**:
   * Tập trung trả lời 3 câu hỏi cốt lõi:
     1. *Tôi đã hoàn thành những gì trong ngày hôm qua?* (Ví dụ: hoàn thành API giữ chỗ tồn kho nguyên tử).
     2. *Tôi sẽ thực hiện những gì trong ngày hôm nay?* (Ví dụ: viết integration test cho Relay Outbox Worker).
     3. *Có trở ngại kỹ thuật (Impediments / Blockers) nào cần nhóm hỗ trợ tháo gỡ không?* (Ví dụ: xung đột định dạng header JWT hoặc lệch chuẩn CloudEvents envelope).
   * Mọi trở ngại kỹ thuật được đưa vào kênh thảo luận chuyên sâu ngay sau buổi họp, không để tồn đọng quá 24 giờ.

2. **Sprint Planning & Backlog Grooming (Đầu mỗi Sprint - 2 giờ)**:
   * Trưởng nhóm phân rã User Stories từ đặc tả nghiệp vụ, gán nhãn ưu tiên (MoSCoW: Must have, Should have, Could have, Won't have).
   * Cả hai thành viên cùng thảo luận giải pháp kỹ thuật, thống nhất hợp đồng API (API Contract) và thỏa thuận chấm điểm Story Points bằng kỹ thuật Planning Poker.

3. **Sprint Review & Retrospective (Cuối mỗi Sprint - 1.5 giờ)**:
   * **Sprint Review**: Trình diễn tính năng thực tế chạy trên môi trường tích hợp cục bộ (Live Demo of Working Software). Tiêu chí hoàn thành (Definition of Done - DoD) bắt buộc: mã nguồn sạch, có unit test, vượt qua kịch bản kiểm thử tích hợp và tài liệu hóa endpoint.
   * **Sprint Retrospective**: Đánh giá dựa trên 3 trụ cột: *Điều gì nhóm đã làm tốt? (What went well?), Điều gì chưa hiệu quả? (What didn't go well?), Hành động cải tiến cụ thể cho Sprint tiếp theo (Action Items)*.

---

### 3.2.2. Ma trận Phân bổ Trách nhiệm Kỹ thuật (RACI Matrix)

Nhóm áp dụng mô hình phân nhiệm **RACI** (*Responsible* - Người thực hiện chính, *Accountable* - Người chịu trách nhiệm giải trình cuối cùng, *Consulted* - Người được tham vấn chuyên môn, *Informed* - Người được thông báo kết quả) nhằm định rõ quyền hạn và nghĩa vụ:

| Hạng mục / Module kỹ thuật | Trưởng nhóm (SV1) | Thành viên (SV2) | Ghi chú quy chuẩn phối hợp |
| :--- | :---: | :---: | :--- |
| **Kiến trúc tổng thể & Chuẩn giao tiếp liên Service** | **A / R** | **C / I** | SV1 chốt chuẩn CloudEvents v1.0.2 và REST API Guidelines; SV2 rà soát tính khả thi trên Laravel. |
| **Nhóm Services C# .NET 10 (`inventory`, `purchase`, `chat`)** | **A / R** | **C / I** | SV1 lập trình lõi; SV2 phối hợp kiểm thử kết nối từ Sales và Frontend. |
| **Nhóm Services PHP Laravel 13 (`identity`, `catalog`, `sales`, `finance`, `eoffice`)** | **C / I** | **A / R** | SV2 lập trình lõi; SV1 tham vấn thiết kế schema và cơ chế gọi giữ kho nguyên tử. |
| **Kho Dữ liệu Phân tích (`erp_analytics_db`) & Mô hình Machine Learning (`ai-service`)** | **A / R** | **I** | SV1 xử lý pipeline làm sạch, huấn luyện K-Means, Holt-Winters và viết API FastAPI. |
| **Phân hệ E-Office (Chấm công, Bảng lương, Hạch toán)** | **C / I** | **A / R** | SV2 thiết kế mô hình thực thể và thuật toán tính công; SV1 rà soát luồng hạch toán Sổ cái. |
| **Động cơ Thông báo (`notification-service`) & Mail Queue** | **I** | **A / R** | SV2 xây dựng Node.js Express, Socket.IO và bộ lắng nghe sự kiện RabbitMQ. |
| **Giao diện Người dùng SPA (Vue 3, Pinia, PrimeVue, BI Dashboard)** | **C / I** | **A / R** | SV2 thiết kế component và tích hợp API; SV1 hỗ trợ kiểm thử tính tương thích SignalR Client. |
| **Cấu hình Hạ tầng Docker Compose, Kong Gateway & RabbitMQ** | **C** | **A / R** | SV2 cấu hình network, reverse proxy và volumes; SV1 cấu hình routing topic RabbitMQ. |
| **Kiểm thử Tích hợp E2E & Smoke Test (`smoke-test.ps1`)** | **A** | **R** | SV2 viết script tự động; SV1 nghiệm thu tính toàn vẹn của mã lỗi và correlation ID. |

---

### 3.2.3. Quy trình Quản lý Mã nguồn (Git Flow) & Tiêu chuẩn Code Review

Nhằm triệt tiêu rủi ro ghi đè mã nguồn và duy trì tính ổn định của sản phẩm, nhóm áp dụng mô hình phân nhánh **Git Flow cải tiến** kết hợp các hàng rào kiểm soát tự động:

```text
  main (Production Ready)  ──────────────────────────────────────────────● (Release v1.0)
                                                                         ▲
  staging (Pre-production) ──────────────────────────●───────────────────┤
                                                     ▲                   │
  develop (Integration)    ──────●───────────────────┴────────●──────────┘
                                 ▲                            ▲
  feature/inventory-lock   ──────┴─── [PR + 1 Review] ────────┘
```

1. **Quy tắc phân nhánh nghiêm ngặt**:
   * `main`: Nhánh ổn định tuyệt đối, sẵn sàng triển khai thực tế. Chỉ cập nhật thông qua Pull Request từ `staging` khi toàn bộ kiểm thử tích hợp đạt 100%.
   * `staging`: Môi trường kiểm thử tích hợp tổng thể (Staging Environment).
   * `develop`: Nhánh làm việc chung hàng ngày của nhóm.
   * `feature/<service-name>-<tên-tính-năng>`: Nhánh phát triển tính năng riêng lẻ (ví dụ: `feature/inventory-atomic-reserve`, `feature/chat-signalr-mongodb`, `feature/eoffice-payroll-calculator`).
   * `bugfix/<service-name>-<mã-lỗi>`: Nhánh khắc phục sự cố khẩn cấp.

2. **Quy chuẩn thông điệp Commit (Conventional Commits v1.0.0)**:
   * Định dạng chuẩn: `<loại>(<phạm vi>): <mô tả ngắn ngọn bằng tiếng Anh hoặc tiếng Việt>`
   * Các tiền tố bắt buộc:
     * `feat`: Bổ sung tính năng nghiệp vụ mới (ví dụ: `feat(inventory): implement batch reservation with pessimistic lock`).
     * `fix`: Sửa lỗi hệ thống (ví dụ: `fix(chat): handle mongo serialization for reactions array`).
     * `docs`: Cập nhật tài liệu kỹ thuật hoặc báo cáo (ví dụ: `docs(report): update chapter 3 project schedule and gantt`).
     * `refactor`: Tái cấu trúc mã nguồn mà không làm thay đổi hành vi nghiệp vụ.
     * `test`: Thêm mới hoặc chỉnh sửa kịch bản kiểm thử tự động.
     * `chore`: Cấu hình build, dependency hoặc công cụ Docker.

3. **Quy trình Code Review bắt buộc 2 chiều (Peer Review Protocol)**:
   * **Nguyên tắc "Bốn con mắt" (Four-Eyes Principle)**: Không một thành viên nào được phép tự ý gộp (merge) mã nguồn vào `develop` hoặc `main`. Mọi Pull Request bắt buộc phải có ít nhất **1 phê duyệt (Approval)** từ thành viên còn lại.
   * **Bảng kiểm tra (Code Review Checklist)** trước khi bấm nút Merge:
     1. [x] Mã nguồn tuân thủ Clean Architecture 4 tầng phân lập, không vi phạm phụ thuộc vòng.
     2. [x] Không tồn tại truy vấn N+1 hoặc câu lệnh SQL nối chuỗi trực tiếp (phòng chống SQL Injection).
     3. [x] Các endpoint ghi dữ liệu quan trọng đều có xác thực JWT và kiểm tra quyền RBAC.
     4. [x] Toàn bộ thao tác biến động kho đều kiểm tra ràng buộc bất biến SCM $\text{Available} \ge 0$.
     5. [x] Các sự kiện tên miền bắn lên RabbitMQ tuân thủ đúng định dạng chuẩn CloudEvents JSON.
     6. [x] Mã nguồn vượt qua 100% các bài Unit/Integration Test tương ứng cục bộ.

---

### 3.2.4. Quy trình Tích hợp Liên tục & Kiểm thử Tự động (Automated CI/CD Pipeline)

Để phát hiện sớm các lỗi hồi quy (Regression Bugs), nhóm đã thiết lập chuỗi kiểm thử tự động trên máy trạm cục bộ và máy chủ tích hợp:

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                      CHUỖI KIỂM THỬ TỰ ĐỘNG CỦA SMART DISTRIBUTED ERP                           │
├──────────────────────┬──────────────────────┬──────────────────────┬────────────────────────────┤
│ 1. Static Analysis   │ 2. Unit & Integration│ 3. Automated Smoke   │ 4. Build & Containerize    │
│    (Code Quality)    │    (Domain Logic)    │    (End-to-End API)  │    (Zero-Error Package)    │
├──────────────────────┼──────────────────────┼──────────────────────┼────────────────────────────┤
│ • C# Roslyn Linter   │ • 21 tests xUnit C#  │ • scripts/smoke-     │ • Vite build SPA Vue 3     │
│ • PHPStan / Pint     │   (ChatApiTests)     │   test.ps1 kiểm tra  │   (0 warning, 0 error)     │
│ • Flake8 / Black     │ • PHPUnit / Pest     │   11/11 endpoints    │ • Docker Compose Multi-    │
│ • ESLint Frontend    │ • 21 bài kiểm định ML│   qua Kong Gateway   │   stage Build Images       │
│                      │   (ml_validation)    │ • Correlation-ID check│                           │
└──────────────────────┴──────────────────────┴──────────────────────┴────────────────────────────┘
```

* **Kiểm thử đơn vị & tích hợp C# (`chat-service` & `inventory-service`)**: Sử dụng framework **xUnit**, **FluentAssertions** và **WebApplicationFactory** trong bộ test [ChatApiTests.cs](../../services/chat-service/tests/Chat.Tests/ChatApiTests.cs) (21 tests vượt qua 100%) để kiểm tra luồng tạo phòng, gửi tin nhắn, đính kèm file, phản hồi reaction và đồng bộ cursor `after_id`.
* **Kiểm định chất lượng mô hình Machine Learning**: Thực thi kịch bản [ml_validation_pipeline.py](../../scripts/ml_validation_pipeline.py) kiểm định tự động **21/21 tiêu chí chất lượng dữ liệu** (Độ tươi dữ liệu $\ge 700$ ngày, kiểm tra dải tin cậy 95%, sóng tuần doanh thu Thứ Hai/Chủ Nhật, cân đối dòng tiền kế toán, bảo toàn 2.591 khách hàng RFM).
* **Kiểm thử khói hệ thống toàn diện (`smoke-test.ps1`)**: Script PowerShell tự động kích hoạt kiểm tra sức khỏe của toàn bộ 10 microservices thông qua Kong Gateway (cổng 8000) và qua Nginx Reverse Proxy (cổng 5175), xác thực mã trạng thái HTTP 200 và sự hiện diện của header truy vết phân tán `X-Correlation-ID`.

---

## 3.3. CÁC VẤN ĐỀ VỀ ĐẠO ĐỨC VÀ LÀM VIỆC CHUYÊN NGHIỆP

Nhóm cam kết thực hiện đồ án dựa trên nền tảng đạo đức nghề nghiệp kỹ sư phần mềm chuẩn mực theo Bộ quy tắc đạo đức **ACM/IEEE-CS Code of Ethics and Professional Practice**. Các vấn đề đạo đức được nhận diện và giải quyết xuyên suốt đồ án:

### 3.3.1. Tuân thủ Pháp luật Việt Nam về Bảo vệ Dữ liệu Cá nhân (Nghị định 13/2023/NĐ-CP)

Dự án phân phối B2B xử lý thông tin cá nhân của hàng ngàn đại lý, chủ hộ kinh doanh và nhân sự nội bộ. Nhóm đã hiện thực hóa kỹ thuật để tuân thủ triệt để **Nghị định số 13/2023/NĐ-CP** ngày 17/04/2023 của Chính phủ:

1. **Phân loại dữ liệu cá nhân (Điều 2)**:
   * Hệ thống phân tách rạch ròi giữa **Dữ liệu cá nhân cơ bản** (Họ tên, ngày sinh, số điện thoại, địa chỉ giao hàng) và **Dữ liệu cá nhân nhạy cảm** (Số tài khoản ngân hàng, lịch sử tín dụng công nợ, mức thu nhập, dữ liệu sinh trắc học vân tay chấm công).
   * Dữ liệu nhạy cảm được cô lập trong các bảng chuyên biệt, mã hóa tĩnh bằng thuật toán **AES-256** và chỉ giải mã trên bộ nhớ RAM khi người dùng có thẩm quyền yêu cầu.

2. **Giải quyết xung đột pháp lý giữa Quyền được xóa dữ liệu (Right to be Forgotten - Điều 9) và Luật Kế toán 2015**:
   * *Nghịch lý pháp lý*: Khoản 7 Điều 9 Nghị định 13 quy định chủ thể dữ liệu có quyền yêu cầu xóa bỏ vĩnh viễn dữ liệu cá nhân của mình. Tuy nhiên, Điều 41 Luật Kế toán 2015 quy định toàn bộ hóa đơn, chứng từ kế toán, sổ theo dõi công nợ phải lưu trữ tối thiểu **05 đến 10 năm** để phục vụ thanh tra thuế.
   * *Giải pháp kỹ thuật của nhóm*: Áp dụng kỹ thuật **Ẩn danh hóa dữ liệu (Data Masking & Pseudonymization)**:
     - Khi một đại lý hoặc nhân sự rời khỏi hệ thống và yêu cầu xóa thông tin: Danh tính trực tiếp được thay thế bằng chuỗi giả danh ngẫu nhiên (ví dụ: Họ tên $\to$ `Khách hàng Ẩn danh #ID_2591`, Số điện thoại $\to$ `0987***999`, CCCD $\to$ `Băm SHA-256 một chiều`).
     - Toàn bộ giá trị giao dịch, số tiền thanh toán, số hóa đơn VAT và bút toán sổ cái gốc được **bảo toàn nguyên vẹn 100%**. Hệ thống vừa đáp ứng tuyệt đối yêu cầu bảo vệ quyền riêng tư cá nhân theo Nghị định 13, vừa tuân thủ đầy đủ giá trị pháp lý kế toán của Nhà nước.

3. **Chống rò rỉ dữ liệu qua kênh chat không chính thức (Elimination of Shadow IT)**:
   * Thay vì để nhân viên chụp ảnh CCCD, hợp đồng tín dụng và chuyển khoản qua Zalo/Facebook cá nhân (dễ bị chụp màn hình tuồn ra ngoài khi nghỉ việc), tính năng **Work Thread** của `chat-service` nhúng trực tiếp vào chứng từ ERP, kiểm soát quyền truy cập theo phiên làm việc, lưu trữ trên MongoDB nội bộ của doanh nghiệp và ghi vết kiểm toán (Audit Trail) bất biến.

---

### 3.3.2. Tính Trung thực, Liêm chính Kỹ thuật và Minh bạch Tài chính

1. **Bảo toàn dữ liệu tài chính qua cơ chế Reference Snapshotting**:
   * Trong thực tế vận hành ERP, có hiện tượng gian lận hồi tố: người dùng thay đổi giá gốc trong danh mục nhằm làm biến động biên lợi nhuận của các đơn hàng quá khứ.
   * Nhóm áp dụng nguyên tắc **Reference Snapshotting**: Khi một Báo giá chuyển thành Đơn bán (SO) hoặc Hóa đơn VAT, toàn bộ thông tin sản phẩm, đơn giá tại thời điểm ký kết được sao chép cứng vào bảng chi tiết chứng từ. Ngay cả khi phòng kinh doanh đổi giá niêm yết trong `catalog-service`, chứng từ tài chính cũ không bị xáo trộn, bảo đảm tính trung thực tuyệt đối của số liệu kế toán trước kiểm toán độc lập.

2. **Liêm chính trong Thuật toán Học máy và Trí tuệ Nhân tạo (AI Transparency & Fairness)**:
   * Toàn bộ các thuật toán phân tích (K-Means, Holt-Winters, Credit Risk Scoring) đều được xây dựng trên công thức toán học minh bạch, công khai sai số đo đạc ($\text{MAPE} = 6.2\%$, $\text{Silhouette} = 0.477$).
   * Thuật toán chấm điểm tín dụng đại lý tuyệt đối dựa trên các tham số tài chính khách quan (Hạn mức, Tỷ lệ nợ quá hạn, Tốc độ quay vòng công nợ), không tích hợp các biến nhạy cảm mang tính định kiến (giới tính, vùng miền, tôn giáo) nhằm đảm bảo sự bình đẳng, công bằng cho mọi đối tác đại lý.

---

### 3.3.3. Tôn trọng Bản quyền Mã nguồn Mở và Sở hữu Trí tuệ

Nhóm cam kết tuân thủ 100% bản quyền sở hữu trí tuệ:
* Toàn bộ các framework, thư viện sử dụng trong hệ thống đều là mã nguồn mở hợp pháp với giấy phép mở thân thiện với doanh nghiệp:
  * **MIT License**: ASP.NET Core, Laravel 13, Vue 3, Pinia, PrimeVue, Tailwind CSS, Express, Socket.IO, Fastify.
  * **Apache 2.0 License**: Kong Gateway, RabbitMQ Client, Scikit-learn, Statsmodels.
  * **Server Side Public License (SSPL) / GNU AGPL / BSD**: MongoDB Community Server, MySQL Community, Pandas, NumPy.
* Không sử dụng bất kỳ đoạn mã bẻ khóa (crack), mã nguồn chiếm dụng trái phép hoặc tài nguyên vi phạm bản quyền thương mại.
* Mọi lý thuyết thuật toán, mô hình kiến trúc trích dẫn đều ghi rõ nguồn gốc học thuật tại Chương 5 (Tài liệu tham khảo).

---

## 3.4. TÁC ĐỘNG XÃ HỘI (SOCIAL IMPACT)

Hệ thống **Smart Distributed ERP** được nghiên cứu và thiết kế không chỉ mang giá trị học thuật kỹ thuật mà còn hướng tới giải quyết các vấn đề kinh tế - xã hội cụ thể tại Việt Nam:

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                           4 TÁC ĐỘNG XÃ HỘI CỐT LÕI CỦA DỰ ÁN                                   │
├──────────────────────────────┬──────────────────────────────┬───────────────────────────────────┤
│ 1. KINH TẾ (ECONOMIC)        │ 2. VẬN HÀNH (OPERATIONAL)    │ 3. MÔI TRƯỜNG (ENVIRONMENTAL)     │
├──────────────────────────────┼──────────────────────────────┼───────────────────────────────────┤
│ • Tiết kiệm 70 - 85% chi phí │ • Triệt tiêu 100% bán khống  │ • Văn phòng không giấy tờ         │
│   đầu tư ERP cho SMEs        │ • Giảm tồn kho ứ đọng 15-20% │   (Paperless Green Office)        │
│ • Mã nguồn mở, triển khai    │ • Rút ngắn chu kỳ đặt hàng   │ • Giảm điện năng làm mát kho bãi  │
│   Docker 1 lệnh tối ưu       │   từ 3-5 ngày xuống 15 phút  │ • Giảm thiểu rác thải điện tử     │
└──────────────────────────────┴──────────────────────────────┴───────────────────────────────────┘
```

### 1. Thúc đẩy Chuyển đổi Số Bình đẳng cho Khối Doanh nghiệp Vừa và Nhỏ (SMEs)
* Tại Việt Nam, hơn 97% doanh nghiệp thuộc khối SMEs. Rào cản lớn nhất ngăn các doanh nghiệp này tiếp cận ERP là chi phí bản quyền khổng lồ của các giải pháp ngoại (SAP, Oracle tiêu tốn hàng trăm ngàn USD).
* Dự án cung cấp một kiến trúc microservices mã nguồn mở hiện đại, có thể vận hành ổn định trên máy chủ điện toán đám mây cấu hình tiêu chuẩn (Cloud VPS giá rẻ), giúp các doanh nghiệp phân phối trong nước nâng cao năng lực cạnh tranh bình đẳng với các tập đoàn đa quốc gia.

### 2. Tối ưu hóa Chuỗi Cung ứng & Tiết kiệm Tài nguyên Kinh tế Quốc gia
* Ngành phân phối thiết bị gia dụng tại Việt Nam thường xuyên chịu tổn thất lớn do dự báo sai nhu cầu mùa vụ (thiếu quạt/điều hòa mùa hè nhưng ứ hàng vào mùa đông).
* Việc ứng dụng mô hình học máy dự báo chuỗi thời gian kết hợp biến ngoại sinh thời tiết giúp doanh nghiệp nhập hàng vừa đủ nhu cầu thị trường, giảm tỷ lệ tồn kho ứ đọng từ **15 - 20%**. Dòng vốn lưu động xã hội được giải phóng, tránh lãng phí hàng ngàn tỷ đồng vốn nhàn rỗi trong kho bãi.

### 3. Bảo vệ Môi trường và Thúc đẩy Văn phòng Không Giấy tờ (Paperless Green Office)
* Số hóa toàn bộ chu trình từ Báo giá $\to$ Đơn hàng $\to$ Phiếu xuất kho $\to$ Hóa đơn VAT điện tử $\to$ Quy trình ký duyệt nghỉ phép, bảng lương giúp loại bỏ hoàn toàn việc in ấn hàng trăm ngàn chứng từ giấy mỗi năm tại mỗi doanh nghiệp.
* Việc giảm tồn kho ứ đọng giúp thu hẹp diện tích kho bãi cần làm mát và bảo quản liên tục bằng năng lượng điện, giảm phát thải khí nhà kính từ các hệ thống điều hòa kho công nghiệp, đóng góp vào mục tiêu phát triển bền vững (ESG).

### 4. Nâng cao Chất lượng Môi trường Làm việc và Văn hóa Doanh nghiệp
* Cơ chế phân quyền minh bạch, tính công tự động chuẩn xác theo từng phút quẹt thẻ, tra cứu phiếu lương cá nhân rõ ràng giúp xóa bỏ hoàn toàn các tranh chấp, khiếu nại giữa người lao động và bộ phận nhân sự.
* Luồng giao tiếp Work Thread gắn liền chứng từ giúp nhân viên kinh doanh, kế toán và thủ kho làm việc nhịp nhàng, giảm bớt căng thẳng giao tiếp nội bộ và nâng cao rõ rệt năng suất lao động.

---

## 3.5. KẾ HOẠCH CHO KIẾN THỨC MỚI VÀ CHIẾN LƯỢC HỌC TẬP (LIFELONG LEARNING)

Đồ án liên ngành đặt ra thách thức công nghệ rất lớn khi đòi hỏi nhóm sinh viên phải làm chủ và kết hợp nhuần nhuyễn nhiều nền tảng công nghệ mới chưa có trong chương trình giảng dạy cơ sở:

### 3.5.1. Phân tích Khoảng cách Kiến thức (Knowledge Gap Analysis)

| Lĩnh vực công nghệ | Kiến thức nền tảng ban đầu | Kiến thức chuyên sâu mới làm chủ qua đồ án |
| :--- | :--- | :--- |
| **C# & Nền tảng .NET** | C# cơ bản, lập trình hướng đối tượng OOP, Windows Forms. | **.NET 10 Web API**, ASP.NET Core SignalR, Tối ưu Concurrency, Task Parallel Library (TPL), EF Core với Raw SQL Locking (`SELECT ... FOR UPDATE`), xUnit Integration Testing với TestContainers/WebApplicationFactory. |
| **PHP & Laravel Framework** | PHP cơ bản, mô hình MVC, lập trình web thông thường. | **PHP 8.4 & Laravel 13**, thiết kế Clean Architecture, Dynamic RBAC Middleware hạt mịn, Transactional Outbox Pattern với Background Relay, Job Queue. |
| **Hệ thống Phân tán (Distributed Systems)** | Khái niệm Client-Server, CSDL quan hệ tập trung. | Nguyên tắc **Database-per-Service**, Bounded Contexts theo Domain-Driven Design (DDD), Event-Driven Architecture qua **RabbitMQ Topic Exchange & Dead-Letter Exchange (DLX)**, Kong API Gateway DB-less routing, Truy vết phân tán (Distributed Tracing qua `Correlation-ID`). |
| **Khoa học Dữ liệu & Học máy** | Đại số tuyến tính, thống kê mô tả, kiến thức ML lý thuyết. | **Star Schema Data Warehouse** trên MySQL, Pipeline xử lý ngoại lai IQR, Phân cụm **K-Means Clustering** RFM, Dự báo chuỗi thời gian **Holt-Winters Exponential Smoothing kết hợp Hồi quy**, Đánh giá sai số MAPE & Silhouette Score, Xây dựng REST API hiệu năng cao với In-memory Caching trên FastAPI. |
| **Cơ sở dữ liệu Đa hình (Polyglot Persistence)** | MySQL cơ bản (các câu lệnh SELECT/JOIN đơn giản). | Kết hợp hài hòa **SQL (MySQL 8)** cho giao dịch tài chính ACID khắt khe và **NoSQL (MongoDB 7 Replica Set)** cho dữ liệu phi cấu trúc có tần suất ghi cao (Chat Messages, Reactions, Push Notifications). |
| **Frontend Chuyên nghiệp** | HTML/CSS/JavaScript cơ bản. | **Vue 3.5 Composition API**, Vite 8, Quản trị trạng thái tập trung **Pinia 4**, Thư viện UI doanh nghiệp **PrimeVue**, Tailwind CSS, Tích hợp SignalR WebSocket Client và biểu đồ động Chart.js. |

---

### 3.5.2. Phương pháp luận và Chiến lược Học tập Hiệu quả (Active Learning Strategies)

Để hoàn thành khối lượng kiến thức khổng lồ trên trong vòng 10 tuần, nhóm đã áp dụng 4 chiến lược học tập chủ động:

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                           4 CHIẾN LƯỢC HỌC TẬP CHỦ ĐỘNG CỦA NHÓM                                │
├──────────────────────────────┬──────────────────────────────┬───────────────────────────────────┤
│ 1. HỌC QUA THỰC HÀNH         │ 2. TIẾP CẬN TÀI LIỆU GỐC     │ 3. PHÂN TÍCH ĐỐI CHUẨN            │
│    (LEARNING BY DOING & TDD) │    (OFFICIAL SPECS & RFCS)   │    (BENCHMARK & EXPERIMENTATION)  │
├──────────────────────────────┼──────────────────────────────┼───────────────────────────────────┤
│ • Viết test trước / song song│ • Đọc Microsoft Learn Docs   │ • Thử nghiệm tải đồng thời        │
│ • Tự cài đặt và gỡ lỗi thực  │ • CloudEvents v1.0.2 Spec    │ • Đo đạc độ trễ và thông lượng    │
│ • Kiểm chứng qua logs hệ thống│ • Tài liệu chuẩn Laravel, Vue│ • So sánh các thuật toán học máy  │
└──────────────────────────────┴──────────────────────────────┴───────────────────────────────────┘
```

1. **Phương pháp "Học qua Thực hành & Kiểm chứng" (Learning by Doing & Test-Driven Verification)**:
   * Không tiếp thu kiến thức một cách thụ động qua video mà trực tiếp bắt tay vào xây dựng các dự án mẫu nhỏ (Proof of Concept - PoC) trước khi đưa vào codebase chính thức (ví dụ: viết PoC kiểm tra hành vi khóa `SELECT ... FOR UPDATE` trên MySQL 8 trong môi trường đa luồng trước khi triển khai vào `inventory-service`).

2. **Khai thác Tài liệu Chuẩn hóa và Đặc tả Kỹ thuật Gốc (First-principles & Official Specs)**:
   * Ưu tiên đọc trực tiếp tài liệu kỹ thuật chính thống: *Microsoft Learn (.NET 10, SignalR)*, *CloudEvents Specification v1.0.2*, *RabbitMQ Tutorials*, *Laravel 13 Documentation*, *Vue 3 Official Guide*.
   * Đọc các công trình nghiên cứu học thuật về chuỗi thời gian (sách *Forecasting: Principles and Practice* của Rob J. Hyndman) để hiểu cặn kẽ bản chất công thức toán học thay vì chỉ gọi hàm đen (black-box libraries).

3. **Thực hành Lập trình Cặp & Chia sẻ Kiến thức chéo (Pair Programming & Cross-skilling)**:
   * SV1 (chuyên sâu .NET & Python) và SV2 (chuyên sâu PHP & Vue 3) tổ chức các buổi Code Walkthrough hàng tuần để hướng dẫn lẫn nhau: SV1 chia sẻ về cơ chế luồng và SignalR; SV2 chia sẻ về Vue 3 Composition API và cơ chế Eloquent ORM. Điều này giúp cả hai thành viên đều nắm bắt được bức tranh toàn cảnh của hệ thống mà không bị cô lập kỹ năng (Silo effect).

---

### 3.5.3. Kế hoạch Phát triển Năng lực Dài hạn (Future Learning Roadmap)

Từ những thành quả kỹ thuật ban đầu của đồ án, nhóm xây dựng lộ trình tiếp tục học tập và nâng cao năng lực chuyên môn sau khi hoàn thành đồ án:

1. **Lộ trình ngắn hạn (6 tháng tiếp theo)**:
   * Nghiên cứu sâu về công nghệ **Điều phối Container (Container Orchestration)**: Chuyển đổi từ Docker Compose sang **Kubernetes (K8s)**, viết cấu hình triển khai qua Helm Charts, quản lý Ingress Controller và triển khai Service Mesh (**Istio**) để quản trị lưu lượng microservices chuyên nghiệp.
   * Xây dựng chuỗi quy trình **DevOps / CI-CD tự động hóa hoàn toàn** trên nền tảng GitHub Actions hoặc GitLab CI: tự động chạy kiểm thử tĩnh, kiểm thử đơn vị, đóng gói Docker Image và triển khai tự động lên cụm máy chủ đám mây.

2. **Lộ trình trung và dài hạn (1 - 2 năm tiếp theo)**:
   * Nâng cấp kiến trúc dữ liệu sang mô hình **Change Data Capture (CDC)**: Ứng dụng **Debezium** kết hợp **Apache Kafka** để chụp lại mọi thay đổi dữ liệu từ MySQL theo thời gian thực và đẩy sang Kho phân tích, thay thế hoàn toàn cơ chế quét định kỳ ETL hiện tại.
   * Làm chủ kiến trúc **Agentic AI & Multi-Agent Systems**: Nghiên cứu các framework trí tuệ nhân tạo thế hệ mới (LangGraph, Semantic Kernel, CrewAI), kết hợp kỹ thuật truy xuất tăng cường sinh dữ liệu (**RAG**) trên đồ thị tri thức (Graph RAG) để tạo ra các Trợ lý Doanh nghiệp tự chủ, có thể thay thế con người thực hiện các tác vụ điều hành chuỗi cung ứng phức tạp bằng giọng nói và ngôn ngữ tự nhiên tiếng Việt.
