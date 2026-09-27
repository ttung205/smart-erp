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

    <!-- PrimeVue 4 DataTable Sổ Cái Thẻ Kho -->
    <DataTable 
      :value="filteredMovements" 
      :loading="loading.movements" 
      showGridlines
      scrollable
      resizableColumns
      columnResizeMode="expand"
      paginator 
      :rows="10" 
      :rowsPerPageOptions="[10, 20, 50]"
    >
      <template #empty>
        <div class="py-8 px-4 text-center">
          <p class="text-slate-500 text-sm font-medium">Không tìm thấy bản ghi biến động thẻ kho nào phù hợp với bộ lọc.</p>
        </div>
      </template>

      <!-- Thời gian -->
      <Column field="created_at" header="Thời Điểm" style="width: 175px">
        <template #body="{ data }">
          <span class="text-xs font-mono text-slate-700">{{ formatDateTime(data.created_at) }}</span>
        </template>
      </Column>

      <!-- Mã chứng từ -->
      <Column field="reference_doc" header="Mã Chứng Từ" style="width: 160px">
        <template #body="{ data }">
          <span 
            class="text-[11px] px-1.5 py-0.5 rounded-[4px] border font-mono inline-block"
            :class="getDocTagClass(data.reference_doc)"
          >
            {{ formatReferenceDoc(data.reference_doc) }}
          </span>
        </template>
      </Column>

      <!-- Kho bãi -->
      <Column field="warehouse_name" header="Kho Bãi" style="width: 160px">
        <template #body="{ data }">
          <span class="text-xs text-slate-700 font-medium">
            {{ data.warehouse_name }}
          </span>
        </template>
      </Column>

      <!-- Sản phẩm & SKU -->
      <Column field="product_name" header="Mặt Hàng" style="width: 220px">
        <template #body="{ data }">
          <div class="text-xs font-semibold text-slate-800 leading-snug">{{ data.product_name }}</div>
          <div class="text-xs text-slate-400 font-mono mt-0.5">{{ data.sku }}</div>
        </template>
      </Column>

      <!-- Loại nghiệp vụ -->
      <Column field="movement_type" header="Loại Nghiệp Vụ" style="width: 140px">
        <template #body="{ data }">
          <span 
            class="font-semibold text-xs" 
            :class="getMovementClass(data.movement_type)"
          >
            {{ formatMovementType(data.movement_type) }}
          </span>
        </template>
      </Column>

      <!-- Độ Biến động +/- -->
      <Column field="quantity_delta" header="Biến Động" style="width: 110px; text-align: right">
        <template #body="{ data }">
          <span 
            class="font-mono font-bold text-xs px-1.5 py-0.5 rounded-[4px]" 
            :class="data.quantity_delta >= 0 ? 'text-emerald-700 bg-emerald-50' : 'text-rose-700 bg-rose-50'"
          >
            {{ data.quantity_delta > 0 ? '+' + formatNumber(data.quantity_delta) : formatNumber(data.quantity_delta) }}
          </span>
        </template>
      </Column>

      <!-- Số dư sau thao tác -->
      <Column header="Số Dư Sau Thao Tác" style="width: 150px; text-align: right">
        <template #body="{ data }">
          <div class="font-mono text-xs text-slate-800">
            Tồn: <strong>{{ formatNumber(data.balance_on_hand_after) }}</strong>
          </div>
          <div class="text-xs text-slate-400 font-mono mt-0.5">
            Giữ: {{ formatNumber(data.balance_reserved_after) }}
          </div>
        </template>
      </Column>

      <!-- Ghi chú / Nguồn gốc -->
      <Column field="notes" header="Ghi Chú / Nguồn Gốc Giao Dịch" style="width: 220px">
        <template #body="{ data }">
          <span class="text-xs text-slate-600 leading-relaxed">{{ formatNotes(data.notes) }}</span>
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Tag from 'primevue/tag'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { useInventory } from '../../composables/useInventory'

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
