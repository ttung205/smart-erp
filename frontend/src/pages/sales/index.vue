<template>
  <div class="sales-page">
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
          @click="showCreateModal = true"
        />
      </template>
    </PageHeader>

    <!-- Operational Toolbar -->
    <Toolbar class="sales-toolbar">
      <template #start>
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="searchQuery"
            placeholder="Tìm theo mã đơn hoặc tên khách hàng..."
            class="search-input"
            size="small"
          />
        </IconField>
      </template>

      <template #end>
        <div class="toolbar-actions">
          <Button
            label="Xuất dữ liệu"
            icon="pi pi-download"
            severity="secondary"
            outlined
            size="small"
            @click="exportCsv"
          />
          <Tag
            severity="secondary"
            :value="orders.length + ' Đơn hàng'"
            class="count-tag"
          />
        </div>
      </template>
    </Toolbar>

    <!-- Orders Table (Flat Container) -->
    <div class="table-container">
      <DataTable
        :value="filteredOrders"
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredOrders.length > 10"
        :rows="10"
      >
        <Column field="order_number" header="Mã Đơn Bán" :sortable="true">
          <template #body="{ data }">
            <span class="order-code">{{ data.order_number }}</span>
          </template>
        </Column>

        <Column field="customer_name" header="Khách Hàng" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-main">{{ data.customer_name }}</span>
          </template>
        </Column>

        <Column field="order_date" header="Ngày Tạo" :sortable="true">
          <template #body="{ data }">
            <span class="text-muted text-xs">{{ formatDate(data.order_date) }}</span>
          </template>
        </Column>

        <Column field="total_amount" header="Tổng Giá Trị" class="text-right" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-main num-tabular">{{ formatCurrency(data.total_amount) }}</span>
          </template>
        </Column>

        <Column header="Trạng Thái" class="text-center">
          <template #body="{ data }">
            <Tag
              :severity="getStatusMeta(data.status).severity"
              :value="getStatusMeta(data.status).label"
              class="cell-tag"
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
              v-tooltip.bottom="'Xem chi tiết đơn'"
              @click="viewOrder(data)"
            />
          </template>
        </Column>

        <template #empty>
          <div class="empty-table-state">
            <i class="pi pi-shopping-cart empty-icon"></i>
            <p class="empty-title">Chưa có đơn bán hàng nào</p>
            <p class="empty-subtitle">Nhấn nút Tạo đơn hàng mới ở trên để bắt đầu lập đơn bán B2B.</p>
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

<style scoped>
.sales-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.sales-toolbar {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 8px 12px;
}

.search-input {
  width: 320px;
}

.toolbar-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.count-tag {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 6px;
}

.table-container {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  overflow: hidden;
}

.order-code {
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 600;
  color: var(--primary);
  background-color: var(--primary-subtle);
  padding: 2px 6px;
  border-radius: 6px;
}

.cell-tag {
  font-size: 11px;
  padding: 2px 6px;
  border-radius: 6px;
}

.empty-table-state {
  padding: 32px 16px;
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.empty-icon {
  font-size: 28px;
  color: var(--text-dim);
}

.empty-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--text-main);
  margin: 0;
}

.empty-subtitle {
  font-size: 12px;
  color: var(--text-muted);
  margin: 0;
}
</style>
