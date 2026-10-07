<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Giao Hàng (Đơn Bán Chờ Giao)"
      subtitle="Theo dõi đơn bán hàng đã duyệt, lập phiếu yêu cầu giao hàng (DR) và theo dõi số lượng tồn đọng cần giao"
      badge="Stage 3: Deliveries"
      badge-severity="info"
    />

    <!-- Toolbar Filter -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="orderSearchQuery"
              placeholder="Tìm theo số đơn nội bộ, số đơn khách, tên khách..."
              class="w-80 rounded-[6px]"
              size="small"
            />
          </IconField>
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span>Tổng số: <strong class="text-slate-800">{{ filteredOrders.length }}</strong> đơn bán chờ giao</span>
        </div>
      </template>
    </Toolbar>

    <!-- CommonDataTable cho Đơn Bán Chờ Giao -->
    <CommonDataTable
      :value="filteredOrders"
      :columns="orderColumns"
      :rows="10"
      :loading="loadingOrders"
      :paginator="filteredOrders.length > 10"
      emptyIcon="pi pi-shopping-bag"
      emptyTitle="Không có đơn bán hàng nào chờ giao"
      emptySubtitle="Các đơn bán hàng sau khi được Phê duyệt (APPROVED) sẽ hiển thị tại đây để điều phối giao hàng."
    >
      <!-- Custom column: order_number (hiển thị số đơn của khách) -->
      <template #col-order_number="{ data }">
        <span class="font-mono text-xs font-bold text-slate-800">
          {{ data.customer_order_number || data.order_number || data.id }}
        </span>
      </template>

      <!-- Custom column: customer_name -->
      <template #col-customer_name="{ data }">
        <div class="flex flex-col">
          <span class="font-semibold text-slate-800 text-xs truncate max-w-xs" :title="data.customer_name">
            {{ data.customer_name }}
          </span>
          <span
            v-if="data.shipping_address"
            class="text-[10px] text-slate-400 truncate max-w-xs flex items-center gap-1 mt-0.5"
          >
            <i class="pi pi-map-marker text-[9px] text-rose-400"></i>
            {{ data.shipping_address }}
          </span>
        </div>
      </template>

      <!-- Custom column: salesperson -->
      <template #col-salesperson="{ data }">
        <span class="text-slate-700 text-xs font-medium">{{ data.salesperson }}</span>
      </template>

      <!-- Custom column: items_count -->
      <template #col-items_count="{ data }">
        <span class="font-mono text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
          {{ data.items_count }}
        </span>
      </template>

      <!-- Custom column: total_ordered_qty -->
      <template #col-total_ordered_qty="{ data }">
        <span class="font-mono text-xs font-bold text-blue-600">
          {{ data.total_ordered_qty }}
        </span>
      </template>

      <!-- Custom column: total_delivered_qty -->
      <template #col-total_delivered_qty="{ data }">
        <span class="font-mono text-xs font-bold text-green-600">
          {{ data.total_delivered_qty }}
        </span>
      </template>

      <!-- Custom column: total_remaining_qty -->
      <template #col-total_remaining_qty="{ data }">
        <span class="font-mono text-xs font-bold text-red-600">
          {{ data.total_remaining_qty }}
        </span>
      </template>

      <!-- Cột Thao Tác cho Đơn Bán -->
      <Column header="Thao Tác" class="text-center" style="width: 100px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <!-- Nút xem dialog đơn hàng (dùng luôn dialog order) -->
            <Button
              icon="pi pi-eye"
              outlined
              size="small"
              severity="secondary"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xem chi tiết đơn bán hàng'"
              @click="openOrderViewDialog(data)"
            />

            <!-- Nút Gửi yêu cầu giao -->
            <Button
              v-if="data.total_remaining_qty > 0"
              icon="pi pi-send"
              outlined
              size="small"
              severity="success"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Lập phiếu yêu cầu giao hàng theo đơn bán này'"
              @click="openCreateDeliveryDialog(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Dialogs -->
    <!-- 1. Xem chi tiết đơn hàng -->
    <OrderDetailDialog v-model:visible="showOrderDialog" :order="selectedOrder" />

    <!-- 2. Gửi yêu cầu giao hàng (cho phép sửa số lượng yêu cầu) -->
    <CreateDeliveryRequestDialog
      v-model:visible="showCreateDeliveryDialog"
      :order="selectedOrder"
      :existing-deliveries="deliveryRequests"
      @created="handleDeliveryCreated"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import OrderDetailDialog from '../../components/order/OrderDetailDialog.vue';
import CreateDeliveryRequestDialog from '../../components/delivery/CreateDeliveryRequestDialog.vue';

import { salesApi } from '../../api/sales.api';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const router = useRouter();
const toast = useToast();

// State Data
const orders = ref([]);
const deliveryRequests = ref([]);
const loadingOrders = ref(false);
const orderSearchQuery = ref('');

// Dialog states
const showOrderDialog = ref(false);
const showCreateDeliveryDialog = ref(false);
const selectedOrder = ref(null);

// Cấu hình cột: Đơn bán chờ giao
const orderColumns = [
  { field: 'order_number', header: 'Số Đơn Hàng', sortable: true, width: '200px' },
  { field: 'customer_name', header: 'Khách Hàng', sortable: true },
  { field: 'salesperson', header: 'Nhân Viên KD', sortable: true, width: '160px' },
  { field: 'items_count', header: 'Số Item', sortable: true, width: '110px', align: 'center' },
  { field: 'total_ordered_qty', header: 'SL Đặt', sortable: true, width: '100px', align: 'center' },
  { field: 'total_delivered_qty', header: 'SL Đã Giao', sortable: true, width: '130px', align: 'center' },
  { field: 'total_remaining_qty', header: 'SL Chưa Giao', sortable: true, width: '120px', align: 'center' }
];

// Tải dữ liệu Đơn Bán Hàng
const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    const res = await salesApi.getOrders();
    orders.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách đơn bán:', err);
  } finally {
    loadingOrders.value = false;
  }
};

// Tải dữ liệu Yêu Cầu Giao Hàng để đối chiếu lũy kế
const loadDeliveries = async () => {
  try {
    const res = await salesApi.getDeliveries();
    deliveryRequests.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách giao hàng:', err);
  }
};

onMounted(() => {
  loadOrders();
  loadDeliveries();
});

// Tính toán danh sách đơn bán hàng đã duyệt (APPROVED, PROCESSING, CONFIRMED, COMPLETED) kèm lũy kế giao hàng
const approvedOrders = computed(() => {
  const allowedStatuses = ['APPROVED', 'PROCESSING', 'CONFIRMED', 'COMPLETED'];
  return orders.value
    .filter((o) => allowedStatuses.includes(o.status))
    .map((o) => {
      const orderNumber = o.order_number || o.id;

      // Tính tổng số lượng đặt của đơn
      const totalOrdered = (o.items || []).reduce((sum, it) => sum + (Number(it.quantity) || 1), 0);

      // Tính tổng số lượng đã giao THỰC TẾ (khi kho đã ghi sổ trừ tồn)
      const totalDelivered = (o.items || []).reduce((sum, it) => sum + (Number(it.delivered_quantity) || 0), 0);
      const totalRemaining = Math.max(0, totalOrdered - totalDelivered);

      return {
        ...o,
        order_number: orderNumber,
        customer_order_number: o.customer_order_number || orderNumber,
        salesperson: o.created_by_name || o.creator?.name || 'Nhân viên KD',
        items_count: (o.items || []).length,
        total_ordered_qty: totalOrdered,
        total_delivered_qty: totalDelivered,
        total_remaining_qty: totalRemaining
      };
    });
});

// Lọc đơn bán theo từ khóa tìm kiếm
const filteredOrders = computed(() => {
  if (!orderSearchQuery.value) return approvedOrders.value;
  const q = orderSearchQuery.value.toLowerCase().trim();
  return approvedOrders.value.filter((o) => {
    return (
      String(o.order_number || '').toLowerCase().includes(q) ||
      String(o.customer_order_number || '').toLowerCase().includes(q) ||
      String(o.customer_name || '').toLowerCase().includes(q) ||
      String(o.salesperson || '').toLowerCase().includes(q) ||
      String(o.shipping_address || '').toLowerCase().includes(q)
    );
  });
});

const openOrderViewDialog = (order) => {
  selectedOrder.value = order;
  showOrderDialog.value = true;
};

const openCreateDeliveryDialog = (order) => {
  selectedOrder.value = order;
  showCreateDeliveryDialog.value = true;
};

// Sau khi tạo yêu cầu giao hàng thành công
const handleDeliveryCreated = (newDr) => {
  loadOrders();
  loadDeliveries();
  toast.add({
    severity: 'success',
    summary: 'Đã gửi yêu cầu giao hàng',
    detail: `Yêu cầu giao hàng ${newDr?.request_number || 'DR'} đã được gửi thành công. Bạn có thể sang trang 'Yêu cầu giao hàng' để xét duyệt.`,
    life: 4000
  });
  // Chuyển sang trang Yêu Cầu Giao Hàng
  router.push('/delivery-request');
};
</script>
