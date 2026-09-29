# UI_RULES.md — Quy Chuẩn Thiết Kế & Lập Trình Giao Diện Vue 3

> **Dự án**: Smart Distributed ERP (v2.0.0)  
> **Framework Frontend**: PrimeVue 4 + Tailwind CSS v4  
> **Phong cách chủ đạo**: **Clean Flat White Canvas** (Enterprise SCM/ERP)  
> **Quy tắc tối thượng**: Bố cục phẳng 100%, nền trắng/slate-50 tinh gọn, **bắt buộc dùng Tailwind CSS v4 thay cho CSS thuần**, cấm Gradient/Radian, cấm Glassmorphism, bo góc chuẩn **`6px`**, viền mỏng hairline `1px solid #E2E8F0` (`border-slate-200`), tối ưu hiển thị dữ liệu mật độ cao cho phần mềm ERP chuyên nghiệp.

---

## 🎨 1. BẢNG MÃ MÀU CHÍNH THỨC (THEME COLOR TOKENS)

Hệ thống sử dụng bảng màu Slate & Azure/Sky phẳng, sạch sẽ, độ tương phản cao:

| Vai Trò | Mã Màu Hex | Tailwind Class Tương Đương | Ứng Dụng Trong Giao Diện |
|---|---|---|---|
| **Màu nền trang (Canvas Bg)** | `#F8FAFC` | `bg-slate-50` | Nền toàn bộ trang bên dưới các thẻ card. |
| **Màu nền bề mặt (Surface White)**| `#FFFFFF` | `bg-white` | Thẻ Card chính, bảng DataTable, Modal/Dialog. |
| **Hover dòng / Khối phụ** | `#F1F5F9` | `bg-slate-100` / `hover:bg-slate-50` | Hover dòng bảng, hover dropdown item, nền tab thụ động. |
| **Màu chủ đạo (Primary Accent)** | `#0284C7` | `bg-sky-600` / `text-sky-600` | Nút bấm chính, tab đang chọn, icon hành động chính. |
| **Hover màu chủ đạo** | `#0369A1` | `hover:bg-sky-700` | Trạng thái hover của nút bấm chính. |
| **Điểm nhấn phụ nhẹ (Subtle Tint)**| `#F0F9FF` | `bg-sky-50` | Nền của mục menu active, nhãn thông tin nhẹ. |
| **Viền mỏng (Hairline Border)** | `#E2E8F0` | `border-slate-200` | Viền card, viền phân cách bảng dữ liệu, ô nhập liệu (`1px`). |
| **Viền nổi bật (Border Hover/Focus)**| `#BAE6FD` | `border-sky-300` | Viền input khi focus, viền thẻ card khi hover. |
| **Màu chữ chính (Headings/Data)**| `#0F172A` | `text-slate-900` | Tiêu đề trang, số liệu tài chính/tồn kho, text quan trọng. |
| **Màu chữ nội dung (Body text)** | `#334155` | `text-slate-700` | Nhãn trường form, nội dung dữ liệu các cột trong bảng. |
| **Màu chữ phụ (Subtext/Muted)** | `#64748B` | `text-slate-500` | Ghi chú, placeholder, đơn vị tính, thời gian tạo. |

> [!WARNING]
> **CẤM GRADIENT & MÀU TÍM/INDIGO**: 
> 1. Không dùng bất kỳ dải màu gradient nào (như `linear-gradient`, `radial-gradient`, `bg-gradient-to-r`). Toàn bộ nền, nút, badge phải dùng màu phẳng (`flat color`).
> 2. Không sử dụng các sắc tố tím `#4F46E5`, `#6366F1`. Màu nhận diện hành động chính là Azure Sky (`#0284C7` / `sky-600`).

---

## ⚡ 2. QUY ĐỊNH BẮT BUỘC: DÙNG TAILWIND CSS THAY VÌ CSS THUẦN

> [!IMPORTANT]
> **100% UTILITY-FIRST VỚI TAILWIND CSS V4**:
> Mọi AI Agent khi code component `.vue` **BẮT BUỘC sử dụng các class utility của Tailwind CSS trực tiếp trên thẻ template**. Tuyệt đối **KHÔNG** tự ý viết các khối `<style scoped>` bằng CSS thuần nếu Tailwind đã cung cấp sẵn utility.

### 2.1. Quy Tắc Sử Dụng Tailwind CSS
* **Bố cục & Flexbox/Grid**: Dùng `flex`, `flex-col`, `grid`, `items-center`, `justify-between`, `gap-2`, `gap-4`.
* **Khoảng cách (Spacing)**: Dùng hệ thống spacing chuẩn `p-2`, `p-4`, `p-6`, `px-4`, `py-2`, `m-0`, `mb-4`.
* **Màu sắc & Viền**: Dùng `bg-white`, `bg-slate-50`, `border border-slate-200`, `text-slate-700`, `text-slate-900`, `text-slate-500`.
* **Bo góc chuẩn**: Luôn dùng `rounded-[6px]` (hoặc `rounded-md`).
* **Kích thước chữ**: Dùng `text-xs` (12px), `text-sm` (14px), `text-base` (16px), `text-lg` (18px), `text-xl` (20px).

### 2.2. Trường Hợp Duy Nhất Được Phép Dùng `<style scoped>`
Chỉ sử dụng `<style scoped>` khi cần:
1. Ghi đè (override) sâu các selector nội bộ đặc thù của PrimeVue không thể can thiệp qua class (ví dụ: `:deep(.p-datatable-thead > tr > th)`).
2. Quy định font chữ đặc thù như font-variant `tabular-nums` hoặc bảo vệ font icon PrimeIcons.
3. **CẤM** đặt class tự chế rồi viết CSS thủ công như `.card-container { display: flex; padding: 16px; }` $\rightarrow$ Phải viết thẳng: `class="flex p-4"`.

---

## 📐 3. QUY TẮC BO GÓC & BỐ CỤC PHẲNG (CLEAN FLAT STANDARDS)

### 3.1. Chuẩn Hóa Bo Góc Đúng 6px (Strict 6px Border Radius)
Để đảm bảo giao diện ERP sắc nét, chuyên nghiệp và đồng bộ 100%:
* **Toàn bộ Button, Input, Select, Card, Modal, Tag/Badge**: Đều cố định bo góc **`6px`** (`rounded-[6px]`).
* **CẤM TUYỆT ĐỐI**: Không dùng bo góc tròn xoe dạng bong bóng (`rounded-2xl`, `rounded-3xl`, `rounded-full`, `20px+`). Không dùng nút bo tròn con nhộng/viên thuốc.

### 3.2. Đường Viền Hairline 1px (No Thick Borders)
* Toàn bộ viền là **1px hairline**: `border border-slate-200` (`1px solid #E2E8F0`).
* **CẤM** dùng viền dày `2px`, `3px` thô kệch.

### 3.3. Cấu Trúc Bố Cục: Không Card Lồng Card
* **Tối đa 1 cấp Card bọc ngoài**: Không lồng `<Card>` bên trong một `<Card>` khác tạo thành nhiều lớp viền hộp dày cộm.
* Cấu trúc bên trong Card phải **phẳng**, phân tách các khu vực bằng đường kẻ mảnh `border-b border-slate-200` hoặc khoảng cách `gap-4`/`p-4`.

### 3.4. Không Shadow Tràn Lan & Không Glassmorphism
* **Cấm Shadow dày đặc**: Ưu tiên viền hairline `border-slate-200` để phân tách khối. Nếu dùng shadow, chỉ dùng shadow siêu nhẹ của hệ thống: `shadow-xs` hoặc `shadow-sm`.
* **Cấm Glassmorphism**: Không dùng `backdrop-filter: blur()`, không dùng nền kính mờ trong suốt. Mọi bề mặt chứa dữ liệu phải là nền trắng đục hoàn toàn (`bg-white`).

### 3.5. Hệ Thống Spacing Chuẩn Lưới 4px / 8px
Mọi padding, margin và gap bắt buộc là bội số của 4 hoặc 8:
* `gap-1` (4px), `gap-2` (8px), `gap-3` (12px), `gap-4` (16px), `gap-6` (24px).
* `p-2` (8px), `p-3` (12px), `p-4` (16px), `p-6` (24px), `p-8` (32px).

---

## 🏷️ 4. BẢNG MÀU TRẠNG THÁI NGHIỆP VỤ (STATUS BADGES)

Trong hệ thống ERP phân tán, trạng thái của Đơn hàng, Kho và Saga phải dùng các cặp màu nhã nhặn, nền phẳng, viền mỏng và **bo góc 6px**:

| Nghiệp Vụ | Trạng Thái | Tailwind Classes | Border | Bo Góc |
|---|---|---|---|---|
| **Thành công / Hoạt động** | `CONFIRMED`, `ACTIVE`, `ONLINE` | `bg-emerald-50 text-emerald-700` | `1px solid #A7F3D0` | `6px` |
| **Cảnh báo / Chờ giữ kho** | `PENDING_RESERVATION`, `WARNING` | `bg-amber-50 text-amber-700` | `1px solid #FDE68A` | `6px` |
| **Thất bại / Hết tồn kho** | `REJECTED_OUT_OF_STOCK`, `OFFLINE`| `bg-rose-50 text-rose-700` | `1px solid #FECDD3` | `6px` |
| **Trạng thái thông thường** | `CUSTOMER`, `PCS`, `DRAFT` | `bg-sky-50 text-sky-700` | `1px solid #BAE6FD` | `6px` |

---

## 🧩 5. QUY CHUẨN SỬ DỤNG COMPONENT CHÍNH THỨC CỦA PRIMEVUE 4

> [!IMPORTANT]
> **Ưu tiên hàng đầu**: Phải tận dụng toàn bộ các component chuẩn có sẵn của PrimeVue 4 thay vì viết các thẻ HTML nguyên thủy (`<select>`, `<input type="checkbox">`, `<ul>/<li>` điều hướng thủ công).

| Phân Loại | Component PrimeVue 4 Bắt Buộc | Thay Thế Cho | Lưu Ý Định Dạng & Styling |
|---|---|---|---|
| **Nút hành động** | `primevue/button` (`<Button>`) | Thẻ `<button>` thô | Bo góc `rounded-[6px]`, font weight 600, text 13px. |
| **Ô nhập văn bản/số**| `primevue/inputtext`, `inputnumber`| `<input>` thô | Viền `border-slate-200`, bo góc 6px, font-mono cho mã. |
| **Chọn danh mục/kho** | `primevue/select` (`<Select>`) | `<select>` HTML thô | Bản PrimeVue 4 dùng `Select` (thay thế `Dropdown` của v3). |
| **Hộp chọn lọc** | `primevue/checkbox` (`<Checkbox>`) | `<input type="checkbox">` | Kích thước vừa vặn, viền mỏng `border-slate-200`. |
| **Nhãn trạng thái** | `primevue/tag` (`<Tag>`) | `<span class="badge">` | Định dạng hình chữ nhật bo góc **6px**, cấm capsule/con nhộng. |
| **Tab chuyển đổi** | `primevue/tabs`, `primevue/tabpanels`| Tab tự chế bằng button | Cấu trúc chuẩn của PrimeVue 4, nền tab phẳng sạch sẽ. |
| **Hộp thoại tương tác** | `primevue/dialog` (`<Dialog>`) | Modal custom | Nền trắng, bo góc 6px, viền `border-slate-200`. |
| **Bảng dữ liệu** | `primevue/datatable` & `primevue/column` | Bảng HTML `<table>` | Phân trang, lọc, căn phải số liệu có `font-mono`. |

### 5.1. Quy Chuẩn Bắt Buộc: Phân Tách Hai Thành Phần Bảng (`CommonDataTable` vs `ItemDataTable`)

Để duy trì tính nhất quán 100% giữa tất cả các phân hệ (Sales, Inventory, Catalog, Purchasing, Accounting), toàn bộ lập trình viên và AI Agent **BẮT BUỘC** tuân thủ nguyên tắc phân định rõ ràng giữa hai component bảng sau:

```text
 ┌────────────────────────────────────────────────────────────────────────────────┐
 │                      BẢNG QUY TẮC SỬ DỤNG COMPONENT BẢNG                        │
 ├─────────────────────────┬──────────────────────────┬───────────────────────────┤
 │ Tiêu chí                │ CommonDataTable.vue      │ ItemDataTable.vue         │
 ├─────────────────────────┼──────────────────────────┼───────────────────────────┤
 │ 🎯 Phạm vi sử dụng      │ 100% Trang INDEX / Danh  │ 100% Trang FORM / Nhập    │
 │                         │ sách bản ghi (Listing)   │ liệu chứng từ (Document)  │
 ├─────────────────────────┼──────────────────────────┼───────────────────────────┤
 │ 🏢 Ví dụ màn hình       │ - Danh sách Báo giá      │ - Form Lập Báo Giá        │
 │                         │ - Tồn kho (StocksTab)    │ - Form Tạo Đơn Hàng (SO)  │
 │                         │ - Biến động (Movements)  │ - Form Đơn Mua Hàng (PO)  │
 │                         │ - Giữ chỗ (Reservations) │ - Phiếu Nhập Kho (GRN)    │
 │                         │ - Danh sách Sản phẩm     │ - Phiếu Xuất Kho (GDN)    │
 ├─────────────────────────┼──────────────────────────┼───────────────────────────┤
 │ ⚙️ Tính năng tích hợp   │ - Phân trang (Paginator) │ - v-model mảng mặt hàng   │
 │                         │ - Loading indicator      │ - Thêm dòng trống         │
 │                         │ - Empty State chuẩn hóa  │ - Tìm & thêm từ Catalog   │
 │                         │ - Slot cột tùy biến      │ - Banner Subtotal/VAT/CK% │
 │                         │ - Căn lề số liệu tự động │ - Tính toán 2 chiều       │
 │                         │ - Gridlines Clean Flat   │ - Ẩn/Hiện cột linh hoạt   │
 │                         │                          │ - Xuất file Excel / CSV   │
 ├─────────────────────────┼──────────────────────────┼───────────────────────────┤
 │ 🚫 Điều cấm             │ Tuyệt đối KHÔNG tự viết  │ Tuyệt đối KHÔNG tự code   │
 │                         │ raw <DataTable> thô ở    │ lại bảng table nhập item  │
 │                         │ các màn hình danh sách.  │ đơn lẻ trên từng form.    │
 └─────────────────────────┴──────────────────────────┴───────────────────────────┘
```

#### A. `CommonDataTable.vue` (`frontend/src/components/common/CommonDataTable.vue`)
* **Mục đích**: Chuẩn hóa toàn bộ bảng hiển thị danh sách của hệ thống theo ngôn ngữ thiết kế Clean Flat White Canvas (`bg-white border border-slate-200 rounded-[6px]`, `showGridlines`, class `clean-datatable`).
* **Cách sử dụng**:
```vue
<CommonDataTable
  :value="filteredStocks" 
  :columns="tableColumns"
  :loading="loading.stocks" 
  :rows="10"
  emptyIcon="pi pi-box"
  emptyTitle="Không tìm thấy dữ liệu tồn kho"
  emptySubtitle="Không có sản phẩm nào phù hợp với bộ lọc kho."
>
  <!-- Slot tùy biến nội dung theo từng cột: #col-[field] -->
  <template #col-sku="{ data }">
    <span class="font-mono text-xs font-semibold text-slate-800">{{ data.sku }}</span>
  </template>

  <!-- Cột hành động thao tác (slot mặc định) -->
  <Column header="Thao Tác" style="width: 140px" class="text-center">
    <template #body="{ data }">
      <Button icon="pi pi-pencil" text size="small" class="!w-7 !h-7 !p-0 !rounded-[6px]" @click="onEdit(data)" />
    </template>
  </Column>
</CommonDataTable>
```

#### B. `ItemDataTable.vue` (`frontend/src/components/common/ItemDataTable.vue`)
* **Mục đích**: Bảng nhập liệu mặt hàng nghiệp vụ cho các chứng từ mua bán, kho vận có khả năng tự động tính toán tài chính và tích hợp danh mục sản phẩm (Catalog).
* **Cách sử dụng**:
```vue
<!-- Trong form.vue (Lập báo giá, Đơn đặt hàng, Đơn mua hàng...) -->
<ItemDataTable 
  v-model="form.items" 
  @summary-change="onItemsSummaryChange" 
/>
```
* **Tính năng bắt buộc phải có**:
  1. **Tính toán 2 chiều (Bidirectional Formula)**:
     - Sửa Chiết khấu % $\rightarrow$ Tự động tính đơn giá bán Selling Price và Thành tiền Subtotal.
     - Sửa Selling Price $\rightarrow$ Tự động tính ngược lại % Chiết khấu.
     - Sửa Số lượng hoặc Giá niêm yết Gross Price $\rightarrow$ Tự cập nhật Subtotal.
  2. **Banner thống kê tóm tắt**: Tự động tính tổng tiền hàng trước thuế, tổng tiền thuế VAT, chiết khấu bình quân và tổng thanh toán.
  3. **Tích hợp Catalog Search Modal**: Cho phép thủ kho hoặc nhân viên kinh doanh tìm kiếm nhanh theo SKU/tên sản phẩm và chọn hàng loạt đưa vào chứng từ.
  4. **Cài đặt ẩn hiện cột**: Cho phép tùy chỉnh hiển thị thông tin như Xuất xứ (COM), Hạn giao hàng (Lead Time), Đơn vị tính (Unit).

### 5.2. Cấu Trúc Tuyến Đường & Menu Phân Hệ Kho Vận (Inventory Routes & Sidebar)

Phân hệ Kho Vận được chuẩn hóa thành 1 Folder mở rộng trên Sidebar (`inventory-hub`) đồng bộ hoàn toàn với các phân hệ khác (Tổ chức, Sản phẩm, Bán hàng), bao gồm 4 trang con với URL và quyền hạn riêng biệt:

| STT | Tên Menu / Trang | Đường Dẫn (Route) | Mục Đích Nghiệp Vụ & Thành Phần |
|---|---|---|---|
| **1** | **Tổng quan kho** | `/inventory/overview` | Dashboard tổng quan KPI, sức chứa 3 kho chi nhánh (Bắc, Trung, Nam), cảnh báo Min-Stock và Dashboard chuyên sâu từng kho. |
| **2** | **Bảng tồn kho** | `/inventory/stocks` | Bảng tra cứu tồn kho thực tế toàn bộ 100+ mã SKU, phân bổ theo kho, số lượng On-Hand, Reserved, Available sử dụng `CommonDataTable`. |
| **3** | **Hàng giữ chỗ** | `/inventory/reservations` | Quản lý mặt hàng đang khóa cam kết theo Đơn Bán Hàng (SO), tỷ lệ giữ chỗ, thao tác hủy giải phóng giữ chỗ hoặc xuất kho. |
| **4** | **Sổ cái thẻ kho** | `/inventory/movements` | Nhật ký Audit Trail truy vết biến động xuất/nhập/giữ/chuyển theo thời gian thực và mã chứng từ nguồn (PO, SO, GDN, TRF). |

* **Quy tắc điều hướng (URL Deep Linking)**:
  - Truy cập `/inventory` tự động chuyển hướng (`redirect`) sang `/inventory/overview`.
  - Mọi thao tác chuyển tab từ Dashboard (như bấm vào thẻ KPI hoặc nút xem thẻ kho) bắt buộc dùng `router.push('/inventory/...')` thay vì lưu state tạm thời, đảm bảo người dùng F5 hoặc chia sẻ link không bị mất ngữ cảnh.

---

## 📑 6. QUY CHUẨN QUẢN LÝ TIÊU ĐỀ (HEADINGS & TITLES)

### 6.1. Cấu Trúc Tiêu Đề Cấp Trang
* **Màn hình danh sách**: `<h1>Danh Mục Sản Phẩm</h1>`
* **Màn hình chi tiết**: `<h1>Đơn Mua Hàng #PO-2024-001</h1>`

### 6.2. Cấm Lặp Lại Tiêu Đề (No Title Duplication)
* Khi ở trên header của trang đã có tiêu đề `Danh Mục Sản Phẩm`, bên dưới trong thẻ `<Card>` hoặc `<DataTable>` **KHÔNG ĐƯỢC** viết lại một thẻ `<h2>` hoặc `<h3>` có chữ `Danh Mục Sản Phẩm`.
* Trong Card hoặc DataTable, chỉ dùng thanh Toolbar chứa các công cụ: Tìm kiếm, Bộ lọc trạng thái, Nút Thêm mới, Nút Xuất dữ liệu.

### 6.3. Không Slogan, Không Mô Tả Dài Dòng
* Dưới tiêu đề trang không chèn các câu văn dài dòng mang tính trang trí.
* Nếu thực sự cần ghi chú phạm vi, chỉ dùng tối đa **1 dòng subtitle ngắn gọn** với màu `text-slate-500` (`var(--text-muted)`):
  * *Ví dụ*: `<p class="text-xs text-slate-500">Quản lý định danh SKU, nhóm ngành hàng và chính sách giá bán buôn.</p>`

### 6.4. Tiêu Đề Modal / Drawer Phải Chính Xác Theo Ngữ Cảnh
* Khi mở Dialog/Drawer, tiêu đề phải nói rõ hành động cụ thể: `"Thêm Mới Khách Hàng"`, `"Cập Nhật Thông Tin #KH-012"`, `"Xác Nhận Hủy Đơn Hàng"`.
* **CẤM** để tiêu đề mập mờ, vô nghĩa: `"Thông tin"`, `"Thao tác"`, `"Chi tiết"`.

---

## 🗂️ 7. QUY CHUẨN THANH ĐIỀU HƯỚNG SIDEBAR & MENU

### 7.1. Font Chữ Ở Menu: Cơ Bản & Tuyệt Đối Không In Đậm
* **Font-family**: Dùng font chữ văn bản cơ bản tiêu chuẩn hệ thống (`Inter, system-ui, sans-serif`).
* **Font-weight**: **Toàn bộ chữ ở Menu là `font-weight: 400 !important;` (chữ thường, KHÔNG in đậm)**.
  * Tiêu đề danh mục: `font-weight: 400`, chữ hoa nhỏ (11px), màu `text-slate-400`.
  * Tên các phân hệ: `font-weight: 400`, cỡ 13px, màu `text-slate-600`.
  * Khi mục đang được chọn (`active`): **Vẫn giữ `font-weight: 400`**, nhận diện bằng nền `bg-sky-50`, chữ `text-sky-700` và vạch chỉ báo xanh dương bên trái (`border-l-2 border-sky-600`), **không làm đậm chữ**.

### 7.2. Bảo Vệ Tuyệt Đối Font-Family Của Biểu Tượng (Icon Isolation)
Khi áp dụng style cho menu, **TUYỆT ĐỐI KHÔNG** được để selector đè lên font của biểu tượng icon PrimeIcons:
```css
/* BẮT BUỘC: Giữ vững font-family primeicons cho icon */
.menu-icon,
.menu-item i,
.menu-item .pi {
  font-family: 'primeicons' !important;
  font-size: 18px !important;
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
}
```

### 7.3. Tính Năng Thụt Ra Thụt Vào (Collapsible Sidebar)
* Thanh menu bên trái hỗ trợ 2 trạng thái: **Mở rộng (260px)** và **Thu gọn (68px)**.
* Kích hoạt toggle qua khối thương hiệu đầu menu (`.brand-box`).
* Khi thu gọn (`isSidebarCollapsed = true`):
  * Icon logo chuyển trạng thái hiển thị (`pi-bars` để báo hiệu mở rộng).
  * Mục menu chuyển thành ô vuông **`44px x 44px`** căn giữa, icon kích thước **`20px`**.
  * Tích hợp `v-tooltip.right` của PrimeVue để hiển thị tên phân hệ khi hover.
  * Lưu trạng thái vào `localStorage` (`smart_erp_sidebar_collapsed`).

---

## 📊 8. QUY CHUẨN THẺ THỐNG KÊ (CARDS) & ỔN ĐỊNH GIAO DIỆN

### 8.1. Cấm Hoàn Toàn Hiệu Ứng Nhảy Rung Lắc (No Hover Bounce / Jump)
* **TUYỆT ĐỐI CẤM** dùng `transform: translateY(-2px)` hoặc `translateY(-4px)` khi `:hover` trên các thẻ Card và Persona.
* **Hover an toàn, tinh tế**: Chỉ thay đổi nhẹ màu viền (`hover:border-slate-300`) hoặc nền nhẹ (`hover:bg-slate-50`), thẻ luôn đứng yên vững chãi.

### 8.2. Định Dạng Số Liệu Khả Dụng & Tiền Tệ
* Toàn bộ số liệu tồn kho, số tiền, mã SKU phải áp dụng class `num-tabular` hoặc `font-mono` (`tabular-nums`) để các chữ số có bề ngang bằng nhau, giúp bảng cân đối và thẻ KPI không bị lệch hàng.

---

## ⚡ 9. NGUYÊN TẮC LẬP TRÌNH VUE 3 (COMPOSITION API)

### 9.1. Cấu Trúc File `.vue` Chuẩn
* Sử dụng `<script setup>` 100%.
* Bố cục template dùng utility class của Tailwind CSS v4.

```vue
<template>
  <div class="bg-white border border-slate-200 rounded-[6px] p-5">
    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
      <div class="flex items-center gap-2">
        <i class="pi pi-box text-sky-600 text-lg"></i>
        <h2 class="text-base font-bold text-slate-900 m-0">{{ title }}</h2>
      </div>
      <Button 
        v-if="canCreate"
        label="Thêm Mới" 
        icon="pi pi-plus" 
        class="!bg-sky-600 hover:!bg-sky-700 !border-none !rounded-[6px] !text-xs !font-semibold" 
        @click="openModal" 
      />
    </div>
    
    <div class="pt-4">
      <!-- Nội dung bảng hoặc dữ liệu -->
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Button from 'primevue/button'

const props = defineProps({
  title: { type: String, required: true }
})

const canCreate = computed(() => true)
const openModal = () => {}
</script>
```

### 9.2. Phân Quyền Động (Stateless RBAC)
Tuyệt đối không để lộ các nút hành động nhạy cảm nếu tài khoản hiện tại không có quyền tương ứng trong JWT Token:
```vue
<Button 
  v-if="currentUser?.permissions?.includes('sales.create')"
  label="Tạo Đơn Hàng Mới" 
  icon="pi pi-plus" 
  class="!bg-sky-600 hover:!bg-sky-700 !rounded-[6px]" 
  @click="openCreateModal" 
/>
<span v-else class="text-xs text-slate-400 inline-flex items-center gap-1">
  <i class="pi pi-lock"></i> Chỉ đọc (Thiếu quyền sales.create)
</span>
```

### 9.3. Gọi API Qua Gateway & Chuẩn Hóa Header
* Mọi API call từ Vue 3 phải gọi qua đường dẫn API Gateway: `/api/v1/{service}/...`
* Bắt buộc đính kèm JWT Bearer Token và `X-Correlation-ID`.

---

## 🚫 10. CÁC ĐIỀU CẤM KỴ KHI CODE UI (ZERO TOLERANCE)

1. **CẤM GRADIENT & GLASSMORPHISM**: Không dùng dải chuyển màu, không dùng hiệu ứng kính mờ `backdrop-filter: blur()`.
2. **CẤM BO GÓC TRÒN QUÁ ĐÀ**: Không dùng bo góc > 8px, cấm `rounded-2xl`, `rounded-3xl`, `rounded-full` cho card và button. Giữ chuẩn cố định **`6px`** (`rounded-[6px]`).
3. **CẤM CARD LỒNG CARD**: Tối đa 1 cấp Card bọc ngoài, cấu trúc bên trong phẳng.
4. **CẤM DÙNG BORDER DÀY THÔ KỆCH**: Chỉ dùng viền mỏng hairline `1px solid #E2E8F0` (`border-slate-200`). Cấm `2px`, `3px`.
5. **CẤM LẶP LẠI TIÊU ĐỀ**: Không viết lại tiêu đề trang bên trong Card con hoặc DataTable con.
6. **CẤM VIẾT CSS THUẦN AD-HOC**: Bắt buộc dùng class tiện ích Tailwind CSS v4, không tạo class tự chế bừa bãi trong `<style scoped>`.
7. **CẤM HOVER NHẢY RUNG LẮC TRÊN THẺ CARD (`transform: translateY`)**: Giữ thẻ cố định, chỉ hover viền/nền nhẹ.
8. **CẤM DÙNG CHỮ IN ĐẬM VÀ FONT KHÔNG PHÙ HỢP TRÊN THANH MENU**: Dùng font văn bản cơ bản không in đậm (`font-weight: 400`).
9. **CẤM ĐÈ FONT CHỮ LÀM BIẾN DẠNG ICON PRIMEICONS**: Luôn bảo vệ font `primeicons` cho các class `.pi`, `.menu-icon`.
10. **CẤM DÙNG MOCK RỖNG HOẶC PLACEHOLDER VÔ NGHĨA**: Luôn tạo 5-10 dòng dữ liệu mẫu tiếng Việt chuẩn ngành SCM/phân phối.

---

## ✅ 11. BẢNG CHECKLIST KIỂM DUYỆT TRƯỚC KHI XUẤT CODE

Mỗi khi sinh hoặc sửa bất kỳ component/màn hình nào, AI bắt buộc rà soát checklist:

- [ ] **100% Tailwind CSS v4**: Dùng utility classes (`flex`, `gap-4`, `p-4`, `rounded-[6px]`, `border-slate-200`...), không tự chế class CSS thuần trong `<style scoped>` khi không cần thiết.
- [ ] **100% PrimeVue 4**: Nút bấm là `<Button>`, bảng là `<DataTable>`, form input là `<InputText>`/`<Select>`...
- [ ] **Chuẩn hóa Bảng Dữ Liệu**:
  - [ ] Trang **Index/Listing**: Bắt buộc dùng `CommonDataTable.vue`. Cấm dùng raw `<DataTable>` trực tiếp.
  - [ ] Trang **Form nhập chứng từ**: Bắt buộc dùng `ItemDataTable.vue` cho danh sách mặt hàng dòng hàng.
- [ ] **Không Gradient**: Không có bất kỳ dải màu chuyển đổi nào trong CSS/Style.
- [ ] **Không Glassmorphism**: Không dùng `backdrop-filter: blur()`, không để nền mờ trong suốt.
- [ ] **Không Card lồng Card**: Tối đa 1 cấp Card bọc ngoài, cấu trúc bên trong phẳng.
- [ ] **Không Shadow tràn lan**: Không gán shadow dày đặc; ưu tiên hairline border `1px solid #E2E8F0` (`border-slate-200`).
- [ ] **Bo góc chuẩn 6px**: Toàn bộ input, button, card, modal đều dùng `border-radius: 6px` (`rounded-[6px]`).
- [ ] **Hệ Spacing 4px/8px**: Padding, margin, gap là bội số của 4/8 (4, 8, 12, 16, 20, 24, 32px).
- [ ] **Văn phong chuẩn tiếng Việt**: Gãy gọn, chuyên nghiệp, không từ dịch máy, không văn AI sáo rỗng.
- [ ] **Title độc nhất**: Đúng 1 tiêu đề cho toàn màn hình, không lặp lại tiêu đề ở Card con bên dưới.
