"""Client đọc endpoint export nội bộ của các service (Infrastructure của ETL kho phân tích).

Mỗi service tự xuất dữ liệu của mình qua `/internal/...` với khóa X-Internal-Service-Key; ai-service không kết nối
database của service khác. Phân trang theo khóa chính: `?after_id=&limit=`, dừng khi `next_after_id` là null.
"""
from __future__ import annotations

import logging
import time
import uuid

import httpx

from . import config

log = logging.getLogger("ai.export_client")

# Đường dẫn export theo service: Laravel phục vụ dưới /api, .NET phục vụ ở gốc
PATHS = {
    ("catalog", "products"): "/api/internal/exports/products",
    ("catalog", "customers"): "/api/internal/exports/customers",
    ("catalog", "suppliers"): "/api/internal/exports/suppliers",
    ("identity", "staff"): "/api/internal/exports/staff",
    ("sales", "revenue_items"): "/api/internal/exports/revenue-items",
    ("sales", "orders"): "/api/internal/exports/orders",
    ("sales", "order_lines"): "/api/internal/exports/order-lines",
    ("finance", "receivables"): "/api/internal/exports/receivables",
    ("inventory", "stock_balances"): "/internal/analytics/stock-balances",
    ("inventory", "reservation_balances"): "/internal/analytics/reservation-balances",
    ("inventory", "stock_moves"): "/internal/analytics/stock-moves",
    ("purchase", "po_lines"): "/internal/analytics/po-lines",
}

MAX_RETRIES = 2
SERVICE_NAME = "ai-service"


class DependencyUnavailable(RuntimeError):
    """Service nguồn không phản hồi hoặc trả lỗi: lần đồng bộ này thất bại, kho phân tích cũ được giữ nguyên."""


class ExportClient:
    def __init__(self, correlation_id: str | None = None, transport: httpx.BaseTransport | None = None):
        if not config.INTERNAL_SERVICE_KEY:
            raise RuntimeError("Thiếu INTERNAL_SERVICE_KEY: ai-service không gọi được endpoint export của các service")
        self.correlation_id = correlation_id or f"corr_{uuid.uuid4().hex}"
        self._http = httpx.Client(
            timeout=config.EXPORT_TIMEOUT_SECONDS,
            transport=transport,
            headers={
                "Accept": "application/json",
                "X-Internal-Service-Key": config.INTERNAL_SERVICE_KEY,
                "X-Correlation-ID": self.correlation_id,
            },
        )

    def close(self) -> None:
        self._http.close()

    def __enter__(self) -> "ExportClient":
        return self

    def __exit__(self, *exc) -> None:
        self.close()

    def fetch_all(self, service: str, dataset: str) -> list[dict]:
        """Đọc hết dataset qua từng trang. Lỗi ở bất kỳ trang nào làm hỏng cả lần đồng bộ (không dùng dữ liệu dở)."""
        base = config.SERVICE_URLS.get(service, "")
        if not base:
            raise RuntimeError(f"Thiếu {service.upper()}_SERVICE_URL cho ETL kho phân tích")
        url = base + PATHS[(service, dataset)]

        items: list[dict] = []
        after_id = 0
        while True:
            page = self._get_page(service, url, after_id)
            items.extend(page["items"])
            if page.get("next_after_id") is None:
                return items
            after_id = int(page["next_after_id"])

    def _get_page(self, service: str, url: str, after_id: int) -> dict:
        params = {"after_id": after_id, "limit": config.EXPORT_PAGE_SIZE}
        for attempt in range(MAX_RETRIES + 1):
            try:
                response = self._http.get(url, params=params)
            except httpx.TransportError as exc:
                error = f"{type(exc).__name__}: {exc}"
            else:
                if response.status_code < 500:
                    if response.status_code != 200:
                        # 4xx là lỗi cấu hình (sai khóa, sai đường dẫn): thử lại cũng vô ích
                        raise DependencyUnavailable(
                            f"{service} trả HTTP {response.status_code} cho {url} (correlation_id={self.correlation_id})")
                    body = response.json()
                    if not body.get("success") or "items" not in (body.get("data") or {}):
                        raise DependencyUnavailable(f"{service} trả dữ liệu export sai định dạng cho {url}")
                    return body["data"]
                error = f"HTTP {response.status_code}"

            log.warning("Gọi export lỗi (lần %d/%d): %s %s, correlation_id=%s, service=%s, user_id=system",
                        attempt + 1, MAX_RETRIES + 1, url, error, self.correlation_id, SERVICE_NAME)
            if attempt < MAX_RETRIES:
                time.sleep(0.5 * 2 ** attempt)
        raise DependencyUnavailable(f"{service} không phản hồi export {url} sau {MAX_RETRIES + 1} lần thử "
                                    f"(correlation_id={self.correlation_id})")
