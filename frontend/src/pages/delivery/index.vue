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
          <span>Tổng số: <strong class="text-slate-800">{{ totalOrders.toLocaleString('vi-VN') }}</strong> đơn bán chờ giao</span>
        </div>
      </template>
    </Toolbar>

    <!-- CommonDataTable cho Đơn Bán Chờ Giao -->
    <CommonDataTable
      :value="approvedOrders"
      :columns="orderColumns"
      :rows="pageSize"
      :first="first"
      lazy
      :totalRecords="totalOrders"
      :loading="loadingOrders"
      :paginator="totalOrders > pageSize"
      @page="onPage"
      emptyIcon="pi pi-shopping-bag"
      emptyTitle="Không có đơn bán hàng nào chờ giao"
      emptySubtitle="Các đơn bán hàng sau khi được Phê duyệt (APPROVED) sẽ hiển thị tại đây để điều phối giao hàng."
    >
      <!-- Custom column: id -->
      <template #col-id="{ data }">
        <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200 inline-block whitespace-nowrap">
          #{{ data.id }}
        </span>
      </template>

      <!-- Custom column: order_number (hiển thị số đơn của khách) -->
      <template #col-order_number="{ data }">
        <span class="font-mono text-xs font-bold text-slate-800 inline-block whitespace-nowrap">
          {{ data.customer_order_number || data.order_number || data.id }}
        </span>
      </template>

      <!-- Custom column: customer_name -->
      <template #col-customer_name="{ data }">
        <div class="flex flex-col">
          <span class="font-semibold text-slate-800 text-xs" :title="data.customer_name">
            {{ data.customer_name }}
          </span>
          <span
            v-if="data.shipping_address"
            class="text-[10px] text-slate-400 flex items-center gap-1 mt-0.5"
          >
            <i class="pi pi-map-marker text-[9px] text-rose-400"></i>
            {{ data.shipping_address }}
          </span>
        </div>
      </template>

      <!-- Custom column: salesperson -->
      <template #col-salesperson="{ data }">
        <span class="text-slate-700 text-xs font-medium whitespace-nowrap">{{ data.salesperson }}</span>
      </template>

      <!-- Custom column: items_count -->
      <template #col-items_count="{ data }">
        <span class="font-mono text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded inline-block whitespace-nowrap">
          {{ data.items_count }}
        </span>
      </template>

      <!-- Custom column: total_ordered_qty -->
      <template #col-total_ordered_qty="{ data }">
        <span class="font-mono text-xs font-bold text-blue-600 whitespace-nowrap">
          {{ data.total_ordered_qty }}
        </span>
      </template>

      <!-- Custom column: total_delivered_qty -->
      <template #col-total_delivered_qty="{ data }">
        <span class="font-mono text-xs font-bold text-green-600 whitespace-nowrap">
          {{ data.total_delivered_qty }}
        </span>
      </template>

      <!-- Custom column: total_remaining_qty -->
      <template #col-total_remaining_qty="{ data }">
        <span class="font-mono text-xs font-bold text-red-600 whitespace-nowrap">
          {{ data.total_remaining_qty }}
        </span>
      </template>

      <!-- Cột Thao Tác cho Đơn Bán -->
      <Column header="Thao Tác" class="text-center whitespace-nowrap" headerClass="whitespace-nowrap" style="width: 100px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
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
import { ref, computed, onMounted, watch } from 'vue';
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
const totalOrders = ref(0);
const pageSize = 10;
const first = ref(0);
// Đơn đã duyệt trở đi mới lập được yêu cầu giao
const DELIVERABLE_STATUSES = ['APPROVED', 'PROCESSING', 'CONFIRMED', 'COMPLETED'];
const deliveryRequests = ref([]);
const loadingOrders = ref(false);
const orderSearchQuery = ref('');

// Dialog states
const showOrderDialog = ref(false);
const showCreateDeliveryDialog = ref(false);
const selectedOrder = ref(null);

// Cấu hình cột: Đơn bán chờ giao (Đã thêm cột ID và điều chỉnh width)
const orderColumns = [
  { field: 'id', header: 'ID', sortable: true, width: '70px', align: 'center' },
  { field: 'order_number', header: 'Số Đơn Hàng', sortable: true, width: '190px' },
  { field: 'customer_name', header: 'Khách Hàng', sortable: true },
  { field: 'salesperson', header: 'Nhân Viên KD', sortable: true, width: '150px' },
  { field: 'items_count', header: 'Số Item', sortable: true, width: '90px', align: 'center' },
  { field: 'total_ordered_qty', header: 'SL Đặt', sortable: true, width: '90px', align: 'center' },
  { field: 'total_delivered_qty', header: 'SL Đã Giao', sortable: true, width: '110px', align: 'center' },
  { field: 'total_remaining_qty', header: 'SL Chưa Giao', sortable: true, width: '110px', align: 'center' }
];

// Tải dữ liệu Đơn Bán Hàng
const loadOrders = async () => {
  loadingOrders.value = true;
  try {
    // Lọc trạng thái, tìm kiếm và phân trang ở server trên toàn bộ đơn
    const res = await salesApi.getOrders({
      page: Math.floor(first.value / pageSize) + 1,
      per_page: pageSize,
      status: DELIVERABLE_STATUSES.join(','),
      search: orderSearchQuery.value.trim()
    });
    orders.value = res.data || [];
    totalOrders.value = res.meta?.total ?? 0;
  } catch (err) {
    console.error('Lỗi tải danh sách đơn bán:', err);
  } finally {
    loadingOrders.value = false;
  }
};

// Yêu cầu giao cũ của đúng đơn đang lập (để trừ số lượng đã yêu cầu), không phải trang yêu cầu mới nhất
const loadDeliveries = async (orderId) => {
  deliveryRequests.value = [];
  try {
    const res = await salesApi.getDeliveries({ order_id: orderId, per_page: 200 });
    deliveryRequests.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải yêu cầu giao hàng của đơn:', err);
    toast.add({ severity: 'error', summary: 'Không tải được yêu cầu giao cũ', detail: err.message || 'Vui lòng thử lại.', life: 4000 });
  }
};

function onPage(event) {
  first.value = event.first;
  loadOrders();
}

let searchTimer = null;
watch(orderSearchQuery, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    first.value = 0;
    loadOrders();
  }, 400);
});

onMounted(() => {
  loadOrders();
});

// Tính toán danh sách đơn bán hàng đã duyệt (APPROVED, PROCESSING, CONFIRMED, COMPLETED) kèm lũy kế giao hàng
const approvedOrders = computed(() => {
  return orders.value
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


const openOrderViewDialog = (order) => {
  selectedOrder.value = order;
  showOrderDialog.value = true;
};

const openCreateDeliveryDialog = async (order) => {
  selectedOrder.value = order;
  await loadDeliveries(order.id);
  showCreateDeliveryDialog.value = true;
};

// Sau khi tạo yêu cầu giao hàng thành công
const handleDeliveryCreated = (newDr) => {
  loadOrders();
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
