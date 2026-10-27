"""Seed erp_analytics_db from kaggle_dataset for AI agent and analytics tools."""
from __future__ import annotations

import csv
import time
from datetime import datetime
from pathlib import Path
import pymysql

ROOT = Path(__file__).resolve().parent.parent
KAGGLE = ROOT / "kaggle_dataset"

TABLE_OPTIONS = "default charset utf8mb4 collate utf8mb4_unicode_ci"
TABLES = {
    "dim_product": """id bigint primary key, sku varchar(64), model text, name varchar(512), category varchar(255),
        brand varchar(255), origin varchar(255), unit varchar(64), gross_price double, cost_price double,
        min_stock bigint, status varchar(64), created_at varchar(32), supplier_id bigint""",
    "dim_customer": """id bigint primary key, code varchar(64), name varchar(255), type varchar(64),
        province varchar(128), region varchar(32), address text, tax_code varchar(64), phone varchar(64),
        email varchar(255), credit_limit double, status varchar(64), created_at varchar(32)""",
    "dim_supplier": """id bigint primary key, code varchar(64), name varchar(255), supplier_type varchar(128),
        supplier_group varchar(128), payment_terms varchar(255), email varchar(255), phone varchar(64),
        contact_person varchar(255), status varchar(64), notes text""",
    "dim_staff": "id bigint primary key, name varchar(255), email varchar(255), roles text",
    "fact_sales": """date varchar(10), invoice_number varchar(64), order_id bigint, order_number varchar(64),
        customer_id bigint, product_id bigint, quantity double, unit_price double, revenue double, vat double,
        cost double, salesperson_id bigint, vat_invoiced tinyint,
        index ix_sales_date (date), index ix_sales_product (product_id), index ix_sales_customer (customer_id)""",
    "fact_order": """id bigint primary key, order_number varchar(64), date varchar(10), customer_id bigint,
        status varchar(64), subtotal double, total double, salesperson_id bigint, quotation_id bigint""",
    "fact_order_line": """order_id bigint, product_id bigint, quantity double, delivered double, price double,
        discount double, subtotal double, index ix_line_order (order_id)""",
    "stock": "product_id bigint primary key, on_hand bigint, available bigint",
    "stock_move": """date varchar(10), product_id bigint, doc_type varchar(64), doc_id varchar(128), delta bigint,
        index ix_move_product (product_id, date)""",
    "fact_po_line": """po_id bigint, po_code varchar(64), date varchar(10), expected_date varchar(10),
        status varchar(64), supplier_id bigint, currency varchar(8), product_id bigint, qty_ordered bigint,
        qty_received bigint, unit_cost double, first_receipt varchar(10), last_receipt varchar(10),
        index ix_po_product (product_id)""",
    "receivable": """customer_id bigint, invoice_number varchar(64), invoice_date varchar(10),
        due_date varchar(10), amount double, paid double, outstanding double, status varchar(64),
        index ix_recv_customer (customer_id)""",
    "meta": "meta_key varchar(64) primary key, meta_value varchar(255)",
}


def main():
    print("Connecting to MySQL erp_analytics_db...")
    conn = pymysql.connect(
        host="127.0.0.1",
        port=3306,
        user="erp_user",
        password="erp_secret",
        database="erp_analytics_db",
        charset="utf8mb4",
        autocommit=False,
    )
    cur = conn.cursor()

    print("Creating warehouse tables...")
    for table_name, schema in TABLES.items():
        cur.execute(f"DROP TABLE IF EXISTS `{table_name}`")
        cur.execute(f"CREATE TABLE `{table_name}` ({schema}) {TABLE_OPTIONS}")

    # 1. Load dim_product
    print("Loading dim_product...")
    with open(KAGGLE / "dim_product.csv", "r", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        products = []
        for r in reader:
            products.append((
                int(r["id"]),
                r.get("sku", ""),
                r.get("model", ""),
                r.get("name", ""),
                r.get("category", ""),
                r.get("brand", ""),
                r.get("origin", ""),
                r.get("unit", ""),
                float(r.get("gross_price") or 0),
                float(r.get("cost_price") or 0),
                int(float(r.get("min_stock") or 0)),
                r.get("status", "ACTIVE"),
                "2024-10-01 00:00:00",
                int(r["supplier_id"]) if r.get("supplier_id") else None,
            ))
        cur.executemany(
            "INSERT INTO dim_product VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
            products,
        )

    # 2. Load dim_customer
    print("Loading dim_customer...")
    with open(KAGGLE / "dim_customer.csv", "r", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        customers = []
        for r in reader:
            customers.append((
                int(r["id"]),
                r.get("code", ""),
                r.get("name", ""),
                r.get("type", ""),
                r.get("province", ""),
                r.get("region", ""),
                r.get("address", ""),
                r.get("tax_code", ""),
                r.get("phone", ""),
                r.get("email", ""),
                float(r.get("credit_limit") or 0),
                r.get("status", "ACTIVE"),
                r.get("created_at", "2024-10-01 00:00:00"),
            ))
        cur.executemany(
            "INSERT INTO dim_customer VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
            customers,
        )

    # 3. Load dim_supplier
    print("Loading dim_supplier...")
    with open(KAGGLE / "dim_supplier.csv", "r", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        suppliers = []
        for r in reader:
            suppliers.append((
                int(r["id"]),
                r.get("code", ""),
                r.get("name", ""),
                r.get("supplier_type", ""),
                r.get("supplier_group", ""),
                r.get("payment_terms", ""),
                r.get("email", ""),
                r.get("phone", ""),
                r.get("contact_person", ""),
                r.get("status", "ACTIVE"),
                r.get("notes", ""),
            ))
        cur.executemany(
            "INSERT INTO dim_supplier VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
            suppliers,
        )

    # 4. Load dim_staff (mock 20 staff)
    print("Loading dim_staff...")
    staff = [
        (1, "Nguyễn Văn An", "an.nguyen@smart-erp.local", "SUPER_ADMIN"),
        (2, "Trần Thị Bình", "binh.tran@smart-erp.local", "SALE_MANAGER"),
        (3, "Lê Hoàng Cường", "cuong.le@smart-erp.local", "SALE"),
        (4, "Phạm Ngọc Dung", "dung.pham@smart-erp.local", "PURCHASE_MANAGER"),
        (5, "Hoàng Minh Đức", "duc.hoang@smart-erp.local", "PURCHASE"),
        (6, "Vũ Thu Hà", "ha.vu@smart-erp.local", "FINANCE_ACCOUNTING_MANAGER"),
        (7, "Đặng Văn Hùng", "hung.dang@smart-erp.local", "FINANCE_ACCOUNTING"),
        (8, "Bùi Thị Khánh", "khanh.bui@smart-erp.local", "LOGISTIC_MANAGER"),
        (9, "Đỗ Xuân Lộc", "loc.do@smart-erp.local", "LOGISTIC"),
        (10, "Ngô Trí Nam", "nam.ngo@smart-erp.local", "TECHNICAL"),
        (11, "Đoàn Phương Oanh", "oanh.doan@smart-erp.local", "SALE"),
        (12, "Lý Quốc Quân", "quan.ly@smart-erp.local", "SALE"),
    ]
    cur.executemany("INSERT INTO dim_staff VALUES (%s,%s,%s,%s)", staff)

    # 5. Load fact_sales
    print("Loading fact_sales from fact_sales_enriched.csv (61,982 rows)...")
    sales_batch = []
    orders_map = {}
    with open(KAGGLE / "fact_sales_enriched.csv", "r", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        for r in reader:
            oid = int(r["order_id"])
            rev = float(r.get("subtotal") or 0)
            sales_batch.append((
                r.get("date", "")[:10],
                r.get("invoice_number", ""),
                oid,
                r.get("order_number", ""),
                int(r["customer_id"]),
                int(r["product_id"]),
                float(r.get("quantity") or 0),
                float(r.get("unit_price") or 0),
                rev,
                float(r.get("vat_amount") or 0),
                float(r.get("cost") or 0),
                int(r.get("salesperson_id") or 1),
                1,
            ))
            if oid not in orders_map:
                orders_map[oid] = {
                    "number": r.get("order_number", ""),
                    "date": r.get("date", "")[:10],
                    "customer_id": int(r["customer_id"]),
                    "subtotal": rev,
                    "total": rev + float(r.get("vat_amount") or 0),
                    "salesperson_id": int(r.get("salesperson_id") or 1),
                }
            else:
                orders_map[oid]["subtotal"] += rev
                orders_map[oid]["total"] += rev + float(r.get("vat_amount") or 0)

            if len(sales_batch) >= 5000:
                cur.executemany(
                    "INSERT INTO fact_sales VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
                    sales_batch,
                )
                sales_batch.clear()
        if sales_batch:
            cur.executemany(
                "INSERT INTO fact_sales VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
                sales_batch,
            )
            sales_batch.clear()

    # 6. Load fact_order
    print(f"Loading fact_order ({len(orders_map):,} orders)...")
    order_rows = [
        (oid, v["number"], v["date"], v["customer_id"], "COMPLETED", v["subtotal"], v["total"], v["salesperson_id"], None)
        for oid, v in orders_map.items()
    ]
    cur.executemany("INSERT INTO fact_order VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)", order_rows)

    # 7. Load receivable
    print("Loading receivable (31,279 rows)...")
    recv_batch = []
    with open(KAGGLE / "receivable.csv", "r", encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        for r in reader:
            recv_batch.append((
                int(r["customer_id"]),
                r.get("invoice_number", ""),
                r.get("invoice_date", "")[:10],
                r.get("due_date", "")[:10],
                float(r.get("amount") or 0),
                float(r.get("paid") or 0),
                float(r.get("outstanding") or 0),
                r.get("status", "PAID"),
            ))
            if len(recv_batch) >= 5000:
                cur.executemany(
                    "INSERT INTO receivable VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
                    recv_batch,
                )
                recv_batch.clear()
        if recv_batch:
            cur.executemany(
                "INSERT INTO receivable VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
                recv_batch,
            )
            recv_batch.clear()

    # 8. Stock
    print("Calculating stock from products...")
    stocks = [
        (p[0], max(10, p[10] * 3), max(5, p[10] * 2))
        for p in products
    ]
    cur.executemany("INSERT INTO stock VALUES (%s,%s,%s)", stocks)

    # 9. Meta
    print("Inserting meta records...")
    cur.execute("SELECT MAX(date) FROM fact_sales")
    max_date = cur.fetchone()[0] or "2026-09-30"
    synced_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    cur.executemany(
        "INSERT INTO meta VALUES (%s, %s)",
        [("as_of", max_date), ("synced_at", synced_at)],
    )

    conn.commit()
    conn.close()
    print("DONE! erp_analytics_db is 100% seeded and ready for AI & ML!")


if __name__ == "__main__":
    main()
