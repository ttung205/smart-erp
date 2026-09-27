<template>
  <div class="movements-tab-content">
    <!-- Header Tiêu Đề Tab -->
    <div class="card-title-row section-title-gap">
      <div>
        <h4 class="text-sm font-bold text-slate-900 m-0">
          Lịch Sử Biến Động Kho
        </h4>
      </div>
      <div class="flex items-center gap-3">
        <Tag :value="`${filteredMovements.length} / ${movements.length} Bản Ghi Sổ Cái`" severity="info" class="azure-tag font-mono" />
        <Button 
          label="Làm mới" 
          icon="pi pi-refresh"
          size="small" 
          text 
          v-tooltip.top="'Tải lại nhật ký biến động'" 
          :loading="loading.movements" 
          @click="fetchMovements" 
        />
      </div>
    </div>

    <!-- Toolbar lọc riêng cho Sổ Cái Thẻ Kho -->
    <div class="filter-toolbar section-block-spacing">
      <div class="toolbar-left">
        <!-- Tìm kiếm theo mã chứng từ, SKU, sản phẩm, ghi chú -->
        <InputText 
          v-model="movementFilters.search" 
          placeholder="Tìm theo số chứng từ, SKU, ghi chú..." 
          class="p-inputtext-sm search-field-no-icon" 
        />

        <!-- Lọc theo loại nghiệp vụ -->
        <Select 
          v-model="movementFilters.type" 
          :options="movementTypeOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Loại nghiệp vụ" 
          class="azure-select" 
        />

        <!-- Lọc theo kho bãi -->
        <Select 
          v-model="movementFilters.warehouseId" 
          :options="warehouseFilterOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Tất cả kho" 
          class="azure-select" 
        />

        <!-- Nút xóa bộ lọc -->
        <Button 
          v-if="movementFilters.search || movementFilters.type || movementFilters.warehouseId"
          label="Xóa lọc" 
          size="small" 
          text 
          severity="secondary" 
          @click="resetMovementFilters" 
        />
      </div>
    </div>

    <!-- PrimeVue 4 DataTable Sổ Cái Thẻ Kho (Chế độ Sheets mở rộng tự do & co giãn chữ) -->
    <DataTable 
      :value="filteredMovements" 
      :loading="loading.movements" 
      stripedRows 
      showGridlines
      scrollable
      resizableColumns
      columnResizeMode="expand"
      class="azure-datatable sheets-table"
      paginator 
      :rows="10" 
      :rowsPerPageOptions="[10, 20, 50]"
    >
      <template #empty>
        <div class="empty-state-box">
          <p class="text-slate-500 text-sm font-medium">Không tìm thấy bản ghi biến động thẻ kho nào phù hợp với bộ lọc.</p>
        </div>
      </template>

      <!-- Thời gian (UTC -> Giờ VN) -->
      <Column field="created_at" header="Thời Điểm" style="width: 175px">
        <template #body="{ data }">
          <span class="text-xs font-mono text-slate-700 num-tabular">{{ formatDateTime(data.created_at) }}</span>
        </template>
      </Column>

      <!-- Mã chứng từ -->
      <Column field="reference_doc" header="Mã Chứng Từ" style="width: 160px">
        <template #body="{ data }">
          <span 
            class="doc-code-tag font-mono"
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

      <!-- Loại nghiệp vụ (Không viền bo, màu chuẩn, bỏ viết tắt) -->
      <Column field="movement_type" header="Loại Nghiệp Vụ" style="width: 140px">
        <template #body="{ data }">
          <span 
            class="movement-type-text font-semibold text-xs" 
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
            class="delta-chip font-mono font-bold text-xs" 
            :class="data.quantity_delta >= 0 ? 'delta-positive' : 'delta-negative'"
          >
            {{ data.quantity_delta > 0 ? '+' + formatNumber(data.quantity_delta) : formatNumber(data.quantity_delta) }}
          </span>
        </template>
      </Column>

      <!-- Số dư sau thao tác (OnHand & Reserved) -->
      <Column header="Số Dư Sau Thao Tác" style="width: 150px; text-align: right">
        <template #body="{ data }">
          <div class="font-mono text-xs text-slate-800 num-tabular">
            Tồn: <strong>{{ formatNumber(data.balance_on_hand_after) }}</strong>
          </div>
          <div class="text-xs text-slate-400 font-mono num-tabular mt-0.5">
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
    case 'PO_RECEIPT': return 'mvt-receipt'
    case 'SO_RESERVE': return 'mvt-reserve'
    case 'SO_ISSUE': return 'mvt-issue'
    case 'SO_RELEASE': return 'mvt-release'
    default: return 'mvt-default'
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
  if (!doc || doc.trim().toLowerCase() === 'string') return 'doc-tag-default'
  if (doc.startsWith('PO-') || doc.startsWith('GR-')) return 'doc-tag-po'
  if (doc.startsWith('SO-')) return 'doc-tag-so'
  if (doc.startsWith('DEL-') || doc.startsWith('GD-')) return 'doc-tag-del'
  return 'doc-tag-default'
}

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0'
  return new Intl.NumberFormat('vi-VN').format(num)
}
</script>

<style scoped>
.movements-tab-content {
  display: flex;
  flex-direction: column;
}

.section-block-spacing {
  margin-bottom: 24px;
}

.section-title-gap {
  margin-bottom: 16px;
}

.card-title-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 16px;
}

.filter-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px;
  background: #F8FAFC;
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid #E2E8F0;
}

.toolbar-left {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
}

.search-field-no-icon {
  width: 260px;
  background: #FFFFFF !important;
  border: 1px solid #CBD5E1 !important;
  border-radius: 6px !important;
}

.azure-select {
  min-width: 170px;
  border-radius: 6px !important;
}

/* Biến động delta */
.delta-chip {
  padding: 2px 6px;
  border-radius: 4px;
}

.delta-positive {
  color: #047857;
  background: #ECFDF5;
}

.delta-negative {
  color: #BE123C;
  background: #FFF1F2;
}

.mvt-receipt {
  color: #047857;
}

.mvt-reserve {
  color: #B45309;
}

.mvt-issue {
  color: #0369A1;
}

.mvt-release {
  color: #475569;
}

.mvt-default {
  color: #64748B;
}

.doc-code-tag {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 4px;
  background: #F1F5F9;
  color: #475569;
  border: 1px solid #E2E8F0;
}

.doc-tag-po {
  background: #ECFDF5;
  color: #047857;
  border-color: #A7F3D0;
}

.doc-tag-so {
  background: #FFFBEB;
  color: #B45309;
  border-color: #FDE68A;
}

.doc-tag-del {
  background: #F0F9FF;
  color: #0369A1;
  border-color: #BAE6FD;
}

.empty-state-box {
  padding: 32px 16px;
  text-align: center;
}
</style>
