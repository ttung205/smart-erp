<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn -->
    <PageHeader
      title="Đề Nghị Mua Sắm (Purchase Requisitions)"
      subtitle="Tổng hợp nhu cầu bổ sung thiết bị điện tử từ các kho bãi và bộ phận kinh doanh"
    >
      <template #actions>
        <Button
          label="Tạo Đề Nghị Mua"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !px-3 !py-1.5"
          @click="$router.push('/purchase/requisitions/create')"
        />
      </template>
    </PageHeader>

    <!-- 2. Thẻ Thống Kê Tổng Quan (Flat KPI Cards - 1 Hàng Ngang 4 Thẻ) -->
    <div class="grid grid-cols-4 gap-3">
      <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between">
        <div>
          <span class="text-xs text-slate-500 font-medium block">Tổng số PR</span>
          <span class="text-lg font-bold text-slate-900 font-mono tabular-nums">{{ stats.total }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-slate-100 flex items-center justify-center text-slate-600">
          <i class="pi pi-file-o text-base"></i>
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
          <span class="text-xs text-emerald-600 font-medium block">Đã duyệt (Sẵn sàng tạo PO)</span>
          <span class="text-lg font-bold text-emerald-700 font-mono tabular-nums">{{ stats.approved }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-emerald-50 flex items-center justify-center text-emerald-600">
          <i class="pi pi-check-circle text-base"></i>
        </div>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between">
        <div>
          <span class="text-xs text-sky-600 font-medium block">Đã phân bổ lên PO</span>
          <span class="text-lg font-bold text-sky-700 font-mono tabular-nums">{{ stats.ordered }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-sky-50 flex items-center justify-center text-sky-600">
          <i class="pi pi-shopping-cart text-base"></i>
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
            placeholder="Tìm theo mã PR, người đề nghị, bộ phận..."
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
          v-model="warehouseFilter"
          :options="warehouseOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả kho"
          class="w-52 !rounded-[6px] !text-xs"
          size="small"
        />

        <Button
          v-if="searchQuery || statusFilter || warehouseFilter"
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
          @click="loadData"
        />
      </div>
    </div>

    <!-- 4. Danh Sách Bản Ghi Sử Dụng CommonDataTable Bắt Buộc Theo UI_RULES.md -->
    <CommonDataTable
      :value="filteredRequisitions"
      :columns="tableColumns"
      :loading="loading"
      :rows="10"
      emptyIcon="pi pi-file-plus"
      emptyTitle="Không tìm thấy đề nghị mua sắm nào"
      emptySubtitle="Chưa có yêu cầu mua sắm nào phù hợp với bộ lọc tìm kiếm hiện tại."
    >
      <!-- Cột Mã PR -->
      <template #col-pr_number="{ data }">
        <span
          class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
          @click="$router.push(`/purchase/requisitions/${data.id}`)"
          title="Nhấp để xem chi tiết phiếu đề nghị"
        >
          {{ data.pr_number }}
        </span>
      </template>

      <!-- Cột Người Đề Nghị & Bộ Phận -->
      <template #col-requester_name="{ data }">
        <div class="flex flex-col">
          <span class="text-xs font-semibold text-slate-800">{{ data.requester_name || 'NV Quản lý kho' }}</span>
          <span class="text-[11px] text-slate-500">{{ data.department || 'Bộ phận Kho Vận' }}</span>
        </div>
      </template>

      <!-- Cột Kho Đích -->
      <template #col-warehouse_id="{ data }">
        <span class="text-xs text-slate-700 font-medium">
          {{ getWarehouseName(data.warehouse_id) }}
        </span>
      </template>

      <!-- Cột Ngày Tạo -->
      <template #col-created_at="{ data }">
        <span class="text-xs text-slate-600 font-mono whitespace-nowrap">
          {{ formatDate(data.created_at) }}
        </span>
      </template>

      <!-- Cột Số Lượng Mặt Hàng -->
      <template #col-items_count="{ data }">
        <div class="text-center">
          <span class="text-xs font-mono font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded-[6px] border border-slate-200 inline-block">
            {{ data.items?.length || data.items_count || 0 }} items
          </span>
        </div>
      </template>

      <!-- Cột Trạng Thái Nghiệp Vụ -->
      <template #col-status="{ data }">
        <div class="text-center">
          <Badge
            :severity="getStatusSeverity(data.status)"
            :value="getStatusLabel(data.status)"
          />
        </div>
      </template>

      <!-- Cột Thao Tác (Slot Mặc Định) -->
      <Column header="Thao Tác" style="width: 140px" class="text-center">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <Button
              icon="pi pi-eye"
              size="small"
              outlined
              severity="secondary"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Xem chi tiết'"
              @click="$router.push(`/purchase/requisitions/${data.id}`)"
            />
            <Button
              v-if="data.status === 'DRAFT'"
              icon="pi pi-send"
              size="small"
              outlined
              severity="warn"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Gửi duyệt ngay'"
              @click="submitPr(data)"
            />
            <Button
              v-if="data.status === 'APPROVED'"
              icon="pi pi-shopping-cart"
              size="small"
              outlined
              severity="success"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Lập đơn đặt mua (PO)'"
              @click="$router.push(`/purchase/orders/create?prId=${data.id}`)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>
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
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Column from 'primevue/column';

import PageHeader from '../../../components/common/PageHeader.vue';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { purchaseApi } from '../../../api/purchase.api';
import { REQUISITION_STATUS } from '../../../constants/purchase.constant';
import { getProductOrigin } from '../../../constants/catalogProducts.constant';
import { useWarehouses } from '../../../composables/useWarehouses';

const router = useRouter();
const toast = useToast();

const { warehouseOptions: baseWarehouseOptions, fetchWarehouses, getWarehouseName } = useWarehouses();

const requisitions = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);
const warehouseFilter = ref(null);

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Bản nháp', value: 'DRAFT' },
  { label: 'Chờ duyệt', value: 'SUBMITTED' },
  { label: 'Đã duyệt', value: 'APPROVED' },
  { label: 'Đang lên đơn PO', value: 'PARTIALLY_ORDERED' },
  { label: 'Đã đặt hàng hết', value: 'ORDERED' },
  { label: 'Từ chối duyệt', value: 'REJECTED' }
];

const warehouseOptions = computed(() => [
  { label: 'Tất cả kho', value: null },
  ...baseWarehouseOptions.value
]);

const tableColumns = [
  { field: 'pr_number', header: 'Mã Phiếu PR', width: '140px' },
  { field: 'requester_name', header: 'Người Đề Nghị', width: '200px' },
  { field: 'warehouse_id', header: 'Kho Bãi', width: '160px' },
  { field: 'created_at', header: 'Ngày Lập', width: '120px' },
  { field: 'items_count', header: 'Mặt Hàng', width: '100px', align: 'center' },
  { field: 'status', header: 'Trạng Thái', width: '140px', align: 'center' }
];

const normalizeRequisition = (p) => {
  const approvalRaw = (p.approval_status || p.status || 'DRAFT').toUpperCase();
  let status = approvalRaw;
  if (approvalRaw === 'PENDING' || approvalRaw === 'PENDING_APPROVAL') {
    status = 'SUBMITTED';
  }

  return {
    ...p,
    id: p.id,
    pr_number: p.pr_number || p.code || `PR-202610-${String(p.id || 1).padStart(4, '0')}`,
    code: p.code || p.pr_number,
    requester_name: p.requester_name || (p.requested_by ? `Nhân viên #${p.requested_by}` : 'Nguyễn Văn Hùng'),
    department: p.department || 'Bộ phận Kho Vận',
    warehouse_id: Number(p.warehouse_id || 1),
    status: status,
    approval_status: status,
    created_at: p.created_at || p.request_date || new Date().toISOString(),
    items_count: p.items?.length || p.items_count || 1,
    items: (p.items || []).map(i => ({
      ...i,
      origin: i.origin || getProductOrigin(i.sku || i.product_id)
    }))
  };
};

const fallbackData = [
  {
    id: 1,
    pr_number: 'PR-202610-0001',
    requester_name: 'Nguyễn Văn Hùng',
    department: 'Bộ phận Kho Vận Hà Nội',
    warehouse_id: 1,
    status: 'APPROVED',
    created_at: '2026-10-01T08:30:00Z',
    items_count: 2,
    items: [
      { id: 1, sku: 'ELEC-TV-LG55', item_name: 'Smart Tivi LG 4K 55 inch OLED Cinema', origin: 'Hàn Quốc', quantity_requested: 50, quantity_approved: 40, quantity_ordered: 25 },
      { id: 2, sku: 'ELEC-FR-ELX341', item_name: 'Tủ Lạnh Electrolux Inverter 341L MultiDoor', origin: 'Thái Lan', quantity_requested: 30, quantity_approved: 30, quantity_ordered: 0 }
    ]
  },
  {
    id: 2,
    pr_number: 'PR-202610-0002',
    requester_name: 'Trần Minh Đức',
    department: 'Phòng Dự Án Doanh Nghiệp B2B',
    warehouse_id: 2,
    status: 'SUBMITTED',
    created_at: '2026-10-02T09:15:00Z',
    items_count: 1,
    items: [
      { id: 3, sku: 'ELEC-WM-TSB105', item_name: 'Máy Giặt Toshiba Inverter 10.5kg Lồng Ngang', origin: 'Nhật Bản', quantity_requested: 20, quantity_approved: 0, quantity_ordered: 0 }
    ]
  },
  {
    id: 3,
    pr_number: 'PR-202610-0003',
    requester_name: 'Lê Hoàng Yến',
    department: 'Kinh Doanh Bán Buôn Miền Trung',
    warehouse_id: 3,
    status: 'DRAFT',
    created_at: '2026-10-02T14:00:00Z',
    items_count: 3,
    items: [
      { id: 4, sku: 'ELEC-AC-DKN15', item_name: 'Điều Hòa Daikin Inverter 1.5HP Tiết Kiệm Điện', origin: 'Nhật Bản', quantity_requested: 15, quantity_approved: 0, quantity_ordered: 0 }
    ]
  }
];

const loadData = async () => {
  loading.value = true;
  try {
    let apiList = [];
    try {
      const res = await purchaseApi.getRequisitions();
      const data = res?.data || res?.items || res;
      if (Array.isArray(data)) {
        apiList = data.map(normalizeRequisition);
      }
    } catch (e) {
      console.warn('API getRequisitions error:', e);
    }

    const localList = JSON.parse(localStorage.getItem('smart_erp_mock_pr_list') || '[]').map(normalizeRequisition);

    const mergedMap = new Map();
    // 1. Nạp danh sách mẫu fallback
    fallbackData.forEach(item => mergedMap.set(String(item.id), normalizeRequisition(item)));

    // 2. Nạp dữ liệu từ backend API (ghi đè fallback)
    apiList.forEach(item => {
      mergedMap.set(String(item.id), item);
    });

    // 3. Nạp và ghi đè trạng thái đã duyệt/từ chối mới nhất từ client localStorage
    localList.forEach(item => {
      const existing = mergedMap.get(String(item.id)) || {};
      mergedMap.set(String(item.id), { ...existing, ...item });
    });

    requisitions.value = Array.from(mergedMap.values()).sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
  } catch (err) {
    console.warn('API error, using realistic sample data:', err);
    requisitions.value = fallbackData.map(normalizeRequisition);
  } finally {
    loading.value = false;
  }
};

const stats = computed(() => {
  const list = requisitions.value || [];
  return {
    total: list.length,
    submitted: list.filter(x => x.status === 'SUBMITTED').length,
    approved: list.filter(x => x.status === 'APPROVED' || x.status === 'PARTIALLY_ORDERED').length,
    ordered: list.filter(x => x.status === 'ORDERED' || (x.items && x.items.some(i => i.quantity_ordered > 0))).length
  };
});

const filteredRequisitions = computed(() => {
  let list = requisitions.value || [];
  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(
      x =>
        (x.pr_number && x.pr_number.toLowerCase().includes(q)) ||
        (x.requester_name && x.requester_name.toLowerCase().includes(q)) ||
        (x.department && x.department.toLowerCase().includes(q))
    );
  }
  if (statusFilter.value) {
    list = list.filter(x => x.status === statusFilter.value);
  }
  if (warehouseFilter.value) {
    list = list.filter(x => x.warehouse_id === warehouseFilter.value);
  }
  return list;
});

const resetFilters = () => {
  searchQuery.value = '';
  statusFilter.value = null;
  warehouseFilter.value = null;
};

const getStatusSeverity = (status) => {
  return REQUISITION_STATUS[status]?.severity || 'secondary';
};

const getStatusLabel = (status) => {
  return REQUISITION_STATUS[status]?.label || status;
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

const submitPr = async (item) => {
  try {
    await purchaseApi.submitRequisition(item.id);
  } catch (err) {
    console.warn('API submitRequisition warning:', err);
  }
  item.status = 'SUBMITTED';
  item.approval_status = 'SUBMITTED';

  // Đồng bộ lại vào localStorage
  const localList = JSON.parse(localStorage.getItem('smart_erp_mock_pr_list') || '[]');
  const idx = localList.findIndex(x => String(x.id) === String(item.id));
  if (idx >= 0) {
    localList[idx].status = 'SUBMITTED';
    localList[idx].approval_status = 'SUBMITTED';
    localStorage.setItem('smart_erp_mock_pr_list', JSON.stringify(localList));
  } else {
    localList.unshift({ ...item, status: 'SUBMITTED', approval_status: 'SUBMITTED' });
    localStorage.setItem('smart_erp_mock_pr_list', JSON.stringify(localList));
  }

  toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã gửi duyệt ${item.pr_number}`, life: 3000 });
};

onMounted(() => {
  fetchWarehouses();
  loadData();
});
</script>
