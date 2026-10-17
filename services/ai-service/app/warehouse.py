"""Kho phân tích (read model) của ai-service.

Job ETL đọc chỉ-đọc từ DB của các service, làm phẳng thành các bảng dễ truy vấn rồi thay thế
analytics.db bằng SQLite backup API (người đang hỏi không bao giờ đọc phải kho dựng dở).
"""
from __future__ import annotations

import logging
import sqlite3
import time
from contextlib import closing

from simulator.people import REGION_OF

from . import config

log = logging.getLogger("ai.warehouse")

SCHEMA = """
create table dim_product (id integer primary key, sku text, model text, name text, category text, brand text,
    origin text, unit text, gross_price real, cost_price real, min_stock integer, status text, created_at text,
    supplier_id integer);
create table dim_customer (id integer primary key, code text, name text, type text, province text, region text,
    address text, tax_code text, phone text, email text, credit_limit real, status text, created_at text);
create table dim_supplier (id integer primary key, code text, name text, supplier_type text, supplier_group text,
    payment_terms text, email text, phone text, contact_person text, status text, notes text);
create table dim_staff (id integer primary key, name text, email text, roles text);
create table fact_sales (date text, invoice_number text, order_id integer, order_number text, customer_id integer,
    product_id integer, quantity real, unit_price real, revenue real, vat real, cost real, salesperson_id integer,
    vat_invoiced integer);
create table fact_order (id integer primary key, order_number text, date text, customer_id integer, status text,
    subtotal real, total real, salesperson_id integer, quotation_id integer);
create table fact_order_line (order_id integer, product_id integer, quantity real, delivered real, price real,
    discount real, subtotal real);
create table stock (product_id integer primary key, on_hand integer, available integer);
create table stock_move (date text, product_id integer, doc_type text, doc_id text, delta integer);
create table fact_po_line (po_id integer, po_code text, date text, expected_date text, status text,
    supplier_id integer, currency text, product_id integer, qty_ordered integer, qty_received integer,
    unit_cost real, first_receipt text, last_receipt text);
create table receivable (customer_id integer, invoice_number text, invoice_date text, due_date text,
    amount real, paid real, outstanding real, status text);
create table meta (key text primary key, value text);
create index ix_sales_date on fact_sales(date);
create index ix_sales_product on fact_sales(product_id);
create index ix_sales_customer on fact_sales(customer_id);
create index ix_move_product on stock_move(product_id, date);
create index ix_po_product on fact_po_line(product_id);
create index ix_recv_customer on receivable(customer_id);
"""


def _src(name: str) -> sqlite3.Connection:
    return sqlite3.connect(f"file:{config.SOURCE_DB[name]}?mode=ro", uri=True)


def _dmy_to_iso(value: str | None) -> str | None:
    if value and len(value) >= 10 and value[2] == "/":
        return f"{value[6:10]}-{value[3:5]}-{value[0:2]}"
    return value


def _province(address: str | None) -> str | None:
    if not address:
        return None
    last = address.split(",")[-1].strip()
    return last if last in REGION_OF else None


def build(target: sqlite3.Connection) -> dict[str, int]:
    target.executescript(SCHEMA)
    counts: dict[str, int] = {}

    def put(table: str, rows: list[tuple]) -> None:
        if rows:
            target.executemany(f"insert into {table} values ({','.join('?' * len(rows[0]))})", rows)
        counts[table] = len(rows)

    with closing(_src("catalog")) as c:
        put("dim_product", [r + (None,) for r in c.execute(
            "select p.id, p.sku, p.designation, p.description, cg.name, b.name, p.com, p.unit, p.gross_price, "
            "p.cost_price, p.min_stock, p.status, p.created_at from products p "
            "left join categories cg on cg.id = p.category_id left join brands b on b.id = p.brand_id")])
        rows = []
        for r in c.execute("select id, code, name, customer_type, address, tax_code, phone, email, credit_limit, "
                           "status, created_at from customers"):
            province = _province(r[4])
            rows.append((r[0], r[1], r[2], r[3], province, REGION_OF.get(province), r[4], r[5], r[6], r[7], r[8],
                         r[9], r[10]))
        put("dim_customer", rows)
        put("dim_supplier", c.execute(
            "select id, code, name, supplier_type, supplier_group, payment_terms, email, phone, contact_person, "
            "status, notes from suppliers").fetchall())

    with closing(_src("identity")) as c:
        put("dim_staff", c.execute(
            "select u.id, u.name, u.email, group_concat(r.name, ', ') from users u left join user_roles ur "
            "on ur.user_id = u.id left join roles r on r.id = ur.role_id group by u.id").fetchall())

    cost = dict(target.execute("select id, cost_price from dim_product"))
    with closing(_src("sales")) as c:
        put("fact_sales", [
            (r[0], r[1], r[2], r[3], r[4], r[5], r[6], r[7], r[8], r[9], (r[6] or 0) * (cost.get(r[5]) or 0), r[10], r[11])
            for r in c.execute(
                "select issue_date, invoice_number, order_id, order_number, customer_id, product_id, quantity, "
                "unit_price, subtotal, vat_amount, salesperson_id, is_vat_invoiced from revenue_report_items")])
        put("fact_order", [
            (r[0], r[1], _dmy_to_iso(r[2]), r[3], r[4], r[5], r[6], int(r[7]) if str(r[7] or "").isdigit() else None, r[8])
            for r in c.execute("select id, order_number, order_date, customer_id, status, subtotal, total_amount, "
                               "coalesce(assigned_to, created_by), quotation_id from sales_orders")])
        put("fact_order_line", c.execute(
            "select order_id, product_id, quantity, delivered_quantity, selling_price, discount_percent, subtotal "
            "from sales_order_items").fetchall())

    with closing(_src("inventory")) as c:
        physical = dict(c.execute("select product_id, sum(quantity) from inventory_stocks s join warehouses w "
                                  "on w.id = s.warehouse_id where w.can_sell = 1 group by product_id"))
        available = dict(c.execute("select product_id, sum(quantity) from inventory_stocks_reservation s join warehouses w "
                                   "on w.id = s.warehouse_id where w.can_sell = 1 group by product_id"))
        put("stock", [(pid, int(physical.get(pid) or 0), int(available.get(pid) or 0))
                      for pid in set(physical) | set(available)])
        put("stock_move", c.execute(
            "select substr(created_at, 1, 10), product_id, document_type, document_id, quantity_delta "
            "from inventory_stocks_transaction").fetchall())

    with closing(_src("purchase")) as c:
        put("fact_po_line", c.execute(
            "select o.id, o.code, substr(o.order_date, 1, 10), substr(o.expected_delivery_date, 1, 10), o.status, "
            "o.supplier_id, o.currency, i.product_id, i.quantity_ordered, i.quantity_received, cast(i.unit_cost as real), "
            "(select substr(min(g.received_at), 1, 10) from goods_receipts g where g.purchase_order_id = o.id), "
            "(select substr(max(g.received_at), 1, 10) from goods_receipts g where g.purchase_order_id = o.id) "
            "from purchase_orders o join purchase_order_items i on i.purchase_order_id = o.id").fetchall())
    # NCC chính của sản phẩm = NCC của PO gần nhất
    target.execute("update dim_product set supplier_id = (select supplier_id from fact_po_line l "
                   "where l.product_id = dim_product.id order by l.date desc limit 1)")

    with closing(_src("finance")) as c:
        put("receivable", c.execute(
            "select customer_id, invoice_number, invoice_date, due_date, original_amount, paid_amount, "
            "outstanding_amount, status from customer_receivables").fetchall())

    as_of = target.execute("select max(d) from (select max(date) d from fact_sales union all "
                           "select max(date) from fact_order)").fetchone()[0]
    target.executemany("insert into meta values (?, ?)", [
        ("as_of", as_of or ""), ("synced_at", time.strftime("%Y-%m-%d %H:%M:%S"))])
    target.commit()
    return counts


def sync() -> dict[str, int]:
    """Dựng lại kho và thay thế analytics.db."""
    started = time.time()
    config.ANALYTICS_DB.parent.mkdir(parents=True, exist_ok=True)
    with closing(sqlite3.connect(":memory:")) as mem:
        counts = build(mem)
        with closing(sqlite3.connect(config.ANALYTICS_DB)) as live:
            mem.backup(live)
    log.info("Đồng bộ kho phân tích xong sau %.1fs: %s", time.time() - started, counts)
    return counts


def connect() -> sqlite3.Connection:
    con = sqlite3.connect(f"file:{config.ANALYTICS_DB}?mode=ro", uri=True)
    con.row_factory = sqlite3.Row
    return con
