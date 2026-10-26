"""Chuyển kết quả mô phỏng thành các dòng đúng schema của từng service.

Quy ước định dạng (khớp với dữ liệu app tự sinh):
- Laravel: timestamp 'Y-m-d H:i:s' theo UTC; order_date/quotation_date 'd/m/Y'; approved_at 'd/m/Y H:i'.
- .NET (EF Core): DateTime UTC 'yyyy-MM-dd HH:mm:ss.fffffff'; decimal lưu TEXT dạng '12345.0'.
"""
from __future__ import annotations

import unicodedata
import uuid
from collections import defaultdict
from datetime import date, datetime, timedelta

from . import config as C
from .engine import MAIN_WAREHOUSE_ID, Simulation
from .models import Customer, Staff

UTC_OFFSET = timedelta(hours=7)
IDENTITY_ROLE = {"Sale": "Sale", "Sale Manager": "Sale Manager", "Purchase": "Purchase",
                 "Purchase Manager": "Purchase Manager", "Logistic": "Logistic", "Logistic Manager": "Logistic Manager",
                 "Finance": "Finance & Accounting", "Finance Manager": "Finance & Accounting Manager"}


def utc(t: datetime | None) -> datetime | None:
    return t - UTC_OFFSET if t else None


def lts(t: datetime | None) -> str | None:
    """Laravel timestamp."""
    return utc(t).strftime("%Y-%m-%d %H:%M:%S") if t else None


def nts(t: datetime | None) -> str | None:
    """EF Core DateTime."""
    if not t:
        return None
    u = utc(t)
    return f"{u:%Y-%m-%d %H:%M:%S}.{(u.hour * 7919 + u.minute * 104729 + u.second * 1299709) % 10**7:07d}"


def dmy(d: date) -> str:
    return d.strftime("%d/%m/%Y")


def dmy_hm(t: datetime | None) -> str | None:
    return utc(t).strftime("%d/%m/%Y %H:%M") if t else None


def dec(x: float) -> str:
    return repr(float(round(x, 4)))


class Writer:
    def __init__(self, sim: Simulation, account_ids: dict[str, int], role_ids: dict[str, int],
                 department_ids: dict[str, int], password_hash: str):
        self.sim = sim
        self.md = sim.md
        self.acc = account_ids
        self.role_ids = role_ids
        self.department_ids = department_ids
        self.password_hash = password_hash
        self.manager = {r: next(s for s in self.md.staff if s.role == r)
                        for r in ("Sale Manager", "Purchase Manager", "Logistic Manager", "Finance Manager")}
        self._renumber_customers()

    def _renumber_customers(self) -> None:
        """ID khách hàng tăng theo ngày tạo (như khi nhập liệu thật)."""
        ordered = sorted(self.md.customers, key=lambda c: (c.since, c.id))
        for i, c in enumerate(ordered, 1):
            c.id = i
        self.md.customers = ordered

    def name(self, staff_id: int) -> str:
        return self.md.staff_by_id[staff_id].name

    # ================================================================ identity
    def identity(self) -> dict[str, list[dict]]:
        users, roles = [], []
        managers = {"Phòng Kinh Doanh": "Sale Manager", "Phòng Mua Hàng & Cung Ứng": "Purchase Manager",
                    "Bộ Phận Kho Vận & Điều Vận": "Logistic Manager", "Phòng Tài Chính Kế Toán": "Finance Manager"}
        created = datetime.combine(C.START_DATE - timedelta(days=20), datetime.min.time()).replace(hour=9)
        for s in self.md.staff:
            if not s.is_new:
                continue
            users.append({
                "id": s.id, "department_id": self.department_ids[s.department], "name": s.name, "email": s.email,
                "password_hash": self.password_hash, "is_active": 1, "created_at": lts(created),
                "updated_at": lts(created), "line_manager_id": self.manager[managers[s.department]].id,
            })
            roles.append({"user_id": s.id, "role_id": self.role_ids[IDENTITY_ROLE[s.role]]})
        return {"users": users, "user_roles": roles}

    # ================================================================= catalog
    def catalog(self) -> dict[str, list[dict]]:
        md = self.md
        start = lts(datetime.combine(C.START_DATE, datetime.min.time()).replace(hour=7))
        categories = sorted({p.category for p in md.products})
        cat_id = {c: i for i, c in enumerate(categories, 1)}
        # MySQL's utf8mb4_unicode_ci unique index is case/accent-insensitive,
        # while SQLite's default UNIQUE comparison is not. Collapse raw
        # variants such as "AVA"/"Ava" and "Hoà Phát"/"Hòa Phát" before IDs.
        def brand_key(name: str) -> str:
            folded = unicodedata.normalize("NFKD", name.casefold().replace("đ", "d"))
            return "".join(ch for ch in folded if not unicodedata.combining(ch))

        brand_by_key: dict[str, str] = {}
        for name in sorted({p.brand for p in md.products}):
            brand_by_key.setdefault(brand_key(name), name)
        brands = sorted(brand_by_key.values())
        brand_id = {brand_key(b): i for i, b in enumerate(brands, 1)}
        brand_index = defaultdict(list)
        for p in md.products:
            brand_index[brand_key(p.brand)].append(p.gross_price / p.cost_price)
        last_change = {}
        for ch in self.sim.price_changes:
            last_change[ch.product.id] = ch.at

        rows: dict[str, list[dict]] = defaultdict(list)
        for c in categories:
            rows["categories"].append({"id": cat_id[c], "code": c.lower().replace(" ", "-"), "name": c,
                                       "description": None, "created_at": start, "updated_at": start})
        for b in brands:
            key = brand_key(b)
            idx = sum(brand_index[key]) / len(brand_index[key])
            rows["brands"].append({"id": brand_id[key], "name": b, "price_index": round(idx, 4),
                                   "created_at": start, "updated_at": start})
        for p in md.products:
            ended = p.discontinued_on and p.discontinued_on <= C.END_DATE and self.sim.on_hand[p.id] <= 0
            rows["products"].append({
                "id": p.id, "sku": p.sku, "designation": p.model, "brand_id": brand_id[brand_key(p.brand)],
                "category_id": cat_id[p.category], "description": p.name, "com": p.origin, "unit": p.unit,
                "gross_price": p.gross_price, "spec": p.spec, "cost_price": p.cost_price, "min_stock": p.min_stock,
                "status": "INACTIVE" if ended else "ACTIVE", "created_at": lts(p.created_at),
                "updated_at": lts(last_change.get(p.id, p.created_at)),
            })
            if p.available_from > C.START_DATE:
                rows["product_price_histories"].append({
                    "product_id": p.id, "old_gross_price": None, "new_gross_price": p.gross_price
                    if p.id not in last_change else next(ch.old_gross for ch in self.sim.price_changes if ch.product.id == p.id),
                    "old_cost_price": None, "new_cost_price": p.cost_price if p.id not in last_change else
                    next(ch.old_cost for ch in self.sim.price_changes if ch.product.id == p.id),
                    "source": "CREATE", "reference": f"Mẫu mới {p.brand} {p.model}",
                    "changed_by": self.manager["Purchase Manager"].id,
                    "changed_by_name": self.manager["Purchase Manager"].name, "created_at": lts(p.created_at)})
        for ch in self.sim.price_changes:
            rows["product_price_histories"].append({
                "product_id": ch.product.id, "old_gross_price": ch.old_gross, "new_gross_price": ch.new_gross,
                "old_cost_price": ch.old_cost, "new_cost_price": ch.new_cost, "source": "MANUAL",
                "reference": ch.reference, "changed_by": ch.staff.id, "changed_by_name": ch.staff.name,
                "created_at": lts(ch.at)})
        rows["product_price_histories"].sort(key=lambda r: r["created_at"])
        for i, r in enumerate(rows["product_price_histories"], 1):
            r["id"] = i

        contact_id = 0
        self.primary_contact: dict[int, int] = {}
        for c in md.customers:
            rep = md.staff_by_id[c.salesperson_id]
            rows["customers"].append({
                "id": c.id, "code": c.code, "name": c.name, "customer_type": c.type, "tax_code": c.tax_code,
                "phone": c.phone, "email": c.email, "address": c.address, "credit_limit": c.credit_limit,
                "status": "INACTIVE" if c.active_until and c.active_until < C.END_DATE else "ACTIVE",
                "created_at": lts(c.since), "updated_at": lts(c.since),
                "created_by": rep.email.split("@")[0],
            })
            for ct in c.contacts:
                contact_id += 1
                if ct.is_primary:
                    self.primary_contact[c.id] = contact_id
                rows["customer_contacts"].append({
                    "id": contact_id, "customer_id": c.id, "name": ct.name, "position": ct.position,
                    "phone": ct.phone, "email": ct.email, "is_primary": int(ct.is_primary), "notes": None,
                    "created_at": lts(c.since), "updated_at": lts(c.since)})
        for s in md.suppliers:
            rows["suppliers"].append({
                "id": s.id, "code": s.code, "name": s.name, "tax_code": s.tax_code, "supplier_group": s.group,
                "supplier_type": s.supplier_type, "phone": s.phone, "email": s.email, "address": s.address,
                "contact_person": s.contact_person, "contact_phone": s.contact_phone,
                "contact_email": s.contact_email, "bank_name": s.bank_name, "bank_account_number": s.bank_account,
                "bank_account_holder": s.name.upper(), "bank_branch": None,
                "payment_terms": f"Net {s.payment_days}" if s.kind == "domestic" else f"T/T {s.payment_days} ngày",
                "credit_limit": 0, "status": "ACTIVE",
                "notes": s.notes or ("Nhãn hàng: " + ", ".join(sorted(s.brands))),
                "created_at": start, "updated_at": start})
        return rows

    # =================================================================== sales
    def sales(self) -> dict[str, list[dict]]:
        sim, md = self.sim, self.md
        rows: dict[str, list[dict]] = defaultdict(list)
        sale_mgr = self.manager["Sale Manager"]
        q_item_by_product: dict[tuple[int, int], int] = {}

        for q in sim.quotations:
            c = q.customer
            subtotal = sum(l.subtotal for l in q.lines)
            vat = sum(round(l.subtotal * l.vat / 100, 2) for l in q.lines)
            updated = q.order.created_at if q.order else (q.approved_at or q.created_at)
            rows["quotations"].append({
                "id": q.id, "quotation_number": q.number, "customer_id": c.id, "customer_name": c.name,
                "contact_id": self.primary_contact.get(c.id), "quotation_date": dmy(q.day), "currency": "VND",
                "exchange_rate": 1, "created_by": self.name(q.staff_id), "assigned_to": str(q.staff_id),
                "status": q.status, "approved_by": sale_mgr.name if q.approved_at else None,
                "approved_at": dmy_hm(q.approved_at), "subtotal": subtotal, "vat_total": vat,
                "total_amount": subtotal + vat, "notes": "Báo giá theo yêu cầu khách hàng",
                "created_at": lts(q.created_at), "updated_at": lts(updated), "created_by_id": q.staff_id})
            ordered = {l.product.id: l.qty for l in q.order.lines} if q.order else {}
            for l in q.lines:
                q_item_by_product[(q.id, l.product.id)] = l.id
                rows["quotation_items"].append({
                    "id": l.id, "quotation_id": q.id, "sku": l.product.sku, "description": l.product.name,
                    "quantity": l.qty, "discount_percent": l.discount, "selling_price": l.price,
                    "vat_percent": l.vat, "lead_time": "Có sẵn", "subtotal": l.subtotal,
                    "won_quantity": ordered.get(l.product.id, 0),
                    "status": "WIN" if l.product.id in ordered else "PENDING",
                    "product_id": l.product.id, "gross_price": l.gross_price,
                    "created_at": lts(q.created_at), "updated_at": lts(updated)})

        for o in sim.orders:
            c = o.customer
            rows["sales_orders"].append({
                "id": o.id, "order_number": o.number, "customer_id": c.id, "order_date": dmy(o.day),
                "total_amount": o.subtotal + o.vat_total, "status": o.status,
                "notes": f"Đơn bán hàng chuyển đổi từ Báo giá {o.quotation.number}" if o.quotation else None,
                "created_at": lts(o.created_at), "updated_at": lts(o.updated_at or o.approved_at or o.created_at),
                "customer_order_number": o.customer_po or o.number, "contact_id": self.primary_contact.get(c.id),
                "currency": "VND", "exchange_rate": 1, "created_by": str(o.staff_id), "assigned_to": str(o.staff_id),
                "subtotal": o.subtotal, "vat_total": o.vat_total, "quotation_id": o.quotation.id if o.quotation else None,
                "approved_by": sale_mgr.id if o.approved_at else None, "approved_at": dmy_hm(o.approved_at),
                "shipping_address": c.address})
            for l in o.lines:
                rows["sales_order_items"].append({
                    "id": l.id, "order_id": o.id, "sku": l.product.sku, "product_name": l.product.model,
                    "quantity": l.qty, "subtotal": l.subtotal, "discount_percent": l.discount,
                    "selling_price": l.price, "vat_percent": l.vat, "lead_time": "Có sẵn", "status": "WIN",
                    "delivery_date": dmy(o.day), "product_id": l.product.id, "gross_price": l.gross_price,
                    "delivered_quantity": l.delivered,
                    "quotation_item_id": q_item_by_product.get((o.quotation.id, l.product.id)) if o.quotation else None,
                    "created_at": lts(o.created_at), "updated_at": lts(o.updated_at or o.created_at)})
                if o.approved_at:
                    vat_amount = round(l.subtotal * l.vat / 100, 2)
                    rows["sale_report_items"].append({
                        "order_id": o.id, "order_number": o.number, "order_item_id": l.id,
                        "product_id": l.product.id, "sku": l.product.sku, "product_name": l.product.model,
                        "quantity": l.qty, "delivered_quantity": l.delivered,
                        "remaining_quantity": max(0, l.qty - l.delivered), "selling_price": l.price,
                        "vat": l.vat, "vat_percent": l.vat, "vat_amount": vat_amount, "subtotal": l.subtotal,
                        "total_amount": l.subtotal + vat_amount, "delivery_date": dmy(o.day), "customer_id": c.id,
                        "salesperson_id": o.staff_id,
                        "status": "DELIVERED" if l.delivered >= l.qty else ("PARTIAL" if l.delivered else "PENDING"),
                        "created_at": lts(o.approved_at), "updated_at": lts(o.updated_at or o.approved_at)})

        for d in sim.deliveries:
            o = d.order
            delivered = all(i.posted_at for i in sim_invoices_of(sim, d))
            updated = max((i.posted_at for i in sim_invoices_of(sim, d) if i.posted_at), default=d.created_at)
            rows["delivery_requests"].append({
                "id": d.id, "request_number": d.number, "warehouse_name": "Kho Chính",
                "shipping_address": o.customer.address, "contact_phone": o.customer.phone,
                "request_date": d.day.isoformat(), "expected_delivery_date": d.expected.isoformat(),
                "status": "DELIVERED" if delivered else "PENDING", "created_at": lts(d.created_at),
                "updated_at": lts(updated), "order_id": o.id, "warehouse_id": MAIN_WAREHOUSE_ID})
            for dl in d.lines:
                rows["delivery_request_items"].append({
                    "id": dl.id, "delivery_request_id": d.id, "sku": dl.order_line.product.sku, "quantity": dl.qty,
                    "order_item_id": dl.order_line.id, "delivered_quantity": dl.qty if delivered else 0,
                    "product_id": dl.order_line.product.id, "created_at": lts(d.created_at),
                    "updated_at": lts(updated)})

        revenue_id, vat_item_id = 0, 0
        for inv in sim.sales_invoices:
            d, o = inv.delivery, inv.delivery.order
            c = o.customer
            posted = inv.posted_at is not None
            terms = "Thanh toán ngay" if c.type == "Retail" else f"Công nợ {c.terms_days} ngày"
            rows["invoices"].append({
                "id": inv.id, "invoice_number": inv.number, "delivery_request_number": d.number,
                "invoice_date": inv.day.isoformat(), "payment_terms": terms, "total_amount": inv.subtotal,
                "warehouse_status": "DELIVERED" if posted else "SENT_TO_WAREHOUSE",
                "accounting_status": "POSTED" if posted else "UNPOSTED",
                "posted_at": lts(inv.posted_at), "posted_by": inv.posted_by.name if posted else None,
                "created_at": lts(inv.created_at), "updated_at": lts(inv.posted_at or inv.created_at),
                "delivery_request_id": d.id, "currency": "VND", "exchange_rate": 1,
                "created_by": inv.created_by.name, "notes": f"Xuất theo Yêu Cầu Giao Hàng #{d.id} ({o.number})",
                "picker_name": inv.picker.name, "packer_name": inv.packer.name,
                "shipper_name": inv.shipper.name if inv.shipper else None,
                "shipping_note": f"Gửi qua {inv.carrier}" if inv.carrier else None,
                "warehouse_posted_at": lts(inv.posted_at), "warehouse_posted_by": inv.posted_by.name if posted else None,
                "shipping_method": inv.shipping_method, "inventory_reservation_status": "RESERVED",
                "inventory_reserved_at": lts(inv.created_at), "inventory_issue_status": "ISSUED" if posted else "PENDING",
                "inventory_issued_at": lts(inv.posted_at), "inventory_warehouse_id": MAIN_WAREHOUSE_ID})
            rows["warehouse_dispatches"].append({
                "id": inv.id, "invoice_id": inv.id, "invoice_number": inv.number, "delivery_request_id": d.id,
                "delivery_request_number": d.number, "order_id": o.id, "order_number": o.number,
                "customer_name": c.name, "warehouse_id": MAIN_WAREHOUSE_ID, "warehouse_name": "Kho Chính",
                "status": "DELIVERED" if posted else "PROCESSING", "picker_id": inv.picker.id,
                "picker_name": inv.picker.name, "packer_id": inv.packer.id, "packer_name": inv.packer.name,
                "shipping_method": inv.shipping_method, "shipper_id": inv.shipper.id if inv.shipper else None,
                "carrier_name": inv.carrier, "shipping_note": None, "posted_at": lts(inv.posted_at),
                "posted_by_id": inv.posted_by.id if posted else None,
                "posted_by_name": inv.posted_by.name if posted else None,
                "created_at": lts(inv.created_at), "updated_at": lts(inv.posted_at or inv.created_at)})
            vat = inv.vat_invoice
            if vat:
                rows["vat_invoices"].append({
                    "id": vat.id, "invoice_number": vat.number, "invoice_series": vat.series,
                    "invoice_date": vat.day.isoformat(), "sales_invoice_id": inv.id, "sales_invoice_number": inv.number,
                    "order_id": o.id, "order_number": o.number, "customer_id": c.id, "customer_name": c.name,
                    "customer_tax_code": c.tax_code, "customer_address": c.address, "currency": "VND",
                    "exchange_rate": 1, "subtotal": vat.subtotal, "vat_total": vat.vat_total,
                    "total_amount": vat.total, "created_by": vat.created_by.name, "status": "ISSUED", "notes": None,
                    "created_at": lts(vat.created_at), "updated_at": lts(vat.created_at),
                    "finance_synced_at": lts(vat.created_at + timedelta(minutes=1)), "adjustment_id": None})
            for il in inv.lines:
                l = il.order_line
                revenue_id += 1
                subtotal = il.qty * l.price
                vat_amount = round(subtotal * l.vat / 100, 2)
                if posted:
                    row = {
                        "id": revenue_id, "invoice_id": inv.id, "invoice_number": inv.number, "invoice_item_id": il.id,
                        "order_id": o.id, "order_number": o.customer_po or o.number, "product_id": l.product.id,
                        "sku": l.product.sku, "product_name": l.product.model, "account_code": "1312",
                        "is_vat_invoiced": 0, "quantity": il.qty, "invoiced_quantity": 0,
                        "remaining_invoice_quantity": il.qty, "currency": "VND", "exchange_rate": 1,
                        "unit_price": l.price, "subtotal": subtotal, "vat_rate": l.vat, "vat_amount": vat_amount,
                        "total_amount": subtotal + vat_amount, "customer_id": c.id, "salesperson_id": o.staff_id,
                        "issue_date": utc(inv.posted_at).date().isoformat(), "vat_invoice_date": None,
                        "created_at": lts(inv.posted_at), "updated_at": lts(inv.posted_at)}
                    if vat:
                        vat_item_id += 1
                        row.update({"account_code": "1311", "is_vat_invoiced": 1, "vat_invoice_id": vat.id,
                                    "vat_invoice_number": vat.number, "vat_invoice_series": vat.series,
                                    "vat_invoice_item_id": vat_item_id, "invoiced_quantity": il.qty,
                                    "remaining_invoice_quantity": 0, "vat_invoice_date": vat.day.isoformat(),
                                    "updated_at": lts(vat.created_at)})
                        rows["vat_invoice_items"].append({
                            "id": vat_item_id, "vat_invoice_id": vat.id, "sales_invoice_item_id": il.id,
                            "order_item_id": l.id, "product_id": l.product.id, "sku": l.product.sku,
                            "product_name": l.product.model, "quantity": il.qty, "unit_price": l.price,
                            "subtotal": subtotal, "vat_percent": l.vat, "vat_amount": vat_amount,
                            "total_amount": subtotal + vat_amount, "revenue_report_item_id": revenue_id,
                            "created_at": lts(vat.created_at), "updated_at": lts(vat.created_at)})
                    rows["revenue_report_items"].append(row)
                rows["invoice_items"].append({
                    "id": il.id, "invoice_id": inv.id, "sku": l.product.sku, "product_name": l.product.model,
                    "quantity": il.qty, "unit_price": l.price, "order_item_id": l.id,
                    "delivery_request_item_id": il.delivery_line.id, "description": l.product.name,
                    "requested_quantity": il.qty, "delivered_quantity": il.qty if posted else 0,
                    "product_id": l.product.id, "created_at": lts(inv.created_at),
                    "updated_at": lts(inv.posted_at or inv.created_at)})
        for i, r in enumerate(rows["sale_report_items"], 1):
            r["id"] = i

        seq = []
        now = lts(datetime.combine(C.END_DATE, datetime.min.time()).replace(hour=17))
        for key, value in sim.n.seq.items():
            if key.startswith(("delivery-", "invoice-")):
                seq.append({"name": key, "next_value": value + 1, "created_at": now, "updated_at": now})
            elif key.startswith("vat-1C"):
                seq.append({"name": "vat-invoice-" + key[4:], "next_value": value + 1, "created_at": now,
                            "updated_at": now})
        rows["document_sequences"] = seq
        return rows

    # =============================================================== inventory
    def inventory(self) -> dict[str, list[dict]]:
        sim = self.sim
        rows: dict[str, list[dict]] = defaultdict(list)
        last_phys, last_avail = {}, {}
        for table, moves, last in (("inventory_stocks_transaction", sim.physical_moves, last_phys),
                                   ("inventory_stocks_reservation_transaction", sim.available_moves, last_avail)):
            balance: dict[int, int] = defaultdict(int)
            for i, m in enumerate(sorted(moves, key=lambda m: m.at), 1):
                # Số dư tính lại theo thứ tự thời gian (engine xử lý theo thứ tự sự kiện trong ngày)
                before = balance[m.product_id]
                balance[m.product_id] = before + m.delta
                rows[table].append({
                    "id": i, "warehouse_id": m.warehouse_id, "product_id": m.product_id, "document_type": m.doc_type,
                    "document_id": m.doc_id, "quantity_delta": m.delta, "balance_before": before,
                    "balance_after": before + m.delta, "notes": m.notes, "created_by": m.created_by,
                    "created_at": nts(m.at)})
                last[m.product_id] = m.at
        start = datetime.combine(C.START_DATE, datetime.min.time()).replace(hour=7)
        for i, p in enumerate(self.md.products, 1):
            rows["inventory_stocks"].append({
                "id": i, "warehouse_id": MAIN_WAREHOUSE_ID, "product_id": p.id, "quantity": sim.on_hand[p.id],
                "updated_at": nts(last_phys.get(p.id, start)), "origin": p.origin})
            rows["inventory_stocks_reservation"].append({
                "id": i, "warehouse_id": MAIN_WAREHOUSE_ID, "product_id": p.id, "quantity": sim.available[p.id],
                "updated_at": nts(last_avail.get(p.id, start))})
        return rows

    # ================================================================ purchase
    def purchase(self) -> dict[str, list[dict]]:
        sim = self.sim
        rows: dict[str, list[dict]] = defaultdict(list)
        alloc_id = 0
        for pr in sim.requisitions:
            rows["purchase_requisitions"].append({
                "id": pr.id, "code": pr.code, "warehouse_id": MAIN_WAREHOUSE_ID, "requested_by": pr.requested_by.id,
                "request_date": nts(pr.created_at), "expected_needed_date": nts(datetime.combine(pr.needed_by, pr.created_at.time())),
                "priority": pr.priority, "reason": pr.reason, "notes": None, "approval_status": "Approved",
                "order_status": "FullyOrdered", "approved_by": pr.approved_by.id, "approved_at": nts(pr.approved_at),
                "rejected_reason": None, "version": 1, "created_at": nts(pr.created_at), "updated_at": nts(pr.approved_at)})
            for l in pr.lines:
                rows["purchase_requisition_items"].append({
                    "id": l.id, "purchase_requisition_id": pr.id, "product_id": l.product.id, "sku": l.product.sku,
                    "product_name": l.product.name, "unit": l.product.unit, "quantity_requested": l.qty,
                    "quantity_approved": l.qty, "quantity_ordered": l.qty, "estimated_unit_cost": dec(l.est_cost),
                    "notes": None})
        for po in sim.purchase_orders:
            sup = po.supplier
            rows["purchase_orders"].append({
                "id": po.id, "code": po.code, "supplier_id": sup.id, "warehouse_id": MAIN_WAREHOUSE_ID,
                "status": po.status, "order_date": nts(po.created_at),
                "expected_delivery_date": nts(datetime.combine(po.expected, po.created_at.time())),
                "currency": po.currency, "subtotal_amount": dec(po.subtotal), "tax_amount": dec(po.tax),
                "discount_amount": "0.0", "grand_total": dec(po.subtotal + po.tax),
                "payment_terms": f"Net {sup.payment_days} ngày kể từ ngày nhận hàng" if sup.kind == "domestic"
                else f"T/T {sup.payment_days} ngày sau ngày B/L",
                "notes": po.notes + (f" | Tỷ giá tham chiếu {po.exchange_rate:,.0f} VND/USD" if po.currency == "USD" else ""),
                "created_by": po.created_by.id, "approved_by": po.approved_by.id, "approved_at": nts(po.approved_at),
                "version": 1, "created_at": nts(po.created_at), "updated_at": nts(po.updated_at or po.approved_at)})
            for l in po.lines:
                rows["purchase_order_items"].append({
                    "id": l.id, "purchase_order_id": po.id, "product_id": l.product.id, "sku": l.product.sku,
                    "product_name": l.product.name, "unit": l.product.unit, "unit_cost": dec(l.unit_cost),
                    "quantity_ordered": l.qty, "quantity_received": l.received, "quantity_invoiced": l.invoiced,
                    "tax_rate": dec(l.tax_rate), "discount_percent": "0.0", "subtotal": dec(l.qty * l.unit_cost),
                    "notes": None})
                alloc_id += 1
                rows["purchase_order_pr_allocations"].append({
                    "id": alloc_id, "purchase_order_id": po.id, "purchase_order_item_id": l.id,
                    "purchase_requisition_id": po.requisition.id, "purchase_requisition_item_id": l.pr_line.id,
                    "allocated_quantity": l.qty, "status": "Active", "created_at": nts(po.created_at),
                    "updated_at": nts(po.created_at)})
        gr_item_id = 0
        gr_item_of: dict[tuple[int, int], int] = {}
        for gr in sim.goods_receipts:
            rows["goods_receipts"].append({
                "id": gr.id, "code": gr.code, "purchase_order_id": gr.po.id, "warehouse_id": MAIN_WAREHOUSE_ID,
                "received_by": gr.received_by.id, "received_at": nts(gr.created_at),
                "delivery_note_number": gr.delivery_note, "status": "Confirmed", "inventory_sync_status": "Synced",
                "notes": None, "version": 1, "created_at": nts(gr.created_at), "updated_at": nts(gr.created_at)})
            for l, qty, rejected in gr.lines:
                gr_item_id += 1
                gr_item_of[(gr.id, l.id)] = gr_item_id
                rows["goods_receipt_items"].append({
                    "id": gr_item_id, "goods_receipt_id": gr.id, "purchase_order_item_id": l.id,
                    "product_id": l.product.id, "sku": l.product.sku, "product_name": l.product.name,
                    "unit": l.product.unit, "quantity_ordered": l.qty, "quantity_received": qty,
                    "quantity_rejected": rejected, "unit_cost": dec(l.unit_cost), "batch_number": None,
                    "expiry_date": None, "notes": "Móp hộp khi vận chuyển" if rejected else None})
        pinv_item_id = 0
        for pi in sim.purchase_invoices:
            subtotal = sum(l.unit_cost * q for l, q in pi.lines)
            tax = sum(l.unit_cost * q * l.tax_rate / 100 for l, q in pi.lines)
            rows["purchase_invoices"].append({
                "id": pi.id, "code": pi.code, "purchase_order_id": pi.po.id, "goods_receipt_id": pi.receipt.id,
                "supplier_id": pi.po.supplier.id, "supplier_invoice_number": pi.supplier_invoice_no,
                "invoice_date": nts(pi.created_at), "due_date": nts(datetime.combine(pi.due, pi.created_at.time())),
                "subtotal_amount": dec(subtotal), "tax_amount": dec(tax), "grand_total": dec(subtotal + tax),
                "status": pi.status, "matching_status": "Matched", "notes": None,
                "created_at": nts(pi.created_at), "updated_at": nts(pi.created_at)})
            for l, q in pi.lines:
                pinv_item_id += 1
                rows["purchase_invoice_items"].append({
                    "id": pinv_item_id, "purchase_invoice_id": pi.id, "purchase_order_item_id": l.id,
                    "goods_receipt_item_id": gr_item_of.get((pi.receipt.id, l.id)), "product_id": l.product.id,
                    "sku": l.product.sku, "product_name": l.product.name, "unit": l.product.unit,
                    "quantity_invoiced": q, "unit_cost": dec(l.unit_cost), "tax_rate": dec(l.tax_rate),
                    "subtotal": dec(l.unit_cost * q), "matching_status": "Matched"})
        return rows

    # ================================================================= finance
    def finance(self) -> dict[str, list[dict]]:
        sim = self.sim
        rows: dict[str, list[dict]] = defaultdict(list)
        ledger_id = 0

        def ledger(source_key, source_type, source_id, number, day, debit, credit, amount, customer_id, desc, at):
            nonlocal ledger_id
            ledger_id += 1
            rows["general_ledger_entries"].append({
                "id": ledger_id, "source_key": source_key, "source_type": source_type, "source_id": source_id,
                "document_number": number, "entry_date": day.isoformat(), "debit_account_id": self.acc[debit],
                "credit_account_id": self.acc[credit], "amount": round(amount, 2), "currency": "VND",
                "customer_id": customer_id, "description": desc, "created_at": lts(at), "updated_at": lts(at)})

        events = []
        for r in sim.receivables:
            events.append((r.vat_invoice.created_at, "vat", r))
        for rc in sim.receipts:
            events.append((rc.created_at, "receipt", rc))
        events.sort(key=lambda e: e[0])

        last_paid: dict[int, datetime] = {}
        for rc in sim.receipts:
            for rec, _ in rc.allocations:
                last_paid[rec.id] = max(last_paid.get(rec.id, rc.created_at), rc.created_at)
        for r in sim.receivables:
            v, c = r.vat_invoice, r.customer
            synced = v.created_at + timedelta(minutes=1)
            status = "PAID" if r.outstanding <= 0.01 else ("PARTIALLY_PAID" if r.paid > 0 else "OPEN")
            rows["customer_receivables"].append({
                "id": r.id, "source_vat_invoice_id": v.id, "source_sales_invoice_id": v.sales_invoice.id,
                "source_type": "VAT_INVOICE", "source_key": f"VAT:{v.id}", "customer_id": c.id,
                "customer_name": c.name, "customer_tax_code": c.tax_code, "invoice_number": v.number,
                "invoice_series": v.series, "invoice_date": v.day.isoformat(), "due_date": r.due.isoformat(),
                "currency": "VND", "account_id": self.acc["1311"], "original_amount": round(r.amount, 2),
                "paid_amount": round(r.paid, 2), "outstanding_amount": max(0.0, r.outstanding), "status": status,
                "created_at": lts(synced), "updated_at": lts(last_paid.get(r.id, synced))})

        line_id, alloc_id = 0, 0
        for kind, obj in ((k, o) for _, k, o in events):
            if kind == "vat":
                v, c = obj.vat_invoice, obj.customer
                at = v.created_at + timedelta(minutes=1)
                ledger(f"VAT_INVOICE:{v.id}:subtotal", "VAT_INVOICE", v.id, v.number, v.day, "1311", "511",
                       v.subtotal, c.id, f"Hóa đơn VAT {v.number}", at)
                ledger(f"VAT_INVOICE:{v.id}:vat_amount", "VAT_INVOICE", v.id, v.number, v.day, "1311", "3331",
                       v.vat_total, c.id, f"Hóa đơn VAT {v.number}", at)
                continue
            rc = obj
            c = rc.customer
            vtype = "CASH_RECEIPT" if rc.channel == "CASH" else "BANK_RECEIPT"
            primary = next((ct.name for ct in c.contacts if ct.is_primary), c.name if c.type == "Retail" else None)
            rows["finance_vouchers"].append({
                "id": rc.id, "idempotency_key": str(uuid.UUID(int=self.sim.rng.getrandbits(128), version=4)),
                "voucher_number": rc.number, "voucher_type": vtype, "channel": rc.channel,
                "voucher_date": rc.day.isoformat(), "accounting_date": rc.day.isoformat(), "party_type": "CUSTOMER",
                "party_id": c.id, "party_code": c.code, "party_name": c.name, "party_address": c.address,
                "contact_name": primary, "description": rc.description, "reason_code": None, "employee_name": None,
                "cash_fund_id": 1 if rc.channel == "CASH" else None, "bank_account_id": rc.bank_account_id,
                "destination_bank_account_id": None, "total_amount": rc.amount, "currency": "VND",
                "exchange_rate": 1, "status": "POSTED", "created_by_id": rc.staff.id, "created_by_name": rc.staff.name,
                "posted_by_id": rc.staff.id, "posted_by_name": rc.staff.name, "posted_at": lts(rc.created_at),
                "cancelled_at": None, "created_at": lts(rc.created_at), "updated_at": lts(rc.created_at)})
            line_id += 1
            debit = "1111" if rc.channel == "CASH" else "1121"
            rows["finance_voucher_lines"].append({
                "id": line_id, "voucher_id": rc.id, "debit_account_id": self.acc[debit],
                "credit_account_id": self.acc["1311"], "amount": rc.amount, "description": rc.description,
                "created_at": lts(rc.created_at), "updated_at": lts(rc.created_at)})
            ledger(f"VOUCHER_LINE:{line_id}", "VOUCHER", rc.id, rc.number, rc.day, debit, "1311", rc.amount,
                   c.id, rc.description, rc.created_at)
            for rec, amount in rc.allocations:
                alloc_id += 1
                rows["receivable_allocations"].append({
                    "id": alloc_id, "voucher_id": rc.id, "receivable_id": rec.id, "amount": amount,
                    "allocated_at": lts(rc.created_at), "created_at": lts(rc.created_at),
                    "updated_at": lts(rc.created_at)})
        return rows


def sim_invoices_of(sim: Simulation, delivery):
    cache = getattr(sim, "_inv_by_delivery", None)
    if cache is None:
        cache = defaultdict(list)
        for inv in sim.sales_invoices:
            cache[inv.delivery.id].append(inv)
        sim._inv_by_delivery = cache
    return cache[delivery.id]
