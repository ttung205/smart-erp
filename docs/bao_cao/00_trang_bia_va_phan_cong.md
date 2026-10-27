# TRƯỜNG ĐẠI HỌC PHENIKAA
## KHOA CÔNG NGHỆ THÔNG TIN

---

<br>

<div align="center">

# BÁO CÁO ĐỒ ÁN LIÊN NGÀNH

### ĐỀ TÀI:
## NGHIÊN CỨU, THIẾT KẾ VÀ XÂY DỰNG HỆ THỐNG QUẢN TRỊ NGUỒN LỰC DOANH NGHIỆP PHÂN TÁN (SMART DISTRIBUTED ERP) TÍCH HỢP CHAT REALTIME VÀ PHÂN TÍCH DỮ LIỆU THÔNG MINH (MACHINE LEARNING & BI)

<br>

**Mã nhóm (Team ID):** `<Team ID>`  
**Tên dự án (Project Name):** SMART DISTRIBUTED ERP  
**Giảng viên hướng dẫn (Supervisor):** `<Tên Giảng Viên Hướng Dẫn>`  

<br>

**DANH SÁCH SINH VIÊN THỰC HIỆN:**

| STT | Mã sinh viên | Họ và tên | Lớp | Email |
| :---: | :---: | :--- | :---: | :--- |
| 1 | `<MSSV_1>` | `<Họ và Tên SV 1>` | `<Lớp_1>` | `<email_1>@st.phenikaa-uni.edu.vn` |
| 2 | `<MSSV_2>` | `<Họ và Tên SV 2>` | `<Lớp_2>` | `<email_2>@st.phenikaa-uni.edu.vn` |

<br>

*Hà Nội, Tháng 10 Năm 2026*

</div>

---

## MỤC LỤC TỔNG QUAN (TABLE OF CONTENTS)

- [Phân công thực hiện đồ án](#phan-cong-thuc-hien-do-an)
- [1. Giới thiệu (01_gioi_thieu.md)](./01_gioi_thieu.md)
  - 1.1 Đặt vấn đề
  - 1.2 Các giải pháp đã có và hạn chế
  - 1.3 Giải pháp đề xuất
- [2. Thiết kế và triển khai (02_thiet_ke_va_trien_khai.md)](./02_thiet_ke_va_trien_khai.md)
  - 2.1 Các yêu cầu chức năng
  - 2.2 Các yêu cầu phi chức năng
  - 2.3 Các ràng buộc (Triển khai, Kinh tế, Đạo đức)
  - 2.4 Mô hình hệ thống & Thiết kế giải pháp
    - 2.4.1 Các kịch bản Use-cases
    - 2.4.2 Mô hình Use-case (PlantUML)
    - 2.4.3 Mô hình Lớp và Đối tượng / ERD (PlantUML)
    - 2.4.4 Các biểu đồ tuần tự Sequence Diagrams (PlantUML)
    - 2.4.5 Thiết kế màn hình giao diện người dùng (UI/UX)
- [3. Một số thành phần khác của đồ án (03_cac_thanh_phan_khac.md)](./03_cac_thanh_phan_khac.md)
  - 3.1 Kế hoạch dự án (Biểu đồ Gantt PlantUML)
  - 3.2 Đảm bảo thực hiện đúng làm việc nhóm
  - 3.3 Các vấn đề về đạo đức và làm việc chuyên nghiệp
  - 3.4 Tác động xã hội
  - 3.5 Kế hoạch cho kiến thức mới và chiến lược học tập
- [4. Kết luận (04_ket_luan_va_tai_lieu_tham_khao.md)](./04_ket_luan_va_tai_lieu_tham_khao.md)
- [5. Tài liệu tham khảo (04_ket_luan_va_tai_lieu_tham_khao.md)](./04_ket_luan_va_tai_lieu_tham_khao.md)

---

## PHÂN CÔNG THỰC HIỆN ĐỒ ÁN {#phan-cong-thuc-hien-do-an}

| Mã sinh viên | Họ và tên | Vai trò chính | Phân công phụ trách Services & Nhiệm vụ chi tiết | Tỷ lệ đóng góp |
| :--- | :--- | :--- | :--- | :---: |
| `<MSSV_1>` | `<Trưởng nhóm / SV 1>` | **Trưởng nhóm & C# Backend, Realtime & Machine Learning Lead** | **1. Phụ trách nhóm Services C# (.NET 10 Web API) & Realtime:**<br>- `inventory-service` (C# .NET 10): Động cơ quản trị tồn kho tốc độ cao, cơ chế Atomic Concurrency Lock (`SELECT ... FOR UPDATE`), bảo đảm tính bất biến SCM $\text{Available} = \text{OnHand} - \text{Reserved} \ge 0$, xử lý giữ hàng và giải phóng đặt chỗ nguyên tử theo chứng từ (`/stocks/reserve/batch`, `/stocks/release/batch`), thẻ kho điện tử.<br>- `purchase-service` (C# .NET 10): Đề nghị mua sắm (PR), Đơn mua hàng (PO), đối soát 3 bên (Three-Way Matching), Phiếu nhập kho (GRN) và nền tảng Confirmed Outbox Dispatcher.<br>- `chat-service` (C# .NET 10): Microservice giao tiếp thời gian thực bằng **ASP.NET Core SignalR**, lưu trữ tin nhắn phân tán trên **MongoDB** (`erp_chat_db`), tính năng Work Thread nhúng trực tiếp chứng từ ERP, cơ chế Catch-up Sync tải bù tin nhắn qua cursor `after_id`.<br>**2. Phụ trách Module Machine Learning & Kho phân tích (`ai-service` - Python FastAPI):**<br>- Chuẩn hóa và xử lý kho dữ liệu phân tích 2 năm trong `kaggle_dataset/` (765.4 tỷ VNĐ, 61.982 giao dịch), lưu trữ tập trung tại MySQL (`erp_analytics_db`).<br>- Nghiên cứu và huấn luyện mô hình **K-Means Clustering** phân cụm RFM khách hàng ($k=4$).<br>- Xây dựng mô hình **Holt-Winters Exponential Smoothing kết hợp Hồi quy** dự báo chuỗi thời gian doanh số 30 ngày tiếp theo kèm dải tin cậy 95%.<br>- Xây dựng thuật toán chấm điểm rủi ro công nợ (**Credit Risk Scoring**).<br>- Phát triển hệ thống REST API phân tích (`/api/v1/ai/analytics/*`) trên FastAPI kèm cơ chế In-memory Caching phản hồi siêu tốc (< 50ms) phục vụ Dashboard BI và Trợ lý AI hội thoại.<br>**3. Viết báo cáo:**<br>- Chương 1, Chương 2 (Kiến trúc phân tán, Thiết kế các service C#, Đặt chỗ tồn kho nguyên tử theo chứng từ, SCM Invariant, Chat SignalR trên MongoDB, Module Machine Learning & Khoa học dữ liệu). | **50%** |
| `<MSSV_2>` | `<Thành viên / SV 2>` | **PHP Backend, Frontend & Infrastructure Lead** | **1. Phụ trách nhóm Services PHP (Laravel 13+) & Node.js:**<br>- `identity-service` (PHP): Xác thực tập trung JWT, phân quyền động 11 Roles (Dynamic RBAC hạt mịn) bao gồm toàn bộ quyền Chấm công & Bảng lương.<br>- `catalog-service` (PHP): Quản lý Master Data (916 sản phẩm gia dụng thật, đối tác đại lý/nhà cung cấp), Transactional Outbox Relay.<br>- `sales-service` (PHP): Báo giá (Quotation), Đơn bán hàng (SO), Hóa đơn xuất kho (Invoice/Dispatch), Data Snapshotting và cơ chế gọi giữ chỗ tồn kho nguyên tử trực tiếp sang Inventory.<br>- `finance-service` (PHP): Quản lý hóa đơn VAT, sổ cái, thu chi, công nợ phải thu, kiểm soát hạn mức tín dụng và hạch toán tự động bút toán lương nhân sự (`PayrollJournalController`).<br>- `eoffice-service` (PHP): Văn phòng số, quy trình phê duyệt nội bộ, module **Chấm công & Bảng lương (Attendance & Payroll)** toàn diện (máy chấm công, ca làm việc, tính công, phiếu lương, hạch toán lương).<br>- `notification-service` (Node.js): Động cơ thông báo đa kênh (Socket.IO, Email SMTP queue với Mailpit), lưu trữ dữ liệu thông báo trên **MongoDB** (`erp_notification_db`), lắng nghe sự kiện từ RabbitMQ.<br>**2. Giao diện người dùng (Frontend SPA) & Dashboard BI:**<br>- Phát triển toàn bộ Single Page Application bằng **Vue 3, Pinia, PrimeVue, Tailwind CSS**, component `WorkThreadDrawer.vue` nhúng vào các chứng từ đơn hàng.<br>- Xây dựng giao diện **Dashboard Quản trị BI** trực quan tương tác cao trên Web (`/analytics`) với Chart.js (thẻ KPI doanh thu, biểu đồ thực tế & dự báo ML, biểu đồ phân cụm RFM tương tác lọc khách hàng).<br>- Xây dựng các trang Chấm công (`/eoffice/attendance`), Yêu cầu chấm công, Bảng lương (`/eoffice/payroll`), Phiếu lương (`/eoffice/payslip`).<br>**3. Cấu hình Hạ tầng phân tán & DevOps (Infrastructure):**<br>- Đóng gói toàn bộ hệ sinh thái 10 microservices, MySQL 8, MongoDB 7, RabbitMQ (Topic Exchange `erp.events`, Dead-Letter `erp.dlx`), Kong Gateway bằng **Docker Compose**.<br>**4. Viết báo cáo:**<br>- Chương 2 (Thiết kế các service PHP, Giao diện Vue 3, Dashboard BI, Cấu hình hạ tầng Docker/RabbitMQ/Kong Gateway), Chương 3, Chương 4 và kiểm thử toàn hệ thống. | **50%** |

