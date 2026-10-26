"""Chạy thử mọi tool trên kho phân tích MySQL thật (cần `docker compose up -d mysql` và đã đồng bộ kho).

    .venv/Scripts/python -m tests.smoke_tools
"""
import json
import sys

from app.auth import Actor
from app.tools import run_tool

actor = Actor(id=1, name="Admin", email="a@erp.local", roles=["SUPER_ADMIN"])
CASES = [
    ("sales_report", {"start_date": "2026-09-01", "end_date": "2026-09-30"}),
    ("sales_report", {"start_date": "2026-01-01", "end_date": "2026-09-30", "group_by": "month"}),
    ("sales_report", {"start_date": "2026-08-01", "end_date": "2026-09-30", "group_by": "week"}),
    ("sales_report", {"group_by": "category", "top_n": 5, "category": "dieu hoa"}),
    ("sales_report", {"group_by": "customer_type", "region": "Bắc"}),
    ("sales_report", {"group_by": "product", "brand": "toshiba", "salesperson": "sale"}),
    ("compare_sales", {"period_a_start": "2026-08-01", "period_a_end": "2026-08-31",
                       "period_b_start": "2026-09-01", "period_b_end": "2026-09-30", "group_by": "category"}),
    ("inventory_status", {"mode": "low_stock"}),
    ("inventory_status", {"mode": "overstock", "category": "tu lanh", "limit": 3}),
    ("product_info", {"query": "dieu hoa toshiba"}),
    ("product_info", {"query": "máy lạnh"}),
    ("product_trend", {"product": "toshiba"}),
    ("customer_info", {"sort": "revenue", "limit": 3}),
    ("customer_info", {"sort": "inactive", "region": "Nam", "limit": 3}),
    ("receivables_report", {"mode": "top_debtors"}),
    ("receivables_report", {"mode": "overdue", "top_n": 3}),
    ("receivables_report", {"mode": "aging"}),
    ("purchase_report", {"mode": "open_orders"}),
    ("purchase_report", {"mode": "late_orders"}),
    ("purchase_report", {"mode": "supplier_performance"}),
    ("purchase_report", {"mode": "supplier_performance", "sort": "late_rate"}),
    ("supplier_info", {"query": "toshiba"}),
    ("order_list", {"start_date": "2026-09-01", "end_date": "2026-09-30", "limit": 3}),
]

failed = 0
for name, args in CASES:
    result = run_tool(actor, name, args)
    bad = "loi" in result
    failed += bad
    preview = json.dumps(result, ensure_ascii=False)[:170]
    print(f"{'FAIL' if bad else 'ok  '} {name} {args} -> {preview}")
sys.exit(1 if failed else 0)
