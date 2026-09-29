<template>
  <div class="flex flex-col gap-6" v-if="selectedWarehouse">
    <!-- Banner cảnh báo phân quyền khu vực nếu tài khoản bị gán cố định theo kho -->
    <div v-if="assignedWarehouseId" class="p-2.5 bg-sky-50/70 border border-sky-200 rounded-[6px] text-xs text-sky-900 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <i class="pi pi-shield text-sky-600"></i>
        <span>Tài khoản của bạn được phân quyền: <strong>Thủ kho khu vực phụ trách {{ selectedWarehouse.name }}</strong></span>
      </div>
      <span class="text-[11px] text-sky-700 font-mono">Quyền hạn phạm vi chi nhánh</span>
    </div>

    <!-- 2. 4 Thẻ KPI Riêng Của Kho Này -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- KPI 1: SKU Tại Kho -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Mã Hàng Tại Kho</span>
        <div class="text-2xl font-bold text-slate-800 leading-tight mb-2 font-mono">
          {{ selectedWarehouseStocks.length }}
          <span class="text-xs font-medium text-slate-500 ml-1">mã SKU</span>
        </div>
        <div class="mt-auto pt-2 border-t border-slate-100 text-xs text-slate-500">
          Chiếm <strong class="text-slate-700">{{ skuPercentage }}%</strong> danh mục toàn công ty
        </div>
      </div>

      <!-- KPI 2: Tồn Thực Tế Tại Kho -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Tồn Kệ Thực Tế</span>
        <div class="text-2xl font-bold text-emerald-600 leading-tight mb-2 font-mono">
          {{ formatNumber(selectedWarehouseOnHand) }}
          <span class="text-xs font-medium text-emerald-600 ml-1">chiếc</span>
        </div>
        <div class="mt-auto pt-2 border-t border-slate-100 text-xs text-slate-500">
          Khả dụng xuất bán: <strong class="text-slate-700">{{ formatNumber(selectedWarehouseAvailable) }}</strong> chiếc
        </div>
      </div>

      <!-- KPI 3: Khóa Giữ Chỗ Tại Kho -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col">
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Khóa Giữ Chỗ (SO)</span>
        <div class="text-2xl font-bold text-amber-600 leading-tight mb-2 font-mono">
          {{ formatNumber(selectedWarehouseReserved) }}
          <span class="text-xs font-medium text-amber-600 ml-1">chiếc</span>
        </div>
        <div class="mt-auto pt-2 border-t border-slate-100 text-xs text-slate-500">
          Tỷ lệ cam kết đơn hàng: <strong class="text-amber-700">{{ selectedWarehouseReservedRate }}%</strong>
        </div>
      </div>

      <!-- KPI 4: Cảnh Báo Chạm Min Tại Kho -->
      <div 
        class="border rounded-[6px] p-4 flex flex-col cursor-pointer transition-colors"
        :class="selectedWarehouseLowStockCount > 0 ? 'bg-rose-50/40 border-rose-200' : 'bg-white border-slate-200'"
        @click="warehouseFilterLowOnly = !warehouseFilterLowOnly"
        v-tooltip.top="'Click để lọc các mặt hàng dưới định mức trong bảng bên dưới'"
      >
        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1.5">Hàng Cần Bổ Sung (Min)</span>
        <div class="text-2xl font-bold leading-tight mb-2 font-mono" :class="selectedWarehouseLowStockCount > 0 ? 'text-rose-600' : 'text-emerald-600'">
          {{ selectedWarehouseLowStockCount }}
          <span class="text-xs font-medium ml-1" :class="selectedWarehouseLowStockCount > 0 ? 'text-rose-500' : 'text-emerald-500'">
            mặt hàng
          </span>
        </div>
        <div class="mt-auto pt-2 border-t border-slate-100 text-xs flex justify-between items-center">
          <span :class="selectedWarehouseLowStockCount > 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-semibold'">
            {{ selectedWarehouseLowStockCount > 0 ? 'Cần lập lệnh nhập hàng' : 'Đạt ngưỡng an toàn' }}
          </span>
          <span class="text-sky-600 font-medium text-[11px]">
            {{ warehouseFilterLowOnly ? 'Xem tất cả' : 'Lọc danh sách' }}
          </span>
        </div>
      </div>
    </div>

    <!-- 3. Khối Cảnh Báo Hàng Chạm/Dưới Định Mức Của Kho Này -->
    <div v-if="selectedWarehouseLowStockList.length > 0" class="bg-rose-50/30 border border-rose-200 rounded-[6px] p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <i class="pi pi-exclamation-circle text-rose-600 text-sm"></i>
          <h5 class="text-xs font-bold text-rose-900 m-0 uppercase tracking-wide">
            Mặt Hàng Chạm Hoặc Dưới Định Mức Tại {{ selectedWarehouse.name }}
          </h5>
        </div>
        <span class="text-xs text-rose-600 font-medium">{{ selectedWarehouseLowStockList.length }} sản phẩm cần tiếp tế</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
        <div 
          v-for="item in selectedWarehouseLowStockList" 
          :key="item.product_id"
          class="p-3 bg-white border border-rose-200 rounded-[6px] flex items-center justify-between text-xs"
        >
          <div>
            <div class="font-semibold text-slate-800">{{ item.product_name }}</div>
            <div class="text-[11px] text-slate-500 font-mono mt-0.5">
              {{ item.sku }} • Tồn kệ: {{ item.quantity_on_hand }} • Giữ chỗ: {{ item.quantity_reserved }}
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-right">
              <div class="font-mono font-bold text-rose-600 text-sm">{{ item.available }} / {{ item.min_stock }}</div>
              <div class="text-[10px] text-rose-500">Khả dụng / Min</div>
            </div>
            <Button 
              v-if="canReceive"
              icon="pi pi-plus" 
              size="small" 
              severity="success"
              class="!w-7 !h-7 !p-0 !rounded-[6px]" 
              v-tooltip.top="'Nhập kho mặt hàng này'" 
              @click="emit('quick-receive', item)" 
            />
          </div>
        </div>
      </div>
    </div>

    <!-- 4. Bảng Tồn Kho Nhanh Của Kho Này (Warehouse Quick Stock Table) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3">
      <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
          <h4 class="text-sm font-bold text-slate-800 m-0">Danh Sách Mặt Hàng Lưu Kho</h4>
          <p class="text-xs text-slate-500 m-0 mt-0.5">
            Hiển thị {{ filteredWarehouseStocks.length }} / {{ selectedWarehouseStocks.length }} mặt hàng trong kho này.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative">
            <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <InputText 
              v-model="warehouseSearch" 
              placeholder="Tìm mã SKU, tên mặt hàng..." 
              class="!text-xs !pl-8 !h-8 !w-64 !rounded-[6px]"
            />
          </div>
          <Button 
            v-if="warehouseFilterLowOnly"
            label="Hủy lọc Min-Stock" 
            icon="pi pi-times" 
            size="small" 
            text 
            class="!text-xs !text-rose-600 !p-1.5"
            @click="warehouseFilterLowOnly = false"
          />
        </div>
      </div>

      <!-- Bảng HTML phẳng chuẩn Clean Flat White Canvas -->
      <div class="border border-slate-200 rounded-[6px] overflow-x-auto">
        <table class="w-full text-xs text-left">
          <thead class="bg-slate-50 text-slate-600 uppercase font-semibold text-[11px] border-b border-slate-200">
            <tr>
              <th class="py-2.5 px-3">Mã SKU</th>
              <th class="py-2.5 px-3">Tên Sản Phẩm</th>
              <th class="py-2.5 px-3 text-right">Tồn Kệ</th>
              <th class="py-2.5 px-3 text-right">Giữ Chỗ</th>
              <th class="py-2.5 px-3 text-right">Khả Dụng</th>
              <th class="py-2.5 px-3 text-right">Định Mức Min</th>
              <th class="py-2.5 px-3 text-center">Trạng Thái</th>
              <th class="py-2.5 px-3 text-center">Thao Tác</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="filteredWarehouseStocks.length === 0">
              <td colspan="8" class="text-center py-6 text-slate-400">
                Không tìm thấy mặt hàng nào phù hợp với bộ lọc trong kho này.
              </td>
            </tr>
            <tr 
              v-for="s in displayedWarehouseStocks" 
              :key="s.id"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="py-2 px-3 font-mono font-bold text-sky-700">{{ s.sku }}</td>
              <td class="py-2 px-3 font-medium text-slate-800">{{ s.product_name }}</td>
              <td class="py-2 px-3 text-right font-mono font-semibold text-slate-800">{{ formatNumber(s.quantity_on_hand) }}</td>
              <td class="py-2 px-3 text-right font-mono text-amber-600">{{ formatNumber(s.quantity_reserved) }}</td>
              <td class="py-2 px-3 text-right font-mono font-bold text-emerald-700">{{ formatNumber(s.available) }}</td>
              <td class="py-2 px-3 text-right font-mono text-slate-500">{{ s.min_stock }}</td>
              <td class="py-2 px-3 text-center">
                <span 
                  class="px-2 py-0.5 rounded-[4px] text-[10px] font-semibold border"
                  :class="s.available === 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : (s.available <= s.min_stock ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200')"
                >
                  {{ s.available === 0 ? 'Hết hàng' : (s.available <= s.min_stock ? 'Chạm Min' : 'Đầy đủ') }}
                </span>
              </td>
              <td class="py-2 px-3 text-center">
                <div class="inline-flex items-center gap-1">
                  <Button 
                    v-if="canReceive"
                    icon="pi pi-plus" 
                    size="small" 
                    text 
                    class="!w-6 !h-6 !p-0 !text-sky-600 hover:!bg-sky-50 !rounded-[4px]" 
                    v-tooltip.top="'Nhập thêm hàng'" 
                    @click="emit('quick-receive', s)" 
                  />
                  <Button 
                    icon="pi pi-history" 
                    size="small" 
                    text 
                    class="!w-6 !h-6 !p-0 !text-slate-500 hover:!bg-slate-100 !rounded-[4px]" 
                    v-tooltip.top="'Xem lịch sử thẻ kho'" 
                    @click="emit('view-movement', s)" 
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="filteredWarehouseStocks.length > 10" class="flex justify-between items-center text-xs text-slate-500 pt-1">
        <span>Đang hiển thị 10 / {{ filteredWarehouseStocks.length }} mặt hàng</span>
        <Button 
          label="Xem tất cả mặt hàng trong tab Tồn Kho →" 
          text 
          size="small" 
          class="!text-xs !text-sky-600 hover:!text-sky-700 !p-0"
          @click="viewAllStocksForWarehouse(selectedWarehouse.id)"
        />
      </div>
    </div>

    <!-- 5. Biến Động Thẻ Kho Gần Đây Của Kho Này (Recent Warehouse Movements) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3">
      <div class="flex items-center justify-between">
        <div>
          <h4 class="text-sm font-bold text-slate-800 m-0">Biến Động Thẻ Kho Gần Đây Tại {{ selectedWarehouse.name }}</h4>
          <p class="text-xs text-slate-500 m-0 mt-0.5">Các giao dịch nhập, xuất, giữ chỗ phát sinh gần nhất tại kho này.</p>
        </div>
        <Button 
          label="Xem toàn bộ lịch sử thẻ kho →" 
          text 
          size="small" 
          class="!text-xs !text-sky-600 !p-0"
          @click="viewAllMovementsForWarehouse(selectedWarehouse.id)"
        />
      </div>

      <div v-if="selectedWarehouseMovements.length === 0" class="p-6 text-center text-slate-400 text-xs border border-dashed border-slate-200 rounded-[6px]">
        Chưa có biến động thẻ kho nào được ghi nhận tại địa điểm kho này.
      </div>

      <div v-else class="divide-y divide-slate-100 border border-slate-100 rounded-[6px]">
        <div 
          v-for="m in selectedWarehouseMovements" 
          :key="m.id"
          class="p-3 flex items-center justify-between text-xs hover:bg-slate-50/60 transition-colors"
        >
          <div class="flex items-center gap-3">
            <div 
              class="w-8 h-8 rounded-[6px] flex items-center justify-center font-mono font-bold text-[11px]"
              :class="m.quantity_delta > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : (m.quantity_delta < 0 ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200')"
            >
              {{ m.quantity_delta > 0 ? `+${m.quantity_delta}` : m.quantity_delta }}
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-mono font-bold text-slate-800">{{ m.reference_doc }}</span>
                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-[3px] bg-slate-100 text-slate-600 font-mono">
                  {{ m.movement_type }}
                </span>
              </div>
              <div class="text-[11px] text-slate-500 mt-0.5">
                {{ m.product_name }} ({{ m.sku }})
                <span v-if="m.notes" class="text-slate-400 ml-1">• {{ m.notes }}</span>
              </div>
            </div>
          </div>

          <div class="text-right text-[11px] text-slate-400 font-mono">
            {{ formatDate(m.created_at) }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import { useInventory } from '../../composables/useInventory'

const props = defineProps({
  warehouseId: {
    type: [Number, String],
    required: true
  },
  currentUser: {
    type: Object,
    default: null
  }
})

const emit = defineEmits([
  'back',
  'change-warehouse',
  'navigate',
  'open-receive',
  'open-transfer',
  'quick-receive',
  'view-movement'
])

const {
  stocks,
  warehouses,
  movements,
  filters,
  movementFilters
} = useInventory()

// Hỗ trợ phân quyền thủ kho khu vực
const assignedWarehouseId = computed(() => {
  return props.currentUser?.warehouse_id || props.currentUser?.assigned_warehouse_id || null
})

// Kiểm tra quyền chuyển đổi kho
const canSwitchWarehouse = computed(() => {
  if (!props.currentUser) return true
  if (['Ban Giám Đốc (BOD)', 'Quản Trị Hệ Thống'].includes(props.currentUser?.department)) return true
  if (props.currentUser?.role === 'admin' || props.currentUser?.role === 'manager') return true
  return !assignedWarehouseId.value
})

// Đối tượng kho đang chọn
const selectedWarehouse = computed(() => {
  return warehouses.value.find(w => Number(w.id) === Number(props.warehouseId)) || null
})

// Options danh sách kho
const warehouseOptions = computed(() => 
  warehouses.value.map(w => ({ label: `${w.code} - ${w.name}`, value: w.id }))
)

// Danh sách tồn kho của riêng kho đang chọn
const selectedWarehouseStocks = computed(() => {
  return stocks.value.filter(s => Number(s.warehouse_id ?? s.warehouseId) === Number(props.warehouseId))
})

// KPI của riêng kho đang chọn
const selectedWarehouseOnHand = computed(() => 
  selectedWarehouseStocks.value.reduce((acc, cur) => acc + (cur.quantity_on_hand || 0), 0)
)

const selectedWarehouseReserved = computed(() => 
  selectedWarehouseStocks.value.reduce((acc, cur) => acc + (cur.quantity_reserved || 0), 0)
)

const selectedWarehouseAvailable = computed(() => 
  selectedWarehouseStocks.value.reduce((acc, cur) => acc + (cur.available || 0), 0)
)

const selectedWarehouseReservedRate = computed(() => 
  selectedWarehouseOnHand.value > 0 ? Math.round((selectedWarehouseReserved.value / selectedWarehouseOnHand.value) * 100) : 0
)

const selectedWarehouseLowStockList = computed(() => 
  selectedWarehouseStocks.value.filter(s => (s.available || 0) <= (s.min_stock || 0))
)

const selectedWarehouseLowStockCount = computed(() => 
  selectedWarehouseLowStockList.value.length
)

const skuPercentage = computed(() => {
  const totalUnique = new Set(stocks.value.map(s => s.product_id)).size
  return totalUnique > 0 ? Math.round((selectedWarehouseStocks.value.length / totalUnique) * 100) : 100
})

// Bộ lọc và tìm kiếm trong bảng tồn kho nhanh của kho đang chọn
const warehouseSearch = ref('')
const warehouseFilterLowOnly = ref(false)

const filteredWarehouseStocks = computed(() => {
  let list = selectedWarehouseStocks.value
  if (warehouseFilterLowOnly.value) {
    list = list.filter(s => (s.available || 0) <= (s.min_stock || 0))
  }
  if (warehouseSearch.value) {
    const q = warehouseSearch.value.toLowerCase().trim()
    list = list.filter(s => 
      String(s.sku || '').toLowerCase().includes(q) ||
      String(s.product_name || '').toLowerCase().includes(q)
    )
  }
  return list
})

const displayedWarehouseStocks = computed(() => 
  filteredWarehouseStocks.value.slice(0, 10)
)

// Biến động thẻ kho của riêng kho đang chọn (6 giao dịch gần nhất)
const selectedWarehouseMovements = computed(() => {
  return movements.value
    .filter(m => Number(m.warehouse_id ?? m.warehouseId) === Number(props.warehouseId))
    .slice(0, 6)
})

// Chuyển sang Tab Tồn Kho với bộ lọc kho tương ứng
const viewAllStocksForWarehouse = (whId) => {
  filters.warehouseId = whId
  filters.lowStockOnly = false
  emit('navigate', 'stocks')
}

// Chuyển sang Tab Lịch Sử Thẻ Kho với bộ lọc kho tương ứng
const viewAllMovementsForWarehouse = (whId) => {
  movementFilters.warehouseId = whId
  movementFilters.search = ''
  movementFilters.type = null
  emit('navigate', 'movements')
}

const canReceive = computed(() => {
  if (!props.currentUser) return true
  return props.currentUser?.permissions?.includes('inventory.receipt') || 
         props.currentUser?.permissions?.includes('inventory.receive') ||
         props.currentUser?.department === 'Ban Giám Đốc (BOD)' ||
         props.currentUser?.department === 'Kho Vận'
})

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0'
  return new Intl.NumberFormat('vi-VN').format(num)
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    return d.toLocaleDateString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  } catch {
    return dateStr
  }
}
</script>
