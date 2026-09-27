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
              placeholder="Tìm theo mã đơn hoặc tên khách hàng..."
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
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span>Tổng số: <strong class="text-slate-800">{{ orders.length }}</strong> đơn bán hàng</span>
        </div>
      </template>
    </Toolbar>

    <!-- Table -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="filteredOrders"
        showGridlines
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredOrders.length > 8"
        :rows="8"
      >
        <Column field="id" header="Mã Đơn Bán (SO)" :sortable="true" style="width: 160px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-[4px] border border-indigo-200">
              {{ data.id }}
            </span>
          </template>
        </Column>

        <Column field="quotation_id" header="Nguồn Báo Giá" style="width: 150px">
          <template #body="{ data }">
            <span v-if="data.quotation_id" class="font-mono text-xs text-sky-600 bg-sky-50 px-1.5 py-0.5 rounded-[4px] border border-sky-100">
              {{ data.quotation_id }}
            </span>
            <span v-else class="text-slate-400 text-xs italic">Đơn trực tiếp</span>
          </template>
        </Column>

        <Column field="customer_name" header="Khách Hàng B2B" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
          </template>
        </Column>

        <Column field="order_date" header="Ngày Tạo" style="width: 110px">
          <template #body="{ data }">
            <span class="text-slate-600 text-xs">{{ data.order_date }}</span>
          </template>
        </Column>

        <Column header="Mặt Hàng Chốt" class="text-center" style="width: 120px">
          <template #body="{ data }">
            <span class="text-xs font-mono font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded-[4px]">
              {{ data.items?.length || 0 }} items win
            </span>
          </template>
        </Column>

        <Column field="total_amount" header="Tổng Tiền Đơn" class="text-right" :sortable="true" style="width: 150px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-slate-900 num-tabular">
              {{ formatCurrency(data.total_amount) }}
            </span>
          </template>
        </Column>

        <Column header="Trạng Thái" class="text-center" style="width: 140px">
          <template #body="{ data }">
            <Tag
              :severity="getStatusMeta(data.status).severity"
              :value="getStatusMeta(data.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[6px]"
            />
          </template>
        </Column>

        <Column header="Thao Tác" class="text-center" style="width: 180px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1">
              <Button
                label="Lập Yêu Cầu Giao"
                icon="pi pi-truck"
                size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !font-medium"
                v-tooltip.bottom="'Tạo lệnh yêu cầu giao hàng sang kho xuất'"
                @click="$emit('open-delivery-request', data)"
              />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-shopping-cart text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có đơn bán hàng nào</p>
            <p class="text-[11px] text-slate-400 m-0">Hãy duyệt chọn Item Win từ mục "Báo Giá" để sinh đơn bán hàng tự động.</p>
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
  orders: {
    type: Array,
    default: () => []
  }
});

defineEmits(['open-delivery-request']);

const searchQuery = ref('');
const statusFilter = ref(null);

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Đã xác nhận', value: 'CONFIRMED' },
  { label: 'Đang điều phối', value: 'DELIVERING' },
  { label: 'Đã hoàn thành', value: 'COMPLETED' }
];

const getStatusMeta = (st) => {
  const map = {
    CONFIRMED: { label: 'Đã xác nhận (SO)', severity: 'info' },
    DELIVERING: { label: 'Đang điều phối giao', severity: 'warn' },
    COMPLETED: { label: 'Đã hoàn tất', severity: 'success' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const filteredOrders = computed(() => {
  return props.orders.filter((o) => {
    const matchSearch =
      !searchQuery.value ||
      o.id.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      o.customer_name.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchStatus = !statusFilter.value || o.status === statusFilter.value;
    return matchSearch && matchStatus;
  });
});
</script>
