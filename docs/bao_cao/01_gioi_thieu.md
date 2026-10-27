# CHƯƠNG 1: GIỚI THIỆU

---

## 1.1. ĐẶT VẤN ĐỀ

### 1.1.1. Bối cảnh thực tiễn ngành phân phối thiết bị gia dụng B2B tại Việt Nam

Trong chuỗi giá trị cung ứng, doanh nghiệp phân phối (Distributor) đóng vai trò trung gian chiến lược kết nối các nhà sản xuất thiết bị gốc (OEMs như Samsung, LG, Daikin, Panasonic, Sunhouse...) với mạng lưới hàng ngàn đại lý cấp 2, cấp 3, nhà thầu dự án và khách hàng bán lẻ trên cả nước.

Đặc thù vận hành của một nhà phân phối điện máy gia dụng điển hình tại Việt Nam bao gồm:
* **Quy mô chu chuyển dòng tiền lớn**: Doanh thu hàng trăm tỷ đồng mỗi năm với hàng chục ngàn giao dịch mua bán, xuất - nhập kho vật lý liên tục.
* **Danh mục sản phẩm (SKU) phức tạp**: Quản lý gần 1.000 mã hàng thuộc hàng chục danh mục (điều hòa, tủ lạnh, máy giặt, tivi, đồ gia dụng...), có giá trị đơn chiếc cao, thông số kỹ thuật đa dạng và chính sách chiết khấu biến động liên tục từ hãng.
* **Bán hàng theo hạn mức công nợ (Credit Term)**: Đại lý phần lớn mua hàng theo kỳ hạn gối đầu (15 – 60 ngày) dựa trên hạn mức tín dụng được cấp.
* **Tính biến động mùa vụ (Seasonality) rõ rệt**: Nhu cầu thiết bị làm mát (điều hòa, quạt cây) tăng vọt trong quý 2 (tháng 4 – 8); trong khi các thiết bị tân trang nhà cửa, tivi, đồ bếp bùng nổ nhu cầu vào mùa mua sắm cận Tết (tháng 11 – 1 năm sau).

---

### 1.1.2. Bốn bài toán cốt lõi và điểm nghẽn vận hành

Từ bối cảnh thực tế đó, các doanh nghiệp phân phối đang đối mặt với 4 điểm nghẽn nghiêm trọng:

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        4 ĐIỂM NGHẼN CỐT LÕI CỦA DOANH NGHIỆP PHÂN PHỐI                 │
├────────────────────────────────────────────────────────────────────────────────────────┤
│ 1. Tranh chấp tồn kho & Bán khống    ──> Tranh chấp giữ chỗ hàng (Concurrency Lock)    │
│ 2. Rủi ro công nợ & Dòng tiền âm     ──> Nợ xấu quá hạn, mất kiểm soát hạn mức tín dụng│
│ 3. Giao tiếp phân mảnh, mất vết      ──> Dùng Zalo/Skype rời rạc, phá vỡ vết kiểm toán │
│ 4. Dữ liệu tĩnh, thiếu năng lực dự báo──> Đứt hàng mùa nóng, ứ đọng vốn mùa thấp điểm  │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

#### 1. Tranh chấp giữ chỗ tồn kho và bài toán bán khống (Stock Over-selling)
Trong các đợt cao điểm hoặc khi xuất hiện mặt hàng "hot", nhiều nhân viên kinh doanh cùng tư vấn và chốt đơn cho nhiều đại lý khác nhau tại cùng một thời điểm. Nếu hệ thống thiếu cơ chế khóa phân tán (Distributed Lock) và kiểm soát tồn kho đồng thời:
* Hiện tượng tranh chấp dữ liệu (Race Condition) xảy ra, dẫn đến **Bán khống (Over-selling)**: Số lượng hàng xác nhận trên đơn vượt quá tồn kho thực tế khả dụng.
* Doanh nghiệp rơi vào tình trạng đứt gãy cam kết giao hàng, phát sinh chi phí bồi thường và tổn hại uy tín thương hiệu.
* Để giải quyết triệt để, hệ thống bắt buộc phải kiểm soát ba trạng thái tồn kho và tuân thủ **Quy tắc bất biến chuỗi cung ứng (SCM Invariant)**:
$$\text{Available} = \text{OnHand} - \text{Reserved} \ge 0$$
*(Trong đó: `OnHand` là tồn thực tế, `Reserved` là tồn đã giữ chỗ cho đơn đã duyệt, `Available` là tồn thực sự khả dụng để tiếp tục bán).*

#### 2. Rủi ro công nợ quá hạn và suy kiệt dòng tiền lưu động
Bán buôn B2B vận hành dựa trên công nợ gối đầu. Khi theo dõi thủ công qua sổ sách hoặc bảng tính Excel rời rạc:
* Nhân viên kinh doanh vì áp lực doanh số vẫn tiếp tục tạo đơn cho các đại lý đã quá hạn thanh toán 30 – 60 ngày mà không có rào cản kiểm soát tự động.
* Ban giám đốc thiếu công cụ cảnh báo trực quan tuổi nợ (Aging Debts) theo thời gian thực.
* Các khoản nợ xấu tích tụ dẫn đến suy kiệt dòng tiền lưu động, doanh nghiệp không còn nguồn vốn nhập hàng đón đầu mùa vụ.

#### 3. Giao tiếp nội bộ phân mảnh và mất dấu vết kiểm toán (Lost Audit Trail)
Các quy trình nghiệp vụ như: xin phê duyệt chiết khấu đặc biệt, gia hạn nợ, giục kho xuất hàng hay xử lý hàng lỗi đa phần diễn ra qua các ứng dụng nhắn tin cá nhân (Zalo, Viber, Skype):
* **Mất ngữ cảnh (Context Loss)**: Nội dung trao đổi bị trôi, không gắn liền với mã chứng từ đơn hàng (`SO`), báo giá (`Quotation`) hay phiếu nhập (`PO`).
* **Mất dấu vết kiểm toán (Audit Trail)**: Khi xảy ra tranh chấp hoặc thất thoát, không thể truy vết ai đã phê duyệt điều khoản gì, vào thời điểm nào và lý do tại sao.
* **Rò rỉ dữ liệu**: Khi nhân viên nghỉ việc, lịch sử thương lượng và dữ liệu khách hàng lưu trên tài khoản chat cá nhân bị thất thoát khỏi doanh nghiệp.

#### 4. Dữ liệu kinh doanh nằm "chết", thiếu khả năng phân tích dự báo
Doanh nghiệp tích lũy hàng chục ngàn dòng giao dịch nhưng chỉ sử dụng cho các báo cáo thống kê tĩnh quá khứ (Descriptive Analytics):
* Thiếu công cụ **Phân tích dự báo (Predictive Analytics)** để ước tính nhu cầu thị trường trong 30 – 60 ngày tiếp theo (trong khi thời gian đặt hàng Lead Time từ nhà sản xuất kéo dài từ 15 – 45 ngày).
* Hậu quả: Lúc cao điểm thì đứt hàng (Stock-out), lúc thấp điểm thì ứ đọng vốn lưu kho.
* Không nhận diện được sớm các đại lý lớn đang giảm sút tần suất mua hàng và có nguy cơ rời bỏ (Churn Risk) sang đối thủ cạnh tranh.

---

## 1.2. CÁC GIẢI PHÁP ĐÃ CÓ VÀ HẠN CHẾ

Hiện nay, các doanh nghiệp thường tiếp cận một trong các nhóm giải pháp sau:

| Nhóm giải pháp | Đại diện tiêu biểu | Ưu điểm | Hạn chế cốt lõi |
| :--- | :--- | :--- | :--- |
| **ERP Quốc tế cao cấp** | SAP S/4HANA, Oracle NetSuite, MS Dynamics 365 | - Chuẩn hóa toàn cầu.<br>- Nghiệp vụ sâu và bảo mật cao. | - Chi phí bản quyền và tư vấn cực lớn (từ hàng trăm ngàn đến hàng triệu USD).<br>- Thời gian triển khai kéo dài (1 – 2 năm), quá nặng nề và phức tạp cho SMEs.<br>- Cứng nhắc, khó tùy biến nhanh theo đặc thù phân phối Việt Nam. |
| **ERP Mã nguồn mở** | Odoo ERP, ERPNext | - Chi phí linh hoạt.<br>- Hệ sinh thái module đa dạng. | - Kiến trúc nguyên khối (Monolith) dùng chung 1 database, dễ quá tải I/O khi dữ liệu và truy cập đồng thời tăng cao.<br>- Module thảo luận chỉ là comment tường tĩnh, thiếu động cơ WebSocket thời gian thực tốc độ cao.<br>- Thiếu các mô hình Machine Learning dự báo chuyên ngành. |
| **Phần mềm Kế toán & Bán lẻ nội địa** | MISA, FAST, Bravo, KiotViet, Sapo | - Đúng chuẩn mực kế toán Việt Nam (VAS/Thông tư 200).<br>- Thân thuộc với kế toán viên. | - Tập trung chủ yếu vào hạch toán thuế hoặc bán lẻ POS, yếu về xử lý chuỗi cung ứng B2B phân tán.<br>- Hệ thống đóng kín, khó tích hợp API mở rộng.<br>- Hoàn toàn thiếu vắng năng lực phân tích dữ liệu nâng cao (ML/BI). |
| **Ứng dụng Chat độc lập** | Zalo, Slack, Microsoft Teams | - Giao tiếp thời gian thực mượt mà.<br>- Phổ biến, dễ sử dụng. | - Tách rời hoàn toàn khỏi chứng từ ERP, gây phân mảnh ngữ cảnh.<br>- Không lưu lại được vết kiểm toán tập trung trên hệ thống quản trị. |

### Bảng ma trận đối chuẩn đa tiêu chí:

| Tiêu chí | ERP Quốc tế | ERP Mã nguồn mở | Phần mềm Nội địa | Chat độc lập | **SMART DISTRIBUTED ERP** |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Kiến trúc phần mềm** | Nặng nề, lai ghép | Monolith | Monolith / C-S | Cloud độc lập | **Event-Driven Microservices** |
| **Cơ chế lưu trữ dữ liệu** | DB khổng lồ tập trung | Single Database | Single Database | DB Chat riêng | **Database-per-Service** |
| **Giải quyết tranh chấp kho** | Khóa cấp CSDL | Khóa bảng thông thường | Dễ bán khống | Không áp dụng | **Atomic Concurrency Lock & Document-level Reservation** |
| **Đảm bảo bất biến SCM** | Có cấu hình | Dễ âm kho khi tải cao | Thường âm kho | Không có | **Bắt buộc: $\text{Available} \ge 0$** |
| **Giao tiếp gắn chứng từ** | Tích hợp Teams rời | Comment tĩnh | Ghi chú văn bản | Tách rời ERP | **Work Thread gắn trực tiếp chứng từ (SignalR + MongoDB)** |
| **Phân tích dữ liệu & BI** | Rất đắt (mua thêm BW) | Pivot tĩnh | Báo cáo kế toán | Không có | **Kho Data Warehouse Star Schema (MySQL) & Dashboard BI** |
| **Machine Learning tích hợp** | Bán rời đắt đỏ | Không có | Không có | Không có | **Tích hợp K-Means RFM, Holt-Winters** |
| **Phân tích dự báo (Analytics)** | Báo cáo tĩnh / đắt | Báo cáo cơ bản | Không có | Không có | **Dự báo chuỗi thời gian, Phân cụm RFM, Chấm điểm nợ** |
| **Triển khai & Chi phí (TCO)** | Rất cao | Trung bình | Cố định / Rẻ | Thuê bao | **Tối ưu, Docker Compose triển khai 1 lệnh** |

---

## 1.3. GIẢI PHÁP ĐỀ XUẤT: HỆ THỐNG SMART DISTRIBUTED ERP

Nhóm nghiên cứu đề xuất xây dựng **Smart Distributed ERP** — Hệ thống quản trị nguồn lực doanh nghiệp phân tán thế hệ mới kết hợp giữa **Kiến trúc Microservices**, **Giao tiếp thời gian thực gắn chứng từ (Work Thread)** và **Trí tuệ nhân tạo / Phân tích dữ liệu thông minh (Machine Learning & BI)**.

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                              HỆ THỐNG SMART DISTRIBUTED ERP                            │
├────────────────────────────┬─────────────────────────────┬─────────────────────────────┤
│   TRỤ CỘT 1: KIẾN TRÚC     │     TRỤ CỘT 2: GIAO TIẾP    │   TRỤ CỘT 3: KHOA HỌC       │
│    MICROSERVICES PHÂN TÁN  │     THỜI GIAN THỰC NHÚNG    │    DỮ LIỆU & MACHINE        │
│   (EVENT-DRIVEN BACKEND)   │     (SIGNALR WORK THREAD)   │     LEARNING ANALYTICS      │
├────────────────────────────┼─────────────────────────────┼─────────────────────────────┤
│ • 10 Microservices phân lập│ • Microservice độc lập      │ • Data Warehouse chuẩn hóa  │
│   theo Bounded Contexts    │   C# (.NET 10) + SignalR    │   (Kaggle Enriched Dataset  │
│ • C# (.NET 10) siêu tốc cho│ • Lưu trữ tin nhắn trên     │   24 tháng, 765 tỷ VNĐ)     │
│   Inventory & Purchase     │   MongoDB phân tán          │ • Lưu trữ kho phân tích trên│
│ • PHP Laravel 13+ cho      │ • Mô hình **Work Thread**:  │   MySQL (erp_analytics_db)  │
│   Identity, Catalog, Sales,│   Hội thoại nhúng trực tiếp │ • Phân cụm khách hàng RFM   │
│   Finance, E-Office        │   vào chứng từ ERP          │   bằng K-Means Clustering   │
│ • E-Office tích hợp        │ • Tự động nhận diện thành   │ • Dự báo chuỗi thời gian    │
│   Chấm công & Bảng lương   │   viên liên quan chứng từ   │   Holt-Winters + Regression │
│ • Database-per-Service     │ • Phục hồi kết nối tự động  │ • Chấm điểm nợ Credit Risk  │
│ • Giữ kho nguyên tử theo   │   (Catch-up Sync qua cursor)│ • Dashboard BI trên Vue 3   │
│   chứng từ (Atomic Reserve)│ • Lưu vết kiểm toán minh bạch│ • REST API Analytics hiệu   │
│ • Ràng buộc bất biến tuyệt │   chống rò rỉ dữ liệu       │   năng cao (Caching < 50ms) │
│   đối Available >= 0       │                             │ • Trợ lý AI hội thoại (LLM) │
└────────────────────────────┴─────────────────────────────┴─────────────────────────────┘
```

### 1.3.1. Ba trụ cột công nghệ cốt lõi

#### Trụ cột 1: Kiến trúc Microservices phân tán hướng sự kiện (Event-Driven)
* **Phân chia 10 Microservices độc lập** theo Bounded Contexts và nguyên tắc **Database-per-Service**:
  * `identity-service` (PHP Laravel 13): Xác thực tập trung JWT, phân quyền động 11 Roles (Dynamic RBAC) bao gồm quyền Chấm công và Bảng lương.
  * `catalog-service` (PHP Laravel 13): Quản lý Master Data (916 sản phẩm gia dụng, khách hàng, nhà cung cấp).
  * `sales-service` (PHP Laravel 13): Xử lý báo giá, đơn bán hàng, snapshot tham chiếu dữ liệu danh mục, hóa đơn xuất kho.
  * `inventory-service` (C# .NET 10): Động cơ kiểm soát tồn kho hiệu năng cao, Atomic Concurrency Lock (`SELECT ... FOR UPDATE`), bảo đảm tuyệt đối tính bất biến $\text{Available} \ge 0$.
  * `purchase-service` (C# .NET 10): Quản lý yêu cầu mua sắm, đơn mua hàng PO, đối soát 3 bên và phiếu nhập kho GRN.
  * `finance-service` (PHP Laravel 13): Quản lý hóa đơn VAT, phiếu thu/chi, công nợ, hạn mức tín dụng và hạch toán sổ cái chi phí lương nhân sự.
  * `eoffice-service` (PHP Laravel 13): Văn phòng số, quy trình ký duyệt, quản lý ca làm việc, máy quét chấm công, tính ngày công và lập bảng lương.
  * `notification-service` (Node.js 22 Express): Động cơ thông báo đa kênh (Socket.IO, Email Queue), lưu trữ trên MongoDB.
  * `chat-service` (C# .NET 10): Giao tiếp thời gian thực SignalR, lưu trữ trên MongoDB.
  * `ai-service` (Python FastAPI): Trợ lý AI hội thoại kết hợp phân tích dự báo Machine Learning và kho phân tích MySQL.
* **Cơ chế giữ chỗ tồn kho nguyên tử theo chứng từ (Atomic Document Reservation)**: Đơn hàng/hóa đơn xuất kho gọi giữ chỗ tồn kho phân tán trực tiếp sang `inventory-service` (`POST /stocks/reserve/batch`) đảm bảo tính nguyên tử, có phản hồi xác thực tức thời (Fail-fast), và tự động hoàn trả giữ chỗ khi hủy chứng từ (`POST /stocks/release/batch`), loại bỏ hoàn toàn hiện tượng bán khống.
* **Mẫu thiết kế Transactional Outbox Pattern với Confirmed Publisher & Idempotent Consumer**: Đảm bảo tin cậy truyền nhận thông điệp qua RabbitMQ với cơ chế Publisher Confirms và Dead-Letter Exchange (`erp.dlx`).

#### Trụ cột 2: Hệ thống giao tiếp thời gian thực chuyên biệt (`chat-service`)
* Xây dựng độc lập trên nền tảng **C# (.NET 10)** kết hợp **ASP.NET Core SignalR** và cơ sở dữ liệu **MongoDB** (`erp_chat_db`), đạt độ trễ truyền tin nội bộ $Latency < 50ms$.
* **Đột phá với mô hình Work Thread**: Luồng chat gắn trực tiếp vào chứng từ ERP (`Sale`, `Quotation`, `Purchase`, `Invoice`).
  * Tự động xác định thành viên tham gia dựa trên quyền hạn chứng từ (người tạo, người duyệt, thủ kho, kế toán liên quan).
  * Lưu lại toàn bộ thảo luận thành vết kiểm toán (Audit Trail) minh bạch ngay trên chứng từ.
* **Khả năng tự phục hồi (Resilience & Catch-up Sync)**: Tự động kết nối lại khi mạng gián đoạn và tải bù tin nhắn bị lỡ qua API cursor `after_id`.

#### Trụ cột 3: Kho dữ liệu làm giàu (Kaggle Dataset), Machine Learning & BI Dashboard
* **Bộ dữ liệu chuẩn hóa 2 năm (Kaggle Enriched Dataset)**: 24 tháng (10/2024 – 09/2026), 765.4 tỷ VNĐ doanh thu, 61.982 giao dịch, 2.591 khách hàng, tích hợp các biến ngoại sinh thời tiết (`avg_temperature_c`, `weather_condition`), chiết khấu và đánh giá khách hàng.
* **Kho phân tích dữ liệu trên MySQL (`erp_analytics_db`)**: Hỗ trợ đồng bộ dữ liệu tự động ETL từ các nghiệp vụ sang các bảng chiều (Dim) và bảng sự kiện (Fact).
* **Phân cụm khách hàng RFM (K-Means Clustering)**: Phân loại 2.591 khách hàng thành 4 cụm (*VIP, Trung thành, Tiềm năng, Nguy cơ rời bỏ*) bằng phương pháp Elbow và Silhouette Score.
* **Dự báo chuỗi thời gian (Time-series Forecasting)**: Mô hình Holt-Winters kết hợp Hồi quy Ridge/Polynomial dự báo doanh số 30 ngày tiếp theo kèm dải tin cậy 95% ($\text{MAPE} < 8\%$).
* **Chấm điểm rủi ro công nợ (Credit Risk Scoring)**: Phân loại mức độ rủi ro (Xanh - Vàng - Đỏ) dựa trên hạn mức tín dụng và số ngày quá hạn.
* **Dashboard BI Web (Vue 3 + Chart.js)**: Trực quan hóa toàn diện chỉ số KPI, biểu đồ đường thực tế & nét đứt dự báo, biểu đồ phân cụm tương tác; phản hồi < 50ms nhờ cơ chế In-memory Caching.
* **REST API Phân tích Dữ liệu (FastAPI & In-memory Caching)**: Cung cấp bộ API `/api/v1/ai/analytics/*` phục vụ trích xuất kết quả dự báo và phân cụm cho các phân hệ ERP và Dashboard điều hành với độ trễ thấp.

---

### 1.3.2. Mục tiêu nghiên cứu và phạm vi đề tài

#### 1. Mục tiêu nghiên cứu
* **Mục tiêu kỹ thuật**: Thiết kế kiến trúc và hiện thực hóa thành công 10 microservices chuẩn Clean Architecture, giải quyết triệt để bài toán giữ kho phân tán bằng cơ chế Atomic Document-level Reservation không bao giờ âm kho, và xây dựng hệ thống chat SignalR trên nền MongoDB đạt chuẩn tải bù tin nhắn.
* **Mục tiêu phân tích dữ liệu**: Xây dựng giải pháp khai phá dữ liệu toàn diện (End-to-End Analytics) từ tiền xử lý, huấn luyện mô hình ML (K-Means, Holt-Winters), xây dựng API phân tích hiệu năng cao và trực quan hóa Dashboard BI trên Web.

#### 2. Phạm vi đề tài
* **Phạm vi nghiệp vụ**: Chuỗi cung ứng phân phối thiết bị điện máy gia dụng B2B tại Việt Nam (Báo giá $\rightarrow$ Đơn bán hàng $\rightarrow$ Giữ kho $\rightarrow$ Xuất kho $\rightarrow$ Hóa đơn & Công nợ, Văn phòng số tích hợp Chấm công & Bảng lương).
* **Phạm vi công nghệ**: C# (.NET 10), PHP 8.4 (Laravel 13), Node.js 22, Python 3.12 (FastAPI), Vue 3, MySQL 8, MongoDB 7, RabbitMQ, Kong API Gateway, Docker Compose.

---

### 1.3.3. Đóng góp chính của đề tài

1. **Về mặt kỹ thuật phần mềm**: Cung cấp kiến trúc tham chiếu hoàn chỉnh về việc áp dụng Microservices, cơ chế giữ chỗ tồn kho nguyên tử theo chứng từ kết hợp Atomic Lock và Clean Architecture để giải quyết bài toán chống bán khống trong ERP.
2. **Về mặt giao tiếp cộng tác**: Đề xuất và triển khai thành công mô hình **Work Thread** gắn liền đối thoại với chứng từ nghiệp vụ, xóa bỏ hiện tượng phân mảnh thông tin của các ứng dụng chat bên ngoài.
3. **Về mặt khoa học dữ liệu**: Ứng dụng thành công các thuật toán học máy vào bài toán thực tiễn của doanh nghiệp phân phối (phân cụm RFM, dự báo nhu cầu có biến ngoại sinh thời tiết, chấm điểm rủi ro nợ).

---

### 1.3.4. Bố cục báo cáo đồ án

Báo cáo đồ án được cấu trúc thành 5 chương:
* **Chương 1: Giới thiệu**: Bối cảnh thực tiễn, các điểm nghẽn vận hành, khảo sát đối chuẩn các giải pháp hiện hữu, giải pháp đề xuất Smart Distributed ERP, mục tiêu, phạm vi và đóng góp của đề tài.
* **Chương 2: Thiết kế và triển khai**: Yêu cầu chức năng/phi chức năng, sơ đồ kiến trúc, kịch bản Use-case, biểu đồ lớp (Class Diagram), cơ sở dữ liệu (ERD), biểu đồ tuần tự (Sequence Diagram) cho Đặt chỗ kho nguyên tử theo chứng từ, Work Thread, Chấm công & Bảng lương, và Machine Learning; thiết kế giao diện Vue 3.
* **Chương 3: Các thành phần khác**: Kế hoạch thực hiện dự án (Gantt Chart), quy trình làm việc nhóm Agile/Scrum, đạo đức nghề nghiệp, bảo mật dữ liệu cá nhân, tác động kinh tế - xã hội và chiến lược tự học công nghệ mới.
* **Chương 4: Kết luận**: Đánh giá kết quả đạt được, chỉ ra các hạn chế và định hướng phát triển tương lai (Kubernetes, Mobile App).
* **Chương 5: Tài liệu tham khảo**: Danh mục các tài liệu khoa học, sách chuyên khảo và chuẩn mực kỹ thuật theo định dạng IEEE.
