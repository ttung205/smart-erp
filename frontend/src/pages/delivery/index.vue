<template>
  <div class="space-y-3 pb-8">
    <PageHeader title="Giao Hàng"
      subtitle="Theo dõi đơn bán hàng đã duyệt, lập lệnh điều phối và quản lý các phiếu yêu cầu giao hàng"
      badge="Stage 3: Deliveries" badge-severity="info" />

    <!-- Navigation Tab Pills: Đơn bán chờ giao & Yêu cầu giao hàng -->
    <div class="flex items-center gap-2 p-1.5 bg-slate-100/90 rounded-[8px] border border-slate-200 w-fit">
      <button type="button"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-[6px] text-xs font-semibold transition-all duration-150"
        :class="activeTab === 'orders'
          ? 'bg-white text-indigo-700 shadow-sm border border-slate-200/80 font-bold'
          : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'" @click="switchTab('orders')">
        <i class="pi pi-shopping-bag"></i>
        <span>Đơn Bán Chờ Giao</span>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
          :class="activeTab === 'orders' ? 'bg-indigo-100 text-indigo-700 font-bold' : 'bg-slate-200 text-slate-600'">
          {{ approvedOrders.length }}
        </span>
      </button>

      <button type="button"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-[6px] text-xs font-semibold transition-all duration-150"
        :class="activeTab === 'requests'
          ? 'bg-white text-sky-700 shadow-sm border border-slate-200/80 font-bold'
          : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/60'" @click="switchTab('requests')">
        <i class="pi pi-truck"></i>
        <span>Yêu Cầu Giao Hàng</span>
        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono"
          :class="activeTab === 'requests' ? 'bg-sky-100 text-sky-700 font-bold' : 'bg-slate-200 text-slate-600'">
          {{ deliveryRequests.length }}
        </span>
      </button>
    </div>

    <!-- ========================================== -->
    <!-- TAB 1: ĐƠN BÁN HÀNG ĐÃ DUYỆT CHỜ GIAO     -->
    <!-- ========================================== -->
    <div v-if="activeTab === 'orders'" class="space-y-3">
      <!-- Toolbar Filter -->
      <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
        <template #start>
          <div class="flex items-center gap-2">
            <IconField>
              <InputIcon class="pi pi-search" />
              <InputText v-model="orderSearchQuery" placeholder="Tìm theo số đơn nội bộ, số đơn khách, tên khách..."
                class="w-80 rounded-[6px]" size="small" />
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
      <CommonDataTable :value="filteredOrders" :columns="orderColumns" :rows="10" :loading="loadingOrders"
        :paginator="filteredOrders.length > 10" emptyIcon="pi pi-shopping-bag"
        emptyTitle="Không có đơn bán hàng nào chờ giao"
        emptySubtitle="Các đơn bán hàng sau khi được Phê duyệt (APPROVED) sẽ hiển thị tại đây để điều phối giao hàng.">
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
            <span v-if="data.shipping_address"
              class="text-[10px] text-slate-400 truncate max-w-xs flex items-center gap-1 mt-0.5">
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
          <span class="font-mono text-xs font-bold text-slate-800">
            {{ data.total_ordered_qty }}
          </span>
        </template>

        <!-- Custom column: total_delivered_qty -->
        <template #col-total_delivered_qty="{ data }">
          <span
            class="font-mono text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
            {{ data.total_delivered_qty }}
          </span>
        </template>

        <!-- Custom column: total_remaining_qty -->
        <template #col-total_remaining_qty="{ data }">
          <span class="font-mono text-xs font-bold px-2 py-0.5 rounded border"
            :class="data.total_remaining_qty > 0 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-400 border-slate-200'">
            {{ data.total_remaining_qty }}
          </span>
        </template>

        <!-- Cột Thao Tác cho Đơn Bán -->
        <Column header="Thao Tác" class="text-center" style="width: 200px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1.5">
              <!-- Nút xem dialog đơn hàng (dùng luôn dialog order) -->
              <Button icon="pi pi-eye" text size="small" severity="secondary" class="!w-7 !h-7 !p-0 rounded-[6px]"
                v-tooltip.bottom="'Xem chi tiết đơn bán hàng'" @click="openOrderViewDialog(data)" />

              <!-- Nút Gửi yêu cầu giao -->
              <Button v-if="data.total_remaining_qty > 0" label="Gửi yêu cầu giao" icon="pi pi-send" size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !font-semibold shadow-sm"
                v-tooltip.bottom="'Lập phiếu yêu cầu giao hàng theo đơn bán này'"
                @click="openCreateDeliveryDialog(data)" />
              <Tag v-else severity="success" value="Đã giao đủ" class="text-[10px] px-2 py-0.5 rounded-[4px]" />
            </div>
          </template>
        </Column>
      </CommonDataTable>
    </div>

    <!-- ========================================== -->
    <!-- TAB 2: DANH SÁCH YÊU CẦU GIAO HÀNG (DR)   -->
    <!-- ========================================== -->
    <div v-else-if="activeTab === 'requests'" class="space-y-3">
      <!-- Toolbar Filter -->
      <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
        <template #start>
          <div class="flex items-center gap-2">
            <IconField>
              <InputIcon class="pi pi-search" />
              <InputText v-model="requestSearchQuery" placeholder="Tìm theo mã yêu cầu, số đơn, khách hàng..."
                class="w-80 rounded-[6px]" size="small" />
            </IconField>
          </div>
        </template>

        <template #end>
          <div class="flex items-center gap-2 text-xs text-slate-500">
            <span>Tổng số: <strong class="text-slate-800">{{ filteredRequests.length }}</strong> yêu cầu giao
              hàng</span>
          </div>
        </template>
      </Toolbar>

      <!-- CommonDataTable cho Yêu Cầu Giao Hàng -->
      <CommonDataTable :value="filteredRequests" :columns="requestColumns" :rows="10" :loading="loadingDeliveries"
        :paginator="filteredRequests.length > 10" emptyIcon="pi pi-truck" emptyTitle="Chưa có yêu cầu giao hàng nào"
        emptySubtitle="Bấm 'Gửi yêu cầu giao' từ danh sách Đơn Bán Chờ Giao ở tab trước để tạo lệnh mới.">
        <!-- Custom column: id -->
        <template #col-id="{ data }">
          <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200">
            #{{ data.id }}
          </span>
        </template>

        <!-- Custom column: order_number (hiển thị số đơn của khách) -->
        <template #col-order_number="{ data }">
          <span class="font-mono text-xs font-semibold text-slate-800">
            {{ data.customer_order_number || data.order_number || data.order_id }}
          </span>
        </template>

        <!-- Custom column: customer_name -->
        <template #col-customer_name="{ data }">
          <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
        </template>

        <!-- Custom column: salesperson -->
        <template #col-salesperson="{ data }">
          <span class="text-slate-700 text-xs font-medium">{{ data.salesperson }}</span>
        </template>

        <!-- Custom column: shipping_address -->
        <template #col-shipping_address="{ data }">
          <div class="flex items-center gap-1 text-xs text-slate-600 truncate max-w-xs" :title="data.shipping_address">
            <i class="pi pi-map-marker text-[10px] text-rose-500 shrink-0"></i>
            <span class="truncate">{{ data.shipping_address || 'Địa chỉ kho khách' }}</span>
          </div>
        </template>

        <!-- Custom column: items_count -->
        <template #col-items_count="{ data }">
          <span class="font-mono text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
            {{ data.items_count }}
          </span>
        </template>

        <!-- Custom column: total_requested_qty -->
        <template #col-total_requested_qty="{ data }">
          <span
            class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
            {{ data.total_requested_qty }}
          </span>
        </template>

        <!-- Custom column: total_delivered_qty -->
        <template #col-total_delivered_qty="{ data }">
          <span
            class="font-mono text-xs font-bold text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
            {{ data.total_delivered_qty }}
          </span>
        </template>

        <!-- Custom column: total_remaining_qty -->
        <template #col-total_remaining_qty="{ data }">
          <span class="font-mono text-xs font-bold px-2 py-0.5 rounded border"
            :class="data.total_remaining_qty > 0 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-slate-50 text-slate-500 border-slate-200'">
            {{ data.total_remaining_qty }}
          </span>
        </template>

        <!-- Custom column: status -->
        <template #col-status="{ data }">
          <div class="text-center">
            <Tag :severity="getStatusMeta(data.status).severity" :value="getStatusMeta(data.status).label"
              class="text-[11px] px-2 py-0.5 rounded-[6px]" />
          </div>
        </template>

        <!-- Cột Thao Tác cho Yêu Cầu Giao Hàng -->
        <Column header="Thao Tác" class="text-center" style="width: 220px">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1.5">
              <!-- Nút View Dialog xem các mã hàng yêu cầu giao -->
              <Button icon="pi pi-eye" text size="small" severity="secondary" class="!w-7 !h-7 !p-0 rounded-[6px]"
                v-tooltip.bottom="'Xem các mã hàng yêu cầu giao'" @click="openRequestDetailDialog(data)" />

              <!-- Nút Duyệt yêu cầu (Quyền delivery-request.approve) -->
              <Button v-if="data.status === 'PENDING'" label="Duyệt yêu cầu" icon="pi pi-check" size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !font-semibold"
                :disabled="!canApproveRequest"
                v-tooltip.bottom="canApproveRequest ? 'Phê duyệt lệnh giao hàng này' : 'Cần quyền delivery-request.approve để duyệt'"
                @click="confirmApproveRequest(data)" />

              <!-- Nút Lập chứng từ bán hàng (khi đã duyệt và chưa tạo chứng từ) -->
              <Button v-else-if="data.status === 'APPROVED'" label="Lập Chứng Từ" icon="pi pi-file" size="small"
                class="!text-xs !py-1 !px-2 rounded-[6px] !bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 !font-semibold"
                v-tooltip.bottom="'Tạo chứng từ bán hàng để kho xuất hàng'" @click="openInvoiceDialog(data)" />

              <Tag v-else-if="data.status === 'INVOICED'" severity="success" value="Đã lập chứng từ"
                class="text-[10px] px-2 py-0.5 rounded-[4px]" />
            </div>
          </template>
        </Column>
      </CommonDataTable>
    </div>

    <!-- ========================================== -->
    <!-- DIALOGS DÙNG CHO GIAO HÀNG                 -->
    <!-- ========================================== -->
    <!-- 1. Xem chi tiết đơn hàng (dùng luôn dialog order) -->
    <OrderDetailDialog v-model:visible="showOrderDialog" :order="selectedOrder" />

    <!-- 2. Gửi yêu cầu giao hàng (cho phép sửa số lượng yêu cầu) -->
    <CreateDeliveryRequestDialog v-model:visible="showCreateDeliveryDialog" :order="selectedOrder"
      :existing-deliveries="deliveryRequests" @created="handleDeliveryCreated" />

    <!-- 3. Xem chi tiết các mã hàng của yêu cầu giao -->
    <DeliveryRequestDetailDialog v-model:visible="showRequestDetailDialog" :delivery-request="selectedRequest" />

    <!-- 4. Lập chứng từ bán hàng từ yêu cầu giao đã duyệt -->
    <DeliveryInvoiceDialog v-model:visible="showInvoiceDialog" :delivery-request="selectedRequest"
      @create-invoice="handleCreateInvoice" />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Tag from 'primevue/tag';

import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import OrderDetailDialog from '../../components/order/OrderDetailDialog.vue';
import CreateDeliveryRequestDialog from '../../components/delivery/CreateDeliveryRequestDialog.vue';
import DeliveryRequestDetailDialog from '../../components/delivery/DeliveryRequestDetailDialog.vue';
import DeliveryInvoiceDialog from '../../components/delivery/DeliveryInvoiceDialog.vue';

import { salesApi } from '../../api/sales.api';
import { isSuperAdmin, hasPermission } from '../../utils/rbac';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const route = useRoute();
const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

// Tab state: 'orders' | 'requests'
const activeTab = ref(route.path.includes('delivery-request') || route.query.tab === 'requests' ? 'requests' : 'orders');

const switchTab = (tab) => {
  activeTab.value = tab;
  if (tab === 'requests') {
    router.replace({ path: '/delivery', query: { tab: 'requests' } });
  } else {
    router.replace({ path: '/delivery', query: {} });
  }
};

watch(
  () => [route.path, route.query.tab],
  ([newPath, newTab]) => {
    if (newPath.includes('delivery-request') || newTab === 'requests') {
      activeTab.value = 'requests';
    } else {
      activeTab.value = 'orders';
    }
  }
);

// Quyền duyệt yêu cầu giao hàng: delivery-request.approve hoặc admin
const canApproveRequest = computed(() => {
  const user = props.currentUser || JSON.parse(localStorage.getItem('user') || '{}');
  if (!user || !user.id) return true; // Fallback demo
  if (isSuperAdmin(user)) return true;
  return hasPermission('delivery-request.approve', user) || hasPermission('delivery.approve', user);
});

// State Data
const orders = ref([]);
const deliveryRequests = ref([]);
const loadingOrders = ref(false);
const loadingDeliveries = ref(false);
const orderSearchQuery = ref('');
const requestSearchQuery = ref('');

// Dialog states
const showOrderDialog = ref(false);
const showCreateDeliveryDialog = ref(false);
const showRequestDetailDialog = ref(false);
const showInvoiceDialog = ref(false);
const selectedOrder = ref(null);
const selectedRequest = ref(null);

// Status mapper
const getStatusMeta = (st) => {
  const map = {
    PENDING: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt (Chờ xuất)', severity: 'info' },
    INVOICED: { label: 'Đã lập chứng từ', severity: 'success' },
    DELIVERED: { label: 'Đã giao hàng', severity: 'success' },
    REJECTED: { label: 'Từ chối', severity: 'danger' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

// Cấu hình cột Tab 1: Đơn bán chờ giao
const orderColumns = [
  { field: 'order_number', header: 'Số Đơn Hàng', sortable: true, width: '160px' },
  { field: 'customer_name', header: 'Khách Hàng', sortable: true },
  { field: 'salesperson', header: 'Nhân Viên KD', sortable: true, width: '160px' },
  { field: 'items_count', header: 'Số Item', sortable: true, width: '110px', align: 'center' },
  { field: 'total_ordered_qty', header: 'SL Đặt', sortable: true, width: '100px', align: 'center' },
  { field: 'total_delivered_qty', header: 'SL Đã Giao', sortable: true, width: '110px', align: 'center' },
  { field: 'total_remaining_qty', header: 'SL Chưa Giao', sortable: true, width: '120px', align: 'center' }
];

// Cấu hình cột Tab 2: Yêu cầu giao hàng
const requestColumns = [
  { field: 'id', header: 'ID', sortable: true, width: '70px', align: 'center' },
  { field: 'order_number', header: 'Số Đơn Hàng', sortable: true, width: '150px' },
  { field: 'customer_name', header: 'Khách Hàng', sortable: true },
  { field: 'salesperson', header: 'Nhân Viên KD', sortable: true, width: '140px' },
  { field: 'shipping_address', header: 'Địa Chỉ Giao Hàng', sortable: false, width: '180px' },
  { field: 'items_count', header: 'Số Item', sortable: true, width: '90px', align: 'center' },
  { field: 'total_requested_qty', header: 'SL Yêu Cầu', sortable: true, width: '110px', align: 'center' },
  { field: 'total_delivered_qty', header: 'SL Đã Giao', sortable: true, width: '110px', align: 'center' },
  { field: 'total_remaining_qty', header: 'SL Chưa Giao', sortable: true, width: '120px', align: 'center' },
  { field: 'status', header: 'Trạng Thái', sortable: true, width: '140px', align: 'center' }
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

// Tải dữ liệu Yêu Cầu Giao Hàng
const loadDeliveries = async () => {
  loadingDeliveries.value = true;
  try {
    const res = await salesApi.getDeliveries();
    deliveryRequests.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách giao hàng:', err);
  } finally {
    loadingDeliveries.value = false;
  }
};

onMounted(() => {
  loadOrders();
  loadDeliveries();
});

// Tính toán danh sách đơn bán hàng đã duyệt (APPROVED, PROCESSING, COMPLETED) kèm lũy kế giao hàng
const approvedOrders = computed(() => {
  const allowedStatuses = ['APPROVED', 'PROCESSING', 'CONFIRMED', 'COMPLETED'];
  return orders.value
    .filter((o) => allowedStatuses.includes(o.status))
    .map((o) => {
      const orderNumber = o.order_number || o.id;
      // Tìm các delivery requests thuộc về đơn này
      const relatedDrs = deliveryRequests.value.filter(
        (dr) => (dr.order_number === orderNumber || dr.order_id === o.id) && dr.status !== 'CANCELLED'
      );

      // Tính tổng số lượng đặt của đơn
      const totalOrdered = (o.items || []).reduce((sum, it) => sum + (Number(it.quantity) || 1), 0);

      // Tính tổng số lượng đã giao qua các phiếu đã duyệt hoặc đã giao
      let totalDelivered = 0;
      relatedDrs.forEach((dr) => {
        if (['APPROVED', 'INVOICED', 'DELIVERED'].includes(dr.status)) {
          (dr.items || []).forEach((it) => {
            totalDelivered += Number(it.quantity || 0);
          });
        }
      });

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
      String(o.salesperson || '').toLowerCase().includes(q)
    );
  });
});

// Tính toán danh sách Yêu Cầu Giao Hàng với 2 cột đã giao và chưa giao tính theo số lượng yêu cầu
const enrichedDeliveryRequests = computed(() => {
  return deliveryRequests.value.map((dr) => {
    const isCompleted = ['DELIVERED', 'INVOICED'].includes(dr.status);
    const items = dr.items || [];
    const totalRequested = items.reduce((sum, it) => sum + (Number(it.quantity) || 0), 0);

    let totalDelivered = 0;
    if (isCompleted) {
      totalDelivered = totalRequested;
    } else {
      totalDelivered = items.reduce((sum, it) => sum + (Number(it.delivered_quantity) || 0), 0);
    }
    const totalRemaining = Math.max(0, totalRequested - totalDelivered);

    // Join trực tiếp từ đơn bán hàng theo order_id chuẩn quan hệ ID (không lưu trùng lặp)
    const relatedOrder = orders.value.find(
      (o) => (dr.order_id && o.id === dr.order_id) ||
             (o.order_number && (o.order_number === dr.order_number || o.order_number === dr.order_id)) ||
             (o.id && String(o.id) === String(dr.order_id || dr.order_number))
    );

    const customerOrderNo = relatedOrder?.customer_order_number || relatedOrder?.order_number || dr.customer_order_number || dr.order_number;
    const customerName = relatedOrder?.customer_name || dr.customer_name || dr.order?.customer_name || 'Khách hàng';
    const salesperson = relatedOrder?.created_by_name || relatedOrder?.creator?.name || relatedOrder?.salesperson || dr.salesperson || dr.order?.creator?.name || 'Nhân viên KD';
    const shippingAddress = relatedOrder?.shipping_address || dr.shipping_address || 'Địa chỉ kho khách';

    return {
      ...dr,
      order_id: dr.order_id || relatedOrder?.id,
      order_number: relatedOrder?.order_number || dr.order_number,
      customer_order_number: customerOrderNo,
      customer_name: customerName,
      salesperson: salesperson,
      shipping_address: shippingAddress,
      items_count: items.length,
      total_requested_qty: totalRequested,
      total_delivered_qty: totalDelivered,
      total_remaining_qty: totalRemaining
    };
  });
});

// Lọc yêu cầu giao hàng theo từ khóa tìm kiếm
const filteredRequests = computed(() => {
  if (!requestSearchQuery.value) return enrichedDeliveryRequests.value;
  const q = requestSearchQuery.value.toLowerCase().trim();
  return enrichedDeliveryRequests.value.filter((r) => {
    return (
      String(r.id || '').toLowerCase().includes(q) ||
      String(r.order_number || '').toLowerCase().includes(q) ||
      String(r.customer_order_number || '').toLowerCase().includes(q) ||
      String(r.customer_name || '').toLowerCase().includes(q) ||
      String(r.salesperson || '').toLowerCase().includes(q)
    );
  });
});

// Thao tác Tab 1: Đơn bán
const openOrderViewDialog = (order) => {
  selectedOrder.value = order;
  showOrderDialog.value = true;
};

const openCreateDeliveryDialog = (order) => {
  selectedOrder.value = order;
  showCreateDeliveryDialog.value = true;
};

// Sau khi tạo yêu cầu giao hàng thành công: tự động chuyển sang Tab Yêu Cầu Giao Hàng
const handleDeliveryCreated = (newDr) => {
  loadOrders();
  loadDeliveries();
  switchTab('requests');
  toast.add({
    severity: 'success',
    summary: 'Đã gửi yêu cầu giao hàng',
    detail: `Yêu cầu giao hàng ${newDr?.request_number || 'DR'} đã được gửi thành công và chuyển sang danh sách chờ duyệt.`,
    life: 4000
  });
};

// Thao tác Tab 2: Yêu cầu giao hàng
const openRequestDetailDialog = (req) => {
  selectedRequest.value = req;
  showRequestDetailDialog.value = true;
};

// Phê duyệt yêu cầu giao hàng
const confirmApproveRequest = (req) => {
  confirm.require({
    header: 'Phê Duyệt Yêu Cầu Giao Hàng',
    message: `Bạn có chắc chắn muốn phê duyệt lệnh giao hàng ${req.request_number || req.id} không?`,
    icon: 'pi pi-check-circle',
    rejectLabel: 'Hủy bỏ',
    rejectProps: { severity: 'secondary', outlined: true },
    acceptLabel: 'Đồng ý phê duyệt',
    acceptProps: { severity: 'success' },
    accept: async () => {
      try {
        await salesApi.updateDeliveryStatus(req.id || req.request_number, 'APPROVED');
        toast.add({
          severity: 'success',
          summary: 'Đã duyệt yêu cầu',
          detail: `Phiếu yêu cầu ${req.request_number || req.id} đã được phê duyệt thành công!`,
          life: 3000
        });
        loadDeliveries();
        loadOrders();
      } catch (err) {
        toast.add({
          severity: 'error',
          summary: 'Lỗi phê duyệt',
          detail: err.message || 'Không thể duyệt yêu cầu giao hàng.',
          life: 3000
        });
      }
    }
  });
};

const openInvoiceDialog = (req) => {
  selectedRequest.value = req;
  showInvoiceDialog.value = true;
};

const handleCreateInvoice = async (newInv) => {
  try {
    const res = await salesApi.createInvoice(newInv);
    const inv = res.data;
    if (selectedRequest.value?.id) {
      await salesApi.updateDeliveryStatus(selectedRequest.value.id, 'INVOICED');
    }
    toast.add({
      severity: 'success',
      summary: 'Đã lập chứng từ bán hàng',
      detail: `Chứng từ ${inv?.invoice_number || 'INV'} đã lập thành công. Đang chuyển sang trang Chứng Từ & Kho...`,
      life: 3000
    });
    loadDeliveries();
    loadOrders();
    router.push('/invoice');
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lập chứng từ',
      detail: err.message || 'Không thể tạo chứng từ.',
      life: 3000
    });
  }
};
</script>
