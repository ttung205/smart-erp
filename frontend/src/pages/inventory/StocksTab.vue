<template>
  <div class="flex flex-col gap-4">
    <!-- Toolbar tìm kiếm và lọc kho -->
    <div class="flex items-center justify-between flex-wrap gap-3 bg-slate-50 p-3 rounded-[6px] border border-slate-200">
      <div class="flex items-center flex-wrap gap-2.5">
        <InputText 
          v-model="search" 
          placeholder="Tìm theo SKU, tên sản phẩm hoặc kho..." 
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
          @change="loadStocks" 
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

      <div class="flex items-center gap-2">
        <Button 
          label="Làm mới" 
          icon="pi pi-refresh"
          size="small" 
          text 
          class="!rounded-[6px]"
          :loading="loading" 
          @click="loadStocks" 
        />
      </div>
    </div>

    <!-- Bảng Dữ Liệu Tồn Kho -->
    <DataTable 
      :value="filteredStocks" 
      :loading="loading" 
      paginator 
      :rows="15" 
      :rowsPerPageOptions="[15, 30, 50, 100]"
      responsiveLayout="scroll" 
      class="p-datatable-sm border border-slate-200 rounded-[6px] overflow-hidden"
    >
      <template #empty>
        <div class="text-center py-8 text-slate-500 text-sm">
          Không tìm thấy dữ liệu tồn kho phù hợp.
        </div>
      </template>

      <!-- SKU -->
      <Column field="sku" header="Mã SKU" style="width: 140px">
        <template #body="{ data }">
          <span class="font-mono text-xs font-semibold text-slate-800">{{ data.sku || ('SKU-' + data.product_id) }}</span>
        </template>
      </Column>

      <!-- Tên Sản Phẩm -->
      <Column field="product_name" header="Tên Sản Phẩm" style="min-width: 220px">
        <template #body="{ data }">
          <div class="font-semibold text-slate-800 text-sm leading-snug">
            {{ data.product_name || ('Sản phẩm #' + data.product_id) }}
          </div>
          <div class="text-[11px] text-slate-400 font-mono mt-0.5">ID: #{{ data.product_id }}</div>
        </template>
      </Column>

      <!-- Kho Lưu Trữ -->
      <Column field="warehouse_name" header="Kho Lưu Trữ" style="min-width: 180px">
        <template #body="{ data }">
          <div class="flex items-center gap-1.5">
            <span class="text-xs font-medium text-slate-700">
              {{ data.warehouse_name || ('Kho #' + data.warehouse_id) }}
            </span>
            <span 
              v-if="data.can_sell" 
              class="text-[10px] px-1.5 py-0.2 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded font-semibold"
              v-tooltip.top="'Kho này được bật để bán hàng'"
            >
              Bán
            </span>
          </div>
        </template>
      </Column>

      <!-- Tồn Thực Tế -->
      <Column field="quantity_on_hand" header="Tồn Thực Tế" style="width: 130px" class="text-right">
        <template #body="{ data }">
          <span class="font-mono text-sm font-bold text-slate-800">{{ formatNumber(data.quantity_on_hand) }}</span>
          <span class="text-xs text-slate-400 ml-1 font-normal">{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Đang Giữ Chỗ (Clickable để xem đơn giữ) -->
      <Column field="quantity_reserved" header="Đang Giữ Chỗ" style="width: 140px" class="text-right">
        <template #body="{ data }">
          <div 
            v-if="data.quantity_reserved > 0"
            class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-200 rounded cursor-pointer hover:bg-amber-100 transition-colors"
            v-tooltip.top="'Nhấn vào để xem chi tiết các đơn đang giữ chỗ'"
            @click="showReservationDetails(data)"
          >
            <i class="pi pi-lock text-[11px]"></i>
            <span class="font-mono text-xs font-bold">{{ formatNumber(data.quantity_reserved) }}</span>
          </div>
          <span v-else class="font-mono text-xs text-slate-400">0</span>
        </template>
      </Column>

      <!-- Khả Dụng Tại Kho -->
      <Column field="available" header="Khả Dụng Tại Kho" style="width: 140px" class="text-right">
        <template #body="{ data }">
          <span 
            class="font-mono text-sm font-bold" 
            :class="data.available > 0 ? 'text-sky-700' : 'text-slate-400'"
          >
            {{ formatNumber(data.available) }}
          </span>
          <span class="text-xs text-slate-400 ml-1 font-normal">{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Tổng Khả Dụng Bán Hàng (Tính từ tất cả các kho được phép bán) -->
      <Column field="sales_available" header="Tồn Bán Hàng" style="width: 140px" class="text-right">
        <template #body="{ data }">
          <span 
            class="font-mono text-sm font-bold text-emerald-600"
            v-tooltip.top="'Tổng tồn khả dụng của sản phẩm này tại các kho được chọn để bán hàng'"
          >
            {{ formatNumber(data.sales_available) }}
          </span>
          <span class="text-xs text-slate-400 ml-1 font-normal">{{ data.unit || 'Chiếc' }}</span>
        </template>
      </Column>

      <!-- Thao Tác (Chỉ để lại nút Tạo đề nghị mua) -->
      <Column header="Thao Tác" style="width: 150px" class="text-center">
        <template #body="{ data }">
          <Button 
            label="Đề nghị mua" 
            icon="pi pi-file-plus" 
            size="small" 
            outlined 
            severity="info" 
            class="!text-xs !py-1 !px-2.5 !rounded-[6px]" 
            v-tooltip.top="'Tạo Đề Nghị Mua Hàng (PR) cho sản phẩm này'" 
            @click="createPurchaseRequisition(data)" 
          />
        </template>
      </Column>
    </DataTable>

    <!-- Dialog Hiển Thị Danh Sách Đơn Hàng Đang Giữ Chỗ -->
    <Dialog 
      v-model:visible="showReserveDialog" 
      :header="`Chi Tiết Đơn Hàng Giữ Chỗ: ${activeItem?.product_name || ''}`" 
      modal 
      class="w-full max-w-2xl"
    >
      <div class="py-2">
        <div class="flex items-center gap-3 mb-3 p-2.5 bg-slate-50 border border-slate-200 rounded text-xs text-slate-600">
          <div>Kho: <strong class="text-slate-800">{{ activeItem?.warehouse_name }}</strong></div>
          <div>Tổng giữ chỗ: <strong class="text-amber-700 font-mono text-sm">{{ formatNumber(activeItem?.quantity_reserved) }}</strong> chiếc</div>
        </div>

        <DataTable 
          :value="reservationOrders" 
          :loading="loadingOrders" 
          responsiveLayout="scroll" 
          class="p-datatable-sm border border-slate-200 rounded overflow-hidden"
        >
          <template #empty>
            <div class="text-center py-6 text-slate-500 text-xs">
              Chưa có thông tin đơn hàng cụ thể.
            </div>
          </template>

          <Column field="order_id" header="Mã Đơn Hàng" style="width: 150px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-sky-700">{{ data.order_id }}</span>
            </template>
          </Column>

          <Column field="quantity" header="Số Lượng Giữ" style="width: 110px" class="text-right">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-amber-700">{{ formatNumber(data.quantity) }}</span>
            </template>
          </Column>

          <Column field="notes" header="Ghi Chú" style="min-width: 180px">
            <template #body="{ data }">
              <span class="text-xs text-slate-600">{{ data.notes || 'Giữ chỗ đơn hàng' }}</span>
            </template>
          </Column>

          <Column field="created_at" header="Thời Gian Giữ" style="width: 140px">
            <template #body="{ data }">
              <span class="text-xs font-mono text-slate-500">{{ formatDateTime(data.created_at) }}</span>
            </template>
          </Column>
        </DataTable>
      </div>

      <template #footer>
        <Button label="Đóng" size="small" text severity="secondary" @click="showReserveDialog = false" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Dialog from 'primevue/dialog'
import { inventoryApi } from '../../api/inventory.api'
import { catalogApi } from '../../api/catalog.api'
import { formatNumber, formatDateTime } from '../../utils/format'

const router = useRouter()

const stocks = ref([])
const warehouses = ref([])
const catalogProducts = ref([])
const loading = ref(false)
const search = ref('')
const selectedWarehouseId = ref(null)

const showReserveDialog = ref(false)
const activeItem = ref(null)
const reservationOrders = ref([])
const loadingOrders = ref(false)

const warehouseOptions = computed(() => {
  const list = [{ label: 'Tất cả kho', value: null }]
  warehouses.value.forEach(w => {
    list.push({ label: `${w.name} (${w.code})`, value: w.id })
  })
  return list
})

const filteredStocks = computed(() => {
  let list = stocks.value || []
  if (selectedWarehouseId.value) {
    list = list.filter(s => Number(s.warehouse_id) === Number(selectedWarehouseId.value))
  }
  if (search.value) {
    const q = search.value.toLowerCase().trim()
    list = list.filter(s => {
      const sku = String(s.sku || '').toLowerCase()
      const name = String(s.product_name || '').toLowerCase()
      const wh = String(s.warehouse_name || '').toLowerCase()
      return sku.includes(q) || name.includes(q) || wh.includes(q)
    })
  }
  return list
})

const resetFilters = () => {
  search.value = ''
  selectedWarehouseId.value = null
  loadStocks()
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

const loadStocks = async () => {
  loading.value = true
  try {
    await loadCatalogProducts()
    const productMap = new Map()
    catalogProducts.value.forEach(p => productMap.set(Number(p.id), p))

    const params = { pageSize: 1000 }
    if (selectedWarehouseId.value) params.warehouseId = selectedWarehouseId.value
    const res = await inventoryApi.getStocks(params)
    const rawStocks = res.data || []

    stocks.value = rawStocks.map(s => {
      const p = productMap.get(Number(s.product_id))
      return {
        ...s,
        sku: p?.sku || `SKU-${s.product_id}`,
        product_name: p?.item_description || p?.designation || p?.name || `Sản phẩm #${s.product_id}`,
        unit: p?.unit || 'Chiếc',
        brand: p?.brand || '',
        category: p?.category || ''
      }
    })
  } catch (err) {
    console.error('Lỗi tải bảng tồn kho:', err)
  } finally {
    loading.value = false
  }
}

const showReservationDetails = async (item) => {
  activeItem.value = item
  showReserveDialog.value = true
  loadingOrders.value = true
  try {
    const res = await inventoryApi.getReservationOrders(item.product_id, item.warehouse_id)
    reservationOrders.value = res.data || []
  } catch (err) {
    console.error('Lỗi tải đơn hàng giữ chỗ:', err)
    reservationOrders.value = []
  } finally {
    loadingOrders.value = false
  }
}

const createPurchaseRequisition = (item) => {
  router.push({
    path: '/purchase/requisition/create',
    query: {
      product_id: item.product_id,
      sku: item.sku,
      product_name: item.product_name
    }
  })
}

onMounted(async () => {
  await loadWarehouses()
  await loadStocks()
})
</script>
