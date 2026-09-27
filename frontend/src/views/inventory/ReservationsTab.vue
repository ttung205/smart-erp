<template>
  <div class="flex flex-col">
    <!-- Header Tiêu Đề Tab -->
    <div class="flex items-center justify-between flex-wrap gap-4 mb-4">
      <div>
        <h4 class="text-sm font-bold text-slate-800 m-0">
          Danh Sách Đơn Giữ Hàng 
        </h4>
      </div>

      <!-- Thẻ tóm tắt nhanh -->
      <div class="flex items-center gap-3">
        <span class="text-xs px-3 py-1.5 bg-amber-50 text-amber-800 rounded-[6px] border border-amber-200 font-mono">
          Đang giữ: <strong>{{ formatNumber(kpiSummary.totalReserved) }}</strong> chiếc
        </span>
        <Button 
          v-if="canReserve"
          label="Khóa Giữ Hàng Mới (SO)" 
          icon="pi pi-lock"
          size="small" 
          outlined 
          class="!border-sky-300 !text-sky-700 hover:!bg-sky-50 !rounded-[6px]" 
          @click="emit('open-reserve')" 
        />
      </div>
    </div>

    <!-- Toolbar Lọc Giữ Hàng -->
    <div class="flex items-center justify-between flex-wrap gap-3 bg-slate-50 p-3 rounded-[6px] border border-slate-200 mb-6">
      <div class="flex items-center flex-wrap gap-2.5">
        <InputText 
          v-model="reservationFilters.search" 
          placeholder="Tìm theo SKU, tên sản phẩm hoặc kho..." 
          size="small"
          class="w-64 bg-white !rounded-[6px]" 
        />

        <Select 
          v-model="reservationFilters.warehouseId" 
          :options="warehouseFilterOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Chọn kho bãi" 
          size="small"
          class="min-w-[170px] !rounded-[6px]" 
        />

        <Button 
          v-if="reservationFilters.search || reservationFilters.warehouseId"
          label="Xóa lọc" 
          size="small" 
          text 
          severity="secondary" 
          class="!rounded-[6px]"
          @click="reservationFilters.search = ''; reservationFilters.warehouseId = null" 
        />
      </div>

      <div>
        <span class="text-xs text-slate-500 font-mono">
          {{ filteredReservations.length }} mặt hàng đang có đơn giữ
        </span>
      </div>
    </div>

    <!-- Bảng DataTable Hàng Giữ Chỗ -->
    <DataTable 
      :value="filteredReservations" 
      responsiveLayout="scroll" 
      :paginator="true" 
      :rows="10" 
      :rowsPerPageOptions="[10, 20, 50]" 
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown" 
      currentPageReportTemplate="Hiển thị {first} - {last} trên {totalRecords} mặt hàng đang giữ chỗ" 
      showGridlines 
      resizableColumns 
      columnResizeMode="expand" 
      scrollable 
      scrollHeight="520px"
    >
      <template #empty>
        <div class="py-8 px-4 text-center">
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

      <!-- Cột Tồn Thực Tế Trên Kệ -->
      <Column field="quantity_on_hand" header="Tồn Thực Tế" style="width: 130px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-sm text-slate-800">{{ formatNumber(data.quantity_on_hand) }}</span>
          <span class="text-xs text-slate-400 ml-1">{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Cột Đang Giữ Chỗ (Nổi bật) -->
      <Column field="quantity_reserved" header="Đang Giữ Chỗ (Reserved)" style="width: 170px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-sm font-bold text-amber-600">{{ formatNumber(data.quantity_reserved) }}</span>
          <span class="text-xs text-amber-500 ml-1 font-semibold">{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Cột Khả Dụng Còn Lại -->
      <Column field="available" header="Khả Dụng Còn Lại" style="width: 140px; text-align: right">
        <template #body="{ data }">
          <span class="font-mono text-sm font-bold text-emerald-700">{{ formatNumber(data.available) }}</span>
        </template>
      </Column>

      <!-- Cột Tỷ Lệ Giữ Chỗ -->
      <Column header="Tỷ Lệ Giữ" style="width: 120px; text-align: center">
        <template #body="{ data }">
          <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded-[4px] bg-amber-50 text-amber-700 border border-amber-200">
            {{ data.quantity_on_hand > 0 ? Math.round((data.quantity_reserved / data.quantity_on_hand) * 100) : 100 }}%
          </span>
        </template>
      </Column>

      <!-- Cột Hành Động Trực Tiếp -->
      <Column header="Hành Động Nghiệp Vụ" style="width: 140px; text-align: center">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <Button 
              v-if="canReserve"
              icon="pi pi-lock-open" 
              size="small" 
              text 
              severity="warn" 
              class="!w-7 !h-7 !p-0 !rounded-[6px]" 
              v-tooltip.top="'Hủy giải phóng lượng đang giữ chỗ'" 
              @click="emit('open-release', data)" 
            />
            <Button 
              v-if="canIssue"
              icon="pi pi-truck" 
              size="small" 
              text 
              severity="info" 
              class="!w-7 !h-7 !p-0 !rounded-[6px]" 
              v-tooltip.top="'Lập phiếu xuất kho cho hàng này'" 
              @click="emit('open-issue', data)" 
            />
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
