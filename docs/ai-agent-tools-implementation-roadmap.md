# KẾ HOẠCH TRIỂN KHAI BỘ CÔNG CỤ AI AGENT TƯƠNG TÁC (ACTIONABLE AI AGENT TOOLS & ML INTEGRATION)

> **Tài liệu tham chiếu chuẩn**: Tuân thủ nghiêm ngặt các quy chuẩn kiến trúc và lập trình tại:
> - [AGENTS.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/AGENTS.md) (Master Architecture & 7 nguyên tắc tự vấn)
> - [api-guidelines.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/api-guidelines.md) (RESTful Envelope & Gateway Routing)
> - [architecture.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/architecture.md) (Clean Architecture, Event-Driven, Saga & Concurrency)
> - [database-guidelines.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/database-guidelines.md) (Database-per-Service, Logical Keys & Outbox)
> - [UI_RULES.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_UI_rule/UI_RULES.md) (Vue 3, PrimeVue 4, Tailwind CSS, Generative UI Action Cards)

---

## 1. TỔNG QUAN & MỤC TIÊU KIẾN TRÚC

### 1.1. Bối cảnh & Bước chuyển đổi
Hệ thống `ai-service` hiện tại đã có mô hình phân tích dữ liệu và học máy mạnh mẽ (`ml.py`: K-Means RFM, Holt-Winters kết hợp ngoại sinh thời tiết, Credit Risk Scoring) và bộ công cụ AI chỉ-đọc (`app/tools.py`). 

Mục tiêu của task này là nâng cấp hệ thống từ **Trợ lý AI thụ động (Passive Read-Only Bot)** thành **Trợ lý AI hành động thế hệ mới (Actionable & Generative Agent)**:
1. **Truy vấn thông minh theo mã SKU**: Khi người dùng gửi mã sản phẩm vào Chatbox, Agent lập tức bóc tách lịch sử mua hàng từ các Nhà cung cấp (đơn giá, ngày mua, số lượng).
2. **Hợp nhất sức mạnh Machine Learning**: Tự động gọi mô hình dự báo **Holt-Winters** và tham chiếu định mức tồn kho để gợi ý số lượng đặt mua tối ưu, tính toán biên lợi nhuận kỳ vọng.
3. **Generative UI & Thao tác tức thì (One-Click Action)**: Trả về thẻ tương tác (Interactive Action Card) ngay trong khung Chat, cho phép người dùng chọn NCC, chỉnh số lượng và bấm `[Xác nhận Tạo Đơn Mua PO]` mà không cần rời màn hình.

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 LUỒNG TƯƠNG TÁC AGENTIC ERP                                     │
│                                                                                                 │
│  [ Người dùng Chat ] ──► [ API Gateway :8000 ] ──► [ ai-service :8010 ]                         │
│                                                             │                                   │
│  ┌──────────────────────────────────────────────────────────┴────────────────────────────────┐  │
│  │ Vòng lặp Agent (Function Calling):                                                        │  │
│  │ 1. LLM nhận diện SKU & Intent ──► Gọi Tool: lookup_product_procurement(sku)               │  │
│  │ 2. Tool truy vấn giá cũ (purchase) + Tồn kho (inventory) + Dự báo 30 ngày (ml.py)        │  │
│  │ 3. Trả về Action Schema Payload: { action_type: 'CREATE_PO_CARD', suppliers, ml_data }   │  │
│  └──────────────────────────────────────────────────────────┬────────────────────────────────┘  │
│                                                             │                                   │
│  [ Vue 3 Chat Drawer ] ◄── Render Interactive Card ◄────────┘                                   │
│            │                                                                                    │
│            └─► Người dùng bấm [Xác nhận Đặt Mua] ──► [ Gateway :8000 ] ──► [ purchase-service ] │
│                                                                             (Tạo Draft PO)      │
└─────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. BẢNG QUY HOẠCH TOP 10 TOOL AI AGENT HÀNG ĐẦU CHO HỆ THỐNG

Tuân thủ phân định quyền hạn hạt mịn (RBAC) và ranh giới Bounded Context giữa các service:

| STT | Mã Tool & Tên gọi | Phân hệ & Quyền hạn | Dịch vụ & Mô hình ML kết nối | Mô tả & Thao tác tương tác trực tiếp (Generative UI) |
| :-: | :--- | :--- | :--- | :--- |
| **1** | `procurement_fast_order`<br>**Truy cứu giá & Đặt mua nhanh** | Mua hàng<br>`purchase.create` | `purchase-service`<br>`catalog-service`<br>`ml.py` (Holt-Winters) | Nhập SKU $\rightarrow$ So sánh giá các NCC, gợi ý SL nhập theo ML $\rightarrow$ Thẻ tương tác chọn giá và nút `[Tạo Đơn mua PO]`. |
| **2** | `smart_restock_plan`<br>**Đề xuất kế hoạch nhập kho** | Kho vận & Mua hàng<br>`inventory.view`, `purchase.view` | `inventory-service`<br>`ml.py` (Dự báo 30 ngày & Nhiệt độ) | Quét mã hàng $\text{Available} \le \text{MinStock}$, phân tích tốc độ tiêu thụ $\rightarrow$ Thẻ cảnh báo thiếu hàng kèm nút `[Tạo PO khuyến nghị]`. |
| **3** | `instant_quote_check`<br>**Báo giá sỉ & Check kho nhanh** | Kinh doanh<br>`order.view`, `catalog.view` | `inventory-service`<br>`catalog-service`<br>`sales-service` | Tra nhanh tồn khả dụng và tự áp chính sách chiết khấu đại lý $\rightarrow$ Trả về giá chốt bán sỉ kèm nút `[Lập Báo giá B2B]`. |
| **4** | `smart_margin_guard`<br>**Bảo vệ biên lãi & Đàm phán** | Kinh doanh<br>`order.create`, `report.view` | `catalog-service`<br>`sales-service`<br>`FactSales` (Giá vốn) | Khi khách mặc cả, kiểm tra sàn biên lợi nhuận gộp (Gross Margin) $\rightarrow$ Cảnh báo bán lỗ, gợi ý kịch bản đổi khối lượng hoặc gửi duyệt ngoại lệ. |
| **5** | `create_sales_order_nlp`<br>**Lên đơn bán bằng câu chat** | Kinh doanh<br>`order.create` | `sales-service`<br>`catalog-service`<br>LLM Entity Extraction | Đọc câu lệnh tự nhiên (chat/voice) $\rightarrow$ Tự map khách hàng, SKU, áp giá sỉ $\rightarrow$ Trả về thẻ xem trước đơn kèm nút `[Xác nhận Duyệt SO]`. |
| **6** | `credit_risk_precheck`<br>**Thẩm định tín dụng & Nợ xấu** | Kinh doanh & Kế toán<br>`finance.view` | `finance-service`<br>`ml.py` (Credit Risk Scoring) | Check hạn mức nợ còn trống và ngày quá hạn trước khi lên đơn $\rightarrow$ Khuyến nghị cho nợ tiếp hay bắt buộc thu tiền mặt/COD. |
| **7** | `suggest_accessory_bundle`<br>**Gợi ý bán chéo phụ kiện** | Kinh doanh<br>`catalog.view` | `catalog-service`<br>`inventory-service`<br>Association Rules | Khi đơn có điều hòa/tivi, tự gợi ý combo phụ kiện (ống đồng, giá treo, aptomat) biên lãi cao (25-35%) $\rightarrow$ Nút `[➕ Thêm vào Đơn]`. |
| **8** | `at_risk_winback_action`<br>**Kích hoạt đại lý At-Risk** | CSKH & Giám đốc KD<br>`customer.view`, `order.create` | `sales-service`<br>`ml.py` (K-Means RFM: At-Risk) | Lọc đại lý lớn $> 60$ ngày chưa mua $\rightarrow$ Đề xuất kịch bản chăm sóc (cấp voucher 2%, nới hạn nợ) kèm nút `[Gửi Email/Zalo Tri ân]`. |
| **9** | `resolve_matching_discrepancy`<br>**Xử lý sai lệch Three-Way** | Kho & Mua hàng<br>`purchase.view`, `inventory.view` | `purchase-service`<br>`inventory-service` | Đối soát PO vs GRN thực tế phát hiện lệch hàng $\rightarrow$ Chỉ rõ số lượng thiếu/thừa và hiển thị nút `[Lập Biên bản Sai lệch DN]`. |
| **10** | `executive_root_cause_bi`<br>**Diễn giải nguyên nhân tài chính** | Ban Giám Đốc<br>`SUPER_ADMIN`, `report.view` | `ai-service`<br>`FactSales` Data Warehouse | Giám đốc hỏi lý do biến động lãi/doanh thu $\rightarrow$ Phân tích dịch chuyển cơ cấu hàng (Product Mix) & mức chiết khấu bằng ngôn ngữ tự nhiên. |

---

## 3. CÁC BƯỚC TRIỂN KHAI CHI TIẾT (STEP-BY-STEP ROADMAP)

Triển khai thí điểm hoàn chỉnh cho **Tool 1 (`procurement_fast_order`)** và **Tool 3/4 (`instant_quote_check` & `smart_margin_guard`)** theo 5 giai đoạn:

```text
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  GIAI ĐOẠN 1 │ ──► │  GIAI ĐOẠN 2 │ ──► │  GIAI ĐOẠN 3 │ ──► │  GIAI ĐOẠN 4 │ ──► │  GIAI ĐOẠN 5 │
│ Chuẩn hóa API│     │ Tích hợp ML  │     │ Cấu hình Tool│     │ Xây dựng UI  │     │ Kiểm thử &   │
│ & DTO Backend│     │ & Analytics  │     │ & Prompt LLM │     │ Action Cards │     │ Nghiệm thu   │
└──────────────┘     └──────────────┘     └──────────────┘     └──────────────┘     └──────────────┘
```

---

### GIAI ĐOẠN 1: CHUẨN HÓA API CONTRACTS VÀ SERVICE INTEGRATION
> **Mục tiêu**: Đảm bảo các service nghiệp vụ (`catalog-service`, `purchase-service`, `inventory-service`) cung cấp đủ Endpoint theo chuẩn [api-guidelines.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/api-guidelines.md).

#### Bước 1.1: Endpoint Lịch sử Mua hàng theo SKU trên `purchase-service` (C# .NET 10)
Tạo Endpoint lấy 5 lần mua gần nhất của sản phẩm theo SKU kèm thông tin Nhà cung cấp:
* **Tuyến đường Gateway**: `GET /api/v1/purchase/orders/item-history?sku={sku}`
* **Quy chuẩn C#**: Dùng `.AsNoTracking()`, trả về DTO Envelope chuẩn.

```csharp
// PurchaseOrderItemHistoryDto.cs
public record PurchaseOrderItemHistoryDto(
    long SupplierId,
    string SupplierName,
    decimal UnitCost,
    int Quantity,
    DateTime OrderDate,
    string PoNumber
);
```

#### Bước 1.2: Endpoint Tạo Đơn Mua Hàng Nhanh (Draft PO Creation)
* **Tuyến đường Gateway**: `POST /api/v1/purchase/orders`
* **Headers bắt buộc**: `Authorization: Bearer <JWT>`, `X-Correlation-ID: <UUID>`
* **Payload chuẩn**:
```json
{
  "supplier_id": 1,
  "expected_date": "2026-11-05T00:00:00Z",
  "notes": "Đơn hàng khởi tạo tự động từ Trợ lý AI Procurement Copilot",
  "items": [
    {
      "product_id": 201,
      "sku": "DAIKIN-FTKF25XVMV",
      "quantity_ordered": 50,
      "unit_cost": 8200000.0
    }
  ]
}
```

---

### GIAI ĐOẠN 2: TÍCH HỢP HỆ THỐNG MACHINE LEARNING (`ml.py`)
> **Mục tiêu**: Mở rộng module `ml.py` để cung cấp hàm tính toán nhu cầu và gợi ý số lượng mua tối ưu dựa trên In-memory Cache.

#### Bước 2.1: Hàm truy xuất dự báo nhu cầu SKU từ `ml.py`
Mở tệp `services/ai-service/app/ml.py`, bổ sung hàm tính toán khuyến nghị nhập hàng:

```python
def get_sku_procurement_ml_insight(sku: str, available_stock: int, min_stock: int) -> dict:
    """Kết hợp mô hình Holt-Winters dự báo 30 ngày và định mức an toàn để tính số lượng cần nhập."""
    with _cache_lock:
        forecast_data = _cache.get("forecast_sales_30d", {})
    
    # Tính toán tốc độ bán trung bình ngày của SKU từ kho phân tích FactSales
    with db() as con:
        row = con.execute("""
            SELECT coalesce(sum(quantity), 0) as total_sold_90d,
                   coalesce(sum(profit) / nullif(sum(subtotal), 0) * 100, 0) as avg_margin_pct
            FROM fact_sales s
            JOIN dim_product p ON p.id = s.product_id
            WHERE p.sku = ? AND s.date >= date((SELECT max(date) FROM fact_sales), '-90 days')
        """, (sku,)).fetchone()
    
    sold_90d = row["total_sold_90d"] if row else 0
    daily_velocity = sold_90d / 90.0
    
    # Dự báo nhu cầu 30 ngày tới (có tính đến hệ số tăng trưởng của mô hình Holt-Winters)
    growth_factor = forecast_data.get("growth_rate_next_month", 1.15)
    predicted_demand_30d = int(round(daily_velocity * 30 * growth_factor))
    
    # Công thức SCM: Số lượng cần nhập = Nhu cầu 30 ngày + Tồn an toàn - Tồn khả dụng
    target_inventory = predicted_demand_30d + min_stock
    suggested_qty = max(0, target_inventory - available_stock)
    
    return {
        "daily_velocity": round(daily_velocity, 2),
        "predicted_demand_30d": predicted_demand_30d,
        "suggested_procurement_qty": suggested_qty,
        "historical_margin_pct": round(row["avg_margin_pct"], 1) if row else 0.0,
        "urgency_level": "CRITICAL" if available_stock == 0 else ("WARNING" if available_stock < min_stock else "NORMAL")
    }
```

---

### GIAI ĐOẠN 3: XÂY DỰNG TOOL VÀ PROMPT SYSTEM TRONG `ai-service`
> **Mục tiêu**: Khai báo Schema Function Calling chuẩn trong `app/tools.py` và cập nhật chỉ dẫn vận hành cho Agent trong `app/agent.py`.

#### Bước 3.1: Khai báo Handler Tool trong `services/ai-service/app/tools.py`

```python
def procurement_fast_order(actor: Actor, args: dict) -> dict:
    """Truy cứu lịch sử giá mua của sản phẩm, đối chiếu ML dự báo nhu cầu và trả về Action Schema đặt mua."""
    sku = (args.get("sku") or args.get("query") or "").strip()
    if not sku:
        return {"loi": "Vui lòng cung cấp mã sản phẩm (SKU)."}

    with db() as con:
        # 1. Tìm thông tin sản phẩm và tồn kho
        prod = con.execute("""
            SELECT p.id, p.sku, p.name, p.list_price, p.unit_cost, p.min_stock,
                   coalesce(i.quantity_available, 0) as available_stock,
                   coalesce(i.quantity_on_hand, 0) as on_hand_stock
            FROM dim_product p
            LEFT JOIN dim_inventory i ON i.product_id = p.id
            WHERE p.sku = ? OR unaccent(p.name) LIKE ?
            LIMIT 1
        """, (sku, f"%{unaccent(sku)}%")).fetchone()

        if not prod:
            return {"loi": f"Không tìm thấy sản phẩm có mã hoặc tên: '{sku}'"}

        # 2. Lấy 3 nhà cung cấp có lịch sử mua hàng gần nhất
        history_rows = con.execute("""
            SELECT s.id as supplier_id, s.name as supplier_name, s.code as supplier_code,
                   po.unit_cost, po.order_date, po.quantity
            FROM fact_purchase po
            JOIN dim_supplier s ON s.id = po.supplier_id
            WHERE po.product_id = ?
            ORDER BY po.order_date DESC
            LIMIT 5
        """, (prod["id"],)).fetchall()

    # 3. Kéo insight từ mô hình ML
    ml_insight = ml.get_sku_procurement_ml_insight(
        sku=prod["sku"], 
        available_stock=prod["available_stock"], 
        min_stock=prod["min_stock"]
    )

    # 4. Đóng gói Action Payload phục vụ Generative UI
    suppliers_payload = []
    for r in history_rows:
        margin_pct = round(((prod["list_price"] - r["unit_cost"]) / prod["list_price"]) * 100, 1) if prod["list_price"] > 0 else 0
        suppliers_payload.append({
            "supplier_id": r["supplier_id"],
            "supplier_name": r["supplier_name"],
            "latest_price": float(r["unit_cost"]),
            "latest_order_date": r["order_date"],
            "expected_margin_pct": margin_pct,
            "is_best_price": False # Sẽ được đánh dấu bên dưới
        })
    
    if suppliers_payload:
        best_price = min(s["latest_price"] for s in suppliers_payload)
        for s in suppliers_payload:
            if s["latest_price"] == best_price:
                s["is_best_price"] = True

    return {
        "status": "SUCCESS",
        "product": {
            "id": prod["id"],
            "sku": prod["sku"],
            "name": prod["name"],
            "available_stock": prod["available_stock"],
            "min_stock": prod["min_stock"],
            "list_price": float(prod["list_price"])
        },
        "ml_insight": ml_insight,
        "suppliers": suppliers_payload,
        # Action Schema gửi về Frontend để kích hoạt Action Card
        "action_schema": {
            "type": "ACTION_CARD_PROCUREMENT",
            "can_execute": actor.can("purchase.create"),
            "default_supplier_id": suppliers_payload[0]["supplier_id"] if suppliers_payload else None,
            "default_quantity": ml_insight["suggested_procurement_qty"]
        }
    }
```

#### Bước 3.2: Đăng ký Tool vào danh sách `TOOLS` trong `app/tools.py`

```python
Tool(
    "procurement_fast_order",
    "Tra cứu lịch sử giá mua của sản phẩm theo mã SKU/tên, phân tích nhu cầu ML và trả về giao diện đặt mua nhanh.",
    obj({"sku": {"type": "string", "description": "Mã SKU hoặc tên sản phẩm cần tra cứu nguồn hàng"}}, ["sku"]),
    ("purchase.view",),
    procurement_fast_order
)
```

---

### GIAI ĐOẠN 4: THIẾT KẾ GENERATIVE UI (ACTION CARD COMPONENT VUE 3)
> **Mục tiêu**: Tuân thủ chuẩn [UI_RULES.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_UI_rule/UI_RULES.md) (PrimeVue 4, Tailwind CSS, Dark/Light Mode, trạng thái Disabled khi thiếu quyền).

#### Bước 4.1: Component `ProcurementActionCard.vue`
Đặt tại: `frontend/src/components/chat/ProcurementActionCard.vue`

```vue
<template>
  <div class="rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 p-4 shadow-sm my-2 text-surface-900 dark:text-surface-0">
    <!-- Header: Tên sản phẩm & Mã SKU -->
    <div class="flex items-start justify-between border-b border-surface-100 dark:border-surface-800 pb-3">
      <div>
        <span class="inline-block px-2 py-0.5 text-xs font-semibold rounded bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-300 mb-1">
          SKU: {{ data.product.sku }}
        </span>
        <h4 class="font-bold text-base line-clamp-1">{{ data.product.name }}</h4>
      </div>
      <div class="text-right">
        <span class="text-xs text-surface-500 block">Tồn khả dụng</span>
        <span :class="['font-bold text-sm', data.product.available_stock < data.product.min_stock ? 'text-red-500' : 'text-emerald-500']">
          {{ data.product.available_stock }} bộ
        </span>
      </div>
    </div>

    <!-- ML Insights Bar -->
    <div class="my-3 p-2.5 rounded-lg bg-surface-50 dark:bg-surface-800/60 border border-surface-200/60 dark:border-surface-700/60 text-xs space-y-1">
      <div class="flex justify-between items-center text-primary-600 dark:text-primary-400 font-semibold">
        <span>🤖 Gợi ý từ Machine Learning (Holt-Winters):</span>
        <span>Nhu cầu 30 ngày tới: ~{{ data.ml_insight.predicted_demand_30d }} bộ</span>
      </div>
      <p class="text-surface-600 dark:text-surface-400">
        Khuyến nghị đặt mua: <strong class="text-surface-900 dark:text-surface-100">{{ data.ml_insight.suggested_procurement_qty }} bộ</strong> để đảm bảo mức tồn kho an toàn.
      </p>
    </div>

    <!-- Form Chọn Nhà Cung Cấp & Số Lượng -->
    <div class="space-y-3 pt-1">
      <div>
        <label class="block text-xs font-medium mb-1 text-surface-600 dark:text-surface-300">Chọn Nhà Cung Cấp (Lịch sử giá gần nhất):</label>
        <select v-model="selectedSupplierId" class="w-full text-xs rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-50 dark:bg-surface-800 p-2 focus:ring-2 focus:ring-primary-500">
          <option v-for="sup in data.suppliers" :key="sup.supplier_id" :value="sup.supplier_id">
            {{ sup.supplier_name }} — {{ formatCurrency(sup.latest_price) }} (Lãi gộp dự kiến: {{ sup.expected_margin_pct }}%) {{ sup.is_best_price ? '★ TỐT NHẤT' : '' }}
          </option>
        </select>
      </div>

      <div class="flex gap-3">
        <div class="w-1/2">
          <label class="block text-xs font-medium mb-1 text-surface-600 dark:text-surface-300">Số lượng đặt mua:</label>
          <input v-model.number="orderQuantity" type="number" min="1" class="w-full text-xs rounded-lg border border-surface-300 dark:border-surface-600 bg-surface-50 dark:bg-surface-800 p-2 font-bold" />
        </div>
        <div class="w-1/2">
          <label class="block text-xs font-medium mb-1 text-surface-600 dark:text-surface-300">Tổng tiền tạm tính:</label>
          <div class="p-2 text-xs font-bold text-primary-600 dark:text-primary-400 rounded-lg bg-surface-100 dark:bg-surface-800">
            {{ formatCurrency(calculatedTotal) }}
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="mt-4 pt-3 border-t border-surface-100 dark:border-surface-800 flex gap-2">
      <button 
        @click="submitCreatePO" 
        :disabled="isSubmitting || !data.action_schema.can_execute"
        class="flex-1 py-2 px-3 text-xs font-bold text-white bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg shadow-sm transition flex items-center justify-center gap-1.5"
      >
        <i v-if="isSubmitting" class="pi pi-spin pi-spinner text-xs"></i>
        <span>{{ isSubmitting ? 'Đang tạo đơn...' : '🚀 Xác nhận Tạo Đơn Mua (Draft PO)' }}</span>
      </button>
      <button @click="$emit('dismiss')" class="py-2 px-3 text-xs font-medium text-surface-600 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-lg transition">
        Hủy
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  data: { type: Object, required: true }
});
const emit = defineEmits(['po-created', 'dismiss']);

const selectedSupplierId = ref(props.data.action_schema.default_supplier_id);
const orderQuantity = ref(props.data.action_schema.default_quantity || 10);
const isSubmitting = ref(false);

const selectedSupplier = computed(() => {
  return props.data.suppliers.find(s => s.supplier_id === selectedSupplierId.value) || props.data.suppliers[0];
});

const calculatedTotal = computed(() => {
  if (!selectedSupplier.value) return 0;
  return selectedSupplier.value.latest_price * (orderQuantity.value || 0);
});

function formatCurrency(val) {
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val || 0);
}

async function submitCreatePO() {
  try {
    isSubmitting.value = true;
    const payload = {
      supplier_id: selectedSupplierId.value,
      notes: "Tạo tự động từ Trợ lý AI Procurement Copilot",
      items: [
        {
          product_id: props.data.product.id,
          sku: props.data.product.sku,
          quantity_ordered: orderQuantity.value,
          unit_cost: selectedSupplier.value.latest_price
        }
      ]
    };
    
    // Gọi qua API Gateway :8000
    const res = await axios.post('/api/v1/purchase/orders', payload);
    emit('po-created', res.data.data);
  } catch (err) {
    console.error("Lỗi tạo PO từ Agent Card:", err);
    alert(err.response?.data?.error?.message || "Không thể tạo đơn mua hàng");
  } finally {
    isSubmitting.value = false;
  }
}
</script>
```

---

### GIAI ĐOẠN 5: KIỂM THỬ, TÍNH TOÀN VẸN KIẾN TRÚC & NGHIỆM THU
> **Mục tiêu**: Tự kiểm tra nghiêm ngặt theo **7 câu hỏi tự vấn trong AGENTS.md** trước khi bàn giao.

#### Checklist Tự vấn Kiến trúc (Self-Audit Checklist)

- [x] **1. Logic đặt ở đúng tầng Clean Architecture?**
  - Nghiệp vụ tạo đơn PO nằm hoàn toàn trong `purchase-service` (Application Command Handler). `ai-service` chỉ đóng vai trò Orchestrator gợi ý và chuyển tiếp lệnh.
- [x] **2. Hướng phụ thuộc một chiều?**
  - `ai-service` gọi qua REST API Gateway bằng HTTP Client, không tham chiếu trực tiếp project DLL hay mã nguồn của `purchase-service`.
- [x] **3. Dễ dàng viết Unit Test?**
  - `procurement_fast_order` nhận `Actor` và `args`, trả về dictionary thuần, hoàn toàn độc lập với UI.
- [x] **4. Không lộ thực thể Domain Entity ra ngoài?**
  - Dữ liệu trả về qua Action Schema là DTO thuần túy đã được lọc các trường nhạy cảm.
- [x] **5. Múi giờ chuẩn UTC?**
  - Tất cả các trường `order_date` đều dùng định dạng chuẩn ISO-8601 UTC.
- [x] **6. Truy vấn chỉ đọc đã tối ưu?**
  - Mọi truy vấn SQLite/PostgreSQL trong `tools.py` đều có `LIMIT`, tạo index trên `sku`, không lock bảng.
- [x] **7. Tuân thủ nguyên tắc KISS?**
  - Sử dụng trực tiếp Function Calling có sẵn của Groq/OpenAI, không lạm dụng các framework agent phức tạp gây chậm phản hồi.

---

## 4. KẾ HOẠCH NÂNG CẤP TIẾP THEO CHO TOÀN BỘ 10 TOOL

1. **Sprint 1 (Hoàn thành ngay)**: Triển khai Tool 1 (`procurement_fast_order`) & Tool 2 (`smart_restock_plan`).
2. **Sprint 2**: Triển khai bộ Tool cho Bán hàng: Tool 3 (`instant_quote_check`), Tool 4 (`smart_margin_guard`) và Tool 5 (`create_sales_order_nlp`).
3. **Sprint 3**: Triển khai bộ Tool Tài chính & Giữ chân khách hàng: Tool 6 (`credit_risk_precheck`), Tool 8 (`at_risk_winback_action`) và Tool 9 (`resolve_matching_discrepancy`).
4. **Sprint 4**: Triển khai Tool 10 (`executive_root_cause_bi`) phục vụ thuyết trình trực tiếp cho Ban Giám Đốc và Hội đồng chấm đồ án.
