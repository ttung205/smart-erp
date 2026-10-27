# CHƯƠNG 4: KẾT LUẬN

---

## 4.1. ĐÁNH GIÁ KẾT QUẢ ĐẠT ĐƯỢC CỦA DỰ ÁN

Sau 12 tuần nỗ lực nghiên cứu và triển khai thực tế, nhóm đã hoàn thành toàn diện các mục tiêu đề ra cho đồ án liên ngành **Smart Distributed ERP**:

### 1. Về mặt Kiến trúc & Công nghệ (Architecture & Engineering)
* **Kiến trúc Microservices chuẩn mực**: Xây dựng thành công hệ sinh thái gồm 10 microservices độc lập tuân thủ nghiêm ngặt nguyên tắc **Database-per-Service** (kết hợp hài hòa giữa MySQL 8 và MongoDB 7) và **Clean Architecture 4 tầng**.
* **Đảm bảo tính toàn vẹn chuỗi cung ứng (SCM Invariant)**: Triển khai động cơ quản trị kho lõi bằng C# (.NET 10 Web API) với cơ chế Atomic Concurrency Lock (`SELECT ... FOR UPDATE`) và Đặt chỗ tồn kho nguyên tử theo chứng từ (Atomic Document Reservation), giải quyết dứt điểm bài toán tranh chấp giữ chỗ hàng, đảm bảo tính bất biến $\text{Available} = (\text{QuantityOnHand} - \text{QuantityReserved}) \ge 0$ trong mọi kịch bản đồng thời.
* **Hệ thống Giao tiếp Thời gian thực đột phá (`chat-service`)**: Xây dựng microservice chat thời gian thực độc lập trên nền tảng **ASP.NET Core SignalR** kết hợp cơ sở dữ liệu **MongoDB** (`erp_chat_db`). Triển khai thành công tính năng **Work Thread** gắn trực tiếp vào chứng từ ERP (Bán hàng, Báo giá, Mua hàng), tích hợp cơ chế phục hồi kết nối tự động (Catch-up Sync qua `after_id`), vượt qua 100% các bài kiểm thử tự động xUnit (21/21 tests Passed).
* **Phân hệ Văn phòng số, Chấm công & Bảng lương hoàn chỉnh (`eoffice-service`)**: Xây dựng đầy đủ chu trình nhân sự từ thu nhận máy quét chấm công (`PunchLog`), so khớp ca làm việc (`Shift`), tính toán ngày công tự động (`AttendanceDayCalculator`), phê duyệt giải trình điều chỉnh công, tính toán bảng lương động theo công thức và tự động hạch toán bút toán Sổ cái sang `finance-service`.

### 2. Về mặt Phân tích Dữ liệu & Học máy (Data Analytics & Machine Learning)
* **Kho dữ liệu phân tích quy mô lớn trên MySQL (`erp_analytics_db`)**: Thiết kế và nạp thành công bộ dữ liệu kinh doanh 2 năm (765.4 tỷ VNĐ doanh thu, 61.982 giao dịch, 916 sản phẩm, 2.591 khách hàng trên 34 tỉnh thành) vào kho dữ liệu Star Schema.
* **Ứng dụng hiệu quả Machine Learning vào nghiệp vụ**:
  * Mô hình **K-Means Clustering** phân khúc khách hàng theo chỉ số **RFM** ($k=4$) giúp bộ phận kinh doanh nhận diện tức thì các đại lý thuộc nhóm *"Nguy cơ rời bỏ"* để kịp thời đưa ra chính sách cứu vãn doanh số.
  * Mô hình **Holt-Winters Exponential Smoothing kết hợp Regression** dự báo chính xác chuỗi doanh thu 30 ngày tiếp theo kèm dải tin cậy 95% có tính đến biến ngoại sinh thời tiết, hỗ trợ ban giám đốc chủ động kế hoạch nhập hàng đón đầu mùa vụ.
  * Mô hình chấm điểm rủi ro công nợ (Credit Risk Scoring) phân loại chính xác các khoản nợ quá hạn cần ưu tiên thu hồi.
  * Tích hợp Trợ lý AI hỏi đáp dữ liệu bằng ngôn ngữ tự nhiên thông qua Groq LLM API.

### 3. Về mặt Giao diện Người dùng (Frontend UI/UX)
* Xây dựng giao diện Single Page Application (SPA) chuyên nghiệp bằng **Vue 3, Pinia và PrimeVue**, tuân thủ triệt để tiêu chuẩn **Enterprise Clean Flat White Canvas**.
* Tích hợp thành công **Dashboard Quản trị BI** trực quan tương tác cao (`/analytics`), các trang Chấm công (`/eoffice/attendance`), Bảng lương (`/eoffice/payroll`), Phiếu lương (`/eoffice/payslip`), và component dùng chung `WorkThreadDrawer.vue` nhúng mượt mà vào toàn bộ các màn hình chứng từ.
* Giao diện đạt độ tương thích cao, tốc độ tải nhanh, hoàn tất biên dịch sản phẩm (Production Build) đạt 0 lỗi.

---

## 4.2. NHỮNG HẠN CHẾ CÒN TỒN TẠI

Mặc dù đạt được nhiều kết quả ấn tượng, đồ án vẫn còn một số điểm có thể cải thiện do giới hạn thời gian nghiên cứu:
1. **Khả năng phân cụm SignalR**: Hiện tại `chat-service` đang chạy trên một container duy nhất. Khi triển khai trên cụm máy chủ nhiều Pod (Swarm/K8s), cần kết nối thêm **Redis Backplane** để truyền tải tin nhắn xuyên suốt giữa các máy chủ.
2. **Dữ liệu huấn luyện mô hình ML**: Mặc dù đã mở rộng dữ liệu lên 2 năm (24 tháng), nếu doanh nghiệp tích lũy được dữ liệu chuỗi thời gian từ 3 - 5 năm, các mô hình học máy sẽ nắm bắt tốt hơn chu kỳ kinh tế vĩ mô và các biến động bất thường của thị trường.
3. **Chưa có phiên bản ứng dụng di động**: Hệ thống mới hỗ trợ Responsive Web trên máy tính và máy tính bảng; chưa có ứng dụng di động chuyên biệt (Mobile Native App) dành cho nhân viên giao vận (Shipper) quét mã vạch nhận hàng tại kho.

---

## 4.3. HƯỚNG PHÁT TRIỂN TRONG TƯƠNG LAI

Từ nền tảng kiến trúc vững chắc đã xây dựng, nhóm định hướng các giai đoạn phát triển tiếp theo:
1. **Triển khai Đám mây trên Kubernetes (K8s) & CI/CD**: Đóng gói toàn bộ hệ thống dưới dạng Helm Chart, cấu hình Auto-scaling tự động co giãn tài nguyên theo tải thực tế và thiết lập quy trình triển khai tự động qua GitHub Actions.
2. **Nâng cấp Trí tuệ Nhân tạo thành Agentic Conversational AI**: Tích hợp mô hình ngôn ngữ lớn (LLM / RAG) kết hợp công nghệ Function Calling, cho phép nhân viên có thể "nói chuyện trực tiếp với số liệu" (*Conversational BI*), ra lệnh lập báo giá hoặc hỏi đáp tồn kho bằng giọng nói tiếng Việt tự nhiên.
3. **Phát triển ứng dụng Mobile App (Flutter / React Native)**: Tích hợp thông báo đẩy (Push Notification qua Firebase FCM), quét mã QR/Barcode trên camera điện thoại để hỗ trợ nhân viên kho kiểm kê và nhân viên kinh doanh đi thị trường.

---

<br>

# CHƯƠNG 5: TÀI LIỆU THAM KHẢO

[1] Robert C. Martin, *Clean Architecture: A Craftsman's Guide to Software Structure and Design*, Prentice Hall, 2017.

[2] Chris Richardson, *Microservices Patterns: With examples in Java*, Manning Publications, 2018.

[3] Martin Fowler, *Patterns of Enterprise Application Architecture*, Addison-Wesley Professional, 2002.

[4] Eric Evans, *Domain-Driven Design: Tackling Complexity in the Heart of Software*, Addison-Wesley, 2003.

[5] Microsoft Corporation, *"ASP.NET Core SignalR Overview and Real-time Web Applications"*, Microsoft Learn Documentation, 2026. [Online]. Available: https://learn.microsoft.com/aspnet/core/signalr/

[6] J. T. F. Hughes, *"Customer Segmentation Using RFM Analysis and K-Means Clustering for B2B Retail"*, Journal of Marketing Analytics, vol. 9, no. 3, pp. 215–228, 2021.

[7] Rob J. Hyndman and George Athanasopoulos, *Forecasting: Principles and Practice*, 3rd ed., OTexts: Melbourne, Australia, 2021. [Online]. Available: https://otexts.com/fpp3/

[8] Vue.js Core Team, *"Vue 3 Guide: Composition API and State Management with Pinia"*, 2026. [Online]. Available: https://vuejs.org/

[9] Cloud Native Computing Foundation (CNCF), *"CloudEvents Specification v1.0.2 - An open standard for describing event data in common formats"*, 2021. [Online]. Available: https://cloudevents.io/

[10] Quốc hội nước Cộng hòa Xã hội Chủ nghĩa Việt Nam, *Nghị định số 13/2023/NĐ-CP về Bảo vệ Dữ liệu Cá nhân*, ban hành ngày 17/04/2023.
