"""Cấu hình bot sinh giao dịch demo, đọc từ biến môi trường."""
from __future__ import annotations

import os
from dataclasses import dataclass, field
from datetime import timedelta, timezone


def _int(name: str, default: int) -> int:
    return int(os.getenv(name, str(default)))


# Giờ làm việc và ngày chứng từ tính theo giờ Việt Nam; thời điểm ghi sổ do server ghi theo UTC.
VN_TZ = timezone(timedelta(hours=7))


@dataclass(frozen=True)
class Accounts:
    """Mỗi bước nghiệp vụ dùng đúng vai trò như người thật (tách quyền lập và duyệt)."""

    sales: str = os.getenv("DEMO_SALES_EMAIL", "sale@erp.local")
    sales_manager: str = os.getenv("DEMO_SALES_MANAGER_EMAIL", "sale.manager@erp.local")
    finance: str = os.getenv("DEMO_FINANCE_EMAIL", "finance@erp.local")
    finance_manager: str = os.getenv("DEMO_FINANCE_MANAGER_EMAIL", "finance.manager@erp.local")
    logistic: str = os.getenv("DEMO_LOGISTIC_EMAIL", "logistic@erp.local")
    purchase: str = os.getenv("DEMO_PURCHASE_EMAIL", "purchase@erp.local")
    purchase_manager: str = os.getenv("DEMO_PURCHASE_MANAGER_EMAIL", "purchase.manager@erp.local")


@dataclass(frozen=True)
class Settings:
    gateway_url: str = os.getenv("GATEWAY_URL", "").rstrip("/")
    # Mật khẩu chung của các tài khoản mẫu; không có mặc định để không lộ trong mã nguồn
    password: str = os.getenv("DEMO_TRAFFIC_PASSWORD", "")
    accounts: Accounts = field(default_factory=Accounts)
    warehouse_id: int = _int("DEMO_WAREHOUSE_ID", 1)
    orders_per_day: int = _int("DEMO_ORDERS_PER_DAY", 30)
    max_catchup_days: int = _int("DEMO_MAX_CATCHUP_DAYS", 3)
    max_purchase_orders_per_day: int = _int("DEMO_MAX_PO_PER_DAY", 5)
    # Số phiếu thu tối đa mỗi ngày (mỗi khách một phiếu); phần nợ quá hạn còn lại để các ngày sau thu tiếp
    max_receipts_per_day: int = _int("DEMO_MAX_RECEIPTS_PER_DAY", 30)
    # Trần tốc độ gọi API của bot, để dưới giới hạn 600 request/phút mỗi IP của gateway; 0 = không giãn cách
    max_requests_per_minute: int = _int("DEMO_MAX_REQUESTS_PER_MINUTE", 300)
    work_start_hour: int = _int("DEMO_WORK_START_HOUR", 8)
    work_end_hour: int = _int("DEMO_WORK_END_HOUR", 17)
    tick_seconds: int = _int("DEMO_TICK_SECONDS", 900)
    state_file: str = os.getenv("DEMO_STATE_FILE", "/state/demo-traffic.json")
    timeout_seconds: float = float(os.getenv("DEMO_HTTP_TIMEOUT_SECONDS", "15"))
    seed: int | None = int(os.environ["DEMO_SEED"]) if os.getenv("DEMO_SEED") else None

    def validate(self) -> None:
        missing = [name for name, value in (("GATEWAY_URL", self.gateway_url),
                                            ("DEMO_TRAFFIC_PASSWORD", self.password)) if not value]
        if missing:
            raise RuntimeError("Thiếu cấu hình: " + ", ".join(missing))
