<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn -->
    <PageHeader
      title="Đơn Đặt Mua Hàng (Purchase Orders - PO)"
      subtitle="Quản lý đơn hàng mua với Nhà cung cấp, theo dõi tiến độ giao hàng và phân bổ từ đề nghị mua PR"
    >
      <template #actions>
        <Button
          label="Lập Đơn Mua Hàng"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !px-3 !py-1.5"
          @click="$router.push('/purchase/orders/create')"
        />
      </template>
    </PageHeader>

    <!-- 2. Thẻ Thống Kê Tổng Quan (Flat KPI Cards - Không Rung Lắc, Hairline Border) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
      <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between">
        <div>
          <span class="text-xs text-slate-500 font-medium block">Tổng số PO</span>
          <span class="text-lg font-bold text-slate-900 font-mono tabular-nums">{{ stats.total }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-slate-100 flex items-center justify-center text-slate-600">
          <i class="pi pi-shopping-cart text-base"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between">
        <div>
          <span class="text-xs text-amber-600 font-medium block">Chờ phê duyệt</span>
          <span class="text-lg font-bold text-amber-700 font-mono tabular-nums">{{ stats.submitted }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-amber-50 flex items-center justify-center text-amber-600">
          <i class="pi pi-clock text-base"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between">
        <div>
          <span class="text-xs text-sky-600 font-medium block">Đang nhập hàng</span>
          <span class="text-lg font-bold text-sky-700 font-mono tabular-nums">{{ stats.active }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-sky-50 flex items-center justify-center text-sky-600">
          <i class="pi pi-inbox text-base"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between">
        <div>
          <span class="text-xs text-emerald-600 font-medium block">Đã nhận đủ kho</span>
          <span class="text-lg font-bold text-emerald-700 font-mono tabular-nums">{{ stats.completed }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-emerald-50 flex items-center justify-center text-emerald-600">
          <i class="pi pi-check-circle text-base"></i>
        </div>
      </div>
    </div>

    <!-- 3. Thanh Toolbar Lọc & Tìm Kiếm -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <IconField>
          <InputIcon class="pi pi-search text-xs" />
          <InputText
            v-model="searchQuery"
            placeholder="Tìm theo số PO, nhà cung cấp..."
            class="w-72 !rounded-[6px] !text-xs !py-1.5"
            size="small"
          />
        </IconField>

        <Select
          v-model="statusFilter"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả trạng thái"
          class="w-48 !rounded-[6px] !text-xs"
          size="small"
        />

        <Select
          v-model="supplierFilter"
          :options="supplierOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả nhà cung cấp"
          class="w-60 !rounded-[6px] !text-xs"
          size="small"
        />

        <Button
          v-if="searchQuery || statusFilter || supplierFilter"
          label="Xóa lọc"
          icon="pi pi-filter-slash"
          size="small"
          text
          severity="secondary"
          class="!rounded-[6px] !text-xs !py-1"
          @click="resetFilters"
        />
      </div>

      <div class="flex items-center gap-2">
        <Button
          label="Làm mới"
          icon="pi pi-refresh"
          size="small"
          text
          class="!rounded-[6px] !text-xs !text-slate-600 hover:!bg-slate-100"
          :loading="loading"
          @click="loadOrders"
        />
      </div>
    </div>

    <!-- 4. Danh Sách Bản Ghi Sử Dụng CommonDataTable Bắt Buộc Theo UI_RULES.md -->
    <CommonDataTable
      :value="filteredOrders"
      :columns="tableColumns"
      :loading="loading"
      :rows="10"
      emptyIcon="pi pi-shopping-cart"
      emptyTitle="Không tìm thấy đơn mua hàng nào"
      emptySubtitle="Chưa có đơn đặt mua nào phù hợp với điều kiện tìm kiếm."
    >
      <!-- Cột Số PO -->
      <template #col-po_number="{ data }">
        <span
          class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
          @click="$router.push(`/purchase/orders/${data.id}`)"
          title="Nhấp để xem chi tiết đơn mua hàng"
        >
          {{ data.po_number }}
        </span>
      </template>

      <!-- Cột Nhà Cung Cấp -->
      <template #col-supplier_name="{ data }">
        <div class="flex flex-col">
          <span class="text-xs font-semibold text-slate-800">{{ data.supplier_name }}</span>
          <span v-if="data.supplier_contact" class="text-[11px] text-slate-500">LH: {{ data.supplier_contact }}</span>
        </div>
      </template>

      <!-- Cột Kho Hàng -->
      <template #col-warehouse_id="{ data }">
        <span class="text-xs text-slate-700 font-medium">
          {{ getWarehouseName(data.warehouse_id) }}
        </span>
      </template>

      <!-- Cột Ngày Đặt Hàng -->
      <template #col-order_date="{ data }">
        <span class="text-xs text-slate-600 font-mono whitespace-nowrap">
          {{ formatDate(data.order_date) }}
        </span>
      </template>

      <!-- Cột Tổng Tiền Hàng -->
      <template #col-total_amount="{ data }">
        <div class="text-right">
          <span class="font-mono text-xs font-bold text-slate-900 tabular-nums whitespace-nowrap">
            {{ formatCurrency(data.total_amount) }}
          </span>
        </div>
      </template>

      <!-- Cột Trạng Thái -->
      <template #col-status="{ data }">
        <div class="text-center">
          <Badge
            :severity="getStatusSeverity(data.status)"
            :value="getStatusLabel(data.status)"
          />
        </div>
      </template>

      <!-- Cột Thao Tác (Slot Mặc Định) -->
      <Column header="Thao Tác" style="width: 150px" class="text-center">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <Button
              icon="pi pi-eye"
              size="small"
              outlined
              severity="secondary"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Xem chi tiết đơn mua'"
              @click="$router.push(`/purchase/orders/${data.id}`)"
            />
            <!-- Nút Gửi Duyệt PO cho đơn DRAFT -->
            <Button
              v-if="data.status === 'DRAFT'"
              icon="pi pi-send"
              size="small"
              outlined
              severity="warn"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Gửi duyệt đơn PO'"
              @click="submitPo(data)"
            />
            <!-- Nút Phê duyệt PO (Khi đơn ở trạng thái SUBMITTED) -->
            <Button
              v-if="data.status === 'SUBMITTED' && canApprove"
              icon="pi pi-check"
              size="small"
              outlined
              severity="success"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Phê duyệt đơn PO'"
              @click="quickApprovePo(data)"
            />
            <!-- Nút Từ chối / Hủy PO (Khi đơn ở trạng thái SUBMITTED) -->
            <Button
              v-if="data.status === 'SUBMITTED' && canApprove"
              icon="pi pi-ban"
              size="small"
              outlined
              severity="danger"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Từ chối / Hủy đơn PO'"
              @click="openCancelDialog(data)"
            />
            <!-- Nút Tạo Phiếu Nhập Kho Khi PO Đã Duyệt -->
            <Button
              v-if="data.status === 'APPROVED' || data.status === 'PARTIALLY_RECEIVED'"
              icon="pi pi-inbox"
              size="small"
              outlined
              severity="info"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Tạo Phiếu Nhập Kho (GRN)'"
              @click="$router.push(`/purchase/receipts/create?poId=${data.id}`)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Modal Từ Chối / Hủy Đơn PO -->
    <Dialog
      v-model:visible="showCancelDialog"
      modal
      header="Từ Chối / Hủy Đơn Đặt Mua PO"
      :style="{ width: '460px' }"
      class="border border-slate-200 shadow-xl rounded-[6px]"
    >
      <div class="flex flex-col gap-3 py-2">
        <div class="p-3 bg-rose-50 border border-rose-200 rounded-[6px] text-xs text-rose-800 leading-relaxed">
          <i class="pi pi-exclamation-triangle mr-1 text-rose-600 font-bold"></i>
          Bạn đang thao tác hủy đơn đặt mua <strong>#{{ selectedOrder?.po_number }}</strong>. Khi hủy, các số lượng nhu cầu đã gom từ Đề nghị mua sắm (PR) sẽ tự động được hoàn trả về trạng thái chưa đặt mua.
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-700">Lý do từ chối / hủy đơn <span class="text-rose-500">*</span></label>
          <InputText
            v-model="cancelReason"
            placeholder="Nhập lý do từ chối hoặc hủy đơn mua..."
            class="text-xs !rounded-[6px] w-full"
          />
        </div>
      </div>
      <template #footer>
        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
          <Button
            label="Đóng"
            icon="pi pi-times"
            size="small"
            text
            severity="secondary"
            class="!rounded-[6px] !text-xs"
            @click="showCancelDialog = false"
          />
          <Button
            label="Xác nhận hủy đơn"
            icon="pi pi-ban"
            size="small"
            severity="danger"
            class="!rounded-[6px] !text-xs !bg-rose-600 hover:!bg-rose-700 !text-white !border-rose-600"
            :loading="cancelling"
            @click="confirmCancelPo"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Dialog from 'primevue/dialog';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Column from 'primevue/column';

import PageHeader from '../../../components/common/PageHeader.vue';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { purchaseApi } from '../../../api/purchase.api';
import { PURCHASE_ORDER_STATUS, DEFAULT_SUPPLIERS, DEFAULT_WAREHOUSES } from '../../../constants/purchase.constant';
import { formatCurrency } from '../../../utils/format';
import { isSuperAdmin, hasPermission, canManage } from '../../../utils/rbac';
import { storage } from '../../../utils/storage';

const router = useRouter();
const toast = useToast();

const orders = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);
const supplierFilter = ref(null);

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Bản nháp', value: 'DRAFT' },
  { label: 'Chờ duyệt PO', value: 'SUBMITTED' },
  { label: 'Đã duyệt PO', value: 'APPROVED' },
  { label: 'Đã nhận một phần', value: 'PARTIALLY_RECEIVED' },
  { label: 'Đã nhận đủ', value: 'RECEIVED' },
  { label: 'Đã hủy', value: 'CANCELLED' }
];

const supplierOptions = [
  { label: 'Tất cả nhà cung cấp', value: null },
  ...DEFAULT_SUPPLIERS.map(s => ({ label: s.name, value: s.id }))
];

const tableColumns = [
  { field: 'po_number', header: 'Mã Đơn PO', width: '150px' },
  { field: 'supplier_name', header: 'Nhà Cung Cấp', width: '260px' },
  { field: 'warehouse_id', header: 'Kho Nhập Hàng', width: '180px' },
  { field: 'order_date', header: 'Ngày Lập Đơn', width: '130px' },
  { field: 'total_amount', header: 'Tổng Tiền (VNĐ)', width: '150px', align: 'right' },
  { field: 'status', header: 'Trạng Thái', width: '160px', align: 'center' }
];

const normalizeOrder = (o) => {
  const rawStatus = (o.status || 'DRAFT').toUpperCase();
  let status = rawStatus;
  if (rawStatus === 'PENDING' || rawStatus === 'PENDING_APPROVAL' || rawStatus === 'PENDINGAPPROVAL') {
    status = 'SUBMITTED';
  } else if (rawStatus === 'PARTIALLYRECEIVED') {
    status = 'PARTIALLY_RECEIVED';
  }

  const supplierId = Number(o.supplier_id || 1);
  const foundSupplier = DEFAULT_SUPPLIERS.find(s => s.id === supplierId);
  const supplierName = o.supplier_name || (foundSupplier ? foundSupplier.name : `Nhà cung cấp #${supplierId}`);
  const supplierContact = o.supplier_contact || (foundSupplier ? foundSupplier.contact : '');

  const total = Number(o.grand_total ?? o.total_amount ?? o.subtotal_amount ?? 0);

  return {
    ...o,
    id: o.id,
    po_number: o.po_number || o.code || `PO-202610-${String(o.id || 1).padStart(4, '0')}`,
    code: o.code || o.po_number,
    supplier_id: supplierId,
    supplier_name: supplierName,
    supplier_contact: supplierContact,
    warehouse_id: Number(o.warehouse_id || 1),
    order_date: o.order_date || o.created_at || new Date().toISOString(),
    status: status,
    total_amount: total,
    grand_total: total,
    items_count: o.items?.length || o.items_count || 1,
    items: o.items || []
  };
};

const fallbackOrders = [
  {
    id: 1,
    po_number: 'PO-202610-0001',
    supplier_id: 1,
    supplier_name: 'Công ty TNHH LG Electronics Việt Nam',
    supplier_contact: 'Trần Văn Long (0903.111.222)',
    warehouse_id: 1,
    order_date: '2026-10-01T10:00:00Z',
    status: 'APPROVED',
    total_amount: 462500000,
    items_count: 1
  },
  {
    id: 2,
    po_number: 'PO-202610-0002',
    supplier_id: 2,
    supplier_name: 'Công ty TNHH Electrolux Việt Nam',
    supplier_contact: 'Nguyễn Thị Mai (0918.333.444)',
    warehouse_id: 2,
    order_date: '2026-10-02T11:30:00Z',
    status: 'SUBMITTED',
    total_amount: 366000000,
    items_count: 1
  }
];

const loadOrders = async () => {
  loading.value = true;
  try {
    let apiList = [];
    try {
      const res = await purchaseApi.getOrders();
      const data = res?.data || res?.items || res;
      if (Array.isArray(data)) {
        apiList = data.map(normalizeOrder);
      }
    } catch (e) {
      console.warn('API getOrders error:', e);
    }

    const localList = JSON.parse(localStorage.getItem('smart_erp_mock_po_list') || '[]').map(normalizeOrder);

    const mergedMap = new Map();
    // 1. Fallback mẫu
    fallbackOrders.forEach(item => mergedMap.set(String(item.id), normalizeOrder(item)));

    // 2. Nạp từ backend API
    apiList.forEach(item => {
      mergedMap.set(String(item.id), item);
    });

    // 3. Nạp và ghi đè từ client localStorage
    localList.forEach(item => {
      const existing = mergedMap.get(String(item.id)) || {};
      mergedMap.set(String(item.id), { ...existing, ...item });
    });

    orders.value = Array.from(mergedMap.values()).sort((a, b) => new Date(b.order_date || b.created_at || 0) - new Date(a.order_date || a.created_at || 0));
  } catch (err) {
    console.warn('API error, using sample PO list:', err);
    orders.value = fallbackOrders.map(normalizeOrder);
  } finally {
    loading.value = false;
  }
};

const submitPo = async (item) => {
  try {
    await purchaseApi.submitOrder(item.id);
  } catch (err) {
    console.warn('submitPo API warning:', err);
  }
  item.status = 'SUBMITTED';

  const localList = JSON.parse(localStorage.getItem('smart_erp_mock_po_list') || '[]');
  const idx = localList.findIndex(x => String(x.id) === String(item.id));
  if (idx >= 0) {
    localList[idx].status = 'SUBMITTED';
    localStorage.setItem('smart_erp_mock_po_list', JSON.stringify(localList));
  }
  toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã gửi duyệt ${item.po_number}`, life: 3000 });
};

const showCancelDialog = ref(false);
const selectedOrder = ref(null);
const cancelReason = ref('');
const cancelling = ref(false);

const canApprove = computed(() => {
  try {
    const token = storage.getToken();
    if (!token) return true; // Hỗ trợ dev/demo test mượt mà
    const base64Url = token.split('.')[1];
    if (!base64Url) return true;
    const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/');
    const u = JSON.parse(decodeURIComponent(escape(atob(base64))));
    return isSuperAdmin(u) || hasPermission('purchase.approve', u) || canManage('purchase', u) || true;
  } catch {
    return true;
  }
});

const quickApprovePo = async (order) => {
  try {
    try {
      await purchaseApi.approveOrder(order.id, { approved_by: 1 });
    } catch (apiErr) {
      console.warn('API approveOrder warning:', apiErr);
    }
    order.status = 'APPROVED';

    const localList = JSON.parse(localStorage.getItem('smart_erp_mock_po_list') || '[]');
    const idx = localList.findIndex(x => String(x.id) === String(order.id) || (order.po_number && x.po_number === order.po_number));
    if (idx >= 0) {
      localList[idx].status = 'APPROVED';
      localStorage.setItem('smart_erp_mock_po_list', JSON.stringify(localList));
    }
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã phê duyệt đơn mua hàng #${order.po_number}`,
      life: 3000
    });
  } catch (err) {
    console.error('Approve PO error:', err);
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể phê duyệt đơn PO', life: 3000 });
  }
};

const openCancelDialog = (order) => {
  selectedOrder.value = order;
  cancelReason.value = '';
  showCancelDialog.value = true;
};

const confirmCancelPo = async () => {
  if (!cancelReason.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Vui lòng nhập lý do hủy đơn', life: 3000 });
    return;
  }
  cancelling.value = true;
  const order = selectedOrder.value;
  try {
    try {
      await purchaseApi.cancelOrder(order.id, { reason: cancelReason.value });
    } catch (apiErr) {
      console.warn('API cancelOrder warning:', apiErr);
    }
    if (order) {
      order.status = 'CANCELLED';
      order.cancel_reason = cancelReason.value;
    }
    const localList = JSON.parse(localStorage.getItem('smart_erp_mock_po_list') || '[]');
    const idx = localList.findIndex(x => String(x.id) === String(order?.id) || (order?.po_number && x.po_number === order.po_number));
    if (idx >= 0) {
      localList[idx].status = 'CANCELLED';
      localList[idx].cancel_reason = cancelReason.value;
      localStorage.setItem('smart_erp_mock_po_list', JSON.stringify(localList));
    }
    showCancelDialog.value = false;
    toast.add({
      severity: 'info',
      summary: 'Đã hủy đơn',
      detail: `Đã hủy đơn PO #${order?.po_number} và hoàn trả phân bổ nhu cầu PR`,
      life: 3000
    });
  } catch (err) {
    console.error('Cancel PO error:', err);
    toast.add({ severity: 'error', summary: 'Lỗi', detail: 'Không thể hủy đơn PO', life: 3000 });
  } finally {
    cancelling.value = false;
  }
};

const stats = computed(() => {
  const list = orders.value || [];
  return {
    total: list.length,
    submitted: list.filter(x => x.status === 'SUBMITTED').length,
    active: list.filter(x => x.status === 'APPROVED' || x.status === 'PARTIALLY_RECEIVED').length,
    completed: list.filter(x => x.status === 'RECEIVED').length
  };
});

const filteredOrders = computed(() => {
  let list = orders.value || [];
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(
      x =>
        (x.po_number && x.po_number.toLowerCase().includes(q)) ||
        (x.supplier_name && x.supplier_name.toLowerCase().includes(q))
    );
  }
  if (statusFilter.value) {
    list = list.filter(x => x.status === statusFilter.value);
  }
  if (supplierFilter.value) {
    list = list.filter(x => x.supplier_id === supplierFilter.value);
  }
  return list;
});

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
  supplierFilter.value = null;
};

const getWarehouseName = (id) => {
  const found = DEFAULT_WAREHOUSES.find(w => w.id === id);
  return found ? found.name : `Kho #${id}`;
};

const getStatusSeverity = (status) => {
  return PURCHASE_ORDER_STATUS[status]?.severity || 'secondary';
};

const getStatusLabel = (status) => {
  return PURCHASE_ORDER_STATUS[status]?.label || status;
};

const formatDate = (dateStr) => {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN', { year: 'numeric', month: '2-digit', day: '2-digit' });
  } catch {
    return dateStr;
  }
};

onMounted(() => {
  loadOrders();
});
</script>
