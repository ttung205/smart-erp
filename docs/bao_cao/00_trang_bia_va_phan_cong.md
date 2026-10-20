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
| 3 | `<MSSV_3>` | `<Họ và Tên SV 3>` | `<Lớp_3>` | `<email_3>@st.phenikaa-uni.edu.vn` |
| 4 | `<MSSV_4>` | `<Họ và Tên SV 4>` | `<Lớp_4>` | `<email_4>@st.phenikaa-uni.edu.vn` |

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

| Mã sinh viên | Họ và tên | Vai trò chính | Nhiệm vụ phân công chi tiết | Tỷ lệ đóng góp |
| :--- | :--- | :--- | :--- | :---: |
| `<MSSV_1>` | `<Trưởng nhóm / SV 1>` | Trưởng nhóm & Backend Lead | - Phân tích kiến trúc Microservices, Clean Architecture 4 tầng.<br>- Triển khai `sales-service` và `inventory-service` bằng C# (.NET 10).<br>- Thiết kế Saga Orchestration và cơ chế khóa phân tán giữ chỗ tồn kho (Atomic Stock Reservation).<br>- Viết báo cáo Phần 1 & Phần 2. | 25% |
| `<MSSV_2>` | `<Thành viên / SV 2>` | Realtime & Infrastructure | - Thiết kế và xây dựng microservice `chat-service` bằng C# (.NET 10) + ASP.NET Core SignalR.<br>- Triển khai tính năng Chat 1-1, Nhóm, Work Thread thảo luận gắn chứng từ ERP.<br>- Thiết kế cơ chế phục hồi kết nối (Resilience Reconnect) và tải bù tin nhắn (Catch-up Sync).<br>- Cấu hình Docker Compose, Reverse Proxy Gateway và RabbitMQ Event Broker. | 25% |
| `<MSSV_3>` | `<Thành viên / SV 3>` | AI & Data Science Lead | - Khai thác và chuẩn hóa kho dữ liệu phân tích Star Schema (`analytics.db`).<br>- Xây dựng thuật toán phân cụm khách hàng RFM bằng **K-Means Clustering**.<br>- Triển khai mô hình dự báo chuỗi thời gian doanh số 30 ngày bằng **Holt-Winters Smoothing & Regression**.<br>- Xây dựng thuật toán chấm điểm rủi ro công nợ (Credit Risk Scoring).<br>- Phát triển REST API phân tích dữ liệu trên FastAPI (`ai-service`). | 25% |
| `<MSSV_4>` | `<Thành viên / SV 4>` | Frontend & UI/UX Lead | - Xây dựng Single Page Application (SPA) bằng Vue 3, Pinia, PrimeVue 4 và Tailwind CSS theo chuẩn Clean Flat White Canvas.<br>- Triển khai Dashboard Quản trị BI trực quan tương tác (`/analytics`).<br>- Xây dựng component dùng chung `WorkThreadDrawer.vue` nhúng vào các chứng từ đơn hàng.<br>- Quản lý định tuyến Vue Router, Navigation Guard phân quyền động RBAC và kiểm thử giao diện. | 25% |
