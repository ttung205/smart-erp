"""Thu thập danh mục sản phẩm gia dụng thật từ dienmayxanh.com.

Chỉ đọc trang danh mục, trang danh mục theo hãng và trang chi tiết sản phẩm
(không gọi /aj/ vì robots.txt chặn). Chạy tuần tự, có delay và cache HTML
để chạy lại không phải tải lại.

    python -m simulator.scrape_products            # chạy đầy đủ
    python -m simulator.scrape_products --list-only  # chỉ quét trang danh mục

Kết quả: simulator/data/products_raw.json
"""
from __future__ import annotations

import argparse
import hashlib
import html
import json
import re
import sys
import time
from collections import Counter
from pathlib import Path

import requests

BASE = "https://www.dienmayxanh.com"
ROOT = Path(__file__).resolve().parent
CACHE_DIR = ROOT / ".cache" / "html"
OUT_FILE = ROOT / "data" / "products_raw.json"
DELAY_SECONDS = 1.5
USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/130.0 Safari/537.36"
)

# (slug trên DMX, tên danh mục trong ERP, số SKU muốn lấy)
CATEGORIES: list[tuple[str, str, int]] = [
    ("may-lanh", "Điều hòa", 110),
    ("tu-lanh", "Tủ lạnh", 100),
    ("may-giat", "Máy giặt", 80),
    ("may-say-quan-ao", "Máy sấy quần áo", 20),
    ("tu-dong", "Tủ đông - Tủ mát", 30),
    ("may-nuoc-nong", "Máy nước nóng", 40),
    ("noi-com-dien", "Nồi cơm điện", 50),
    ("noi-chien-khong-dau", "Nồi chiên không dầu", 45),
    ("lo-vi-song", "Lò vi sóng", 25),
    ("bep-tu", "Bếp từ", 40),
    ("bep-hong-ngoai", "Bếp hồng ngoại", 15),
    ("bep-ga", "Bếp ga", 25),
    ("may-hut-mui", "Máy hút mùi", 30),
    ("may-rua-chen", "Máy rửa chén", 20),
    ("binh-dun-sieu-toc", "Bình đun siêu tốc", 30),
    ("may-xay-sinh-to", "Máy xay sinh tố", 30),
    ("may-xay-thit", "Máy xay thịt", 10),
    ("noi-ap-suat", "Nồi áp suất", 20),
    ("lo-nuong", "Lò nướng", 20),
    ("may-ep-trai-cay", "Máy ép trái cây", 20),
    ("may-loc-nuoc", "Máy lọc nước", 50),
    ("may-loc-khong-khi", "Máy lọc không khí", 35),
    ("quat", "Quạt điện", 60),
    ("quat-dieu-hoa", "Quạt điều hòa", 25),
    ("quat-suoi", "Quạt sưởi", 25),
    ("may-hut-am", "Máy hút ẩm", 10),
    ("may-hut-bui", "Máy hút bụi", 30),
    ("robot-hut-bui", "Robot hút bụi", 25),
    ("ban-ui", "Bàn ủi", 20),
]

# Slug hãng hay gặp trong link lọc kiểu /tu-lanh-samsung
BRAND_SLUGS = {
    "lg", "samsung", "panasonic", "toshiba", "sharp", "hitachi", "electrolux",
    "aqua", "aqua-sanyo", "casper", "daikin", "gree", "midea", "nagakawa",
    "funiki", "comfee", "tcl", "beko", "haier", "hisense", "xiaomi", "mitsubishi",
    "mitsubishi-electric", "mitsubishi-heavy", "fujitsu", "sunhouse", "kangaroo",
    "karofi", "ao-smith", "aosmith", "hoa-phat", "mutosi", "toshiba-lifestyle",
    "philips", "tefal", "cuckoo", "bluestone", "lock-lock", "locknlock", "kitchenaid",
    "bosch", "hafele", "teka", "malloca", "canzy", "kocher", "elmich", "fujihome",
    "ferroli", "ariston", "atlantic", "rossi", "stiebel-eltron", "centon", "senko",
    "asia", "mitsubishi-electric", "kdk", "boss", "rapido", "ecovacs", "roborock",
    "dreame", "deerma", "coway", "sanaky", "alaska", "kahchan", "hommy", "makano",
    "delites", "toshiba-tu-lanh", "uniq", "perfect", "rinnai", "paloma", "namilux",
    "media", "pensonic", "bear", "bluestone", "joyoung", "tiger", "zojirushi",
    "supor", "hawonkoo", "gaabor", "ukg", "germ", "paveden", "tiross", "panworld",
}

PRODUCT_HREF = re.compile(r'href=["\'](/([a-z0-9-]+)/([a-z0-9-]+))["\']')
PRICE_RE = re.compile(r"(\d{1,3}(?:\.\d{3})+)\s*[₫đ]")
SOLD_RE = re.compile(r"Đã bán\s*([\d.,]+)\s*(k)?", re.IGNORECASE)
LD_JSON_RE = re.compile(r'<script type="application/ld\+json"[^>]*>(.*?)</script>', re.S)

session = requests.Session()
session.headers.update({"User-Agent": USER_AGENT, "Accept-Language": "vi-VN,vi;q=0.9"})
_last_request_at = 0.0


def log(msg: str) -> None:
    print(msg, flush=True)


def fetch(path: str) -> str | None:
    """GET có cache + delay. Trả None nếu lỗi."""
    global _last_request_at
    url = path if path.startswith("http") else BASE + path
    key = hashlib.sha1(url.encode()).hexdigest()
    cached = CACHE_DIR / f"{key}.html"
    if cached.exists():
        return cached.read_text(encoding="utf-8")

    for attempt in range(3):
        wait = DELAY_SECONDS - (time.time() - _last_request_at)
        if wait > 0:
            time.sleep(wait)
        _last_request_at = time.time()
        try:
            resp = session.get(url, timeout=30)
        except requests.RequestException as exc:
            log(f"  ! {url}: {exc}")
            time.sleep(5 * (attempt + 1))
            continue
        if resp.status_code == 200:
            CACHE_DIR.mkdir(parents=True, exist_ok=True)
            cached.write_text(resp.text, encoding="utf-8")
            return resp.text
        if resp.status_code in (404, 410):
            return None
        log(f"  ! {url}: HTTP {resp.status_code}")
        time.sleep(10 * (attempt + 1))
    return None


def parse_number(text: str) -> int:
    return int(text.replace(".", ""))


def parse_sold(num: str, k: str | None) -> int:
    value = float(num.replace(".", "").replace(",", ".")) if k else float(num.replace(".", "").replace(",", ""))
    return int(value * 1000) if k else int(value)


NON_PRODUCT_SEGMENTS = {"may-doi-tra", "khuyen-mai", "kinh-nghiem-hay", "dich-vu-bao-duong", "tin-tuc"}
GIFT_RE = re.compile(r"(Quà|Giảm|Tặng|Voucher)[^\d]{0,20}\d{1,3}(?:\.\d{3})+\s*[₫đ]", re.IGNORECASE)


def parse_listing(page: str, slug: str) -> list[dict]:
    """Tách thẻ sản phẩm trên trang danh mục (hỗ trợ cả template cũ và Next.js)."""
    page = html.unescape(page)
    hrefs = [(m.start(), m.group(1), m.group(2)) for m in PRODUCT_HREF.finditer(page)
             if m.group(2) not in NON_PRODUCT_SEGMENTS]
    if not hrefs:
        return []
    # Sản phẩm nằm dưới /<slug>/...; nếu trang bị redirect thì lấy thư mục xuất hiện nhiều nhất
    segments = Counter(seg for _, _, seg in hrefs)
    main_seg = slug if slug in segments else segments.most_common(1)[0][0]
    first_pos: dict[str, int] = {}
    for pos, href, seg in hrefs:
        if seg == main_seg and href not in first_pos:
            first_pos[href] = pos
    ordered = sorted(first_pos.items(), key=lambda x: x[1])

    cards = []
    for i, (href, start) in enumerate(ordered):
        end = ordered[i + 1][1] if i + 1 < len(ordered) else start + 6000
        chunk = GIFT_RE.sub(" ", re.sub(r"<[^>]+>", " ", page[start:end]))
        prices = [parse_number(p) for p in PRICE_RE.findall(chunk)]
        prices = [p for p in prices if p >= 50_000]
        if not prices:
            continue
        sold = SOLD_RE.search(chunk)
        cards.append({
            "url": href,
            "price": prices[0],
            "list_price": max(prices[:2]),  # giá gốc (gạch ngang) nếu có
            "sold": parse_sold(*sold.groups()) if sold else 0,
        })
    return cards


def brand_pages(page: str, slug: str) -> list[str]:
    found = set(re.findall(rf'href=["\'](?:{re.escape(BASE)})?/{re.escape(slug)}-([a-z0-9-]+)["\']', page))
    return sorted(f"/{slug}-{b}" for b in found if b in BRAND_SLUGS)


MODEL_RE = re.compile(r"^(?=.*\d)(?=.*[A-Z])[A-Z0-9][A-Z0-9\-/.()+]{3,}$")


def extract_model(name: str, url: str) -> str:
    tokens = name.split()
    # Model thường nằm cuối tên, có thể gồm 2 token (vd. "GR-RF611WI-PGV(22)-XK")
    for tok in reversed(tokens):
        if MODEL_RE.match(tok):
            return tok
    return url.rsplit("/", 1)[-1].upper()


def parse_detail(page: str) -> dict | None:
    for raw in LD_JSON_RE.findall(page):
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            continue
        if data.get("@type") not in ("ProductGroup", "Product"):
            continue
        props: dict[str, str] = {}
        for p in data.get("additionalProperty") or []:
            name, value = p.get("name"), p.get("value")
            if name and value and name not in props:
                props[name] = str(value).replace("|", ", ")
        variants = data.get("hasVariant") or [data]
        v0 = variants[0]
        offers = v0.get("offers") or {}
        rating = data.get("aggregateRating") or {}
        brand = data.get("brand")
        if isinstance(brand, list):
            brand = brand[0] if brand else None
        return {
            "variant_name": v0.get("name") or data.get("name"),
            "product_code": v0.get("sku"),
            "brand": brand.get("name") if isinstance(brand, dict) else brand,
            "dmx_category": data.get("category"),
            "list_price_detail": offers.get("price"),
            "rating": rating.get("ratingValue"),
            "rating_count": rating.get("ratingCount") or 0,
            "origin": props.get("Sản xuất tại") or props.get("Xuất xứ") or props.get("Nơi sản xuất"),
            "launch_year": props.get("Thời gian ra mắt") or props.get("Năm ra mắt"),
            "specs": props,
        }
    return None


def collect_listings() -> dict[str, list[dict]]:
    by_category: dict[str, list[dict]] = {}
    for slug, cat_name, quota in CATEGORIES:
        main = fetch(f"/{slug}")
        if not main:
            log(f"[{slug}] không tải được trang danh mục")
            by_category[slug] = []
            continue
        cards = {c["url"]: c for c in parse_listing(main, slug)}
        subpages = brand_pages(main, slug)
        for sub in subpages:
            page = fetch(sub)
            if page:
                for c in parse_listing(page, slug):
                    cards.setdefault(c["url"], c)
        by_category[slug] = list(cards.values())
        log(f"[{slug}] {len(cards)} sản phẩm từ {1 + len(subpages)} trang (cần {quota})")
    return by_category


def sitemap_product_urls(years: range) -> list[str]:
    """URL sản phẩm từ sitemap công khai (chia theo năm-tháng tạo sản phẩm)."""
    index = fetch("/newsitemap/sitemap-product") or ""
    urls: list[str] = []
    for loc in re.findall(r"<loc>([^<]+)</loc>", index):
        m = re.search(r"sitemap-product-(\d{4})-\d+", loc)
        if m and int(m.group(1)) in years:
            page = fetch(html.unescape(loc)) or ""
            urls += [u.replace(BASE, "") for u in re.findall(r"<loc>([^<]+)</loc>", page)]
    return urls


def build_product(slug: str, cat_name: str, url: str, card: dict, detail: dict) -> dict:
    name = detail.pop("variant_name") or url
    return {
        "source_url": BASE + url,
        "dmx_slug": slug,
        "category": cat_name,
        "name": name,
        "model": extract_model(name, url),
        **detail,
        "price": card.get("price") or detail.get("list_price_detail"),
        "list_price": max(card.get("list_price") or 0, detail.get("list_price_detail") or 0),
        "sold": card.get("sold", 0),
    }


def fill_from_sitemap() -> None:
    """Bù các danh mục chưa đủ quota (trang danh mục render bằng JS) từ sitemap sản phẩm."""
    products = json.loads(OUT_FILE.read_text(encoding="utf-8"))
    have = Counter(p["dmx_slug"] for p in products)
    seen = {p["source_url"].replace(BASE, "") for p in products}
    missing = {slug: (cat, quota - have[slug]) for slug, cat, quota in CATEGORIES if have[slug] < quota * 0.8}
    if not missing:
        log("Không có danh mục nào thiếu.")
        return
    log(f"Danh mục thiếu: { {s: n for s, (_, n) in missing.items()} }")
    urls = sitemap_product_urls(range(2022, 2027))
    for slug, (cat_name, need) in missing.items():
        candidates = [u for u in urls if u.startswith(f"/{slug}/") and u not in seen][: need * 3]
        found = []
        for url in candidates:
            page = fetch(url)
            detail = parse_detail(page) if page else None
            if detail and detail.get("list_price_detail"):
                found.append(build_product(slug, cat_name, url, {}, detail))
        found.sort(key=lambda p: p["rating_count"], reverse=True)
        products += found[:need]
        log(f"[{slug}] bù {len(found[:need])}/{need} từ sitemap ({len(candidates)} ứng viên)")
    OUT_FILE.write_text(json.dumps(products, ensure_ascii=False, indent=1), encoding="utf-8")
    log(f"Tổng {len(products)} sản phẩm")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--list-only", action="store_true")
    parser.add_argument("--fill", action="store_true", help="bù danh mục thiếu từ sitemap")
    args = parser.parse_args()

    if args.fill:
        fill_from_sitemap()
        return

    listings = collect_listings()
    if args.list_only:
        return

    seen: set[str] = set()
    selected: list[tuple[str, str, dict]] = []
    for slug, cat_name, quota in CATEGORIES:
        ranked = sorted(listings.get(slug, []), key=lambda c: c["sold"], reverse=True)
        picked = 0
        for card in ranked:
            if picked >= quota:
                break
            if card["url"] in seen:
                continue
            seen.add(card["url"])
            selected.append((slug, cat_name, card))
            picked += 1

    log(f"Tải chi tiết {len(selected)} sản phẩm...")
    products = []
    for i, (slug, cat_name, card) in enumerate(selected, 1):
        page = fetch(card["url"])
        detail = parse_detail(page) if page else None
        if not detail:
            log(f"  - bỏ qua {card['url']} (không đọc được chi tiết)")
            continue
        products.append(build_product(slug, cat_name, card["url"], card, detail))
        if i % 50 == 0:
            log(f"  {i}/{len(selected)}")

    OUT_FILE.parent.mkdir(parents=True, exist_ok=True)
    OUT_FILE.write_text(json.dumps(products, ensure_ascii=False, indent=1), encoding="utf-8")
    log(f"Đã ghi {len(products)} sản phẩm -> {OUT_FILE}")
    by_cat = Counter(p["category"] for p in products)
    for cat, n in by_cat.most_common():
        log(f"  {cat}: {n}")


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    main()
