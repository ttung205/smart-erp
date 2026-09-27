<template>
  <div class="flex flex-col gap-4">
    <!-- Single Page Title -->
    <PageHeader
      title="Đối Tác B2B & Hệ Thống Kho Bãi"
      subtitle="Quản lý hồ sơ khách hàng, nhà cung cấp, hạn mức công nợ và mạng lưới trung tâm lưu kho"
    />

    <!-- Sibling Panels Grid (100% Tailwind, Zero Nested Cards) -->
    <div class="grid grid-cols-2 gap-4">
      <!-- Panel 1: Partners Table -->
      <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden flex flex-col shadow-none">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-200 bg-slate-50">
          <span class="text-sm font-bold text-slate-900">Danh sách đối tác B2B</span>
          <Tag
            severity="info"
            :value="partners.length + ' Đối tác'"
            class="text-[11px] px-2 py-0.5 rounded-[6px]"
          />
        </div>

        <DataTable
          :value="partners"
          showGridlines
          class="clean-datatable"
          responsiveLayout="scroll"
        >
          <Column field="code" header="Mã">
            <template #body="{ data }">
              <span class="font-mono text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-[4px] border border-indigo-200">{{ data.code }}</span>
            </template>
          </Column>

          <Column field="name" header="Tên Đối Tác">
            <template #body="{ data }">
              <span class="font-semibold text-slate-900">{{ data.name }}</span>
            </template>
          </Column>

          <Column field="type" header="Loại" class="text-center">
            <template #body="{ data }">
              <Tag
                :severity="data.type === 'CUSTOMER' ? 'info' : 'warn'"
                :value="data.type === 'CUSTOMER' ? 'Khách hàng' : 'Nhà cung cấp'"
                class="text-[11px] px-1.5 py-0.5 rounded-[6px]"
              />
            </template>
          </Column>

          <Column field="credit_limit" header="Hạn Mức Nợ" class="text-right">
            <template #body="{ data }">
              <span class="font-semibold text-slate-900 num-tabular">{{ formatCurrency(data.credit_limit) }}</span>
            </template>
          </Column>

          <template #empty>
            <div class="p-6 text-center text-xs text-slate-500">
              Chưa có thông tin đối tác.
            </div>
          </template>
        </DataTable>
      </div>

      <!-- Panel 2: Warehouses Table -->
      <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden flex flex-col shadow-none">
        <div class="flex justify-between items-center px-4 py-3 border-b border-slate-200 bg-slate-50">
          <span class="text-sm font-bold text-slate-900">Danh mục kho bãi & Điểm tập kết</span>
          <Tag
            severity="success"
            :value="warehouses.length + ' Kho hoạt động'"
            class="text-[11px] px-2 py-0.5 rounded-[6px]"
          />
        </div>

        <DataTable
          :value="warehouses"
          showGridlines
          class="clean-datatable"
          responsiveLayout="scroll"
        >
          <Column field="code" header="Mã Kho">
            <template #body="{ data }">
              <span class="font-mono text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-[4px] border border-indigo-200">{{ data.code }}</span>
            </template>
          </Column>

          <Column field="name" header="Tên Kho Bãi">
            <template #body="{ data }">
              <span class="font-semibold text-slate-900">{{ data.name }}</span>
            </template>
          </Column>

          <Column field="address" header="Địa Chỉ">
            <template #body="{ data }">
              <span class="text-slate-500 text-xs">{{ data.address }}</span>
            </template>
          </Column>

          <Column header="Trạng Thái" class="text-center">
            <template #body>
              <Tag severity="success" value="Hoạt động" class="text-[11px] px-1.5 py-0.5 rounded-[6px]" />
            </template>
          </Column>

          <template #empty>
            <div class="p-6 text-center text-xs text-slate-500">
              Chưa có thông tin kho bãi.
            </div>
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>

<script setup>
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import { formatCurrency } from '../../utils/format';

defineProps({
  partners: {
    type: Array,
    default: () => []
  },
  warehouses: {
    type: Array,
    default: () => []
  }
});
</script>
