<template>
  <div class="flex flex-col gap-4">
    <!-- Single Page Title -->
    <PageHeader
      title="Quản Lý Đơn Bán Hàng"
      subtitle="Theo dõi đơn bán B2B, quản lý tiến độ thực hiện hợp đồng và duyệt xuất kho"
    >
      <template #actions>
        <Button
          label="Tạo đơn hàng mới"
          icon="pi pi-plus"
          size="small"
          class="rounded-[6px]"
          @click="showCreateModal = true"
        />
      </template>
    </PageHeader>

    <!-- Operational Toolbar -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="searchQuery"
            placeholder="Tìm theo mã đơn hoặc tên khách hàng..."
            class="w-80 rounded-[6px]"
            size="small"
          />
        </IconField>
      </template>

      <template #end>
        <div class="flex items-center gap-2">
          <Button
            label="Xuất dữ liệu"
            icon="pi pi-download"
            severity="secondary"
            outlined
            size="small"
            class="rounded-[6px]"
            @click="exportCsv"
          />
          <Tag
            severity="secondary"
            :value="orders.length + ' Đơn hàng'"
            class="text-[11px] px-2 py-1 rounded-[6px]"
          />
        </div>
      </template>
    </Toolbar>

    <!-- Orders Table (Flat Container) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden shadow-none">
      <DataTable
        :value="filteredOrders"
        showGridlines
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredOrders.length > 10"
        :rows="10"
      >
        <Column field="order_number" header="Mã Đơn Bán" :sortable="true">
          <template #body="{ data }">
            <span class="font-mono text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-[4px] border border-indigo-200">{{ data.order_number }}</span>
          </template>
        </Column>

        <Column field="customer_name" header="Khách Hàng" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-900">{{ data.customer_name }}</span>
          </template>
        </Column>

        <Column field="order_date" header="Ngày Tạo" :sortable="true">
          <template #body="{ data }">
            <span class="text-slate-500 text-xs">{{ formatDate(data.order_date) }}</span>
          </template>
        </Column>

        <Column field="total_amount" header="Tổng Giá Trị" class="text-right" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-slate-900 num-tabular">{{ formatCurrency(data.total_amount) }}</span>
          </template>
        </Column>

        <Column header="Trạng Thái" class="text-center">
          <template #body="{ data }">
            <Tag
              :severity="getStatusMeta(data.status).severity"
              :value="getStatusMeta(data.status).label"
              class="text-[11px] px-1.5 py-0.5 rounded-[6px]"
            />
          </template>
        </Column>

        <Column header="Thao Tác" class="text-center" style="width: 100px">
          <template #body="{ data }">
            <Button
              icon="pi pi-eye"
              text
              size="small"
              severity="secondary"
              class="rounded-[6px]"
              v-tooltip.bottom="'Xem chi tiết đơn'"
              @click="viewOrder(data)"
            />
          </template>
        </Column>

        <template #empty>
          <div class="py-8 px-4 text-center flex flex-col items-center gap-2">
            <i class="pi pi-shopping-cart text-3xl text-slate-400"></i>
            <p class="text-sm font-semibold text-slate-900 m-0">Chưa có đơn bán hàng nào</p>
            <p class="text-xs text-slate-500 m-0">Nhấn nút Tạo đơn hàng mới ở trên để bắt đầu lập đơn bán B2B.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Create Sales Order Modal -->
    <SalesOrderForm
      v-model:visible="showCreateModal"
      :customers="customers"
      :loading="loadingCreate"
      @submit="handleCreateOrder"
    />
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
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import SalesOrderForm from './form.vue';
import { formatCurrency, formatDate } from '../../utils/format';
import { ORDER_STATUSES } from '../../constants/status.constant';

const props = defineProps({
  customers: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['order-created']);

const searchQuery = ref('');
const showCreateModal = ref(false);
const loadingCreate = ref(false);

// Seed sample orders for demo
const orders = ref([
  {
    id: 1,
    order_number: 'SO-2026-0891',
    customer_name: 'FPT Retail - Chi Nhánh Hà Nội',
    order_date: '2026-09-25',
    total_amount: 148500000,
    status: 'CONFIRMED'
  },
  {
    id: 2,
    order_number: 'SO-2026-0892',
    customer_name: 'Thế Giới Di Động (MWG Corp)',
    order_date: '2026-09-26',
    total_amount: 329900000,
    status: 'DELIVERED'
  },
  {
    id: 3,
    order_number: 'SO-2026-0893',
    customer_name: 'Viettel Commerce Solution',
    order_date: '2026-09-27',
    total_amount: 65980000,
    status: 'DRAFT'
  }
]);

const getStatusMeta = (status) => {
  return ORDER_STATUSES[status] || { label: status, severity: 'secondary' };
};

const filteredOrders = computed(() => {
  if (!searchQuery.value) return orders.value;
  const q = searchQuery.value.toLowerCase().trim();
  return orders.value.filter(
    (o) =>
      o.order_number.toLowerCase().includes(q) ||
      o.customer_name.toLowerCase().includes(q)
  );
});

const handleCreateOrder = (newOrder) => {
  loadingCreate.value = true;
  setTimeout(() => {
    const customer = props.customers.find((c) => c.id === newOrder.customer_id);
    orders.value.unshift({
      id: Date.now(),
      order_number: newOrder.order_number,
      customer_name: customer ? customer.name : 'Khách hàng B2B Doanh Nghiệp',
      order_date: newOrder.order_date,
      total_amount: newOrder.total_amount,
      status: 'CONFIRMED'
    });
    loadingCreate.value = false;
    showCreateModal.value = false;
    emit('order-created', newOrder);
  }, 400);
};

const exportCsv = () => {
  // Demo export handler
};

const viewOrder = (order) => {
  // Demo view handler
};
</script>
