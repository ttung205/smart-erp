"""Mô phỏng hoạt động hằng ngày của nhà phân phối trong 1 năm.

Mọi chứng từ sinh ra theo đúng trình tự nghiệp vụ của app:
  Bán:  (báo giá) → đơn bán → duyệt → yêu cầu giao → phiếu xuất (giữ chỗ) → xuất kho
        → hóa đơn VAT → công nợ → phiếu thu
  Mua:  rà soát tồn (thứ 2) → đề nghị mua → đơn mua → nhập kho → hóa đơn mua
Tồn kho chỉ thay đổi qua StockMove nên luôn khớp với chứng từ.
"""
from __future__ import annotations

import math
import random
from collections import defaultdict, deque
from datetime import date, datetime, time, timedelta

from . import config as C
from .masterdata import MasterData
from .models import (
    Customer, Delivery, DeliveryLine, GoodsReceipt, InvoiceLine, Line, Order, POLine, PRLine, PriceChange,
    Product, PurchaseInvoice, PurchaseOrder, Quotation, Receipt, Receivable, Requisition, SalesInvoice,
    StockMove, VatInvoice,
)

MAIN_WAREHOUSE_ID = 1
CARRIERS = ["Viettel Post", "Giao Hàng Nhanh", "Nhất Tín Logistics", "J&T Express", "Xe khách gửi hàng"]


def is_workday(d: date) -> bool:
    return d.weekday() != 6 and d not in C.HOLIDAYS


def add_workdays(d: date, n: int) -> date:
    while n > 0:
        d += timedelta(days=1)
        if is_workday(d):
            n -= 1
    return d


class Counter:
    def __init__(self):
        self.ids: dict[str, int] = defaultdict(int)
        self.seq: dict[str, int] = defaultdict(int)

    def id(self, kind: str) -> int:
        self.ids[kind] += 1
        return self.ids[kind]

    def next(self, key: str) -> int:
        self.seq[key] += 1
        return self.seq[key]


class Simulation:
    def __init__(self, md: MasterData, seed: int = C.SEED):
        self.md = md
        self.rng = random.Random(seed)
        self.n = Counter()
        self.today = C.START_DATE

        self.on_hand: dict[int, int] = defaultdict(int)
        self.available: dict[int, int] = defaultdict(int)
        self.on_order: dict[int, int] = defaultdict(int)
        self.backlog: dict[int, deque] = defaultdict(deque)     # product_id -> (Line, Order)
        self.open_po_lines: dict[int, list[POLine]] = defaultdict(list)
        self.sold_by_day: dict[int, dict[date, int]] = defaultdict(lambda: defaultdict(int))
        self.lost_by_day: dict[int, dict[date, int]] = defaultdict(lambda: defaultdict(int))
        self.lost_demand: dict[int, int] = defaultdict(int)
        self.events: dict[date, list] = defaultdict(list)

        self.quotations: list[Quotation] = []
        self.orders: list[Order] = []
        self.deliveries: list[Delivery] = []
        self.sales_invoices: list[SalesInvoice] = []
        self.vat_invoices: list[VatInvoice] = []
        self.receivables: list[Receivable] = []
        self.receipts: list[Receipt] = []
        self.requisitions: list[Requisition] = []
        self.purchase_orders: list[PurchaseOrder] = []
        self.goods_receipts: list[GoodsReceipt] = []
        self.purchase_invoices: list[PurchaseInvoice] = []
        self.physical_moves: list[StockMove] = []
        self.available_moves: list[StockMove] = []
        self.price_changes: list[PriceChange] = []
        self.open_receivables: dict[int, list[Receivable]] = defaultdict(list)
        self.order_numbers_taken: set[str] = set()
        self.retail_pool: list[Customer] = []

    # ------------------------------------------------------------------ utils
    def at(self, d: date, start: float = 8.0, end: float = 17.5) -> datetime:
        hours = self.rng.uniform(start, end)
        return datetime.combine(d, time(int(hours), int((hours % 1) * 60), self.rng.randint(0, 59)))

    def later(self, t: datetime, min_minutes: int, max_minutes: int) -> datetime:
        return t + timedelta(minutes=self.rng.randint(min_minutes, max_minutes))

    def schedule(self, d: date, fn, *args) -> None:
        self.events[d].append((fn, args))

    def staff(self, role: str, region: str | None = None):
        people = [s for s in self.md.staff if s.role == role and (region is None or s.region in (region, None))]
        return self.rng.choice(people or [s for s in self.md.staff if s.role == role])

    def move(self, kind: str, at: datetime, product: Product, doc_type: str, doc_id: str, delta: int,
             notes: str, user_id: int) -> None:
        book = self.on_hand if kind == "physical" else self.available
        before = book[product.id]
        book[product.id] = before + delta
        target = self.physical_moves if kind == "physical" else self.available_moves
        target.append(StockMove(at, MAIN_WAREHOUSE_ID, product.id, doc_type, doc_id, delta, before,
                                before + delta, notes, user_id))

    # ------------------------------------------------------------ demand model
    def month_factor(self, category: str, d: date) -> float:
        return C.SEASONALITY.get(category, C.DEFAULT_SEASONALITY)[d.month - 1]

    def tet_factor(self, d: date) -> float:
        days = (C.TET_DATE - d).days
        if 8 <= days <= 35:
            return 1.35
        if 4 <= days <= 7:
            return 0.8
        if -14 <= days <= -6:
            return 0.5
        return 1.0

    def lifecycle(self, p: Product, d: date) -> float:
        if d < p.available_from or (p.discontinued_on and d >= p.discontinued_on):
            return 0.0
        factor = 1.0
        if p.decay:
            progress = (d - C.START_DATE).days / (C.END_DATE - C.START_DATE).days
            factor = 1.0 - 0.7 * progress
        weeks_live = (d - p.available_from).days / 7
        if p.available_from > C.START_DATE and weeks_live < 6:
            factor *= 0.4 + 0.1 * weeks_live              # model mới tăng dần
        return factor

    def category_weights(self, customer: Customer, d: date) -> dict[str, float]:
        base = C.PROJECT_CATEGORIES if customer.type == "Project / Business" else C.CATEGORY_SHARE
        weights = {}
        for cat, share in base.items():
            if cat not in self.md.products_by_category:
                continue
            region = C.REGION_CATEGORY_FACTOR.get(cat, {}).get(customer.region, 1.0)
            weights[cat] = share * self.month_factor(cat, d) * region * customer.affinity.get(cat, 1.0)
        return weights

    def season_multiplier(self, customer: Customer, d: date) -> float:
        weights = self.category_weights(customer, d)
        flat = sum(share * C.REGION_CATEGORY_FACTOR.get(cat, {}).get(customer.region, 1.0) * customer.affinity.get(cat, 1.0)
                   for cat, share in C.CATEGORY_SHARE.items() if cat in weights)
        return sum(weights.values()) / flat if flat else 1.0

    def pick_product(self, category: str, d: date) -> Product | None:
        products = self.md.products_by_category[category]
        weights = [p.popularity * self.lifecycle(p, d) for p in products]
        if not any(weights):
            return None
        return self.rng.choices(products, weights=weights)[0]

    def round_qty(self, qty: float, product: Product) -> int:
        qty = max(1, round(qty))
        if qty >= 10 and product.pack > 1:
            qty = max(product.pack, round(qty / product.pack) * product.pack)
        return int(qty)

    def unit_price(self, product: Product, discount: float) -> float:
        return float(round(product.gross_price * (1 - discount / 100), -3))

    def build_retail_lines(self, customer: Customer, d: date) -> list[Line]:
        """Khách lẻ mua 1-2 món: chọn ngành theo tỷ trọng số lượng (doanh thu / giá bình quân)."""
        weights = {cat: w / self.md.avg_price[cat] for cat, w in self.category_weights(customer, d).items()}
        cats, cw = zip(*weights.items())
        lines: dict[int, Line] = {}
        for _ in range(1 if self.rng.random() < 0.85 else 2):
            product = self.pick_product(self.rng.choices(cats, weights=cw)[0], d)
            if product and product.id not in lines:
                discount = customer.discount
                lines[product.id] = Line(0, product, 1 if self.rng.random() < 0.9 else 2, product.gross_price,
                                         discount, self.unit_price(product, discount), product.vat)
        return list(lines.values())

    def build_lines(self, customer: Customer, d: date, value_target: float, max_lines: int) -> list[Line]:
        if customer.type == "Retail":
            return self.build_retail_lines(customer, d)
        weights = self.category_weights(customer, d)
        if not weights:
            return []
        cats, cw = zip(*weights.items())
        chosen: dict[int, Line] = {}
        total, attempts = 0.0, 0
        while total < value_target and len(chosen) < max_lines and attempts < max_lines * 4:
            attempts += 1
            product = self.pick_product(self.rng.choices(cats, weights=cw)[0], d)
            if not product:
                continue
            discount = customer.discount + (self.rng.choice([0, 0, 0, 1, 2]) if customer.type != "Retail" else 0)
            price = self.unit_price(product, discount)
            line_value = value_target * self.rng.uniform(0.08, 0.35)
            qty = self.round_qty(line_value / price, product)
            if product.id in chosen:
                chosen[product.id].qty += qty
            else:
                chosen[product.id] = Line(0, product, qty, product.gross_price, discount, price, product.vat)
            total += qty * price
        return list(chosen.values())

    # ----------------------------------------------------------- availability
    def incoming_soon(self, product: Product, days: int) -> int:
        horizon = self.today + timedelta(days=days)
        return sum(l.qty - l.received - l.rejected for l in self.open_po_lines[product.id]
                   if l._po.expected <= horizon)

    @staticmethod
    def unshipped(line: Line) -> int:
        """Phần đơn chưa giữ chỗ (chưa lên phiếu xuất)."""
        return line.qty - line.delivered - getattr(line, "_shipping", 0)

    def backlog_qty(self, product: Product) -> int:
        return sum(self.unshipped(line) for line, _ in self.backlog[product.id])

    def promise(self, product: Product, wanted: int, allow_backorder: bool = False) -> int:
        """Khách dự án/NPP có thể chờ mua bù; khách khác chỉ nhận hàng sẵn hoặc sắp về."""
        if allow_backorder:
            return wanted
        can = self.available[product.id] + self.incoming_soon(product, 10) - self.backlog_qty(product)
        return max(0, min(wanted, can))

    # ---------------------------------------------------------------- selling
    def number_for_customer(self, prefix: str, customer: Customer, d: date) -> str:
        base = f"{prefix}-{customer.code}-{d.strftime('%d%m%y')}"
        candidate, i = base, 0
        while candidate in self.order_numbers_taken:
            candidate = f"{base}-{chr(65 + i)}" if i < 26 else f"{base}-Z{i - 25}"
            i += 1
        self.order_numbers_taken.add(candidate)
        return candidate

    def place_order(self, customer: Customer, d: date, value_target: float, max_lines: int,
                    with_quote: bool, quote_lead_days: int = 0) -> None:
        lines = self.build_lines(customer, d, value_target, max_lines)
        if not lines:
            return
        sale = self.md.staff_by_id[customer.salesperson_id]
        quotation = None
        if with_quote:
            q_day = d - timedelta(days=quote_lead_days)
            while not is_workday(q_day):
                q_day -= timedelta(days=1)
            q_day = max(q_day, C.START_DATE)
            q_at = self.at(q_day)
            q_lines = [Line(self.n.id("quotation_item"), l.product, l.qty, l.gross_price, l.discount, l.price, l.vat)
                       for l in lines]
            quotation = Quotation(self.n.id("quotation"), self.number_for_customer("QT", customer, q_day),
                                  customer, q_day, q_at, sale.id, q_lines,
                                  approved_at=self.later(q_at, 20, 240))
            self.quotations.append(quotation)

        kept: list[Line] = []
        for line in lines:
            qty = self.promise(line.product, line.qty, customer.type in ("Distributor", "Project / Business"))
            if qty < line.qty:
                self.lost_demand[line.product.id] += line.qty - qty
                self.lost_by_day[line.product.id][d] += line.qty - qty
            if qty > 0:
                line.qty = qty
                line.id = self.n.id("order_item")
                kept.append(line)
        if not kept:
            return
        created = self.at(d)
        order = Order(self.n.id("order"), self.number_for_customer("OD", customer, d), customer, d, created,
                      sale.id, kept, quotation=quotation)
        if quotation:
            quotation.status, quotation.order = "CONVERTED", order
            if quotation.approved_at >= created:
                quotation.approved_at = created - timedelta(minutes=self.rng.randint(5, 60))
        order.customer_po = (f"PO-{customer.code[-4:]}-{d.strftime('%y%m')}{self.rng.randint(10, 99)}"
                             if customer.type in ("Distributor", "Project / Business") else None)
        self.orders.append(order)

        # Duyệt đơn: kiểm tra công nợ quá hạn
        overdue = sum(r.outstanding for r in self.open_receivables[customer.id] if r.due < d - timedelta(days=30))
        if customer.type != "Retail" and overdue > 0.3 * max(customer.credit_limit, 1) and self.rng.random() < 0.5:
            order.status = "REJECTED"
            order.updated_at = self.later(created, 30, 300)
            return
        if customer.type != "Retail" and self.rng.random() < 0.02:
            order.status = "CANCELLED"
            order.updated_at = self.later(created, 60, 600)
            return
        order.status = "APPROVED"
        order.approved_at = self.later(created, 5, 180) if customer.type != "Retail" else self.later(created, 1, 10)
        for line in kept:
            self.backlog[line.product.id].append((line, order))
        ship_day = d if (customer.type == "Retail" or self.rng.random() < 0.6) else add_workdays(d, 1)
        self.schedule(ship_day, self.ship_order, order)

    def ship_order(self, order: Order) -> None:
        """Tạo yêu cầu giao + phiếu xuất cho phần có hàng; phần thiếu chờ hàng về."""
        if not is_workday(self.today) and order.customer.type != "Retail":
            self.schedule(add_workdays(self.today, 1), self.ship_order, order)
            return
        ready: list[tuple[Line, int]] = []
        for line in order.lines:
            remaining = self.unshipped(line)
            if remaining <= 0:
                continue
            qty = min(remaining, self.available[line.product.id])
            if qty > 0:
                ready.append((line, qty))
        if not ready:
            return
        start = max(self.at(self.today, 8, 16), order.approved_at or order.created_at)
        dr_at = self.later(start, 10, 90)
        dr_lines = [DeliveryLine(self.n.id("delivery_item"), line, qty) for line, qty in ready]
        year = self.today.year
        delivery = Delivery(self.n.id("delivery"),
                            f"DR-{year}-{self.n.next(f'delivery-{year}'):06d}", order, self.today, dr_at,
                            add_workdays(self.today, self.rng.randint(1, 3)), dr_lines)
        self.deliveries.append(delivery)

        inv_at = self.later(dr_at, 15, 120)
        customer = order.customer
        if customer.type == "Retail":
            method, carrier = self.rng.choice(["CUSTOMER_PICKUP", "WAREHOUSE_DELIVERY"]), None
        elif customer.region == "Bắc" and self.rng.random() < 0.7:
            method, carrier = "WAREHOUSE_DELIVERY", None
        else:
            method, carrier = "THIRD_PARTY", self.rng.choice(CARRIERS)
        inv = SalesInvoice(self.n.id("sales_invoice"),
                           f"INV-{year}-{self.n.next(f'invoice-{year}'):06d}", delivery, self.today, inv_at,
                           self.staff("Sale", customer.region),
                           [InvoiceLine(self.n.id("invoice_item"), dl, dl.qty) for dl in dr_lines],
                           method, carrier, self.staff("Logistic"), self.staff("Logistic"),
                           self.staff("Logistic") if method == "WAREHOUSE_DELIVERY" else None)
        self.sales_invoices.append(inv)
        for il in inv.lines:
            line = il.order_line
            line._shipping = getattr(line, "_shipping", 0) + il.qty
            self.move("available", inv_at, line.product, "SALES_ORDER", inv.number, -il.qty,
                      "Giữ chỗ đơn bán hàng", inv.created_by.id)
        post_day = self.today if (customer.type == "Retail" or self.rng.random() < 0.55) else add_workdays(self.today, 1)
        self.schedule(post_day, self.post_invoice, inv)

    def post_invoice(self, inv: SalesInvoice) -> None:
        posted_at = max(self.at(self.today, 9, 17.5), self.later(inv.created_at, 30, 240))
        inv.posted_at, inv.posted_by = posted_at, self.staff("Logistic Manager")
        order = inv.delivery.order
        for il in inv.lines:
            line = il.order_line
            self.move("physical", posted_at, line.product, "DELIVERY_NOTE", f"{inv.number}:{il.id}", -il.qty,
                      f"Xuất kho giao hàng (Đơn hàng: {order.number})", inv.posted_by.id)
            line._shipping -= il.qty
            line.delivered += il.qty
            self.sold_by_day[line.product.id][self.today] += il.qty
            if line.delivered >= line.qty:
                queue = self.backlog[line.product.id]
                if (line, order) in queue:
                    queue.remove((line, order))
        inv.delivery.status = "DELIVERED"
        delivered = sum(l.delivered for l in order.lines)
        order.status = "COMPLETED" if delivered >= sum(l.qty for l in order.lines) else "PROCESSING"
        order.updated_at = posted_at

        # Hóa đơn VAT: phần lớn xuất ngay, một số gom cuối tuần
        if order.customer.type == "Retail" or self.rng.random() < 0.85:
            self.schedule(self.today, self.issue_vat, inv)
        else:
            self.schedule(add_workdays(self.today, self.rng.randint(1, 5)), self.issue_vat, inv)

    def issue_vat(self, inv: SalesInvoice) -> None:
        series = f"1C{self.today.strftime('%y')}TBB"
        subtotal = sum(il.qty * il.order_line.price for il in inv.lines)
        vat = sum(round(il.qty * il.order_line.price * il.order_line.vat / 100, 2) for il in inv.lines)
        at = max(self.at(self.today, 13, 18), self.later(inv.posted_at, 10, 90))
        vat_inv = VatInvoice(self.n.id("vat_invoice"), f"{self.n.next(f'vat-{series}'):08d}", series, self.today, at,
                             self.staff("Finance"), inv, subtotal, vat)
        inv.vat_invoice = vat_inv
        self.vat_invoices.append(vat_inv)
        customer = inv.delivery.order.customer
        rec = Receivable(self.n.id("receivable"), vat_inv, customer,
                         self.today + timedelta(days=customer.terms_days), vat_inv.total)
        self.receivables.append(rec)
        self.open_receivables[customer.id].append(rec)
        if customer.type == "Retail":
            self.collect(customer, [rec], rec.amount, "CASH" if self.rng.random() < 0.55 else "BANK",
                         self.later(at, 5, 40))

    # --------------------------------------------------------------- collect
    def collect(self, customer: Customer, recs: list[Receivable], amount: float, channel: str,
                at: datetime | None = None) -> None:
        amount = round(amount, 0)
        if amount <= 0:
            return
        at = at or self.at(self.today, 8.5, 16.5)
        allocations, left = [], amount
        for rec in sorted(recs, key=lambda r: r.due):
            pay = min(left, rec.outstanding)
            if pay <= 0:
                continue
            rec.paid += pay
            allocations.append((rec, pay))
            left -= pay
            if rec.outstanding <= 0.01:
                self.open_receivables[customer.id].remove(rec)
        if not allocations:
            return
        total = sum(a for _, a in allocations)
        prefix, kind = ("PT", "CASH_RECEIPT") if channel == "CASH" else ("BT", "BANK_RECEIPT")
        number = f"{prefix}-{self.today.strftime('%Y%m')}-{self.n.next(kind):05d}"
        invoices = ", ".join(r.vat_invoice.number for r, _ in allocations)
        bank = None if channel == "CASH" else (1 if customer.id % 3 else 2)
        self.receipts.append(Receipt(self.n.id("receipt"), number, channel, self.today, at, customer, total,
                                     self.staff("Finance"), bank, allocations, f"Thu tiền hóa đơn {invoices}"))

    def payment_run(self, customer: Customer) -> None:
        open_recs = [r for r in self.open_receivables[customer.id] if r.outstanding > 0]
        if customer.late_payer:
            delay = self.rng.randint(10, 50)
            due = [r for r in open_recs if r.due <= self.today - timedelta(days=delay)]
        else:
            due = [r for r in open_recs if r.due <= self.today + timedelta(days=customer.pay_cycle)]
        if due:
            amount = sum(r.outstanding for r in due)
            if self.rng.random() < 0.1:
                amount *= self.rng.uniform(0.4, 0.8)                      # trả một phần
            channel = "CASH" if (customer.type == "Dealer" and self.rng.random() < 0.15) else "BANK"
            self.collect(customer, due, amount, channel)
        nxt = self.today + timedelta(days=customer.pay_cycle + self.rng.randint(-2, 2))
        while not is_workday(nxt):
            nxt += timedelta(days=1)
        customer.next_pay = nxt

    # -------------------------------------------------------------- customers
    def run_b2b_customers(self) -> None:
        d = self.today
        for c in self.md.b2b_customers:
            if c.since.date() > d or (c.active_until and d > c.active_until):
                continue
            if c.type == "Project / Business":
                for pd in [x for x in c.project_dates if x == d]:
                    value = self.rng.lognormvariate(math.log(C.PROJECT_ORDER_VALUE[0]), C.PROJECT_ORDER_VALUE[1])
                    self.place_order(c, d, value, 6, with_quote=True, quote_lead_days=self.rng.randint(5, 20))
            elif c.next_order and c.next_order <= d:
                value = c.budget * c.interval / 30 * self.season_multiplier(c, d) * self.tet_factor(d)
                value *= self.rng.lognormvariate(0, 0.25)
                max_lines = 25 if c.type == "Distributor" else 12
                with_quote = self.rng.random() < (0.5 if c.type == "Distributor" else 0.15)
                self.place_order(c, d, value, max_lines, with_quote, self.rng.randint(1, 5))
                gap = max(3, round(c.interval * self.rng.uniform(0.7, 1.3) / max(0.6, self.tet_factor(d))))
                c.next_order = d + timedelta(days=gap)
            if c.next_pay and c.next_pay <= d:
                self.payment_run(c)

    def lost_quotations(self) -> None:
        """Báo giá dự án/NPP không chốt được (khách mua nơi khác)."""
        for c in self.md.b2b_customers:
            if c.type not in ("Project / Business", "Distributor") or c.since.date() > self.today:
                continue
            chance = 0.006 if c.type == "Project / Business" else 0.004
            if self.rng.random() < chance:
                value = (self.rng.lognormvariate(math.log(C.PROJECT_ORDER_VALUE[0]), 0.8)
                         if c.type == "Project / Business" else c.budget * 0.5)
                lines = self.build_lines(c, self.today, value, 6)
                if not lines:
                    continue
                for l in lines:
                    l.id = self.n.id("quotation_item")
                at = self.at(self.today)
                status = self.rng.choice(["APPROVED", "APPROVED", "APPROVED", "REJECTED", "PENDING_APPROVAL"])
                self.quotations.append(Quotation(self.n.id("quotation"), self.number_for_customer("QT", c, self.today),
                                                 c, self.today, at, c.salesperson_id, lines, status=status,
                                                 approved_at=self.later(at, 30, 300) if status == "APPROVED" else None))

    def run_retail(self) -> None:
        d = self.today
        if d in C.HOLIDAYS:
            return
        lam = C.RETAIL_ORDERS_PER_DAY * {5: 1.3, 6: 1.5}.get(d.weekday(), 1.0)
        for start, end, factor in C.RETAIL_EVENTS:
            if start <= d <= end:
                lam *= factor
        lam *= self.tet_factor(d) * (0.85 + 0.3 * sum(self.month_factor(cat, d) for cat in ("Điều hòa", "Nồi cơm điện")) / 2)
        for _ in range(self.poisson(lam)):
            if self.retail_pool and self.rng.random() < 0.12:
                customer = self.rng.choice(self.retail_pool)
            else:
                customer = self.md.new_retail_customer(self.at(d, 8, 20))
                self.retail_pool.append(customer)
            self.place_order(customer, d, 0, 2, with_quote=False)

    def poisson(self, lam: float) -> int:
        l, k, p = math.exp(-lam), 0, 1.0
        while True:
            p *= self.rng.random()
            if p <= l:
                return k
            k += 1

    # ------------------------------------------------------------- purchasing
    def daily_rate(self, product: Product, days: int = 28) -> float:
        sold = self.sold_by_day[product.id]
        lost = self.lost_by_day[product.id]
        start = self.today - timedelta(days=days)
        return (sum(q for d, q in sold.items() if d >= start)
                + sum(q for d, q in lost.items() if d >= start)) / days

    def forecast(self, product: Product, lead: int) -> float:
        d = self.today
        ordered_recent = self.backlog_qty(product)
        planned = self.md.expected_rate.get(product.id, 0) * product.guess_error
        live_days = (d - product.available_from).days
        if live_days < 28:
            observed = self.daily_rate(product)
            base = planned if live_days < 0 or observed == 0 else (observed * live_days + planned * (28 - live_days)) / 28
        else:
            observed = self.daily_rate(product)
            base = observed + ordered_recent / 28
        cur = self.month_factor(product.category, d)
        future = self.month_factor(product.category, d + timedelta(days=lead + 7))
        adj = min(3.0, max(0.3, future / cur)) if cur else 1.0
        return base * adj * self.lifecycle(product, d + timedelta(days=lead)) / max(self.lifecycle(product, d), 0.2)

    def purchasing_review(self) -> None:
        d = self.today
        first_monday = d.day <= 7
        for supplier in self.md.suppliers:
            if supplier.kind == "import" and not first_monday:
                continue
            lead = sum(supplier.lead_days) // 2
            review = C.ORDER_COVER_DAYS[supplier.kind]
            noise = self.rng.gauss(1.0, 0.15)
            lines: list[tuple[Product, int]] = []
            urgent = False
            for p in self.md.products_by_supplier[supplier.id]:
                if p.discontinued_on and p.discontinued_on <= d + timedelta(days=lead):
                    continue
                if d + timedelta(days=lead) < p.available_from:
                    continue
                rate = self.forecast(p, lead) * noise
                if rate <= 0.01:
                    continue
                position = self.available[p.id] + self.on_order[p.id] - self.backlog_qty(p)
                reorder_point = rate * (lead + C.SAFETY_DAYS)
                if position > reorder_point:
                    continue
                target = rate * (lead + review + C.SAFETY_DAYS)
                qty = math.ceil((target - position) / p.pack) * p.pack
                if qty > 0:
                    lines.append((p, qty))
                    urgent |= position <= rate * 3
            if not lines:
                continue
            value = sum(p.cost_price * q for p, q in lines)
            if value < supplier.min_order_value and not urgent:
                continue
            self.create_purchase(supplier, lines, urgent)

    def create_purchase(self, supplier, lines, urgent: bool) -> None:
        d = self.today
        buyer = self.staff("Purchase")
        manager = self.staff("Purchase Manager")
        pr_at = self.at(d, 8.5, 11.5)
        week = d.isocalendar().week
        n_lines = len(lines)
        reason = (f"Tồn kho dưới điểm đặt hàng, có nguy cơ hết hàng ({n_lines} mã)" if urgent
                  else f"Bổ sung tồn kho định kỳ tuần {week} cho {supplier.name} ({n_lines} mã)")
        pr = Requisition(self.n.id("requisition"), f"PR-{d.strftime('%Y%m')}-{self.n.next('PR' + d.strftime('%Y%m')):04d}",
                         d, pr_at, buyer, manager, self.later(pr_at, 30, 300),
                         "Urgent" if urgent else self.rng.choice(["Medium", "Medium", "High", "Low"]), reason,
                         d + timedelta(days=sum(supplier.lead_days) // 2 + 3),
                         [PRLine(self.n.id("requisition_item"), p, q, p.cost_price) for p, q in lines])
        self.requisitions.append(pr)

        po_day = d if urgent else add_workdays(d, self.rng.randint(0, 1))
        po_at = max(self.at(po_day, 9, 16), self.later(pr.approved_at, 20, 120))
        rate = C.USD_VND[(po_day.year, po_day.month)] if supplier.currency == "USD" else 1.0
        po_lines = []
        for prl in pr.lines:
            cost = prl.product.cost_price / rate
            cost = round(cost, 2) if supplier.currency == "USD" else round(cost, -2)
            po_lines.append(POLine(self.n.id("po_item"), prl.product, prl.qty, cost,
                                   0.0 if supplier.kind == "import" else float(prl.product.vat), pr_line=prl))
        lead = self.rng.randint(*supplier.lead_days)
        expected = po_day + timedelta(days=supplier.lead_days[1])   # NCC hẹn theo lead time tối đa
        po = PurchaseOrder(self.n.id("po"), f"PO-{po_day.strftime('%Y%m')}-{self.n.next('PO' + po_day.strftime('%Y%m')):04d}",
                           supplier, po_day, po_at, buyer, manager, self.later(po_at, 15, 240), expected,
                           supplier.currency, rate, po_lines, pr,
                           notes=f"Đơn mua từ đề nghị {pr.code}")
        self.purchase_orders.append(po)
        for l in po_lines:
            l._po = po
            self.on_order[l.product.id] += l.qty
            self.open_po_lines[l.product.id].append(l)
        if self.rng.random() < C.SUPPLIER_LATE_RATIO[supplier.kind]:
            lead += self.rng.randint(4, 15 if supplier.kind == "domestic" else 25)
        arrival = po_day + timedelta(days=lead)
        while not is_workday(arrival):
            arrival += timedelta(days=1)
        partial = self.rng.random() < 0.06
        self.schedule(arrival, self.receive_po, po, 0.6 if partial else 1.0)
        if partial:
            self.schedule(add_workdays(arrival, self.rng.randint(4, 10)), self.receive_po, po, 1.0)

    def receive_po(self, po: PurchaseOrder, share: float) -> None:
        d = self.today
        at = self.at(d, 7, 8.25)          # xe hàng về đầu ngày, trước khi kho bắt đầu xuất
        receiver = self.staff("Logistic")
        received_lines = []
        for l in po.lines:
            remaining = l.qty - l.received - l.rejected
            if remaining <= 0:
                continue
            qty = remaining if share >= 1 else max(0, math.floor(remaining * share))
            if qty <= 0:
                continue
            rejected = 1 if (qty >= 10 and self.rng.random() < 0.02) else 0
            l.received += qty - rejected
            l.rejected += rejected
            self.on_order[l.product.id] -= qty
            if l.received + l.rejected >= l.qty:
                self.open_po_lines[l.product.id].remove(l)
            received_lines.append((l, qty - rejected, rejected))
        if not received_lines:
            return
        gr = GoodsReceipt(self.n.id("gr"), f"GR-{d.strftime('%Y%m')}-{self.n.next('GR' + d.strftime('%Y%m')):04d}",
                          po, d, at, receiver,
                          f"PGH-{po.supplier.code}-{self.rng.randint(10000, 99999)}", received_lines)
        self.goods_receipts.append(gr)
        for l, qty, _ in received_lines:
            if qty > 0:
                self.move("physical", at, l.product, "PURCHASE_RECEIPT", gr.code, qty,
                          "Nhập kho mua hàng (PO Receipt)", receiver.id)
                self.move("available", at, l.product, "PURCHASE_RECEIPT", gr.code, qty,
                          "Tăng tồn khả dụng từ đơn nhập kho", receiver.id)
        done = all(l.received + l.rejected >= l.qty for l in po.lines)
        po.status = "Received" if done else "PartiallyReceived"
        po.updated_at = at
        inv_day = add_workdays(d, self.rng.randint(0, 5))
        self.schedule(inv_day, self.book_purchase_invoice, gr)
        # Hàng về: giao các đơn đang chờ
        for l, qty, _ in received_lines:
            for line, order in list(self.backlog[l.product.id]):
                if order not in self._pending_ship:
                    self._pending_ship.append(order)

    def book_purchase_invoice(self, gr: GoodsReceipt) -> None:
        d = self.today
        po = gr.po
        lines = [(l, qty) for l, qty, _ in gr.lines if qty > 0]
        for l, qty in lines:
            l.invoiced += qty
        inv = PurchaseInvoice(self.n.id("pinv"), f"PINV-{d.strftime('%Y%m')}-{self.n.next('PINV' + d.strftime('%Y%m')):04d}",
                              po, gr, f"{self.rng.randint(1, 9999999):08d}", d,
                              d + timedelta(days=po.supplier.payment_days), self.at(d, 9, 17), lines)
        inv.status = "Paid" if inv.due <= C.END_DATE else "Posted"
        self.purchase_invoices.append(inv)
        if all(l.invoiced >= l.qty - l.rejected for l in po.lines) and po.status == "Received":
            po.status = "Completed"
            po.updated_at = inv.created_at

    # ------------------------------------------------------------ price moves
    def price_events(self) -> None:
        plan = self.md.price_plan.get(self.today)
        if not plan:
            return
        manager = self.staff("Purchase Manager")
        for brand, pct in plan:
            for p in self.md.products_by_brand.get(brand, []):
                at = self.at(self.today, 8, 10)
                new_gross = float(round(p.gross_price * (1 + pct / 100), -4))
                new_cost = float(round(p.cost_price * (1 + pct / 100), -3))
                self.price_changes.append(PriceChange(at, p, p.gross_price, new_gross, p.cost_price, new_cost,
                                                      f"Thông báo điều chỉnh giá {brand} {self.today:%m/%Y}", manager))
                p.gross_price, p.cost_price = new_gross, new_cost

    # ------------------------------------------------------------------ main
    def estimate_rates(self) -> None:
        """Nhu cầu kỳ vọng (cái/ngày) của từng SKU tại thời điểm bắt đầu bán, tính giải tích
        từ sức mua của khách. Dùng cho tồn đầu kỳ và dự báo ban đầu của model mới."""
        rates: dict[int, float] = defaultdict(float)
        retail_daily = 0.0   # khách lẻ chiếm tỷ trọng nhỏ, bỏ qua khi ước tính
        groups = [(c, c.budget / 30) for c in self.md.b2b_customers if c.budget]
        groups += [(c, len(c.project_dates) * C.PROJECT_ORDER_VALUE[0] * 1.4 / 365)
                   for c in self.md.b2b_customers if c.type == "Project / Business"]
        for p in self.md.products:
            d = max(C.START_DATE, p.available_from)
            total = 0.0
            sample = groups + [(None, retail_daily)]
            for c, daily_value in sample:
                proxy = c or self.md.b2b_customers[0]
                weights = self.category_weights(proxy, d) if c else {
                    k: v * self.month_factor(k, d) for k, v in C.CATEGORY_SHARE.items()}
                if p.category not in weights:
                    continue
                cat_share = weights[p.category] / sum(weights.values())
                in_cat = self.md.products_by_category[p.category]
                pop_total = sum(x.popularity for x in in_cat if x.available_from <= d + timedelta(days=30))
                share = p.popularity / max(pop_total, p.popularity)
                total += daily_value * cat_share * share / p.gross_price / 0.85
            rates[p.id] = total
            p.min_stock = max(1, math.ceil(total * C.SAFETY_DAYS))
        self.md.expected_rate = dict(rates)

    def opening_stock(self) -> None:
        at = datetime.combine(C.START_DATE, time(7, 30))
        admin = self.md.staff_by_id[self.md.admin_id]
        for p in self.md.products:
            if p.available_from > C.START_DATE:
                continue
            days = self.rng.uniform(20, 55) * (1.6 if p.decay else 1.0)
            qty = math.ceil(self.md.expected_rate.get(p.id, 0) * days / p.pack) * p.pack
            if qty <= 0:
                continue
            doc = f"OB-{C.START_DATE:%Y%m%d}"
            self.move("physical", at, p, "PURCHASE_RECEIPT", doc, qty, "Nhập tồn đầu kỳ khi triển khai ERP", admin.id)
            self.move("available", at, p, "PURCHASE_RECEIPT", doc, qty, "Nhập tồn đầu kỳ khi triển khai ERP", admin.id)

    def run(self) -> None:
        self._pending_ship: list[Order] = []
        self.estimate_rates()
        self.opening_stock()
        d = C.START_DATE
        while d <= C.END_DATE:
            self.today = d
            self.price_events()
            for fn, args in self.events.pop(d, [])[:]:
                fn(*args)
            if is_workday(d):
                if d.weekday() == C.REVIEW_WEEKDAY:
                    self.purchasing_review()
                self.run_b2b_customers()
                self.lost_quotations()
            self.run_retail()
            # sự kiện phát sinh trong ngày (giao hàng, xuất kho, VAT trong ngày)
            while self.events.get(d):
                for fn, args in self.events.pop(d):
                    fn(*args)
            if is_workday(d) and self._pending_ship:
                pending, self._pending_ship = self._pending_ship, []
                for order in pending:
                    self.ship_order(order)
                while self.events.get(d):
                    for fn, args in self.events.pop(d):
                        fn(*args)
            d += timedelta(days=1)
