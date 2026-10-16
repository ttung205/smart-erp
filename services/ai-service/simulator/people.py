"""Sinh tên người, tên doanh nghiệp, địa chỉ, MST, SĐT, email (đều là dữ liệu giả).

Email luôn dùng TLD `.example` (RFC 2606) để không bao giờ gửi được ra ngoài.
"""
from __future__ import annotations

import json
import random
import re
import unicodedata
from pathlib import Path

DATA_DIR = Path(__file__).resolve().parent / "data"

HO = (["Nguyễn"] * 30 + ["Trần"] * 11 + ["Lê"] * 9 + ["Phạm"] * 7 + ["Hoàng"] * 5 + ["Huỳnh"] * 4
      + ["Vũ"] * 4 + ["Võ"] * 4 + ["Phan"] * 3 + ["Đặng"] * 2 + ["Bùi"] * 2 + ["Đỗ"] * 2
      + ["Hồ", "Ngô", "Dương", "Lý", "Đinh", "Trịnh", "Đào", "Mai", "Cao", "Lương", "Tạ", "Chu"])
DEM_NAM = ["Văn", "Đức", "Minh", "Quang", "Hữu", "Thành", "Công", "Ngọc", "Xuân", "Tuấn", "Hoàng", "Mạnh", "Anh", "Trung"]
DEM_NU = ["Thị", "Thu", "Ngọc", "Thanh", "Minh", "Phương", "Bích", "Hồng", "Kim", "Mai", "Diệu", "Khánh"]
TEN_NAM = ["Hùng", "Dũng", "Tuấn", "Minh", "Nam", "Long", "Hải", "Sơn", "Thắng", "Cường", "Đức", "Hiếu", "Phong",
           "Quân", "Trung", "Tùng", "Việt", "Hoàng", "Khánh", "Bình", "Toàn", "Thành", "Hưng", "Lâm", "Kiên", "Đạt", "Vinh"]
TEN_NU = ["Lan", "Hương", "Hoa", "Mai", "Linh", "Trang", "Huyền", "Thảo", "Ngọc", "Hà", "Yến", "Nhung", "Phương",
          "Vân", "Thủy", "Hằng", "Nga", "Quỳnh", "Anh", "Loan", "Dung", "Giang", "Oanh", "Tâm"]

# Tên hiệu cửa hàng hay dùng ở Việt Nam
HIEU = ["Hoàng Phát", "Minh Phát", "Thành Đạt", "Phú Thịnh", "Hưng Thịnh", "An Phát", "Tân Phát", "Đại Phát",
        "Thịnh Vượng", "Phúc An", "Kim Long", "Hoàng Long", "Việt Hưng", "Quang Minh", "Hải Đăng", "Bảo Anh",
        "Trường Thịnh", "Đức Huy", "Gia Huy", "Nam Phong", "Thái Hòa", "Hồng Hà", "Song Long", "Toàn Thắng",
        "Phương Nam", "Thuận Phát", "Vạn Phúc", "Tiến Thành", "Hòa Bình", "Lộc Phát", "Ngọc Lan", "Mạnh Hùng",
        "Hải Yến", "Tuấn Linh", "Hùng Cường", "Minh Khai", "Kim Ngân", "Trường An", "Phát Đạt", "Quốc Bảo",
        "Hưng Long", "Đông Á", "Sao Mai", "Bình Minh", "Ánh Dương", "Thiên Phú", "Hồng Phúc", "Xuân Mai"]
DUONG = ["Trần Hưng Đạo", "Lê Lợi", "Nguyễn Trãi", "Quang Trung", "Hai Bà Trưng", "Lý Thường Kiệt", "Hùng Vương",
         "Lê Duẩn", "Nguyễn Huệ", "Phan Chu Trinh", "Điện Biên Phủ", "Võ Thị Sáu", "Nguyễn Văn Cừ", "Lê Hồng Phong",
         "Trường Chinh", "Phạm Văn Đồng", "Nguyễn Du", "Bà Triệu", "Ngô Quyền", "Lạc Long Quân", "Trần Phú",
         "Nguyễn Thái Học", "Tôn Đức Thắng", "Hoàng Hoa Thám", "Cách Mạng Tháng Tám", "Nguyễn Tất Thành",
         "Phan Đình Phùng", "Lê Thánh Tông", "Hoàng Diệu", "Chu Văn An", "Quốc lộ 1A", "Đại lộ Thăng Long"]

# Phân vùng 34 tỉnh/thành sau sáp nhập (theo tên trong dữ liệu hành chính 2025)
REGION_OF = {
    "Thành phố Hà Nội": "Bắc", "Tỉnh Cao Bằng": "Bắc", "Tỉnh Tuyên Quang": "Bắc", "Tỉnh Điện Biên": "Bắc",
    "Tỉnh Lai Châu": "Bắc", "Tỉnh Sơn La": "Bắc", "Tỉnh Lào Cai": "Bắc", "Tỉnh Thái Nguyên": "Bắc",
    "Tỉnh Lạng Sơn": "Bắc", "Tỉnh Quảng Ninh": "Bắc", "Tỉnh Bắc Ninh": "Bắc", "Tỉnh Phú Thọ": "Bắc",
    "Thành phố Hải Phòng": "Bắc", "Tỉnh Hưng Yên": "Bắc", "Tỉnh Ninh Bình": "Bắc",
    "Tỉnh Thanh Hóa": "Trung", "Tỉnh Nghệ An": "Trung", "Tỉnh Hà Tĩnh": "Trung", "Tỉnh Quảng Trị": "Trung",
    "Thành phố Huế": "Trung", "Thành phố Đà Nẵng": "Trung", "Tỉnh Quảng Ngãi": "Trung", "Tỉnh Gia Lai": "Trung",
    "Tỉnh Khánh Hòa": "Trung", "Tỉnh Đắk Lắk": "Trung", "Tỉnh Lâm Đồng": "Trung",
    "Tỉnh Đồng Nai": "Nam", "Thành phố Hồ Chí Minh": "Nam", "Tỉnh Tây Ninh": "Nam", "Tỉnh Đồng Tháp": "Nam",
    "Tỉnh Vĩnh Long": "Nam", "Tỉnh An Giang": "Nam", "Thành phố Cần Thơ": "Nam", "Tỉnh Cà Mau": "Nam",
}
# Trọng số dân số/sức mua tương đối trong vùng
PROVINCE_WEIGHT = {
    "Thành phố Hà Nội": 9, "Thành phố Hải Phòng": 4, "Tỉnh Bắc Ninh": 3, "Tỉnh Hưng Yên": 3, "Tỉnh Ninh Bình": 3,
    "Tỉnh Phú Thọ": 3, "Tỉnh Thái Nguyên": 2, "Tỉnh Quảng Ninh": 2, "Tỉnh Thanh Hóa": 4, "Tỉnh Nghệ An": 4,
    "Thành phố Hồ Chí Minh": 9, "Tỉnh Đồng Nai": 4, "Thành phố Đà Nẵng": 3, "Thành phố Cần Thơ": 3,
}


def strip_accents(text: str) -> str:
    text = text.replace("Đ", "D").replace("đ", "d")
    return "".join(c for c in unicodedata.normalize("NFD", text) if unicodedata.category(c) != "Mn")


def slugify(text: str, sep: str = "") -> str:
    return re.sub(r"[^a-z0-9]+", sep, strip_accents(text).lower()).strip(sep)


class People:
    def __init__(self, rng: random.Random):
        self.rng = rng
        admin = json.loads((DATA_DIR / "vn_admin_2025.json").read_text(encoding="utf-8"))
        self.provinces = {p["name"]: p["wards"] for p in admin}
        self._used_tax: set[str] = set()
        self._used_names: set[str] = set()

    # --- người -------------------------------------------------------------
    def person(self, gender: str | None = None) -> tuple[str, str]:
        gender = gender or self.rng.choice("MF")
        dem = self.rng.choice(DEM_NAM if gender == "M" else DEM_NU)
        ten = self.rng.choice(TEN_NAM if gender == "M" else TEN_NU)
        return f"{self.rng.choice(HO)} {dem} {ten}", gender

    def phone(self, mobile: bool = True) -> str:
        if mobile:
            prefix = self.rng.choice(["090", "091", "093", "094", "096", "097", "098", "086", "088", "089",
                                      "032", "033", "035", "037", "038", "039", "070", "077", "079", "081", "083", "085"])
            n = f"{self.rng.randint(0, 9_999_999):07d}"
            return f"{prefix[:4] if len(prefix) == 4 else prefix}{n[0]} {n[1:4]} {n[4:]}"
        area = self.rng.choice(["024", "028", "0236", "0225", "0237", "0238", "0292"])
        return f"{area} {self.rng.randint(3000, 3999)} {self.rng.randint(1000, 9999)}"

    @staticmethod
    def email(local: str, domain_hint: str) -> str:
        return f"{slugify(local, '.')}@{slugify(domain_hint, '-')[:30]}.example"

    def tax_code(self, province: str | None = None) -> str:
        """MST 10 số, có chữ số kiểm tra đúng thuật toán của Tổng cục Thuế."""
        prefix = {"Thành phố Hà Nội": "01", "Thành phố Hồ Chí Minh": "03", "Thành phố Đà Nẵng": "04",
                  "Thành phố Hải Phòng": "02"}.get(province or "", f"{self.rng.randint(5, 63):02d}")
        weights = [31, 29, 23, 19, 17, 13, 7, 5, 3]
        while True:
            body = prefix + "".join(str(self.rng.randint(0, 9)) for _ in range(7))
            check = 10 - sum(int(d) * w for d, w in zip(body, weights)) % 11
            if check == 10 or body + str(check) in self._used_tax:
                continue
            code = body + str(check)
            self._used_tax.add(code)
            return code

    # --- địa chỉ -----------------------------------------------------------
    def pick_province(self, region: str) -> str:
        names = [p for p, r in REGION_OF.items() if r == region]
        return self.rng.choices(names, weights=[PROVINCE_WEIGHT.get(p, 1) for p in names])[0]

    def address(self, province: str) -> str:
        ward = self.rng.choice(self.provinces[province])
        street = self.rng.choice(DUONG)
        number = self.rng.choice([str(self.rng.randint(1, 450)), f"{self.rng.randint(1, 200)}/{self.rng.randint(1, 40)}",
                                  f"Lô {self.rng.choice('ABCDE')}{self.rng.randint(1, 30)}"])
        return f"{number} {street}, {ward}, {province}"

    # --- doanh nghiệp ------------------------------------------------------
    def _unique(self, make) -> str:
        for _ in range(200):
            name = make()
            if name not in self._used_names:
                self._used_names.add(name)
                return name
        raise RuntimeError("Hết tổ hợp tên")

    def dealer_name(self, province: str) -> str:
        place = province.replace("Thành phố ", "").replace("Tỉnh ", "")
        forms = [
            lambda h: f"Điện máy {h}",
            lambda h: f"Cửa hàng Điện máy {h}",
            lambda h: f"Siêu thị Điện máy {h}",
            lambda h: f"Gia dụng {h}",
            lambda h: f"Công ty TNHH Thương mại {h}",
            lambda h: f"Công ty TNHH Điện máy {h} {place}",
            lambda h: f"Hộ kinh doanh Điện lạnh {h}",
            lambda h: f"Công ty CP Thương mại Điện máy {h}",
        ]
        return self._unique(lambda: self.rng.choice(forms)(self.rng.choice(HIEU)))

    def distributor_name(self, province: str) -> str:
        place = province.replace("Thành phố ", "").replace("Tỉnh ", "")
        forms = [
            lambda h: f"Công ty CP Phân phối {h} {place}",
            lambda h: f"Công ty TNHH Phân phối Điện máy {h}",
            lambda h: f"Công ty CP Thương mại & Dịch vụ {h}",
            lambda h: f"Công ty TNHH Đầu tư Thương mại {h} {place}",
        ]
        return self._unique(lambda: self.rng.choice(forms)(self.rng.choice(HIEU)))

    def project_name(self, province: str) -> str:
        place = province.replace("Thành phố ", "").replace("Tỉnh ", "")
        forms = [
            lambda h: f"Công ty CP Đầu tư Xây dựng {h}",
            lambda h: f"Công ty TNHH Khách sạn {h} {place}",
            lambda h: f"Công ty CP Bất động sản {h}",
            lambda h: f"Công ty TNHH Nội thất {h}",
            lambda h: f"Công ty CP Xây lắp {h}",
            lambda h: f"Trường Liên cấp {h} {place}",
            lambda h: f"Công ty TNHH Quản lý Tòa nhà {h}",
            lambda h: f"Công ty CP Du lịch {h} {place}",
        ]
        return self._unique(lambda: self.rng.choice(forms)(self.rng.choice(HIEU)))
