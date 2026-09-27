<template>
  <div class="overview-tab-content">
    <!-- 1.1 Khối 4 Thẻ KPI Soft Azure -->
    <div class="kpi-grid section-block-spacing">
      <!-- KPI 1: Tổng Mã Hàng Đang Quản Lý -->
      <Card 
        class="azure-metric-card cursor-pointer" 
        @click="emit('navigate', 'stocks')" 
        v-tooltip.top="'Click để xem bảng danh mục sản phẩm'"
      >
        <template #content>
          <div class="metric-header">
            <span class="metric-label">Tổng Mã Hàng Quản Lý</span>
          </div>
          <div class="metric-value font-mono">
            {{ kpiSummary.uniqueSkus }}
            <span class="metric-unit">mã SKU</span>
          </div>
          <div class="metric-footer">
            <span class="text-xs text-slate-500">
              {{ kpiSummary.totalStockRecords }} vị trí tồn kho • {{ warehouses.length || 3 }} kho bãi
            </span>
          </div>
        </template>
      </Card>

      <!-- KPI 2: Tồn Kho Vật Lý Thực Tế (OnHand) -->
      <Card 
        class="azure-metric-card cursor-pointer" 
        @click="emit('navigate', 'stocks')" 
        v-tooltip.top="'Click để xem chi tiết tồn kho'"
      >
        <template #content>
          <div class="metric-header">
            <span class="metric-label">Tồn Thực Tế (OnHand)</span>
          </div>
          <div class="metric-value font-mono text-emerald-600">
            {{ formatNumber(kpiSummary.totalOnHand) }}
            <span class="metric-unit text-emerald-600">chiếc</span>
          </div>
          <div class="metric-footer">
            <span class="text-xs text-slate-500">
              Khả dụng xuất bán: <strong class="text-slate-700">{{ formatNumber(kpiSummary.totalAvailable) }}</strong> chiếc
            </span>
          </div>
        </template>
      </Card>

      <!-- KPI 3: Tồn Đang Giữ Chỗ Cho Đơn Bán (Reserved) -->
      <Card 
        class="azure-metric-card cursor-pointer" 
        @click="emit('navigate', 'reservations')" 
        v-tooltip.top="'Click để xem danh sách các đơn đang giữ hàng'"
      >
        <template #content>
          <div class="metric-header">
            <span class="metric-label">Đang Giữ Chỗ (Reserved)</span>
          </div>
          <div class="metric-value font-mono text-amber-600">
            {{ formatNumber(kpiSummary.totalReserved) }}
            <span class="metric-unit text-amber-600">chiếc</span>
          </div>
          <div class="metric-footer">
            <span class="text-xs text-slate-500">
              Tỉ lệ cam kết đơn SO: <strong class="text-amber-700">{{ kpiSummary.reservedPercentage }}%</strong> tổng tồn
            </span>
          </div>
        </template>
      </Card>

      <!-- KPI 4: Cảnh Báo Dưới Định Mức An Toàn (Low Stock Warning) -->
      <Card 
        class="azure-metric-card kpi-warning-interactive cursor-pointer" 
        :class="{ 
          'warning-card': kpiSummary.lowStockCount > 0,
          'is-active-filter': filters.lowStockOnly
        }"
        @click="emit('filter-low-stock')"
        v-tooltip.top="'Click để xem các mặt hàng chạm hoặc dưới Min-Stock'"
      >
        <template #content>
          <div class="metric-header">
            <span class="metric-label">Cảnh Báo Định Mức (Min-Stock)</span>
          </div>
          <div class="metric-value font-mono" :class="kpiSummary.lowStockCount > 0 ? 'text-rose-600' : 'text-emerald-600'">
            {{ kpiSummary.lowStockCount }}
            <span class="metric-unit" :class="kpiSummary.lowStockCount > 0 ? 'text-rose-500' : 'text-emerald-500'">
              mặt hàng
            </span>
          </div>
          <div class="metric-footer flex justify-between items-center">
            <span class="text-xs" :class="kpiSummary.lowStockCount > 0 ? 'text-rose-600 font-semibold' : 'text-emerald-600 font-semibold'">
              {{ kpiSummary.lowStockCount > 0 ? 'Có mặt hàng chạm hoặc dưới Min-Stock' : 'Tất cả đạt ngưỡng an toàn' }}
            </span>
            <span class="text-sky-600 font-medium text-xs">
              Xem chi tiết
            </span>
          </div>
        </template>
      </Card>
    </div>

    <!-- 1.2 Tình Hình Sức Chứa & Lưu Trữ Từng Kho Bãi -->
    <div class="section-block-spacing">
      <div class="card-title-row section-title-gap">
        <div>
          <h4 class="text-sm font-bold text-slate-900 m-0">
            Tình Hình Sức Chứa & Lưu Trữ Tại Các Kho Chi Nhánh
          </h4>
          <p class="text-xs text-slate-500 m-0 mt-1">
            Số liệu thực tế về lượng hàng trên kệ, lượng đang giữ và khả dụng theo từng địa điểm kho.
          </p>
        </div>
        <Button 
          label="Điều chuyển kho" 
          icon="pi pi-arrows-h"
          size="small" 
          outlined 
          class="btn-azure-outlined" 
          @click="emit('open-transfer')" 
        />
      </div>

      <div class="warehouse-grid">
        <div v-for="wh in warehouseSummaryList" :key="wh.id" class="warehouse-card">
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <span class="font-bold text-slate-900 text-sm">{{ wh.name }}</span>
              <span class="text-xs font-mono px-2 py-0.5 bg-sky-50 text-sky-700 rounded border border-sky-200">{{ wh.code }}</span>
            </div>
            <span v-if="wh.lowCount > 0" class="text-[11px] font-semibold text-rose-600 bg-rose-50 px-2.5 py-0.5 rounded border border-rose-200">
              {{ wh.lowCount }} hàng chạm Min
            </span>
          </div>
          <div class="text-xs text-slate-400 mb-4 truncate">{{ wh.address || 'Kho chi nhánh trực thuộc Smart ERP' }}</div>

          <div class="grid grid-cols-3 gap-3 py-3 px-3 bg-slate-50/80 rounded-lg text-xs mb-4">
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

          <div class="flex justify-between items-center text-[11px] text-slate-500 pt-2 border-t border-slate-100">
            <span>Tỷ lệ giữ chỗ (SO)</span>
            <span class="font-mono font-bold" :class="wh.reservedRate > 25 ? 'text-amber-600' : 'text-slate-700'">{{ wh.reservedRate }}%</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 1.3 Cảnh Báo Tồn Kho Sắp Hết -->
    <div class="arch-box section-block-spacing">
      <div class="flex items-center justify-between mb-4">
        <h5 class="text-xs font-bold text-slate-900 m-0 uppercase tracking-wide">
          Mặt Hàng Dưới Định Mức An Toàn (Cần Bổ Sung)
        </h5>
        <Button 
          label="Xem tất cả" 
          text 
          size="small" 
          class="text-xs p-0 text-sky-600" 
          @click="emit('filter-low-stock')" 
        />
      </div>

      <div v-if="criticalLowStockList.length === 0" class="p-4 text-center text-xs text-emerald-700 bg-emerald-50/60 rounded border border-emerald-100">
        <span>Tất cả mặt hàng đều đạt ngưỡng định mức an toàn Min-Stock.</span>
      </div>

      <div v-else class="space-y-3">
        <div 
          v-for="item in criticalLowStockList" 
          :key="`${item.warehouse_id}-${item.product_id}`" 
          class="p-3 rounded-lg border border-rose-100 bg-rose-50/40 flex items-center justify-between text-xs mb-2"
        >
          <div>
            <div class="font-semibold text-slate-900">{{ item.product_name }}</div>
            <div class="text-[11px] text-slate-500 font-mono mt-0.5">
              {{ item.sku }} • {{ item.warehouse_name }}
            </div>
          </div>
          <div class="flex items-center gap-3">
            <div class="text-right">
              <div class="font-mono font-bold text-rose-600">{{ item.available }} / {{ item.min_stock }}</div>
              <div class="text-[10px] text-rose-500">Khả dụng / Min</div>
            </div>
            <button 
              v-if="canReceive"
              type="button"
              class="action-pill-btn pill-emerald" 
              v-tooltip.top="'Nhập thêm hàng'" 
              @click="emit('quick-receive', item)" 
            >
              <span class="sq-icon-wrap"><i class="pi pi-plus"></i></span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Card from 'primevue/card'
import Button from 'primevue/button'
import { useInventory } from '../../composables/useInventory'

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['navigate', 'open-transfer', 'quick-receive', 'filter-low-stock'])

const {
  stocks,
  warehouses,
  filters,
  kpiSummary
} = useInventory()

// Thống kê phân bổ theo từng kho bãi cho Tab Tổng Quan
const warehouseSummaryList = computed(() => {
  return warehouses.value.map(w => {
    const list = stocks.value.filter(s => s.warehouse_id === w.id)
    const onHand = list.reduce((acc, cur) => acc + (cur.quantity_on_hand || 0), 0)
    const reserved = list.reduce((acc, cur) => acc + (cur.quantity_reserved || 0), 0)
    const available = list.reduce((acc, cur) => acc + (cur.available || 0), 0)
    const lowCount = list.filter(s => (s.available || 0) <= (s.min_stock || 0)).length
    return {
      ...w,
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

<style scoped>
.overview-tab-content {
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

/* Khối 4 Thẻ KPI Soft Azure */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.azure-metric-card {
  background: linear-gradient(145deg, #F0F9FF 0%, #FFFFFF 65%);
  border: 1px solid #E0F2FE;
  border-radius: 8px;
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 2px 8px rgba(2, 132, 199, 0.04);
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.azure-metric-card:hover {
  border-color: #BAE6FD;
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.08);
}

.azure-metric-card :deep(.p-card-body),
.azure-metric-card :deep(.p-card-content) {
  padding: 0 !important;
  display: flex !important;
  flex-direction: column !important;
  flex: 1 !important;
}

.metric-header {
  margin-bottom: 6px;
}

.metric-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748B;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.metric-value {
  font-size: 26px;
  font-weight: 700;
  color: #0F172A;
  line-height: 1.2;
  margin-bottom: 8px;
}

.metric-unit {
  font-size: 13px;
  font-weight: 500;
  color: #64748B;
  margin-left: 4px;
}

.metric-footer {
  margin-top: auto;
  padding-top: 8px;
  border-top: 1px solid rgba(226, 232, 240, 0.6);
  font-size: 11.5px;
}

.kpi-warning-interactive {
  transition: all 0.2s ease;
}

.kpi-warning-interactive.is-active-filter {
  border-color: #F43F5E !important;
  background: #FFF1F2 !important;
  box-shadow: 0 0 0 2px rgba(244, 63, 94, 0.2) !important;
}

.warning-card {
  border-color: #FECDD3;
  background: linear-gradient(145deg, #FFF1F2 0%, #FFFFFF 70%);
}

/* Kho Bãi Grid */
.warehouse-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 16px;
}

.warehouse-card {
  background: #FFFFFF;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  padding: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.warehouse-card:hover {
  border-color: #BAE6FD;
  box-shadow: 0 4px 12px rgba(2, 132, 199, 0.06);
}

.arch-box {
  background: #F8FAFC;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  padding: 16px 20px;
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
