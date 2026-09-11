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

## ⚡ 5. NGUYÊN TẮC LẬP TRÌNH VUE 3 (COMPOSITION API)

Mọi component sinh ra bắt buộc tuân theo các tiêu chuẩn kỹ thuật sau:

### 5.1. Cấu Trúc File `.vue` Bắt Buộc
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

### 5.2. Bảo Vệ Giao Diện Bằng Phân Quyền Động (Stateless RBAC)
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

### 5.3. Gọi API Qua Gateway & Chuẩn Hóa Header
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

## 📐 6. QUY CHUẨN THIẾT KẾ CÁC THÀNH PHẦN (COMPONENTS)

### 6.1. Thẻ Thống Kê KPI (KPI Metric Cards)
* Luôn sử dụng font monospace hoặc `tabular-nums` cho các số liệu tài chính/tồn kho để các chữ số thẳng hàng nhau.
* Nhãn tiêu đề viết hoa nhỏ (`text-xs font-semibold uppercase tracking-wider text-slate-500`).
* Giá trị chính cỡ chữ lớn (`text-2xl font-bold text-slate-900`).
* Khối card bo góc gọn gàng: `border-radius: 8px`.

### 6.2. Bảng Dữ Liệu (PrimeVue DataTable)
* Header bảng có màu nền `var(--primary-subtle)` (`#F0F9FF`) với chữ `var(--text-main)` viền đáy `var(--border-azure)`.
* Dòng dữ liệu chẵn/lẻ hoặc hover nhẹ (`hover:bg-[#F0F9FF]/50`).
* Phân trang rõ ràng (10, 20, 50 dòng/trang).

### 6.3. Form & Dialog Nhập Liệu
* Nền Dialog là màu trắng tinh khiết (`#FFFFFF`), bo góc `8px`.
* Viền input bình thường là `#E2E8F0`, bo góc `4px` - `6px`.
* Khi focus chuyển sang viền `#BAE6FD` và đổ bóng tỏa xanh nhẹ `box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15)`.

---

## 🚫 7. CÁC ĐIỀU CẤM KỴ KHI CODE UI (ZERO TOLERANCE)

1. **CẤM BO GÓC TRÒN QUÁ ĐÀ (NO EXCESSIVE ROUNDED CORNERS)**:
   - Nghiêm cấm dùng `border-radius: 16px`, `20px`, `24px` hoặc `rounded-2xl`, `rounded-3xl`, `rounded-full` cho card và button. Giữ border-radius trong khoảng `4px - 8px`.
2. **CẤM DÙNG BORDER DÀY THÔ KỆCH**:
   - Chỉ dùng viền mỏng hairline `1px solid #E0F2FE`. Cấm `2px`, `3px`.
3. **CẤM DÙNG MOCK RỖNG HOẶC PLACEHOLDER CHỮ VÔ NGHĨA**:
   - Không render bảng trắng trơn không có dữ liệu mẫu.
   - Luôn tạo 5-10 dòng dữ liệu mẫu tiếng Việt chuẩn ngành SCM/phân phối (MacBook, iPhone, Nhà cung cấp FPT, DigiWorld...).
4. **CẤM DÙNG DARK MODE NẶNG NỀ CHO BẢNG BIỂU ERP**:
   - Hệ thống được thiết kế theo trường phái Clean White Canvas để hiển thị mật độ thông tin cao nhất.
5. **CẤM HARDCODE ĐƯỜNG DẪN HOẶC MÃ MÀU LẺ TẺ**:
   - Sử dụng đúng các biến CSS `:root` hoặc PrimeVue preset tokens đã quy định ở Mục 1.
