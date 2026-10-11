<template>
  <div class="flex flex-col gap-4">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3 pb-2 border-b border-slate-100">
      <div>
        <h3 class="text-base font-bold text-slate-800 m-0">Lịch Sử Tồn Kho</h3>
        <p class="text-xs text-slate-500 m-0 mt-0.5">
          Theo dõi chi tiết mọi biến động nhập, xuất kho thực tế và nhật ký giữ chỗ tồn kho khả dụng.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button 
          label="Làm mới" 
          icon="pi pi-refresh" 
          size="small" 
          text 
          class="!rounded-[6px]"
          :loading="loading" 
          @click="loadCurrentTab" 
        />
      </div>
    </div>

    <!-- Thanh chuyển 2 Tab con -->
    <div class="flex items-center gap-2 border-b border-slate-200">
      <button 
        class="px-4 py-2.5 text-xs font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2"
        :class="activeSubTab === 'physical' 
          ? 'border-sky-600 text-sky-700 bg-sky-50/50' 
          : 'border-transparent text-slate-600 hover:text-slate-900'"
        @click="switchSubTab('physical')"
      >
        <i class="pi pi-box"></i>
        Lịch Sử Tồn Kho Thực Tế
      </button>

      <button 
        class="px-4 py-2.5 text-xs font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2"
        :class="activeSubTab === 'reservation' 
          ? 'border-amber-600 text-amber-700 bg-amber-50/50' 
          : 'border-transparent text-slate-600 hover:text-slate-900'"
        @click="switchSubTab('reservation')"
      >
        <i class="pi pi-lock"></i>
        Lịch Sử Tồn Kho Khả Dụng
      </button>
    </div>

    <!-- Toolbar tìm kiếm & lọc kho -->
    <div class="flex items-center justify-between flex-wrap gap-3 bg-slate-50 p-2.5 rounded-[6px] border border-slate-200">
      <div class="flex items-center flex-wrap gap-2.5">
        <InputText 
          v-model="search" 
          placeholder="Tìm theo mã chứng từ, SKU, ghi chú..." 
          size="small"
          class="w-72 bg-white !rounded-[6px]" 
        />

        <Select 
          v-model="selectedWarehouseId" 
          :options="warehouseOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Tất cả kho" 
          size="small"
          class="min-w-[180px] !rounded-[6px]" 
          @change="loadCurrentTab" 
        />

        <Button 
          v-if="search || selectedWarehouseId"
          label="Xóa lọc" 
          size="small" 
          text 
          severity="secondary" 
          class="!rounded-[6px]"
          @click="resetFilters" 
        />
      </div>
    </div>

    <!-- BẢNG 1: LỊCH SỬ TỒN KHO THỰC TẾ -->
    <CommonDataTable
      v-if="activeSubTab === 'physical'"
      :value="filteredPhysicalTxs"
      :columns="physicalColumns"
      :loading="loading"
      :rows="15"
      :rowsPerPageOptions="[15, 30, 50, 100]"
      :searchQuery="search"
      :searchFields="['document_id', 'sku', 'product_name', 'warehouse_name', 'notes']"
      defaultSortField="created_at"
      :defaultSortOrder="-1"
      emptyIcon="pi pi-box"
      emptyTitle="Chưa có giao dịch biến động tồn kho thực tế"
      emptySubtitle="Các giao dịch nhập xuất kho sẽ được ghi nhận tại đây."
    >
      <!-- Thời Gian -->
      <template #col-created_at="{ data }">
        <span class="font-mono text-xs text-slate-700">{{ formatDateTime(data.created_at) }}</span>
      </template>

      <!-- Loại Giao Dịch -->
      <template #col-document_type="{ data }">
        <Tag 
          :value="getPhysicalTypeLabel(data.document_type || data.movement_type)" 
          :severity="getPhysicalTypeSeverity(data.document_type || data.movement_type)"
          class="!text-[11px] !rounded-[4px]" 
        />
      </template>

      <!-- Mã Chứng Từ -->
      <template #col-document_id="{ data }">
        <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-200">
          {{ data.document_id || data.reference_doc }}
        </span>
      </template>

      <!-- Kho Hàng -->
      <template #col-warehouse_name="{ data }">
        <span class="text-xs font-medium text-slate-800">{{ data.warehouse_name || ('Kho #' + data.warehouse_id) }}</span>
      </template>

      <!-- Mã Nội Bộ (SKU) -->
      <template #col-sku="{ data }">
        <span class="font-mono text-xs font-semibold text-slate-800">{{ data.sku || ('SKU-' + data.product_id) }}</span>
      </template>

      <!-- Tồn Trước -->
      <template #col-balance_before="{ data }">
        <div class="text-right">
          <span class="font-mono text-sm font-bold text-slate-500">
            {{ formatNumber(data.balance_before != null ? data.balance_before : ((data.balance_after ?? data.balance_on_hand_after ?? 0) - (data.quantity_delta ?? 0))) }}
          </span>
        </div>
      </template>

      <!-- Biến Động -->
      <template #col-quantity_delta="{ data }">
        <div class="text-right">
          <span 
            class="font-mono text-sm font-bold"
            :class="data.quantity_delta > 0 ? 'text-emerald-600' : 'text-rose-600'"
          >
            {{ data.quantity_delta > 0 ? ('+' + formatNumber(data.quantity_delta)) : formatNumber(data.quantity_delta) }}
          </span>
        </div>
      </template>

      <!-- Tồn Sau -->
      <template #col-balance_after="{ data }">
        <div class="text-right">
          <span class="font-mono text-sm font-bold text-slate-800">
            {{ formatNumber(data.balance_after ?? data.balance_on_hand_after) }}
          </span>
        </div>
      </template>
    </CommonDataTable>

    <!-- BẢNG 2: LỊCH SỬ TỒN KHO KHẢ DỤNG -->
    <CommonDataTable
      v-else
      :value="filteredReservationTxs"
      :columns="reservationColumns"
      :loading="loading"
      :rows="15"
      :rowsPerPageOptions="[15, 30, 50, 100]"
      :searchQuery="search"
      :searchFields="['document_id', 'sku', 'product_name', 'warehouse_name', 'notes']"
      defaultSortField="created_at"
      :defaultSortOrder="-1"
      emptyIcon="pi pi-lock"
      emptyTitle="Chưa có giao dịch biến động tồn kho khả dụng"
      emptySubtitle="Các thao tác giữ chỗ, hủy giữ chỗ sẽ được ghi nhận tại đây."
    >
      <!-- Thời Gian -->
      <template #col-created_at="{ data }">
        <span class="font-mono text-xs text-slate-700">{{ formatDateTime(data.created_at) }}</span>
      </template>

      <!-- Loại Giao Dịch -->
      <template #col-document_type="{ data }">
        <Tag 
          :value="getReservationTypeLabel(data.document_type)" 
          :severity="getReservationTypeSeverity(data.document_type)"
          class="!text-[11px] !rounded-[4px]" 
        />
      </template>

      <!-- Mã Chứng Từ -->
      <template #col-document_id="{ data }">
        <span class="font-mono text-xs font-bold text-amber-800 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200">
          {{ data.document_id }}
        </span>
      </template>

      <!-- Kho Hàng -->
      <template #col-warehouse_name="{ data }">
        <span class="text-xs font-medium text-slate-800">{{ data.warehouse_name || ('Kho #' + data.warehouse_id) }}</span>
      </template>

      <!-- Mã Nội Bộ (SKU) -->
      <template #col-sku="{ data }">
        <span class="font-mono text-xs font-semibold text-slate-800">{{ data.sku || ('SKU-' + data.product_id) }}</span>
      </template>

      <!-- Tồn Trước -->
      <template #col-balance_before="{ data }">
        <div class="text-right">
          <span class="font-mono text-sm font-bold text-slate-500">
            {{ formatNumber(data.balance_before != null ? data.balance_before : ((data.balance_after ?? 0) - (data.quantity_delta ?? 0))) }}
          </span>
        </div>
      </template>

      <!-- Biến Động -->
      <template #col-quantity_delta="{ data }">
        <div class="text-right">
          <span 
            class="font-mono text-sm font-bold"
            :class="data.quantity_delta < 0 ? 'text-amber-600' : 'text-emerald-600'"
          >
            {{ data.quantity_delta > 0 ? ('+' + formatNumber(data.quantity_delta)) : formatNumber(data.quantity_delta) }}
          </span>
        </div>
      </template>

      <!-- Tồn Sau -->
      <template #col-balance_after="{ data }">
        <div class="text-right">
          <span class="font-mono text-sm font-bold text-emerald-700">
            {{ formatNumber(data.balance_after) }}
          </span>
        </div>
      </template>
    </CommonDataTable>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import CommonDataTable from '../../components/common/CommonDataTable.vue'
import { inventoryApi } from '../../api/inventory.api'
import { catalogApi } from '../../api/catalog.api'
import { formatNumber, formatDateTime } from '../../utils/format'

// Cột bảng lịch sử thực tế: Thời gian, Loại giao dịch, Mã chứng từ, Kho hàng, Mã nội bộ, Tồn trước, Biến động, Tồn sau
const physicalColumns = [
  { field: 'created_at', header: 'Thời Gian', width: '160px', sortable: true },
  { field: 'document_type', header: 'Loại Giao Dịch', width: '160px', sortable: true },
  { field: 'document_id', header: 'Mã Chứng Từ', width: '150px', sortable: true },
  { field: 'warehouse_name', header: 'Kho Hàng', width: '160px', sortable: true },
  { field: 'sku', header: 'Mã Nội Bộ', width: '140px', sortable: true },
  { field: 'balance_before', header: 'Tồn Trước', width: '110px', align: 'right', sortable: true },
  { field: 'quantity_delta', header: 'Biến Động', width: '110px', align: 'right', sortable: true },
  { field: 'balance_after', header: 'Tồn Sau', width: '110px', align: 'right', sortable: true }
]

// Cột bảng lịch sử khả dụng: cùng cấu trúc
const reservationColumns = [
  { field: 'created_at', header: 'Thời Gian', width: '160px', sortable: true },
  { field: 'document_type', header: 'Loại Giao Dịch', width: '160px', sortable: true },
  { field: 'document_id', header: 'Mã Chứng Từ', width: '150px', sortable: true },
  { field: 'warehouse_name', header: 'Kho Hàng', width: '160px', sortable: true },
  { field: 'sku', header: 'Mã Nội Bộ', width: '140px', sortable: true },
  { field: 'balance_before', header: 'Tồn Trước', width: '110px', align: 'right', sortable: true },
  { field: 'quantity_delta', header: 'Biến Động', width: '110px', align: 'right', sortable: true },
  { field: 'balance_after', header: 'Tồn Sau', width: '110px', align: 'right', sortable: true }
]

const activeSubTab = ref('physical') // 'physical' | 'reservation'
const loading = ref(false)
const search = ref('')
const selectedWarehouseId = ref(null)

const warehouses = ref([])
const catalogProducts = ref([])
const physicalTxs = ref([])
const reservationTxs = ref([])

const warehouseOptions = computed(() => {
  const list = [{ label: 'Tất cả kho', value: null }]
  warehouses.value.forEach(w => {
    list.push({ label: `${w.name} (${w.code})`, value: w.id })
  })
  return list
})

const filteredPhysicalTxs = computed(() => {
  let list = physicalTxs.value || []
  if (selectedWarehouseId.value) {
    list = list.filter(t => Number(t.warehouse_id) === Number(selectedWarehouseId.value))
  }
  return list
})

const filteredReservationTxs = computed(() => {
  let list = reservationTxs.value || []
  if (selectedWarehouseId.value) {
    list = list.filter(t => Number(t.warehouse_id) === Number(selectedWarehouseId.value))
  }
  return list
})

const resetFilters = () => {
  search.value = ''
  selectedWarehouseId.value = null
  loadCurrentTab()
}

const switchSubTab = (tab) => {
  activeSubTab.value = tab
  loadCurrentTab()
}

const loadCatalogProducts = async () => {
  if (catalogProducts.value.length > 0) return
  try {
    const res = await catalogApi.getProducts({ per_page: 500 })
    catalogProducts.value = res.data || (Array.isArray(res) ? res : [])
  } catch (err) {
    console.error('Lỗi tải danh mục sản phẩm từ catalog:', err)
  }
}

const loadWarehouses = async () => {
  try {
    const res = await inventoryApi.getWarehouses()
    warehouses.value = res.data || []
  } catch (err) {
    console.error('Lỗi tải danh mục kho:', err)
  }
}

const loadPhysicalTxs = async () => {
  loading.value = true
  try {
    await loadCatalogProducts()
    const productMap = new Map()
    catalogProducts.value.forEach(p => productMap.set(Number(p.id), p))

    const params = {}
    if (selectedWarehouseId.value) params.warehouseId = selectedWarehouseId.value
    const res = await inventoryApi.getPhysicalTransactions(params)
    const rawList = res.data || []

    physicalTxs.value = rawList.map(t => {
      const p = productMap.get(Number(t.product_id))
      return {
        ...t,
        product_name: p?.item_description || p?.designation || p?.name || `Sản phẩm #${t.product_id}`,
        sku: p?.sku || `SKU-${t.product_id}`
      }
    })
  } catch (err) {
    console.error('Lỗi tải giao dịch kho thực tế:', err)
  } finally {
    loading.value = false
  }
}

const loadReservationTxs = async () => {
  loading.value = true
  try {
    await loadCatalogProducts()
    const productMap = new Map()
    catalogProducts.value.forEach(p => productMap.set(Number(p.id), p))

    const params = {}
    if (selectedWarehouseId.value) params.warehouseId = selectedWarehouseId.value
    const res = await inventoryApi.getReservationTransactions(params)
    const rawList = res.data || []

    reservationTxs.value = rawList.map(t => {
      const p = productMap.get(Number(t.product_id))
      return {
        ...t,
        product_name: p?.item_description || p?.designation || p?.name || `Sản phẩm #${t.product_id}`,
        sku: p?.sku || `SKU-${t.product_id}`
      }
    })
  } catch (err) {
    console.error('Lỗi tải giao dịch kho khả dụng:', err)
  } finally {
    loading.value = false
  }
}

const loadCurrentTab = () => {
  if (activeSubTab.value === 'physical') {
    loadPhysicalTxs()
  } else {
    loadReservationTxs()
  }
}

const getPhysicalTypeLabel = (type) => {
  switch (type) {
    case 'PURCHASE_RECEIPT': return 'Nhập mua hàng'
    case 'DELIVERY_NOTE': return 'Xuất giao hàng'
    case 'TRANSFER_IN': return 'Nhập điều chuyển'
    case 'TRANSFER_OUT': return 'Xuất điều chuyển'
    case 'ADJUSTMENT': return 'Kiểm kê điều chỉnh'
    default: return type || 'Khác'
  }
}

const getPhysicalTypeSeverity = (type) => {
  switch (type) {
    case 'PURCHASE_RECEIPT': return 'success'
    case 'DELIVERY_NOTE': return 'info'
    case 'TRANSFER_IN': return 'success'
    case 'TRANSFER_OUT': return 'warn'
    case 'ADJUSTMENT': return 'secondary'
    default: return 'secondary'
  }
}

const getReservationTypeLabel = (type) => {
  switch (type) {
    case 'SALES_ORDER': return 'Giữ chỗ đơn bán'
    case 'CANCEL_ORDER': return 'Hủy đơn hoàn trả'
    case 'PURCHASE_RECEIPT': return 'Tăng từ đơn nhập'
    case 'ADJUSTMENT': return 'Điều chỉnh khả dụng'
    default: return type || 'Khác'
  }
}

const getReservationTypeSeverity = (type) => {
  switch (type) {
    case 'SALES_ORDER': return 'warn'
    case 'CANCEL_ORDER': return 'success'
    case 'PURCHASE_RECEIPT': return 'success'
    default: return 'secondary'
  }
}

onMounted(async () => {
  await loadWarehouses()
  loadCurrentTab()
})
</script>
