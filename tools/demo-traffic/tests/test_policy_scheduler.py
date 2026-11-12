import random
import unittest
from datetime import date, datetime

from demo_traffic import policy, scheduler

PRODUCTS = [
    {"id": 1, "sku": "A", "gross_price": "10000000.00", "min_stock": 5, "brand": "Toshiba"},
    {"id": 2, "sku": "B", "gross_price": "2000000.00", "min_stock": 0, "brand": "LG"},
]
SUPPLIERS = [{"id": 10, "name": "Công ty TNHH Toshiba Lifestyle Việt Nam"}, {"id": 11, "name": "NCC Tổng hợp"}]


class PolicyTest(unittest.TestCase):
    def test_plan_never_sells_more_than_available(self):
        rng = random.Random(1)
        available = {1: 3, 2: 0}
        customers = {"Distributor": [{"id": 7, "customer_type": "Distributor"}]}
        for _ in range(20):
            plan = policy.plan_order(customers, PRODUCTS, available, rng)
            if plan:
                for line in plan.lines:
                    self.assertGreaterEqual(available[line.product["id"]], 0)
        self.assertGreaterEqual(available[1], 0)
        self.assertEqual(available[2], 0)

    def test_price_applies_tier_discount(self):
        rng = random.Random(2)
        plan = policy.plan_order({"Dealer": [{"id": 1, "customer_type": "Dealer"}]}, PRODUCTS[:1], {1: 100}, rng)
        self.assertEqual(plan.lines[0].selling_price, 8800000.0)  # đại lý chiết khấu 12%

    def test_sunday_only_retail(self):
        rng = random.Random(3)
        customers = {"Dealer": [{"id": 1, "customer_type": "Dealer"}], "Retail": [{"id": 2, "customer_type": "Retail"}]}
        for _ in range(10):
            self.assertEqual(policy.plan_order(customers, PRODUCTS, {1: 50, 2: 50}, rng, sunday=True).customer["id"], 2)

    def test_replenishment_orders_items_below_min_stock(self):
        self.assertEqual(policy.replenishment(PRODUCTS, {1: 2, 2: 0}, limit=10), [(PRODUCTS[0], 13)])

    def test_supplier_matches_brand_then_falls_back(self):
        self.assertEqual(policy.supplier_for(PRODUCTS[0], SUPPLIERS)["id"], 10)
        self.assertEqual(policy.supplier_for(PRODUCTS[1], SUPPLIERS)["id"], SUPPLIERS[2 % 2]["id"])


class SchedulerTest(unittest.TestCase):
    def test_new_day_carries_unfinished_and_missed_days_with_cap(self):
        rng = random.Random(4)
        state = {"day": "2026-11-02", "quota": 30, "done": 10}
        new = scheduler.start_day(state, date(2026, 11, 10), orders_per_day=30, max_catchup_days=3, rng=rng)
        self.assertEqual(new["day"], "2026-11-10")
        self.assertEqual(new["done"], 0)
        self.assertLessEqual(new["quota"] - policy.daily_quota(date(2026, 11, 10), 30, random.Random(4)), 30 * 3)
        self.assertGreater(new["quota"], 30)

    def test_same_day_keeps_progress(self):
        state = {"day": "2026-11-10", "quota": 30, "done": 12}
        self.assertIs(scheduler.start_day(state, date(2026, 11, 10), 30, 3, random.Random()), state)

    def test_due_follows_working_hours(self):
        state = {"quota": 40, "done": 0}
        self.assertEqual(scheduler.due_now(state, datetime(2026, 11, 10, 7, 0), 8, 17), 0)
        self.assertEqual(scheduler.due_now(state, datetime(2026, 11, 10, 12, 30), 8, 17), 20)
        self.assertEqual(scheduler.due_now({"quota": 40, "done": 35}, datetime(2026, 11, 10, 18, 0), 8, 17), 5)


if __name__ == "__main__":
    unittest.main()
