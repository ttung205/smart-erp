"""Dựng dữ liệu gốc: sản phẩm (từ dữ liệu đã cào), nhà cung cấp, khách hàng, nhân viên."""
from __future__ import annotations

import json
import hashlib
import math
import random
import sqlite3
from collections import defaultdict
from datetime import date, datetime, time, timedelta
from pathlib import Path

from . import config as C
from .models import Contact, Customer, Product, Staff, Supplier
from .people import People, slugify

DATA_DIR = Path(__file__).resolve().parent / "data"

# Thương hiệu -> (tên pháp nhân tham khảo, loại, nhóm, quốc gia/địa chỉ, lead time (ngày), tiền tệ)
# kind: domestic = mua từ pháp nhân tại VN (VND); import = nhập khẩu trực tiếp (USD)
BRAND_SUPPLIERS: dict[str, tuple] = {
    "Panasonic": ("Công ty TNHH Panasonic Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hà Nội", (3, 7)),
    "KDK": ("Công ty TNHH Panasonic Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hà Nội", (3, 7)),
    "LG": ("Công ty TNHH LG Electronics Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hải Phòng", (3, 7)),
    "Samsung": ("Công ty TNHH Điện tử Samsung Vina", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (5, 10)),
    "Toshiba": ("Công ty TNHH Toshiba Lifestyle Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (5, 10)),
    "Sharp": ("Công ty TNHH Sharp Electronics Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (5, 10)),
    "Electrolux": ("Công ty TNHH Electrolux Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (5, 10)),
    "Daikin": ("Công ty CP Daikin Air Conditioning Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (4, 8)),
    "Aqua": ("Công ty TNHH Điện máy Aqua Việt Nam", "domestic", "MANUFACTURER", "Tỉnh Đồng Nai", (5, 10)),
    "Casper": ("Công ty CP Casper Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hà Nội", (3, 6)),
    "Nagakawa": ("Công ty CP Tập đoàn Nagakawa", "domestic", "MANUFACTURER", "Tỉnh Bắc Ninh", (2, 5)),
    "Funiki": ("Công ty CP Điện lạnh Hòa Phát", "domestic", "MANUFACTURER", "Tỉnh Hưng Yên", (2, 5)),
    "Hòa Phát": ("Công ty CP Điện lạnh Hòa Phát", "domestic", "MANUFACTURER", "Tỉnh Hưng Yên", (2, 5)),
    "Sunhouse": ("Công ty CP Tập đoàn Sunhouse", "domestic", "MANUFACTURER", "Thành phố Hà Nội", (2, 4)),
    "Kangaroo": ("Công ty CP Tập đoàn Kangaroo", "domestic", "MANUFACTURER", "Thành phố Hà Nội", (2, 4)),
    "Karofi": ("Công ty CP Karofi Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hà Nội", (2, 4)),
    "A. O. Smith": ("Công ty TNHH A. O. Smith Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (5, 10)),
    "AO Smith": ("Công ty TNHH A. O. Smith Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (5, 10)),
    "Ferroli": ("Công ty TNHH Ferroli Asean", "domestic", "MANUFACTURER", "Tỉnh Hưng Yên", (3, 6)),
    "Ariston": ("Công ty TNHH Ariston Thermo Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (5, 10)),
    "Rossi": ("Công ty CP Tập đoàn Tân Á Đại Thành", "domestic", "MANUFACTURER", "Thành phố Hà Nội", (2, 4)),
    "Lock&Lock": ("Công ty TNHH Lock&Lock HN", "domestic", "MANUFACTURER", "Thành phố Hà Nội", (2, 5)),
    "Xiaomi": ("Công ty CP Thế Giới Số (Digiworld)", "domestic", "TRADING", "Thành phố Hồ Chí Minh", (4, 8)),
    "TCL": ("Công ty TNHH TCL Electronics Việt Nam", "domestic", "MANUFACTURER", "Tỉnh Đồng Nai", (5, 10)),
    "Beko": ("Công ty TNHH Beko Việt Nam", "domestic", "TRADING", "Thành phố Hồ Chí Minh", (5, 10)),
    "Mitsubishi Electric": ("Công ty TNHH Mitsubishi Electric Việt Nam", "domestic", "MANUFACTURER", "Thành phố Hồ Chí Minh", (5, 10)),
    "Hitachi": ("Hitachi Global Life Solutions (Thailand) Ltd.", "import", "MANUFACTURER", "Bangkok, Thailand", (25, 40)),
    "Midea": ("Guangdong Midea Environment Appliances Mfg. Co., Ltd.", "import", "MANUFACTURER", "Foshan, Guangdong, China", (30, 45)),
    "Comfee": ("Guangdong Midea Environment Appliances Mfg. Co., Ltd.", "import", "MANUFACTURER", "Foshan, Guangdong, China", (30, 45)),
    "Gree": ("Gree Electric Appliances Inc. of Zhuhai", "import", "MANUFACTURER", "Zhuhai, Guangdong, China", (30, 45)),
    "Hisense": ("Hisense International Co., Ltd.", "import", "MANUFACTURER", "Qingdao, Shandong, China", (30, 45)),
    "Haier": ("Haier Overseas Electric Appliances Corp. Ltd.", "import", "MANUFACTURER", "Qingdao, Shandong, China", (30, 45)),
    "Cuckoo": ("Cuckoo Electronics Co., Ltd.", "import", "MANUFACTURER", "Yangsan, Gyeongnam, Korea", (30, 40)),
    "Tefal": ("SEB Asia Ltd.", "import", "TRADING", "Kowloon, Hong Kong", (25, 40)),
    "Philips": ("Versuni Singapore Pte. Ltd.", "import", "TRADING", "Singapore", (25, 40)),
    "Bosch": ("BSH Hausgeräte GmbH", "import", "MANUFACTURER", "München, Germany", (45, 60)),
}
# Hãng nhỏ không có trong danh sách: đi qua công ty thương mại trung gian
TRADING_IMPORTER = ("Công ty TNHH Xuất nhập khẩu Gia dụng Hưng Thịnh", "domestic", "TRADING", "Thành phố Hà Nội", (3, 7))
TRADING_LOCAL = ("Công ty CP Phân phối Thiết bị Gia dụng Toàn Cầu", "domestic", "TRADING", "Thành phố Hà Nội", (2, 5))

# Điều chỉnh giá của hãng trong năm (ngày hiệu lực -> [(hãng, %)])
PRICE_PLAN = {
    date(2026, 1, 5): [("Panasonic", 3), ("Daikin", 4), ("LG", 3), ("Sunhouse", 5), ("Toshiba", 3)],
    date(2026, 3, 2): [("Kangaroo", 4), ("Karofi", 3)],
    date(2026, 7, 1): [("Samsung", 2), ("Electrolux", 3), ("Midea", -3), ("Xiaomi", -2), ("Casper", -4)],
}

BIG_CATEGORIES = {"Điều hòa", "Tủ lạnh", "Máy giặt", "Máy sấy quần áo", "Tủ đông - Tủ mát", "Máy nước nóng",
                  "Máy rửa chén", "Máy lọc nước"}
SPEC_KEYS_LIMIT = 8


def _brand_key(brand: str) -> str:
    for key in BRAND_SUPPLIERS:
        if key.lower() == (brand or "").lower():
            return key
    return brand


class MasterData:
    def __init__(self, identity_db: Path, seed: int = C.SEED):
        self.rng = random.Random(seed)
        self.people = People(self.rng)
        self.staff: list[Staff] = []
        self.suppliers: list[Supplier] = []
        self.products: list[Product] = []
        self.customers: list[Customer] = []
        self.b2b_customers: list[Customer] = []
        self.expected_rate: dict[int, float] = {}
        self.price_plan = PRICE_PLAN
        self._used_codes: set[str] = set()
        self._customer_id = 0

        self.load_staff(identity_db)
        self.build_suppliers_and_products()
        self.build_customers()

        self.products_by_category: dict[str, list[Product]] = defaultdict(list)
        self.products_by_supplier: dict[int, list[Product]] = defaultdict(list)
        self.products_by_brand: dict[str, list[Product]] = defaultdict(list)
        for p in self.products:
            self.products_by_category[p.category].append(p)
            self.products_by_supplier[p.supplier_id].append(p)
            self.products_by_brand[_brand_key(p.brand)].append(p)
        self.staff_by_id = {s.id: s for s in self.staff}
        self.avg_price = {cat: sum(p.gross_price for p in ps) / len(ps) for cat, ps in self.products_by_category.items()}

    # ---------------------------------------------------------------- staff
    def load_staff(self, identity_db: Path) -> None:
        con = sqlite3.connect(f"file:{identity_db}?mode=ro", uri=True)
        rows = con.execute(
            "select u.id, u.name, u.email, coalesce(r.name, ''), coalesce(d.name, '') from users u "
            "left join user_roles ur on ur.user_id = u.id left join roles r on r.id = ur.role_id "
            "left join departments d on d.id = u.department_id order by u.id").fetchall()
        next_id = max(r[0] for r in rows) + 1
        con.close()
        for uid, name, email, role, dept in rows:
            self.staff.append(Staff(uid, name, email, role, dept, None, is_new=False))
            if role == "Super Admin":
                self.admin_id = uid
        existing_sale = next((s for s in self.staff if s.role == "Sale"), None)
        if existing_sale:
            existing_sale.region = "Bắc"

        plan = [
            ("Sale", "Phòng Kinh Doanh", "Bắc", 2), ("Sale", "Phòng Kinh Doanh", "Trung", 2),
            ("Sale", "Phòng Kinh Doanh", "Nam", 1), ("Sale", "Phòng Kinh Doanh", "Dự án", 1),
            ("Sale", "Phòng Kinh Doanh", "Showroom", 1),
            ("Purchase", "Phòng Mua Hàng & Cung Ứng", None, 1),
            ("Logistic", "Bộ Phận Kho Vận & Điều Vận", None, 3),
            ("Finance & Accounting", "Phòng Tài Chính Kế Toán", None, 1),
        ]
        for role, dept, region, count in plan:
            for _ in range(count):
                name, _ = self.people.person()
                local = slugify(name.split()[-1]) + "." + "".join(slugify(w)[0] for w in name.split()[:-1])
                self.staff.append(Staff(next_id, name, f"{local}{next_id}@erp.local", role, dept, region))
                next_id += 1
        # Tên vai trò dùng trong engine
        for s in self.staff:
            if s.role == "Finance & Accounting":
                s.role = "Finance"
            elif s.role == "Finance & Accounting Manager":
                s.role = "Finance Manager"

    def sales_rep(self, region: str) -> Staff:
        reps = [s for s in self.staff if s.role == "Sale" and s.region == region]
        return self.rng.choice(reps or [s for s in self.staff if s.role == "Sale"])

    # ------------------------------------------------ suppliers & products
    def build_suppliers_and_products(self) -> None:
        raw = json.loads((DATA_DIR / "products_raw.json").read_text(encoding="utf-8"))
        by_name: dict[str, Supplier] = {}

        def supplier_for(brand: str, origin: str) -> Supplier:
            info = BRAND_SUPPLIERS.get(_brand_key(brand))
            if not info:
                info = TRADING_IMPORTER if origin in ("Trung Quốc", "Hàn Quốc", "Thái Lan", "Malaysia", "Indonesia") \
                    else TRADING_LOCAL
            name, kind, stype, place, lead = info
            if name not in by_name:
                by_name[name] = self.make_supplier(len(by_name) + 1, name, kind, stype, place, lead)
            sup = by_name[name]
            if brand not in sup.brands:
                sup.brands.append(brand)
            return sup

        seen_sku: set[str] = set()
        start_at = datetime.combine(C.START_DATE, time(7, 0))
        for item in raw:
            price = item.get("list_price") or item.get("price")
            if not price or price < 100_000:
                continue
            brand = item.get("brand") or "Khác"
            origin = item.get("origin") or "Trung Quốc"
            base_sku = item["model"].upper()
            sku = base_sku[:50]
            if len(base_sku) > 50 or sku in seen_sku:
                digest = hashlib.sha1(item["source_url"].encode()).hexdigest().upper()
                sku = f"{base_sku[:41]}-{digest[:8]}"
                suffix = 1
                while sku in seen_sku:
                    tag = f"-{digest[:6]}{suffix:02d}"
                    sku = f"{base_sku[:50 - len(tag)]}{tag}"
                    suffix += 1
            seen_sku.add(sku)
            sup = supplier_for(brand, origin)
            launch = None
            if item.get("launch_year"):
                digits = "".join(ch for ch in str(item["launch_year"]) if ch.isdigit())
                launch = int(digits[-4:]) if len(digits) >= 4 else None
            available_from = C.START_DATE
            if launch and launch >= 2026:
                available_from = date(2026, 2, 1) + timedelta(days=self.rng.randint(0, 190))
            elif launch == 2025 and self.rng.random() < 0.3:
                available_from = C.START_DATE + timedelta(days=self.rng.randint(10, 90))
            decay = bool(launch and launch <= 2022)
            discontinued = None
            if decay and self.rng.random() < 0.3:
                discontinued = date(2026, 3, 1) + timedelta(days=self.rng.randint(0, 160))
            gross = float(round(price, -4))
            cost = float(round(gross * self.rng.uniform(*C.COST_RATIO_RANGE), -3))
            category = item["category"]
            specs = item.get("specs") or {}
            spec = "; ".join(f"{k}: {v}" for k, v in list(specs.items())[:SPEC_KEYS_LIMIT]
                             if k not in ("Sản xuất tại", "Thời gian ra mắt"))
            pid = len(self.products) + 1
            created = start_at if available_from <= C.START_DATE else datetime.combine(
                available_from - timedelta(days=self.rng.randint(3, 14)), time(9, self.rng.randint(0, 59)))
            self.products.append(Product(
                id=pid, sku=sku, model=item["model"], name=item["name"], category=category, brand=brand,
                origin=origin, unit="Bộ" if category == "Điều hòa" else "Cái", gross_price=gross, cost_price=cost,
                vat=C.VAT_BY_CATEGORY.get(category, C.VAT_DEFAULT), supplier_id=sup.id,
                popularity=(item.get("sold", 0) + 5 * (item.get("rating_count") or 0) + 10) ** 0.75,
                spec=spec, launch_year=launch, available_from=available_from,
                pack=1 if gross >= 5_000_000 else (2 if gross >= 1_000_000 else 6),
                decay=decay, discontinued_on=discontinued,
                guess_error=self.rng.uniform(0.5, 1.8) if available_from > C.START_DATE else 1.0,
                created_at=created,
            ))
        self.suppliers = sorted(by_name.values(), key=lambda s: s.id)

    def make_supplier(self, sid: int, name: str, kind: str, stype: str, place: str, lead) -> Supplier:
        foreign = kind == "import"
        contact, _ = self.people.person()
        if foreign:
            contact = self.rng.choice(["Mr. Kevin Zhang", "Ms. Lily Chen", "Mr. Park Ji-hoon", "Ms. Anna Weber",
                                       "Mr. Somchai Wong", "Ms. Grace Tan", "Mr. David Liu", "Ms. Kim Soo-jin"])
        domain = slugify(name.replace("Công ty", "").replace("TNHH", "").replace("CP", ""), "-")[:24]
        group = "Điện lạnh - Điện tử" if any(k in name for k in ("LG", "Samsung", "Daikin", "Gree", "Midea", "Toshiba",
                                                                  "Sharp", "Aqua", "Casper", "Nagakawa")) \
            else "Gia dụng"
        return Supplier(
            id=sid, code=f"NCC-{sid:03d}", name=name, kind=kind, supplier_type=stype, group=group,
            currency="USD" if foreign else "VND", lead_days=lead,
            payment_days=self.rng.choice([30, 45]) if not foreign else 60,
            min_order_value=(300_000_000 if foreign else 30_000_000),
            tax_code=None if foreign else self.people.tax_code(place),
            address=place if foreign else self.people.address(place),
            phone=f"+{self.rng.randint(60, 86)} {self.rng.randint(10, 99)} {self.rng.randint(1000, 9999)} {self.rng.randint(1000, 9999)}"
            if foreign else self.people.phone(mobile=False),
            email=f"sales@{domain}.example",
            contact_person=contact,
            contact_phone=self.people.phone(),
            contact_email=self.people.email(contact.replace("Mr. ", "").replace("Ms. ", ""), domain),
            bank_name=self.rng.choice(["HSBC Hong Kong", "Bank of China", "Citibank Singapore", "Shinhan Bank"])
            if foreign else self.rng.choice(["Vietcombank", "VietinBank", "BIDV", "Techcombank", "MB Bank", "ACB"]),
            bank_account=str(self.rng.randint(10**11, 10**12 - 1)),
            notes="Nhập khẩu trực tiếp, thanh toán T/T 60 ngày" if foreign else None,
        )

    # -------------------------------------------------------------- customers
    def next_customer_code(self, ctype: str) -> str:
        prefix = C.CUSTOMER_PREFIX[ctype]
        while True:
            code = f"{prefix}-{self.rng.randint(1, 99999):05d}"
            if code not in self._used_codes:
                self._used_codes.add(code)
                return code

    def _new_customer(self, ctype: str, since: datetime, region: str | None = None) -> Customer:
        self._customer_id += 1
        region = region or self.rng.choices(list(C.REGION_WEIGHT), weights=list(C.REGION_WEIGHT.values()))[0]
        province = self.people.pick_province(region)
        p = self.people
        if ctype == "Dealer":
            name = p.dealer_name(province)
        elif ctype == "Distributor":
            name = p.distributor_name(province)
        elif ctype == "Project / Business":
            name = p.project_name(province)
        else:
            name, _ = p.person()
        rep_region = "Dự án" if ctype == "Project / Business" else ("Showroom" if ctype == "Retail" else region)
        lo, hi = C.CREDIT_LIMIT[ctype]
        credit = float(round(self.rng.uniform(lo, hi), -7)) if hi else 0.0
        is_company = ctype != "Retail" and not name.startswith(("Điện máy", "Cửa hàng", "Gia dụng", "Hộ kinh doanh"))
        c = Customer(
            id=self._customer_id, code=self.next_customer_code(ctype), type=ctype, name=name, region=region,
            province=province, address=p.address(province),
            tax_code=p.tax_code(province) if (is_company or (ctype == "Dealer" and self.rng.random() < 0.6)) else None,
            phone=p.phone(), email=p.email("kinhdoanh" if ctype != "Retail" else name, name) if ctype != "Retail"
            and self.rng.random() < 0.9 else (p.email(name, "gmail") if self.rng.random() < 0.4 else None),
            credit_limit=credit, terms_days=C.PAYMENT_TERMS_DAYS[ctype],
            salesperson_id=self.sales_rep(rep_region).id, since=since,
            discount=round(self.rng.uniform(*C.DISCOUNT_RANGE[ctype]), 0),
        )
        if ctype != "Retail":
            owner, g = p.person()
            c.contacts.append(Contact(owner, "Chủ cửa hàng" if ctype == "Dealer" else "Giám đốc", c.phone,
                                      p.email(owner, name), True))
            if self.rng.random() < 0.7:
                buyer, _ = p.person()
                position = {"Dealer": "Kế toán", "Distributor": "Trưởng phòng Mua hàng",
                            "Project / Business": "Phụ trách mua sắm"}[ctype]
                c.contacts.append(Contact(buyer, position, p.phone(), p.email(buyer, name), False))
        return c

    def build_customers(self) -> None:
        start = datetime.combine(C.START_DATE, time(7, 15))
        horizon = (C.END_DATE - C.START_DATE).days
        plan = {"Distributor": (C.CUSTOMER_MIX["Distributor"], 0),
                "Dealer": (C.CUSTOMER_MIX["Dealer"] - 30, 30),
                "Project / Business": (C.CUSTOMER_MIX["Project / Business"] - 20, 20)}
        for ctype, (existing, joining) in plan.items():
            for i in range(existing + joining):
                if i < existing:
                    since = start + timedelta(minutes=self._customer_id)
                else:
                    day = C.START_DATE + timedelta(days=self.rng.randint(20, horizon - 20))
                    since = datetime.combine(day, time(self.rng.randint(8, 16), self.rng.randint(0, 59)))
                region = None if ctype != "Distributor" else self.rng.choices(["Bắc", "Trung", "Nam"], [0.4, 0.35, 0.25])[0]
                c = self._new_customer(ctype, since, region)
                self.setup_behavior(c)
                self.b2b_customers.append(c)
        self.customers = list(self.b2b_customers)

    def setup_behavior(self, c: Customer) -> None:
        r = self.rng
        if c.type in C.MONTHLY_BUDGET:
            median, sigma = C.MONTHLY_BUDGET[c.type]
            c.budget = r.lognormvariate(math.log(median), sigma)
            lo, hi = C.ORDER_INTERVAL_DAYS[c.type]
            size_rank = min(1.0, c.budget / (median * 4))
            c.interval = round(hi - (hi - lo) * size_rank * r.uniform(0.6, 1.0))
            first = max(C.START_DATE, c.since.date())
            c.next_order = first + timedelta(days=r.randint(0, c.interval))
            if c.type == "Dealer" and r.random() < 0.05:
                c.active_until = date(2026, 1, 1) + timedelta(days=r.randint(0, 240))
        if c.type == "Project / Business":
            first = max(C.START_DATE, c.since.date())
            n = r.randint(*C.PROJECT_ORDERS_PER_YEAR)
            span = (C.END_DATE - first).days
            for _ in range(n):
                d = first + timedelta(days=r.randint(0, max(1, span)))
                while d.weekday() == 6 or d in C.HOLIDAYS:
                    d += timedelta(days=1)
                if d <= C.END_DATE:
                    c.project_dates.append(d)
        # Chuyên ngành: đại lý điện lạnh / gia dụng / tổng hợp
        focus = r.choices(["big", "small", "all"], [0.4, 0.3, 0.3])[0]
        for cat in C.CATEGORY_SHARE:
            base = {"big": 1.5 if cat in BIG_CATEGORIES else 0.5,
                    "small": 0.5 if cat in BIG_CATEGORIES else 1.8, "all": 1.0}[focus]
            c.affinity[cat] = base * r.lognormvariate(0, 0.4)
        c.late_payer = r.random() < C.LATE_PAYER_RATIO
        c.pay_cycle = r.choice([7, 10, 15, 15, 30])
        c.next_pay = max(C.START_DATE, c.since.date()) + timedelta(days=c.pay_cycle)
        c.terms_days = {"Dealer": r.choice([15, 30, 30, 30, 45]), "Distributor": r.choice([30, 45, 45, 60]),
                        "Project / Business": r.choice([30, 45, 60, 60, 90])}.get(c.type, c.terms_days)

    def new_retail_customer(self, at: datetime) -> Customer:
        region = "Bắc"
        c = self._new_customer("Retail", at, region)
        if self.rng.random() < 0.8:
            c.province = "Thành phố Hà Nội"
            c.address = self.people.address(c.province)
        self.customers.append(c)
        return c
