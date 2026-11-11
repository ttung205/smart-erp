"""Chạy một lượt đầy đủ trên gateway giả lập: kiểm tra thứ tự gọi, vai trò thực hiện và payload."""
import json
import random
import unittest
from datetime import date

import httpx

from demo_traffic.api import ApiClient
from demo_traffic.config import Settings
from demo_traffic.flows import Erp, run_day

USERS = {"sale@erp.local": 1, "sale.manager@erp.local": 2, "finance@erp.local": 3, "finance.manager@erp.local": 4,
         "logistic@erp.local": 5, "purchase@erp.local": 6, "purchase.manager@erp.local": 7}


class FakeGateway:
    def __init__(self):
        self.calls: list[tuple[str, str, str, dict]] = []  # (email, method, path, body)
        self.tokens = {}

    def __call__(self, request: httpx.Request) -> httpx.Response:
        path = request.url.path.removeprefix("/api/v1")
        body = json.loads(request.content) if request.content else {}
        if path == "/auth/login":
            token = f"t-{body['email']}"
            self.tokens[token] = body["email"]
            return self.ok({"token": token, "expires_in": 3600, "user": {"id": USERS[body["email"]], "name": body["email"]}})
        email = self.tokens[request.headers["Authorization"].removeprefix("Bearer ")]
        assert request.headers["X-Correlation-ID"].startswith("demo_")
        self.calls.append((email, request.method, path, body))
        return self.ok(self.route(request.method, path, body, request.url.params))

    @staticmethod
    def ok(data):
        return httpx.Response(200, json={"success": True, "data": data})

    def route(self, method, path, body, params):
        if path == "/catalog/customers":
            return [{"id": 7, "code": "DI-1", "name": "Đại lý A", "customer_type": "Distributor", "address": "Hà Nội", "status": "ACTIVE"}]
        if path == "/catalog/products":
            return [{"id": 1, "sku": "AC-1", "designation": "Điều hòa", "gross_price": "10000000.00", "cost_price": "7000000.00",
                     "min_stock": 50, "brand": "Daikin", "unit": "Bộ"}]
        if path == "/catalog/suppliers":
            return [{"id": 9, "code": "NCC-9", "name": "Daikin Việt Nam", "status": "ACTIVE"}]
        if path == "/inventory/warehouses":
            return [{"id": 1, "name": "Kho Chính"}]
        if path == "/inventory/stocks":
            return [{"product_id": 1, "warehouse_id": 1, "available": 30}]
        if path == "/sales/orders" and method == "POST":
            return {"id": 100, "items": [{"id": 501, "product_id": 1, "sku": "AC-1", "quantity": str(i["quantity"]),
                                          "selling_price": f"{i['selling_price']:.2f}"} for i in body["items"]]}
        if path == "/sales/deliveries" and method == "POST":
            return {"id": 200, "items": [{"id": 601, "order_item_id": 501, "product_id": 1, "sku": "AC-1",
                                          "quantity": body["items"][0]["quantity"]}]}
        if path == "/sales/invoices" and method == "POST":
            return {"id": 300, "invoice_number": "INV-DI-1-101126"}
        if path == "/sales/vat-invoices/unbilled-items":
            return [{"id": 700, "invoice_item_id": 801, "remaining_invoice_quantity": "5.00"}]
        if path == "/purchase/orders" and method == "POST":
            return {"id": 40, "code": "PO-1", "items": [{"id": 41, "product_id": 1, "sku": "AC-1", "unit": "Bộ",
                                                         "quantity_ordered": body["items"][0]["quantity_ordered"], "unit_cost": 7000000.0}]}
        if path == "/purchase/receipts" and method == "POST":
            return {"id": 50}
        if path == "/finance/master-data":
            return {"bank_accounts": [{"id": 3}], "cash_funds": []}
        if path == "/finance/customer-receivables":
            return [{"id": 90, "customer_id": 7, "customer_name": "Đại lý A", "invoice_number": "VAT-1", "outstanding_amount": "5000000.00"}]
        if path == "/finance/vouchers" and method == "POST":
            return {"id": 60, "voucher_number": "BT-202611-00001"}
        return {"id": 1}


class FlowTest(unittest.TestCase):
    def test_full_day_runs_each_step_with_the_right_role(self):
        gateway = FakeGateway()
        settings = Settings(gateway_url="http://gateway:8000", password="x", warehouse_id=1, max_purchase_orders_per_day=2)
        rng = random.Random(7)
        rng.random = lambda: 0.0  # mọi khoản nợ quá hạn đều được trả trong test
        with ApiClient(settings, transport=httpx.MockTransport(gateway)) as api:
            stats = run_day(Erp(api, settings), 1, date(2026, 11, 10), rng)

        self.assertEqual(stats, {"orders": 1, "order_failed": 0, "purchase_orders": 1, "po_failed": 0,
                                 "receipts": 1, "receipt_failed": 0})
        writes = [(email, method, path) for email, method, path, _ in gateway.calls if method != "GET"]
        self.assertEqual(writes[:9], [
            ("sale@erp.local", "POST", "/sales/orders"),
            ("sale@erp.local", "POST", "/sales/orders/100/submit-approval"),
            ("sale.manager@erp.local", "POST", "/sales/orders/100/approve"),
            ("sale@erp.local", "POST", "/sales/deliveries"),
            ("sale.manager@erp.local", "PUT", "/sales/deliveries/200/status"),
            ("finance@erp.local", "POST", "/sales/invoices"),
            ("logistic@erp.local", "PUT", "/sales/invoices/300/assign-logistics"),
            ("logistic@erp.local", "POST", "/sales/invoices/300/warehouse-post"),
            ("finance.manager@erp.local", "POST", "/sales/vat-invoices"),
        ])
        self.assertIn(("purchase.manager@erp.local", "POST", "/purchase/orders/40/approve"), writes)
        self.assertIn(("purchase@erp.local", "POST", "/purchase/receipts/50/confirm"), writes)
        self.assertIn(("finance.manager@erp.local", "POST", "/finance/vouchers/60/post"), writes)

        body = {path: b for _, method, path, b in gateway.calls if method != "GET"}
        sale_price = body["/sales/orders"]["items"][0]["selling_price"]
        self.assertEqual(sale_price, 8200000.0)  # nhà phân phối chiết khấu 18%
        invoice_item = body["/sales/invoices"]["items"][0]
        self.assertEqual((invoice_item["delivery_request_item_id"], invoice_item["order_item_id"], invoice_item["unit_price"]),
                         (601, 501, sale_price))
        self.assertTrue(body["/sales/invoices"]["send_to_warehouse"])
        self.assertEqual(body["/sales/vat-invoices"]["items"], [{"revenue_report_item_id": 700, "sales_invoice_item_id": 801, "quantity": 5.0}])
        po_line = body["/purchase/orders"]["items"][0]
        self.assertEqual((po_line["unit_cost"], body["/purchase/orders"]["supplier_id"]), (7000000.0, 9))
        voucher = body["/finance/vouchers"]
        self.assertEqual((voucher["total_amount"], voucher["allocations"]), (5000000.0, [{"receivable_id": 90, "amount": 5000000.0}]))

    def test_one_failed_order_does_not_stop_the_day(self):
        gateway = FakeGateway()
        original = gateway.route

        def failing(method, path, body, params):
            if path == "/sales/invoices" and method == "POST":
                raise _Reject()
            return original(method, path, body, params)

        class _Reject(Exception):
            pass

        def handler(request):
            try:
                return gateway(request)
            except _Reject:
                return httpx.Response(409, json={"success": False, "error": {"code": "INSUFFICIENT_STOCK", "message": "Thiếu hàng"}})

        gateway.route = failing
        settings = Settings(gateway_url="http://gateway:8000", password="x")
        with ApiClient(settings, transport=httpx.MockTransport(handler)) as api:
            stats = run_day(Erp(api, settings), 2, date(2026, 11, 10), random.Random(1))
        self.assertEqual(stats["order_failed"], 2)
        self.assertEqual(stats["orders"], 0)


if __name__ == "__main__":
    unittest.main()
