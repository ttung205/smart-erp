"""Kho phân tích (read model) của ai-service, nằm trong schema MySQL `erp_analytics_db`.

Job ETL đọc dữ liệu qua endpoint export nội bộ của từng service (export_client), không kết nối database của
service khác. Mỗi lần đồng bộ dựng các bảng `<tên>_new` rồi đổi tên đồng loạt bằng một câu RENAME TABLE
(nguyên tử): người đang hỏi không bao giờ đọc phải kho dựng dở.
"""
from __future__ import annotations

import logging
import time
from contextlib import closing

from simulator.people import REGION_OF

from . import db
from .export_client import ExportClient

log = logging.getLogger("ai.warehouse")

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
        move_id bigint, warehouse_id bigint, balance_after bigint, created_by bigint,
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


def _day(value) -> str | None:
    return str(value)[:10] if value else None


def _dmy_to_iso(value: str | None) -> str | None:
    if value and len(value) >= 10 and value[2] == "/":
        return f"{value[6:10]}-{value[3:5]}-{value[0:2]}"
    return value


def _num(value) -> float | None:
    # Laravel trả cột DECIMAL dưới dạng chuỗi ("100.00"): đổi sang số để còn tính toán (giá vốn = SL x đơn giá vốn)
    return float(value) if value not in (None, "") else None


def _province(address: str | None) -> str | None:
    if not address:
        return None
    last = address.split(",")[-1].strip()
    return last if last in REGION_OF else None


def build(target: db.Connection, source: ExportClient) -> dict[str, int]:
    """Dựng toàn bộ bảng `<tên>_new` trong schema phân tích từ dữ liệu export của các service."""
    # Đọc hết nguồn trước khi đụng vào schema phân tích: service nào lỗi thì dừng sớm, không dựng dở
    products = source.fetch_all("catalog", "products")
    customers = source.fetch_all("catalog", "customers")
    suppliers = source.fetch_all("catalog", "suppliers")
    staff = source.fetch_all("identity", "staff")
    revenue_items = source.fetch_all("sales", "revenue_items")
    orders = source.fetch_all("sales", "orders")
    order_lines = source.fetch_all("sales", "order_lines")
    stock_balances = source.fetch_all("inventory", "stock_balances")
    reservation_balances = source.fetch_all("inventory", "reservation_balances")
    stock_moves = source.fetch_all("inventory", "stock_moves")
    po_lines = source.fetch_all("purchase", "po_lines")
    receivables = source.fetch_all("finance", "receivables")

    for table, columns in TABLES.items():
        target.execute(f"drop table if exists {table}_new")
        target.execute(f"create table {table}_new ({columns}) {TABLE_OPTIONS}")
    counts: dict[str, int] = {}

    def put(table: str, rows: list[tuple]) -> None:
        if rows:
            target.executemany(f"insert into {table}_new values ({','.join('?' * len(rows[0]))})", rows)
        counts[table] = len(rows)

    put("dim_product", [
        (p["id"], p["sku"], p["designation"], p["description"], p["category"], p["brand"], p["com"], p["unit"],
         _num(p["gross_price"]), _num(p["cost_price"]), p["min_stock"], p["status"], p["created_at"], None)
        for p in products])
    rows = []
    for c in customers:
        province = _province(c["address"])
        rows.append((c["id"], c["code"], c["name"], c["customer_type"], province, REGION_OF.get(province),
                     c["address"], c["tax_code"], c["phone"], c["email"], _num(c["credit_limit"]), c["status"],
                     c["created_at"]))
    put("dim_customer", rows)
    put("dim_supplier", [
        (s["id"], s["code"], s["name"], s["supplier_type"], s["supplier_group"], s["payment_terms"], s["email"],
         s["phone"], s["contact_person"], s["status"], s["notes"])
        for s in suppliers])
    put("dim_staff", [(u["id"], u["name"], u["email"], u["roles"]) for u in staff])

    cost = {p["id"]: _num(p["cost_price"]) or 0 for p in products}
    put("fact_sales", [
        (_day(r["issue_date"]), r["invoice_number"], r["order_id"], r["order_number"], r["customer_id"],
         r["product_id"], _num(r["quantity"]), _num(r["unit_price"]), _num(r["subtotal"]), _num(r["vat_amount"]),
         (_num(r["quantity"]) or 0) * cost.get(r["product_id"], 0), r["salesperson_id"],
         1 if r["is_vat_invoiced"] else 0)
        for r in revenue_items])
    put("fact_order", [
        (o["id"], o["order_number"], _dmy_to_iso(_day(o["order_date"])), o["customer_id"], o["status"],
         _num(o["subtotal"]), _num(o["total_amount"]),
         int(o["salesperson"]) if str(o["salesperson"] or "").isdigit() else None, o["quotation_id"])
        for o in orders])
    put("fact_order_line", [
        (line["order_id"], line["product_id"], _num(line["quantity"]), _num(line["delivered_quantity"]),
         _num(line["selling_price"]), _num(line["discount_percent"]), _num(line["subtotal"]))
        for line in order_lines])

    # Tồn bán được của sản phẩm = tổng các kho được bán (endpoint đã lọc can_sell)
    physical: dict[int, int] = {}
    for s in stock_balances:
        physical[s["product_id"]] = physical.get(s["product_id"], 0) + int(s["quantity"])
    available: dict[int, int] = {}
    for r in reservation_balances:
        available[r["product_id"]] = available.get(r["product_id"], 0) + int(r["quantity"])
    put("stock", [(pid, physical.get(pid, 0), available.get(pid, 0)) for pid in set(physical) | set(available)])
    # inventory-service (.NET) bỏ trường null khỏi JSON: biến động do hệ thống tạo không có created_by
    put("stock_move", [
        (_day(m["created_at"]), m["product_id"], m["document_type"], m["document_id"], m["quantity_delta"],
         m["id"], m.get("warehouse_id"), m.get("balance_after"), m.get("created_by"))
        for m in stock_moves])

    # purchase-service (.NET) bỏ trường null khỏi JSON: PO chưa nhận hàng không có first_receipt/last_receipt
    put("fact_po_line", [
        (r["po_id"], r["po_code"], _day(r["order_date"]), _day(r.get("expected_date")), r["status"],
         r["supplier_id"], r["currency"], r["product_id"], r["qty_ordered"], r["qty_received"], _num(r["unit_cost"]),
         _day(r.get("first_receipt")), _day(r.get("last_receipt")))
        for r in po_lines])
    # NCC chính của sản phẩm = NCC của PO gần nhất
    target.execute("update dim_product_new set supplier_id = (select supplier_id from fact_po_line_new l "
                   "where l.product_id = dim_product_new.id order by l.date desc limit 1)")

    put("receivable", [
        (r["customer_id"], r["invoice_number"], _day(r["invoice_date"]), _day(r["due_date"]),
         _num(r["original_amount"]), _num(r["paid_amount"]), _num(r["outstanding_amount"]), r["status"])
        for r in receivables])

    as_of = target.execute("select max(d) from (select max(date) d from fact_sales_new union all "
                           "select max(date) from fact_order_new) x").fetchone()[0]
    target.executemany("insert into meta_new values (?, ?)", [
        ("as_of", as_of or ""), ("synced_at", time.strftime("%Y-%m-%d %H:%M:%S", time.gmtime()))])
    return counts


def _swap(target: db.Connection) -> None:
    """Thay bảng cũ bằng bảng mới trong một câu RENAME TABLE (nguyên tử), rồi xóa bảng cũ."""
    existing = {row[0] for row in target.execute("show tables").fetchall()}
    pairs = []
    for table in TABLES:
        if table in existing:
            pairs.append(f"{table} to {table}_old")
        pairs.append(f"{table}_new to {table}")
    target.execute("rename table " + ", ".join(pairs))
    for table in TABLES:
        target.execute(f"drop table if exists {table}_old")


def _current_sales_rows(target: db.Connection) -> int:
    existing = {row[0] for row in target.execute("show tables").fetchall()}
    return target.execute("select count(*) from fact_sales").fetchone()[0] if "fact_sales" in existing else 0


def sync() -> dict[str, int]:
    """Dựng lại kho phân tích từ export của các service và thay thế bản đang dùng.

    Không thay khi nguồn không có dòng doanh thu nào mà kho đang có dữ liệu: thường là service bán hàng vừa được
    dựng lại DB rỗng, thay vào sẽ xóa sạch màn hình phân tích.
    """
    started = time.time()
    with closing(db.connect()) as target, ExportClient() as source:
        counts = build(target, source)
        current = _current_sales_rows(target)
        if counts.get("fact_sales", 0) == 0 and current > 0:
            log.warning("Bỏ qua thay kho phân tích: nguồn bán hàng không có dòng doanh thu nào, kho hiện có %d dòng "
                        "(correlation_id=%s, service=ai-service, user_id=system)", current, source.correlation_id)
            return {**counts, "swapped": 0}
        _swap(target)
    log.info("Đồng bộ kho phân tích xong sau %.1fs: %s", time.time() - started, counts)
    return {**counts, "swapped": 1}


def connect() -> db.Connection:
    return db.connect(read_only=True)


def is_ready() -> bool:
    """Kho đã được dựng ít nhất một lần chưa (có bảng meta)."""
    try:
        with closing(connect()) as con:
            return con.execute("select count(*) from meta").fetchone()[0] > 0
    except Exception:
        return False
