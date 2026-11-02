import sys

path = r"G:\Do_an_lien_nganh\smart-erp\docs\ai-agent-tools-implementation-roadmap.md"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

target_marker = "### GIAI ĐOẠN 5: KIỂM THỬ, TÍNH TOÀN VẸN KIẾN TRÚC & NGHIỆM THU"
new_doc = """#### Bước 4.2: Component `SalesActionCard.vue` (Báo giá sỉ & Tạo đơn bán hàng SO trực tiếp từ chat)
Đặt tại: `frontend/src/components/ai/SalesActionCard.vue`
* **Tính năng nổi bật**:
  - Tự động hiển thị phân hạng đại lý (`Distributor`, `Dealer`, `Project`, `Retail`) và hạn mức tín dụng.
  - 3 Badge cảnh báo tự động: Tình trạng tồn kho khả dụng, An toàn hạn mức tín dụng (hoặc nợ quá hạn), Biên lợi nhuận gộp.
  - Cho phép người dùng trực tiếp tinh chỉnh số lượng và tỷ lệ chiết khấu thương lượng trên thẻ để kiểm tra lại biên lãi và tổng thanh toán.
  - Kiểm soát quyền RBAC hạt mịn: Gắn cờ `can_execute = actor.can('order.create')`. Nếu thiếu quyền, hiển thị ở chế độ Read-only Preview.
  - 2 nút hành động: `[🚀 Tạo Đơn Bán Hàng (Draft SO)]` (gọi `salesApi.createOrder`) và `[📑 Tạo Báo Giá (Quotation)]` (gọi `salesApi.createQuotation`).

#### Bước 4.3: Tích hợp Generative UI vào `AiConversationView.vue` và `aiStore.js`
* Đặt tại: `frontend/src/components/ai/AiConversationView.vue` và `frontend/src/stores/aiStore.js`.
* Tự động nhận diện `action_schema` từ phản hồi của LLM:
  - Nếu `action_type === 'ACTION_CARD_PROCUREMENT'`: Render `ProcurementActionCard`.
  - Nếu `action_type === 'ACTION_CARD_SALES_ORDER'`: Render `SalesActionCard`.
* Bắt sự kiện `@order-created` để kích hoạt Toast thông báo thành công và điều hướng xem chứng từ.

---

### GIAI ĐOẠN 5: KIỂM THỬ, TÍNH TOÀN VẸN KIẾN TRÚC & NGHIỆM THU"""

if target_marker in content:
    content = content.replace(target_marker, new_doc)
elif target_marker.replace("\n", "\r\n") in content:
    content = content.replace(target_marker.replace("\n", "\r\n"), new_doc.replace("\n", "\r\n"))
else:
    print("Error: target_marker not found in roadmap")
    sys.exit(1)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)

print("Successfully updated roadmap.md!")

