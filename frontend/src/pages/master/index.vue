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

        <CommonDataTable
          :value="partners"
          :columns="partnerColumns"
          :paginator="partners.length > 8"
          :rows="8"
          emptyIcon="pi pi-users"
          emptyTitle="Chưa có thông tin đối tác."
          emptySubtitle=""
        >
          <template #col-code="{ data }">
            <span class="font-mono text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-[4px] border border-indigo-200">{{ data.code }}</span>
          </template>

          <template #col-name="{ data }">
            <span class="font-semibold text-slate-900">{{ data.name }}</span>
          </template>

          <template #col-type="{ data }">
            <div class="text-center">
              <Tag
                :severity="data.type === 'CUSTOMER' ? 'info' : 'warn'"
                :value="data.type === 'CUSTOMER' ? 'Khách hàng' : 'Nhà cung cấp'"
                class="text-[11px] px-1.5 py-0.5 rounded-[6px]"
              />
            </div>
          </template>

          <template #col-credit_limit="{ data }">
            <div class="text-right">
              <span class="font-semibold text-slate-900 num-tabular">{{ formatCurrency(data.credit_limit) }}</span>
            </div>
          </template>
        </CommonDataTable>
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

        <CommonDataTable
          :value="warehouses"
          :columns="warehouseColumns"
          :paginator="warehouses.length > 8"
          :rows="8"
          emptyIcon="pi pi-building"
          emptyTitle="Chưa có thông tin kho bãi."
          emptySubtitle=""
        >
          <template #col-code="{ data }">
            <span class="font-mono text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-[4px] border border-indigo-200">{{ data.code }}</span>
          </template>

          <template #col-name="{ data }">
            <span class="font-semibold text-slate-900">{{ data.name }}</span>
          </template>

          <template #col-address="{ data }">
            <span class="text-slate-500 text-xs">{{ data.address }}</span>
          </template>

          <template #col-status>
            <div class="text-center">
              <Tag severity="success" value="Hoạt động" class="text-[11px] px-1.5 py-0.5 rounded-[6px]" />
            </div>
          </template>
        </CommonDataTable>
      </div>
    </div>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
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

const partnerColumns = [
  { field: 'code', header: 'Mã', sortable: true },
  { field: 'name', header: 'Tên Đối Tác', sortable: true },
  { field: 'type', header: 'Loại', sortable: true, align: 'center' },
  { field: 'credit_limit', header: 'Hạn Mức Nợ', sortable: true, align: 'right' }
];

const warehouseColumns = [
  { field: 'code', header: 'Mã Kho', sortable: true },
  { field: 'name', header: 'Tên Kho Bãi', sortable: true },
  { field: 'address', header: 'Địa Chỉ', sortable: false },
  { field: 'status', header: 'Trạng Thái', sortable: false, align: 'center' }
];
</script>
