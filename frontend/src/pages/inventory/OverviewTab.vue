<template>
  <div class="flex flex-col gap-6">
    <!-- 1.1 Khối 4 Thẻ KPI Toàn Hệ Thống -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- KPI 1: Tổng Mã Hàng Đang Quản Lý -->
      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col hover:border-sky-300 cursor-pointer transition-colors"
        @click="emit('navigate', 'stocks')" 
        v-tooltip.top="'Click để xem bảng danh mục sản phẩm'"
      >
        <div class="mb-1.5">
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tổng Mã Hàng Quản Lý</span>
        </div>
        <div class="text-2xl font-bold text-slate-800 leading-tight mb-2 font-mono">
          {{ kpiSummary.uniqueSkus }}
          <span class="text-xs font-medium text-slate-500 ml-1">mã SKU</span>
        </div>
        <div class="mt-auto pt-2 border-t border-slate-100 text-xs text-slate-500">
          {{ kpiSummary.totalStockRecords }} vị trí tồn kho • {{ warehouses.length || 3 }} kho bãi
        </div>
      </div>

      <!-- KPI 2: Tồn Kho Vật Lý Thực Tế (OnHand) -->
      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col hover:border-sky-300 cursor-pointer transition-colors"
        @click="emit('navigate', 'stocks')" 
        v-tooltip.top="'Click để xem chi tiết tồn kho'"
      >
        <div class="mb-1.5">
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Tồn Thực Tế (OnHand)</span>
        </div>
        <div class="text-2xl font-bold text-emerald-600 leading-tight mb-2 font-mono">
          {{ formatNumber(kpiSummary.totalOnHand) }}
          <span class="text-xs font-medium text-emerald-600 ml-1">chiếc</span>
        </div>
        <div class="mt-auto pt-2 border-t border-slate-100 text-xs text-slate-500">
          Khả dụng xuất bán: <strong class="text-slate-700">{{ formatNumber(kpiSummary.totalAvailable) }}</strong> chiếc
        </div>
      </div>

      <!-- KPI 3: Tồn Đang Giữ Chỗ Cho Đơn Bán (Reserved) -->
      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col hover:border-sky-300 cursor-pointer transition-colors"
        @click="emit('navigate', 'reservations')" 
        v-tooltip.top="'Click để xem danh sách các đơn đang giữ hàng'"
      >
        <div class="mb-1.5">
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Đang Giữ Chỗ (Reserved)</span>
        </div>
        <div class="text-2xl font-bold text-amber-600 leading-tight mb-2 font-mono">
          {{ formatNumber(kpiSummary.totalReserved) }}
          <span class="text-xs font-medium text-amber-600 ml-1">chiếc</span>
        </div>
        <div class="mt-auto pt-2 border-t border-slate-100 text-xs text-slate-500">
          Tỉ lệ cam kết đơn SO: <strong class="text-amber-700">{{ kpiSummary.reservedPercentage }}%</strong> tổng tồn
        </div>
      </div>

      <!-- KPI 4: Cảnh Báo Dưới Định Mức An Toàn (Low Stock Warning) -->
      <div 
        class="border rounded-[6px] p-4 flex flex-col cursor-pointer transition-colors"
        :class="[
          kpiSummary.lowStockCount > 0 ? 'bg-rose-50/40 border-rose-200 hover:border-rose-300' : 'bg-white border-slate-200 hover:border-sky-300',
          filters.lowStockOnly ? 'ring-2 ring-rose-400' : ''
        ]"
        @click="emit('filter-low-stock')"
        v-tooltip.top="'Click để xem các mặt hàng chạm hoặc dưới Min-Stock'"
      >
        <div class="mb-1.5">
          <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Cảnh Báo Định Mức (Min-Stock)</span>
        </div>
        <div class="text-2xl font-bold leading-tight mb-2 font-mono" :class="kpiSummary.lowStockCount > 0 ? 'text-rose-600' : 'text-emerald-600'">
          {{ kpiSummary.lowStockCount }}
          <span class="text-xs font-medium ml-1" :class="kpiSummary.lowStockCount > 0 ? 'text-rose-500' : 'text-emerald-500'">
            mặt hàng
          </span>
        </div>
        <div class="mt-auto pt-2 border-t border-slate-100 text-xs flex justify-between items-center">
          <span :class="kpiSummary.lowStockCount > 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-semibold'">
            {{ kpiSummary.lowStockCount > 0 ? 'Có mặt hàng chạm Min-Stock' : 'Tất cả đạt ngưỡng an toàn' }}
          </span>
          <span class="text-sky-600 font-medium">Xem chi tiết</span>
        </div>
      </div>
    </div>

    <!-- 1.2 Tình Hình Sức Chứa & Lưu Trữ Từng Kho Bãi -->
    <div>
      <div class="flex items-center justify-between flex-wrap gap-4 mb-3">
        <div>
          <h4 class="text-sm font-bold text-slate-800 m-0">
            Tình Hình Sức Chứa & Lưu Trữ Tại Các Kho Chi Nhánh
          </h4>
          <p class="text-xs text-slate-500 m-0 mt-0.5">
            Click vào thẻ kho để mở Dashboard quản trị chuyên sâu của thủ kho khu vực.
          </p>
        </div>
        <Button 
          label="Điều chuyển kho" 
          icon="pi pi-arrows-h"
          size="small" 
          outlined 
          class="!border-sky-300 !text-sky-700 hover:!bg-sky-50 !rounded-[6px]" 
          @click="emit('open-transfer')" 
        />
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div 
          v-for="wh in warehouseSummaryList" 
          :key="wh.id" 
          class="group bg-white border border-slate-200 rounded-[6px] p-4 hover:border-sky-400 hover:shadow-md cursor-pointer transition-all flex flex-col relative"
          @click="emit('select-warehouse', wh.id)"
          v-tooltip.top="`Click để mở Dashboard quản lý kho ${wh.name}`"
        >
          <div class="flex items-center justify-between mb-2.5">
            <div class="flex items-center gap-2">
              <span class="font-bold text-slate-800 text-sm group-hover:text-sky-600 transition-colors">{{ wh.name }}</span>
              <span class="text-xs font-mono px-1.5 py-0.5 bg-sky-50 text-sky-700 rounded-[4px] border border-sky-200 font-semibold">{{ wh.code }}</span>
            </div>
            <span v-if="wh.lowCount > 0" class="text-[11px] font-semibold text-rose-600 bg-rose-50 px-2 py-0.5 rounded-[4px] border border-rose-200">
              {{ wh.lowCount }} hàng chạm Min
            </span>
          </div>
          
          <div class="text-xs text-slate-400 mb-3 truncate flex items-center gap-1">
            <i class="pi pi-map-marker text-[11px] text-slate-400"></i>
            {{ wh.address || 'Kho chi nhánh trực thuộc Smart ERP' }}
          </div>

          <div class="grid grid-cols-3 gap-2.5 py-2.5 px-3 bg-slate-50 rounded-[6px] text-xs mb-3 border border-slate-100 group-hover:bg-sky-50/40 transition-colors">
            <div>
              <div class="text-slate-500 text-[11px]">Tồn thực tế</div>
              <div class="font-mono font-bold text-slate-800 text-sm mt-0.5">{{ formatNumber(wh.onHand) }}</div>
            </div>
            <div>
              <div class="text-slate-500 text-[11px]">Đang giữ chỗ</div>
              <div class="font-mono font-semibold text-amber-600 text-sm mt-0.5">{{ formatNumber(wh.reserved) }}</div>
            </div>
            <div>
              <div class="text-slate-500 text-[11px]">Khả dụng</div>
              <div class="font-mono font-bold text-emerald-700 text-sm mt-0.5">{{ formatNumber(wh.available) }}</div>
            </div>
          </div>

          <div class="flex justify-between items-center text-[11px] text-slate-500 pt-2 border-t border-slate-100 mt-auto">
            <div>
              <span>Tỷ lệ giữ: </span>
              <span class="font-mono font-bold" :class="wh.reservedRate > 25 ? 'text-amber-600' : 'text-slate-700'">{{ wh.reservedRate }}%</span>
            </div>
            <div class="inline-flex items-center gap-1 text-sky-600 font-semibold text-xs group-hover:translate-x-1 transition-transform">
              <span>Vào Dashboard kho</span>
              <i class="pi pi-arrow-right text-[10px]"></i>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 1.3 Cảnh Báo Tồn Kho Sắp Hết Toàn Hệ Thống -->
    <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-4">
      <div class="flex items-center justify-between mb-3">
        <h5 class="text-xs font-bold text-slate-800 m-0 uppercase tracking-wide">
          Mặt Hàng Dưới Định Mức An Toàn (Toàn Hệ Thống)
        </h5>
        <Button 
          label="Xem tất cả" 
          text 
          size="small" 
          class="!text-xs !p-0 !text-sky-600" 
          @click="emit('filter-low-stock')" 
        />
      </div>

      <div v-if="criticalLowStockList.length === 0" class="p-4 text-center text-xs text-emerald-700 bg-emerald-50 rounded-[6px] border border-emerald-200">
        <span>Tất cả mặt hàng đều đạt ngưỡng định mức an toàn Min-Stock.</span>
      </div>

      <div v-else class="flex flex-col gap-2">
        <div 
          v-for="item in criticalLowStockList" 
          :key="`${item.warehouse_id}-${item.product_id}`" 
          class="p-3 rounded-[6px] border border-rose-200 bg-white flex items-center justify-between text-xs"
        >
          <div>
            <div class="font-semibold text-slate-800">{{ item.product_name }}</div>
            <div class="text-[11px] text-slate-500 font-mono mt-0.5">
              {{ item.sku }} • {{ item.warehouse_name }}
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-right">
              <div class="font-mono font-bold text-rose-600">{{ item.available }} / {{ item.min_stock }}</div>
              <div class="text-[10px] text-rose-500">Khả dụng / Min</div>
            </div>
            <Button 
              v-if="canReceive"
              icon="pi pi-plus" 
              size="small" 
              severity="success"
              class="!w-7 !h-7 !p-0 !rounded-[6px]" 
              v-tooltip.top="'Nhập thêm hàng'" 
              @click="emit('quick-receive', item)" 
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Button from 'primevue/button'
import { useInventory } from '../../composables/useInventory'

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
})

const emit = defineEmits([
  'navigate',
  'select-warehouse',
  'open-transfer',
  'quick-receive',
  'filter-low-stock'
])

const {
  stocks,
  warehouses,
  filters,
  kpiSummary
} = useInventory()

// Thống kê phân bổ theo từng kho bãi cho Tab Tổng Quan
const warehouseSummaryList = computed(() => {
  return warehouses.value.map(w => {
    const list = stocks.value.filter(s => Number(s.warehouse_id ?? s.warehouseId) === Number(w.id))
    const onHand = list.reduce((acc, cur) => acc + (cur.quantity_on_hand || 0), 0)
    const reserved = list.reduce((acc, cur) => acc + (cur.quantity_reserved || 0), 0)
    const available = list.reduce((acc, cur) => acc + (cur.available || 0), 0)
    const lowCount = list.filter(s => (s.available || 0) <= (s.min_stock || 0)).length
    return {
      ...w,
      address: w.location || w.address || 'Kho chi nhánh trực thuộc Smart ERP',
      skuCount: list.length,
      onHand,
      reserved,
      available,
      lowCount,
      reservedRate: onHand > 0 ? Math.round((reserved / onHand) * 100) : 0
    }
  })
})

// Danh sách cảnh báo hàng dưới định mức cho Tab Tổng Quan
const criticalLowStockList = computed(() => {
  return stocks.value
    .filter(s => (s.available || 0) <= (s.min_stock || 0))
    .slice(0, 5)
})

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
</script>
