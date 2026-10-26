"""Kho phân tích (read model) của ai-service.

Job ETL đọc chỉ-đọc từ DB của các service, làm phẳng thành các bảng dễ truy vấn. Mỗi lần đồng bộ dựng các
bảng `<tên>_new` rồi đổi tên đồng loạt bằng một câu RENAME TABLE (nguyên tử): người đang hỏi không bao giờ
đọc phải kho dựng dở.
"""
from __future__ import annotations

import logging
import time
from contextlib import closing

from simulator.people import REGION_OF

from . import config, db

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


def _src(name: str) -> db.Connection:
    return db.connect(config.SOURCE_DB[name], read_only=True)


def _rows(con: db.Connection, sql: str) -> list[tuple]:
    """Đọc nguồn và chuẩn hóa ngày/Decimal về chuỗi/số, như kho phân tích vẫn lưu."""
    return [tuple(db.to_text(v) for v in row) for row in con.execute(sql).fetchall()]


def _day(value) -> str | None:
    return str(value)[:10] if value else None


def _dmy_to_iso(value: str | None) -> str | None:
    if value and len(value) >= 10 and value[2] == "/":
        return f"{value[6:10]}-{value[3:5]}-{value[0:2]}"
    return value


def _province(address: str | None) -> str | None:
    if not address:
        return None
    last = address.split(",")[-1].strip()
    return last if last in REGION_OF else None


def build(target: db.Connection) -> dict[str, int]:
    """Dựng toàn bộ bảng `<tên>_new` trong schema phân tích."""
    for table, columns in TABLES.items():
        target.execute(f"drop table if exists {table}_new")
        target.execute(f"create table {table}_new ({columns}) {TABLE_OPTIONS}")
    counts: dict[str, int] = {}

    def put(table: str, rows: list[tuple]) -> None:
        if rows:
            target.executemany(f"insert into {table}_new values ({','.join('?' * len(rows[0]))})", rows)
        counts[table] = len(rows)

    with closing(_src("catalog")) as c:
        put("dim_product", [r + (None,) for r in _rows(c, (
            "select p.id, p.sku, p.designation, p.description, cg.name, b.name, p.com, p.unit, p.gross_price, "
            "p.cost_price, p.min_stock, p.status, p.created_at from products p "
            "left join categories cg on cg.id = p.category_id left join brands b on b.id = p.brand_id"))])
        rows = []
        for r in _rows(c, "select id, code, name, customer_type, address, tax_code, phone, email, credit_limit, "
                          "status, created_at from customers"):
            province = _province(r[4])
            rows.append((r[0], r[1], r[2], r[3], province, REGION_OF.get(province), r[4], r[5], r[6], r[7], r[8],
                         r[9], r[10]))
        put("dim_customer", rows)
        put("dim_supplier", _rows(c, (
            "select id, code, name, supplier_type, supplier_group, payment_terms, email, phone, contact_person, "
            "status, notes from suppliers")))

    with closing(_src("identity")) as c:
        put("dim_staff", _rows(c, (
            "select u.id, u.name, u.email, group_concat(r.name separator ', ') from users u left join user_roles ur "
            "on ur.user_id = u.id left join roles r on r.id = ur.role_id group by u.id, u.name, u.email")))

    cost = dict(target.execute("select id, cost_price from dim_product_new").fetchall())
    with closing(_src("sales")) as c:
        put("fact_sales", [
            (_day(r[0]), r[1], r[2], r[3], r[4], r[5], r[6], r[7], r[8], r[9],
             (r[6] or 0) * (cost.get(r[5]) or 0), r[10], r[11])
            for r in _rows(c, (
                "select issue_date, invoice_number, order_id, order_number, customer_id, product_id, quantity, "
                "unit_price, subtotal, vat_amount, salesperson_id, is_vat_invoiced from revenue_report_items"))])
        put("fact_order", [
            (r[0], r[1], _dmy_to_iso(_day(r[2])), r[3], r[4], r[5], r[6],
             int(r[7]) if str(r[7] or "").isdigit() else None, r[8])
            for r in _rows(c, "select id, order_number, order_date, customer_id, status, subtotal, total_amount, "
                              "coalesce(assigned_to, created_by), quotation_id from sales_orders")])
        put("fact_order_line", _rows(c, (
            "select order_id, product_id, quantity, delivered_quantity, selling_price, discount_percent, subtotal "
            "from sales_order_items")))

    with closing(_src("inventory")) as c:
        physical = dict(_rows(c, "select product_id, sum(quantity) from inventory_stocks s join warehouses w "
                                 "on w.id = s.warehouse_id where w.can_sell = 1 group by product_id"))
        available = dict(_rows(c, "select product_id, sum(quantity) from inventory_stocks_reservation s "
                                  "join warehouses w on w.id = s.warehouse_id where w.can_sell = 1 group by product_id"))
        put("stock", [(pid, int(physical.get(pid) or 0), int(available.get(pid) or 0))
                      for pid in set(physical) | set(available)])
        put("stock_move", _rows(c, (
            "select substr(created_at, 1, 10), product_id, document_type, document_id, quantity_delta "
            "from inventory_stocks_transaction")))

    with closing(_src("purchase")) as c:
        put("fact_po_line", _rows(c, (
            "select o.id, o.code, substr(o.order_date, 1, 10), substr(o.expected_delivery_date, 1, 10), o.status, "
            "o.supplier_id, o.currency, i.product_id, i.quantity_ordered, i.quantity_received, "
            "cast(i.unit_cost as double), "
            "(select substr(min(g.received_at), 1, 10) from goods_receipts g where g.purchase_order_id = o.id), "
            "(select substr(max(g.received_at), 1, 10) from goods_receipts g where g.purchase_order_id = o.id) "
            "from purchase_orders o join purchase_order_items i on i.purchase_order_id = o.id")))
    # NCC chính của sản phẩm = NCC của PO gần nhất
    target.execute("update dim_product_new set supplier_id = (select supplier_id from fact_po_line_new l "
                   "where l.product_id = dim_product_new.id order by l.date desc limit 1)")

    with closing(_src("finance")) as c:
        put("receivable", _rows(c, (
            "select customer_id, invoice_number, invoice_date, due_date, original_amount, paid_amount, "
            "outstanding_amount, status from customer_receivables")))

    as_of = target.execute("select max(d) from (select max(date) d from fact_sales_new union all "
                           "select max(date) from fact_order_new) x").fetchone()[0]
    target.executemany("insert into meta_new values (?, ?)", [
        ("as_of", as_of or ""), ("synced_at", time.strftime("%Y-%m-%d %H:%M:%S"))])
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


def sync() -> dict[str, int]:
    """Dựng lại kho phân tích và thay thế bản đang dùng."""
    started = time.time()
    with closing(db.connect()) as target:
        counts = build(target)
        _swap(target)
    log.info("Đồng bộ kho phân tích xong sau %.1fs: %s", time.time() - started, counts)
    return counts


def connect() -> db.Connection:
    return db.connect(read_only=True)


def is_ready() -> bool:
    """Kho đã được dựng ít nhất một lần chưa (có bảng meta)."""
    try:
        with closing(connect()) as con:
            return con.execute("select count(*) from meta").fetchone()[0] > 0
    except Exception:
        return False
