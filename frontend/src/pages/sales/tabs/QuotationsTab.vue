<template>
  <div class="space-y-3">
    <!-- Action Toolbar -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo mã báo giá hoặc tên khách hàng..."
              class="w-72 rounded-[6px]"
              size="small"
            />
          </IconField>
          <Select
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc trạng thái..."
            class="w-44 rounded-[6px]"
            size="small"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2">
          <Button
            label="Lập Báo Giá Mới"
            icon="pi pi-plus"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px] !font-medium"
            @click="$emit('open-create-quotation')"
          />
        </div>
      </template>
    </Toolbar>

    <!-- Table -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="filteredQuotations"
        showGridlines
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredQuotations.length > 8"
        :rows="8"
      >
        <Column field="quotation_number" header="Mã Báo Giá" :sortable="true" style="width: 150px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-semibold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200">
              {{ data.quotation_number }}
            </span>
          </template>
        </Column>

        <Column field="customer_name" header="Khách Hàng B2B" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
          </template>
        </Column>

        <Column field="quotation_date" header="Ngày Lập" style="width: 110px">
          <template #body="{ data }">
            <span class="text-slate-600 text-xs">{{ data.quotation_date }}</span>
          </template>
        </Column>

        <Column field="valid_until" header="Hạn Hiệu Lực" style="width: 110px">
          <template #body="{ data }">
            <span class="text-slate-500 text-xs">{{ data.valid_until }}</span>
          </template>
        </Column>

        <Column header="Số Mặt Hàng" class="text-center" style="width: 110px">
          <template #body="{ data }">
            <span class="text-xs font-mono font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded-[4px]">
              {{ data.items?.length || 0 }} items
            </span>
          </template>
        </Column>

        <Column field="total_amount" header="Tổng Báo Giá" class="text-right" :sortable="true" style="width: 150px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-slate-900 num-tabular">
              {{ formatCurrency(data.total_amount) }}
            </span>
          </template>
        </Column>

        <Column header="Trạng Thái" class="text-center" style="width: 150px">
          <template #body="{ data }">
            <Tag
              :severity="getStatusMeta(data.status).severity"
              :value="getStatusMeta(data.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[6px]"
            />
          </template>
        </Column>

        <Column header="Thao Tác" class="text-center" style="width: 140px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1">
              <Button
                label="Chọn Win"
                icon="pi pi-check-circle"
                size="small"
                text
                class="!text-xs !py-1 !px-2 rounded-[6px] !text-indigo-600 hover:!bg-indigo-50 !font-semibold"
                v-tooltip.bottom="'Xem chi tiết & chọn Item Win để tạo Đơn bán'"
                @click="$emit('view-quotation', data)"
              />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-file-edit text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có báo giá nào</p>
            <p class="text-[11px] text-slate-400 m-0">Nhấn nút "Lập Báo Giá Mới" ở trên để bắt đầu quy trình bán hàng.</p>
          </div>
        </template>
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { formatCurrency } from '../../../utils/format';

const props = defineProps({
  quotations: {
    type: Array,
    default: () => []
  }
});

defineEmits(['open-create-quotation', 'view-quotation']);

const searchQuery = ref('');
const statusFilter = ref(null);

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Dự thảo', value: 'DRAFT' },
  { label: 'Đang đàm phán', value: 'NEGOTIATING' },
  { label: 'Đã sinh đơn bán (SO)', value: 'CONVERTED' }
];

const getStatusMeta = (st) => {
  const map = {
    DRAFT: { label: 'Dự thảo', severity: 'secondary' },
    SENT: { label: 'Đã gửi khách', severity: 'info' },
    NEGOTIATING: { label: 'Đang đàm phán', severity: 'warn' },
    CONVERTED: { label: 'Đã tạo SO', severity: 'success' },
    REJECTED: { label: 'Từ chối', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const filteredQuotations = computed(() => {
  return props.quotations.filter((q) => {
    const matchSearch =
      !searchQuery.value ||
      q.quotation_number.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      q.customer_name.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchStatus = !statusFilter.value || q.status === statusFilter.value;
    return matchSearch && matchStatus;
  });
});
</script>
