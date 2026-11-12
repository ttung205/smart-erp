"""Chia số đơn của ngày ra suốt giờ làm việc và chạy bù những ngày máy tắt.

Trạng thái lưu ở file JSON: {"day": "YYYY-MM-DD", "quota": n, "done": k}. API ghi thời điểm hiện tại nên không
thể sinh chứng từ cho ngày đã qua: số đơn của những ngày bỏ lỡ (tối đa max_catchup_days) dồn vào hôm nay.
"""
from __future__ import annotations

import json
import logging
import random
from datetime import date, datetime
from pathlib import Path

from .policy import daily_quota

log = logging.getLogger("demo_traffic.scheduler")


def load_state(path: str) -> dict:
    file = Path(path)
    if not file.exists():
        return {}
    return json.loads(file.read_text(encoding="utf-8"))


def save_state(path: str, state: dict) -> None:
    file = Path(path)
    file.parent.mkdir(parents=True, exist_ok=True)
    tmp = file.with_suffix(".tmp")
    tmp.write_text(json.dumps(state, ensure_ascii=False), encoding="utf-8")
    tmp.replace(file)  # ghi nguyên tử: tắt máy giữa chừng không làm hỏng file trạng thái


def start_day(state: dict, today: date, orders_per_day: int, max_catchup_days: int, rng: random.Random) -> dict:
    """Trạng thái cho ngày mới: hạn mức hôm nay + phần còn thiếu của hôm trước + các ngày bỏ lỡ (có giới hạn)."""
    if state.get("day") == today.isoformat():
        return state
    quota = daily_quota(today, orders_per_day, rng)
    carried = 0
    if state.get("day"):
        last = date.fromisoformat(state["day"])
        carried = max(0, int(state.get("quota", 0)) - int(state.get("done", 0)))
        missed = min(max(0, (today - last).days - 1), max_catchup_days)
        for offset in range(missed):
            carried += daily_quota(date.fromordinal(today.toordinal() - missed + offset), orders_per_day, rng)
        carried = min(carried, orders_per_day * max_catchup_days)
    if carried:
        log.info("Ngày %s: chạy bù %d đơn còn thiếu từ những ngày trước", today, carried)
    return {"day": today.isoformat(), "quota": quota + carried, "done": 0, "collected": False}


def due_now(state: dict, now: datetime, start_hour: int, end_hour: int) -> int:
    """Số đơn cần tạo ở lượt này để tiến độ bám theo thời gian đã trôi qua trong giờ làm việc."""
    minutes = now.hour * 60 + now.minute
    start, end = start_hour * 60, end_hour * 60
    if minutes < start:
        return 0
    progress = 1.0 if minutes >= end else (minutes - start) / (end - start)
    target = round(int(state["quota"]) * progress)
    return max(0, target - int(state["done"]))
