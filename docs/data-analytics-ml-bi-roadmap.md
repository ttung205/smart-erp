# LỘ TRÌNH ĐỒ ÁN: PHÂN TÍCH DỮ LIỆU, MACHINE LEARNING & BUSINESS INTELLIGENCE

> **Mục tiêu**: Xây dựng giải pháp phân tích dữ liệu toàn diện (**End-to-End Analytics Solution**) cho hệ thống Smart ERP phân phối thiết bị gia dụng: kết hợp giữa **Data Warehouse (Star Schema)**, các mô hình **Machine Learning (K-Means & Time Series Forecasting)**, **Dashboard Quản trị BI** trực quan trên Web và **Trợ lý AI Đàm thoại (Agentic Conversational AI)**.

---

## 1. TỔNG QUAN HỆ THỐNG & NGUỒN DỮ LIỆU

### 1.1. Bối cảnh Dữ liệu Nghiệp vụ (Enterprise Dataset)
Dữ liệu được mô phỏng chuẩn xác 1 năm hoạt động kinh doanh (**01/10/2025 → 30/09/2026**), đã được chuẩn hóa vào kho phân tích [services/ai-service/analytics.db](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/analytics.db):

* **Quy mô Doanh thu**: ~216.9 tỷ VNĐ trước VAT (**8.307** hóa đơn VAT, **6.417** đơn bán hàng).
* **Danh mục Sản phẩm**: **916** sản phẩm thật cào từ Điện Máy Xanh (thuộc **29** danh mục, **78** thương hiệu, có đầy đủ giá niêm yết, giá vốn, thông số kỹ thuật).
* **Khách hàng**: **1.256** khách hàng doanh nghiệp & bán lẻ trên **34** tỉnh thành (Dealer, Distributor, Project, Retail).
* **Chuỗi cung ứng & Mua hàng**: **32** nhà cung cấp, **839** đề nghị mua sắm PR, **839** đơn mua hàng PO, **873** phiếu nhập kho GRN.
* **Tồn kho & Kế toán**: **22.214** giao dịch thẻ kho, **22.466** bút toán sổ cái, **8.307** khoản công nợ phải thu (**20.6** tỷ công nợ, **3.6** tỷ quá hạn).

---

### 1.2. Sơ đồ Kiến trúc Kết hợp (2 trong 1)

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                 KHO DỮ LIỆU (DATA WAREHOUSE)                           │
│              analytics.db (Star Schema: fact_sales, dim_customer, dim_product,...)     │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        MODULE MACHINE LEARNING (Python - ai-service)                  │
│                                                                                        │
│   [1. K-Means Clustering]       [2. Time Series Forecasting]     [3. Credit Risk]     │
│   Phân cụm 1.256 KH theo RFM    Dự báo 30 ngày doanh số tới     Chấm điểm nợ quá hạn  │
└─────────────────────┬────────────────────────────────────────────────────┬─────────────┘
                      │                                                    │
        REST API JSON │                                      Tools/Function│Calling
                      ▼                                                    ▼
┌──────────────────────────────────────────────┐  ┌──────────────────────────────────────┐
│       📊 DASHBOARD QUẢN TRỊ (BI WEB)         │  │       🤖 TRỢ LÝ AI ĐÀM THOẠI        │
│                                              │  │                                      │
│ - Thẻ KPI Doanh thu, Tăng trưởng, Nợ xấu     │  │ - Hỏi đáp tự nhiên bằng tiếng Việt   │
│ - Biểu đồ đường dự báo tương lai (ML)        │  │ - Tự kích hoạt mô hình ML để trả lời │
│ - Biểu đồ phân bổ cụm khách hàng RFM         │  │ - Diễn giải nguyên nhân & khuyến nghị│
│ - Bảng lọc khách hàng "Nguy cơ rời bỏ"       │  │ - "Trò chuyện trực tiếp với dữ liệu" │
└──────────────────────────────────────────────┘  └──────────────────────────────────────┘
```

---

## 2. LỘ TRÌNH 5 GIAI ĐOẠN CHI TIẾT

```text
Giai đoạn 1 ──> Giai đoạn 2 ──> Giai đoạn 3 ──> Giai đoạn 4 ──> Giai đoạn 5
(Machine        (Xây dựng       (Dựng Dashboard (Tích hợp AI    (Báo cáo &
 Learning)       FastAPI)        Vue 3 Web)      Agent Chatbot)  Thuyết trình)
```

---

### GIAI ĐOẠN 1: XÂY DỰNG MODULE MACHINE LEARNING (PYTHON CORE)

**Mục tiêu**: Xây dựng module độc lập [services/ai-service/app/ml.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/ml.py) chứa các thuật toán học máy xử lý dữ liệu từ `analytics.db`.

#### 1.1. Bài toán 1: Phân cụm Khách hàng theo mô hình RFM (K-Means Clustering)
* **Trích xuất đặc trưng RFM** từ bảng `fact_sales` và `dim_customer`:
  * **Recency ($R$)**: Số ngày tính từ ngày mua hàng cuối cùng của khách đến ngày chốt sổ (`2026-09-30`).
  * **Frequency ($F$)**: Tổng số đơn hàng thành công của khách trong năm.
  * **Monetary ($M$)**: Tổng doanh thu (VNĐ) mà khách hàng đã mang lại.
* **Tiền xử lý dữ liệu**:
  * Log-transform hoặc RobustScaler để giảm ảnh hưởng của khách hàng ngoại lai (outliers doanh thu lớn).
  * Chuẩn hóa về cùng thang đo bằng `StandardScaler`.
* **Huấn luyện mô hình K-Means**:
  * Dùng phương pháp **Elbow Method** và **Silhouette Score** để xác định số cụm tối ưu ($k=4$).
  * Đặt tên nhãn nghiệp vụ cho từng cụm:
    1. **VIP / Champions**: $R$ cực thấp (vừa mới mua), $F$ rất cao, $M$ lớn nhất ➔ Cần chính sách chăm sóc đặc biệt, quà tặng tri ân.
    2. **Khách hàng trung thành (Loyal)**: Mua hàng đều đặn, giá trị khá ➔ Tiếp tục duy trì chiết khấu.
    3. **Khách hàng tiềm năng (Potential)**: Mới mua gần đây, giá trị trung bình ➔ Up-sell / Cross-sell.
    4. **Khách hàng nguy cơ rời bỏ (At-Risk / Churn)**: Từng mua nhiều trong quá khứ nhưng thời gian dài gần đây không phát sinh đơn ➔ Cần nhân viên kinh doanh liên hệ tìm hiểu lý do ngay.

#### 1.2. Bài toán 2: Dự báo Doanh số Chuỗi thời gian (Sales Demand Forecasting)
* **Dữ liệu đầu vào**: Chuỗi doanh thu theo ngày từ `fact_sales` (365 ngày).
* **Đặc trưng chu kỳ & mùa vụ**:
  * Tính chu kỳ tuần (thứ 2 - chủ nhật).
  * Tính chu kỳ mùa vụ: Đợt nóng tháng 4 - tháng 6 (nhu cầu điều hòa/quạt tăng vọt) và giáp Tết tháng 12 - tháng 1 (mua sắm đồ gia dụng, TV).
* **Mô hình dự báo**:
  * Sử dụng mô hình **Holt-Winters Exponential Smoothing** kết hợp **Ridge / Polynomial Regression** với các biến giả (dummy variables) theo mùa vụ.
  * Dự báo doanh thu từng ngày cho **30 ngày tiếp theo**.
  * Tính toán khoảng tin cậy 95% (Confidence Interval: `forecast_lower`, `forecast_upper`).
  * Đánh giá chất lượng mô hình: MAE (Mean Absolute Error), RMSE, MAPE (< 10%).

#### 1.3. Bài toán 3: Phân tích Rủi ro Công nợ (Credit Risk Scoring)
* **Dữ liệu đầu vào**: Bảng `receivable` kết hợp `dim_customer`.
* **Chỉ số tính toán**:
  * Tỷ lệ nợ quá hạn trên hạn mức tín dụng (`outstanding / credit_limit`).
  * Số ngày quá hạn thanh toán trung bình (Overdue Days).
  * Gán nhãn mức độ rủi ro: **Thấp** (Xanh), **Trung bình** (Vàng), **Cao / Báo động** (Đỏ).

---

### GIAI ĐOẠN 2: THIẾT KẾ REST API TRÊN BACKEND (FASTAPI)

**Mục tiêu**: Bổ sung router phân tích vào [services/ai-service/app/main.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/main.py).

#### 2.1. Danh sách Endpoints

| Phương thức | Đường dẫn API | Mô tả chức năng |
| :--- | :--- | :--- |
| `GET` | `/api/v1/ai/analytics/overview` | Trả về các thẻ KPI chính (Tổng doanh thu, Lợi nhuận, Tồn kho, Công nợ, Số khách hàng). |
| `GET` | `/api/v1/ai/analytics/forecast` | Trả về chuỗi doanh thu thực tế 12 tháng qua + 30 ngày dự báo ML tương lai kèm dải tin cậy. |
| `GET` | `/api/v1/ai/analytics/rfm-clusters` | Trả về tỷ lệ phân bổ các cụm khách hàng, trọng tâm cụm và danh sách chi tiết khách hàng từng cụm. |
| `GET` | `/api/v1/ai/analytics/credit-risk` | Danh sách các khoản nợ có điểm rủi ro cao nhất cần thu hồi. |
| `GET` | `/api/v1/ai/analytics/ai-summary` | Đoạn nhận định và khuyến nghị kinh doanh tự động từ AI. |

#### 2.2. Cấu trúc Response mẫu: `/api/v1/ai/analytics/forecast`
```json
{
  "success": true,
  "data": {
    "historical": [
      { "date": "2026-09-01", "actual": 482000000 },
      { "date": "2026-09-30", "actual": 512000000 }
    ],
    "forecast": [
      { "date": "2026-10-01", "predicted": 525000000, "lower": 490000000, "upper": 560000000 },
      { "date": "2026-10-30", "predicted": 580000000, "lower": 535000000, "upper": 625000000 }
    ],
    "metrics": { "mape": "6.8%", "trend": "Tăng trưởng (+4.5%)", "season": "Bắt đầu mùa cao điểm cuối năm" }
  }
}
```

---

### GIAI ĐOẠN 3: XÂY DỰNG DASHBOARD BI TRÊN WEB (VUE 3)

**Mục tiêu**: Tạo giao diện báo cáo thông minh tại [frontend/src/pages/analytics/index.vue](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/pages/analytics/index.vue).

#### 3.1. Bố cục Giao diện (Layout Wireframe)
* **Khu vực 1: Header & AI Insights Banner**
  * Tiêu đề: *Báo Cáo Phân Tích Dữ Liệu & Dự Báo Kinh Doanh (BI Dashboard)*.
  * Banner AI thông minh: Hộp thông báo màu tím gradient tóm tắt xu hướng dự báo và cảnh báo rủi ro quan trọng nhất do AI tạo ra.
* **Khu vực 2: Hàng Thẻ KPI (Metric Cards)**
  * *Tổng Doanh Thu*: 216.9 tỷ (MoM +4.2%).
  * *Lợi Nhuận Gộp*: ~38.5 tỷ (Tỷ suất 17.8%).
  * *Giá Trị Tồn Kho*: 27.6 tỷ (12.850 sản phẩm).
  * *Công Nợ Quá Hạn*: 3.6 tỷ (Cần ưu tiên thu hồi).
* **Khu vực 3: Biểu đồ Dự Báo Doanh Số (Line Chart)**
  * Hiển thị đường doanh thu thực tế (màu xanh dương).
  * Nối tiếp bằng đường dự báo nét đứt của Machine Learning (màu tím sáng).
  * Vùng mờ bao quanh thể hiện khoảng dao động tin cậy (Confidence Band).
* **Khu vực 4: Phân Khúc Khách Hàng RFM (Doughnut Chart & Bảng Tương Tác)**
  * Biểu đồ tròn hiển thị tỷ trọng: VIP (8%), Trung thành (24%), Tiềm năng (38%), Nguy cơ rời bỏ (30%).
  * Nút bấm lọc theo cụm: Khi bấm vào cụm **"Nguy cơ rời bỏ"**, bảng bên cạnh sẽ hiển thị ngay tên khách hàng, tỉnh thành, công nợ và số ngày chưa mua hàng.
* **Khu vực 5: Cảnh Báo Tồn Kho & Công Nợ Quá Hạn (Risk Radar)**
  * Top sản phẩm có nguy cơ thiếu hàng (Stockout Risk) dựa trên tốc độ bán.
  * Top đại lý quá hạn thanh toán trên 60 ngày.

#### 3.2. Điều hướng Menu
Thêm route `/analytics` vào [frontend/src/router/index.js](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/router/index.js) và hiển thị mục **"Phân tích & Dự báo BI"** trong nhóm menu *Tổng quan* trên Sidebar.

---

### GIAI ĐOẠN 4: TÍCH HỢP MACHINE LEARNING VÀO TRỢ LÝ AI CHATBOT

**Mục tiêu**: Đăng ký các hàm ML thành Tools trong [services/ai-service/app/tools.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/tools.py) để Chatbot tự động phân tích khi đàm thoại.

#### 4.1. Khai báo Tools cho AI Agent
1. **Tool `get_sales_forecast_summary`**:
   * Gọi mô hình dự báo chuỗi thời gian, trả về dự báo 30 ngày và nhận định xu hướng.
2. **Tool `get_customer_churn_risk_list`**:
   * Gọi mô hình K-Means, trả về danh sách các khách hàng thuộc cụm "Nguy cơ rời bỏ" có doanh số quá khứ lớn nhất.
3. **Tool `analyze_product_inventory_health`**:
   * Đánh giá độ phủ tồn kho (ngày tồn kho an toàn) dựa trên tốc độ tiêu thụ dự báo.

#### 4.2. Kịch bản Prompt Demo mẫu cho Chatbot
* *Câu hỏi 1*: *"Dự báo doanh số 30 ngày tới của công ty ra sao? Xu hướng thế nào?"*
  ➔ **AI trả lời**: Gọi tool dự báo, đưa ra con số ~16.5 tỷ, phân tích xu hướng tăng trưởng đón đầu đợt mua sắm cuối năm.
* *Câu hỏi 2*: *"Nhóm khách hàng có nguy cơ rời bỏ gồm những ai và tôi nên xử lý thế nào?"*
  ➔ **AI trả lời**: Liệt kê top 5 đại lý lớn nhất đã quá 60 ngày chưa đặt đơn, gợi ý chính sách chiết khấu thêm 2% và cử nhân viên kinh doanh liên hệ trực tiếp.

---

### GIAI ĐOẠN 5: BÁO CÁO ĐỒ ÁN & KỊCH BẢN THUYẾT TRÌNH BẢO VỆ

#### 5.1. Dàn ý Báo cáo Học thuật (Chuẩn Đồ Án Điểm A+)
* **LỜI MỞ ĐẦU & TÍNH CẤP THIẾT CỦA ĐỀ TÀI**
* **CHƯƠNG 1: TỔNG QUAN HỆ THỐNG SMART ERP & NGUỒN DỮ LIỆU KINH DOANH**
  * 1.1. Mô hình hoạt động của nhà phân phối điện máy gia dụng.
  * 1.2. Kiến trúc kho dữ liệu Data Warehouse (Mô hình Star Schema).
* **CHƯƠNG 2: PHƯƠNG PHÁP NGHIÊN CỨU & MÔ HÌNH MACHINE LEARNING**
  * 2.1. Kỹ thuật phân khúc khách hàng RFM và thuật toán K-Means Clustering.
  * 2.2. Phương pháp lựa chọn số cụm tối ưu (Elbow Method & Silhouette Analysis).
  * 2.3. Mô hình dự báo chuỗi thời gian doanh số bán hàng (Time-series Forecasting).
  * 2.4. Đánh giá độ chính xác và sai số của mô hình (MAE, RMSE, MAPE).
* **CHƯƠNG 3: THIẾT KẾ & TRIỂN KHAI HỆ THỐNG BI DASHBOARD**
  * 3.1. Thiết kế luồng xử lý dữ liệu và REST API trên FastAPI.
  * 3.2. Xây dựng giao diện Dashboard trực quan tương tác bằng Vue 3.
  * 3.3. Tích hợp Trợ lý AI đàm thoại với công nghệ Function Calling / Agentic AI.
* **CHƯƠNG 4: KẾT QUẢ THỰC NGHIỆM & ĐỀ XUẤT GIẢI PHÁP KINH DOANH**
  * 4.1. Đánh giá hiệu quả kinh doanh từ các phân khúc khách hàng.
  * 4.2. Chiến lược điều tiết hàng tồn kho đón đầu mùa vụ theo dự báo.
  * 4.3. Biện pháp kiểm soát và thu hồi nợ quá hạn.
* **KẾT LUẬN & HƯỚNG PHÁT TRIỂN**

#### 5.2. Kịch bản Thuyết trình Demo 5 Phút Thuyết phục Hội đồng
1. **Phút 0 - 1**: Giới thiệu bài toán phân phối điện máy (217 tỷ doanh thu, 916 sản phẩm, 1.256 khách hàng) và mục tiêu kết hợp ML + Dashboard + Chatbot.
2. **Phút 1 - 3**: Trình chiếu **Trang Dashboard BI trên Web**:
   * Chỉ vào các thẻ KPI tổng quan.
   * Giới thiệu biểu đồ đường dự báo: giải thích đường nét đứt của mô hình Machine Learning dự báo trước 30 ngày tới.
   * Thao tác click vào cụm *Khách hàng nguy cơ rời bỏ* trên biểu đồ tròn để bảng dữ liệu lọc ra các khách hàng cần cứu vãn.
3. **Phút 3 - 4.5**: Chuyển sang **Trợ lý AI Chatbot**:
   * Gõ câu hỏi tự nhiên: *"Hãy phân tích cho tôi nhóm khách hàng nguy cơ rời bỏ"* hoặc *"Dự báo tháng tới thế nào?"*.
   * Chứng minh Chatbot tự động gọi mô hình ML để phân tích và đưa ra khuyến nghị thực tế.
4. **Phút 4.5 - 5**: Tóm tắt giá trị nghiệp vụ mà hệ thống mang lại cho doanh nghiệp và kết thúc phần trình bày.

---

## 3. CHECKLIST CÁC FILE CẦN TẠO / CHỈNH SỬA

| File | Vai trò | Trạng thái |
| :--- | :--- | :--- |
| [services/ai-service/app/ml.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/ml.py) | Module tính toán Machine Learning (K-Means, Forecasting, RFM) | ⏳ Sắp tạo |
| [services/ai-service/app/main.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/main.py) | Mở thêm các API endpoints `/api/v1/ai/analytics/*` | ⏳ Sắp chỉnh sửa |
| [services/ai-service/app/tools.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/tools.py) | Khai báo Tools ML cho Trợ lý AI Chatbot | ⏳ Sắp chỉnh sửa |
| [frontend/src/pages/analytics/index.vue](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/pages/analytics/index.vue) | Giao diện Dashboard Quản trị BI tương tác | ⏳ Sắp tạo |
| [frontend/src/router/index.js](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/router/index.js) | Đăng ký route `/analytics` vào menu Sidebar | ⏳ Sắp chỉnh sửa |
| `docs/data-analytics-report-draft.md` | Bản nháp báo cáo đồ án chi tiết hỗ trợ làm slide & word | ⏳ Sắp tạo |
