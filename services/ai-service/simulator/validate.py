"""Kiểm tra tính nhất quán của dữ liệu giữa các service (đọc trực tiếp DB sau khi nạp).

    python -m simulator.validate
"""
from __future__ import annotations

import sqlite3
import sys
from collections import defaultdict
from pathlib import Path

from .run import DB


class Checker:
    def __init__(self, paths: dict[str, Path]):
        self.db = {k: sqlite3.connect(f"file:{p}?mode=ro", uri=True) for k, p in paths.items()}
        self.failed = 0

    def q(self, service: str, sql: str):
        return self.db[service].execute(sql).fetchall()

    def check(self, title: str, problems: list) -> None:
        if problems:
            self.failed += 1
            print(f"✗ {title}: {len(problems)} lỗi, ví dụ {problems[:3]}")
        else:
            print(f"✓ {title}")

    def run(self) -> int:
        # --- Tham chiếu chéo tới catalog
        products = {r[0] for r in self.q("catalog", "select id from products")}
        customers = {r[0] for r in self.q("catalog", "select id from customers")}
        suppliers = {r[0] for r in self.q("catalog", "select id from suppliers")}
        refs = [
            ("sales", "select distinct product_id from sales_order_items", products),
            ("sales", "select distinct customer_id from sales_orders", customers),
            ("inventory", "select distinct product_id from inventory_stocks_transaction", products),
            ("purchase", "select distinct product_id from purchase_order_items", products),
            ("purchase", "select distinct supplier_id from purchase_orders", suppliers),
            ("finance", "select distinct customer_id from customer_receivables", customers),
        ]
        for service, sql, valid in refs:
            self.check(f"{service}: tham chiếu hợp lệ ({sql.split('from')[1].strip()})",
                       [r[0] for r in self.q(service, sql) if r[0] not in valid])

        # --- Thẻ kho khớp tồn
        for table, ledger in (("inventory_stocks", "inventory_stocks_transaction"),
                              ("inventory_stocks_reservation", "inventory_stocks_reservation_transaction")):
            sums = dict(self.q("inventory", f"select product_id, sum(quantity_delta) from {ledger} group by product_id"))
            self.check(f"{table} = tổng phát sinh thẻ kho",
                       [(p, q, sums.get(p, 0)) for p, q in self.q("inventory", f"select product_id, quantity from {table}")
                        if q != sums.get(p, 0)])
            self.check(f"{ledger}: không có số dư âm",
                       self.q("inventory", f"select id, product_id, balance_after from {ledger} where balance_after < 0"))
            chain = self.q("inventory", f"select product_id, balance_before, balance_after, quantity_delta from {ledger} "
                                        "order by product_id, created_at, id")
            last: dict[int, int] = {}
            broken = []
            for pid, before, after, delta in chain:
                if last.get(pid, 0) != before or before + delta != after:
                    broken.append(pid)
                last[pid] = after
            self.check(f"{ledger}: số dư liên tục theo thời gian", broken)
        phys = dict(self.q("inventory", "select product_id, quantity from inventory_stocks"))
        avail = dict(self.q("inventory", "select product_id, quantity from inventory_stocks_reservation"))
        self.check("tồn khả dụng ≤ tồn thực tế", [p for p in phys if avail.get(p, 0) > phys[p]])

        # --- Bán hàng ↔ kho
        issued = defaultdict(int)
        for pid, qty in self.q("sales", "select ii.product_id, ii.quantity from invoice_items ii join invoices i "
                                        "on i.id = ii.invoice_id where i.inventory_issue_status = 'ISSUED'"):
            issued[pid] += qty
        notes = dict(self.q("inventory", "select product_id, -sum(quantity_delta) from inventory_stocks_transaction "
                                         "where document_type = 'DELIVERY_NOTE' group by product_id"))
        self.check("xuất kho (inventory) = phiếu xuất đã ghi sổ (sales)",
                   [(p, issued[p], notes.get(p, 0)) for p in set(issued) | set(notes) if issued[p] != notes.get(p, 0)])
        delivered = defaultdict(int)
        for oid, qty in self.q("sales", "select ii.order_item_id, ii.quantity from invoice_items ii join invoices i "
                                        "on i.id = ii.invoice_id where i.inventory_issue_status = 'ISSUED'"):
            delivered[oid] += qty
        self.check("đã giao trên đơn = tổng phiếu xuất",
                   [(i, d, delivered[i]) for i, d in self.q("sales", "select id, delivered_quantity from sales_order_items")
                    if d != delivered[i]])
        self.check("đơn COMPLETED đã giao đủ",
                   self.q("sales", "select o.id from sales_orders o join sales_order_items i on i.order_id = o.id "
                                   "where o.status = 'COMPLETED' group by o.id "
                                   "having sum(i.delivered_quantity) < sum(i.quantity)"))
        self.check("doanh thu chỉ ghi cho phiếu đã xuất kho",
                   self.q("sales", "select r.id from revenue_report_items r join invoices i on i.id = r.invoice_id "
                                   "where i.inventory_issue_status != 'ISSUED'"))

        # --- Mua hàng ↔ kho
        received = dict(self.q("purchase", "select product_id, sum(quantity_received) from goods_receipt_items group by product_id"))
        receipts = dict(self.q("inventory", "select product_id, sum(quantity_delta) from inventory_stocks_transaction "
                                            "where document_type = 'PURCHASE_RECEIPT' and document_id like 'GR-%' "
                                            "group by product_id"))
        self.check("nhập kho (inventory) = phiếu nhập (purchase)",
                   [(p, received.get(p), receipts.get(p)) for p in set(received) | set(receipts)
                    if (received.get(p) or 0) != (receipts.get(p) or 0)])
        self.check("PO Received/Completed đã nhận đủ",
                   self.q("purchase", "select o.id from purchase_orders o join purchase_order_items i "
                                      "on i.purchase_order_id = o.id where o.status in ('Received', 'Completed') "
                                      "group by o.id having sum(i.quantity_received) < sum(i.quantity_ordered) - 5"))

        # --- Hóa đơn VAT ↔ công nợ ↔ phiếu thu ↔ sổ cái
        vat = dict(self.q("sales", "select id, total_amount from vat_invoices"))
        rec = {r[0]: r[1:] for r in self.q("finance", "select source_vat_invoice_id, original_amount, paid_amount, "
                                                      "outstanding_amount from customer_receivables")}
        self.check("mỗi HĐ VAT có đúng 1 công nợ, số tiền khớp",
                   [v for v, total in vat.items() if v not in rec or abs(rec[v][0] - total) > 1])
        alloc = dict(self.q("finance", "select receivable_id, sum(amount) from receivable_allocations group by receivable_id"))
        self.check("đã thu trên công nợ = tổng phân bổ phiếu thu",
                   [(i, paid, alloc.get(i, 0)) for i, paid in self.q("finance", "select id, paid_amount from customer_receivables")
                    if abs(paid - alloc.get(i, 0)) > 1])
        self.check("tiền phiếu thu = tổng phân bổ",
                   self.q("finance", "select v.id from finance_vouchers v left join receivable_allocations a "
                                     "on a.voucher_id = v.id group by v.id having abs(v.total_amount - sum(a.amount)) > 1"))
        gl = self.q("finance", "select (select coalesce(sum(amount), 0) from general_ledger_entries g join chart_of_accounts a "
                               "on a.id = g.debit_account_id where a.code = '1311') - "
                               "(select coalesce(sum(amount), 0) from general_ledger_entries g join chart_of_accounts a "
                               "on a.id = g.credit_account_id where a.code = '1311')")[0][0]
        outstanding = self.q("finance", "select sum(outstanding_amount) from customer_receivables")[0][0] or 0
        self.check("số dư TK 1311 trên sổ cái = tổng công nợ còn lại", [] if abs(gl - outstanding) < 10 else [(gl, outstanding)])

        # --- Trình tự thời gian
        self.check("phiếu xuất tạo sau khi duyệt đơn",
                   self.q("sales", "select i.id from invoices i join delivery_requests d on d.id = i.delivery_request_id "
                                   "join sales_orders o on o.id = d.order_id where i.created_at < o.created_at"))
        self.check("HĐ VAT lập sau khi xuất kho",
                   self.q("sales", "select v.id from vat_invoices v join invoices i on i.id = v.sales_invoice_id "
                                   "where v.created_at < i.posted_at"))
        print("Kết quả:", "ĐẠT" if not self.failed else f"{self.failed} nhóm kiểm tra lỗi")
        return self.failed


def main(paths: dict[str, Path] | None = None) -> int:
    sys.stdout.reconfigure(encoding="utf-8")
    return Checker(paths or DB).run()


if __name__ == "__main__":
    sys.exit(main())
