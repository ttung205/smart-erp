"""Quy tắc kinh doanh của công ty mô phỏng: hôm nay bán bao nhiêu đơn, cho ai, mặt hàng nào, giá nào.

Thuần Python, không gọi mạng, để dễ kiểm thử. Tỷ lệ lấy theo simulator (services/ai-service/simulator/config.py).
"""
from __future__ import annotations

import random
from dataclasses import dataclass
from datetime import date

# Tỷ trọng số đơn theo loại khách và chiết khấu theo phân hạng đại lý (khớp chính sách báo giá sỉ của AI)
ORDER_SHARE = {"Retail": 0.35, "Dealer": 0.45, "Distributor": 0.12, "Project / Business": 0.08}
DISCOUNT = {"Retail": 0.0, "Dealer": 0.12, "Distributor": 0.18, "Project / Business": 0.10}
QUANTITY = {"Retail": (1, 1), "Dealer": (1, 5), "Distributor": (5, 20), "Project / Business": (5, 30)}
LINES = {"Retail": (1, 1), "Dealer": (1, 3), "Distributor": (2, 5), "Project / Business": (1, 4)}
# Thứ 7 làm nửa ngày, Chủ nhật chỉ khách lẻ mua ở showroom
WEEKDAY_FACTOR = {5: 0.5, 6: 0.2}
PAY_PROBABILITY = 0.6          # mỗi lần chạy, khoảng 60% khoản nợ quá hạn được khách trả
REORDER_COVER = 3              # đặt hàng đủ cho khoảng 3 lần mức tồn tối thiểu


@dataclass(frozen=True)
class OrderLine:
    product: dict
    quantity: int
    selling_price: float


@dataclass(frozen=True)
class OrderPlan:
    customer: dict
    lines: list[OrderLine]


def customer_type(customer: dict) -> str:
    kind = customer.get("customer_type") or "Retail"
    return kind if kind in ORDER_SHARE else "Retail"


def daily_quota(day: date, orders_per_day: int, rng: random.Random) -> int:
    """Số đơn của một ngày: dao động ±20% quanh mức trung bình, giảm vào cuối tuần."""
    base = orders_per_day * WEEKDAY_FACTOR.get(day.weekday(), 1.0)
    return max(0, round(base * rng.uniform(0.8, 1.2)))


def plan_order(customers_by_type: dict[str, list[dict]], products: list[dict], available: dict[int, int],
               rng: random.Random, sunday: bool = False) -> OrderPlan | None:
    """Chọn khách và các dòng hàng còn đủ tồn khả dụng; trừ tồn tạm trong `available` để không bán vượt."""
    kinds = ["Retail"] if sunday else [k for k in ORDER_SHARE if customers_by_type.get(k)]
    if not kinds:
        return None
    kind = rng.choices(kinds, weights=[ORDER_SHARE[k] for k in kinds])[0]
    customer = rng.choice(customers_by_type[kind])

    low, high = QUANTITY[kind]
    lines: list[OrderLine] = []
    used: set[int] = set()
    for _ in range(rng.randint(*LINES[kind])):
        quantity = rng.randint(low, high)
        candidates = [p for p in products if p["id"] not in used and available.get(p["id"], 0) >= quantity]
        if not candidates:
            continue
        product = rng.choice(candidates)
        used.add(product["id"])
        available[product["id"]] -= quantity
        price = round(float(product["gross_price"]) * (1 - DISCOUNT[kind]), -3)
        lines.append(OrderLine(product, quantity, price))
    return OrderPlan(customer, lines) if lines else None


def replenishment(products: list[dict], available: dict[int, int], limit: int) -> list[tuple[dict, int]]:
    """Các sản phẩm dưới mức tồn tối thiểu, thiếu nhiều nhất trước, kèm số lượng cần đặt."""
    short = []
    for product in products:
        min_stock = int(product.get("min_stock") or 0)
        have = available.get(product["id"], 0)
        if min_stock > 0 and have < min_stock:
            short.append((min_stock - have, product, min_stock * REORDER_COVER - have))
    short.sort(key=lambda item: item[0], reverse=True)
    return [(product, quantity) for _, product, quantity in short[:limit]]


def supplier_for(product: dict, suppliers: list[dict]) -> dict:
    """NCC có tên chứa thương hiệu sản phẩm (vd. Toshiba), không có thì chọn cố định theo mã sản phẩm."""
    brand = (product.get("brand") or "").lower()
    if brand:
        for supplier in suppliers:
            if brand in (supplier.get("name") or "").lower():
                return supplier
    return suppliers[product["id"] % len(suppliers)]


def receivables_to_collect(receivables: list[dict], rng: random.Random) -> dict[int, list[dict]]:
    """Gom theo khách các khoản nợ quá hạn được trả trong lần chạy này."""
    paying: dict[int, list[dict]] = {}
    for receivable in receivables:
        if float(receivable.get("outstanding_amount") or 0) > 0 and rng.random() < PAY_PROBABILITY:
            paying.setdefault(int(receivable["customer_id"]), []).append(receivable)
    return paying
