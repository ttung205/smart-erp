"""Client gọi API ERP qua gateway, đăng nhập theo từng vai trò như người dùng thật."""
from __future__ import annotations

import logging
import time
import uuid
from typing import Any

import httpx

from .config import Settings

log = logging.getLogger("demo_traffic.api")

MAX_GET_RETRIES = 2
# Gateway trả 429 trước khi request tới service, nên gửi lại an toàn với mọi method (kể cả POST)
MAX_RATE_LIMIT_RETRIES = 3
MAX_RATE_LIMIT_WAIT_SECONDS = 65.0


class ApiError(RuntimeError):
    def __init__(self, status: int, code: str, message: str, correlation_id: str):
        super().__init__(f"HTTP {status} {code}: {message} (correlation_id={correlation_id})")
        self.status = status
        self.code = code
        self.correlation_id = correlation_id


class ApiClient:
    def __init__(self, settings: Settings, transport: httpx.BaseTransport | None = None,
                 sleep=time.sleep, clock=time.monotonic):
        self._settings = settings
        self._sleep = sleep
        self._clock = clock
        # Giãn cách tối thiểu giữa hai request để không chạm giới hạn tốc độ của gateway (Kong: 600 request/phút mỗi IP)
        self._min_interval = 60.0 / settings.max_requests_per_minute if settings.max_requests_per_minute > 0 else 0.0
        self._last_request_at: float | None = None
        self._http = httpx.Client(base_url=settings.gateway_url + "/api/v1", timeout=settings.timeout_seconds,
                                  transport=transport, headers={"Accept": "application/json"})
        self._sessions: dict[str, dict[str, Any]] = {}

    def close(self) -> None:
        self._http.close()

    def __enter__(self) -> "ApiClient":
        return self

    def __exit__(self, *exc) -> None:
        self.close()

    def user(self, email: str) -> dict[str, Any]:
        """Thông tin người dùng (id, name) của tài khoản, đăng nhập nếu chưa có phiên."""
        return self._session(email)["user"]

    def get(self, email: str, path: str, **params) -> Any:
        return self._call(email, "GET", path, params=params or None)

    def post(self, email: str, path: str, body: dict | None = None) -> Any:
        return self._call(email, "POST", path, json=body or {})

    def put(self, email: str, path: str, body: dict) -> Any:
        return self._call(email, "PUT", path, json=body)

    def _session(self, email: str) -> dict[str, Any]:
        session = self._sessions.get(email)
        if session and session["expires_at"] > time.time() + 60:
            return session
        response = self._send("POST", "/auth/login", json={"email": email, "password": self._settings.password})
        body = self._json(response)
        if response.status_code != 200 or not body.get("success"):
            raise self._error(response, body, "login")
        data = body["data"]
        session = {"token": data["token"], "user": data["user"],
                   "expires_at": time.time() + int(data.get("expires_in") or 3600)}
        self._sessions[email] = session
        return session

    def _call(self, email: str, method: str, path: str, **kwargs) -> Any:
        correlation_id = f"demo_{uuid.uuid4().hex}"
        attempts = MAX_GET_RETRIES + 1 if method == "GET" else 1
        for attempt in range(attempts):
            headers = {"Authorization": "Bearer " + self._session(email)["token"], "X-Correlation-ID": correlation_id}
            try:
                response = self._send(method, path, headers=headers, **kwargs)
            except httpx.TransportError as exc:
                if attempt + 1 < attempts:
                    self._sleep(0.5 * 2 ** attempt)
                    continue
                raise ApiError(0, "DEPENDENCY_UNAVAILABLE", str(exc), correlation_id) from exc
            if response.status_code == 401:
                self._sessions.pop(email, None)  # token hết hạn: đăng nhập lại ở lần gọi sau
            if response.status_code >= 500 and attempt + 1 < attempts:
                self._sleep(0.5 * 2 ** attempt)
                continue
            body = self._json(response)
            if response.status_code >= 400 or body.get("success") is False:
                raise self._error(response, body, f"{method} {path}", correlation_id)
            return body.get("data", body)
        raise AssertionError("không tới được")

    def _send(self, method: str, path: str, **kwargs) -> httpx.Response:
        """Gửi một request có giãn cách; gặp 429 thì chờ theo Retry-After rồi gửi lại."""
        for attempt in range(MAX_RATE_LIMIT_RETRIES + 1):
            self._throttle()
            response = self._http.request(method, path, **kwargs)
            if response.status_code != 429 or attempt == MAX_RATE_LIMIT_RETRIES:
                return response
            wait = self._retry_after(response)
            log.warning("Gateway giới hạn tốc độ (429) ở %s %s, chờ %.0f giây rồi gửi lại (lần %d/%d)",
                        method, path, wait, attempt + 1, MAX_RATE_LIMIT_RETRIES)
            self._sleep(wait)
        raise AssertionError("không tới được")

    def _throttle(self) -> None:
        now = self._clock()
        if self._last_request_at is not None and self._min_interval > 0:
            remaining = self._min_interval - (now - self._last_request_at)
            if remaining > 0:
                self._sleep(remaining)
                now = self._clock()
        self._last_request_at = now

    @staticmethod
    def _retry_after(response: httpx.Response) -> float:
        """Số giây gateway yêu cầu chờ (Retry-After hoặc RateLimit-Reset), có trần để không treo bot."""
        for header in ("Retry-After", "RateLimit-Reset"):
            try:
                return min(MAX_RATE_LIMIT_WAIT_SECONDS, max(1.0, float(response.headers[header]) + 1))
            except (KeyError, ValueError):
                continue
        return 10.0

    @staticmethod
    def _json(response: httpx.Response) -> dict:
        try:
            body = response.json()
        except ValueError:
            return {}
        return body if isinstance(body, dict) else {"data": body}

    @staticmethod
    def _error(response: httpx.Response, body: dict, action: str, correlation_id: str = "") -> ApiError:
        error = body.get("error") or {}
        code = error.get("code") if isinstance(error, dict) else None
        message = (error.get("message") if isinstance(error, dict) else None) or body.get("message") or action
        return ApiError(response.status_code, code or f"HTTP_{response.status_code}", message, correlation_id)
