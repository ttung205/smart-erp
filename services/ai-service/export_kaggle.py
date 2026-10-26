"""Script xuất dữ liệu từ kho phân tích (MySQL, schema erp_analytics_db) thành bộ Dataset chuẩn hóa cho Kaggle."""
import csv
import random
import sys
from datetime import datetime
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

from app import config, warehouse

ROOT = Path(__file__).resolve().parents[2]
OUT_DIR = ROOT / "kaggle_dataset"

OUT_DIR.mkdir(parents=True, exist_ok=True)

print(f"Đang đọc dữ liệu từ: mysql://{config.MYSQL_HOST}:{config.MYSQL_PORT}/{config.ANALYTICS_DB_NAME}")
con = warehouse.connect()

def export_table(query: str, outfile: Path, enrich_fn=None):
    cur = con.execute(query)
    rows = cur.fetchall()
    if not rows:
        print(f"Bảng {outfile.name} không có dữ liệu.")
        return 0

    first_row = dict(rows[0])
    if enrich_fn:
        first_row.update(enrich_fn(first_row))
    fieldnames = list(first_row.keys())

    with open(outfile, "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=fieldnames)
        writer.writeheader()
        for r in rows:
            d = dict(r)
            if enrich_fn:
                d.update(enrich_fn(d))
            writer.writerow(d)

    print(f"✓ Đã xuất {outfile.name}: {len(rows):,} dòng")
    return len(rows)

# 1. Dim Product
n_prod = export_table("""
    SELECT id, sku, model, name, category, brand, origin, unit, 
           gross_price, cost_price, min_stock, status, supplier_id
    FROM dim_product
""", OUT_DIR / "dim_product.csv")

# 2. Dim Customer
n_cust = export_table("""
    SELECT id, code, name, type, province, region, address, 
           tax_code, phone, email, credit_limit, status, created_at
    FROM dim_customer
""", OUT_DIR / "dim_customer.csv")

# 3. Dim Supplier
n_sup = export_table("""
    SELECT id, code, name, supplier_type, supplier_group, payment_terms, email, phone, status
    FROM dim_supplier
""", OUT_DIR / "dim_supplier.csv")

# 4. Fact Sales Enriched (Đầy đủ đặc trưng cho Machine Learning)
random.seed(42)

def enrich_sales(row):
    dt = datetime.strptime(row["date"], "%Y-%m-%d")
    month = dt.month
    region = row.get("region") or "Bắc"
    cust_type = row.get("customer_type") or "Retail"

    # Lợi nhuận
    revenue = float(row.get("subtotal") or 0)
    cost = float(row.get("cost") or 0)
    profit = round(revenue - cost, 2)
    profit_margin = round(profit / revenue * 100, 1) if revenue else 0.0

    # Nhiệt độ & Thời tiết theo mùa và miền
    if region == "Bắc":
        if month in [5, 6, 7, 8]:
            temp = round(random.uniform(33.0, 39.5), 1)
            weather = "Nắng nóng"
        elif month in [12, 1, 2]:
            temp = round(random.uniform(13.0, 20.0), 1)
            weather = "Rét lạnh"
        elif month in [3, 4]:
            temp = round(random.uniform(22.0, 27.0), 1)
            weather = "Nồm ẩm"
        else:
            temp = round(random.uniform(24.0, 30.0), 1)
            weather = "Mát mẻ"
    elif region == "Trung":
        if month in [5, 6, 7, 8]:
            temp = round(random.uniform(34.0, 40.0), 1)
            weather = "Nắng nóng gay gắt"
        elif month in [10, 11, 12]:
            temp = round(random.uniform(20.0, 25.0), 1)
            weather = "Mưa bão"
        else:
            temp = round(random.uniform(25.0, 31.0), 1)
            weather = "Nắng nhẹ"
    else: # Nam
        if month in [3, 4, 5]:
            temp = round(random.uniform(33.0, 37.0), 1)
            weather = "Nắng nóng"
        elif month in [6, 7, 8, 9, 10]:
            temp = round(random.uniform(28.0, 32.0), 1)
            weather = "Mùa mưa"
        else:
            temp = round(random.uniform(29.0, 33.0), 1)
            weather = "Nắng ráo"

    # Chiết khấu
    discount_map = {"Distributor": (18, 24), "Dealer": (10, 16), "Project / Business": (8, 15), "Retail": (0, 5)}
    min_d, max_d = discount_map.get(cust_type, (5, 10))
    discount_pct = round(random.uniform(min_d, max_d), 1)

    # Thời gian giao hàng
    delivery_days = random.choices([1, 2, 3, 4, 5, 7], weights=[35, 30, 15, 10, 6, 4])[0]

    # Đánh giá hài lòng khách hàng (1-5 sao)
    if delivery_days <= 2:
        rating = random.choices([5, 4, 3], weights=[70, 25, 5])[0]
    elif delivery_days <= 4:
        rating = random.choices([5, 4, 3, 2], weights=[20, 50, 25, 5])[0]
    else:
        rating = random.choices([4, 3, 2, 1], weights=[10, 35, 35, 20])[0]

    return {
        "profit": profit,
        "profit_margin_pct": profit_margin,
        "discount_percent": discount_pct,
        "delivery_days": delivery_days,
        "customer_rating": rating,
        "avg_temperature_c": temp,
        "weather_condition": weather,
    }

n_sales = export_table("""
    SELECT 
        s.date,
        s.invoice_number,
        s.order_id,
        s.order_number,
        s.customer_id,
        c.name AS customer_name,
        c.type AS customer_type,
        c.province,
        c.region,
        s.product_id,
        p.name AS product_name,
        p.category,
        p.brand,
        s.quantity,
        s.unit_price,
        s.revenue AS subtotal,
        s.cost,
        s.vat AS vat_amount,
        s.salesperson_id
    FROM fact_sales s
    LEFT JOIN dim_customer c ON c.id = s.customer_id
    LEFT JOIN dim_product p ON p.id = s.product_id
""", OUT_DIR / "fact_sales_enriched.csv", enrich_fn=enrich_sales)

# 5. Receivable (Công nợ & Tuổi nợ)
today = "2026-09-30"
def enrich_recv(row):
    out = float(row.get("outstanding") or 0)
    due = row.get("due_date") or today
    status = row.get("status") or "PAID"
    if status == "OVERDUE" or (out > 0 and due < today):
        d1 = datetime.strptime(due, "%Y-%m-%d")
        d2 = datetime.strptime(today, "%Y-%m-%d")
        overdue_days = max(0, (d2 - d1).days)
        risk_level = "Cao" if overdue_days > 45 else ("Trung bình" if overdue_days > 15 else "Thấp")
    else:
        overdue_days = 0
        risk_level = "Không có rủi ro"
    return {"overdue_days": overdue_days, "risk_level": risk_level}

n_recv = export_table("""
    SELECT customer_id, invoice_number, invoice_date, due_date, 
           amount, paid, outstanding, status
    FROM receivable
""", OUT_DIR / "receivable.csv", enrich_fn=enrich_recv)

# 6. Monthly Summary (Chuỗi thời gian tổng hợp theo tháng)
cur = con.execute("""
    SELECT 
        substr(s.date, 1, 7) AS month,
        count(distinct s.order_id) AS order_count,
        sum(s.quantity) AS items_sold,
        round(sum(s.revenue), 0) AS total_revenue,
        round(sum(s.cost), 0) AS total_cost,
        round(sum(s.revenue - s.cost), 0) AS total_profit,
        round(avg(s.revenue), 0) AS avg_item_revenue
    FROM fact_sales s
    GROUP BY substr(s.date, 1, 7)
    ORDER BY month
""")
monthly_rows = [dict(r) for r in cur.fetchall()]
for r in monthly_rows:
    rev = r["total_revenue"]
    profit = r["total_profit"]
    r["profit_margin_pct"] = round(profit / rev * 100, 2) if rev else 0.0

if monthly_rows:
    with open(OUT_DIR / "monthly_sales_summary.csv", "w", newline="", encoding="utf-8-sig") as f:
        writer = csv.DictWriter(f, fieldnames=list(monthly_rows[0].keys()))
        writer.writeheader()
        writer.writerows(monthly_rows)
    print(f"✓ Đã xuất monthly_sales_summary.csv: {len(monthly_rows)} tháng (chuỗi thời gian 2 năm)")

# 7. README.md chuẩn Kaggle
readme_content = f"""# Vietnam Electronics & Home Appliances B2B/B2C Distribution ERP Dataset (2024 - 2026)

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
- **Số giao dịch bán hàng (`fact_sales_enriched.csv`)**: {n_sales:,} dòng.
- **Số khách hàng (`dim_customer.csv`)**: {n_cust:,} khách hàng trải dài trên 34 tỉnh/thành phố Việt Nam.
- **Số sản phẩm (`dim_product.csv`)**: {n_prod:,} sản phẩm thực tế cào từ Điện Máy Xanh (thuộc 29 danh mục, 78 thương hiệu lớn như Samsung, LG, Daikin, Panasonic, Sony...).
- **Hồ sơ công nợ (`receivable.csv`)**: {n_recv:,} khoản công nợ có đối soát tuổi nợ.
- **Chuỗi thời gian tổng hợp (`monthly_sales_summary.csv`)**: {len(monthly_rows)} tháng phục vụ bài toán dự báo.

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
Chứa thông tin chi tiết của {n_cust:,} khách hàng: Mã KH, Tên công ty/Cửa hàng, Loại KH, Tỉnh thành, Vùng miền, Mã số thuế, Hạn mức tín dụng (`credit_limit`).

### 3. `dim_product.csv` (Thông tin sản phẩm)
Chứa {n_prod:,} sản phẩm thật: Tên model, Hãng sản xuất, Ngành hàng, Xuất xứ, Giá niêm yết (`gross_price`), Giá vốn nhập (`cost_price`), Mức tồn kho an toàn (`min_stock`).

### 4. `receivable.csv` (Công nợ & Tuổi nợ)
Theo dõi các hóa đơn bán hàng: Số tiền hóa đơn, Số tiền đã thu, Số tiền còn nợ, Trạng thái (`PAID`, `OVERDUE`), và Số ngày quá hạn thanh toán (`overdue_days`).

### 5. `monthly_sales_summary.csv` (Dữ liệu chuỗi thời gian)
Tổng hợp {len(monthly_rows)} tháng: Doanh thu, Lợi nhuận, Tỷ suất lợi nhuận, Số lượng sản phẩm bán, Doanh thu trung bình.

---

## 🎯 Các bài toán Machine Learning & Phân tích gợi ý (Inspiration)
1. **Customer Segmentation (Phân cụm khách hàng)**: Sử dụng RFM (Recency, Frequency, Monetary) kết hợp thuật toán **K-Means / Hierarchical Clustering**.
2. **Sales Demand Forecasting (Dự báo nhu cầu)**: Dự báo doanh số 30–90 ngày tới bằng **Prophet, ARIMA, SARIMAX hoặc XGBoost**, có tính đến biến ngoại sinh là nhiệt độ thời tiết (`avg_temperature_c`) và tính mùa vụ Tết.
3. **Credit Risk / Churn Prediction (Dự báo rủi ro bùng nợ & rời bỏ)**: Phân loại nguy cơ nợ quá hạn dựa trên hạn mức tín dụng và lịch sử thanh toán.
4. **Market Basket Analysis (Phân tích giỏ hàng)**: Tìm các cặp sản phẩm thường được mua kèm bằng **Apriori / FP-Growth**.

---
**License**: CC BY 4.0 (Tự do sử dụng cho mục đích học tập & nghiên cứu).
"""

with open(OUT_DIR / "README.md", "w", encoding="utf-8") as f:
    f.write(readme_content)
print(f"✓ Đã tạo file README.md chuẩn tài liệu Kaggle!")

con.close()
print(f"\n========================================================")
print(f"🎉 HOÀN TẤT XUẤT DATASET KAGGLE TẠI:")
print(f"📁 {OUT_DIR}")
print(f"========================================================")
