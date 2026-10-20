# CHƯƠNG 3: MỘT SỐ THÀNH PHẦN KHÁC CỦA ĐỒ ÁN

---

## 3.1. KẾ HOẠCH DỰ ÁN (PROJECT SCHEDULE & GANTT CHART)

Dự án được triển khai theo mô hình linh hoạt **Agile / Scrum** trong thời gian **12 tuần** (tương đương 6 Sprint, mỗi Sprint kéo dài 2 tuần). Kế hoạch phân bổ chi tiết các giai đoạn công việc như sau:

| Giai đoạn (Milestone) | Thời gian | Nhiệm vụ trọng tâm | Sản phẩm bàn giao (Deliverables) |
| :--- | :---: | :--- | :--- |
| **Milestone 1: Khảo sát & Thiết kế Kiến trúc** | Tuần 1 - 2 | - Khảo sát bài toán chuỗi cung ứng phân phối thiết bị gia dụng.<br>- Phân tích ranh giới Bounded Contexts, thiết kế Data Warehouse Star Schema.<br>- Lập tài liệu đặc tả kiến trúc [architecture.md](../Ai_rule/architecture.md) và chuẩn REST API [api-guidelines.md](../Ai_rule/api-guidelines.md). | - Tài liệu kiến trúc hệ thống.<br>- Thiết kế CSDL phân tán cho từng service. |
| **Milestone 2: Xây dựng Core ERP Services** | Tuần 3 - 5 | - Triển khai `identity-service` (RBAC 11 roles, Stateless JWT).<br>- Triển khai `sales-service` và `inventory-service` bằng C# .NET 10.<br>- Thiết kế cơ chế Saga Orchestration và RabbitMQ Outbox Relay. | - API quản lý đơn hàng, kho hàng.<br>- Bộ test kiểm thử ràng buộc bất biến SCM. |
| **Milestone 3: Phát triển Realtime Chat Service** | Tuần 6 - 7 | - Xây dựng `chat-service` độc lập bằng C# (.NET 10) + SignalR Hub.<br>- Xây dựng cơ chế Work Thread gắn chứng từ ERP.<br>- Triển khai lưu trữ file an toàn, ghim tin, emoji reactions, cursor đã đọc.<br>- Viết bộ test tự động [ChatApiTests.cs](../../services/chat-service/tests/Chat.Tests/ChatApiTests.cs) (21 tests). | - Microservice chat cổng 8009.<br>- 100% Unit/Integration Test chat Passed. |
| **Milestone 4: Xây dựng Module Machine Learning & BI** | Tuần 8 - 9 | - Chuẩn hóa kho dữ liệu `analytics.db` (217 tỷ VNĐ).<br>- Huấn luyện mô hình **K-Means Clustering** phân cụm RFM khách hàng.<br>- Xây dựng mô hình **Holt-Winters Forecasting** dự báo chuỗi thời gian 30 ngày.<br>- Xây dựng các API phân tích dữ liệu trên FastAPI (`ai-service`). | - Module học máy `ml.py`.<br>- API endpoints `/api/v1/ai/analytics/*`. |
| **Milestone 5: Phát triển Frontend SPA & Dashboard BI** | Tuần 10 - 11 | - Xây dựng giao diện Vue 3 theo chuẩn Clean Flat White Canvas.<br>- Dựng trang Báo cáo Quản trị BI tương tác (`/analytics`).<br>- Dựng component tái sử dụng `WorkThreadDrawer.vue` trên các màn hình chứng từ.<br>- Tích hợp Pinia store và SignalR WebSocket Client. | - Single Page Application hoàn chỉnh.<br>- Giao diện web build thành công (0 lỗi). |
| **Milestone 6: Kiểm thử Toàn diện & Báo cáo** | Tuần 12 | - Kiểm thử tải đồng thời và kiểm thử tính năng đa trình duyệt.<br>- Rà soát mã nguồn theo các quy tắc trong `docs/Ai_rule/`.<br>- Hoàn thiện báo cáo đồ án và chuẩn bị kịch bản thuyết trình bảo vệ. | - Báo cáo đồ án liên ngành hoàn chỉnh.<br>- Video demo và Slide thuyết trình. |

### Biểu đồ Gantt Kế hoạch Dự án (PlantUML Gantt Chart)

```plantuml
@startgantt
skinparam roundcorner 6
skinparam defaultFontName Arial
skinparam defaultFontSize 11

Project starts 2026-08-03

[1. Khảo sát & Thiết kế Kiến trúc] as [M1] lasts 14 days
[2. Xây dựng Core ERP Services] as [M2] lasts 21 days
[3. Phát triển Realtime Chat Service] as [M3] lasts 14 days
[4. Xây dựng Module Machine Learning & BI] as [M4] lasts 14 days
[5. Phát triển Frontend SPA & Dashboard BI] as [M5] lasts 14 days
[6. Kiểm thử Toàn diện & Viết Báo cáo] as [M6] lasts 7 days

[M2] starts at [M1]'s end
[M3] starts at [M2]'s end - 7 days
[M4] starts at [M3]'s end - 7 days
[M5] starts at [M4]'s end - 7 days
[M6] starts at [M5]'s end

[M1] is colored in LightSkyBlue
[M2] is colored in LightGreen
[M3] is colored in LightPink
[M4] is colored in MediumPurple
[M5] is colored in Khaki
[M6] is colored in Coral
@endgantt
```

---

## 3.2. ĐẢM BẢO THỰC HIỆN ĐÚNG LÀM VIỆC NHÓM (TEAMWORK ASSURANCE)

Để đảm bảo dự án có quy mô phân tán lớn về đích đúng tiến độ và đạt chất lượng kỹ thuật cao nhất, nhóm đã áp dụng kỷ luật làm việc nhóm nghiêm ngặt:

1. **Phương pháp luận Agile / Scrum**:
   * **Daily Standup (15 phút mỗi sáng)**: Họp nhanh qua Discord để trả lời 3 câu hỏi: *Hôm qua đã làm gì? Hôm nay sẽ làm gì? Đang gặp khó khăn/vướng mắc gì cần hỗ trợ?*
   * **Sprint Planning & Review (Đầu và cuối mỗi 2 tuần)**: Xác định rõ Sprint Backlog, chấm điểm Story Points và nghiệm thu tính năng chạy được (Working Software).

2. **Quy trình Quản lý Mã nguồn (Git Flow & Code Review)**:
   * Phân nhánh nghiêm ngặt: `main` (mã nguồn production ổn định), `develop` (nhánh tích hợp), và các nhánh tính năng `feature/<service-name>-<feature>`.
   * **Bắt buộc Code Review**: Tuyệt đối không ai được push trực tiếp vào `develop` hoặc `main`. Mọi Pull Request (PR) phải được ít nhất 1 thành viên khác trong nhóm review, kiểm tra tuân thủ coding style (`docs/Ai_rule/coding-style.md`) và chạy qua toàn bộ test trước khi merge.

3. **Tích hợp Liên tục & Kiểm thử Tự động (Automated Testing)**:
   * Xây dựng kịch bản kiểm thử tự động tại từng service (ví dụ: bộ kiểm thử xUnit gồm 21 bài test của `chat-service` tự động chạy để kiểm tra lỗi hồi quy).
   * Frontend liên tục kiểm tra tính toàn vẹn cú pháp qua `npm run build` của Vite.

---

## 3.3. CÁC VẤN ĐỀ VỀ ĐẠO ĐỨC VÀ LÀM VIỆC CHUYÊN NGHIỆP

Nhóm cam kết tuân thủ các quy tắc đạo đức nghề nghiệp theo chuẩn **ACM/IEEE-CS Code of Ethics**:

1. **Bảo mật và Quyền riêng tư Dữ liệu (Privacy & Data Protection)**:
   * Tuân thủ quy định tại Nghị định 13/2023/NĐ-CP về bảo vệ dữ liệu cá nhân.
   * Dữ liệu khách hàng đại lý, số điện thoại, công nợ và thông tin nội bộ không được chia sẻ cho bất kỳ bên thứ ba nào. Hệ thống thiết lập phân quyền nghiêm ngặt để nhân viên chỉ thấy dữ liệu trong phạm vi công việc được giao.

2. **Tính Trung thực và Minh bạch Tài chính**:
   * Áp dụng nguyên tắc **Data Snapshotting**: Mọi chứng từ giao dịch thương mại (Hóa đơn, Đơn bán hàng) đều lưu cứng giá trị tại thời điểm ký kết, tuyệt đối không cho phép chỉnh sửa hồi tố làm sai lệch doanh thu hoặc sổ sách kế toán thuế.
   * Toàn bộ các thuật toán phân tích số liệu (RFM, Dự báo chuỗi thời gian) đều dựa trên công thức toán học minh bạch, có báo cáo sai số (MAPE, RMSE) rõ ràng, không tạo số liệu giả mạo.

3. **Tôn trọng Bản quyền và Sở hữu Trí tuệ (Intellectual Property)**:
   * 100% các thư viện, framework sử dụng trong dự án đều có giấy phép mã nguồn mở hợp pháp (MIT, Apache 2.0, BSD).
   * Toàn bộ mã nguồn, sơ đồ kiến trúc và báo cáo đồ án đều do nhóm tự nghiên cứu, phát triển và tổng hợp, trích dẫn đầy đủ các nguồn tài liệu tham khảo khoa học.

---

## 3.4. TÁC ĐỘNG XÃ HỘI (SOCIAL IMPACT)

Dự án mang lại những giá trị thực tiễn thiết thực đối với xã hội và cộng đồng doanh nghiệp:

1. **Thúc đẩy Chuyển đổi Số cho Khối Doanh nghiệp Phân phối (SMEs)**:
   * Cung cấp một giải pháp ERP phân tán hiện đại với chi phí đầu tư ban đầu gần như bằng 0 (nhờ mã nguồn mở), giúp hàng chục ngàn doanh nghiệp phân phối vừa và nhỏ tại Việt Nam thoát khỏi sự phụ thuộc vào các phần mềm ngoại đắt đỏ hoặc sổ sách thủ công kém hiệu quả.

2. **Giảm thiểu Lãng phí Chuỗi Cung ứng & Tiết kiệm Tài nguyên Xã hội**:
   * Ứng dụng mô hình Machine Learning dự báo nhu cầu thị trường giúp doanh nghiệp nhập hàng vừa đủ, giảm tỷ lệ tồn kho ứ đọng từ 15 - 20%. Điều này giúp giảm diện tích kho bãi tiêu thụ điện năng làm mát/bảo quản, hạn chế hỏng hóc thiết bị điện máy trong quá trình lưu kho, gián tiếp bảo vệ môi trường.

3. **Môi trường Làm việc Không Giấy tờ (Paperless Office) & Nâng cao Năng suất Lao động**:
   * Việc số hóa toàn bộ quy trình phê duyệt báo giá, lập đơn hàng và trao đổi nội bộ trực tiếp trên chứng từ giúp loại bỏ hoàn toàn việc in ấn giấy tờ, tăng tốc độ xử lý đơn hàng từ hàng giờ xuống còn vài phút, nâng cao rõ rệt chất lượng cuộc sống và hiệu suất của người lao động.

---

## 3.5. KẾ HOẠCH CHO KIẾN THỨC MỚI VÀ CHIẾN LƯỢC HỌC TẬP

Đồ án liên ngành là cơ hội để nhóm chủ động nghiên cứu và làm chủ các công nghệ tiên tiến ngoài chương trình giảng dạy tiêu chuẩn:

1. **Các kiến thức và công nghệ mới đã tiếp cận**:
   * **Nền tảng C# (.NET 10 Web API)**: Làm chủ tính năng mới nhất của ngôn ngữ C# hiện đại, Entity Framework Core 10, ASP.NET Core SignalR cho các ứng dụng thời gian thực chịu tải cao.
   * **Mẫu thiết kế Hệ thống Phân tán (Distributed System Patterns)**: Áp dụng thành công các mẫu thiết kế phức tạp như: *Database-per-Service, Clean Architecture 4 tầng, Transactional Outbox Pattern, Saga Orchestrator và Event-Driven Architecture*.
   * **Khoa học Dữ liệu & Học máy Thực hành (Practical Machine Learning)**: Kỹ thuật xây dựng Data Warehouse (Star Schema), xử lý ngoại lai (Outlier handling), phân cụm phi giám sát (K-Means Clustering) và dự báo chuỗi thời gian (Holt-Winters Exponential Smoothing).
   * **Frontend Chuyên nghiệp**: Vue 3 Composition API, kiến trúc quản trị trạng thái tập trung với Pinia, thư viện UI doanh nghiệp PrimeVue 4 và Tailwind CSS.

2. **Chiến lược học tập liên tục (Lifelong Learning Strategy)**:
   * **Phương pháp "Học qua Thực hành" (Learning by Doing)**: Mỗi thành viên không chỉ dừng lại ở lý thuyết mà đều trực tiếp thiết kế, viết code, sửa lỗi (debug) và viết unit test cho từng module cụ thể.
   * **Đọc tài liệu gốc (Official Documentation)**: Ưu tiên tối đa tài liệu kỹ thuật chuẩn từ Microsoft Docs (.NET/SignalR), Laravel Docs, Vue.js Guide và các bài báo khoa học về thuật toán học máy.
   * **Định hướng phát triển tương lai**: Tiếp tục nghiên cứu mở rộng hệ thống lên kiến trúc điện toán đám mây Kubernetes (K8s), áp dụng CI/CD tự động qua GitHub Actions và nâng cấp mô hình AI phân tích sang xử lý ngôn ngữ tự nhiên (Large Language Model / Copilot Agent).
