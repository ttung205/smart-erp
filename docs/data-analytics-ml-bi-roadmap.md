# LỘ TRÌNH ĐỒ ÁN: PHÂN TÍCH DỮ LIỆU, MACHINE LEARNING & BUSINESS INTELLIGENCE

> **Mục tiêu**: Xây dựng giải pháp phân tích dữ liệu toàn diện (**End-to-End Analytics Solution**) cho hệ thống Smart ERP phân phối thiết bị gia dụng: kết hợp giữa **Kho dữ liệu đã làm giàu (Enriched Kaggle Dataset)**, các mô hình **Machine Learning (K-Means RFM, Time Series Forecasting, Credit Risk)** và **Dashboard Quản trị BI** trực quan trên Web (Vue 3 + Chart.js).

---

## 1. TỔNG QUAN HỆ THỐNG & NGUỒN DỮ LIỆU

### 1.1. Bối cảnh Dữ liệu Nghiệp vụ (Enterprise Dataset: `kaggle_dataset/`)
Bộ dữ liệu ghi nhận hoạt động kinh doanh thực tế trong **2 năm liên tục (24 tháng: 01/10/2024 → 30/09/2026)** tại thư mục [kaggle_dataset/](file:///g:/Do_an_lien_nganh/smart-erp/kaggle_dataset):

* **Quy mô Doanh thu**: **~765.4 tỷ VNĐ** với **61.982** giao dịch bán hàng chi tiết (`fact_sales_enriched.csv`).
* **Khách hàng**: **2.591** khách hàng doanh nghiệp & bán lẻ trên **34** tỉnh thành (`dim_customer.csv`) gồm 4 phân khúc: *Dealer*, *Distributor*, *Project / Business*, *Retail*.
* **Danh mục Sản phẩm**: **916** sản phẩm thật cào từ Điện Máy Xanh (`dim_product.csv`) thuộc **29** danh mục, **78** thương hiệu lớn (Samsung, LG, Daikin, Panasonic, Sony...).
* **Hồ sơ Công nợ**: **31.279** khoản công nợ có đối soát ngày đến hạn và tuổi nợ (`receivable.csv`).
* **Chuỗi thời gian tổng hợp**: **24 tháng** kinh doanh (`monthly_sales_summary.csv`).
* **Các đặc trưng Machine Learning đã làm giàu (Feature Enrichment)**:
  * Lợi nhuận gộp (`profit`, `profit_margin_pct`), Tỷ lệ chiết khấu (`discount_percent`).
  * Thời gian giao hàng (`delivery_days`), Đánh giá hài lòng (`customer_rating`).
  * **Biến ngoại sinh thời tiết**: Nhiệt độ trung bình ngày (`avg_temperature_c`), Điều kiện thời tiết theo mùa (`weather_condition`: Nắng nóng, Rét lạnh, Nồm ẩm, Mưa bão) - cực kỳ giá trị cho bài toán dự báo nhu cầu điều hòa/quạt theo mùa.

---

### 1.2. Sơ đồ Kiến trúc Module Machine Learning & BI

```text
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                      BỘ DỮ LIỆU ĐÃ LÀM GIÀU (KAGGLE ENRICHED DATASET)                  │
│   kaggle_dataset/: fact_sales_enriched.csv, dim_customer.csv, dim_product.csv,...     │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │ Pandas DataFrames
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                        MODULE MACHINE LEARNING (Python - ai-service)                  │
│                    (Kèm In-Memory Caching để phản hồi API < 50ms)                      │
│                                                                                        │
│   [1. K-Means Clustering]       [2. Time Series Forecasting]     [3. Credit Risk]     │
│   Phân cụm 2.591 KH theo RFM    Dự báo 30 ngày doanh số tới     Chấm điểm nợ quá hạn  │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼ REST API JSON (`/api/v1/ai/analytics/*`)
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                             📊 DASHBOARD QUẢN TRỊ (BI VUE 3)                           │
│                                                                                        │
│ - Thẻ KPI Doanh thu (765 tỷ), Lợi nhuận, Tồn kho, Nợ xấu quá hạn                       │
│ - Biểu đồ đường 24 tháng thực tế + 30 ngày dự báo Machine Learning (kèm dải tin cậy)   │
│ - Biểu đồ tròn phân khúc khách hàng RFM tương tác: Click lọc danh sách khách hàng      │
│ - Bảng cảnh báo nợ quá hạn và danh sách khách hàng thuộc cụm "Nguy cơ rời bỏ"         │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. LỘ TRÌNH 4 GIAI ĐOẠN CHI TIẾT

```text
Giai đoạn 1 ──> Giai đoạn 2 ──> Giai đoạn 3 ──> Giai đoạn 4
(Machine        (Xây dựng       (Dựng Dashboard (Kiểm thử, Báo cáo &
 Learning)       FastAPI)        Vue 3 Web)      Thuyết trình)
```

---

### GIAI ĐOẠN 1: XÂY DỰNG MODULE MACHINE LEARNING (PYTHON CORE)

**Mục tiêu**: Xây dựng module độc lập [services/ai-service/app/ml.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/ml.py) xử lý dữ liệu từ `kaggle_dataset/` sử dụng `pandas`, `scikit-learn`, `statsmodels`.

#### 1.1. Bài toán 1: Phân cụm Khách hàng theo mô hình RFM (K-Means Clustering)
* **Trích xuất đặc trưng RFM** từ `fact_sales_enriched.csv` và `dim_customer.csv`:
  * **Recency ($R$)**: Số ngày tính từ ngày mua hàng cuối cùng của khách đến ngày chốt sổ (`2026-09-30`).
  * **Frequency ($F$)**: Tổng số đơn hàng/giao dịch thành công của khách trong 2 năm.
  * **Monetary ($M$)**: Tổng doanh thu (VNĐ) mà khách hàng đã đóng góp.
* **Tiền xử lý dữ liệu**:
  * Log-transform `np.log1p()` để giảm độ lệch phân phối và triệt tiêu ảnh hưởng của khách hàng ngoại lai (outliers).
  * Chuẩn hóa về cùng thang đo bằng `StandardScaler`.
* **Huấn luyện mô hình K-Means**:
  * Dùng phương pháp **Elbow Method** và **Silhouette Score** để xác định số cụm tối ưu ($k=4$).
  * Gán nhãn nghiệp vụ cho từng cụm:
    1. **VIP / Champions**: $R$ cực thấp (vừa mới mua), $F$ rất cao, $M$ lớn nhất ➔ Chính sách chăm sóc VIP, quà tặng tri ân.
    2. **Khách hàng trung thành (Loyal)**: Mua hàng đều đặn, giá trị khá ➔ Duy trì chiết khấu ổn định.
    3. **Khách hàng tiềm năng (Potential)**: Mới mua gần đây, giá trị trung bình ➔ Up-sell / Cross-sell.
    4. **Khách hàng nguy cơ rời bỏ (At-Risk / Churn)**: Từng mua nhiều trong quá khứ nhưng lâu không phát sinh đơn ➔ Nhân viên kinh doanh liên hệ tìm hiểu nguyên nhân ngay.

#### 1.2. Bài toán 2: Dự báo Doanh số Chuỗi thời gian (Sales Demand Forecasting)
* **Dữ liệu đầu vào**: Chuỗi doanh thu theo ngày/tháng từ `fact_sales_enriched.csv` (730 ngày / 24 tháng).
* **Đặc trưng chu kỳ & mùa vụ**:
  * Chu kỳ tuần (thứ 2 - chủ nhật): `seasonal_periods = 7`.
  * Tính mùa vụ năm lặp lại (Yearly Seasonality): Đợt nóng tháng 5 - tháng 8 (nhu cầu điều hòa/quạt tăng vọt) và giáp Tết tháng 12 - tháng 1 (mua sắm đồ gia dụng, TV).
  * Biến ngoại sinh: Nhiệt độ trung bình (`avg_temperature_c`).
* **Mô hình dự báo**:
  * Sử dụng mô hình **Holt-Winters Exponential Smoothing** kết hợp **Ridge / Polynomial Regression** với biến mùa vụ và nhiệt độ.
  * Dự báo doanh thu từng ngày cho **30 ngày tiếp theo**.
  * Tính toán khoảng tin cậy 95% (Confidence Interval: `forecast_lower`, `forecast_upper`).
  * Đánh giá chất lượng mô hình: MAE, RMSE, MAPE (< 8%).

#### 1.3. Bài toán 3: Phân tích Rủi ro Công nợ (Credit Risk Scoring)
* **Dữ liệu đầu vào**: `receivable.csv` kết hợp `dim_customer.csv`.
* **Chỉ số tính toán**:
  * Tỷ lệ nợ quá hạn trên hạn mức tín dụng (`outstanding / credit_limit`).
  * Số ngày quá hạn thanh toán (`overdue_days`).
  * Gán nhãn mức độ rủi ro: **Thấp** (Xanh), **Trung bình** (Vàng), **Cao / Báo động** (Đỏ).

#### 1.4. Cơ chế Caching (Tối ưu hiệu năng)
* Huấn luyện và pre-compute kết quả một lần khi khởi động service hoặc lưu vào in-memory cache.
* Đảm bảo API trả lời tức thì (< 50ms) mà không cần train lại model mỗi lần người dùng bấm xem dashboard.

#### 1.5. Quy trình 4 Bước Vận hành Thực tế (Cold Start Seeding & Rolling Window Incremental Sync)
Hệ thống kết hợp hài hòa giữa dữ liệu lịch sử nền tảng ban đầu (Kaggle/Doanh nghiệp cũ) và dữ liệu giao dịch phát sinh thực tế hàng ngày:
1. **Bước 1: Khởi động nền tảng (Cold Start Seeding)**: Dữ liệu 24 tháng (Kaggle/CSV) làm bộ dữ liệu nền tảng ban đầu nạp vào kho phân tích [analytics.db](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/data/analytics.db), giúp mô hình ML có ngay "tri thức" về quy luật chu kỳ tuần và mùa vụ mà không phải chờ tích lũy dữ liệu từ đầu.
2. **Bước 2: Phát sinh giao dịch hàng ngày (OLTP)**: Các đơn hàng, phiếu xuất kho, phiếu thu mới được nhân viên tạo trực tiếp trên giao diện ERP và lưu vào cơ sở dữ liệu tác nghiệp của các microservices (`sales`, `inventory`, `finance`).
3. **Bước 3: Tiến trình ETL ngầm tự động hợp nhất (Incremental ETL)**: Dịch vụ `ai-service` chạy ETL nền [warehouse.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/warehouse.py) (chế độ chỉ-đọc `mode=ro`) quét dữ liệu mới và nối tiếp (APPEND) vào bảng `fact_sales` và `dim_customer` trong kho [analytics.db](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/data/analytics.db).
4. **Bước 4: Tự động dịch chuyển cửa sổ thời gian (Rolling Window ML Retraining)**: Ngày chốt sổ $\text{cutoff\_date}$ tự động dịch chuyển theo ngày giao dịch mới nhất; điểm RFM khách hàng tự động thăng hạng khi mua thêm đơn; chuỗi dự báo tự động ngoại suy 30 ngày tiếp theo tính từ ngày mới nhất.

---

### GIAI ĐOẠN 2: THIẾT KẾ REST API TRÊN BACKEND (FASTAPI)

**Mục tiêu**: Bổ sung router phân tích vào [services/ai-service/app/main.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/main.py).

#### 2.1. Danh sách Endpoints

| Phương thức | Đường dẫn API | Mô tả chức năng |
| :--- | :--- | :--- |
| `GET` | `/api/v1/ai/analytics/overview` | Trả về các thẻ KPI chính (Tổng doanh thu 765 tỷ, Lợi nhuận, Tồn kho, Công nợ, Số khách hàng). |
| `GET` | `/api/v1/ai/analytics/forecast` | Trả về chuỗi doanh thu lịch sử 24 tháng + 30 ngày dự báo ML tương lai kèm dải tin cậy. |
| `GET` | `/api/v1/ai/analytics/rfm-clusters` | Trả về tỷ lệ phân bổ các cụm khách hàng, trọng tâm cụm và danh sách chi tiết khách hàng từng cụm. |
| `GET` | `/api/v1/ai/analytics/credit-risk` | Danh sách các khoản nợ có điểm rủi ro cao nhất cần thu hồi. |

#### 2.2. Cấu trúc Response mẫu: `/api/v1/ai/analytics/forecast`
```json
{
  "success": true,
  "data": {
    "historical": [
      { "date": "2026-09-01", "actual": 1280000000 },
      { "date": "2026-09-30", "actual": 1410000000 }
    ],
    "forecast": [
      { "date": "2026-10-01", "predicted": 1450000000, "lower": 1360000000, "upper": 1540000000 },
      { "date": "2026-10-30", "predicted": 1620000000, "lower": 1510000000, "upper": 1730000000 }
    ],
    "metrics": { "mape": "6.2%", "trend": "Tăng trưởng (+5.8%)", "season": "Bắt đầu mùa cao điểm cuối năm" }
  }
}
```

---

### GIAI ĐOẠN 3: XÂY DỰNG DASHBOARD BI TRÊN WEB (VUE 3 + CHART.JS)

**Mục tiêu**: Tạo giao diện báo cáo thông minh tại [frontend/src/pages/analytics/index.vue](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/pages/analytics/index.vue).

#### 3.1. Thư viện sử dụng
* Cài đặt `chart.js` (`npm install chart.js`) để kết hợp với `<Chart />` component của PrimeVue.
* Hỗ trợ render biểu đồ kết hợp đường thực tế + đường dự báo nét đứt + dải tin cậy màu mờ (fill band).

#### 3.2. Bố cục Giao diện (Layout Wireframe)
* **Khu vực 1: Hàng Thẻ KPI (Metric Cards)**
  * *Tổng Doanh Thu*: 765.4 tỷ VNĐ (24 tháng).
  * *Lợi Nhuận Gộp*: ~135.8 tỷ (Tỷ suất 17.7%).
  * *Khách hàng hoạt động*: 2.591 đối tác.
  * *Công Nợ Quá Hạn*: Cảnh báo các khoản nợ quá hạn > 60 ngày cần ưu tiên thu hồi.
* **Khu vực 2: Biểu đồ Dự Báo Doanh Số (Line Chart)**
  * Hiển thị đường doanh thu thực tế 24 tháng (màu xanh dương).
  * Nối tiếp bằng đường dự báo nét đứt của Machine Learning (màu tím sáng).
  * Vùng mờ bao quanh thể hiện khoảng dao động tin cậy (Confidence Band).
* **Khu vực 3: Phân Khúc Khách Hàng RFM (Doughnut Chart & Bảng Tương Tác)**
  * Biểu đồ tròn hiển thị tỷ trọng: VIP (8%), Trung thành (24%), Tiềm năng (38%), Nguy cơ rời bỏ (30%).
  * Nút bấm lọc theo cụm: Khi bấm vào cụm **"Nguy cơ rời bỏ"**, bảng bên cạnh sẽ hiển thị ngay tên khách hàng, tỉnh thành, công nợ và số ngày chưa mua hàng.
* **Khu vực 4: Cảnh Báo Công Nợ Quá Hạn (Risk Radar)**
  * Top đại lý quá hạn thanh toán trên 60 ngày kèm mức độ rủi ro Đỏ / Vàng.

#### 3.3. Điều hướng Menu
Thêm route `/analytics` vào [frontend/src/router/index.js](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/router/index.js) và hiển thị mục **"Phân tích & Dự báo BI"** trong nhóm menu *Tổng quan* trên Sidebar.

---

### GIAI ĐOẠN 4: KIỂM THỬ HIỆU NĂNG, BÁO CÁO ĐỒ ÁN & THUYẾT TRÌNH BẢO VỆ

#### 4.1. Kiểm thử hiệu năng (Benchmarking)
* Kiểm thử thời gian đáp ứng API `/api/v1/ai/analytics/*` đảm bảo $P_{95} < 50ms$ nhờ cơ chế In-memory Caching.
* Kiểm thử độ chính xác mô hình: MAPE < 8% cho dự báo doanh số và Silhouette Score > 0.4 cho phân cụm RFM.

#### 4.2. Dàn ý Báo cáo Học thuật (Chuẩn Đồ Án Điểm A+)
* **LỜI MỞ ĐẦU & TÍNH CẤP THIẾT CỦA ĐỀ TÀI**
* **CHƯƠNG 1: TỔNG QUAN HỆ THỐNG SMART ERP & BỘ DỮ LIỆU KINH DOANH KAGGLE**
  * 1.1. Mô hình hoạt động phân phối điện máy gia dụng (B2B/B2C).
  * 1.2. Cấu trúc bộ dữ liệu chuẩn hóa 2 năm (765.4 tỷ VNĐ, 61.982 giao dịch).
  * 1.3. Các đặc trưng đã làm giàu: thời tiết, nhiệt độ, chiết khấu, đánh giá hài lòng.
* **CHƯƠNG 2: PHƯƠNG PHÁP NGHIÊN CỨU & MÔ HÌNH MACHINE LEARNING**
  * 2.1. Kỹ thuật phân khúc khách hàng RFM và thuật toán K-Means Clustering.
  * 2.2. Phương pháp lựa chọn số cụm tối ưu (Elbow Method & Silhouette Analysis).
  * 2.3. Mô hình dự báo chuỗi thời gian doanh số bán hàng (Time-series Forecasting).
  * 2.4. Đánh giá độ chính xác và sai số của mô hình (MAE, RMSE, MAPE).
* **CHƯƠNG 3: THIẾT KẾ & TRIỂN KHAI HỆ THỐNG BI DASHBOARD**
  * 3.1. Thiết kế luồng xử lý dữ liệu và REST API trên FastAPI.
  * 3.2. Xây dựng giao diện Dashboard trực quan tương tác bằng Vue 3 + Chart.js.
  * 3.3. Tối ưu hóa hiệu năng bằng cơ chế In-memory Caching.
* **CHƯƠNG 4: KẾT QUẢ THỰC NGHIỆM & ĐỀ XUẤT GIẢI PHÁP KINH DOANH**
  * 4.1. Đánh giá hiệu quả kinh doanh từ các phân khúc khách hàng.
  * 4.2. Chiến lược điều tiết bán hàng đón đầu mùa vụ theo dự báo.
  * 4.3. Biện pháp kiểm soát và thu hồi nợ quá hạn.
* **KẾT LUẬN & HƯỚNG PHÁT TRIỂN**

#### 4.3. Kịch bản Thuyết trình Demo 5 Phút Thuyết phục Hội đồng
1. **Phút 0 - 1**: Giới thiệu bài toán phân phối điện máy (765 tỷ doanh thu, 916 sản phẩm, 2.591 khách hàng) và mục tiêu ứng dụng Machine Learning & Dashboard BI trong ERP.
2. **Phút 1 - 3**: Trình chiếu **Trang Dashboard BI trên Web**:
   * Chỉ vào các thẻ KPI tổng quan.
   * Giới thiệu biểu đồ đường dự báo: giải thích đường nét đứt của mô hình Machine Learning dự báo trước 30 ngày tới kèm dải tin cậy.
   * Thao tác click vào cụm *Khách hàng nguy cơ rời bỏ* trên biểu đồ tròn để bảng dữ liệu lọc ra các khách hàng cần cứu vãn.
3. **Phút 3 - 4.5**: Giải thích phương pháp luận mô hình học máy:
   * Trình bày thuật toán K-Means phân cụm RFM và số cụm tối ưu theo Silhouette Score.
   * Trình bày mô hình Holt-Winters kết hợp biến ngoại sinh thời tiết.
4. **Phút 4.5 - 5**: Tóm tắt giá trị nghiệp vụ mà hệ thống mang lại cho doanh nghiệp và kết thúc phần trình bày.

---

## 3. CHECKLIST CÁC FILE CẦN TẠO / CHỈNH SỬA

| File | Vai trò | Trạng thái |
| :--- | :--- | :---: |
| [services/ai-service/requirements.txt](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/requirements.txt) | Bổ sung thư viện `pandas`, `numpy`, `scikit-learn`, `statsmodels` | ✅ Đã hoàn thành |
| [services/ai-service/app/ml.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/ml.py) | Module ML (K-Means RFM đủ 2.591 khách, Holt-Winters + Decomposition sóng tuần, Credit Risk, Caching, nạp từ SQLite `analytics.db`) | ✅ Đã hoàn thành |
| [services/ai-service/app/main.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/main.py) | REST API endpoints `/api/v1/ai/analytics/*` có middleware JWT và ETL sync | ✅ Đã hoàn thành |
| [frontend/package.json](file:///g:/Do_an_lien_nganh/smart-erp/frontend/package.json) | Cài đặt `chart.js` cho Vue 3 | ✅ Đã hoàn thành |
| [frontend/src/api/analytics.api.js](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/api/analytics.api.js) | Axios API client kết nối các endpoints analytics | ✅ Đã hoàn thành |
| [frontend/src/pages/analytics/index.vue](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/pages/analytics/index.vue) | Giao diện Dashboard BI tương tác, 4 thẻ KPI, dải tin cậy 95%, bảng kiểm thử 30 ngày, phân cụm RFM | ✅ Đã hoàn thành |
| [frontend/src/router/index.js](file:///g:/Do_an_lien_nganh/smart-erp/frontend/src/router/index.js) | Đăng ký route `/analytics` vào menu Sidebar "Phân tích & Dự báo BI" | ✅ Đã hoàn thành |
| [scripts/data_cleaning_pipeline.py](file:///g:/Do_an_lien_nganh/smart-erp/scripts/data_cleaning_pipeline.py) | Script làm sạch dữ liệu 5 giai đoạn cho demo/báo cáo | ✅ Đã hoàn thành |
| [docs/bao_cao/data_cleaning_report.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/bao_cao/data_cleaning_report.md) | Báo cáo kiểm định chất lượng làm sạch dữ liệu | ✅ Đã hoàn thành |
| [services/ai-service/app/tools.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/tools.py) | Tích hợp 6 công cụ Nhóm 1 (Predictive ML) & Nhóm 2 (Prescriptive Actions) vào Trợ lý AI Agent | ✅ Đã hoàn thành |
| [services/ai-service/app/agent.py](file:///g:/Do_an_lien_nganh/smart-erp/services/ai-service/app/agent.py) | Cập nhật SYSTEM_PROMPT hướng dẫn LLM tự động kích hoạt các mô hình ML & Đề xuất hành động | ✅ Đã hoàn thành |
| [docs/bao_cao/data-analytics-report-draft.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/bao_cao/data-analytics-report-draft.md) | Báo cáo học thuật đồ án chi tiết (4 chương + kịch bản demo 5 phút) | ✅ Đã hoàn thành |
