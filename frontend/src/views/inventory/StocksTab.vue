<template>
  <div class="flex flex-col">
    <!-- Toolbar sử dụng 100% PrimeVue 4 Input, Select, Checkbox, Button -->
    <div class="flex items-center justify-between flex-wrap gap-3 bg-slate-50 p-3 rounded-[6px] border border-slate-200 mb-6">
      <div class="flex items-center flex-wrap gap-2.5">
        <!-- Ô Tìm kiếm tức thì không dùng icon -->
        <InputText 
          v-model="filters.search" 
          placeholder="Tìm theo SKU, tên sản phẩm hoặc kho..." 
          size="small"
          class="w-64 bg-white !rounded-[6px]" 
        />

        <!-- PrimeVue 4 Select Dropdown chọn kho bãi -->
        <Select 
          v-model="filters.warehouseId" 
          :options="warehouseFilterOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Chọn kho bãi" 
          size="small"
          class="min-w-[170px] !rounded-[6px]" 
          @change="fetchStocks" 
        />

        <!-- PrimeVue 4 Checkbox: Toggle xem hàng sắp hết -->
        <div class="flex items-center gap-2 px-2">
          <Checkbox 
            v-model="filters.lowStockOnly" 
            :binary="true" 
            inputId="lowStockFilter" 
            @change="fetchStocks" 
          />
          <label for="lowStockFilter" class="text-xs font-medium text-slate-700 cursor-pointer select-none">
            Dưới định mức Min-Stock
          </label>
        </div>

        <!-- Nút Xóa bộ lọc nếu có lọc -->
        <Button 
          v-if="filters.search || filters.warehouseId || filters.lowStockOnly"
          label="Xóa lọc" 
          size="small" 
          text 
          severity="secondary" 
          class="!rounded-[6px]"
          @click="resetStockFilters" 
        />
      </div>

      <!-- Nút Hành Động Nghiệp Vụ có phân quyền RBAC -->
      <div class="flex items-center gap-2">
        <Button 
          label="Làm mới" 
          icon="pi pi-refresh"
          size="small" 
          text 
          class="!rounded-[6px]"
          :loading="loading.stocks" 
          @click="fetchStocks" 
        />

        <Button 
          label="Điều Chuyển" 
          icon="pi pi-arrows-h"
          size="small" 
          outlined 
          class="!border-sky-300 !text-sky-700 hover:!bg-sky-50 !rounded-[6px]" 
          @click="emit('open-transfer')" 
        />

        <Button 
          v-if="canReserve"
          label="Giữ Chỗ (SO)" 
          icon="pi pi-lock"
          size="small" 
          outlined 
          class="!border-sky-300 !text-sky-700 hover:!bg-sky-50 !rounded-[6px]" 
          @click="emit('open-reserve')" 
        />

        <Button 
          v-if="canReserve"
          label="Hủy Giữ Chỗ" 
          icon="pi pi-lock-open"
          size="small" 
          outlined 
          class="!border-sky-300 !text-sky-700 hover:!bg-sky-50 !rounded-[6px]" 
          @click="emit('open-release')" 
        />

        <Button 
          v-if="canIssue"
          label="Xuất Kho" 
          icon="pi pi-truck"
          size="small" 
          outlined 
          class="!border-sky-300 !text-sky-700 hover:!bg-sky-50 !rounded-[6px]" 
          @click="emit('open-issue')" 
        />

        <Button 
          v-if="canReceive"
          label="Nhập Kho" 
          icon="pi pi-plus"
          size="small" 
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]" 
          @click="emit('open-receive')" 
        />

        <span v-if="!canReceive && !canReserve && !canIssue" class="text-xs text-slate-400">
          (Chế độ chỉ xem)
        </span>
      </div>
    </div>

    <!-- PrimeVue 4 DataTable Tồn Kho -->
    <DataTable 
      :value="filteredStocks" 
      :loading="loading.stocks" 
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
          <p class="text-slate-500 text-sm font-medium">Không tìm thấy dữ liệu tồn kho nào phù hợp với bộ lọc.</p>
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
          <div class="font-semibold text-slate-800 text-sm leading-snug">
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

      <!-- Cột Tồn Thực Tế -->
      <Column field="quantity_on_hand" header="Tồn Thực Tế" style="width: 125px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-sm font-bold text-slate-800">{{ formatNumber(data.quantity_on_hand) }}</span>
          <span class="text-xs text-slate-400 ml-1.5 font-normal">&nbsp;{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Cột Đang Giữ Chỗ -->
      <Column field="quantity_reserved" header="Đang Giữ Chỗ" style="width: 120px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-sm font-semibold text-amber-600">{{ formatNumber(data.quantity_reserved) }}</span>
          <span v-if="data.quantity_reserved > 0" class="text-xs text-amber-600 ml-1.5 font-normal">&nbsp;{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Cột Khả Dụng (Available = OnHand - Reserved) -->
      <Column field="available" header="Khả Dụng" style="width: 145px; text-align: right">
        <template #body="{ data }">
          <span 
            class="font-mono text-sm font-bold" 
            :class="data.available > 0 ? 'text-sky-700' : 'text-rose-600'"
          >
            {{ formatNumber(data.available) }}
          </span>
          <span class="text-xs text-slate-400 ml-1.5 font-normal">&nbsp;{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Cột Ngưỡng An Toàn Min-Stock -->
      <Column field="min_stock" header="Ngưỡng An Toàn" style="width: 120px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-xs text-slate-500">{{ formatNumber(data.min_stock || 0) }}</span>
        </template>
      </Column>

      <!-- Cột Trạng Thái -->
      <Column header="Trạng Thái" style="width: 135px; text-align: center">
        <template #body="{ data }">
          <span 
            class="font-semibold text-xs" 
            :class="getStockStatusClass(data.available, data.min_stock)"
          >
            {{ getStockStatusText(data.available, data.min_stock) }}
          </span>
        </template>
      </Column>

      <!-- Cột Thao Tác -->
      <Column header="Hành Động" style="width: 140px; text-align: center">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <Button 
              icon="pi pi-history" 
              size="small" 
              text 
              severity="secondary" 
              class="!w-7 !h-7 !p-0 !rounded-[6px]" 
              v-tooltip.top="'Xem lịch sử thẻ kho'" 
              @click="emit('view-movement', data)" 
            />
            <Button 
              v-if="canReserve && (data.quantity_reserved > 0)"
              icon="pi pi-lock-open" 
              size="small" 
              text 
              severity="warn" 
              class="!w-7 !h-7 !p-0 !rounded-[6px]" 
              v-tooltip.top="'Hủy giải phóng lượng đang giữ chỗ'" 
              @click="emit('open-release', data)" 
            />
            <Button 
              v-if="canReceive"
              icon="pi pi-plus" 
              size="small" 
              text 
              severity="success" 
              class="!w-7 !h-7 !p-0 !rounded-[6px]" 
              v-tooltip.top="'Nhập thêm hàng'" 
              @click="emit('quick-receive', data)" 
            />
          </div>
        </template>
      </Column>
    </DataTable>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Checkbox from 'primevue/checkbox'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import { useInventory } from '../../composables/useInventory'

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
})

const emit = defineEmits([
  'open-receive', 
  'open-reserve', 
  'open-release', 
  'open-issue', 
  'open-transfer', 
  'view-movement', 
  'quick-receive'
])

const {
  warehouses,
  filteredStocks,
  loading,
  filters,
  fetchStocks
} = useInventory()

const warehouseFilterOptions = computed(() => [
  { label: 'Tất cả kho bãi', value: null },
  ...warehouses.value.map(w => ({ label: `${w.code} - ${w.name}`, value: w.id }))
])

const canReceive = computed(() => {
  if (!props.currentUser) return true
  return props.currentUser?.permissions?.includes('inventory.receipt') || 
         props.currentUser?.permissions?.includes('inventory.receive') ||
         props.currentUser?.department === 'Ban Giám Đốc (BOD)' ||
         props.currentUser?.department === 'Kho Vận'
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

const resetStockFilters = () => {
  filters.search = ''
  filters.warehouseId = null
  filters.lowStockOnly = false
  fetchStocks()
}

const formatNumber = (num) => {
  if (num === null || num === undefined) return '0'
  return new Intl.NumberFormat('vi-VN').format(num)
}

const getStockStatusText = (available, minStock) => {
  if ((available ?? 0) <= 0) return 'Hết Hàng'
  if ((available ?? 0) <= (minStock ?? 0)) return 'Sắp Hết'
  return 'Còn Hàng'
}

const getStockStatusClass = (available, minStock) => {
  if ((available ?? 0) <= 0) return 'text-rose-600 font-bold'
  if ((available ?? 0) <= (minStock ?? 0)) return 'text-amber-600 font-semibold'
  return 'text-emerald-600 font-semibold'
}
</script>
