<template>
  <div class="flex flex-col gap-4 pb-10">
    <!-- Header Phân Hệ Kho -->
    <PageHeader
      :title="pageTitle"
      :subtitle="pageSubtitle"
    />

    <!-- Navigation Tabs Phân Hệ Kho -->
    <div class="flex items-center gap-2 border-b border-slate-200 bg-white px-4 pt-2 rounded-t-[6px]">
      <button 
        class="px-4 py-2 text-xs font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2"
        :class="currentSubTab === 'overview' 
          ? 'border-sky-600 text-sky-700 bg-sky-50/50 rounded-t-[4px]' 
          : 'border-transparent text-slate-600 hover:text-slate-900'"
        @click="switchTab('overview')"
      >
        <i class="pi pi-chart-bar"></i>
        Tổng Quan & Quản Lý Kho
      </button>

      <button 
        class="px-4 py-2 text-xs font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2"
        :class="currentSubTab === 'stocks' 
          ? 'border-sky-600 text-sky-700 bg-sky-50/50 rounded-t-[4px]' 
          : 'border-transparent text-slate-600 hover:text-slate-900'"
        @click="switchTab('stocks')"
      >
        <i class="pi pi-box"></i>
        Bảng Tồn Kho
      </button>

      <button 
        class="px-4 py-2 text-xs font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-2"
        :class="currentSubTab === 'movements' 
          ? 'border-sky-600 text-sky-700 bg-sky-50/50 rounded-t-[4px]' 
          : 'border-transparent text-slate-600 hover:text-slate-900'"
        @click="switchTab('movements')"
      >
        <i class="pi pi-history"></i>
        Lịch Sử Tồn Kho
      </button>
    </div>

    <!-- Nội Dung Thẻ Tab Tương Ứng -->
    <div class="bg-white border border-slate-200 rounded-b-[6px] p-4 shadow-sm">
      <OverviewTab v-if="currentSubTab === 'overview'" />
      <StocksTab v-else-if="currentSubTab === 'stocks'" />
      <MovementsTab v-else-if="currentSubTab === 'movements'" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '../../components/common/PageHeader.vue'
import OverviewTab from './OverviewTab.vue'
import StocksTab from './StocksTab.vue'
import MovementsTab from './MovementsTab.vue'

const route = useRoute()
const router = useRouter()

const currentSubTab = computed(() => {
  const p = route.path
  if (p.includes('/inventory/stocks')) return 'stocks'
  if (p.includes('/inventory/movements')) return 'movements'
  return 'overview'
})

const pageTitle = computed(() => {
  switch (currentSubTab.value) {
    case 'stocks': return 'Bảng Tồn Kho'
    case 'movements': return 'Lịch Sử Tồn Kho'
    default: return 'Quản Lý Kho Hàng'
  }
})

const pageSubtitle = computed(() => {
  switch (currentSubTab.value) {
    case 'stocks': return 'Theo dõi tồn kho thực tế, tồn giữ chỗ và tồn khả dụng xuất bán'
    case 'movements': return 'Nhật ký biến động nhập xuất kho thực tế và giữ chỗ khả dụng'
    default: return 'Danh mục các kho bãi và thiết lập kho phục vụ bán hàng'
  }
})

const switchTab = (tab) => {
  switch (tab) {
    case 'stocks':
      router.push('/inventory/stocks')
      break
    case 'movements':
      router.push('/inventory/movements')
      break
    default:
      router.push('/inventory/overview')
      break
  }
}
</script>
