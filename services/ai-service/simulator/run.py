"""Chạy mô phỏng và nạp dữ liệu vào DB SQLite của các service (môi trường dev).

    python -m simulator.run --dry-run     # chỉ mô phỏng + in thống kê, không ghi DB
    python -m simulator.run               # backup DB rồi thay dữ liệu nghiệp vụ bằng dữ liệu mô phỏng

Giữ nguyên: user/role gốc (identity), hệ thống tài khoản, quỹ, ngân hàng (finance), danh sách kho (inventory).
Xóa và nạp lại: danh mục, khách hàng, NCC, toàn bộ chứng từ bán/mua/kho/công nợ.
"""
from __future__ import annotations

import argparse
import re
import sqlite3
import sys
import time
from collections import Counter
from datetime import datetime
from pathlib import Path

from . import config as C
from .engine import Simulation
from .masterdata import MasterData
from .writers import Writer

SERVICES = Path(__file__).resolve().parents[2]
DB = {
    "identity": SERVICES / "identity-service/database/database.sqlite",
    "catalog": SERVICES / "catalog-service/database/database.sqlite",
    "sales": SERVICES / "sales-service/database/database.sqlite",
    "inventory": SERVICES / "inventory-service/src/Inventory.Api/inventory.db",
    "purchase": SERVICES / "purchase-service/src/Purchase.Api/purchase.db",
    "finance": SERVICES / "finance-service/database/database.sqlite",
}
# Bảng bị xóa sạch trước khi nạp (kể cả bảng không được nạp lại, vd. dữ liệu demo cũ)
CLEAR = {
    "catalog": ["product_price_histories", "customer_contacts", "customers", "suppliers", "products", "brands",
                "categories", "partners", "outbox_events"],
    "sales": ["sale_return_items", "sale_returns", "sale_return_request_items", "sale_return_request_documents",
              "sale_return_requests", "vat_invoice_adjustment_items", "vat_invoice_adjustments",
              "revenue_report_items", "vat_invoice_items", "vat_invoices", "warehouse_dispatches", "invoice_items",
              "invoices", "delivery_request_items", "delivery_requests", "sale_report_items", "sales_order_items",
              "sales_orders", "quotation_items", "quotations", "sales_attachments", "document_sequences",
              "outbox_events"],
    "inventory": ["inventory_stocks_transaction", "inventory_stocks_reservation_transaction", "inventory_stocks",
                  "inventory_stocks_reservation", "outbox_events", "processed_events"],
    "purchase": ["purchase_invoice_items", "purchase_invoices", "goods_receipt_items", "goods_receipts",
                 "purchase_return_items", "purchase_returns", "purchase_order_pr_allocations",
                 "purchase_order_items", "purchase_orders", "purchase_requisition_items", "purchase_requisitions",
                 "supplier_inquiry_attachments", "supplier_inquiry_items", "supplier_inquiries",
                 "item_inquiry_attachments", "item_inquiry_request_items", "item_inquiry_requests",
                 "outbox_events", "processed_events"],
    "finance": ["receivable_allocations", "receivable_adjustments", "general_ledger_entries",
                "finance_voucher_lines", "finance_vouchers", "customer_receivables"],
}
SIMULATED_USER = re.compile(r"^[a-z]+\.[a-z]+\d+@erp\.local$")


def remove_simulated_users() -> None:
    con = sqlite3.connect(DB["identity"])
    ids = [uid for uid, email in con.execute("select id, email from users") if SIMULATED_USER.match(email)]
    if ids:
        marks = ",".join("?" * len(ids))
        con.execute(f"delete from user_roles where user_id in ({marks})", ids)
        con.execute(f"delete from users where id in ({marks})", ids)
        con.commit()
    con.close()


def lookups() -> tuple[dict, dict, dict, str]:
    fin = sqlite3.connect(f"file:{DB['finance']}?mode=ro", uri=True)
    accounts = dict(fin.execute("select code, id from chart_of_accounts"))
    ident = sqlite3.connect(f"file:{DB['identity']}?mode=ro", uri=True)
    roles = {name: rid for rid, name in ident.execute("select id, name from roles")}
    departments = {name: did for did, name in ident.execute("select id, name from departments")}
    password = ident.execute("select password_hash from users where email = 'sale@erp.local'").fetchone()[0]
    return accounts, roles, departments, password


def insert(con: sqlite3.Connection, table: str, rows: list[dict]) -> None:
    if not rows:
        return
    cols = list(dict.fromkeys(k for r in rows for k in r))
    sql = f'insert into "{table}" ({", ".join(cols)}) values ({", ".join("?" * len(cols))})'
    con.executemany(sql, [tuple(r.get(c) for c in cols) for r in rows])


def load(service: str, rows: dict[str, list[dict]]) -> None:
    con = sqlite3.connect(DB[service])
    con.execute("pragma foreign_keys = off")
    try:
        con.execute("begin")
        for table in CLEAR.get(service, []):
            con.execute(f'delete from "{table}"')
        has_seq = con.execute("select 1 from sqlite_master where name = 'sqlite_sequence'").fetchone()
        if has_seq:
            con.execute("delete from sqlite_sequence where name in (%s)" % ",".join("?" * len(CLEAR.get(service, []))),
                        CLEAR.get(service, []))
        for table, data in rows.items():
            insert(con, table, data)
        con.commit()
    except Exception:
        con.rollback()
        raise
    finally:
        con.close()


def copy_db(src: Path, dst: Path) -> None:
    """Sao chép bằng SQLite backup API để lấy cả phần dữ liệu còn nằm trong file WAL."""
    with sqlite3.connect(f"file:{src}?mode=ro", uri=True) as s, sqlite3.connect(dst) as d:
        s.backup(d)


def backup() -> Path:
    target = Path(__file__).resolve().parent / ".backup" / datetime.now().strftime("%Y%m%d-%H%M%S")
    target.mkdir(parents=True)
    for name, path in DB.items():
        copy_db(path, target / f"{name}{path.suffix}")
    return target


def summary(sim: Simulation) -> None:
    md = sim.md
    revenue = sum(v.subtotal for v in sim.vat_invoices)
    by_type = Counter()
    for v in sim.vat_invoices:
        by_type[v.sales_invoice.delivery.order.customer.type] += v.subtotal
    by_month = Counter()
    for v in sim.vat_invoices:
        by_month[v.day.strftime("%Y-%m")] += v.subtotal
    print(f"Sản phẩm {len(md.products)} | NCC {len(md.suppliers)} | Khách hàng {len(md.customers)} "
          f"| Nhân viên mới {sum(s.is_new for s in md.staff)}")
    print(f"Báo giá {len(sim.quotations)} | Đơn bán {len(sim.orders)} | Phiếu xuất {len(sim.sales_invoices)} "
          f"| HĐ VAT {len(sim.vat_invoices)} | Phiếu thu {len(sim.receipts)}")
    print(f"PR {len(sim.requisitions)} | PO {len(sim.purchase_orders)} | Nhập kho {len(sim.goods_receipts)} "
          f"| HĐ mua {len(sim.purchase_invoices)}")
    print(f"Doanh thu (trước VAT): {revenue / 1e9:,.1f} tỷ")
    for t, v in by_type.most_common():
        print(f"  {t:<20} {v / 1e9:7.1f} tỷ ({v / revenue:5.1%})")
    print("Theo tháng:", "  ".join(f"{m[2:]}:{v / 1e9:.1f}" for m, v in sorted(by_month.items())))
    outstanding = sum(r.outstanding for r in sim.receivables)
    overdue = sum(r.outstanding for r in sim.receivables if r.due < C.END_DATE)
    print(f"Công nợ cuối kỳ: {outstanding / 1e9:.1f} tỷ (quá hạn {overdue / 1e9:.1f} tỷ)")
    stock_value = sum(sim.on_hand[p.id] * p.cost_price for p in md.products)
    print(f"Tồn kho cuối kỳ: {sum(sim.on_hand.values()):,} cái, giá trị {stock_value / 1e9:.1f} tỷ")
    statuses = Counter(o.status for o in sim.orders)
    print("Trạng thái đơn:", dict(statuses))
    pending = sum(sim.unshipped(line) * line.price for o in sim.orders
                  if o.status not in ("CANCELLED", "REJECTED") for line in o.lines)
    print(f"Đơn còn chờ giao cuối kỳ: {pending / 1e9:.1f} tỷ")
    lost = sum(sim.lost_demand[p.id] * p.gross_price for p in md.products)
    print(f"Nhu cầu không đáp ứng được (hết hàng): ~{lost / 1e9:.1f} tỷ theo giá niêm yết")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--dry-run", action="store_true")
    args = parser.parse_args()

    t0 = time.time()
    if not args.dry_run:
        print("Backup ->", backup())
        remove_simulated_users()
    md = MasterData(DB["identity"])
    sim = Simulation(md)
    sim.run()
    print(f"Mô phỏng xong sau {time.time() - t0:.0f}s")
    summary(sim)
    if args.dry_run:
        return
    accounts, roles, departments, password = lookups()
    writer = Writer(sim, accounts, roles, departments, password)
    for service, build in (("identity", writer.identity), ("catalog", writer.catalog), ("sales", writer.sales),
                           ("inventory", writer.inventory), ("purchase", writer.purchase),
                           ("finance", writer.finance)):
        rows = build()
        load(service, rows)
        print(f"[{service}] " + ", ".join(f"{t}={len(r)}" for t, r in rows.items()))
    print(f"Hoàn tất sau {time.time() - t0:.0f}s")


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    main()
