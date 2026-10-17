"""Client gọi Groq (API tương thích OpenAI chat completions + tool calling)."""
from __future__ import annotations

import asyncio
import logging
import re

import httpx

from . import config

log = logging.getLogger("ai.llm")


class LLMError(Exception):
    def __init__(self, message: str, status: int = 502):
        super().__init__(message)
        self.status = status


class RateLimited(Exception):
    def __init__(self, wait: float):
        self.wait = wait


def _retry_after(resp: httpx.Response) -> float:
    header = resp.headers.get("retry-after")
    if header:
        try:
            return float(header)
        except ValueError:
            pass
    m = re.search(r"try again in ([\d.]+)s", resp.text)
    return float(m.group(1)) if m else 30.0


async def _call(client: httpx.AsyncClient, model: str, messages: list[dict], tools: list[dict] | None) -> dict:
    body = {"model": model, "messages": messages, "temperature": 0.2, "max_tokens": 1500}
    if tools:
        body.update({"tools": tools, "tool_choice": "auto"})
    for attempt in range(3):
        try:
            resp = await client.post("/chat/completions", json=body)
        except httpx.HTTPError as exc:
            log.warning("Groq lỗi kết nối (%s): %s", model, exc)
            await asyncio.sleep(1.5 * (attempt + 1))
            continue
        if resp.status_code == 200:
            return resp.json()["choices"][0]["message"]
        detail = resp.text[:400]
        log.warning("Groq HTTP %s (%s): %s", resp.status_code, model, detail)
        if resp.status_code == 429:
            raise RateLimited(_retry_after(resp))
        if resp.status_code in (401, 403):
            raise LLMError("GROQ_API_KEY không hợp lệ", 500)
        if resp.status_code == 400 and ("tool_use_failed" in detail or "Tool call validation failed" in detail):
            continue                     # model sinh lời gọi tool sai định dạng: thử lại
        if resp.status_code >= 500:
            await asyncio.sleep(1.5 * (attempt + 1))
            continue
        raise LLMError("Dịch vụ AI trả lỗi, vui lòng thử lại")
    raise LLMError("Không kết nối được dịch vụ AI, vui lòng thử lại sau")


async def chat(messages: list[dict], tools: list[dict] | None = None) -> dict:
    """Trả về message của assistant (có thể chứa tool_calls). Thử lần lượt model chính rồi model dự phòng."""
    if not config.GROQ_API_KEY:
        raise LLMError("ai-service chưa cấu hình GROQ_API_KEY", 500)
    models = [config.GROQ_MODEL, *[m for m in config.GROQ_FALLBACK_MODELS if m != config.GROQ_MODEL]]
    headers = {"Authorization": f"Bearer {config.GROQ_API_KEY}"}
    async with httpx.AsyncClient(base_url=config.GROQ_BASE_URL, timeout=config.LLM_TIMEOUT, headers=headers) as client:
        for round_ in range(2):
            waits = []
            for model in models:
                try:
                    return await _call(client, model, messages, tools)
                except RateLimited as rl:
                    waits.append(rl.wait)
            # Mọi model đều đang bị giới hạn: chờ ngắn rồi thử lại một lần
            wait = min(waits)
            if round_ == 1 or wait > 30:
                break
            await asyncio.sleep(wait + 0.5)
    raise LLMError("Trợ lý AI đang quá tải (giới hạn gói miễn phí), vui lòng thử lại sau ít phút", 429)
