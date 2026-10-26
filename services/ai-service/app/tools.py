"""Bộ tool chỉ-đọc cho trợ lý AI. Mỗi tool:
- khai báo schema (định dạng OpenAI function calling, Groq dùng chung),
- yêu cầu quyền của người hỏi (theo permissions trong JWT),
- tự tính mọi con số bằng SQL trên kho phân tích; LLM chỉ chọn tool và diễn giải.
"""
from __future__ import annotations

import unicodedata
from contextvars import ContextVar
from dataclasses import dataclass
from datetime import date, timedelta
from typing import Any, Callable

from . import db as database
from . import warehouse
from .auth import Actor

SALES_PERMS = ("order.view", "report.view")


# ----------------------------------------------------------------- helpers
def unaccent(text: str | None) -> str:
    if not text:
        return ""
    text = text.replace("Đ", "D").replace("đ", "d")
    return "".join(c for c in unicodedata.normalize("NFD", text) if unicodedata.category(c) != "Mn").lower()


def money(v: float | None) -> str:
    v = float(v or 0)
    sign = "-" if v < 0 else ""
    v = abs(v)
    if v >= 1e9:
        return f"{sign}{v / 1e9:,.2f} tỷ".replace(",", "X").replace(".", ",").replace("X", ".")
    if v >= 1e6:
        return f"{sign}{v / 1e6:,.1f} triệu".replace(",", "X").replace(".", ",").replace("X", ".")
    return f"{sign}{v:,.0f} đ".replace(",", ".")


def pct(part: float, whole: float) -> str:
    return f"{part / whole * 100:.1f}%" if whole else "—"


def change(new: float, old: float) -> str:
    if not old:
        return "mới phát sinh" if new else "—"
    return f"{(new - old) / old * 100:+.1f}%"


_open_connections: ContextVar[list | None] = ContextVar("open_connections", default=None)


def db() -> database.Connection:
    """Mở kết nối đọc kho phân tích; run_tool đóng lại sau khi tool chạy xong."""
    con = warehouse.connect()
    connections = _open_connections.get()
    if connections is not None:
        connections.append(con)
    return con


def ua(expr: str) -> str:
    """Biểu thức SQL so khớp không phân biệt dấu: collation unicode_ci bỏ dấu và hoa/thường, riêng đ/Đ phải đổi tay."""
    return f"replace(replace({expr}, 'đ', 'd'), 'Đ', 'D')"


def as_of(con: database.Connection) -> date:
    value = con.execute("select meta_value from meta where meta_key = 'as_of'").fetchone()
    return date.fromisoformat(value[0]) if value and value[0] else date.today()


def period(con, start: str | None, end: str | None, default_days: int = 30) -> tuple[str, str]:
    end_d = date.fromisoformat(end) if end else as_of(con)
    start_d = date.fromisoformat(start) if start else end_d - timedelta(days=default_days - 1)
    return start_d.isoformat(), end_d.isoformat()


class Filters:
    """Ghép điều kiện lọc dùng chung cho các truy vấn doanh số."""

    def __init__(self):
        self.sql: list[str] = []
        self.args: list[Any] = []

    def like(self, column: str, value: str | None) -> None:
        if value:
            self.sql.append(f"{ua(column)} like ?")
            self.args.append(f"%{unaccent(value)}%")

    def eq(self, column: str, value: Any) -> None:
        if value not in (None, ""):
            self.sql.append(f"{column} = ?")
            self.args.append(value)

    @property
    def where(self) -> str:
        return (" and " + " and ".join(self.sql)) if self.sql else ""


def sales_filters(args: dict) -> Filters:
    f = Filters()
    f.like("p.category", args.get("category"))
    f.like("p.brand", args.get("brand"))
    f.eq("c.type", args.get("customer_type"))
    f.eq("c.region", args.get("region"))
    f.like("c.name || ' ' || c.code", args.get("customer"))
    f.like("p.sku || ' ' || p.name", args.get("product"))
    f.like("st.name", args.get("salesperson"))
    return f


SALES_JOIN = ("from fact_sales s join dim_product p on p.id = s.product_id "
              "left join dim_customer c on c.id = s.customer_id left join dim_staff st on st.id = s.salesperson_id")
GROUP_EXPR = {
    "month": "substr(s.date, 1, 7)", "week": "date_format(s.date, '%Y-W%u')", "day": "s.date",
    "category": "p.category", "brand": "p.brand", "product": "p.sku || ' - ' || p.name",
    "customer": "c.code || ' - ' || c.name", "customer_type": "c.type", "region": "c.region",
    "province": "c.province", "salesperson": "st.name", "none": "'Tổng'",
}
TIME_GROUPS = {"month", "week", "day"}

FILTER_PROPS = {
    "category": {"type": "string", "description": "e.g. Điều hòa, Tủ lạnh"},
    "brand": {"type": "string"},
    "customer_type": {"type": "string", "enum": ["Dealer", "Distributor", "Project / Business", "Retail"]},
    "region": {"type": "string", "enum": ["Bắc", "Trung", "Nam"]},
    "customer": {"type": "string", "description": "name or code"},
    "product": {"type": "string", "description": "SKU or name"},
    "salesperson": {"type": "string"},
}
DATE_PROPS = {
    "start_date": {"type": "string", "description": "YYYY-MM-DD"},
    "end_date": {"type": "string", "description": "YYYY-MM-DD, default = latest data date"},
}


# ------------------------------------------------------------------- tools
def sales_report(actor: Actor, args: dict) -> dict:
    con = db()
    start, end = period(con, args.get("start_date"), args.get("end_date"))
    group_by = args.get("group_by", "none")
    expr = GROUP_EXPR.get(group_by, GROUP_EXPR["none"])
    f = sales_filters(args)
    base = f"{SALES_JOIN} where s.date between ? and ?{f.where}"
    params = [start, end, *f.args]
    total = con.execute(f"select sum(s.revenue), sum(s.cost), sum(s.quantity), count(distinct s.order_id), "
                        f"count(distinct s.customer_id) {base}", params).fetchone()
    revenue = total[0] or 0
    top_n = min(int(args.get("top_n") or 10), 25)
    order = "grp" if group_by in TIME_GROUPS else "revenue desc"
    limit = 400 if group_by in TIME_GROUPS else top_n
    rows = con.execute(f"select {expr} grp, sum(s.revenue) revenue, sum(s.cost) cost, sum(s.quantity) qty, "
                       f"count(distinct s.order_id) orders {base} group by grp order by {order} limit ?",
                       [*params, limit]).fetchall()
    return {
        "ky": f"{start} → {end}",
        "tong": {"doanh_thu": money(revenue), "loi_nhuan_gop_uoc_tinh": money(revenue - (total[1] or 0)),
                 "bien_lai_gop": pct(revenue - (total[1] or 0), revenue), "so_luong": int(total[2] or 0),
                 "so_don": total[3], "so_khach": total[4]},
        "theo": group_by,
        "dong": [{"nhom": r["grp"] or "(không rõ)", "doanh_thu": money(r["revenue"]), "ty_trong": pct(r["revenue"], revenue),
                  "so_luong": int(r["qty"] or 0), "so_don": r["orders"]} for r in rows],
        "ghi_chu": "Doanh thu trước VAT, ghi nhận khi xuất kho. Lợi nhuận gộp ước tính theo giá vốn hiện hành.",
    }


def compare_sales(actor: Actor, args: dict) -> dict:
    con = db()
    periods = sorted([(args["period_a_start"], args["period_a_end"]), (args["period_b_start"], args["period_b_end"])])
    before, after = periods
    group_by = args.get("group_by", "none")
    expr = GROUP_EXPR.get(group_by, GROUP_EXPR["none"])
    f = sales_filters(args)

    def collect(p):
        rows = con.execute(f"select {expr} grp, sum(s.revenue), sum(s.quantity) {SALES_JOIN} "
                           f"where s.date between ? and ?{f.where} group by grp", [p[0], p[1], *f.args]).fetchall()
        return {r[0]: (r[1] or 0, r[2] or 0) for r in rows}

    rb, ra = collect(before), collect(after)
    zero = (0, 0)
    keys = sorted(set(rb) | set(ra), key=lambda k: -(ra.get(k, zero)[0] + rb.get(k, zero)[0]))
    top_n = min(int(args.get("top_n") or 12), 25)
    tb, ta = sum(v[0] for v in rb.values()), sum(v[0] for v in ra.values())
    return {
        "ky_truoc": f"{before[0]} → {before[1]}", "ky_sau": f"{after[0]} → {after[1]}",
        "tong": {"ky_truoc": money(tb), "ky_sau": money(ta), "tang_giam_ky_sau_so_ky_truoc": change(ta, tb)},
        "dong": [{"nhom": k or "(không rõ)", "ky_truoc": money(rb.get(k, zero)[0]), "ky_sau": money(ra.get(k, zero)[0]),
                  "tang_giam": change(ra.get(k, zero)[0], rb.get(k, zero)[0]),
                  "sl_ky_truoc": int(rb.get(k, zero)[1]), "sl_ky_sau": int(ra.get(k, zero)[1])}
                 for k in keys[:top_n]],
    }


def _stock_rows(con, args: dict) -> tuple[list[dict], date]:
    today = as_of(con)
    d30, d90 = (today - timedelta(days=29)).isoformat(), (today - timedelta(days=89)).isoformat()
    f = Filters()
    f.like("p.category", args.get("category"))
    f.like("p.brand", args.get("brand"))
    f.like("p.sku || ' ' || p.name", args.get("product"))
    rows = con.execute(f"""
        select p.id, p.sku, p.name, p.category, p.brand, p.cost_price, p.min_stock, p.status,
               coalesce(k.on_hand, 0) on_hand, coalesce(k.available, 0) available,
               coalesce((select sum(quantity) from fact_sales s where s.product_id = p.id and s.date >= ?), 0) q30,
               coalesce((select sum(quantity) from fact_sales s where s.product_id = p.id and s.date >= ?), 0) q90,
               (select max(date) from fact_sales s where s.product_id = p.id) last_sale,
               coalesce((select sum(qty_ordered - qty_received) from fact_po_line l where l.product_id = p.id
                         and l.status in ('Approved', 'PartiallyReceived')), 0) incoming,
               (select min(expected_date) from fact_po_line l where l.product_id = p.id
                         and l.status in ('Approved', 'PartiallyReceived')) next_arrival,
               sp.name supplier
        from dim_product p left join stock k on k.product_id = p.id left join dim_supplier sp on sp.id = p.supplier_id
        where 1 = 1{f.where}""", [d30, d90, *f.args]).fetchall()
    out = []
    for r in rows:
        rate = r["q90"] / 90
        out.append({**dict(r), "rate": rate, "cover": (r["available"] / rate) if rate else None})
    return out, today


def inventory_status(actor: Actor, args: dict) -> dict:
    con = db()
    rows, today = _stock_rows(con, args)
    mode = args.get("mode", "low_stock")
    limit = min(int(args.get("limit") or 15), 30)
    stale = (today - timedelta(days=60)).isoformat()
    pick = {
        "out_of_stock": lambda r: r["available"] <= 0 and r["q90"] > 0,
        "low_stock": lambda r: r["rate"] > 0 and r["available"] > 0 and (r["available"] + r["incoming"]) / r["rate"] < 15,
        "overstock": lambda r: r["on_hand"] > 0 and (r["cover"] is None or r["cover"] > 90),
        "slow_moving": lambda r: r["on_hand"] > 0 and (r["last_sale"] is None or r["last_sale"] < stale),
        "all": lambda r: True,
    }[mode]
    chosen = [r for r in rows if pick(r)]
    sort = {"out_of_stock": lambda r: -r["q90"], "low_stock": lambda r: r["cover"] or 0,
            "overstock": lambda r: -(r["on_hand"] * r["cost_price"]),
            "slow_moving": lambda r: -(r["on_hand"] * r["cost_price"]), "all": lambda r: -r["q90"]}[mode]
    chosen.sort(key=sort)
    value = sum(r["on_hand"] * r["cost_price"] for r in rows)
    return {
        "ngay_du_lieu": today.isoformat(), "che_do": mode, "so_ma_thoa_dieu_kien": len(chosen),
        "tong_gia_tri_ton_kho_pham_vi_loc": money(value),
        "gia_tri_ton_cua_nhom_nay": money(sum(r["on_hand"] * r["cost_price"] for r in chosen)),
        "dong": [{"sku": r["sku"], "ten": r["name"], "nganh": r["category"], "ton_thuc_te": r["on_hand"],
                  "kha_dung": r["available"], "ban_30_ngay": int(r["q30"]), "ban_90_ngay": int(r["q90"]),
                  "so_ngay_du_ban": round(r["cover"]) if r["cover"] is not None else "không bán 90 ngày",
                  "dang_ve": r["incoming"], "du_kien_ve": r["next_arrival"], "ban_gan_nhat": r["last_sale"],
                  "gia_tri_ton": money(r["on_hand"] * r["cost_price"]), "ncc": r["supplier"]}
                 for r in chosen[:limit]],
        "ghi_chu": "Số ngày đủ bán = khả dụng / bình quân bán 90 ngày.",
    }


def product_info(actor: Actor, args: dict) -> dict:
    con = db()
    words = unaccent(args.get("query")).split() or [""]
    text = ua("p.sku || ' ' || p.model || ' ' || p.name || ' ' || p.category || ' ' || p.brand")
    match = " and ".join([f"{text} like ?"] * len(words))
    rows = con.execute(f"""
        select p.*, coalesce(k.on_hand, 0) on_hand, coalesce(k.available, 0) available, sp.name supplier
        from dim_product p left join stock k on k.product_id = p.id left join dim_supplier sp on sp.id = p.supplier_id
        where {match} order by p.status, p.name limit ?""",
                       [*(f"%{w}%" for w in words), min(int(args.get("limit") or 5), 10)]).fetchall()
    today = as_of(con)
    show_sales = actor.can(*SALES_PERMS, "inventory.view")
    show_stock = actor.can("inventory.view")
    out = []
    for r in rows:
        item = {"sku": r["sku"], "ten": r["name"], "nganh": r["category"], "hang": r["brand"], "xuat_xu": r["origin"],
                "gia_niem_yet": money(r["gross_price"]), "trang_thai": r["status"]}
        if actor.can("purchase.view", "finance.view"):
            item["gia_von"] = money(r["cost_price"])
            item["ncc"] = r["supplier"]
        if show_stock:
            item.update({"ton_thuc_te": r["on_hand"], "kha_dung": r["available"], "ton_toi_thieu": r["min_stock"]})
        if show_sales:
            q90 = con.execute("select coalesce(sum(quantity), 0), coalesce(sum(revenue), 0) from fact_sales "
                              "where product_id = ? and date >= ?", [r["id"], (today - timedelta(days=89)).isoformat()]).fetchone()
            item.update({"ban_90_ngay": int(q90[0]), "doanh_thu_90_ngay": money(q90[1])})
        out.append(item)
    return {"ket_qua": out, "so_ket_qua": len(out)}


def product_trend(actor: Actor, args: dict) -> dict:
    con = db()
    q = unaccent(args.get("product"))
    text = ua("sku || ' ' || name")
    p = con.execute(f"select id, sku, name from dim_product where {text} like ? limit 1", [f"%{q}%"]).fetchone()
    if not p:
        return {"loi": f"Không tìm thấy sản phẩm '{args.get('product')}'"}
    rows = con.execute("select substr(date, 1, 7) m, sum(quantity), sum(revenue) from fact_sales where product_id = ? "
                       "group by m order by m", [p["id"]]).fetchall()
    return {"sku": p["sku"], "ten": p["name"],
            "theo_thang": [{"thang": r[0], "so_luong": int(r[1]), "doanh_thu": money(r[2])} for r in rows]}


def customer_info(actor: Actor, args: dict) -> dict:
    con = db()
    f = Filters()
    f.like("c.name || ' ' || c.code", args.get("query"))
    f.eq("c.type", args.get("customer_type"))
    f.eq("c.region", args.get("region"))
    f.like("c.province", args.get("province"))
    today = as_of(con)
    year_ago = (today - timedelta(days=364)).isoformat()
    sort = args.get("sort", "revenue")
    rows = con.execute(f"""
        select c.*, coalesce(sum(s.revenue), 0) revenue, max(s.date) last_purchase
        from dim_customer c left join fact_sales s on s.customer_id = c.id and s.date >= ?
        where 1 = 1{f.where} group by c.id
        order by {'revenue desc' if sort == 'revenue' else 'last_purchase asc'} limit ?""",
                       [year_ago, *f.args, min(int(args.get("limit") or 5), 20)]).fetchall()
    out = []
    for r in rows:
        item = {"ma": r["code"], "ten": r["name"], "loai": r["type"], "tinh": r["province"], "vung": r["region"],
                "sdt": r["phone"], "email": r["email"], "trang_thai": r["status"],
                "ngay_mua_gan_nhat": r["last_purchase"]}
        if actor.can(*SALES_PERMS):
            item["doanh_thu_12_thang"] = money(r["revenue"])
        if actor.can("finance.view"):
            debt = con.execute("select coalesce(sum(outstanding), 0), coalesce(sum(case when due_date < ? then "
                               "outstanding end), 0) from receivable where customer_id = ?", [today.isoformat(), r["id"]]).fetchone()
            item.update({"han_muc_cong_no": money(r["credit_limit"]), "cong_no": money(debt[0]), "qua_han": money(debt[1])})
        out.append(item)
    return {"ket_qua": out, "so_ket_qua": len(out)}


def receivables_report(actor: Actor, args: dict) -> dict:
    con = db()
    today = as_of(con).isoformat()
    mode = args.get("mode", "top_debtors")
    top_n = min(int(args.get("top_n") or 10), 25)
    f = Filters()
    f.like("c.name || ' ' || c.code", args.get("customer"))
    f.eq("c.type", args.get("customer_type"))
    base = f"from receivable r join dim_customer c on c.id = r.customer_id where r.outstanding > 0{f.where}"
    total = con.execute(f"select sum(r.outstanding), sum(case when r.due_date < ? then r.outstanding end), "
                        f"count(distinct r.customer_id) {base}", [today, *f.args]).fetchone()
    result = {"ngay_du_lieu": today, "tong_cong_no": money(total[0]), "qua_han": money(total[1]),
              "so_khach_con_no": total[2]}
    if mode == "aging":
        buckets = con.execute(f"""select
            sum(case when r.due_date >= ? then r.outstanding end),
            sum(case when datediff(?, r.due_date) between 1 and 30 then r.outstanding end),
            sum(case when datediff(?, r.due_date) between 31 and 60 then r.outstanding end),
            sum(case when datediff(?, r.due_date) between 61 and 90 then r.outstanding end),
            sum(case when datediff(?, r.due_date) > 90 then r.outstanding end) {base}""",
                              [today] * 5 + f.args).fetchone()
        result["tuoi_no"] = dict(zip(["trong_han", "qua_han_1_30", "qua_han_31_60", "qua_han_61_90", "qua_han_tren_90"],
                                     map(money, buckets)))
        return result
    overdue_only = mode == "overdue"
    rows = con.execute(f"""select c.code, c.name, c.type, sum(r.outstanding) debt,
            sum(case when r.due_date < ? then r.outstanding end) overdue,
            max(datediff(?, r.due_date)) max_days, count(*) invoices, min(r.due_date) oldest_due
            {base} group by c.id {'having overdue > 0' if overdue_only else ''}
            order by {'overdue' if overdue_only else 'debt'} desc limit ?""", [today, today, *f.args, top_n]).fetchall()
    result["dong"] = [{"ma": r["code"], "khach": r["name"], "loai": r["type"], "con_no": money(r["debt"]),
                       "qua_han": money(r["overdue"]), "qua_han_lau_nhat_ngay": max(0, int(r["max_days"] or 0)),
                       "so_hoa_don": r["invoices"], "han_som_nhat": r["oldest_due"]} for r in rows]
    return result


def purchase_report(actor: Actor, args: dict) -> dict:
    con = db()
    today = as_of(con).isoformat()
    mode = args.get("mode", "open_orders")
    limit = min(int(args.get("limit") or 15), 30)
    f = Filters()
    f.like("sp.name || ' ' || sp.code", args.get("supplier"))
    f.like("p.sku || ' ' || p.name", args.get("product"))
    join = "from fact_po_line l join dim_supplier sp on sp.id = l.supplier_id join dim_product p on p.id = l.product_id"
    value = "sum(l.qty_ordered * l.unit_cost)"
    if mode in ("open_orders", "late_orders"):
        late = " and l.expected_date < ?" if mode == "late_orders" else ""
        params = [*f.args, *([today] if late else []), limit]
        rows = con.execute(f"""select l.po_code, l.date, l.expected_date, l.status, sp.name supplier, l.currency,
                {value} value, sum(l.qty_ordered - l.qty_received) pending, count(*) line_count
                {join} where l.status in ('Approved', 'PartiallyReceived'){f.where}{late}
                group by l.po_id order by l.expected_date limit ?""", params).fetchall()
        return {"ngay_du_lieu": today, "che_do": mode, "so_po": len(rows), "dong": [
            {"po": r["po_code"], "ngay_dat": r["date"], "du_kien_ve": r["expected_date"], "trang_thai": r["status"],
             "ncc": r["supplier"], "gia_tri": f"{r['value']:,.0f} USD" if r["currency"] == "USD" else money(r["value"]),
             "so_luong_chua_ve": int(r["pending"]), "so_dong": r["line_count"]} for r in rows]}
    start, end = period(con, args.get("start_date"), args.get("end_date"), default_days=365)
    rows = con.execute(f"""select sp.name supplier, sp.code, max(l.currency) currency, count(distinct l.po_id) pos,
            {value} value,
            avg(case when l.first_receipt is not null then datediff(l.first_receipt, l.date) end) lead_days,
            count(distinct case when l.first_receipt > l.expected_date then l.po_id end) late,
            count(distinct case when l.first_receipt is not null then l.po_id end) received
            {join} where l.date between ? and ?{f.where} group by sp.id
            order by {"1.0 * late / greatest(received, 1) desc" if args.get("sort") == "late_rate" else "value desc"} limit ?""",
                       [start, end, *f.args, limit]).fetchall()
    return {"ky": f"{start} → {end}", "che_do": "supplier_performance", "dong": [
        {"ncc": r["supplier"], "ma": r["code"], "so_po": r["pos"],
         "gia_tri_mua": f"{r['value']:,.0f} USD" if r["currency"] == "USD" else money(r["value"]),
         "lead_time_tb_ngay": round(r["lead_days"] or 0, 1), "ty_le_giao_tre": pct(r["late"], r["received"])} for r in rows]}


def supplier_info(actor: Actor, args: dict) -> dict:
    con = db()
    rows = con.execute(f"""select sp.*, (select count(*) from dim_product p where p.supplier_id = sp.id) products
        from dim_supplier sp where {ua("sp.name || ' ' || sp.code || ' ' || coalesce(sp.notes, '')")} like ?
        limit ?""", [f"%{unaccent(args.get('query'))}%", min(int(args.get("limit") or 5), 15)]).fetchall()
    return {"ket_qua": [{"ma": r["code"], "ten": r["name"], "loai": r["supplier_type"], "nhom": r["supplier_group"],
                         "dieu_khoan_thanh_toan": r["payment_terms"], "email": r["email"], "sdt": r["phone"],
                         "nguoi_lien_he": r["contact_person"], "ghi_chu": r["notes"], "so_ma_hang": r["products"]}
                        for r in rows]}


def order_list(actor: Actor, args: dict) -> dict:
    con = db()
    start, end = period(con, args.get("start_date"), args.get("end_date"))
    f = Filters()
    f.like("c.name || ' ' || c.code", args.get("customer"))
    f.eq("o.status", args.get("status"))
    f.like("o.order_number", args.get("order_number"))
    rows = con.execute(f"""select o.order_number, o.date, o.status, o.total, c.name customer, st.name salesperson,
            (select sum(quantity) from fact_order_line l where l.order_id = o.id) qty,
            (select sum(delivered) from fact_order_line l where l.order_id = o.id) delivered
            from fact_order o left join dim_customer c on c.id = o.customer_id left join dim_staff st on st.id = o.salesperson_id
            where o.date between ? and ?{f.where} order by o.date desc limit ?""",
                       [start, end, *f.args, min(int(args.get("limit") or 15), 30)]).fetchall()
    summary = con.execute(f"select o.status, count(*), sum(o.total) from fact_order o left join dim_customer c "
                          f"on c.id = o.customer_id where o.date between ? and ?{f.where} group by o.status",
                          [start, end, *f.args]).fetchall()
    return {"ky": f"{start} → {end}",
            "theo_trang_thai": {r[0]: {"so_don": r[1], "gia_tri": money(r[2])} for r in summary},
            "dong": [{"so_don": r["order_number"], "ngay": r["date"], "trang_thai": r["status"],
                      "gia_tri_gom_vat": money(r["total"]), "khach": r["customer"], "nv_kinh_doanh": r["salesperson"],
                      "da_giao": f"{int(r['delivered'] or 0)}/{int(r['qty'] or 0)}"} for r in rows]}


# --------------------------------------------------------------- registry
@dataclass
class Tool:
    name: str
    description: str
    parameters: dict
    permissions: tuple[str, ...]
    handler: Callable[[Actor, dict], dict]

    def schema(self) -> dict:
        # Tham số tùy chọn cho phép null: một số model (gpt-oss) gửi null thay vì bỏ trống
        params = {**self.parameters, "properties": {}}
        for key, prop in self.parameters["properties"].items():
            if key not in self.parameters.get("required", []):
                prop = {**prop, "type": [prop["type"], "null"]}
                if "enum" in prop:
                    prop["enum"] = [*prop["enum"], None]
            params["properties"][key] = prop
        return {"type": "function", "function": {"name": self.name, "description": self.description,
                                                 "parameters": params}}


def obj(props: dict, required: list[str] | None = None) -> dict:
    return {"type": "object", "properties": props, "required": required or []}


TOOLS: list[Tool] = [
    Tool("sales_report", "Revenue (before VAT), quantity, orders, estimated gross profit for a date range, "
         "optionally grouped and filtered.",
         obj({**DATE_PROPS, "group_by": {"type": "string", "enum": list(GROUP_EXPR)},
              "top_n": {"type": "integer"}, **FILTER_PROPS}), SALES_PERMS, sales_report),
    Tool("compare_sales", "Compare revenue between two periods (e.g. this month vs last month), optionally grouped.",
         obj({"period_a_start": {"type": "string"}, "period_a_end": {"type": "string"},
              "period_b_start": {"type": "string"}, "period_b_end": {"type": "string"},
              "group_by": {"type": "string", "enum": ["none", "category", "brand", "customer_type", "region",
                                                      "salesperson", "product", "customer"]},
              "top_n": {"type": "integer"}, **FILTER_PROPS},
             ["period_a_start", "period_a_end", "period_b_start", "period_b_end"]), SALES_PERMS, compare_sales),
    Tool("inventory_status", "Stock status with days of cover and incoming POs.",
         obj({"mode": {"type": "string", "enum": ["low_stock", "out_of_stock", "overstock", "slow_moving", "all"]},
              "category": FILTER_PROPS["category"], "brand": FILTER_PROPS["brand"],
              "product": FILTER_PROPS["product"], "limit": {"type": "integer"}}), ("inventory.view",), inventory_status),
    Tool("product_info", "Look up products: list price, stock, last 90 days sales.",
         obj({"query": {"type": "string"}, "limit": {"type": "integer"}}, ["query"]),
         ("catalog.view",), product_info),
    Tool("product_trend", "Monthly sales of one product.",
         obj({"product": FILTER_PROPS["product"]}, ["product"]), (*SALES_PERMS, "inventory.view"), product_trend),
    Tool("customer_info", "Look up/list customers: contact, 12-month revenue, last purchase, debt. "
         "sort=inactive finds customers not buying for long.",
         obj({"query": {"type": "string"}, "customer_type": FILTER_PROPS["customer_type"],
              "region": FILTER_PROPS["region"], "province": {"type": "string"},
              "sort": {"type": "string", "enum": ["revenue", "inactive"]}, "limit": {"type": "integer"}}),
         ("customer.view",), customer_info),
    Tool("receivables_report", "Accounts receivable: top debtors, overdue, aging buckets.",
         obj({"mode": {"type": "string", "enum": ["top_debtors", "overdue", "aging"]},
              "customer": FILTER_PROPS["customer"], "customer_type": FILTER_PROPS["customer_type"],
              "top_n": {"type": "integer"}}), ("finance.view",), receivables_report),
    Tool("purchase_report", "Purchasing: open POs, late POs, supplier performance (value, lead time, late rate; "
         "sort=late_rate to find suppliers delivering late most often).",
         obj({"mode": {"type": "string", "enum": ["open_orders", "late_orders", "supplier_performance"]},
              "sort": {"type": "string", "enum": ["value", "late_rate"]},
              "supplier": {"type": "string"}, "product": FILTER_PROPS["product"], **DATE_PROPS,
              "limit": {"type": "integer"}}), ("purchase.view",), purchase_report),
    Tool("supplier_info", "Look up suppliers by name/code/brand: payment terms, contacts.",
         obj({"query": {"type": "string"}, "limit": {"type": "integer"}}, ["query"]),
         ("supplier.view", "purchase.view"), supplier_info),
    Tool("order_list", "Sales orders by date range, customer, status "
         "(PENDING_APPROVAL, APPROVED, PROCESSING, COMPLETED, CANCELLED, REJECTED).",
         obj({**DATE_PROPS, "customer": FILTER_PROPS["customer"], "status": {"type": "string"},
              "order_number": {"type": "string"}, "limit": {"type": "integer"}}), ("order.view",), order_list),
]
# Mô tả phạm vi dữ liệu của từng tool, dùng để báo cho model biết người dùng KHÔNG có quyền xem gì
TOOL_SCOPE = {
    "sales_report": "doanh số", "inventory_status": "tồn kho", "receivables_report": "công nợ khách hàng",
    "purchase_report": "mua hàng / đơn mua", "supplier_info": "nhà cung cấp", "order_list": "đơn bán hàng",
    "customer_info": "thông tin khách hàng",
}
TOOL_BY_NAME = {t.name: t for t in TOOLS}


def available_tools(actor: Actor) -> list[Tool]:
    return [t for t in TOOLS if actor.can(*t.permissions)]


def run_tool(actor: Actor, name: str, args: dict) -> dict:
    connections: list[database.Connection] = []
    token = _open_connections.set(connections)
    try:
        return _run_tool(actor, name, args)
    finally:
        _open_connections.reset(token)
        for con in connections:
            con.close()


def _run_tool(actor: Actor, name: str, args: dict) -> dict:
    tool = TOOL_BY_NAME.get(name)
    if not tool:
        return {"loi": f"Không có công cụ {name}"}
    if not actor.can(*tool.permissions):
        return {"loi": "Người dùng không có quyền xem dữ liệu này", "can_quyen": list(tool.permissions)}
    try:
        return tool.handler(actor, args or {})
    except (ValueError, KeyError) as exc:
        return {"loi": f"Tham số không hợp lệ: {exc}"}
