# BÁO CÁO ĐỒ ÁN LIÊN NGÀNH: HỆ THỐNG SMART DISTRIBUTED ERP

> **Khoa Công nghệ Thông tin — Trường Đại học Phenikaa**  
> **Đề tài**: Nghiên cứu, Thiết kế và Xây dựng Hệ thống Quản trị Nguồn lực Doanh nghiệp Phân tán (Smart Distributed ERP) Tích hợp Chat Realtime và Phân tích Dữ liệu Thông minh (Machine Learning & BI).

---

## CẤU TRÚC BỘ TÀI LIỆU BÁO CÁO (DOCUMENTATION STRUCTURE)

Bộ báo cáo đồ án được chuẩn hóa theo đúng mẫu quy định của **Khoa Công nghệ Thông tin - Trường Đại học Phenikaa**, được phân tách thành các tập tin markdown chi tiết trong thư mục [docs/bao_cao/](./bao_cao/):

| Tập tin | Tên chương / Nội dung | Tóm tắt nội dung chính |
| :--- | :--- | :--- |
| 📄 [00_trang_bia_va_phan_cong.md](./bao_cao/00_trang_bia_va_phan_cong.md) | **Trang bìa, Mục lục & Phân công** | - Trang bìa chuẩn Đại học Phenikaa, thông tin nhóm, GVHD.<br>- Bảng phân công nhiệm vụ chi tiết và tỷ lệ đóng góp của từng thành viên. |
| 📄 [01_gioi_thieu.md](./bao_cao/01_gioi_thieu.md) | **Chương 1: Giới thiệu** | - **1.1 Đặt vấn đề**: Bài toán chuỗi cung ứng điện máy gia dụng, bán khống tồn kho, rủi ro công nợ, giao tiếp phân mảnh.<br>- **1.2 Các giải pháp đã có**: Phân tích SAP, Odoo, phần mềm nội địa và chỉ ra các hạn chế.<br>- **1.3 Giải pháp đề xuất**: Hệ thống Smart Distributed ERP (Microservices + Realtime Chat + ML/BI). |
| 📄 [02_thiet_ke_va_trien_khai.md](./bao_cao/02_thiet_ke_va_trien_khai.md) | **Chương 2: Thiết kế & Triển khai** | - **2.1 Yêu cầu chức năng**: 8 phân hệ cốt lõi (Bán hàng, Kho SCM, Mua hàng, Kế toán, Chat, BI...).<br>- **2.2 Yêu cầu phi chức năng**: Hiệu năng, tính bất biến SCM Invariant, bảo mật RBAC.<br>- **2.3 Các ràng buộc**: Triển khai Docker, công nghệ .NET/Python/Laravel, kinh tế, đạo đức.<br>- **2.4 Mô hình giải pháp (PlantUML)**:<br>  * 2.4.1 Kịch bản Use-case.<br>  * 2.4.2 Sơ đồ Use-case tổng thể (Mã PlantUML).<br>  * 2.4.3 Sơ đồ Lớp & Thực thể ERD (Mã PlantUML).<br>  * 2.4.4 Biểu đồ tuần tự: Giữ kho Saga, Work Thread Chat, Dự báo ML (Mã PlantUML).<br>  * 2.4.5 Thiết kế màn hình giao diện người dùng Vue 3. |
| 📄 [03_cac_thanh_phan_khac.md](./bao_cao/03_cac_thanh_phan_khac.md) | **Chương 3: Các thành phần khác** | - **3.1 Kế hoạch dự án**: Tiến độ 12 tuần kèm Biểu đồ Gantt Chart (Mã PlantUML).<br>- **3.2 Làm việc nhóm**: Quy trình Agile/Scrum, Git Flow, Code Review bắt buộc.<br>- **3.3 Đạo đức & Làm việc chuyên nghiệp**: Bảo mật dữ liệu cá nhân (NĐ 13/2023), trung thực tài chính.<br>- **3.4 Tác động xã hội**: Chuyển đổi số SMEs, giảm thiểu lãng phí chuỗi cung ứng, văn phòng không giấy tờ.<br>- **3.5 Chiến lược học tập**: Nghiên cứu .NET 10, Microservices Saga, Scikit-learn, Vue 3. |
| 📄 [04_ket_luan_va_tai_lieu_tham_khao.md](./bao_cao/04_ket_luan_va_tai_lieu_tham_khao.md) | **Chương 4 & 5: Kết luận & Tài liệu tham khảo** | - **4.1 Đánh giá kết quả**: Hoàn thành 10 microservices, 21/21 Unit test Chat passed, BI Dashboard.<br>- **4.2 Hạn chế & 4.3 Hướng phát triển**: Kubernetes, Conversational AI Agent, Mobile App.<br>- **Chương 5**: 10 tài liệu tham khảo học thuật chuẩn IEEE. |

---

## HƯỚNG DẪN XEM VÀ CHỈNH SỬA BIỂU ĐỒ PLANTUML

Mọi biểu đồ trong báo cáo (Use-case, Class Diagram, Sequence Diagram, Gantt Chart) đều được viết bằng mã nguồn **PlantUML** thuần túy trong các khối code block. Bạn có thể dễ dàng:
1. Copy đoạn mã giữa `@startuml ... @enduml` (hoặc `@startgantt ... @endgantt`).
2. Dán vào trang [PlantText UML Editor](https://www.planttext.com/) hoặc [PlantUML Online Server](https://www.plantuml.com/plantuml/uml/) để xuất ra file ảnh PNG / SVG hoặc chỉnh sửa trực tiếp.
3. Cài extension **PlantUML** trên VS Code / Antigravity IDE để xem trước biểu đồ trực tiếp bằng tổ hợp phím `Alt + D`.
