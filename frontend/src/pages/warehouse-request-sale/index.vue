<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Yêu Cầu Xuất Kho Bán Hàng (Warehouse Sales Dispatch)"
      subtitle="Tiếp nhận lệnh xuất kho: Phân công nhân sự Soạn - Đóng - Giao (Đã xử lý) và Ghi sổ trừ tồn kho thực tế"
      badge="Logistics Fulfillment"
      badge-severity="info"
    />

    <!-- KPI Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-2.5">
      <div class="bg-white border border-slate-200 rounded-[6px] p-3 shadow-none">
        <div class="text-[11px] font-medium text-slate-500 uppercase">Chờ Xử Lý</div>
        <div class="text-xl font-bold text-amber-600 mt-1 num-tabular">{{ countWaiting }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-[6px] p-3 shadow-none">
        <div class="text-[11px] font-medium text-slate-500 uppercase">Đã Xử Lý (Đã Phân Soạn / Đóng / Giao)</div>
        <div class="text-xl font-bold text-sky-700 mt-1 num-tabular">{{ countProcessed }}</div>
      </div>
      <div class="bg-white border border-slate-200 rounded-[6px] p-3 shadow-none">
        <div class="text-[11px] font-medium text-slate-500 uppercase">Đã Xuất Kho & Ghi Sổ</div>
        <div class="text-xl font-bold text-emerald-600 mt-1 num-tabular">{{ countDelivered }}</div>
      </div>
    </div>

    <!-- Explanatory Inventory Banner -->
    <div class="bg-sky-50/80 border border-sky-200 rounded-[6px] p-3 text-xs text-sky-900 flex items-start gap-2.5">
      <i class="pi pi-info-circle text-sky-600 text-base mt-0.5 shrink-0"></i>
      <div class="space-y-0.5">
        <div class="font-bold text-sky-900">Quy trình Điều phối & Xuất kho bán hàng</div>
        <p class="m-0 text-[11px] text-sky-800 leading-relaxed">
          Chứng từ bán hàng sau khi tạo sẽ giữ chỗ tồn kho (Reserved). Tại đây thủ kho thực hiện <strong>Phân công Soạn - Đóng - Giao</strong> (chuyển trạng thái sang <strong>Đã xử lý</strong>) và bấm <strong>Ghi sổ xuất kho</strong> để chính thức <strong>trừ tồn kho thật (On-Hand)</strong>.
        </p>
      </div>
    </div>

    <!-- Toolbar Filter -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo mã chứng từ, đơn hàng, khách hàng, nhân sự..."
              class="w-80 rounded-[6px]"
              size="small"
            />
          </IconField>
          <Select
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc tiến độ kho..."
            class="w-52 rounded-[6px]"
            size="small"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span>Tổng số: <strong class="text-slate-800">{{ filteredRequests.length }}</strong> lệnh xuất kho</span>
        </div>
      </template>
    </Toolbar>

    <!-- Main Data Table -->
    <CommonDataTable
      :value="filteredRequests"
      :columns="tableColumns"
      :rows="10"
      :loading="loading"
      :paginator="filteredRequests.length > 10"
      emptyIcon="pi pi-box"
      emptyTitle="Chưa có yêu cầu xuất kho nào"
      emptySubtitle="Khi nhân viên kinh doanh lập chứng từ bán hàng và bấm 'Gửi xuống kho', yêu cầu sẽ xuất hiện tại đây."
    >
      <!-- Custom column: invoice_number -->
      <template #col-invoice_number="{ data }">
        <span class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-[4px] border border-indigo-200">
          {{ data.invoice_number }}
        </span>
      </template>

      <!-- Custom column: order_info -->
      <template #col-order_info="{ data }">
        <div class="flex flex-col text-xs leading-tight">
          <span class="font-mono font-semibold text-slate-800">
            {{ data.customer_order_number || data.order_number || data.order_id || '---' }}
          </span>
          <span class="font-mono text-slate-400 text-[10px]">YC: #{{ data.delivery_request_id || data.delivery_request_number || '---' }}</span>
        </div>
      </template>

      <!-- Custom column: customer_name -->
      <template #col-customer_name="{ data }">
        <div class="flex flex-col max-w-xs">
          <span class="font-semibold text-slate-800 text-xs truncate" :title="data.customer_name">
            {{ data.customer_name }}
          </span>
          <span v-if="data.shipping_address" class="text-[10px] text-slate-400 truncate flex items-center gap-1 mt-0.5" :title="data.shipping_address">
            <i class="pi pi-map-marker text-[9px] text-rose-400"></i>
            {{ data.shipping_address }}
          </span>
        </div>
      </template>

      <!-- Custom column: total_items_qty -->
      <template #col-total_items_qty="{ data }">
        <div class="text-center font-mono text-xs">
          <span class="font-bold text-indigo-700">{{ data.total_qty }}</span>
          <span class="text-slate-400 text-[10px] block">({{ (data.items || []).length }} mã hàng)</span>
        </div>
      </template>

      <!-- Custom column: warehouse_name -->
      <template #col-warehouse_name="{ data }">
        <span class="text-slate-700 text-xs">{{ data.warehouse_name || 'Kho Tổng Miền Bắc' }}</span>
      </template>

      <!-- Custom column: picker_name (Người Soạn) -->
      <template #col-picker_name="{ data }">
        <div v-if="data.picker_name" class="flex items-center gap-1.5 text-xs text-slate-800">
          <i class="pi pi-user text-[11px] text-amber-500"></i>
          <span class="font-medium truncate" :title="data.picker_name">{{ data.picker_name }}</span>
        </div>
        <span v-else class="text-slate-400 italic text-[11px]">Chưa phân công</span>
      </template>

      <!-- Custom column: packer_name (Người Đóng) -->
      <template #col-packer_name="{ data }">
        <div v-if="data.packer_name" class="flex items-center gap-1.5 text-xs text-slate-800">
          <i class="pi pi-box text-[11px] text-indigo-500"></i>
          <span class="font-medium truncate" :title="data.packer_name">{{ data.packer_name }}</span>
        </div>
        <span v-else class="text-slate-400 italic text-[11px]">Chưa phân công</span>
      </template>

      <!-- Custom column: shipping_method (Hình Thức) -->
      <template #col-shipping_method="{ data }">
        <div class="text-xs">
          <div v-if="data.shipping_method === 'CUSTOMER_PICKUP'" class="inline-flex items-center">
            <span class="bg-emerald-50 text-emerald-800 border border-emerald-200 px-2 py-0.5 rounded-[4px] text-[11px] font-semibold flex items-center gap-1">
              <i class="pi pi-user text-[10px]"></i> Khách tới lấy
            </span>
          </div>
          <div v-else-if="data.shipping_method === 'WAREHOUSE_DELIVERY'" class="flex flex-col gap-0.5">
            <span class="bg-sky-50 text-sky-800 border border-sky-200 px-2 py-0.5 rounded-[4px] text-[11px] font-semibold inline-flex items-center gap-1 w-fit">
              <i class="pi pi-truck text-[10px]"></i> Kho giao
            </span>
            <span v-if="data.shipper_name" class="text-[11px] text-slate-600 truncate" :title="data.shipper_name">
              NV: <strong>{{ data.shipper_name }}</strong>
            </span>
          </div>
          <div v-else-if="data.shipping_method === 'THIRD_PARTY_CARRIER'" class="flex flex-col gap-0.5">
            <span class="bg-amber-50 text-amber-800 border border-amber-200 px-2 py-0.5 rounded-[4px] text-[11px] font-semibold inline-flex items-center gap-1 w-fit">
              <i class="pi pi-send text-[10px]"></i> Gửi VC
            </span>
            <span v-if="data.shipper_name || data.shipping_note" class="text-[11px] text-slate-600 truncate" :title="`${data.shipper_name || ''} ${data.shipping_note || ''}`">
              {{ data.shipper_name || 'Hãng VC' }}{{ data.shipping_note ? ' (' + data.shipping_note + ')' : '' }}
            </span>
          </div>
          <div v-else-if="data.shipper_name" class="inline-flex items-center">
            <span class="bg-slate-100 text-slate-700 border border-slate-200 px-2 py-0.5 rounded-[4px] text-[11px] font-medium">
              {{ data.shipper_name }}
            </span>
          </div>
          <span v-else class="text-slate-400 italic text-[11px]">Chưa chọn</span>
        </div>
      </template>

      <!-- Custom column: warehouse_status -->
      <template #col-warehouse_status="{ data }">
        <div class="text-center">
          <Badge
            :severity="getStatusMeta(data.warehouse_status).severity"
            :value="getStatusMeta(data.warehouse_status).label"
          />
        </div>
      </template>

      <!-- Cột Thao Tác -->
      <Column header="Thao Tác" class="text-center" style="width: 110px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <!-- Xem chi tiết mặt hàng cần soạn -->
            <Button
              icon="pi pi-eye"
              outlined
              size="small"
              severity="secondary"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xem chi tiết danh sách mặt hàng'"
              @click="openViewDetails(data)"
            />

            <!-- Phân công Soạn - Đóng - Giao trong 1 Dialog duy nhất -->
            <Button
              v-if="data.accounting_status !== 'POSTED' && data.warehouse_status !== 'DELIVERED'"
              icon="pi pi-users"
              outlined
              size="small"
              severity="info"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Phân công nhân sự Soạn - Đóng - Giao'"
              @click="openAssignModal(data)"
            />

            <!-- Ghi sổ xuất kho & trừ tồn thật ngay tại đây -->
            <Button
              v-if="data.accounting_status !== 'POSTED' && data.warehouse_status !== 'DELIVERED'"
              icon="pi pi-check-circle"
              outlined
              size="small"
              severity="success"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Ghi sổ xuất kho & trừ tồn kho thực tế'"
              @click="openPostModal(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- 1. Modal Xem Chi Tiết Mặt Hàng Cần Soạn -->
    <WarehouseRequestDetailDialog
      v-model:visible="showDetailModal"
      :invoice="selectedInvoice"
    />

    <!-- 2. Modal Phân Công Soạn - Đóng - Giao Hàng (1 Dialog duy nhất) -->
    <WarehouseRequestAssignDialog
      v-model:visible="showAssignModal"
      :invoice="selectedInvoice"
      :form="assignForm"
      :logistic-employees="logisticEmployees"
      :shipping-method-options="shippingMethodOptions"
      :carrier-options="carrierOptions"
      @submit="submitAssign"
    />

    <!-- 3. Modal Ghi Sổ Xuất Kho & Trừ Tồn Thật (Tích hợp trực tiếp) -->
    <WarehouseRequestPostDialog
      v-model:visible="showPostModal"
      :invoice="selectedInvoice"
      :form="postForm"
      :posting-loading="postingLoading"
      @submit="submitPost"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Badge from 'primevue/badge';

import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import WarehouseRequestDetailDialog from '../../components/warehouse-request-sale/WarehouseRequestDetailDialog.vue';
import WarehouseRequestAssignDialog from '../../components/warehouse-request-sale/WarehouseRequestAssignDialog.vue';
import WarehouseRequestPostDialog from '../../components/warehouse-request-sale/WarehouseRequestPostDialog.vue';
import { salesApi } from '../../api/sales.api';
import { identityApi } from '../../api/identity.api';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const toast = useToast();

const requests = ref([]);
const allUsers = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);

const showDetailModal = ref(false);
const showAssignModal = ref(false);
const showPostModal = ref(false);
const selectedInvoice = ref(null);
const postingLoading = ref(false);

const shippingMethodOptions = [
  { label: 'Khách tới lấy', value: 'CUSTOMER_PICKUP' },
  { label: 'Kho giao', value: 'WAREHOUSE_DELIVERY' },
  { label: 'Gửi vận chuyển', value: 'THIRD_PARTY_CARRIER' }
];

const carrierOptions = [
  'Viettel Post',
  'Giao Hàng Tiết Kiệm (GHTK)',
  'Giao Hàng Nhanh (GHN)',
  'VNPost (Bưu Điện)',
  'J&T Express',
  'Ahamove',
  'Lalamove',
  'Đơn vị vận chuyển khác'
];

const assignForm = ref({
  shipping_method: 'CUSTOMER_PICKUP',
  picker_name: '',
  packer_name: '',
  shipper_name: '',
  shipping_note: ''
});

const postForm = ref({
  posted_by: ''
});

const tableColumns = [
  { field: 'invoice_number', header: 'Mã Chứng Từ (INV)', sortable: true, width: '140px' },
  { field: 'order_info', header: 'Đơn Bán / Yêu Cầu', sortable: false, width: '150px' },
  { field: 'customer_name', header: 'Khách Hàng & Địa Chỉ', sortable: true },
  { field: 'total_items_qty', header: 'Tổng SL Xuất', sortable: true, width: '100px', align: 'center' },
  { field: 'warehouse_name', header: 'Kho Xuất', sortable: false, width: '140px' },
  { field: 'picker_name', header: 'Người Soạn', sortable: true, width: '130px' },
  { field: 'packer_name', header: 'Người Đóng', sortable: true, width: '130px' },
  { field: 'shipping_method', header: 'Hình Thức', sortable: true, width: '160px' },
  { field: 'warehouse_status', header: 'Tiến Độ Kho', sortable: true, width: '110px', align: 'center' }
];

const statusOptions = [
  { label: 'Tất cả lệnh kho', value: null },
  { label: 'Chờ xử lý', value: 'SENT_TO_WAREHOUSE' },
  { label: 'Đã xử lý', value: 'PROCESSED' },
  { label: 'Đã xuất kho', value: 'DELIVERED' }
];

// Lọc chỉ lấy nhân viên có role là LOGISTIC (hoặc LOGISTIC_MANAGER, SUPER_ADMIN)
const logisticEmployees = computed(() => {
  if (!allUsers.value || allUsers.value.length === 0) {
    return [
      { id: 1, name: 'Nguyễn Văn Soạn (Logistics)' },
      { id: 2, name: 'Trần Thị Đóng (Kho)' },
      { id: 3, name: 'Lê Văn Giao (Tài xế)' },
      { id: 4, name: 'Phạm Đức Kho (Thủ kho)' }
    ];
  }
  const filtered = allUsers.value.filter((u) => {
    const roles = u.roles || [];
    const hasLogisticRole = roles.some((r) => {
      const code = (r.code || '').toUpperCase();
      const name = (r.name || '').toLowerCase();
      return (
        code === 'LOGISTIC' ||
        code === 'LOGISTIC_MANAGER' ||
        code === 'SUPER_ADMIN' ||
        name.includes('logistic') ||
        name.includes('kho') ||
        name.includes('vận chuyển') ||
        name.includes('giao hàng')
      );
    });
    return hasLogisticRole;
  });

  return filtered.length > 0 ? filtered : allUsers.value;
});

const getStatusMeta = (st) => {
  const map = {
    PENDING_WAREHOUSE: { label: 'Chưa gửi kho', severity: 'secondary' },
    SENT_TO_WAREHOUSE: { label: 'Chờ xử lý', severity: 'warn' },
    PROCESSED: { label: 'Đã xử lý', severity: 'info' },
    PICKING: { label: 'Đã xử lý', severity: 'info' },
    PACKED: { label: 'Đã xử lý', severity: 'info' },
    DISPATCHED: { label: 'Đã xử lý', severity: 'info' },
    DELIVERED: { label: 'Đã xuất kho', severity: 'success' }
  };
  return map[st] || { label: st || 'Chờ xử lý', severity: 'secondary' };
};

const getShippingMethodLabel = (method) => {
  const found = shippingMethodOptions.find((m) => m.value === method);
  return found ? found.label : (method || 'Chưa chọn');
};

const countWaiting = computed(() => {
  return requests.value.filter((r) => r.warehouse_status === 'SENT_TO_WAREHOUSE').length;
});

const countProcessed = computed(() => {
  return requests.value.filter((r) => ['PROCESSED', 'PICKING', 'PACKED', 'DISPATCHED'].includes(r.warehouse_status)).length;
});

const countDelivered = computed(() => {
  return requests.value.filter((r) => r.warehouse_status === 'DELIVERED').length;
});

const loadUsers = async () => {
  try {
    const res = await identityApi.getUsers();
    if (res && res.data) {
      allUsers.value = res.data;
    }
  } catch (err) {
    console.warn('Lỗi tải danh sách người dùng:', err);
  }
};

const loadRequests = async () => {
  loading.value = true;
  try {
    const res = await salesApi.getInvoices();
    const allInvs = res.data || [];
    requests.value = allInvs
      .filter((inv) => inv.warehouse_status && inv.warehouse_status !== 'PENDING_WAREHOUSE')
      .map((inv) => {
        const totalQty = (inv.items || []).reduce((sum, it) => sum + Number(it.quantity || 0), 0);
        return {
          ...inv,
          total_qty: totalQty
        };
      });
  } catch (err) {
    console.error('Lỗi tải danh sách yêu cầu xuất kho:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadRequests();
  loadUsers();
});

const filteredRequests = computed(() => {
  return requests.value.filter((r) => {
    const invNo = String(r.invoice_number || '').toLowerCase();
    const cust = String(r.customer_name || '').toLowerCase();
    const ordNo = String(r.customer_order_number || r.order_number || '').toLowerCase();
    const picker = String(r.picker_name || '').toLowerCase();
    const packer = String(r.packer_name || '').toLowerCase();
    const shipper = String(r.shipper_name || '').toLowerCase();
    const q = searchQuery.value.toLowerCase().trim();

    const matchSearch =
      !q ||
      invNo.includes(q) ||
      cust.includes(q) ||
      ordNo.includes(q) ||
      picker.includes(q) ||
      packer.includes(q) ||
      shipper.includes(q);

    let matchStatus = true;
    if (statusFilter.value === 'PROCESSED') {
      matchStatus = ['PROCESSED', 'PICKING', 'PACKED', 'DISPATCHED'].includes(r.warehouse_status);
    } else if (statusFilter.value) {
      matchStatus = r.warehouse_status === statusFilter.value;
    }

    return matchSearch && matchStatus;
  });
});

const openViewDetails = (inv) => {
  selectedInvoice.value = inv;
  showDetailModal.value = true;
};

const openAssignModal = (inv) => {
  selectedInvoice.value = inv;

  // Lấy ID từ invoice hoặc join relation hoặc tìm theo tên
  let pId = inv.picker_id || inv.warehouse_dispatch?.picker_id || null;
  if (!pId && inv.picker_name) {
    const found = allUsers.value.find((u) => u.name === inv.picker_name);
    if (found) pId = found.id;
  }

  let pkId = inv.packer_id || inv.warehouse_dispatch?.packer_id || null;
  if (!pkId && inv.packer_name) {
    const found = allUsers.value.find((u) => u.name === inv.packer_name);
    if (found) pkId = found.id;
  }

  let sId = inv.shipper_id || inv.warehouse_dispatch?.shipper_id || null;
  if (!sId && inv.shipper_name) {
    const found = allUsers.value.find((u) => u.name === inv.shipper_name);
    if (found) sId = found.id;
  }

  assignForm.value = {
    shipping_method: inv.shipping_method || inv.warehouse_dispatch?.shipping_method || 'CUSTOMER_PICKUP',
    picker_id: pId,
    picker_name: inv.picker_name || '',
    packer_id: pkId,
    packer_name: inv.packer_name || '',
    shipper_id: sId,
    shipper_name: inv.shipper_name || '',
    shipping_note: inv.shipping_note || inv.warehouse_dispatch?.shipping_note || ''
  };
  showAssignModal.value = true;
};

const submitAssign = async () => {
  if (!selectedInvoice.value) return;
  try {
    const pickerUser = allUsers.value.find((u) => u.id === assignForm.value.picker_id);
    const packerUser = allUsers.value.find((u) => u.id === assignForm.value.packer_id);
    const shipperUser = allUsers.value.find((u) => u.id === assignForm.value.shipper_id);

    const payload = {
      shipping_method: assignForm.value.shipping_method,
      picker_id: assignForm.value.picker_id || null,
      picker_name: pickerUser?.name || assignForm.value.picker_name || '',
      packer_id: assignForm.value.packer_id || null,
      packer_name: packerUser?.name || assignForm.value.packer_name || '',
      shipper_id: assignForm.value.shipping_method === 'WAREHOUSE_DELIVERY' ? (assignForm.value.shipper_id || null) : null,
      shipper_name: assignForm.value.shipping_method === 'WAREHOUSE_DELIVERY'
        ? (shipperUser?.name || assignForm.value.shipper_name)
        : assignForm.value.shipper_name,
      shipping_note: assignForm.value.shipping_note,
      status: 'PROCESSED'
    };
    await salesApi.assignLogistics(selectedInvoice.value.id, payload);

    toast.add({
      severity: 'success',
      summary: 'Đã phân công điều phối',
      detail: `Lệnh xuất kho ${selectedInvoice.value.invoice_number} đã cập nhật sang bảng điều phối kho thành công`,
      life: 3000
    });

    showAssignModal.value = false;
    loadRequests();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi cập nhật',
      detail: err.message || 'Không thể cập nhật điều phối kho.',
      life: 3000
    });
  }
};

const openPostModal = (inv) => {
  selectedInvoice.value = inv;
  postForm.value.posted_by = props.currentUser?.name || 'Thủ kho trưởng';
  showPostModal.value = true;
};

const submitPost = async () => {
  if (!selectedInvoice.value) return;
  postingLoading.value = true;
  try {
    await salesApi.warehousePost(selectedInvoice.value.id, {
      posted_by: postForm.value.posted_by
    });

    toast.add({
      severity: 'success',
      summary: 'Ghi Sổ Xuất Kho Thành Công!',
      detail: `Chứng từ ${selectedInvoice.value.invoice_number} đã trừ tồn kho thật và cập nhật tiến độ giao hàng.`,
      life: 5000
    });

    showPostModal.value = false;
    loadRequests();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi ghi sổ xuất kho',
      detail: err.message || 'Không thể ghi sổ xuất kho.',
      life: 4000
    });
  } finally {
    postingLoading.value = false;
  }
};
</script>
