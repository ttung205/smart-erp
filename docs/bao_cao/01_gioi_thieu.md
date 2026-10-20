# CHƯƠNG 1: GIỚI THIỆU

---

## 1.1. ĐẶT VẤN ĐỀ

Trong kỷ nguyên số và thương mại đa kênh (Omnichannel), ngành phân phối thiết bị gia dụng và điện máy tại Việt Nam đang có tốc độ phát triển mạnh mẽ. Doanh nghiệp phân phối đóng vai trò là "mắt xích chiến lược" kết nối giữa các tập đoàn sản xuất lớn (Samsung, LG, Panasonic, Daikin, Sunhouse...) với mạng lưới hàng ngàn đại lý bán buôn, cửa hàng bán lẻ và các dự án công trình trải dài trên khắp cả nước. 

Quy mô hoạt động của một nhà phân phối điện máy điển hình thường ghi nhận:
* Doanh thu hàng trăm tỷ đồng mỗi năm với hàng ngàn giao dịch mua bán, xuất nhập kho liên tục.
* Danh mục sản phẩm (SKU) đa dạng lên tới hàng ngàn mã hàng với thông số kỹ thuật phức tạp, giá trị đơn chiếc lớn và chịu sự biến động giá liên tục theo chính sách chiết khấu của hãng.
* Mạng lưới khách hàng đại lý mua hàng công nợ (Credit Term) với hạn mức tín dụng và thời hạn thanh toán gối đầu khác nhau.
* **Tính mùa vụ (Seasonality) cực kỳ khắt khe**: Nhu cầu các mặt hàng làm mát (điều hòa, quạt cây, máy phun sương) tăng vọt trong quý 2 (tháng 4 - tháng 6); trong khi các mặt hàng gia dụng, ti vi, tủ lạnh, bếp từ lại bùng nổ nhu cầu mua sắm cận Tết vào quý 4 (tháng 11 - tháng 1 năm sau).

Từ bối cảnh kinh doanh thực tế đó, các doanh nghiệp phân phối đang phải đối mặt với **bốn bài toán cốt lõi**:

1. **Nút thắt cổ chai trong quản lý tồn kho và giữ chỗ đơn hàng (Stock Over-selling & Concurrency)**:
   Nhiều nhân viên kinh doanh cùng bán một mặt hàng "hot" có số lượng giới hạn tại cùng một thời điểm. Nếu hệ thống không hỗ trợ cơ chế khóa phân tán (Distributed Lock) và phân tách rõ ràng giữa **Tồn thực tế trong kho (Quantity On-Hand)** và **Tồn đã cam kết giao (Quantity Reserved)**, doanh nghiệp sẽ rơi vào tình trạng bán khống (xuất đơn vượt quá tồn kho khả dụng), gây đứt gãy cam kết giao hàng và chịu phạt hợp đồng nặng nề.

2. **Rủi ro công nợ khó đòi và dòng tiền âm (Credit Risk & Overdue Debts)**:
   Việc bán hàng gối đầu cho hàng ngàn đại lý cấp 2, cấp 3 nếu chỉ theo dõi bằng sổ sách kế toán thủ công hoặc các bảng Excel rời rạc sẽ không thể phát hiện kịp thời các khoản nợ quá hạn 30 - 60 ngày. Doanh nghiệp đối mặt với nguy cơ mất vốn và thiếu hụt dòng tiền lưu động để nhập hàng mùa cao điểm.

3. **Giao tiếp nội bộ phân mảnh và mất dấu vết kiểm toán (Fragmented Collaboration & Audit Trail)**:
   Hiện nay, quá trình trao đổi phê duyệt chiết khấu đặc biệt cho khách hàng, giục kho xuất hàng gấp, hay xử lý hàng lỗi trả lại đa phần diễn ra qua các ứng dụng nhắn tin cá nhân như Zalo, Messenger, Skype hoặc email. Thông tin trao đổi bị phân tán, không gắn liền với chứng từ đơn hàng trong hệ thống quản trị, dẫn đến việc thiếu cơ sở đối soát, mất dấu vết trách nhiệm khi xảy ra sai sót và gây rò rỉ dữ liệu khách hàng nội bộ.

4. **Dữ liệu kinh doanh nằm "chết", thiếu khả năng dự báo nhu cầu (Lack of Predictive Analytics)**:
   Các báo cáo kinh doanh truyền thống chỉ mang tính chất thống kê tĩnh những gì đã xảy ra trong quá khứ (Historical Data). Nhà quản trị thiếu công cụ tự động hóa dự báo nhu cầu thị trường trong 30 ngày tiếp theo để chủ động đặt hàng từ nhà sản xuất (Lead Time đặt hàng thường từ 15 - 45 ngày), dẫn đến tình trạng: lúc cao điểm thì đứt hàng, lúc thấp điểm thì ứ đọng vốn tồn kho.

---

## 1.2. CÁC GIẢI PHÁP ĐÃ CÓ VÀ HẠN CHẾ

Để giải quyết các bài toán trên, các doanh nghiệp thường tiếp cận một số nhóm giải pháp sau:

| Nhóm giải pháp | Đại diện tiêu biểu | Ưu điểm | Hạn chế cốt lõi |
| :--- | :--- | :--- | :--- |
| **Hệ thống ERP Quốc tế cao cấp** | SAP S/4HANA, Oracle NetSuite, Microsoft Dynamics 365 | - Quy trình chuẩn hóa toàn cầu.<br>- Đầy đủ các phân hệ quản trị lớn. | - Chi phí bản quyền và tư vấn triển khai cực kỳ đắt đỏ (từ hàng trăm ngàn đến hàng triệu USD).<br>- Thời gian triển khai kéo dài (từ 1 - 2 năm), quá nặng nề và phức tạp đối với doanh nghiệp vừa và nhỏ.<br>- Khó tùy biến giao diện và quy trình linh hoạt theo đặc thù phân phối Việt Nam. |
| **ERP Mã nguồn mở** | Odoo ERP, ERPNext | - Chi phí bản quyền mềm dẻo.<br>- Cộng đồng lớn, kho module phong phú. | - Đa phần xây dựng trên kiến trúc nguyên khối (**Monolithic Architecture**), dễ gặp nút thắt cổ chai khi dữ liệu lớn và lượng truy cập đồng thời tăng cao.<br>- Module thảo luận nội bộ chỉ là dạng comment tĩnh đơn giản, chưa tối ưu cho trao đổi thời gian thực tốc độ cao.<br>- Thiếu các module Machine Learning chuyên biệt hóa cho ngành phân phối gia dụng. |
| **Phần mềm Quản lý Bán lẻ & Kế toán nội địa** | MISA, FAST, Bravo, KiotViet, Sapo | - Phù hợp chuẩn mực kế toán Việt Nam (VAS/Thông tư 200/133).<br>- Giao diện thân thuộc với kế toán viên. | - Chủ yếu tập trung vào hạch toán kế toán hoặc bán lẻ cửa hàng nhỏ lẻ (B2C), chưa tối ưu bài toán chuỗi cung ứng B2B phân phối lớn.<br>- Hệ thống đóng kín, khó tích hợp Microservices hiện đại.<br>- Hoàn toàn thiếu vắng năng lực phân tích dữ liệu nâng cao (Clustering, Demand Forecasting). |

---

## 1.3. GIẢI PHÁP ĐỀ XUẤT

Trước các hạn chế nêu trên, nhóm nghiên cứu đề xuất xây dựng hệ thống **Smart Distributed ERP** — Giải pháp quản trị nguồn lực doanh nghiệp thế hệ mới được thiết kế riêng cho chuỗi cung ứng phân phối thiết bị gia dụng B2B, tích hợp ba trụ cột công nghệ đột phá:

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                 HỆ THỐNG SMART DISTRIBUTED ERP                         │
├────────────────────────────┬─────────────────────────────┬─────────────────────────────┤
│   1. KIẾN TRÚC PHÂN TÁN    │    2. TRAO ĐỔI THỜI GIAN THỰC │  3. PHÂN TÍCH DỮ LIỆU & AI │
│     (MICROSERVICES)        │      (REALTIME CHAT)        │     (MACHINE LEARNING)      │
├────────────────────────────┼─────────────────────────────┼─────────────────────────────┤
│ - Tách biệt Bounded Context│ - Microservice độc lập      │ - Kho dữ liệu Star Schema   │
│ - C# .NET 10 cho Inventory │   C# .NET 10 + SignalR      │   (analytics.db 217 tỷ VNĐ) │
│   & Sales (Engine tốc độ)  │ - Chat 1-1, Nhóm phòng ban  │ - Phân cụm RFM (K-Means)    │
│ - PHP Laravel cho Identity,│ - **Work Thread**: Thảo luận│ - Dự báo chuỗi thời gian    │
│   Catalog, Finance         │   gắn trực tiếp chứng từ ERP│   30 ngày (Holt-Winters)    │
│ - Bất biến SCM Invariant:  │ - Lưu vết kiểm toán minh    │ - Chấm điểm nợ quá hạn      │
│   Available >= 0           │   bạch, thông báo đa kênh   │ - Dashboard BI Vue 3        │
└────────────────────────────┴─────────────────────────────┴─────────────────────────────┘
```

1. **Kiến trúc Microservices kết hợp Clean Architecture 4 tầng**:
   * Phân chia các phân hệ thành các dịch vụ độc lập theo nguyên tắc **Database-per-Service** và ranh giới nghiệp vụ (Bounded Contexts).
   * Phân hệ Lõi Kho (`inventory-service`) và Bán hàng (`sales-service`) được xây dựng trên nền tảng **C# .NET 10 Web API**, áp dụng cơ chế khóa phân tán và Saga Orchestration để giải quyết triệt để bài toán tranh chấp giữ chỗ hàng (Atomic Stock Reservation).
   * Phân hệ Quản trị Tổ chức, Phân quyền động RBAC (`identity-service`), Danh mục (`catalog-service`) và Tài chính (`finance-service`) được tối ưu hóa bằng **PHP 8.2+ (Laravel)**.
   * Giao tiếp phi đồng bộ đảm bảo tính nhất quán cuối cùng qua RabbitMQ CloudEvents Envelope.

2. **Hệ thống Giao tiếp Thời gian thực chuyên biệt (`chat-service`)**:
   * Xây dựng microservice chat thời gian thực độc lập bằng **C# (.NET 10)** với **ASP.NET Core SignalR**.
   * Đột phá với cơ chế **Work Thread (Hội thoại gắn chứng từ ERP)**: Mọi thảo luận, duyệt giá gấp, yêu cầu điều xe kho vận đều được nhúng trực tiếp vào Đơn bán hàng (`Sale`), Báo giá (`Quotation`), hay Phiếu nhập mua (`Purchase`).
   * Tự động nhận diện thành viên liên quan, hỗ trợ @mention, gửi file/ảnh an toàn và tự động phục hồi đồng bộ tin nhắn (Catch-up Sync) khi mạng gián đoạn.

3. **Phân hệ Phân tích Dữ liệu, Machine Learning & Business Intelligence (`ai-service`)**:
   * Dựa trên bộ dữ liệu kinh doanh thực tế mô phỏng 1 năm hoạt động (216.9 tỷ VNĐ doanh thu, 916 sản phẩm, 1.256 khách hàng trên 34 tỉnh thành), hệ thống chuẩn hóa vào Data Warehouse mô hình Star Schema.
   * Áp dụng thuật toán **K-Means Clustering** phân khúc khách hàng theo mô hình **RFM (Recency - Frequency - Monetary)** để tự động nhận diện và cảnh báo nhóm "Khách hàng nguy cơ rời bỏ" (Churn Risk).
   * Áp dụng mô hình chuỗi thời gian **Holt-Winters Exponential Smoothing kết hợp Regression** dự báo nhu cầu doanh số từng ngày cho 30 ngày tiếp theo kèm dải tin cậy 95%.
   * Trực quan hóa toàn bộ chỉ số lên **Dashboard Quản trị BI tương tác cao** trên Web (Vue 3, Pinia, PrimeVue 4).
