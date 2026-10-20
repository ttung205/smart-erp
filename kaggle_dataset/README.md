# Vietnam Electronics & Home Appliances B2B/B2C Distribution ERP Dataset (2024 - 2026)

## 📌 Bối cảnh Dataset (Context)
Bộ dữ liệu ghi nhận lịch sử giao dịch kinh doanh trong **2 năm liên tục (01/10/2024 – 30/09/2026)** của một công ty phân phối thiết bị điện máy gia dụng hàng đầu tại Việt Nam (Smart ERP).

Hệ thống phân phối phục vụ đa kênh:
- **Đại lý cấp 2 (Dealer)**: Cửa hàng điện máy, siêu thị tại các tỉnh thành.
- **Nhà phân phối cấp 1 (Distributor)**: Các nhà phân phối khu vực quy mô lớn.
- **Khách hàng Dự án (Project / Business)**: Bệnh viện, trường học, khách sạn, dự án chung cư.
- **Khách hàng Bán lẻ (Retail)**: Khách mua trực tiếp tại showroom.

---

## 📊 Quy mô Dữ liệu (Overview)
- **Thời gian**: 24 tháng liên tiếp (01/10/2024 → 30/09/2026).
- **Tổng doanh thu**: ~765.4 tỷ VNĐ.
- **Số giao dịch bán hàng (`fact_sales_enriched.csv`)**: 61,982 dòng.
- **Số khách hàng (`dim_customer.csv`)**: 2,591 khách hàng trải dài trên 34 tỉnh/thành phố Việt Nam.
- **Số sản phẩm (`dim_product.csv`)**: 916 sản phẩm thực tế cào từ Điện Máy Xanh (thuộc 29 danh mục, 78 thương hiệu lớn như Samsung, LG, Daikin, Panasonic, Sony...).
- **Hồ sơ công nợ (`receivable.csv`)**: 31,279 khoản công nợ có đối soát tuổi nợ.
- **Chuỗi thời gian tổng hợp (`monthly_sales_summary.csv`)**: 24 tháng phục vụ bài toán dự báo.

---

## 🗂 Cấu trúc các tệp dữ liệu (File Descriptions)

### 1. `fact_sales_enriched.csv` (Bảng giao dịch bán hàng chi tiết & Đặc trưng ML)
| Cột | Ý nghĩa | Ví dụ |
| :--- | :--- | :--- |
| `date` | Ngày phát sinh hóa đơn | `2025-05-18` |
| `invoice_number` | Số hóa đơn VAT | `INV-202505-0124` |
| `customer_id` | Mã định danh khách hàng | `104` |
| `customer_type` | Phân khúc khách hàng | `Dealer`, `Distributor`, `Project`, `Retail` |
| `province` / `region` | Tỉnh thành & Vùng miền | `Tỉnh Bắc Ninh` / `Bắc` |
| `product_id` | Mã sản phẩm | `312` |
| `category` / `brand` | Ngành hàng & Hãng | `Điều hòa` / `Daikin` |
| `quantity` | Số lượng mua | `5` |
| `unit_price` | Đơn giá bán (VNĐ) | `10,500,000` |
| `subtotal` | Doanh thu trước VAT (VNĐ) | `52,500,000` |
| `cost` / `profit` | Giá vốn & Lợi nhuận gộp | `39,375,000` / `13,125,000` |
| `profit_margin_pct` | Tỷ suất lợi nhuận gộp (%) | `25.0%` |
| `discount_percent` | Tỷ lệ chiết khấu (%) | `12.5%` |
| `delivery_days` | Số ngày giao hàng thực tế | `2` ngày |
| `customer_rating` | Đánh giá hài lòng (1-5 sao) | `5` sao |
| `avg_temperature_c` | Nhiệt độ trung bình ngày (°C) | `37.2` |
| `weather_condition` | Điều kiện thời tiết theo mùa | `Nắng nóng`, `Nồm ẩm`, `Rét lạnh` |

### 2. `dim_customer.csv` (Thông tin khách hàng)
Chứa thông tin chi tiết của 2,591 khách hàng: Mã KH, Tên công ty/Cửa hàng, Loại KH, Tỉnh thành, Vùng miền, Mã số thuế, Hạn mức tín dụng (`credit_limit`).

### 3. `dim_product.csv` (Thông tin sản phẩm)
Chứa 916 sản phẩm thật: Tên model, Hãng sản xuất, Ngành hàng, Xuất xứ, Giá niêm yết (`gross_price`), Giá vốn nhập (`cost_price`), Mức tồn kho an toàn (`min_stock`).

### 4. `receivable.csv` (Công nợ & Tuổi nợ)
Theo dõi các hóa đơn bán hàng: Số tiền hóa đơn, Số tiền đã thu, Số tiền còn nợ, Trạng thái (`PAID`, `OVERDUE`), và Số ngày quá hạn thanh toán (`overdue_days`).

### 5. `monthly_sales_summary.csv` (Dữ liệu chuỗi thời gian)
Tổng hợp 24 tháng: Doanh thu, Lợi nhuận, Tỷ suất lợi nhuận, Số lượng sản phẩm bán, Doanh thu trung bình.

---

## 🎯 Các bài toán Machine Learning & Phân tích gợi ý (Inspiration)
1. **Customer Segmentation (Phân cụm khách hàng)**: Sử dụng RFM (Recency, Frequency, Monetary) kết hợp thuật toán **K-Means / Hierarchical Clustering**.
2. **Sales Demand Forecasting (Dự báo nhu cầu)**: Dự báo doanh số 30–90 ngày tới bằng **Prophet, ARIMA, SARIMAX hoặc XGBoost**, có tính đến biến ngoại sinh là nhiệt độ thời tiết (`avg_temperature_c`) và tính mùa vụ Tết.
3. **Credit Risk / Churn Prediction (Dự báo rủi ro bùng nợ & rời bỏ)**: Phân loại nguy cơ nợ quá hạn dựa trên hạn mức tín dụng và lịch sử thanh toán.
4. **Market Basket Analysis (Phân tích giỏ hàng)**: Tìm các cặp sản phẩm thường được mua kèm bằng **Apriori / FP-Growth**.

---
**License**: CC BY 4.0 (Tự do sử dụng cho mục đích học tập & nghiên cứu).
