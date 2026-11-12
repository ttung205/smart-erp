import sys
import json
sys.stdout.reconfigure(encoding='utf-8')

from app.auth import Actor
from app.tools import run_tool

# Setup actors
salesperson = Actor(
    id=10,
    name="Nguyễn Văn Kinh Doanh",
    email="sale@smarterp.vn",
    roles=["SALE"],
    permissions={"order.view", "catalog.view", "order.create"}
)

sales_viewer = Actor(
    id=11,
    name="Trần Thị Xem Báo Giá",
    email="viewer@smarterp.vn",
    roles=["SALE"],
    permissions={"order.view", "catalog.view"}  # Không có order.create
)

unauthorized = Actor(
    id=99,
    name="Lê Kỹ Thuật",
    email="tech@smarterp.vn",
    roles=["TECHNICAL"],
    permissions={"technical.view"}
)

print("=== TEST 1: Missing Internal Code ===")
res1 = run_tool(salesperson, "instant_quote_check", {"customer_code": "DI-14998"})
print(json.dumps(res1, ensure_ascii=False, indent=2))
assert "loi" in res1, "Test 1 failed: Should return error for missing Internal Code"

print("\n=== TEST 2: Missing Customer Code ===")
res2 = run_tool(salesperson, "instant_quote_check", {"sku": "CFS-13VDM-COM"})
print(json.dumps(res2, ensure_ascii=False, indent=2))
assert "loi" in res2, "Test 2 failed: Should return error for missing customer code"

print("\n=== TEST 3: Fuzzy / Non-existent Internal Code ===")
res3 = run_tool(salesperson, "instant_quote_check", {"sku": "comfee 1.5 hp", "customer_code": "DI-14998"})
print(json.dumps(res3, ensure_ascii=False, indent=2))
assert "Không tìm thấy sản phẩm có Internal Code chính xác" in res3["loi"], "Test 3 failed: Should reject fuzzy Internal Code"

print("\n=== TEST 4: Non-existent Customer Code ===")
res4 = run_tool(salesperson, "instant_quote_check", {"sku": "CFS-13VDM-COM", "customer_code": "DI-99999999"})
print(json.dumps(res4, ensure_ascii=False, indent=2))
assert "Không tìm thấy khách hàng có mã code chính xác" in res4["loi"], "Test 4 failed: Should reject non-existent customer"

print("\n=== TEST 5: Valid Exact Internal Code & Customer Code (Distributor - order.create granted) ===")
res5 = run_tool(salesperson, "instant_quote_check", {"sku": "CFS-13VDM-COM", "customer_code": "DI-14998", "quantity": 10})
print(json.dumps(res5, ensure_ascii=False, indent=2))
assert "action_schema" in res5, "Test 5 failed: Missing action_schema"
assert res5["action_schema"]["can_execute"] is True, "Test 5 failed: can_execute should be True"
assert res5["action_schema"]["customer_code"] == "DI-14998"
assert res5["action_schema"]["sku"] == "CFS-13VDM-COM"

print("\n=== TEST 6: RBAC Preview Mode (Actor lacks order.create) ===")
res6 = run_tool(sales_viewer, "instant_quote_check", {"sku": "CFS-13VDM-COM", "customer_code": "DI-14998", "quantity": 5})
assert "action_schema" in res6
assert res6["action_schema"]["can_execute"] is False, "Test 6 failed: can_execute should be False when lacking order.create"
print("RBAC can_execute correctly set to False for viewer without order.create!")

print("\n=== TEST 7: Unauthorized Actor (Lacks order.view) ===")
res7 = run_tool(unauthorized, "instant_quote_check", {"sku": "CFS-13VDM-COM", "customer_code": "DI-14998"})
print("Unauthorized response:", res7)
assert "loi" in res7 and "Người dùng không có quyền" in res7["loi"]

print("\n=== TEST 8: Customer with Overdue Debt (DI-90398) ===")
res8 = run_tool(salesperson, "instant_quote_check", {"sku": "CFS-13VDM-COM", "customer_code": "DI-90398", "quantity": 5})
print(json.dumps(res8["khach_hang"], ensure_ascii=False, indent=2))
print("Credit note:", res8["khach_hang"]["danh_gia_cong_no"])
assert res8["action_schema"]["is_credit_ok"] is False, "Test 8 failed: Should flag credit not OK due to overdue debt"

print("\n=== TEST 9: Excessive Discount Triggering Margin Warning ===")
res9 = run_tool(salesperson, "instant_quote_check", {"sku": "CFS-13VDM-COM", "customer_code": "DI-14998", "quantity": 5, "custom_discount_percent": 35.0})
print("Margin evaluation with 35% discount:", json.dumps(res9["danh_gia_hieu_qua_kinh_doanh"], ensure_ascii=False, indent=2))
assert res9["action_schema"]["margin_status"] in ["CANH_BAO_BIEN_THAP", "LO_VON"], "Test 9 failed: Should flag low or negative margin"

print("\nALL 9 TESTS PASSED PERFECTLY!")

