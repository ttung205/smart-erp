<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Yêu Cầu Giao Hàng (Delivery Requests)"
      subtitle="Quản lý và xét duyệt các phiếu yêu cầu điều phối giao hàng (DR) từ các đơn bán hàng đã duyệt"
      badge="Stage 3: Delivery Requests"
      badge-severity="info"
    />

    <!-- Toolbar Filter -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="requestSearchQuery"
              placeholder="Tìm theo mã yêu cầu, số đơn, khách hàng..."
              class="w-80 rounded-[6px]"
              size="small"
            />
          </IconField>
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span>Tổng số: <strong class="text-slate-800">{{ filteredRequests.length }}</strong> yêu cầu giao hàng</span>
        </div>
      </template>
    </Toolbar>

    <!-- CommonDataTable cho Yêu Cầu Giao Hàng -->
    <CommonDataTable
      :value="filteredRequests"
      :columns="requestColumns"
      :rows="10"
      :loading="loadingDeliveries"
      :paginator="filteredRequests.length > 10"
      emptyIcon="pi pi-truck"
      emptyTitle="Chưa có yêu cầu giao hàng nào"
      emptySubtitle="Vào trang 'Giao hàng' và bấm 'Gửi yêu cầu giao' trên đơn bán hàng để tạo lệnh mới."
    >
      <!-- Custom column: id -->
      <template #col-id="{ data }">
        <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200 inline-block whitespace-nowrap">
          #{{ data.id }}
        </span>
      </template>

      <!-- Custom column: order_number (hiển thị số đơn của khách) -->
      <template #col-order_number="{ data }">
        <span class="font-mono text-xs font-semibold text-slate-800 inline-block whitespace-nowrap">
          {{ data.customer_order_number || data.order_number || data.order_id }}
        </span>
      </template>

      <!-- Custom column: customer_name -->
      <template #col-customer_name="{ data }">
        <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
      </template>

      <!-- Custom column: items_count -->
      <template #col-items_count="{ data }">
        <span class="font-mono text-xs font-semibold text-slate-600 bg-slate-100 px-2 py-0.5 rounded inline-block whitespace-nowrap">
          {{ data.items_count }}
        </span>
      </template>

      <!-- Custom column: total_requested_qty -->
      <template #col-total_requested_qty="{ data }">
        <span class="font-mono text-xs font-bold text-blue-600 whitespace-nowrap">
          {{ data.total_requested_qty }}
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

      <!-- Custom column: status -->
      <template #col-status="{ data }">
        <div class="text-center whitespace-nowrap">
          <Badge :severity="getStatusMeta(data.status).severity" :value="getStatusMeta(data.status).label" />
        </div>
      </template>

      <!-- Custom column: requester_name (Người Yêu Cầu) -->
      <template #col-requester_name="{ data }">
        <span class="text-slate-700 text-xs font-medium whitespace-nowrap">{{ data.requester_name }}</span>
      </template>

      <!-- Cột Thao Tác cho Yêu Cầu Giao Hàng -->
      <Column header="Thao Tác" class="text-center whitespace-nowrap" headerClass="whitespace-nowrap" style="width: 110px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
            <!-- Nút View Dialog xem các mã hàng yêu cầu giao -->
            <Button
              icon="pi pi-eye"
              outlined
              size="small"
              severity="secondary"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xem các mã hàng yêu cầu giao'"
              @click="openRequestDetailDialog(data)"
            />

            <!-- Nút Duyệt yêu cầu -->
            <Button
              v-if="data.status === 'PENDING'"
              icon="pi pi-check"
              outlined
              size="small"
              severity="success"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              :disabled="!canApproveRequest"
              v-tooltip.bottom="canApproveRequest ? 'Phê duyệt lệnh giao hàng này' : 'Cần quyền delivery.manage để duyệt'"
              @click="confirmApproveRequest(data)"
            />

            <!-- Nút Lập chứng từ bán hàng (khi đã duyệt và chưa tạo chứng từ) -->
            <Button
              v-else-if="data.status === 'APPROVED' && canCreateSaleInvoice"
              icon="pi pi-file-plus"
              outlined
              size="small"
              severity="info"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Tạo chứng từ bán hàng để kho xuất hàng'"
              @click="openInvoiceDialog(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Dialog Xem chi tiết các mã hàng của yêu cầu giao -->
    <DeliveryRequestDetailDialog
      v-model:visible="showRequestDetailDialog"
      :delivery-request="selectedRequest"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import DeliveryRequestDetailDialog from '../../components/delivery/DeliveryRequestDetailDialog.vue';

import { salesApi } from '../../api/sales.api';
import { identityApi } from '../../api/identity.api';
import { hasPermission } from '../../utils/rbac';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const router = useRouter();
const toast = useToast();
const confirm = useConfirm();

// State Data
const deliveryRequests = ref([]);
const usersList = ref([]);
const loadingDeliveries = ref(false);
const requestSearchQuery = ref('');

// Lookup Map người dùng (Identity Users)
const usersMap = computed(() => {
  const map = new Map();
  usersList.value.forEach((u) => {
    if (u.id) {
      map.set(Number(u.id), u);
      map.set(String(u.id), u);
    }
  });
  return map;
});

const resolveUserName = (userId, fallbackName = '') => {
  if (userId && usersMap.value.has(Number(userId))) {
    const u = usersMap.value.get(Number(userId));
    return u.name || u.full_name || fallbackName || 'N/A';
  }
  return fallbackName || 'N/A';
};

// Dialog states
const showRequestDetailDialog = ref(false);
const selectedRequest = ref(null);

// Status mapper
const getStatusMeta = (st) => {
  const map = {
    PENDING: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt', severity: 'info' },
    INVOICED: { label: 'Đã lập CT', severity: 'secondary' },
    PARTIAL: { label: 'Giao 1 phần', severity: 'warn' },
    DELIVERED: { label: 'Đã giao', severity: 'success' },
    REJECTED: { label: 'Từ chối', severity: 'danger' },
    CANCELLED: { label: 'Đã hủy', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

// Cấu hình cột: Yêu cầu giao hàng (Đã dời Người Yêu Cầu xuống sau Trạng Thái, ẩn Địa chỉ ra Dialog)
const requestColumns = [
  { field: 'id', header: 'ID', sortable: true, width: '70px', align: 'center' },
  { field: 'order_number', header: 'Số Đơn Hàng', sortable: true, width: '200px' },
  { field: 'customer_name', header: 'Khách Hàng', sortable: true },
  { field: 'items_count', header: 'Số Item', sortable: true, width: '90px', align: 'center' },
  { field: 'total_requested_qty', header: 'SL Yêu Cầu', sortable: true, width: '110px', align: 'center' },
  { field: 'total_delivered_qty', header: 'SL Đã Giao', sortable: true, width: '130px', align: 'center' },
  { field: 'total_remaining_qty', header: 'SL Chưa Giao', sortable: true, width: '120px', align: 'center' },
  { field: 'status', header: 'Trạng Thái', sortable: true, width: '140px', align: 'center' },
  { field: 'requester_name', header: 'Người Yêu Cầu', sortable: true, width: '160px' }
];

// Quyền duyệt yêu cầu giao hàng
const canApproveRequest = computed(() => {
  return hasPermission('delivery.manage', props.currentUser);
});
const canCreateSaleInvoice = computed(() => hasPermission('sale-invoice.create', props.currentUser));

// Tải dữ liệu Yêu Cầu Giao Hàng và Danh sách người dùng để join ID
const loadDeliveries = async () => {
  loadingDeliveries.value = true;
  try {
    const [delivRes, usersRes] = await Promise.all([
      salesApi.getDeliveries(),
      identityApi.getUsers().catch(() => ({ data: [] }))
    ]);
    deliveryRequests.value = delivRes.data || [];
    usersList.value = usersRes.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách giao hàng:', err);
  } finally {
    loadingDeliveries.value = false;
  }
};

onMounted(() => {
  loadDeliveries();
});

// Lọc yêu cầu giao hàng theo từ khóa tìm kiếm
const filteredRequests = computed(() => {
  const query = requestSearchQuery.value.trim().toLowerCase();
  return deliveryRequests.value
    .map((d) => {
      const items = d.items || [];
      const totalReq = items.reduce((sum, it) => sum + (Number(it.requested_quantity) || Number(it.quantity) || 0), 0);
      const totalDelivered = items.reduce((sum, it) => sum + (Number(it.delivered_quantity) || 0), 0);
      const totalRemaining = Math.max(0, totalReq - totalDelivered);

      const reqUserId = d.requested_by || d.created_by || d.user_id || d.order?.created_by || d.order?.assigned_to;
      const rawRequester = d.created_by_name || d.salesperson || d.order?.created_by_name || '';
      const requesterName = resolveUserName(reqUserId, rawRequester);

      return {
        ...d,
        order_number: d.order_number || (d.order ? d.order.order_number : `#${d.order_id}`),
        customer_order_number: d.order?.customer_order_number || d.customer_order_number,
        customer_name: d.customer_name || (d.order ? d.order.customer_name : 'N/A'),
        requester_name: requesterName,
        salesperson: requesterName,
        shipping_address: d.shipping_address || (d.order ? d.order.shipping_address : ''),
        items_count: items.length,
        total_requested_qty: totalReq,
        total_delivered_qty: totalDelivered,
        total_remaining_qty: totalRemaining
      };
    })
    .filter((d) => {
      if (!query) return true;
      return (
        String(d.id).includes(query) ||
        String(d.order_number || '').toLowerCase().includes(query) ||
        String(d.customer_order_number || '').toLowerCase().includes(query) ||
        String(d.customer_name || '').toLowerCase().includes(query) ||
        String(d.requester_name || '').toLowerCase().includes(query) ||
        String(d.shipping_address || '').toLowerCase().includes(query)
      );
    });
});

const openRequestDetailDialog = (req) => {
  selectedRequest.value = req;
  showRequestDetailDialog.value = true;
};

const confirmApproveRequest = (req) => {
  confirm.require({
    header: 'Phê Duyệt Lệnh Giao Hàng',
    message: `Bạn có chắc chắn muốn phê duyệt Yêu cầu giao hàng #${req.id} cho đơn ${req.customer_order_number || req.order_number} không?`,
    icon: 'pi pi-check-circle',
    rejectLabel: 'Hủy bỏ',
    rejectProps: { severity: 'secondary', outlined: true },
    acceptLabel: 'Đồng ý duyệt',
    acceptProps: { severity: 'success' },
    accept: async () => {
      try {
        await salesApi.approveDelivery(req.id, props.currentUser?.name || 'admin');
        toast.add({
          severity: 'success',
          summary: 'Đã duyệt yêu cầu',
          detail: `Phiếu yêu cầu giao hàng #${req.id} đã được phê duyệt thành công!`,
          life: 3000
        });
        loadDeliveries();
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
  sessionStorage.setItem(
    'pending_invoice_creation',
    JSON.stringify({
      delivery_id: req.id,
      delivery_request_number: req.request_number || req.id,
      order_id: req.order_id,
      order_number: req.order_number || req.order?.order_number,
      customer_order_number: req.customer_order_number || req.order?.customer_order_number,
      customer_id: req.customer_id || req.order?.customer_id,
      customer_name: req.customer_name,
      warehouse_name: req.warehouse_name || 'Kho Tổng Miền Bắc (Hà Nội)',
      shipping_address: req.shipping_address,
      items: req.items || []
    })
  );
  router.push({
    path: '/invoice/create',
    query: {
      delivery_request_id: req.id,
      from: 'delivery-request'
    }
  });
};
</script>
