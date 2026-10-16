"""Tham số kinh doanh của công ty mô phỏng.

Công ty: nhà phân phối thiết bị gia dụng đặt kho tại Hà Nội, bán cho đại lý,
nhà phân phối cấp dưới, khách dự án và khách lẻ trên toàn quốc.
Mọi số liệu ở đây là giả định, chỉnh tại đây để đổi "tính cách" của dữ liệu.
"""
from datetime import date

SEED = 20251001

START_DATE = date(2025, 10, 1)   # ngày bắt đầu ghi nhận trên ERP (có số dư đầu kỳ)
END_DATE = date(2026, 9, 30)     # ngày cuối cùng có chứng từ

# --- Lịch làm việc ---------------------------------------------------------
TET_DATE = date(2026, 2, 17)     # Mùng 1 Tết Bính Ngọ
# Ngày nghỉ (không phát sinh chứng từ)
HOLIDAYS = {
    date(2026, 1, 1),
    *[date(2026, 2, d) for d in range(14, 23)],   # nghỉ Tết 14/2 - 22/2
    date(2026, 4, 26),                           # Giỗ Tổ (10/3 âm lịch)
    date(2026, 4, 30), date(2026, 5, 1),
    date(2026, 9, 1), date(2026, 9, 2),
}
SATURDAY_FACTOR = 0.5            # thứ 7 làm nửa ngày
# Chủ nhật: chỉ khách lẻ mua tại showroom

# --- Khách hàng -----------------------------------------------------------
# customer_type trong catalog: Retail / Dealer / Distributor / Project / Business
CUSTOMER_MIX = {
    "Dealer": 320,
    "Distributor": 18,
    "Project / Business": 45,
    "Retail": 1800,
}
CUSTOMER_PREFIX = {"Retail": "RE", "Dealer": "DE", "Distributor": "DI", "Project / Business": "PR"}

# Sức mua mỗi tháng (VND, trước VAT): trung vị và độ phân tán lognormal.
# Doanh thu năm mục tiêu ≈ 200 tỷ: đại lý ~55%, NPP ~30%, dự án ~12%, khách lẻ ~3%.
MONTHLY_BUDGET = {
    "Dealer": (15_000_000, 0.9),
    "Distributor": (240_000_000, 0.6),
}
ORDER_INTERVAL_DAYS = {"Dealer": (7, 30), "Distributor": (10, 20)}
PROJECT_ORDERS_PER_YEAR = (1, 4)
PROJECT_ORDER_VALUE = (125_000_000, 0.8)      # trung vị, sigma
RETAIL_ORDERS_PER_DAY = 2.2

# Tỷ trọng doanh thu theo ngành hàng (dùng để chọn ngành khi lên đơn)
CATEGORY_SHARE = {
    "Điều hòa": 22, "Tủ lạnh": 16, "Máy giặt": 13, "Tủ đông - Tủ mát": 4, "Máy nước nóng": 5,
    "Máy sấy quần áo": 2, "Máy lọc nước": 5, "Máy lọc không khí": 3, "Quạt điện": 4,
    "Quạt điều hòa": 1.5, "Quạt sưởi": 1.5, "Máy hút ẩm": 0.6, "Máy hút bụi": 1.6, "Robot hút bụi": 1.6,
    "Nồi cơm điện": 3, "Nồi chiên không dầu": 2.5, "Lò vi sóng": 1.5, "Bếp từ": 3, "Bếp hồng ngoại": 0.8,
    "Bếp ga": 1, "Máy hút mùi": 1.5, "Máy rửa chén": 1.2, "Bình đun siêu tốc": 0.6, "Máy xay sinh tố": 1,
    "Máy xay thịt": 0.3, "Nồi áp suất": 0.8, "Lò nướng": 0.6, "Máy ép trái cây": 0.6, "Bàn ủi": 0.5,
}
# Khách dự án (khách sạn, chung cư, trường học) chỉ mua một số ngành
PROJECT_CATEGORIES = {"Điều hòa": 5, "Máy nước nóng": 3, "Tủ lạnh": 2, "Máy giặt": 1, "Tủ đông - Tủ mát": 1,
                      "Máy lọc nước": 2, "Bếp từ": 1, "Máy hút mùi": 1, "Quạt điện": 1}

# Kho đặt tại Hà Nội nên khách tập trung miền Bắc
REGION_WEIGHT = {"Bắc": 0.58, "Trung": 0.25, "Nam": 0.17}

# Chiết khấu trên giá niêm yết (gross_price) theo loại khách: (min, max) %
DISCOUNT_RANGE = {
    "Distributor": (18, 24),
    "Dealer": (10, 16),
    "Project / Business": (8, 15),
    "Retail": (0, 5),
}
PAYMENT_TERMS_DAYS = {"Distributor": 45, "Dealer": 30, "Project / Business": 60, "Retail": 0}
# Hạn mức công nợ (VND)
CREDIT_LIMIT = {
    "Distributor": (3_000_000_000, 8_000_000_000),
    "Dealer": (300_000_000, 1_500_000_000),
    "Project / Business": (500_000_000, 3_000_000_000),
    "Retail": (0, 0),
}
# Tỷ lệ khách trả chậm hơn hạn (tạo nợ quá hạn thực tế)
LATE_PAYER_RATIO = 0.12

# --- Thuế ------------------------------------------------------------------
# Giảm thuế GTGT còn 8% đến hết 31/12/2026 (NQ 204/2025/QH15).
# Điều hòa ≤ 90.000 BTU chịu thuế TTĐB nên không được giảm, giữ 10%.
VAT_DEFAULT = 8
VAT_BY_CATEGORY = {"Điều hòa": 10}

# --- Giá vốn ---------------------------------------------------------------
# Giá mua từ hãng = giá niêm yết × hệ số (hãng chiết khấu cho nhà phân phối)
COST_RATIO_RANGE = (0.68, 0.78)
USD_VND = {  # tỷ giá bán USD tham khảo theo tháng (VCB), dùng cho PO nhập khẩu
    (2025, 10): 26_350, (2025, 11): 26_390, (2025, 12): 26_420,
    (2026, 1): 26_300, (2026, 2): 26_250, (2026, 3): 26_280,
    (2026, 4): 26_330, (2026, 5): 26_360, (2026, 6): 26_400,
    (2026, 7): 26_420, (2026, 8): 26_380, (2026, 9): 26_350,
}

# --- Mùa vụ ---------------------------------------------------------------
# Hệ số theo tháng (1..12) cho lượng hàng đại lý nhập về (sell-in đi trước
# mùa bán lẻ khoảng 1 tháng).
_FLAT = [1.0] * 12
_SUMMER = [0.6, 0.7, 1.6, 2.1, 2.0, 1.6, 1.1, 0.7, 0.5, 0.4, 0.4, 0.5]
_WINTER = [1.6, 0.8, 0.5, 0.3, 0.3, 0.3, 0.3, 0.3, 0.6, 1.0, 1.8, 2.0]
_TET = [1.7, 0.6, 0.8, 0.9, 0.9, 0.9, 0.9, 0.9, 1.0, 1.0, 1.2, 1.5]
_HUMID = [0.9, 1.6, 2.0, 1.4, 0.8, 0.7, 0.7, 0.7, 0.8, 0.8, 0.9, 0.9]   # nồm miền Bắc
_AIR = [1.5, 1.0, 0.8, 0.7, 0.6, 0.6, 0.6, 0.6, 0.8, 1.2, 1.5, 1.6]     # ô nhiễm mùa đông

SEASONALITY = {
    "Điều hòa": _SUMMER,
    "Quạt điện": _SUMMER,
    "Quạt điều hòa": _SUMMER,
    "Tủ đông - Tủ mát": [0.8, 0.6, 1.1, 1.4, 1.5, 1.4, 1.1, 0.9, 0.8, 0.8, 0.8, 1.1],
    "Tủ lạnh": [1.4, 0.6, 1.0, 1.2, 1.2, 1.1, 1.0, 0.9, 0.9, 0.9, 1.0, 1.3],
    "Máy nước nóng": _WINTER,
    "Quạt sưởi": [1.5, 0.5, 0.1, 0.05, 0.05, 0.05, 0.05, 0.05, 0.3, 1.0, 2.2, 2.5],
    "Máy sấy quần áo": _HUMID,
    "Máy hút ẩm": _HUMID,
    "Máy lọc không khí": _AIR,
    "Máy giặt": [1.3, 0.8, 1.1, 1.0, 1.0, 0.9, 0.9, 0.9, 0.9, 1.0, 1.0, 1.2],
}
DEFAULT_SEASONALITY = _TET          # đồ gia dụng nhỏ: cao điểm mua sắm Tết

# Ngành hàng gần như không bán ở miền Nam / Trung
REGION_CATEGORY_FACTOR = {
    "Quạt sưởi": {"Bắc": 1.0, "Trung": 0.3, "Nam": 0.0},
    "Máy nước nóng": {"Bắc": 1.0, "Trung": 0.6, "Nam": 0.3},
    "Máy hút ẩm": {"Bắc": 1.0, "Trung": 0.3, "Nam": 0.1},
    "Máy sấy quần áo": {"Bắc": 1.0, "Trung": 0.6, "Nam": 0.5},
    "Máy lọc không khí": {"Bắc": 1.0, "Trung": 0.6, "Nam": 0.8},
}

# Dịp khuyến mãi bán lẻ làm tăng đơn khách lẻ trong khoảng ngày
RETAIL_EVENTS = [
    (date(2025, 11, 9), date(2025, 11, 12), 1.8),    # 11.11
    (date(2025, 11, 26), date(2025, 11, 30), 1.6),   # Black Friday
    (date(2025, 12, 10), date(2025, 12, 13), 1.5),   # 12.12
    (date(2026, 6, 4), date(2026, 6, 7), 1.4),       # 6.6
]

# --- Mua hàng -------------------------------------------------------------
REVIEW_WEEKDAY = 0           # thứ 2 hằng tuần phòng mua rà soát tồn kho
SAFETY_DAYS = 7              # tồn an toàn ≈ 7 ngày bán
ORDER_COVER_DAYS = {"domestic": 7, "import": 20}   # mỗi lần đặt hàng đủ bán bao nhiêu ngày
SUPPLIER_LATE_RATIO = {"domestic": 0.08, "import": 0.20}
