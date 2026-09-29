<template>
  <div class="flex flex-col">
    <!-- Header Tiêu Đề Tab -->
    <div class="flex items-center justify-between flex-wrap gap-4 mb-4">
      <div>
        <h4 class="text-sm font-bold text-slate-800 m-0">
          Lịch Sử Biến Động Kho
        </h4>
      </div>
      <div class="flex items-center gap-3">
        <Tag :value="`${filteredMovements.length} / ${movements.length} Bản Ghi Sổ Cái`" severity="info" class="font-mono !text-xs !rounded-[6px]" />
        <Button 
          label="Làm mới" 
          icon="pi pi-refresh"
          size="small" 
          text 
          class="!rounded-[6px]"
          v-tooltip.top="'Tải lại nhật ký biến động'" 
          :loading="loading.movements" 
          @click="fetchMovements" 
        />
      </div>
    </div>

    <!-- Toolbar lọc riêng cho Sổ Cái Thẻ Kho -->
    <div class="flex items-center justify-between flex-wrap gap-3 bg-slate-50 p-3 rounded-[6px] border border-slate-200 mb-6">
      <div class="flex items-center flex-wrap gap-2.5">
        <!-- Tìm kiếm theo mã chứng từ, SKU, sản phẩm, ghi chú -->
        <InputText 
          v-model="movementFilters.search" 
          placeholder="Tìm theo số chứng từ, SKU, ghi chú..." 
          size="small"
          class="w-64 bg-white !rounded-[6px]" 
        />

        <!-- Lọc theo loại nghiệp vụ -->
        <Select 
          v-model="movementFilters.type" 
          :options="movementTypeOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Loại nghiệp vụ" 
          size="small"
          class="min-w-[160px] !rounded-[6px]" 
        />

        <!-- Lọc theo kho bãi -->
        <Select 
          v-model="movementFilters.warehouseId" 
          :options="warehouseFilterOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Tất cả kho" 
          size="small"
          class="min-w-[160px] !rounded-[6px]" 
        />

        <!-- Nút xóa bộ lọc -->
        <Button 
          v-if="movementFilters.search || movementFilters.type || movementFilters.warehouseId"
          label="Xóa lọc" 
          size="small" 
          text 
          severity="secondary" 
          class="!rounded-[6px]"
          @click="resetMovementFilters" 
        />
      </div>
    </div>

    <!-- Bảng Sổ Cái Thẻ Kho Sử Dụng CommonDataTable Đồng Bộ -->
    <CommonDataTable 
      :value="filteredMovements" 
      :columns="tableColumns"
      :loading="loading.movements" 
      :rows="10"
      emptyIcon="pi pi-history"
      emptyTitle="Không tìm thấy bản ghi biến động thẻ kho"
      emptySubtitle="Không có giao dịch nhập, xuất hoặc điều chuyển nào phù hợp với bộ lọc."
    >
      <!-- Thời gian -->
      <template #col-created_at="{ data }">
        <span class="text-xs font-mono text-slate-700">{{ formatDateTime(data.created_at) }}</span>
      </template>

      <!-- Mã chứng từ -->
      <template #col-reference_doc="{ data }">
        <span 
          class="text-[11px] px-1.5 py-0.5 rounded-[4px] border font-mono inline-block"
          :class="getDocTagClass(data.reference_doc)"
        >
          {{ formatReferenceDoc(data.reference_doc) }}
        </span>
      </template>

      <!-- Kho bãi -->
      <template #col-warehouse_name="{ data }">
        <span class="text-xs text-slate-700 font-medium">
          {{ data.warehouse_name }}
        </span>
      </template>

      <!-- Sản phẩm & SKU -->
      <template #col-product_name="{ data }">
        <div class="text-xs font-semibold text-slate-800 leading-snug">{{ data.product_name }}</div>
        <div class="text-xs text-slate-400 font-mono mt-0.5">{{ data.sku }}</div>
      </template>

      <!-- Loại nghiệp vụ -->
      <template #col-movement_type="{ data }">
        <span 
          class="font-semibold text-xs" 
          :class="getMovementClass(data.movement_type)"
        >
          {{ formatMovementType(data.movement_type) }}
        </span>
      </template>

      <!-- Độ Biến động +/- -->
      <template #col-quantity_delta="{ data }">
        <div class="text-right">
          <span 
            class="font-mono font-bold text-xs px-1.5 py-0.5 rounded-[4px]" 
            :class="data.quantity_delta >= 0 ? 'text-emerald-700 bg-emerald-50' : 'text-rose-700 bg-rose-50'"
          >
            {{ data.quantity_delta > 0 ? '+' + formatNumber(data.quantity_delta) : formatNumber(data.quantity_delta) }}
          </span>
        </div>
      </template>

      <!-- Số dư sau thao tác -->
      <template #col-balance="{ data }">
        <div class="text-right">
          <div class="font-mono text-xs text-slate-800">
            Tồn: <strong>{{ formatNumber(data.balance_on_hand_after) }}</strong>
          </div>
          <div class="text-xs text-slate-400 font-mono mt-0.5">
            Giữ: {{ formatNumber(data.balance_reserved_after) }}
          </div>
        </div>
      </template>

      <!-- Ghi chú / Nguồn gốc -->
      <template #col-notes="{ data }">
        <span class="text-xs text-slate-600 leading-relaxed">{{ formatNotes(data.notes) }}</span>
      </template>
    </CommonDataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import CommonDataTable from '../../components/common/CommonDataTable.vue'
import { useInventory } from '../../composables/useInventory'

const tableColumns = [
  { field: 'created_at', header: 'Thời Điểm', width: '175px', sortable: true },
  { field: 'reference_doc', header: 'Mã Chứng Từ', width: '160px', sortable: true },
  { field: 'warehouse_name', header: 'Kho Bãi', width: '160px', sortable: true },
  { field: 'product_name', header: 'Mặt Hàng', width: '220px', sortable: true },
  { field: 'movement_type', header: 'Loại Nghiệp Vụ', width: '140px', sortable: true },
  { field: 'quantity_delta', header: 'Biến Động', width: '110px', align: 'right', sortable: true },
  { field: 'balance', header: 'Số Dư Sau Thao Tác', width: '150px', align: 'right', sortable: false },
  { field: 'notes', header: 'Ghi Chú / Nguồn Gốc Giao Dịch', width: '220px', sortable: false }
]

const {
  movements,
  warehouses,
  loading,
  movementFilters,
  filteredMovements,
  fetchMovements
} = useInventory()

const movementTypeOptions = [
  { label: 'Tất cả loại nghiệp vụ', value: null },
  { label: 'Nhập Hàng', value: 'PO_RECEIPT' },
  { label: 'Giữ Chỗ', value: 'SO_RESERVE' },
  { label: 'Xuất Kho', value: 'SO_ISSUE' },
  { label: 'Hủy Giữ Chỗ', value: 'SO_RELEASE' }
]

const warehouseFilterOptions = computed(() => [
  { label: 'Tất cả kho bãi', value: null },
  ...warehouses.value.map(w => ({ label: `${w.code} - ${w.name}`, value: w.id }))
])

const resetMovementFilters = () => {
  movementFilters.search = ''
  movementFilters.type = null
  movementFilters.warehouseId = null
}

const formatDateTime = (isoStr) => {
  if (!isoStr) return '---'
  try {
    const d = new Date(isoStr)
    return d.toLocaleString('vi-VN', {
      hour: '2-digit',
      minute: '2-digit',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    })
  } catch (e) {
    return isoStr
  }
}

const formatMovementType = (type) => {
  switch (type) {
    case 'PO_RECEIPT': return 'Nhập Hàng'
    case 'SO_RESERVE': return 'Giữ Chỗ'
    case 'SO_ISSUE': return 'Xuất Kho'
    case 'SO_RELEASE': return 'Hủy Giữ Chỗ'
    default: return type
  }
}

const getMovementClass = (type) => {
  switch (type) {
    case 'PO_RECEIPT': return 'text-emerald-700'
    case 'SO_RESERVE': return 'text-amber-700'
    case 'SO_ISSUE': return 'text-sky-700'
    case 'SO_RELEASE': return 'text-slate-600'
    default: return 'text-slate-500'
  }
}

const formatReferenceDoc = (doc) => {
  if (!doc || doc.trim() === '' || doc.trim().toLowerCase() === 'string') {
    return 'null'
  }
  return doc
}

const formatNotes = (notes) => {
  if (!notes || notes.trim() === '' || notes.trim().toLowerCase() === 'string') {
    return '---'
  }
  return notes
}

const getDocTagClass = (doc) => {
  if (!doc || doc.trim().toLowerCase() === 'string') return 'bg-slate-100 text-slate-600 border-slate-200'
  if (doc.startsWith('PO-') || doc.startsWith('GR-')) return 'bg-emerald-50 text-emerald-700 border-emerald-200'
  if (doc.startsWith('SO-')) return 'bg-amber-50 text-amber-700 border-amber-200'
  if (doc.startsWith('DEL-') || doc.startsWith('GD-')) return 'bg-sky-50 text-sky-700 border-sky-200'
  return 'bg-slate-100 text-slate-600 border-slate-200'
}

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0'
  return new Intl.NumberFormat('vi-VN').format(num)
}
</script>
