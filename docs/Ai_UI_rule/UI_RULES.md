# UI_RULES.md — Quy Chuẩn Thiết Kế & Lập Trình Giao Diện Vue 3

> **Dự án**: Smart Distributed ERP (v2.0.0)  
> **Framework Frontend**: Vue 3 (Composition API `<script setup>`) + PrimeVue 4  
> **Phong cách chủ đạo**: **Soft Azure Radiant White** (Clean Canvas Enterprise)  
> **Quy tắc tối thượng**: Nền trắng tinh khiết, điểm xuyết ánh xanh nhẹ Radian/Gradient, bố cục **sắc nét, tinh tế, hạn chế bo góc tròn quá đà**, tối ưu hiển thị dữ liệu mật độ cao cho phần mềm ERP chuyên nghiệp.

---

## 🎨 1. BẢNG MÃ MÀU CHÍNH THỨC (THEME COLOR TOKENS)

| Vai Trò | Mã Màu Hex | Tên Màu | Ứng Dụng Trong Giao Diện |
|---|---|---|---|
| **Màu nền chính (Background)** | `#FFFFFF` | **Pure White** | Nền trang chính, bề mặt Dialog, khối dữ liệu trung tâm. |
| **Màu nền phụ (Backdrop Tint)** | `#F0F9FF` | **Ice Blue / Sky-50** | Nền topbar, thẻ card khởi nguồn gradient, hover dòng bảng. |
| **Màu chủ đạo (Primary Accent)** | `#0284C7` | **Azure Blue / Sky-600** | Nút bấm chính, icon phân hệ, tiêu đề KPI nổi bật, link active. |
| **Hover / Trạng thái nhấn** | `#0369A1` | **Deep Azure / Sky-700** | Trạng thái hover/active của nút bấm và link điều hướng. |
| **Điểm nhấn sáng (Light Accent)** | `#38BDF8` | **Sky-400** | Màu kết thúc của dải gradient nút bấm và badge. |
| **Viền mỏng (Hairline Border)** | `#E0F2FE` | **Sky-100** | Viền card, viền bảng dữ liệu, tạo cảm giác mặt kính trong suốt. |
| **Viền nổi bật (Border Hover)** | `#BAE6FD` | **Sky-200** | Viền input khi focus, viền thẻ card khi hover. |
| **Màu chữ chính (Headings)** | `#0F172A` | **Slate-900** | Tiêu đề trang, số liệu tài chính/kho, text quan trọng. |
| **Màu chữ nội dung (Body text)**| `#334155` | **Slate-700** | Nhãn trường, nội dung dữ liệu bảng. |
| **Màu chữ phụ (Subtext)** | `#64748B` | **Slate-500** | Ghi chú, placeholder, đơn vị tính, thời gian tạo. |

> [!WARNING]
> **TUYỆT ĐỐI KHÔNG DÙNG MÀU TÍM HOẶC INDIGO**: Không sử dụng `#4F46E5`, `#6366F1` hay các sắc tố tím. Toàn bộ điểm nhấn nhận diện của hệ thống phải trung thành với hệ màu **Soft Azure Sky Palette** (`#0284C7`).

---

## 📐 2. QUY TẮC BO GÓC & ĐƯỜNG VIỀN SẮC NÉT (STRICT SUBTLE CORNERS)

> [!IMPORTANT]
> **ĐẶC BIỆT LƯU Ý CHO AI**: Phần mềm ERP doanh nghiệp đòi hỏi sự **nghiêm túc, sắc sảo và mật độ thông tin cao**. Tuyệt đối **KHÔNG** thiết kế bo góc tròn xoe dạng bong bóng/đồ chơi (như `border-radius: 20px`, `rounded-2xl`, `rounded-3xl` hay `rounded-full`). Phải tuân thủ nghiêm ngặt bảng giới hạn bo góc dưới đây:

| Thành Phần Giao Diện | Giới Hạn Border Radius Chuẩn | Giải Thích Thiết Kế |
|---|---|---|
| **Thẻ Card / Container chính** | `6px` đến tối đa `8px` (`rounded-md` / `rounded-lg`) | Giữ dáng vuông vắn, cứng cáp, chuyên nghiệp của phần mềm B2B. |
| **Nút bấm (Buttons)** | `4px` đến `6px` (`rounded`) | Nhỏ gọn, chắc chắn, không dùng nút bo tròn viên thuốc. |
| **Ô nhập liệu (Input, Select, Number)** | `4px` đến `6px` (`rounded`) | Tiêu chuẩn của các hệ thống SAP, Salesforce, Linear. |
| **Hộp thoại (Dialog, Modal)** | `8px` (`rounded-lg`) | Tinh tế, vừa đủ mềm mại mà không lố. |
| **Nhãn trạng thái (Badges / Tags)** | `4px` (`rounded-sm` / `rounded`) | Dạng tag hình chữ nhật sắc sảo, **CẤM bo tròn con nhộng (`rounded-full`)**. |
| **Bảng dữ liệu (DataTable)** | `4px` ở góc ngoài, cell bên trong `0px` | Tối ưu không gian hiển thị tối đa các cột dữ liệu. |
| **Độ dày đường viền (Border Width)**| **Cố định 1px (Hairline)** | Luôn dùng `1px solid #E0F2FE`, tuyệt đối không dùng viền dày 2px-3px thô kệch. |

---

## ✨ 3. CÔNG THỨC GRADIENTS & RADIAN BẮT BUỘC

Mọi AI Agent khi viết CSS/Component phải sử dụng đúng 4 công thức Gradient chuẩn sau:

```css
/* 1. Nền trang (Quầng sáng Radian xanh nhẹ tỏa ở góc trên xuống nền trắng) */
--gradient-page-bg: radial-gradient(circle at top left, rgba(224, 242, 254, 0.6) 0%, rgba(240, 249, 255, 0.2) 40%, #FFFFFF 75%);

/* 2. Thẻ Card ERP (Gradient chuyển từ Xanh nhạt sang Trắng tinh khiết) */
--gradient-card: linear-gradient(145deg, #F0F9FF 0%, #FFFFFF 65%);

/* 3. Nút bấm chính & Brand Logo Icon */
--gradient-primary: linear-gradient(135deg, #0284C7 0%, #38BDF8 100%);
--gradient-primary-hover: linear-gradient(135deg, #0369A1 0%, #0284C7 100%);

/* 4. Soft Azure Badge (Góc bo sắc nét 4px) */
--gradient-badge: linear-gradient(135deg, #F0F9FF 0%, #E0F2FE 100%);

/* 5. Hiệu ứng đổ bóng ánh xanh dịu mắt (Elevation) */
--shadow-azure: 0 4px 20px -2px rgba(2, 132, 199, 0.07), 0 2px 6px -1px rgba(2, 132, 199, 0.04);
--shadow-button: 0 2px 8px rgba(2, 132, 199, 0.24);
```

---

## 🏷️ 4. BẢNG MÀU TRẠNG THÁI NGHIỆP VỤ (STATUS BADGES)

Trong hệ thống ERP phân tán, trạng thái của Đơn hàng, Kho và Saga phải dùng các cặp màu nhã nhặn, viền mỏng và **bo góc 4px**:

| Nghiệp Vụ | Trạng Thái | Background | Text Color | Border | Border-Radius |
|---|---|---|---|---|---|
| **Thành công / Hoạt động** | `CONFIRMED`, `ACTIVE`, `ONLINE` | `#ECFDF5` | `#047857` | `1px solid #A7F3D0` | `4px` |
| **Cảnh báo / Chờ giữ kho** | `PENDING_RESERVATION`, `WARNING` | `#FFFBEB` | `#B45309` | `1px solid #FDE68A` | `4px` |
| **Thất bại / Hết tồn kho** | `REJECTED_OUT_OF_STOCK`, `OFFLINE`| `#FEF2F2` | `#B91C1C` | `1px solid #FECDD3` | `4px` |
| **Trạng thái thông thường** | `CUSTOMER`, `PCS`, `DRAFT` | `#F0F9FF` | `#0369A1` | `1px solid #BAE6FD` | `4px` |

---

## 🧩 5. QUY CHUẨN SỬ DỤNG COMPONENT CHÍNH THỨC CỦA PRIMEVUE 4

> [!IMPORTANT]
> **Ưu tiên hàng đầu**: Phải tận dụng toàn bộ các component chuẩn có sẵn của PrimeVue 4 thay vì viết các thẻ HTML nguyên thủy (`<select>`, `<input type="checkbox">`, `<ul>/<li>` điều hướng thủ công).

| Phân Loại | Component PrimeVue 4 Bắt Buộc | Thay Thế Cho | Lưu Ý Định Dạng & Styling |
|---|---|---|---|
| **Điều hướng Sidebar** | `primevue/menu` (`<Menu :model="..." />`) | Thẻ `<nav>`, `<button>` thủ công | Dùng `#submenulabel` và `#item`, bo góc 6px, nền trong suốt. |
| **Thẻ hiển thị chỉ số** | `primevue/card` (`<Card>`) | `<div class="card">` thủ công | Thẻ cố định, không hover nhảy, padding body/content là 0. |
| **Chọn danh mục/kho** | `primevue/select` (`<Select>`) | `<select>` HTML thô | Bản PrimeVue 4 dùng `Select` (thay thế `Dropdown` của v3). |
| **Hộp chọn lọc** | `primevue/checkbox` (`<Checkbox>`) | `<input type="checkbox">` | Kích thước vừa vặn, viền mỏng `border-azure`. |
| **Nhãn trạng thái** | `primevue/tag` (`<Tag>`) | `<span class="badge">` | Định dạng hình chữ nhật bo góc **4px**, cấm capsule. |
| **Tab chuyển đổi** | `primevue/tabs`, `primevue/tablist`, `primevue/tab`, `primevue/tabpanels`, `primevue/tabpanel` | Tab tự chế bằng button | Cấu trúc chuẩn của PrimeVue 4. |
| **Hộp thoại tương tác** | `primevue/dialog` (`<Dialog>`) | Modal custom | Nền trắng, bo góc 8px, nút đóng tinh giản. |
| **Bảng dữ liệu** | `primevue/datatable` & `primevue/column` | Bảng HTML `<table>` | Phân trang, lọc, căn phải số liệu có `font-mono`. |

---

## 🗂️ 6. QUY CHUẨN THANH ĐIỀU HƯỚNG SIDEBAR & MENU

### 6.1. Font Chữ Ở Menu: Cơ Bản & Tuyệt Đối Không In Đậm
- **Font-family**: Dùng font chữ văn bản cơ bản tiêu chuẩn hệ thống (`var(--font-body)` hoặc `Inter, system-ui, sans-serif`).
- **Font-weight**: **Toàn bộ chữ ở Menu là `font-weight: 400 !important;` (chữ thường, KHÔNG in đậm)**.
  - Tiêu đề danh mục (`.menu-category`): `font-weight: 400`, chữ hoa nhỏ (11px), màu slate-400.
  - Tên các phân hệ (`.menu-item`): `font-weight: 400`, cỡ 13px.
  - Khi mục đang được chọn (`.menu-item.active`): **Vẫn giữ `font-weight: 400`**, nhận diện bằng nền Soft Azure (`#F0F9FF`), viền xanh mỏng và vạch chỉ báo xanh dương bên trái (`border-left` indicator), **không làm đậm chữ**.

### 6.2. Bảo Vệ Tuyệt Đối Font-Family Của Biểu Tượng (Icon Isolation)
- Khi áp dụng font chữ cơ bản cho menu, **TUYỆT ĐỐI KHÔNG** được để selector chung đè lên font của biểu tượng icon:
```css
/* ĐÚNG: Cô lập rõ ràng nhãn chữ và icon */
.menu-item span,
.azure-prime-menu span:not(.pi) {
  font-family: var(--font-body) !important;
  font-weight: 400 !important;
}

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

### 6.3. Tính Năng Thụt Ra Thụt Vào (Collapsible Sidebar)
- Thanh menu bên trái hỗ trợ 2 trạng thái: **Mở rộng (260px)** và **Thu gọn (68px)**.
- **Kích hoạt toggle**: Người dùng click chuột trực tiếp vào khối thương hiệu đầu menu (`.brand-box`).
- **Khi thu gọn (`isSidebarCollapsed = true`)**:
  - Chuyển động mượt mà bằng CSS: `transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1)`.
  - Icon logo chuyển trạng thái hiển thị (ví dụ: `pi-bars` khi thu gọn để báo hiệu mở rộng).
  - Tiêu đề phân nhóm chuyển thành đường kẻ mảnh căn giữa (`.menu-category-divider`).
  - Các mục menu chuyển thành các ô vuông **`44px x 44px`** căn giữa, icon kích thước **`20px`** sắc nét, rõ ràng.
  - Tích hợp `v-tooltip.right` của PrimeVue: khi rê chuột vào icon ở trạng thái thu gọn, hiển thị popover tên phân hệ.
  - Khung nội dung chính (`.layout-main`) co giãn đồng bộ (`margin-left: 68px`).
  - Lưu trạng thái vào `localStorage` (`smart_erp_sidebar_collapsed`) để duy trì sau khi F5.

---

## 📊 7. QUY CHUẨN THẺ THỐNG KÊ (CARDS) & ỔN ĐỊNH GIAO DIỆN

### 7.1. Cấm Hoàn Toàn Hiệu Ứng Nhảy Rung Lắc (No Hover Bounce / Jump)
- Đối với phần mềm ERP quản trị kinh doanh, việc các thẻ card KPI hay bảng biểu nảy lên thụt xuống khi rê chuột gây mất tập trung và mỏi mắt.
- **TUYỆT ĐỐI CẤM** dùng `transform: translateY(-2px)` hoặc `transform: translateY(-4px)` khi `:hover` trên các thẻ Card và Persona.
- **Hover an toàn, tinh tế**: Chỉ thay đổi nhẹ màu viền (`border-color: #BAE6FD`) hoặc nền xanh mờ (`background: #F0F9FF`), thẻ luôn đứng yên vững chãi.

### 7.2. Định Dạng Số Liệu Khả Dụng & Tiền Tệ
- Toàn bộ số liệu tồn kho, số tiền, mã SKU phải áp dụng font `num-tabular` hoặc `font-mono` (`JetBrains Mono`, `tabular-nums`) để các chữ số có bề ngang bằng nhau, giúp bảng cân đối và thẻ KPI không bị lệch hàng.

---

## ⚡ 8. NGUYÊN TẮC LẬP TRÌNH VUE 3 (COMPOSITION API)

Mọi component sinh ra bắt buộc tuân theo các tiêu chuẩn kỹ thuật sau:

### 8.1. Cấu Trúc File `.vue` Bắt Buộc
* Sử dụng `<script setup>` 100%.
* Thứ tự chuẩn trong file: `<template>` $\rightarrow$ `<script setup>` $\rightarrow$ `<style scoped>`.

```vue
<template>
  <div class="azure-card">
    <div class="card-header">
      <div class="brand-icon">📦</div>
      <h3>{{ title }}</h3>
    </div>
    ...
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'

// 1. Props & Emits
const props = defineProps({
  warehouseId: { type: Number, required: true }
})

// 2. Reactive State (Dùng ref cho mảng/primitive, reactive cho forms)
const loading = ref(false)
const stocks = ref([])

// 3. Computed Properties (Quyền hạn & Dữ liệu lọc)
const canReserve = computed(() => {
  return currentUser.value?.permissions?.includes('inventory.reserve')
})

// 4. Methods & Helpers
const formatVND = (val) => {
  if (!val && val !== 0) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val)
}
</script>

<style scoped>
.azure-card {
  background: var(--gradient-card);
  border: 1px solid var(--border-azure);
  border-radius: 8px; /* TỐI ĐA 8px */
  padding: 20px;
}
</style>
```

### 8.2. Bảo Vệ Giao Diện Bằng Phân Quyền Động (Stateless RBAC)
Tuyệt đối không để lộ các nút hành động nhạy cảm nếu tài khoản hiện tại không có quyền tương ứng trong JWT Token:

```vue
<!-- Nút tạo đơn chỉ hiển thị khi có quyền sales.create -->
<Button 
  v-if="currentUser?.permissions?.includes('sales.create')"
  label="Tạo Đơn Hàng Mới" 
  icon="pi pi-plus" 
  class="btn-azure-primary" 
  @click="openCreateModal" 
/>
<span v-else class="text-xs text-muted flex items-center gap-1">
  <i class="pi pi-lock text-slate-400"></i> Chỉ đọc (Thiếu quyền sales.create)
</span>
```

### 8.3. Gọi API Qua Gateway & Chuẩn Hóa Header
* Mọi API call từ Vue 3 phải gọi qua đường dẫn API Gateway: `/api/v1/{service}/...`
* Bắt buộc đính kèm JWT Bearer Token và `X-Correlation-ID`:
```javascript
const fetchProducts = async () => {
  try {
    const res = await fetch('/api/v1/catalog/products', {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${localStorage.getItem('smart_erp_token')}`,
        'X-Correlation-ID': crypto.randomUUID()
      }
    })
    const envelope = await res.json()
    if (envelope.success) {
      products.value = envelope.data
    }
  } catch (error) {
    console.error('Lỗi khi tải danh mục sản phẩm:', error)
  }
}
```

---

## 🚫 9. CÁC ĐIỀU CẤM KỴ KHI CODE UI (ZERO TOLERANCE)

1. **CẤM BO GÓC TRÒN QUÁ ĐÀ (NO EXCESSIVE ROUNDED CORNERS)**:
   - Nghiêm cấm dùng `border-radius: 16px`, `20px`, `24px` hoặc `rounded-2xl`, `rounded-3xl`, `rounded-full` cho card và button. Giữ border-radius trong khoảng `4px - 8px`.
2. **CẤM DÙNG BORDER DÀY THÔ KỆCH**:
   - Chỉ dùng viền mỏng hairline `1px solid #E0F2FE`. Cấm `2px`, `3px`.
3. **CẤM DÙNG MOCK RỖNG HOẶC PLACEHOLDER CHỮ VÔ NGHĨA**:
   - Không render bảng trắng trơn không có dữ liệu mẫu.
   - Luôn tạo 5-10 dòng dữ liệu mẫu tiếng Việt chuẩn ngành SCM/phân phối (MacBook, iPhone, Nhà cung cấp FPT, DigiWorld...).
4. **CẤM DÙNG DARK MODE NẶNG NỀ CHO BẢNG BIỂU ERP**:
   - Hệ thống được thiết kế theo trường phái Clean White Canvas để hiển thị mật độ thông tin cao nhất.
5. **CẤM HOVER NHẢY RUNG LẮC TRÊN THẺ CARD (`transform: translateY`)**:
   - Giữ thẻ cố định, chỉ hover viền/nền nhẹ.
6. **CẤM DÙNG CHỮ IN ĐẬM VÀ FONT KHÔNG PHÙ HỢP TRÊN THANH MENU**:
   - Dùng font văn bản cơ bản không in đậm (`font-weight: 400`).
7. **CẤM ĐÈ FONT CHỮ LÀM MẤT HOẶC BIẾN DẠNG ICON PRIMEICONS**:
   - Luôn bảo vệ font `primeicons` cho các class `.pi`, `.menu-icon`.
8. **CẤM HARDCODE ĐƯỜNG DẪN HOẶC MÃ MÀU LẺ TẺ**:
   - Sử dụng đúng các biến CSS `:root` hoặc PrimeVue preset tokens đã quy định ở Mục 1.

