#!/usr/bin/env python3
"""Sinh tài liệu API tổng hợp (OpenAPI 3.0) cho 4 service: inventory, purchase, chat, ai.

Nguồn: route và tên trường JSON đọc từ source code của từng service (thời điểm viết file này).
Chỉ mô tả API công khai qua gateway (:8000). Endpoint `/internal/*` không đưa vào.

Đầu ra (cùng thư mục với script):
  - openapi.json : đặc tả OpenAPI 3.0 để import vào công cụ khác (Postman, Insomnia...)
  - index.html   : Swagger UI, nhúng sẵn đặc tả. Mở trực tiếp bằng trình duyệt, không cần server.

Chạy:  python build_openapi.py

Khi thêm hoặc đổi route trong service, sửa bảng OPERATIONS bên dưới rồi chạy lại.
"""
from __future__ import annotations

import json
import re
from pathlib import Path

OUT_DIR = Path(__file__).resolve().parent
GATEWAY_URL = "http://localhost:8000"

SERVICES = {
    "inventory": ("Kho (Inventory)", "Tồn kho thực tế và khả dụng, giữ hàng nguyên tử cho chứng từ bán, sổ cái bất biến. Service .NET 10, port 8004."),
    "purchase": ("Mua hàng (Purchase)", "Yêu cầu mua, đơn mua, nhận hàng (GRN), hóa đơn NCC với đối chiếu 3 bên, trả hàng NCC, yêu cầu báo giá. Service .NET 10, port 8003."),
    "chat": ("Chat nội bộ", "Hội thoại trực tiếp, nhóm và luồng công việc gắn chứng từ. Lưu MongoDB, realtime qua SignalR. Service .NET 10, port 8009."),
    "ai": ("Trợ lý AI và BI", "Trợ lý AI chỉ đọc gọi tool SQL theo quyền người hỏi; dự báo, phân khúc RFM, rủi ro công nợ. Service FastAPI, port 8010."),
}

# ---------------------------------------------------------------------------
# Schema request body (tên trường trùng JSON trên wire, snake_case)
# ---------------------------------------------------------------------------
def _i(desc: str = "", minimum: int | None = None) -> dict:
    s = {"type": "integer", "format": "int64", "description": desc}
    if minimum is not None:
        s["minimum"] = minimum
    return s


def _s(desc: str = "") -> dict:
    return {"type": "string", "description": desc}


def _b(desc: str = "") -> dict:
    return {"type": "boolean", "description": desc}


def _obj(props: dict, required: list[str] | None = None, desc: str = "") -> dict:
    s = {"type": "object", "properties": props, "description": desc}
    if required:
        s["required"] = required
    return s


def _arr(item: dict | str, desc: str = "") -> dict:
    return {"type": "array", "items": {"$ref": f"#/components/schemas/{item}"} if isinstance(item, str) else item, "description": desc}


def _ref(name: str) -> dict:
    return {"$ref": f"#/components/schemas/{name}"}


SCHEMAS: dict[str, dict] = {
    # --- inventory: nguồn Application/DTOs/StockRequestDtos.cs ---
    "ChangeStockRequest": _obj({
        "warehouse_id": _i("Kho (> 0)", 1), "product_id": _i("Sản phẩm (> 0)", 1),
        "quantity_delta": {"type": "integer", "description": "Biến động có dấu (+ nhập, - xuất)"},
        "document_type": _s("Loại chứng từ"), "document_id": _s("Số chứng từ"),
        "notes": _s(), "created_by": _i(), "origin": _s(),
    }, ["warehouse_id", "product_id", "quantity_delta", "document_type", "document_id"]),
    "ReserveStockRequest": _obj({
        "order_id": _s("Số chứng từ bán, ví dụ INV-{mã khách}-{ddmmyy}"),
        "warehouse_id": _i(minimum=1), "product_id": _i(minimum=1),
        "quantity": {"type": "integer", "minimum": 1}, "expires_at": {"type": "string", "format": "date-time"},
    }, ["order_id", "warehouse_id", "product_id", "quantity"]),
    "BatchReserveItem": _obj({
        "warehouse_id": _i(minimum=1), "product_id": _i(minimum=1), "quantity": {"type": "integer", "minimum": 1},
    }, ["warehouse_id", "product_id", "quantity"]),
    "BatchReserveStockRequest": _obj({
        "order_id": _s("Số chứng từ bán. Idempotent theo (số chứng từ, kho, sản phẩm)"),
        "items": _arr("BatchReserveItem", "Ít nhất 1 dòng. Thiếu một dòng thì rollback cả lô"),
        "expires_at": {"type": "string", "format": "date-time"},
    }, ["order_id", "items"]),
    "ReleaseStockRequest": _obj({
        "order_id": _s(), "warehouse_id": _i(minimum=1), "product_id": _i(minimum=1),
        "quantity": {"type": "integer", "minimum": 1},
    }, ["order_id", "warehouse_id", "product_id", "quantity"]),
    "ReleaseDocumentRequest": _obj({"order_id": _s("Nhả toàn bộ hàng giữ của chứng từ")}, ["order_id"]),
    "ReceiveStockRequest": _obj({
        "reference_doc": _s("Mã chứng từ nhập"), "warehouse_id": _i(minimum=1), "product_id": _i(minimum=1),
        "quantity": {"type": "integer", "minimum": 1},
    }, ["reference_doc", "warehouse_id", "product_id", "quantity"]),
    "IssueStockRequest": _obj({
        "reference_doc": _s("Mã chứng từ xuất"), "warehouse_id": _i(minimum=1), "product_id": _i(minimum=1),
        "quantity": {"type": "integer", "minimum": 1}, "order_id": _s("Số chứng từ bán (nếu có)"),
    }, ["reference_doc", "warehouse_id", "product_id", "quantity"]),
    "WarehouseRequest": _obj({
        "code": _s("Mã kho"), "name": _s("Tên kho"), "location": _s("Địa chỉ"),
        "can_sell": _b("Kho được dùng để bán hàng"), "is_active": _b("Đang hoạt động"),
    }, ["name"]),
    "SalesReturnLine": _obj({"product_id": _i(minimum=1), "quantity": {"type": "integer", "minimum": 1}}, ["product_id", "quantity"]),
    "SalesReturnReceiveRequest": _obj({
        "reference_doc": _s(), "warehouse_id": _i(minimum=1), "items": _arr("SalesReturnLine"),
    }, ["reference_doc", "warehouse_id", "items"]),
    "SalesReturnTransferRequest": _obj({
        "reference_doc": _s(), "source_warehouse_id": _i(minimum=1), "destination_warehouse_id": _i(minimum=1),
        "items": _arr("SalesReturnLine"),
    }, ["reference_doc", "source_warehouse_id", "destination_warehouse_id", "items"]),

    # --- purchase: nguồn Application/DTOs ---
    "CreatePurchaseOrderItem": _obj({
        "product_id": _i(minimum=1), "sku": _s(), "product_name": _s(), "unit": _s("Đơn vị, mặc định 'Cái'"),
        "origin": _s("Xuất xứ"), "unit_cost": {"type": "number", "description": "Đơn giá"},
        "quantity_ordered": {"type": "integer", "minimum": 0}, "tax_rate": {"type": "number", "description": "Thuế %, mặc định 10"},
        "discount_percent": {"type": "number", "description": "Chiết khấu %, mặc định 0"}, "notes": _s(),
        "allocations": _arr(_obj({"pr_id": _i(), "pr_item_id": _i(), "quantity": {"type": "integer"}}), "Gom nhu cầu từ PR đã duyệt"),
    }, ["product_id", "sku", "product_name", "unit_cost", "quantity_ordered"]),
    "CreatePurchaseOrder": _obj({
        "supplier_id": _i(minimum=1), "warehouse_id": _i(minimum=1), "order_date": {"type": "string", "format": "date-time"},
        "expected_delivery_date": {"type": "string", "format": "date-time"}, "currency": _s("Mặc định VND"),
        "payment_terms": _s(), "notes": _s(), "items": _arr("CreatePurchaseOrderItem"),
    }, ["supplier_id", "warehouse_id", "items"]),
    "CreateGoodsReceiptItem": _obj({
        "purchase_order_item_id": _i(minimum=1), "product_id": _i(minimum=1), "sku": _s(), "product_name": _s(),
        "unit": _s(), "quantity_received": {"type": "integer", "minimum": 0}, "quantity_rejected": {"type": "integer", "minimum": 0},
        "unit_cost": {"type": "number"}, "batch_number": _s("Số lô"), "expiry_date": {"type": "string", "format": "date-time"},
        "notes": _s(),
    }, ["purchase_order_item_id", "product_id", "quantity_received"]),
    "CreateGoodsReceipt": _obj({
        "purchase_order_id": _i(minimum=1), "warehouse_id": _i(minimum=1), "received_by": _i(minimum=1),
        "received_at": {"type": "string", "format": "date-time"}, "delivery_note_number": _s("Số phiếu giao của NCC"),
        "notes": _s(), "items": _arr("CreateGoodsReceiptItem"),
    }, ["purchase_order_id", "warehouse_id", "items"]),
    "GenericPurchaseDto": _obj({}, desc="Cấu trúc đầy đủ xem DTO tương ứng trong purchase-service/src/Purchase.Application/DTOs."),

    # --- chat: Api/Controllers (record Input) ---
    "DirectConversationInput": _obj({"user_id": _i(minimum=1)}, ["user_id"]),
    "WorkConversationInput": _obj({
        "subject_type": _s("Loại chứng từ, ví dụ purchase_order"), "subject_id": _i(minimum=1),
        "subject_code": _s("Số chứng từ hiển thị"), "subject_title": _s("Tiêu đề hiển thị"),
    }, ["subject_type", "subject_id"]),
    "GroupConversationInput": _obj({"title": _s(), "user_ids": {"type": "array", "items": _i()}}),
    "TitleInput": _obj({"title": _s()}),
    "UserIdsInput": _obj({"user_ids": {"type": "array", "items": _i()}}),
    "LeaveInput": _obj({"new_owner_id": _i("Bắt buộc nếu người rời là owner")}),
    "RoleInput": _obj({"role": {"type": "string", "enum": ["owner", "deputy", "member"]}}, ["role"]),
    "ReadInput": _obj({"message_id": _i(minimum=1)}, ["message_id"]),
    "MessageContentInput": _obj({"content": _s()}, ["content"]),
    "EmojiInput": _obj({"emoji": _s("Ký tự emoji")}, ["emoji"]),
    "ForwardInput": _obj({"conversation_ids": {"type": "array", "items": _i()}}, ["conversation_ids"]),
    "AliasInput": _obj({"alias": _s("Biệt danh riêng của người gọi cho người khác")}),

    # --- ai ---
    "AiChatRequest": _obj({
        "message": {"type": "string", "minLength": 1, "maxLength": 2000},
        "conversation_id": _i("Bỏ trống để mở hội thoại mới"),
    }, ["message"]),
}

# ---------------------------------------------------------------------------
# Bảng endpoint. Mỗi dòng: (service, method, path gateway, summary, options)
# options: perm (quyền), body (tên schema), query [(tên, kiểu, mô tả)], form {tên: "file"|"array"|"string"},
#          public (không cần JWT), desc (mô tả thêm)
# ---------------------------------------------------------------------------
OPERATIONS: list[tuple] = []


def _default_perm(svc: str, method: str, path: str) -> str | None:
    """Quyền mặc định theo RequirePermission trong source. Nhiều quyền cách nhau bởi ' | ' (cần một trong số đó)."""
    if svc == "chat":
        return "chat.use"
    if svc != "inventory":
        return None
    m = method.upper()
    if m == "GET":
        return "inventory.view"
    if path.endswith("/sales-returns/receive"):
        return "inventory.receive | sale_return.technical"
    if path.endswith("/sales-returns/transfer"):
        return "inventory.transfer | sale_return.post"
    if path.endswith("/stocks/receive"):
        return "inventory.receive"
    if path.endswith("/stocks/issue"):
        return "inventory.issue | invoice.manage"
    if path.endswith("/stocks/reserve") or path.endswith("/stocks/reserve/batch")             or path.endswith("/stocks/release") or path.endswith("/stocks/release/document"):
        return "inventory.manage | sale-invoice.create | invoice.manage"
    return "inventory.manage"


def op(svc: str, method: str, path: str, summary: str, **opts) -> None:
    if not opts.get("public") and "perm" not in opts:
        opts["perm"] = _default_perm(svc, method, path)
    OPERATIONS.append((svc, method.lower(), path, summary, opts))


P_VIEW, P_CREATE, P_APPROVE = "purchase.view", "purchase.create", "purchase.approve"
PAGE = [("page", "integer", "Trang (từ 1)"), ("pageSize", "integer", "Số dòng mỗi trang")]

# ---- inventory ----
op("inventory", "get", "/api/v1/inventory/health", "Kiểm tra trạng thái service", public=True)
op("inventory", "get", "/api/v1/inventory/stocks", "Danh sách tồn kho theo kho và sản phẩm",
   query=[("productId", "integer", "Lọc theo sản phẩm"), ("warehouseId", "integer", "Lọc theo kho"),
          ("lowStock", "boolean", "Chỉ hàng sắp hết")] + PAGE)
op("inventory", "get", "/api/v1/inventory/stocks/{productId}", "Tồn kho của một sản phẩm ở các kho")
op("inventory", "get", "/api/v1/inventory/stocks/movements", "Sổ cái biến động tồn kho thực tế",
   query=[("productId", "integer", ""), ("warehouseId", "integer", ""), ("movementType", "string", "Loại biến động"),
          ("page", "integer", ""), ("pageSize", "integer", "")])
op("inventory", "get", "/api/v1/inventory/stocks/transactions", "Alias của /stocks/movements",
   query=[("productId", "integer", ""), ("warehouseId", "integer", ""), ("movementType", "string", ""),
          ("page", "integer", ""), ("pageSize", "integer", "")])
op("inventory", "get", "/api/v1/inventory/stocks/reservations/orders", "Hàng đang giữ theo chứng từ bán của một sản phẩm",
   query=[("productId", "integer", "Bắt buộc"), ("warehouseId", "integer", ""),
          ("reservedFrom", "string", "Từ ngày (ISO 8601 UTC)"), ("reservedTo", "string", "Đến ngày (ISO 8601 UTC)")])
op("inventory", "get", "/api/v1/inventory/stocks/reservations/documents", "Chứng từ đang giữ hàng chưa ghi sổ xuất")
op("inventory", "post", "/api/v1/inventory/stocks/change", "Điều chỉnh tồn thực tế (có dấu)",
   perm=None, body="ChangeStockRequest", conflict=True)
op("inventory", "post", "/api/v1/inventory/stocks/reserve", "Giữ hàng cho một dòng chứng từ",
   body="ReserveStockRequest", conflict=True)
op("inventory", "post", "/api/v1/inventory/stocks/reserve/batch", "Giữ hàng cho cả chứng từ (một transaction)",
   body="BatchReserveStockRequest", conflict=True,
   desc="Thiếu hàng ở bất kỳ dòng nào → 409 INSUFFICIENT_STOCK, rollback toàn bộ. Cùng số chứng từ khác số lượng → 422 DOCUMENT_ALREADY_POSTED.")
op("inventory", "post", "/api/v1/inventory/stocks/release", "Nhả một phần hàng đã giữ", body="ReleaseStockRequest")
op("inventory", "post", "/api/v1/inventory/stocks/release/document", "Nhả toàn bộ hàng giữ của chứng từ (hủy chứng từ)",
   body="ReleaseDocumentRequest")
op("inventory", "post", "/api/v1/inventory/stocks/receive", "Nhập kho (tăng tồn thực tế và khả dụng)", body="ReceiveStockRequest")
op("inventory", "post", "/api/v1/inventory/stocks/issue", "Ghi sổ xuất kho (giảm tồn thực tế)", body="IssueStockRequest", conflict=True)
op("inventory", "get", "/api/v1/inventory/reservations", "Danh sách số dư khả dụng",
   query=[("productId", "integer", ""), ("warehouseId", "integer", ""), ("page", "integer", ""), ("pageSize", "integer", "")])
op("inventory", "post", "/api/v1/inventory/reservations/change", "Điều chỉnh tồn khả dụng (có dấu)",
   body="ChangeStockRequest", conflict=True)
op("inventory", "get", "/api/v1/inventory/reservations/transactions", "Sổ cái tồn khả dụng",
   query=[("productId", "integer", ""), ("warehouseId", "integer", ""), ("documentType", "string", ""),
          ("documentId", "string", ""), ("page", "integer", ""), ("pageSize", "integer", "")])
op("inventory", "get", "/api/v1/inventory/warehouses", "Danh sách kho")
op("inventory", "post", "/api/v1/inventory/warehouses", "Tạo kho", body="WarehouseRequest")
op("inventory", "put", "/api/v1/inventory/warehouses/{id}", "Cập nhật kho", body="WarehouseRequest")
op("inventory", "delete", "/api/v1/inventory/warehouses/{id}", "Xóa kho (soft-delete)")
op("inventory", "post", "/api/v1/inventory/sales-returns/receive", "Nhận hàng trả từ khách", body="SalesReturnReceiveRequest")
op("inventory", "post", "/api/v1/inventory/sales-returns/transfer", "Chuyển hàng trả giữa hai kho", body="SalesReturnTransferRequest")

# ---- purchase ----
op("purchase", "get", "/api/v1/purchase/health", "Kiểm tra trạng thái service", public=True)
op("purchase", "get", "/api/v1/purchase/requisitions", "Danh sách yêu cầu mua (PR)", perm=P_VIEW,
   query=[("warehouseId", "integer", ""), ("approvalStatus", "string", ""), ("orderStatus", "string", "")])
op("purchase", "get", "/api/v1/purchase/requisitions/available-for-order", "PR đã duyệt còn hàng để lập PO", perm=P_VIEW)
op("purchase", "get", "/api/v1/purchase/requisitions/{id}", "Chi tiết PR", perm=P_VIEW)
op("purchase", "post", "/api/v1/purchase/requisitions", "Tạo PR", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "post", "/api/v1/purchase/requisitions/{id}/submit", "Gửi PR duyệt", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/requisitions/{id}/approve", "Duyệt PR", perm=P_APPROVE, body="GenericPurchaseDto")
op("purchase", "post", "/api/v1/purchase/requisitions/{id}/reject", "Từ chối PR", perm=P_APPROVE, body="GenericPurchaseDto")
op("purchase", "get", "/api/v1/purchase/orders", "Danh sách đơn mua (PO)", perm=P_VIEW,
   query=[("supplierId", "integer", ""), ("warehouseId", "integer", ""), ("status", "string", "")])
op("purchase", "get", "/api/v1/purchase/orders/{id}", "Chi tiết PO", perm=P_VIEW)
op("purchase", "post", "/api/v1/purchase/orders", "Tạo PO", perm=P_CREATE, body="CreatePurchaseOrder",
   desc="Người tạo lấy từ JWT, không nhận từ body.")
op("purchase", "post", "/api/v1/purchase/orders/{id}/submit", "Gửi PO duyệt", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/orders/{id}/approve", "Duyệt PO", perm=P_APPROVE)
op("purchase", "post", "/api/v1/purchase/orders/{id}/cancel", "Hủy PO", perm=P_APPROVE, body="GenericPurchaseDto")
op("purchase", "post", "/api/v1/purchase/orders/{id}/close", "Đóng PO", perm=P_APPROVE)
op("purchase", "get", "/api/v1/purchase/receipts", "Danh sách phiếu nhận hàng (GRN)", perm=P_VIEW,
   query=[("purchaseOrderId", "integer", ""), ("warehouseId", "integer", ""), ("status", "string", "")])
op("purchase", "get", "/api/v1/purchase/receipts/{id}", "Chi tiết GRN", perm=P_VIEW)
op("purchase", "post", "/api/v1/purchase/receipts", "Tạo GRN", perm=P_CREATE, body="CreateGoodsReceipt")
op("purchase", "post", "/api/v1/purchase/receipts/{id}/confirm", "Xác nhận nhập kho (phát GoodsReceived)", perm=P_CREATE)
op("purchase", "get", "/api/v1/purchase/invoices", "Danh sách hóa đơn NCC", perm=P_VIEW,
   query=[("purchaseOrderId", "integer", ""), ("supplierId", "integer", ""), ("status", "string", "")])
op("purchase", "get", "/api/v1/purchase/invoices/{id}", "Chi tiết hóa đơn", perm=P_VIEW)
op("purchase", "post", "/api/v1/purchase/invoices", "Tạo hóa đơn NCC", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "post", "/api/v1/purchase/invoices/{id}/match", "Đối chiếu 3 bên PO – GRN – hóa đơn", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/invoices/{id}/post", "Ghi sổ hóa đơn", perm=P_APPROVE)
op("purchase", "get", "/api/v1/purchase/returns", "Danh sách phiếu trả hàng NCC", perm=P_VIEW,
   query=[("supplierId", "integer", ""), ("status", "string", "")])
op("purchase", "get", "/api/v1/purchase/returns/{id}", "Chi tiết phiếu trả", perm=P_VIEW)
op("purchase", "post", "/api/v1/purchase/returns", "Tạo phiếu trả hàng NCC", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "put", "/api/v1/purchase/returns/{id}", "Cập nhật phiếu trả", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "post", "/api/v1/purchase/returns/{id}/submit", "Gửi phiếu trả duyệt", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/returns/{id}/approve", "Duyệt phiếu trả", perm=P_APPROVE)
op("purchase", "post", "/api/v1/purchase/returns/{id}/dispatch", "Xác nhận xuất trả hàng", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/returns/{id}/cancel", "Hủy phiếu trả", perm=P_APPROVE)
op("purchase", "get", "/api/v1/purchase/inquiries/requests", "Danh sách yêu cầu báo giá nội bộ", perm=P_VIEW)
op("purchase", "post", "/api/v1/purchase/inquiries/requests", "Tạo yêu cầu báo giá", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "get", "/api/v1/purchase/inquiries/requests/{id}", "Chi tiết yêu cầu báo giá", perm=P_VIEW)
op("purchase", "put", "/api/v1/purchase/inquiries/requests/{id}", "Cập nhật yêu cầu báo giá", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "post", "/api/v1/purchase/inquiries/requests/{id}/submit", "Gửi yêu cầu báo giá", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/inquiries/requests/{id}/close", "Đóng yêu cầu báo giá", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/inquiries/requests/{id}/attachments", "Tải tệp đính kèm", perm=P_CREATE, form={"file": "file"})
op("purchase", "get", "/api/v1/purchase/inquiries/requests/{id}/attachments/{attachmentId}", "Tải về tệp đính kèm", perm=P_VIEW)
op("purchase", "delete", "/api/v1/purchase/inquiries/requests/{id}/attachments/{attachmentId}", "Xóa tệp đính kèm", perm=P_CREATE)
op("purchase", "get", "/api/v1/purchase/inquiries/requests/{id}/supplier-inquiries", "Báo giá NCC theo yêu cầu", perm=P_VIEW)
op("purchase", "post", "/api/v1/purchase/inquiries/requests/{id}/supplier-inquiries", "Tạo báo giá gửi NCC", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "post", "/api/v1/purchase/inquiries/requests/{id}/items/{itemId}/select-result", "Chọn NCC cho một dòng", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "get", "/api/v1/purchase/inquiries/supplier-inquiries", "Danh sách báo giá NCC", perm=P_VIEW)
op("purchase", "get", "/api/v1/purchase/inquiries/supplier-inquiries/{id}", "Chi tiết báo giá NCC", perm=P_VIEW)
op("purchase", "put", "/api/v1/purchase/inquiries/supplier-inquiries/{id}", "Cập nhật báo giá NCC", perm=P_CREATE, body="GenericPurchaseDto")
op("purchase", "post", "/api/v1/purchase/inquiries/supplier-inquiries/{id}/send", "Gửi báo giá cho NCC", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/inquiries/supplier-inquiries/{id}/sync-email", "Đồng bộ trạng thái email phản hồi", perm=P_CREATE)
op("purchase", "post", "/api/v1/purchase/inquiries/supplier-inquiries/{id}/complete", "Hoàn tất báo giá NCC", perm=P_CREATE)

# ---- chat ----
op("chat", "get", "/api/v1/chat/health", "Kiểm tra trạng thái service", public=True)
op("chat", "get", "/api/v1/chat/conversations", "Danh sách hội thoại của người dùng",
   query=[("page", "integer", ""), ("per_page", "integer", "Mặc định 30"), ("search", "string", ""),
          ("filter", "string", "")])
op("chat", "get", "/api/v1/chat/conversations/{id}", "Chi tiết hội thoại")
op("chat", "get", "/api/v1/chat/conversations/unread-count", "Tổng số tin chưa đọc")
op("chat", "post", "/api/v1/chat/conversations/direct", "Mở hội thoại trực tiếp", body="DirectConversationInput")
op("chat", "post", "/api/v1/chat/conversations/work", "Mở luồng công việc gắn chứng từ", body="WorkConversationInput")
op("chat", "post", "/api/v1/chat/conversations/group", "Tạo nhóm chat", body="GroupConversationInput")
op("chat", "post", "/api/v1/chat/conversations/read-all", "Đánh dấu đã đọc toàn bộ")
op("chat", "post", "/api/v1/chat/conversations/{id}/read", "Đánh dấu đã đọc đến một tin", body="ReadInput")
op("chat", "post", "/api/v1/chat/conversations/{id}/pin", "Ghim hội thoại (chỉ với người gọi)")
op("chat", "delete", "/api/v1/chat/conversations/{id}/pin", "Bỏ ghim hội thoại")
op("chat", "post", "/api/v1/chat/conversations/{id}/mute", "Tắt thông báo hội thoại")
op("chat", "delete", "/api/v1/chat/conversations/{id}/mute", "Bật lại thông báo hội thoại")
op("chat", "patch", "/api/v1/chat/conversations/{id}", "Đổi tên nhóm", body="TitleInput")
op("chat", "post", "/api/v1/chat/conversations/{id}/avatar", "Đặt ảnh nhóm", form={"avatar": "file"})
op("chat", "get", "/api/v1/chat/conversations/{id}/avatar", "Lấy ảnh nhóm")
op("chat", "delete", "/api/v1/chat/conversations/{id}/avatar", "Xóa ảnh nhóm")
op("chat", "post", "/api/v1/chat/conversations/{id}/members", "Thêm thành viên nhóm", body="UserIdsInput")
op("chat", "delete", "/api/v1/chat/conversations/{id}/members/{userId}", "Xóa thành viên khỏi nhóm")
op("chat", "put", "/api/v1/chat/conversations/{id}/members/{userId}/role", "Đổi vai trò thành viên", body="RoleInput")
op("chat", "post", "/api/v1/chat/conversations/{id}/transfer-owner/{userId}", "Chuyển quyền sở hữu nhóm")
op("chat", "post", "/api/v1/chat/conversations/{id}/leave", "Rời nhóm", body="LeaveInput")
op("chat", "delete", "/api/v1/chat/conversations/{id}", "Giải tán nhóm (chỉ owner)")
op("chat", "get", "/api/v1/chat/conversations/{id}/messages", "Tin nhắn của hội thoại (phân trang theo ID)",
   query=[("before_id", "integer", "Lấy tin cũ hơn ID này"), ("after_id", "integer", "Lấy tin mới hơn ID này"),
          ("limit", "integer", "Mặc định 50")])
op("chat", "post", "/api/v1/chat/conversations/{id}/messages", "Gửi tin nhắn (có thể kèm tệp)",
   form={"content": "string", "reply_to_id": "string", "mentions[]": "array", "mention_all": "string",
         "attachments[]": "array"},
   desc="Giới hạn: tối đa 10 tệp mỗi tin, mỗi tệp tối đa 20 MB. Bị giới hạn tần suất (policy `send`).")
op("chat", "get", "/api/v1/chat/conversations/{id}/pins", "Tin nhắn đang ghim trong hội thoại")
op("chat", "get", "/api/v1/chat/conversations/{id}/messages/search", "Tìm tin nhắn trong hội thoại",
   query=[("q", "string", "Từ khóa")])
op("chat", "patch", "/api/v1/chat/messages/{id}", "Sửa nội dung tin nhắn", body="MessageContentInput")
op("chat", "post", "/api/v1/chat/messages/{id}/recall", "Thu hồi tin nhắn")
op("chat", "post", "/api/v1/chat/messages/{id}/hide", "Ẩn tin nhắn với người gọi")
op("chat", "post", "/api/v1/chat/messages/{id}/reactions", "Thả cảm xúc", body="EmojiInput")
op("chat", "post", "/api/v1/chat/messages/{id}/pin", "Ghim tin nhắn")
op("chat", "delete", "/api/v1/chat/messages/{id}/pin", "Bỏ ghim tin nhắn")
op("chat", "post", "/api/v1/chat/messages/{id}/forward", "Chuyển tiếp tin nhắn", body="ForwardInput")
op("chat", "get", "/api/v1/chat/attachments/{id}", "Tải tệp đính kèm")
op("chat", "get", "/api/v1/chat/users/search", "Tìm người dùng", query=[("q", "string", ""), ("limit", "integer", "Mặc định 20")])
op("chat", "get", "/api/v1/chat/users/{id}", "Thông tin người dùng")
op("chat", "get", "/api/v1/chat/users/online", "Danh sách người dùng đang trực tuyến")
op("chat", "put", "/api/v1/chat/users/{id}/alias", "Đặt biệt danh cho người khác", body="AliasInput")
op("chat", "get", "/api/v1/chat/contacts", "Danh sách liên hệ")
op("chat", "get", "/api/v1/chat/preferences", "Tùy chọn cá nhân (biệt danh)")

# ---- ai ----
op("ai", "get", "/api/v1/ai/health", "Trạng thái service, model LLM và kho phân tích", public=True)
op("ai", "post", "/api/v1/ai/chat", "Hỏi trợ lý AI", body="AiChatRequest",
   desc="Trợ lý chỉ đọc. Chỉ dùng tool mà người hỏi có quyền. Trả về `tools_used` để kiểm tra nguồn số liệu.")
op("ai", "get", "/api/v1/ai/conversations", "Lịch sử hội thoại của người dùng")
op("ai", "get", "/api/v1/ai/conversations/{id}", "Nội dung một hội thoại")
op("ai", "delete", "/api/v1/ai/conversations/{id}", "Xóa hội thoại")
op("ai", "post", "/api/v1/ai/sync", "Đồng bộ kho phân tích ngay", desc="Chỉ SUPER_ADMIN.")
op("ai", "get", "/api/v1/ai/analytics/overview", "KPI tổng quan BI", perm="ai.use | finance.view")
op("ai", "get", "/api/v1/ai/analytics/forecast", "Dự báo doanh số theo ngày", perm="ai.use | finance.view")
op("ai", "get", "/api/v1/ai/analytics/rfm-clusters", "Phân khúc khách hàng RFM", perm="ai.use | finance.view",
   query=[("cluster_key", "string", "Lọc một cụm")])
op("ai", "get", "/api/v1/ai/analytics/credit-risk", "Rủi ro công nợ khách hàng", perm="ai.use | finance.view")
op("ai", "post", "/api/v1/ai/analytics/refresh", "Huấn luyện lại mô hình phân tích", perm="ai.use")

SVC_TAG = {k: v[0] for k, v in SERVICES.items()}
DATA_HINT = "Phần `data` mô tả chung. Cấu trúc chi tiết từng DTO xem trong source của service."


def _path_params(path: str) -> list[dict]:
    params = []
    for name in re.findall(r"\{([A-Za-z]+)\}", path):
        is_id = name.lower().endswith("id")
        params.append({
            "name": name, "in": "path", "required": True,
            "schema": {"type": "integer", "format": "int64"} if is_id else {"type": "string"},
        })
    return params


def _op_object(svc: str, method: str, path: str, summary: str, opts: dict, op_ids: set[str]) -> dict:
    slug = re.sub(r"[^a-zA-Z0-9]+", "_", path.replace("/api/v1/", "")).strip("_")
    op_id = f"{svc}_{method}_{slug}"
    base = op_id
    n = 2
    while op_id in op_ids:
        op_id = f"{base}_{n}"
        n += 1
    op_ids.add(op_id)

    parameters = _path_params(path)
    for name, typ, desc in opts.get("query", []):
        schema = {"type": "integer", "format": "int64"} if typ == "integer" else {"type": typ}
        parameters.append({"name": name, "in": "query", "required": False, "description": desc, "schema": schema})

    responses = {
        "200": {"description": "Thành công", "content": {"application/json": {"schema": _ref("SuccessEnvelope")}}},
    }
    errors = [] if opts.get("public") else ["401"]
    if opts.get("perm"):
        errors.append("403")
    if "{id}" in path or "{productId}" in path or "{userId}" in path:
        errors.append("404")
    if opts.get("body"):
        errors.append("422")
    if opts.get("conflict"):
        errors.append("409")
    for code in errors:
        responses[code] = {"description": {"401": "Thiếu hoặc sai JWT", "403": "Thiếu quyền",
                                            "404": "Không tìm thấy", "409": "Xung đột hoặc thiếu tồn kho",
                                            "422": "Dữ liệu không hợp lệ"}[code],
                           "content": {"application/json": {"schema": _ref("ErrorEnvelope")}}}

    desc_parts = [opts.get("desc", "")]
    if opts.get("perm"):
        perms = " hoặc ".join(f"`{p.strip()}`" for p in opts["perm"].split("|"))
        desc_parts.append(f"Quyền: {perms}.")
    description = " ".join(p for p in desc_parts if p).strip() or None

    obj: dict = {
        "tags": [SVC_TAG[svc]], "summary": summary, "operationId": op_id, "parameters": parameters,
        "responses": responses,
    }
    if description:
        obj["description"] = description
    if opts.get("public"):
        obj["security"] = []
    if opts.get("form"):
        props = {}
        for field, kind in opts["form"].items():
            if kind == "file":
                props[field] = {"type": "string", "format": "binary"}
            elif kind == "array":
                props[field] = {"type": "array", "items": {"type": "string", "format": "binary" if "attach" in field else "string"}}
            else:
                props[field] = {"type": "string"}
        obj["requestBody"] = {"required": True, "content": {"multipart/form-data": {"schema": {"type": "object", "properties": props}}}}
    elif opts.get("body"):
        obj["requestBody"] = {"required": True, "content": {"application/json": {"schema": _ref(opts["body"])}}}
    return obj


def build() -> dict:
    paths: dict[str, dict] = {}
    op_ids: set[str] = set()
    for svc, method, path, summary, opts in OPERATIONS:
        paths.setdefault(path, {})[method] = _op_object(svc, method, path, summary, opts, op_ids)

    schemas = dict(SCHEMAS)
    schemas["SuccessEnvelope"] = _obj({
        "success": {"type": "boolean", "example": True},
        "data": {"description": DATA_HINT, "nullable": True},
        "meta": _obj({"correlation_id": _s(), "timestamp": {"type": "integer"}}),
    }, ["success", "data"])
    schemas["ErrorEnvelope"] = _obj({
        "success": {"type": "boolean", "example": False},
        "error": _obj({
            "code": {"type": "string", "example": "INSUFFICIENT_STOCK",
                     "description": "Mã lỗi chuẩn: UNAUTHENTICATED, PERMISSION_DENIED, RESOURCE_NOT_FOUND, INSUFFICIENT_STOCK, "
                                    "CONCURRENT_CONFLICT, VALIDATION_FAILED, OVER_DELIVERY_NOT_ALLOWED, DEPENDENCY_UNAVAILABLE, SAGA_TIMEOUT"},
            "message": _s("Thông báo tiếng Việt"), "details": {"type": "object", "additionalProperties": True},
        }, ["code", "message"]),
    }, ["success", "error"])

    description = (
        "Tài liệu tổng hợp API của 4 service: **inventory**, **purchase**, **chat**, **ai**.\n\n"
        f"- Mọi request đi qua API Gateway `{GATEWAY_URL}`.\n"
        "- Xác thực: `Authorization: Bearer <JWT>` (nhấn **Authorize** ở trên). Các endpoint `health` không cần token.\n"
        "- JSON dùng `snake_case`. Thời gian là ISO 8601 UTC có hậu tố `Z`. Số tiền là số, không có dấu phân cách.\n"
        "- Mọi phản hồi bọc trong envelope `success` / `data` / `error` / `meta`.\n"
        "- Endpoint `/internal/*` (ETL giữa các service) không nằm trong tài liệu này.\n"
        "- Realtime chat: kết nối SignalR tại `/api/v1/chat/hub` (không nằm trong OpenAPI; chỉ nhận sự kiện server gửi)."
    ).strip()

    return {
        "openapi": "3.0.3",
        "info": {"title": "Smart ERP — API tổng hợp (inventory, purchase, chat, ai)", "version": "1.0.0", "description": description},
        "servers": [{"url": GATEWAY_URL, "description": "API Gateway (môi trường phát triển)"}],
        "tags": [{"name": SVC_TAG[k], "description": v[1]} for k, v in SERVICES.items()],
        "security": [{"bearerAuth": []}],
        "paths": paths,
        "components": {
            "securitySchemes": {"bearerAuth": {"type": "http", "scheme": "bearer", "bearerFormat": "JWT"}},
            "schemas": schemas,
        },
    }


HTML_TEMPLATE = """<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Smart ERP — Tài liệu API tổng hợp</title>
<link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
<style>
  body { margin: 0; background: #F8FAFC; }
  .topbar { display: none; }
  .intro { max-width: 1200px; margin: 24px auto 0; padding: 0 16px; font-family: system-ui, sans-serif; color: #334155; font-size: 14px; }
  .intro h1 { color: #0F172A; font-size: 20px; margin: 0 0 4px; }
</style>
</head>
<body>
<div class="intro">
  <h1>Tài liệu API tổng hợp — Smart ERP</h1>
  <div>Inventory · Purchase · Chat · AI. Sinh từ <code>build_openapi.py</code>, đặc tả nhúng trong trang này.</div>
</div>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script>
  const spec = __SPEC__;
  window.onload = () => {
    window.ui = SwaggerUIBundle({
      spec: spec,
      dom_id: "#swagger-ui",
      deepLinking: true,
      persistAuthorization: true,
      tryItOutEnabled: true,
      docExpansion: "list",
      defaultModelsExpandDepth: 0,
    });
  };
</script>
</body>
</html>
"""


def main() -> None:
    spec = build()
    (OUT_DIR / "openapi.json").write_text(json.dumps(spec, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    spec_js = json.dumps(spec, ensure_ascii=False).replace("</", "<\\/")
    (OUT_DIR / "index.html").write_text(HTML_TEMPLATE.replace("__SPEC__", spec_js), encoding="utf-8")
    counts = {svc: sum(1 for o in OPERATIONS if o[0] == svc) for svc in SERVICES}
    print(f"Đã sinh {len(OPERATIONS)} endpoint: {counts}")
    print(f"  {OUT_DIR / 'openapi.json'}")
    print(f"  {OUT_DIR / 'index.html'}")


if __name__ == "__main__":
    import sys

    sys.stdout.reconfigure(encoding="utf-8")  # console Windows mặc định cp1252, không in được tiếng Việt
    main()
