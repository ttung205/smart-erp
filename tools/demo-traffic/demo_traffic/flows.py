"""Các luồng nghiệp vụ chạy qua API thật: bán hàng, bổ sung hàng, thu tiền.

Mỗi bước dùng đúng vai trò (sale lập, sale.manager duyệt, finance lập hóa đơn, logistic xuất kho...), nên dữ liệu
sinh ra đi qua đầy đủ logic nghiệp vụ: giữ hàng nguyên tử, xuất kho, outbox, công nợ.
"""
from __future__ import annotations

import logging
import random
import uuid
from datetime import date

from . import policy
from .api import ApiClient
from .config import Settings
from .policy import OrderPlan

log = logging.getLogger("demo_traffic.flows")


class Erp:
    def __init__(self, api: ApiClient, settings: Settings):
        self.api = api
        self.s = settings
        self.a = settings.accounts

    # ------------------------------------------------------------------ dữ liệu tham chiếu
    def customers(self) -> list[dict]:
        rows = self.api.get(self.a.sales, "/catalog/customers")
        return [c for c in rows if (c.get("status") or "ACTIVE") == "ACTIVE"]

    def products(self) -> list[dict]:
        rows = self.api.get(self.a.purchase, "/catalog/products")  # vai trò mua hàng được xem giá vốn
        return [p for p in rows if float(p.get("gross_price") or 0) > 0]

    def suppliers(self) -> list[dict]:
        rows = self.api.get(self.a.purchase, "/catalog/suppliers")
        rows = rows.get("data", rows) if isinstance(rows, dict) else rows
        return [s for s in rows if (s.get("status") or "ACTIVE") == "ACTIVE"]

    def warehouse_name(self) -> str:
        for w in self.api.get(self.a.logistic, "/inventory/warehouses"):
            if int(w["id"]) == self.s.warehouse_id:
                return w["name"]
        raise RuntimeError(f"Không tìm thấy kho id={self.s.warehouse_id}")

    def available_stock(self) -> dict[int, int]:
        rows = self.api.get(self.a.logistic, "/inventory/stocks", warehouseId=self.s.warehouse_id, pageSize=1000)
        return {int(r["product_id"]): int(r["available"]) for r in rows}

    # ------------------------------------------------------------------ bán hàng
    def sell(self, plan: OrderPlan, warehouse_name: str, today: date) -> str:
        """Đơn bán → duyệt → yêu cầu giao → hóa đơn giữ hàng → xuất kho → hóa đơn VAT (sinh công nợ)."""
        a, customer = self.a, plan.customer
        order = self.api.post(a.sales, "/sales/orders", {
            "customer_id": customer["id"],
            "order_date": today.isoformat(),
            "shipping_address": customer.get("address"),
            "items": [{"product_id": line.product["id"], "sku": line.product["sku"], "quantity": line.quantity,
                       "selling_price": line.selling_price, "vat_percent": 10} for line in plan.lines],
        })
        self.api.post(a.sales, f"/sales/orders/{order['id']}/submit-approval")
        self.api.post(a.sales_manager, f"/sales/orders/{order['id']}/approve")

        delivery = self.api.post(a.sales, "/sales/deliveries", {
            "order_id": order["id"],
            "warehouse_id": self.s.warehouse_id,
            "warehouse_name": warehouse_name,
            "shipping_address": customer.get("address") or customer["name"],
            "items": [{"order_item_id": item["id"], "product_id": item["product_id"], "sku": item["sku"],
                       "quantity": float(item["quantity"])} for item in order["items"]],
        })
        self.api.put(a.sales_manager, f"/sales/deliveries/{delivery['id']}/status", {"status": "APPROVED"})

        price_by_order_item = {item["id"]: float(item["selling_price"]) for item in order["items"]}
        invoice = self.api.post(a.finance, "/sales/invoices", {
            "delivery_request_id": delivery["id"],
            "invoice_date": today.isoformat(),
            "send_to_warehouse": True,
            "items": [{"delivery_request_item_id": item["id"], "order_item_id": item["order_item_id"],
                       "product_id": item.get("product_id"), "sku": item["sku"], "quantity": float(item["quantity"]),
                       "unit_price": price_by_order_item[item["order_item_id"]]} for item in delivery["items"]],
        })
        self.api.put(a.logistic, f"/sales/invoices/{invoice['id']}/assign-logistics",
                     {"shipping_method": "CUSTOMER_PICKUP", "status": "READY_TO_SHIP"})
        self.api.post(a.logistic, f"/sales/invoices/{invoice['id']}/warehouse-post",
                      {"warehouse_id": self.s.warehouse_id})

        unbilled = self.api.get(a.finance_manager, "/sales/vat-invoices/unbilled-items",
                                invoice_id=invoice["id"], per_page=200)
        if unbilled:
            self.api.post(a.finance_manager, "/sales/vat-invoices", {
                "sales_invoice_id": invoice["id"],
                "invoice_date": today.isoformat(),
                "items": [{"revenue_report_item_id": r["id"], "sales_invoice_item_id": r["invoice_item_id"],
                           "quantity": float(r["remaining_invoice_quantity"])} for r in unbilled],
            })
        return invoice.get("invoice_number") or str(invoice["id"])

    # ------------------------------------------------------------------ mua hàng
    def replenish(self, supplier: dict, lines: list[tuple[dict, int]], today: date) -> str:
        """PO → gửi duyệt → duyệt → phiếu nhập → xác nhận (inventory nhận event GoodsReceived và tăng tồn)."""
        a = self.a
        buyer = self.api.user(a.purchase)
        po = self.api.post(a.purchase, "/purchase/orders", {
            "supplier_id": supplier["id"],
            "warehouse_id": self.s.warehouse_id,
            "order_date": today.isoformat(),
            "created_by": buyer["id"],
            "items": [{"product_id": p["id"], "sku": p["sku"], "product_name": p.get("designation") or p["sku"],
                       "unit": p.get("unit") or "Cái", "unit_cost": float(p.get("cost_price") or p["gross_price"]),
                       "quantity_ordered": quantity, "tax_rate": 10} for p, quantity in lines],
        })
        self.api.post(a.purchase, f"/purchase/orders/{po['id']}/submit")
        self.api.post(a.purchase_manager, f"/purchase/orders/{po['id']}/approve")

        receipt = self.api.post(a.purchase, "/purchase/receipts", {
            "purchase_order_id": po["id"],
            "warehouse_id": self.s.warehouse_id,
            "received_by": buyer["id"],
            "items": [{"purchase_order_item_id": item["id"], "product_id": item["product_id"], "sku": item["sku"],
                       "product_name": item.get("product_name") or item["sku"], "unit": item.get("unit") or "Cái",
                       "quantity_received": int(item["quantity_ordered"]), "unit_cost": float(item["unit_cost"])}
                      for item in po["items"]],
        })
        self.api.post(a.purchase, f"/purchase/receipts/{receipt['id']}/confirm")
        return po.get("code") or str(po["id"])

    # ------------------------------------------------------------------ thu tiền
    def overdue_receivables(self, max_pages: int = 5) -> list[dict]:
        rows: list[dict] = []
        for page in range(1, max_pages + 1):
            batch = self.api.get(self.a.finance_manager, "/finance/customer-receivables",
                                 status="OVERDUE", per_page=200, page=page)
            rows.extend(batch)
            if len(batch) < 200:
                break
        return rows

    def bank_account_id(self) -> int:
        accounts = self.api.get(self.a.finance_manager, "/finance/master-data")["bank_accounts"]
        if not accounts:
            raise RuntimeError("Chưa có tài khoản ngân hàng đang hoạt động trong finance-service")
        return int(accounts[0]["id"])

    def collect(self, customer_id: int, receivables: list[dict], bank_account_id: int, today: date) -> str:
        """Phiếu thu ngân hàng phân bổ vào các khoản nợ của một khách, rồi ghi sổ."""
        total = round(sum(float(r["outstanding_amount"]) for r in receivables), 2)
        first = receivables[0]
        voucher = self.api.post(self.a.finance_manager, "/finance/vouchers", {
            "idempotency_key": str(uuid.uuid4()),
            "voucher_type": "BANK_RECEIPT",
            "channel": "BANK",
            "voucher_date": today.isoformat(),
            "party_type": "CUSTOMER",
            "party_id": customer_id,
            "party_name": first.get("customer_name") or f"Khách hàng {customer_id}",
            "description": "Thu tiền hàng theo hóa đơn " + ", ".join(r["invoice_number"] for r in receivables[:5]),
            "bank_account_id": bank_account_id,
            "total_amount": total,
            "allocations": [{"receivable_id": r["id"], "amount": float(r["outstanding_amount"])} for r in receivables],
        })
        self.api.post(self.a.finance_manager, f"/finance/vouchers/{voucher['id']}/post")
        return voucher.get("voucher_number") or str(voucher["id"])


def run_day(erp: Erp, orders: int, today: date, rng: random.Random) -> dict[str, int]:
    """Một lượt đầy đủ: bán, bổ sung hàng, thu nợ quá hạn."""
    stats = run_sales(erp, orders, today, rng)
    stats.update(replenish_stock(erp, today))
    stats.update(collect_payments(erp, today, rng))
    return stats


def run_sales(erp: Erp, orders: int, today: date, rng: random.Random) -> dict[str, int]:
    """Bán `orders` đơn trong giới hạn tồn khả dụng. Lỗi một chứng từ không dừng cả lượt."""
    stats = {"orders": 0, "order_failed": 0}
    if orders <= 0:
        return stats
    products = erp.products()
    available = erp.available_stock()
    warehouse = erp.warehouse_name()
    by_type: dict[str, list[dict]] = {}
    for customer in erp.customers():
        by_type.setdefault(policy.customer_type(customer), []).append(customer)

    for _ in range(orders):
        plan = policy.plan_order(by_type, products, available, rng, sunday=today.weekday() == 6)
        if plan is None:
            log.warning("Không còn mặt hàng đủ tồn khả dụng để lên đơn; dừng bán ở lượt này")
            break
        try:
            number = erp.sell(plan, warehouse, today)
            stats["orders"] += 1
            log.info("Đã bán %s cho %s (%d dòng)", number, plan.customer.get("code"), len(plan.lines))
        except Exception:
            stats["order_failed"] += 1
            log.exception("Tạo chuỗi chứng từ bán cho %s lỗi", plan.customer.get("code"))
    return stats


def replenish_stock(erp: Erp, today: date) -> dict[str, int]:
    """Đặt và nhập hàng cho sản phẩm dưới mức tồn tối thiểu, mỗi NCC một PO. Chạy mỗi ngày một lần: inventory
    tăng tồn khi xử lý event GoodsReceived (bất đồng bộ), chạy dày hơn sẽ đặt trùng."""
    stats = {"purchase_orders": 0, "po_failed": 0}
    products = erp.products()
    suppliers = erp.suppliers()
    if not suppliers:
        log.warning("Không có nhà cung cấp đang hoạt động; bỏ qua bổ sung hàng")
        return stats
    short = policy.replenishment(products, erp.available_stock(), limit=erp.s.max_purchase_orders_per_day * 10)
    by_supplier: dict[int, tuple[dict, list[tuple[dict, int]]]] = {}
    for product, quantity in short:
        supplier = policy.supplier_for(product, suppliers)
        by_supplier.setdefault(supplier["id"], (supplier, []))[1].append((product, quantity))
    for supplier, lines in list(by_supplier.values())[:erp.s.max_purchase_orders_per_day]:
        try:
            code = erp.replenish(supplier, lines[:10], today)
            stats["purchase_orders"] += 1
            log.info("Đã đặt và nhập %s từ %s (%d dòng)", code, supplier.get("code"), len(lines[:10]))
        except Exception:
            stats["po_failed"] += 1
            log.exception("Tạo đơn mua cho NCC %s lỗi", supplier.get("code"))
    return stats


def collect_payments(erp: Erp, today: date, rng: random.Random) -> dict[str, int]:
    """Khách trả một phần các khoản nợ quá hạn: mỗi khách một phiếu thu ngân hàng đã ghi sổ."""
    stats = {"receipts": 0, "receipt_failed": 0}
    bank_account_id = erp.bank_account_id()
    paying = list(policy.receivables_to_collect(erp.overdue_receivables(), rng).items())
    limit = erp.s.max_receipts_per_day
    if len(paying) > limit:
        log.info("Có %d khách trả nợ quá hạn, hôm nay thu %d khách; phần còn lại để các ngày sau", len(paying), limit)
    for customer_id, items in paying[:limit]:
        try:
            erp.collect(customer_id, items, bank_account_id, today)
            stats["receipts"] += 1
        except Exception:
            stats["receipt_failed"] += 1
            log.exception("Thu tiền khách %s lỗi", customer_id)
    return stats
