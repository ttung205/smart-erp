import sys

tools_path = r"G:\Do_an_lien_nganh\smart-erp\services\ai-service\app\tools.py"
with open(tools_path, "r", encoding="utf-8") as f:
    content = f.read()

new_func = '''# ------------------------------------------------------------------- Actionable Sales Copilot
def instant_quote_check(actor: Actor, args: dict) -> dict:
    """Báo giá sỉ nhanh, tra cứu tồn khả dụng, kiểm tra chính sách chiết khấu theo phân hạng đối tác, kiểm tra hạn mức công nợ và sàn biên lợi nhuận gộp theo MÃ CODE SKU ĐẦY ĐỦ và MÃ KHÁCH HÀNG ĐẦY ĐỦ."""
    raw_sku = (args.get("sku") or args.get("product_code") or "").strip()
    if not raw_sku:
        return {
            "loi": (
                "Vui lòng cung cấp chính xác MÃ CODE SKU ĐẦY ĐỦ của sản phẩm cần báo giá "
                "(Ví dụ: RT38CG6584B1SV-SAM, CFS-13VDM-COM, DAIKIN-FTKF25XVMV). "
                "Hệ thống yêu cầu mã đầy đủ để đảm bảo tính toàn vẹn và truy vết chứng từ trong ERP."
            )
        }

    raw_customer_code = (args.get("customer_code") or args.get("customer") or "").strip()
    if not raw_customer_code:
        return {
            "loi": (
                "Vui lòng cung cấp chính xác MÃ KHÁCH HÀNG ĐẦY ĐỦ để áp dụng chính sách chiết khấu và kiểm tra hạn mức công nợ "
                "(Ví dụ: DI-14998, DE-00123, PR-00045, RE-00012). "
                "Hệ thống yêu cầu mã đầy đủ để đảm bảo truy vết trong ERP."
            )
        }

    user_qty = args.get("quantity") or args.get("target_quantity") or 1
    try:
        quantity = int(user_qty)
        if quantity <= 0:
            quantity = 1
    except (ValueError, TypeError):
        quantity = 1

    custom_discount_raw = args.get("custom_discount_percent") or args.get("discount_percent")
    try:
        custom_discount_pct = float(custom_discount_raw) if custom_discount_raw is not None else None
    except (ValueError, TypeError):
        custom_discount_pct = None

    con = db()
    today = as_of(con).isoformat()

    # 1. Tìm sản phẩm CHÍNH XÁC theo mã SKU đầy đủ (Không dùng LIKE mơ hồ nhằm bảo đảm tính truy vết)
    clean_sku = raw_sku.strip()
    prod_row = con.execute("""
        SELECT p.id, p.sku, p.name, p.category, p.brand, p.unit, p.gross_price, p.cost_price, p.min_stock,
               coalesce(k.on_hand, 0) as on_hand, coalesce(k.available, 0) as available
        FROM dim_product p
        LEFT JOIN stock k ON k.product_id = p.id
        WHERE lower(p.sku) = lower(?)
        LIMIT 1
    """, [clean_sku]).fetchone()

    if not prod_row:
        return {
            "loi": (
                f"Không tìm thấy sản phẩm có mã SKU chính xác là '{clean_sku}'. "
                "Để đảm bảo tính toàn vẹn dữ liệu và truy vết chứng từ trong hệ thống ERP, "
                "vui lòng nhập đúng MÃ CODE SKU ĐẦY ĐỦ (Ví dụ: RT38CG6584B1SV-SAM, CFS-13VDM-COM, DAIKIN-FTKF25XVMV...)."
            )
        }

    prod = dict(prod_row)
    product_id = prod["id"]
    sku = prod["sku"]
    gross_price = float(prod["gross_price"] or 0.0)
    cost_price = float(prod["cost_price"] or 0.0)
    on_hand = int(prod["on_hand"])
    available = int(prod["available"])

    # 2. Tìm khách hàng CHÍNH XÁC theo mã khách hàng đầy đủ
    clean_cust_code = raw_customer_code.strip()
    cust_row = con.execute("""
        SELECT c.id, c.code, c.name, c.type, c.province, c.credit_limit, c.phone, c.status
        FROM dim_customer c
        WHERE lower(c.code) = lower(?)
        LIMIT 1
    """, [clean_cust_code]).fetchone()

    if not cust_row:
        return {
            "loi": (
                f"Không tìm thấy khách hàng có mã code chính xác là '{clean_cust_code}'. "
                "Để đảm bảo tính toàn vẹn dữ liệu và truy vết chứng từ trong hệ thống ERP, "
                "vui lòng nhập đúng MÃ KHÁCH HÀNG ĐẦY ĐỦ (Ví dụ: DI-14998, DE-00123, PR-00045, RE-00012...)."
            )
        }

    cust = dict(cust_row)
    customer_id = cust["id"]
    customer_code = cust["code"]
    customer_name = cust["name"]
    customer_type = cust["type"]
    credit_limit = float(cust["credit_limit"] or 0.0)

    # 3. Kiểm tra tình hình công nợ thực tế của khách hàng từ kho phân tích
    rec_row = con.execute("""
        SELECT coalesce(sum(outstanding), 0) as total_debt,
               coalesce(sum(case when due_date < ? and outstanding > 0 then outstanding else 0 end), 0) as overdue_debt
        FROM receivable
        WHERE customer_id = ? AND outstanding > 0
    """, [today, customer_id]).fetchone()

    total_debt = float(rec_row["total_debt"]) if rec_row else 0.0
    overdue_debt = float(rec_row["overdue_debt"]) if rec_row else 0.0
    remaining_credit = credit_limit - total_debt

    # 4. Lịch sử mua hàng của khách đối với mã này và tổng quan
    hist_order_row = con.execute("""
        SELECT o.order_number, o.date as last_order_date, ol.quantity as last_qty,
               ol.price as last_price, ol.discount as last_discount
        FROM fact_order o
        JOIN fact_order_line ol ON ol.order_id = o.id
        WHERE o.customer_id = ? AND ol.product_id = ?
        ORDER BY o.date DESC
        LIMIT 1
    """, [customer_id, product_id]).fetchone()

    hist_total_row = con.execute("""
        SELECT count(distinct o.id) as total_orders,
               coalesce(sum(o.total), 0) as lifetime_spend
        FROM fact_order o
        WHERE o.customer_id = ? AND o.status in ('COMPLETED', 'APPROVED', 'PROCESSING')
    """, [customer_id]).fetchone()
    lifetime_spend = float(hist_total_row["lifetime_spend"]) if hist_total_row else 0.0
    total_orders = int(hist_total_row["total_orders"]) if hist_total_row else 0

    # 5. Chính sách chiết khấu theo phân hạng khách hàng (Distributor: 18%, Dealer: 12%, Project: 10%, Retail: 0%)
    tier_base_discount = {
        "Distributor": 18.0,
        "Dealer": 12.0,
        "Project / Business": 10.0,
        "Retail": 0.0,
    }
    base_discount = tier_base_discount.get(customer_type, 0.0)

    # Thưởng số lượng (Volume Bonus)
    volume_bonus = 0.0
    if customer_type == "Distributor" and quantity >= 20:
        volume_bonus = 2.0
    elif customer_type == "Dealer" and quantity >= 10:
        volume_bonus = 2.0
    elif customer_type == "Project / Business" and quantity >= 15:
        volume_bonus = 2.0
    elif customer_type == "Retail" and quantity >= 5:
        volume_bonus = 2.0

    system_discount_pct = round(base_discount + volume_bonus, 1)

    if custom_discount_pct is not None:
        final_discount_pct = custom_discount_pct
        policy_applied = f"Chiết khấu thương lượng ({custom_discount_pct}%)"
    else:
        final_discount_pct = system_discount_pct
        policy_applied = f"Chính sách {customer_type} ({base_discount}%)" + (f" + Thưởng số lượng ({volume_bonus}%)" if volume_bonus > 0 else "")

    # Đơn giá bán, doanh thu và thuế VAT
    unit_price = round(gross_price * (1.0 - (final_discount_pct / 100.0)), 0)
    subtotal = round(unit_price * quantity, 0)
    vat_rate = 0.08  # Chuẩn VAT 8% ngành gia dụng, điện máy
    vat_amount = round(subtotal * vat_rate, 0)
    total_amount = subtotal + vat_amount

    # 6. Kiểm tra biên lợi nhuận gộp & sàn an toàn giá vốn
    unit_profit = unit_price - cost_price
    total_profit = unit_profit * quantity
    gross_margin_pct = round((unit_profit / unit_price * 100.0), 1) if unit_price > 0 else 0.0

    margin_status = "AN_TOAN"
    margin_note = f"Biên lãi gộp {gross_margin_pct}% (Đạt tiêu chuẩn an toàn)"
    if unit_price < cost_price:
        margin_status = "LO_VON"
        margin_note = f"NGUY HIỂM: Đơn giá bán ({money(unit_price)}) thấp hơn giá vốn ({money(cost_price)}). Bán lỗ {money(abs(unit_profit))}/sản phẩm!"
    elif gross_margin_pct < 5.0:
        margin_status = "CANH_BAO_BIEN_THAP"
        margin_note = f"CẢNH BÁO: Biên lợi nhuận gộp {gross_margin_pct}% thấp hơn ngưỡng sàn an toàn 5.0%. Cần Trưởng phòng kinh doanh duyệt ngoại lệ."

    # 7. Kiểm tra tình trạng tồn kho khả dụng
    is_stock_sufficient = available >= quantity
    stock_deficit = (quantity - available) if quantity > available else 0
    stock_note = "Đủ tồn kho khả dụng để xuất ngay" if is_stock_sufficient else f"Thiếu hụt {stock_deficit} {prod.get('unit', 'Bộ')} (Tồn khả dụng hiện tại: {available})"

    # 8. Kiểm tra thẩm định tín dụng & hạn mức công nợ
    post_order_debt = total_debt + total_amount
    is_credit_ok = (post_order_debt <= credit_limit) and (overdue_debt == 0)
    credit_warnings = []
    if overdue_debt > 0:
        credit_warnings.append(f"Khách hàng đang có {money(overdue_debt)} nợ QUÁ HẠN chưa thanh toán.")
    if post_order_debt > credit_limit:
        exceed_amt = post_order_debt - credit_limit
        credit_warnings.append(f"Đơn hàng mới ({money(total_amount)}) khiến tổng nợ ({money(post_order_debt)}) vượt hạn mức ({money(credit_limit)}) một khoản {money(exceed_amt)}.")
    credit_note = "; ".join(credit_warnings) if credit_warnings else f"Hạn mức tín dụng tốt (Dư mức nợ còn lại sau đơn: {money(credit_limit - post_order_debt)})"

    # 9. Quyền hạn khởi tạo Đơn bán hàng (Draft SO)
    can_create_so = actor.can("order.create")

    # 10. Đóng gói Action Schema cho Generative UI (Sales Action Card)
    action_schema = {
        "action_type": "ACTION_CARD_SALES_ORDER",
        "action_title": f"Tạo Báo Giá / Đơn Bán Hàng (Draft SO) - {customer_code}",
        "can_execute": can_create_so,
        "required_permission": "order.create",
        "customer_id": customer_id,
        "customer_code": customer_code,
        "customer_name": customer_name,
        "customer_type": customer_type,
        "product_id": product_id,
        "sku": sku,
        "product_name": prod["name"],
        "unit": prod.get("unit") or "Bộ",
        "quantity": quantity,
        "list_price": gross_price,
        "discount_percent": final_discount_pct,
        "unit_price": unit_price,
        "subtotal": subtotal,
        "vat_rate": vat_rate,
        "vat_amount": vat_amount,
        "total_amount": total_amount,
        "gross_margin_pct": gross_margin_pct,
        "is_stock_sufficient": is_stock_sufficient,
        "is_credit_ok": is_credit_ok,
        "margin_status": margin_status,
    }

    return {
        "ngay_du_lieu": today,
        "san_pham": {
            "id": product_id,
            "sku": sku,
            "ten_san_pham": prod["name"],
            "don_vi_tinh": prod.get("unit") or "Bộ",
            "gia_niem_yet": money(gross_price),
            "gia_von": money(cost_price),
            "ton_kho_thuc_te": on_hand,
            "ton_kha_dung": available,
            "tinh_trang_kho": stock_note,
            "so_luong_yeu_cau": quantity,
            "thieu_hut_kho": stock_deficit,
        },
        "khach_hang": {
            "id": customer_id,
            "ma_khach_hang": customer_code,
            "ten_khach_hang": customer_name,
            "phan_hang": customer_type,
            "han_muc_tin_dung": money(credit_limit),
            "tong_no_hien_tai": money(total_debt),
            "no_qua_han": money(overdue_debt),
            "han_muc_con_lai": money(remaining_credit),
            "danh_gia_cong_no": credit_note,
            "tong_doanh_thu_lich_su": money(lifetime_spend),
            "so_don_da_mua": total_orders,
        },
        "chinh_sach_gia_va_chiet_khau": {
            "chinh_sach_ap_dung": policy_applied,
            "ty_le_chiet_khau": f"{final_discount_pct}%",
            "don_gia_chot_chua_vat": money(unit_price),
            "thanh_tien_chua_vat": money(subtotal),
            "thue_vat_8_pct": money(vat_amount),
            "tong_thanh_toan": money(total_amount),
        },
        "danh_gia_hieu_qua_kinh_doanh": {
            "loi_nhuan_gop_moi_chiec": money(unit_profit),
            "tong_loi_nhuan_gop_don_hang": money(total_profit),
            "bien_loi_nhuan_gop_pct": f"{gross_margin_pct}%",
            "trang_thai_bien_lai": margin_status,
            "nhan_xet_gia_von": margin_note,
        },
        "lich_su_mua_ma_hang_nay": {
            "da_tung_mua": hist_order_row is not None,
            "ngay_mua_gan_nhat": hist_order_row["last_order_date"] if hist_order_row else "Chưa từng mua",
            "don_gia_lan_truoc": money(float(hist_order_row["last_price"])) if hist_order_row else "—",
            "chiet_khau_lan_truoc": f"{float(hist_order_row['last_discount'])}%" if hist_order_row else "—",
        },
        "action_schema": action_schema,
    }
'''

target_marker = "# ------------------------------------------------------------------- ML & Recommendations (Nhóm 1 & Nhóm 2)"
if target_marker in content:
    content = content.replace(target_marker, new_func + "\n\n" + target_marker)
else:
    print("Error: target_marker not found")
    sys.exit(1)

# Now update TOOLS list
old_tools = '''    # --- Nhóm 3: Actionable Copilot Tools
    Tool("procurement_fast_order",
         "Truy cứu lịch sử giá mua và phân tích nhu cầu nhập hàng theo MÃ CODE SKU ĐẦY ĐỦ của sản phẩm (và mã NCC đầy đủ nếu có) để lập đơn mua nhanh.",
         obj({"sku": {"type": "string", "description": "Bắt buộc: Mã SKU đầy đủ của sản phẩm cần đặt mua (ví dụ: RT38CG6584B1SV-SAM, DAIKIN-FTKF25XVMV)"},
              "supplier_code": {"type": "string", "description": "Tùy chọn: Mã Nhà Cung Cấp đầy đủ nếu muốn chỉ định đích danh (ví dụ: NCC-008)"},
              "quantity": {"type": "integer", "description": "Số lượng muốn đặt mua (nếu người dùng có nêu cụ thể)"}},
             ["sku"]),
         ("purchase.view",), procurement_fast_order),
]'''

new_tools = '''    # --- Nhóm 3: Actionable Copilot Tools
    Tool("procurement_fast_order",
         "Truy cứu lịch sử giá mua và phân tích nhu cầu nhập hàng theo MÃ CODE SKU ĐẦY ĐỦ của sản phẩm (và mã NCC đầy đủ nếu có) để lập đơn mua nhanh.",
         obj({"sku": {"type": "string", "description": "Bắt buộc: Mã SKU đầy đủ của sản phẩm cần đặt mua (ví dụ: RT38CG6584B1SV-SAM, DAIKIN-FTKF25XVMV)"},
              "supplier_code": {"type": "string", "description": "Tùy chọn: Mã Nhà Cung Cấp đầy đủ nếu muốn chỉ định đích danh (ví dụ: NCC-008)"},
              "quantity": {"type": "integer", "description": "Số lượng muốn đặt mua (nếu người dùng có nêu cụ thể)"}},
             ["sku"]),
         ("purchase.view",), procurement_fast_order),
    Tool("instant_quote_check",
         "Báo giá sỉ nhanh, tra cứu tồn khả dụng, kiểm tra chính sách chiết khấu theo phân hạng đối tác, kiểm tra hạn mức công nợ và sàn biên lợi nhuận gộp theo MÃ SKU ĐẦY ĐỦ và MÃ KHÁCH HÀNG ĐẦY ĐỦ.",
         obj({"sku": {"type": "string", "description": "Bắt buộc: Mã code SKU đầy đủ của sản phẩm cần báo giá (ví dụ: RT38CG6584B1SV-SAM, CFS-13VDM-COM)"},
              "customer_code": {"type": "string", "description": "Bắt buộc: Mã khách hàng đầy đủ để kiểm tra công nợ và áp chiết khấu phân hạng (ví dụ: DI-14998, DE-00123)"},
              "quantity": {"type": "integer", "description": "Số lượng đặt mua hoặc báo giá (mặc định 1)"},
              "custom_discount_percent": {"type": "number", "description": "Tùy chọn: Tỷ lệ chiết khấu thương lượng riêng (%) nếu muốn kiểm tra đàm phán giá"}},
             ["sku", "customer_code"]),
         ("order.view", "catalog.view"), instant_quote_check),
]'''

if old_tools in content:
    content = content.replace(old_tools, new_tools)
else:
    # Try normalizing newlines
    old_tools_crlf = old_tools.replace("\n", "\r\n")
    if old_tools_crlf in content:
        content = content.replace(old_tools_crlf, new_tools.replace("\n", "\r\n"))
    else:
        print("Error: old_tools not found")
        sys.exit(1)

# Now update TOOL_SCOPE
old_scope = '''TOOL_SCOPE = {
    "procurement_fast_order": "truy cứu nguồn hàng & đặt mua nhanh",'''

new_scope = '''TOOL_SCOPE = {
    "procurement_fast_order": "truy cứu nguồn hàng & đặt mua nhanh",
    "instant_quote_check": "báo giá sỉ & lập đơn bán hàng",'''

if old_scope in content:
    content = content.replace(old_scope, new_scope)
else:
    old_scope_crlf = old_scope.replace("\n", "\r\n")
    if old_scope_crlf in content:
        content = content.replace(old_scope_crlf, new_scope.replace("\n", "\r\n"))
    else:
        print("Error: old_scope not found")
        sys.exit(1)

with open(tools_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Successfully updated tools.py!")

