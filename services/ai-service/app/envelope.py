"""Envelope phản hồi chuẩn (docs/contracts/api-spec.md): mọi phản hồi có meta.correlation_id và meta.timestamp.

Correlation ID lấy từ header X-Correlation-ID, nếu không có thì sinh mới. Middleware trong main.py gán giá trị vào ContextVar.
"""
from __future__ import annotations

import time
import uuid
from contextvars import ContextVar

CORRELATION_HEADER = "X-Correlation-ID"
_correlation_id: ContextVar[str] = ContextVar("correlation_id", default="")

# Ánh xạ HTTP status sang mã lỗi nghiệp vụ chuẩn của hệ thống
ERROR_CODE_BY_STATUS = {
    400: "VALIDATION_FAILED",
    401: "UNAUTHENTICATED",
    403: "PERMISSION_DENIED",
    404: "RESOURCE_NOT_FOUND",
    409: "CONCURRENT_CONFLICT",
    422: "VALIDATION_FAILED",
    503: "DEPENDENCY_UNAVAILABLE",
}


def new_correlation_id() -> str:
    return f"req_corr_{uuid.uuid4().hex}"


def bind_correlation_id(value: str | None) -> str:
    correlation_id = value.strip() if value and value.strip() else new_correlation_id()
    _correlation_id.set(correlation_id)
    return correlation_id


def current_correlation_id() -> str:
    return _correlation_id.get()


def _meta() -> dict:
    return {"correlation_id": current_correlation_id(), "timestamp": int(time.time())}


def ok(data) -> dict:
    return {"success": True, "data": data, "meta": _meta()}


def fail(status: int, message: str, code: str | None = None, details=None) -> dict:
    error = {"code": code or ERROR_CODE_BY_STATUS.get(status, "INTERNAL_SERVER_ERROR"), "message": message}
    if details is not None:
        error["details"] = details
    return {"success": False, "error": error, "meta": _meta()}
