<template>
  <div class="reservations-tab-content">
    <!-- Header Tiêu Đề Tab -->
    <div class="card-title-row section-title-gap">
      <div>
        <h4 class="text-sm font-bold text-slate-900 m-0">
          Danh Sách Đơn Giữ Hàng 
        </h4>
      </div>

      <!-- Thẻ tóm tắt nhanh -->
      <div class="flex items-center gap-3">
        <span class="text-xs px-3 py-1.5 bg-amber-50 text-amber-800 rounded border border-amber-200 font-mono">
          Đang giữ: <strong>{{ formatNumber(kpiSummary.totalReserved) }}</strong> chiếc
        </span>
        <Button 
          v-if="canReserve"
          label="Khóa Giữ Hàng Mới (SO)" 
          icon="pi pi-lock"
          size="small" 
          outlined 
          class="btn-azure-outlined" 
          @click="emit('open-reserve')" 
        />
      </div>
    </div>

    <!-- Toolbar Lọc Giữ Hàng -->
    <div class="filter-toolbar section-block-spacing">
      <div class="toolbar-left">
        <InputText 
          v-model="reservationFilters.search" 
          placeholder="Tìm theo SKU, tên sản phẩm hoặc kho..." 
          class="p-inputtext-sm search-field-no-icon" 
        />

        <Select 
          v-model="reservationFilters.warehouseId" 
          :options="warehouseFilterOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Chọn kho bãi" 
          class="azure-select" 
        />

        <Button 
          v-if="reservationFilters.search || reservationFilters.warehouseId"
          label="Xóa lọc" 
          size="small" 
          text 
          severity="secondary" 
          @click="reservationFilters.search = ''; reservationFilters.warehouseId = null" 
        />
      </div>

      <div class="toolbar-right">
        <span class="text-xs text-slate-500 font-mono">
          {{ filteredReservations.length }} mặt hàng đang có đơn giữ
        </span>
      </div>
    </div>

    <!-- Bảng DataTable Hàng Giữ Chỗ -->
    <DataTable 
      :value="filteredReservations" 
      class="azure-datatable sheets-table" 
      responsiveLayout="scroll" 
      :paginator="true" 
      :rows="10" 
      :rowsPerPageOptions="[10, 20, 50]" 
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown" 
      currentPageReportTemplate="Hiển thị {first} - {last} trên {totalRecords} mặt hàng đang giữ chỗ" 
      stripedRows 
      resizableColumns 
      columnResizeMode="expand" 
      scrollable 
      scrollHeight="520px"
    >
      <template #empty>
        <div class="empty-state-box">
          <p class="text-slate-600 text-sm font-semibold">Hiện không có mặt hàng nào đang bị khóa giữ chỗ.</p>
          <p class="text-slate-400 text-xs mt-1">Mọi sản phẩm trong kho đều ở trạng thái 100% Khả Dụng để bán.</p>
        </div>
      </template>

      <!-- Cột SKU -->
      <Column field="sku" header="Mã SKU" style="width: 140px">
        <template #body="{ data }">
          <span class="font-mono text-xs font-semibold text-slate-800">{{ data.sku || ('SKU-' + data.product_id) }}</span>
        </template>
      </Column>

      <!-- Cột Tên Sản Phẩm -->
      <Column field="product_name" header="Tên Mặt Hàng" style="width: 240px">
        <template #body="{ data }">
          <div class="font-semibold text-slate-900 text-sm leading-snug">
            {{ data.product_name || ('Sản phẩm #' + data.product_id) }}
          </div>
          <div class="text-xs text-slate-400 font-mono mt-0.5">Mã ID: #{{ data.product_id }}</div>
        </template>
      </Column>

      <!-- Cột Kho Lưu Trữ -->
      <Column field="warehouse_name" header="Kho Bãi" style="width: 170px">
        <template #body="{ data }">
          <span class="text-xs font-medium text-slate-700">
            {{ data.warehouse_name || ('Kho #' + data.warehouse_id) }}
          </span>
        </template>
      </Column>

      <!-- Cột Tồn Thực Tế Trên Kệ -->
      <Column field="quantity_on_hand" header="Tồn Thực Tế" style="width: 130px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-sm text-slate-800 num-tabular">{{ formatNumber(data.quantity_on_hand) }}</span>
          <span class="text-xs text-slate-400 ml-1">{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Cột Đang Giữ Chỗ (Nổi bật) -->
      <Column field="quantity_reserved" header="Đang Giữ Chỗ (Reserved)" style="width: 170px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-sm font-bold text-amber-600 num-tabular">{{ formatNumber(data.quantity_reserved) }}</span>
          <span class="text-xs text-amber-500 ml-1 font-semibold">{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Cột Khả Dụng Còn Lại -->
      <Column field="available" header="Khả Dụng Còn Lại" style="width: 140px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-sm font-bold text-emerald-700 num-tabular">{{ formatNumber(data.available) }}</span>
        </template>
      </Column>

      <!-- Cột Tỷ Lệ Giữ Chỗ -->
      <Column header="Tỷ Lệ Giữ" style="width: 120px; text-align: center">
        <template #body="{ data }">
          <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-200">
            {{ data.quantity_on_hand > 0 ? Math.round((data.quantity_reserved / data.quantity_on_hand) * 100) : 100 }}%
          </span>
        </template>
      </Column>

      <!-- Cột Hành Động Trực Tiếp -->
      <Column header="Hành Động Nghiệp Vụ" style="width: 180px; text-align: center">
        <template #body="{ data }">
          <div class="table-action-group">
            <button 
              v-if="canReserve"
              type="button"
              class="action-pill-btn pill-amber" 
              v-tooltip.top="'Hủy giải phóng lượng đang giữ chỗ'" 
              @click="emit('open-release', data)" 
            >
              <span class="sq-icon-wrap"><i class="pi pi-lock-open"></i></span>
            </button>
            <button 
              v-if="canIssue"
              type="button"
              class="action-pill-btn pill-sky" 
              v-tooltip.top="'Lập phiếu xuất kho cho hàng này'" 
              @click="emit('open-issue', data)" 
            >
              <span class="sq-icon-wrap"><i class="pi pi-truck"></i></span>
            </button>
          </div>
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup>
import { reactive, computed } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { useInventory } from '../../composables/useInventory'

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['open-reserve', 'open-release', 'open-issue'])

const {
  stocks,
  warehouses,
  kpiSummary
} = useInventory()

const reservationFilters = reactive({
  search: '',
  warehouseId: null
})

const warehouseFilterOptions = computed(() => [
  { label: 'Tất cả kho bãi', value: null },
  ...warehouses.value.map(w => ({ label: `${w.code} - ${w.name}`, value: w.id }))
])

const filteredReservations = computed(() => {
  return stocks.value.filter(s => {
    if ((s.quantity_reserved || 0) <= 0) return false

    if (reservationFilters.warehouseId && s.warehouse_id !== reservationFilters.warehouseId) {
      return false
    }

    if (reservationFilters.search && reservationFilters.search.trim() !== '') {
      const q = reservationFilters.search.toLowerCase().trim()
      const sku = (s.sku || '').toLowerCase()
      const name = (s.product_name || '').toLowerCase()
      const wh = (s.warehouse_name || '').toLowerCase()
      return sku.includes(q) || name.includes(q) || wh.includes(q) || String(s.product_id).includes(q)
    }

    return true
  })
})

const canReserve = computed(() => {
  if (!props.currentUser) return true
  return props.currentUser?.permissions?.includes('inventory.reserve') || 
         props.currentUser?.permissions?.includes('sales.create') ||
         props.currentUser?.department === 'Ban Giám Đốc (BOD)' ||
         props.currentUser?.department === 'Phòng Sales'
})

const canIssue = computed(() => {
  if (!props.currentUser) return true
  return props.currentUser?.permissions?.includes('inventory.issue') || 
         props.currentUser?.permissions?.includes('inventory.dispatch') ||
         props.currentUser?.department === 'Ban Giám Đốc (BOD)' ||
         props.currentUser?.department === 'Kho Vận'
})

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0'
  return new Intl.NumberFormat('vi-VN').format(num)
}
</script>

<style scoped>
.reservations-tab-content {
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

.toolbar-right {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
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

.empty-state-box {
  padding: 32px 16px;
  text-align: center;
}

.btn-azure-outlined {
  color: #0284C7 !important;
  border-color: #BAE6FD !important;
  background: #F0F9FF !important;
}

.btn-azure-outlined:hover {
  background: #E0F2FE !important;
  border-color: #0284C7 !important;
}
</style>
