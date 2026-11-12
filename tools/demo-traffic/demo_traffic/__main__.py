"""Bot sinh giao dịch demo hằng ngày qua API của Smart ERP.

    python -m demo_traffic once --orders 5      # chạy một lượt ngay (bán 5 đơn, bổ sung hàng, thu nợ)
    python -m demo_traffic daemon               # chạy liên tục: rải đơn trong giờ làm việc, chạy bù ngày bỏ lỡ
"""
from __future__ import annotations

import argparse
import logging
import random
import sys
import time
from datetime import datetime

from . import scheduler
from .api import ApiClient
from .config import VN_TZ, Settings
from .flows import Erp, collect_payments, replenish_stock, run_day, run_sales

log = logging.getLogger("demo_traffic")


def run_once(settings: Settings, orders: int) -> dict:
    rng = random.Random(settings.seed)
    with ApiClient(settings) as api:
        stats = run_day(Erp(api, settings), orders, datetime.now(VN_TZ).date(), rng)
    log.info("Kết quả: %s", stats)
    return stats


def daemon(settings: Settings) -> None:
    rng = random.Random(settings.seed)
    while True:
        now = datetime.now(VN_TZ)
        try:
            state = scheduler.start_day(scheduler.load_state(settings.state_file), now.date(),
                                        settings.orders_per_day, settings.max_catchup_days, rng)
            due = scheduler.due_now(state, now, settings.work_start_hour, settings.work_end_hour)
            # Bổ sung hàng và thu nợ mỗi ngày một lần, ở lượt đầu tiên trong giờ làm việc
            daily = not state.get("collected") and now.hour >= settings.work_start_hour
            if due or daily:
                stats: dict = {}
                with ApiClient(settings) as api:
                    erp = Erp(api, settings)
                    if daily:
                        stats.update(replenish_stock(erp, now.date()))
                        stats.update(collect_payments(erp, now.date(), rng))
                        state["collected"] = True
                    if due:
                        stats.update(run_sales(erp, due, now.date(), rng))
                        state["done"] = int(state["done"]) + due
                log.info("Lượt %s: %d/%d đơn trong ngày, %s", now.strftime("%H:%M"), state["done"], state["quota"], stats)
            scheduler.save_state(settings.state_file, state)
        except Exception:
            log.exception("Lượt chạy demo-traffic lỗi (service=demo-traffic, user_id=bot); thử lại ở lượt sau")
        time.sleep(settings.tick_seconds)


def main(argv: list[str] | None = None) -> int:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
    parser = argparse.ArgumentParser(prog="demo_traffic")
    sub = parser.add_subparsers(dest="command", required=True)
    once = sub.add_parser("once", help="chạy một lượt ngay")
    once.add_argument("--orders", type=int, default=None, help="số đơn bán (mặc định: DEMO_ORDERS_PER_DAY)")
    sub.add_parser("daemon", help="chạy liên tục theo giờ làm việc")
    args = parser.parse_args(argv)

    settings = Settings()
    settings.validate()
    if args.command == "once":
        stats = run_once(settings, settings.orders_per_day if args.orders is None else args.orders)
        return 1 if stats["order_failed"] or stats["po_failed"] or stats["receipt_failed"] else 0
    daemon(settings)
    return 0


if __name__ == "__main__":
    sys.exit(main())
