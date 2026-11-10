"""Đổi id theo khóa tự nhiên khi nạp dữ liệu mô phỏng vào MySQL đích.

Dữ liệu mô phỏng mang id của DB nguồn (SQLite dev hoặc bản snapshot): role, phòng ban, tài khoản kế toán, kho, user
mẫu... Trên MySQL đích các bảng danh mục này đã được seed với id KHÁC. Nạp nguyên id thì user bị gán sai role, bút toán
trỏ sai tài khoản. Module này ghép từng id nguồn sang id đích bằng khóa tự nhiên (tên role, mã tài khoản, email...)
rồi đổi mọi cột tham chiếu theo bảng REMAP_RULES. Không phụ thuộc DB nên kiểm thử được bằng dữ liệu giả.
"""
from __future__ import annotations

import re
import unicodedata
from collections import Counter
from collections.abc import Iterable, Sequence

# domain -> (service, bảng danh mục, cột khóa tự nhiên)
DOMAINS: dict[str, tuple[str, str, str]] = {
    "role": ("identity", "roles", "name"),
    "department": ("identity", "departments", "name"),
    "user": ("identity", "users", "email"),
    "account": ("finance", "chart_of_accounts", "code"),
    "cash_fund": ("finance", "cash_funds", "code"),
    "bank_account": ("finance", "bank_accounts", "code"),
    "warehouse": ("inventory", "warehouses", "code"),
}

# (service, bảng, cột) -> domain mà cột đó tham chiếu. Lập từ việc quét mọi bảng được nạp: thêm cột mới tham chiếu
# danh mục thì phải thêm vào đây, nếu không id sẽ không được đổi (lệnh --check có cảnh báo cột nghi vấn).
REMAP_RULES: dict[tuple[str, str, str], str] = {
    # identity
    ("identity", "users", "department_id"): "department",
    ("identity", "users", "line_manager_id"): "user",
    ("identity", "user_roles", "user_id"): "user",
    ("identity", "user_roles", "role_id"): "role",
    # catalog
    ("catalog", "product_price_histories", "changed_by"): "user",
    # sales
    ("sales", "quotations", "created_by_id"): "user",
    ("sales", "quotations", "created_by"): "user",
    ("sales", "quotations", "assigned_to"): "user",
    ("sales", "quotations", "approved_by"): "user",
    ("sales", "sales_orders", "created_by"): "user",
    ("sales", "sales_orders", "assigned_to"): "user",
    ("sales", "sales_orders", "approved_by"): "user",
    ("sales", "invoices", "created_by"): "user",
    ("sales", "invoices", "posted_by"): "user",
    ("sales", "invoices", "warehouse_posted_by"): "user",
    ("sales", "invoices", "cancelled_by_id"): "user",
    ("sales", "vat_invoices", "created_by"): "user",
    ("sales", "vat_invoice_adjustments", "created_by_id"): "user",
    ("sales", "vat_invoice_adjustments", "approved_by_id"): "user",
    ("sales", "sales_attachments", "uploaded_by"): "user",
    ("sales", "warehouse_dispatches", "packer_id"): "user",
    ("sales", "warehouse_dispatches", "shipper_id"): "user",
    ("sales", "sale_returns", "created_by_id"): "user",
    ("sales", "sale_returns", "technical_warehouse_id"): "warehouse",
    ("sales", "sale_returns", "sales_warehouse_id"): "warehouse",
    ("sales", "sale_return_requests", "technical_warehouse_id"): "warehouse",
    ("sales", "sale_return_requests", "sales_warehouse_id"): "warehouse",
    ("sales", "sale_return_requests", "created_by_id"): "user",
    ("sales", "sale_return_requests", "inspection_started_by_id"): "user",
    ("sales", "sale_return_requests", "inspected_by_id"): "user",
    ("sales", "sale_return_requests", "technical_received_by_id"): "user",
    ("sales", "sale_return_requests", "main_requested_by_id"): "user",
    ("sales", "sale_return_requests", "main_approved_by_id"): "user",
    ("sales", "sale_return_requests", "main_rejected_by_id"): "user",
    ("sales", "sale_return_requests", "posted_by_id"): "user",
    ("sales", "sale_report_items", "salesperson_id"): "user",
    ("sales", "revenue_report_items", "salesperson_id"): "user",
    ("sales", "warehouse_dispatches", "picker_id"): "user",
    ("sales", "warehouse_dispatches", "posted_by_id"): "user",
    ("sales", "warehouse_dispatches", "warehouse_id"): "warehouse",
    ("sales", "delivery_requests", "warehouse_id"): "warehouse",
    ("sales", "invoices", "inventory_warehouse_id"): "warehouse",
    # inventory
    ("inventory", "inventory_stocks", "warehouse_id"): "warehouse",
    ("inventory", "inventory_stocks_reservation", "warehouse_id"): "warehouse",
    ("inventory", "inventory_stocks_transaction", "warehouse_id"): "warehouse",
    ("inventory", "inventory_stocks_transaction", "created_by"): "user",
    ("inventory", "inventory_stocks_reservation_transaction", "warehouse_id"): "warehouse",
    ("inventory", "inventory_stocks_reservation_transaction", "created_by"): "user",
    # purchase
    ("purchase", "purchase_requisitions", "requested_by"): "user",
    ("purchase", "purchase_requisitions", "approved_by"): "user",
    ("purchase", "purchase_requisitions", "warehouse_id"): "warehouse",
    ("purchase", "purchase_orders", "created_by"): "user",
    ("purchase", "purchase_orders", "approved_by"): "user",
    ("purchase", "purchase_orders", "warehouse_id"): "warehouse",
    ("purchase", "goods_receipts", "received_by"): "user",
    ("purchase", "goods_receipts", "warehouse_id"): "warehouse",
    ("purchase", "purchase_returns", "created_by"): "user",
    ("purchase", "purchase_returns", "approved_by"): "user",
    ("purchase", "purchase_returns", "warehouse_id"): "warehouse",
    ("purchase", "supplier_inquiries", "created_by"): "user",
    ("purchase", "item_inquiry_requests", "requested_by"): "user",
    ("purchase", "item_inquiry_requests", "assignee_id"): "user",
    ("purchase", "item_inquiry_requests", "watcher_id"): "user",
    # finance
    ("finance", "finance_vouchers", "created_by_id"): "user",
    ("finance", "finance_vouchers", "posted_by_id"): "user",
    ("finance", "finance_vouchers", "cash_fund_id"): "cash_fund",
    ("finance", "finance_vouchers", "bank_account_id"): "bank_account",
    ("finance", "finance_vouchers", "destination_bank_account_id"): "bank_account",
    ("finance", "finance_voucher_lines", "debit_account_id"): "account",
    ("finance", "finance_voucher_lines", "credit_account_id"): "account",
    ("finance", "general_ledger_entries", "debit_account_id"): "account",
    ("finance", "general_ledger_entries", "credit_account_id"): "account",
    ("finance", "customer_receivables", "account_id"): "account",
}

# Bảng có UNIQUE trên cột khóa tự nhiên mà MySQL (collation utf8mb4_unicode_ci) so sánh KHÔNG phân biệt hoa thường, dấu
# thanh và khoảng trắng cuối, còn SQLite thì có. Các dòng trùng theo collation phải gộp thành một, nếu không MySQL báo
# "Duplicate entry". (service, bảng, cột khóa) -> các cột tham chiếu id của bảng đó cần trỏ lại: (service, bảng, cột).
COLLATION_DEDUPE: dict[tuple[str, str, str], list[tuple[str, str, str]]] = {
    ("catalog", "brands", "name"): [
        ("catalog", "products", "brand_id"),
        ("purchase", "supplier_inquiry_items", "brand_id"),
    ],
}

# Tên cột có dạng "người thực hiện / danh mục" mà chưa có trong REMAP_RULES sẽ bị cảnh báo khi --check.
SUSPECT_COLUMN = re.compile(
    r"(_by$|_by_id$|^created_by|salesperson|picker|approver|requester|user_id$|manager_id$|assignee|owner_id$"
    r"|warehouse_id$|account_id$|department_id$|role_id$|cash_fund_id$|bank_account_id$)",
    re.IGNORECASE,
)


# Cột có tên giống tham chiếu người dùng nhưng chứa chuỗi (tên đăng nhập, tên người), không phải id nên không đổi.
NOT_ID_COLUMNS: set[tuple[str, str, str]] = {
    ("catalog", "customers", "created_by"),  # tên đăng nhập của nhân viên tạo khách, ví dụ 'thang.dh17'
}


def collation_key(value: str) -> str:
    """Khóa so sánh xấp xỉ collation utf8mb4_unicode_ci: bỏ dấu, không phân biệt hoa thường, bỏ khoảng trắng cuối."""
    text = unicodedata.normalize("NFD", value.replace("Đ", "D").replace("đ", "d"))
    return "".join(ch for ch in text if unicodedata.category(ch) != "Mn").casefold().rstrip(" ")


def merge_collation_duplicates(
    columns: Sequence[str],
    rows: Sequence[Sequence],
    key_column: str,
    usage: Counter,
) -> tuple[list[tuple], dict[int, int], list[str]]:
    """Gộp các dòng có khóa trùng theo collation MySQL. Giữ dòng được tham chiếu nhiều nhất (hòa thì id nhỏ nhất).

    usage[id] = số dòng ở các bảng khác đang tham chiếu id đó. Trả về (các dòng giữ lại, {id bị gộp: id giữ lại},
    ghi chú để in ra)."""
    id_index, key_index = columns.index("id"), columns.index(key_column)
    groups: dict[str, list[Sequence]] = {}
    for row in rows:
        value = row[key_index]
        groups.setdefault(collation_key(value) if isinstance(value, str) else value, []).append(row)

    merged: dict[int, int] = {}
    notes: list[str] = []
    for group in groups.values():
        if len(group) < 2:
            continue
        keep = max(group, key=lambda row: (usage.get(row[id_index], 0), -row[id_index]))
        for row in group:
            if row is not keep:
                merged[row[id_index]] = keep[id_index]
                notes.append(
                    f"{key_column}: gộp id {row[id_index]} ({row[key_index]!r}, {usage.get(row[id_index], 0)} tham chiếu) "
                    f"vào id {keep[id_index]} ({keep[key_index]!r}, {usage.get(keep[id_index], 0)} tham chiếu)")
    kept = [tuple(row) for row in rows if row[id_index] not in merged]
    return kept, merged, notes


def rewrite_references(columns: Sequence[str], rows: Iterable[Sequence], column: str, id_map: dict[int, int]) -> list[tuple]:
    """Đổi giá trị của một cột tham chiếu theo id_map (id bị gộp -> id giữ lại)."""
    if column not in columns:
        return [tuple(row) for row in rows]
    index = columns.index(column)
    result = []
    for row in rows:
        values = list(row)
        if isinstance(values[index], int) and values[index] in id_map:
            values[index] = id_map[values[index]]
        result.append(tuple(values))
    return result


def build_id_maps(
    source: dict[str, dict[int, str]],
    target: dict[str, dict[str, int]],
    keep: dict[str, set[int]] | None = None,
) -> tuple[dict[str, dict[int, int]], dict[str, dict[int, str]]]:
    """Ghép id nguồn sang id đích theo khóa tự nhiên.

    source[domain] = {id_nguồn: khóa}; target[domain] = {khóa: id_đích}; keep[domain] = các id nguồn giữ nguyên
    (user mô phỏng chưa có ở đích, sẽ được nạp mới với đúng id đó).
    Trả về (maps, unmatched): maps[domain] = {id_nguồn: id_đích}; unmatched[domain] = {id_nguồn: khóa không có ở đích}.
    """
    keep = keep or {}
    maps: dict[str, dict[int, int]] = {}
    unmatched: dict[str, dict[int, str]] = {}
    for domain, rows in source.items():
        found = target.get(domain, {})
        kept = keep.get(domain, set())
        mapped: dict[int, int] = {}
        missing: dict[int, str] = {}
        for source_id, key in rows.items():
            if source_id in kept:
                mapped[source_id] = source_id
            elif key in found:
                mapped[source_id] = found[key]
            else:
                missing[source_id] = key
        maps[domain], unmatched[domain] = mapped, missing
    return maps, unmatched


def remap_rows(
    service: str,
    table: str,
    columns: Sequence[str],
    rows: Iterable[Sequence],
    maps: dict[str, dict[int, int]],
    unmatched: dict[str, dict[int, str]],
    problems: set[str],
) -> list[tuple]:
    """Đổi các cột tham chiếu danh mục theo maps. Giá trị không ghép được thì ghi vào problems (không ném lỗi ngay,
    để báo đủ mọi vấn đề một lượt)."""
    positions = [
        (index, REMAP_RULES[(service, table, column)])
        for index, column in enumerate(columns)
        if (service, table, column) in REMAP_RULES
    ]
    if not positions:
        return [tuple(row) for row in rows]

    result: list[tuple] = []
    for row in rows:
        values = list(row)
        for index, domain in positions:
            value = values[index]
            if value is None or not isinstance(value, int):
                continue  # NULL hoặc giá trị không phải id (ví dụ tên người) thì giữ nguyên
            target_id = maps.get(domain, {}).get(value)
            if target_id is None:
                key = unmatched.get(domain, {}).get(value, "không có trong nguồn")
                problems.add(f"{service}.{table}.{columns[index]}: {domain} id {value} ({key}) không có trên MySQL đích")
            else:
                values[index] = target_id
        result.append(tuple(values))
    return result


def apply_user_aliases(target_users: dict[str, int], aliases: dict[str, str]) -> list[str]:
    """Ghép email user ở nguồn với email khác ở đích (user thật đã đổi email, ví dụ admin@erp.local đổi thành email
    cá nhân). aliases = {email nguồn: email đích}. Sửa target_users tại chỗ, trả về danh sách lỗi."""
    errors = []
    for source_email, target_email in aliases.items():
        if target_email not in target_users:
            errors.append(f"--map-user: không có user {target_email} trên MySQL đích")
        else:
            target_users[source_email] = target_users[target_email]
    return errors


def user_id_conflicts(
    new_users: dict[int, str],
    target_users: dict[int, str],
    is_replaceable,
) -> list[str]:
    """User mô phỏng giữ nguyên id: báo lỗi nếu id đó đã thuộc một user khác ở đích (user mô phỏng cũ sẽ bị xóa
    trước khi nạp nên không tính là xung đột; is_replaceable(email) cho biết email đó có phải user mô phỏng không)."""
    problems = []
    for user_id, email in sorted(new_users.items()):
        owner = target_users.get(user_id)
        if owner is not None and owner != email and not is_replaceable(owner):
            problems.append(f"id user {user_id} ({email}) đã thuộc về {owner} trên MySQL đích")
    return problems


def suspect_columns(tables: dict[tuple[str, str], Sequence[str]]) -> list[str]:
    """Cột trông như tham chiếu danh mục/người dùng nhưng chưa có trong REMAP_RULES (có thể bị bỏ sót khi đổi id)."""
    found = []
    for (service, table), columns in sorted(tables.items()):
        for column in columns:
            if column == "id" or column.endswith("_name") or (service, table, column) in REMAP_RULES:
                continue
            if (service, table, column) in NOT_ID_COLUMNS:
                continue
            if SUSPECT_COLUMN.search(column):
                found.append(f"{service}.{table}.{column}")
    return found
