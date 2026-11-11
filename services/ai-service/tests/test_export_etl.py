"""Test ETL kho phân tích đọc qua endpoint export (không cần MySQL hay service thật).

    .venv/Scripts/python -m unittest tests.test_export_etl
"""
from __future__ import annotations

import unittest
from unittest import mock

import httpx

from app import config, export_client, warehouse
from app.export_client import DependencyUnavailable, ExportClient

ITEMS = {
    "/api/internal/exports/products": [{
        "id": 1, "sku": "CFS-13", "designation": "Comfee 1.5HP", "description": "Điều hòa", "category": "Điều hòa",
        "brand": "Comfee", "com": "Việt Nam", "unit": "Bộ", "gross_price": "9000000.00", "cost_price": "6000000.00",
        "min_stock": 8, "status": "ACTIVE", "created_at": "2026-01-01 00:00:00"}],
    "/api/internal/exports/customers": [{
        "id": 7, "code": "DI-1", "name": "Đại lý A", "customer_type": "DISTRIBUTOR", "address": "1 Lê Lợi, Thành phố Hà Nội",
        "tax_code": "01", "phone": "09", "email": "a@x.vn", "credit_limit": "100000000.00", "status": "ACTIVE",
        "created_at": "2026-01-01 00:00:00"}],
    "/api/internal/exports/suppliers": [],
    "/api/internal/exports/staff": [{"id": 3, "name": "Sale 1", "email": "s@erp.local", "roles": "Kinh doanh"}],
    "/api/internal/exports/revenue-items": [{
        "id": 1, "issue_date": "2026-10-05", "invoice_number": "INV-1", "order_id": 9, "order_number": "SO-9",
        "customer_id": 7, "product_id": 1, "quantity": "2.00", "unit_price": "9000000.00", "subtotal": "18000000.00",
        "vat_amount": "1440000.00", "salesperson_id": 3, "is_vat_invoiced": True}],
    "/api/internal/exports/orders": [{
        "id": 9, "order_number": "SO-9", "order_date": "05/10/2026", "customer_id": 7, "status": "CONFIRMED",
        "subtotal": "18000000.00", "total_amount": "19440000.00", "salesperson": "3", "quotation_id": None}],
    "/api/internal/exports/order-lines": [],
    "/api/internal/exports/receivables": [{
        "id": 1, "customer_id": 7, "invoice_number": "INV-1", "invoice_date": "2026-10-05", "due_date": "2026-11-04",
        "original_amount": "19440000.00", "paid_amount": "0.00", "outstanding_amount": "19440000.00", "status": "OPEN"}],
    "/internal/analytics/stock-balances": [
        {"id": 1, "warehouse_id": 1, "product_id": 1, "quantity": 10},
        {"id": 2, "warehouse_id": 2, "product_id": 1, "quantity": 5}],
    "/internal/analytics/reservation-balances": [{"id": 1, "warehouse_id": 1, "product_id": 1, "quantity": 12}],
    "/internal/analytics/stock-moves": [{
        "id": 1, "created_at": "2026-10-05T03:00:00", "product_id": 1, "document_type": "SALES_INVOICE",
        "document_id": "INV-1", "quantity_delta": -2, "warehouse_id": 1, "balance_after": 8, "created_by": 3}],
    # .NET bỏ trường null: PO chưa nhận hàng không có expected_date/first_receipt/last_receipt
    "/internal/analytics/po-lines": [{
        "id": 1, "po_id": 4, "po_code": "PO-1", "order_date": "2026-10-01T08:00:00", "status": "Approved",
        "supplier_id": 2, "currency": "VND", "product_id": 1, "qty_ordered": 6, "qty_received": 0,
        "unit_cost": 6000000.0}],
}

URLS = {name: f"http://{name}" for name in config.SERVICE_URLS}


def _handler(request: httpx.Request) -> httpx.Response:
    assert request.headers["X-Internal-Service-Key"] == "test-key"
    items = ITEMS[request.url.path]
    return httpx.Response(200, json={"success": True, "data": {"items": items, "next_after_id": None}})


class FakeTarget:
    """Ghi lại các dòng ETL chèn vào bảng *_new thay cho MySQL."""

    def __init__(self):
        self.inserted: dict[str, list[tuple]] = {}

    def execute(self, sql, params=None):
        result = mock.Mock()
        result.fetchone.return_value = ("2026-10-05",) if sql.startswith("select max(d)") else (0,)
        return result

    def executemany(self, sql, rows):
        table = sql.split()[2].removesuffix("_new")
        self.inserted.setdefault(table, []).extend(rows)


@mock.patch.object(config, "INTERNAL_SERVICE_KEY", "test-key")
@mock.patch.dict(config.SERVICE_URLS, URLS)
class ExportEtlTest(unittest.TestCase):
    def test_build_maps_export_rows_into_read_model(self):
        target = FakeTarget()
        with ExportClient(transport=httpx.MockTransport(_handler)) as source:
            counts = warehouse.build(target, source)

        self.assertEqual(counts["fact_sales"], 1)
        sale = target.inserted["fact_sales"][0]
        self.assertEqual(sale[0], "2026-10-05")
        self.assertEqual(sale[8], 18000000.0)          # doanh thu đổi chuỗi DECIMAL sang số
        self.assertEqual(sale[10], 2 * 6000000.0)      # giá vốn = SL x giá vốn sản phẩm
        self.assertEqual(sale[12], 1)
        self.assertEqual(target.inserted["fact_order"][0][2], "2026-10-05")  # dd/mm/yyyy -> ISO
        self.assertEqual(target.inserted["fact_order"][0][7], 3)
        self.assertEqual(target.inserted["stock"], [(1, 15, 12)])            # cộng các kho được bán
        self.assertEqual(target.inserted["stock_move"][0], ("2026-10-05", 1, "SALES_INVOICE", "INV-1", -2, 1, 1, 8, 3))
        self.assertEqual(target.inserted["dim_customer"][0][4], "Thành phố Hà Nội")
        po_line = target.inserted["fact_po_line"][0]
        self.assertEqual(po_line[2], "2026-10-01")
        self.assertEqual((po_line[3], po_line[11], po_line[12]), (None, None, None))

    def test_pages_until_next_after_id_is_null(self):
        calls = []

        def paged(request):
            after = int(request.url.params["after_id"])
            calls.append(after)
            nxt = 2 if after == 0 else None
            return httpx.Response(200, json={"success": True, "data": {"items": [{"id": after + 1}], "next_after_id": nxt}})

        with ExportClient(transport=httpx.MockTransport(paged)) as source:
            items = source.fetch_all("catalog", "products")
        self.assertEqual(calls, [0, 2])
        self.assertEqual(len(items), 2)

    @mock.patch.object(export_client.time, "sleep")
    def test_retries_server_errors_then_fails(self, _sleep):
        attempts = []

        def failing(request):
            attempts.append(1)
            return httpx.Response(503)

        with ExportClient(transport=httpx.MockTransport(failing)) as source:
            with self.assertRaises(DependencyUnavailable):
                source.fetch_all("sales", "orders")
        self.assertEqual(len(attempts), export_client.MAX_RETRIES + 1)

    def test_client_error_is_not_retried(self):
        attempts = []

        def forbidden(request):
            attempts.append(1)
            return httpx.Response(403, json={"success": False})

        with ExportClient(transport=httpx.MockTransport(forbidden)) as source:
            with self.assertRaises(DependencyUnavailable):
                source.fetch_all("finance", "receivables")
        self.assertEqual(len(attempts), 1)


if __name__ == "__main__":
    unittest.main()
