<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header Chuẩn Theo UI Guidelines -->
    <PageHeader
      title="Trả Hàng Mua (Return to Vendor - RTV)"
      subtitle="Quản lý nghiệp vụ xuất trả hàng lỗi, sai quy cách cho Nhà cung cấp, bù trừ công nợ & xuất giảm tồn kho"
    >
      <template #actions>
        <Button
          label="Tạo Phiếu Trả Hàng"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !px-3 !py-1.5"
          @click="$router.push('/purchase/returns/create')"
        />
      </template>
    </PageHeader>

    <!-- 2. Thẻ Thống Kê Tổng Quan (Flat KPI Cards - 1 Hàng Ngang 4 Thẻ) -->
    <div class="grid grid-cols-4 gap-3">
      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-slate-300 transition-colors"
        @click="statusFilter = ''"
      >
        <div>
          <span class="text-xs text-slate-500 font-medium block">Tổng số phiếu trả</span>
          <span class="text-lg font-bold text-slate-800 font-mono tabular-nums">{{ stats.total }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-slate-100 flex items-center justify-center text-slate-600">
          <i class="pi pi-replay text-base"></i>
        </div>
      </div>

      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-amber-300 transition-colors"
        @click="statusFilter = 'Submitted'"
      >
        <div>
          <span class="text-xs text-amber-600 font-medium block">Chờ phê duyệt</span>
          <span class="text-lg font-bold text-amber-700 font-mono tabular-nums">{{ stats.submitted }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-amber-50 flex items-center justify-center text-amber-600">
          <i class="pi pi-clock text-base"></i>
        </div>
      </div>

      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-sky-300 transition-colors"
        @click="statusFilter = 'Approved'"
      >
        <div>
          <span class="text-xs text-sky-600 font-medium block">Đã duyệt chờ xuất kho</span>
          <span class="text-lg font-bold text-sky-700 font-mono tabular-nums">{{ stats.approved }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-sky-50 flex items-center justify-center text-sky-600">
          <i class="pi pi-truck text-base"></i>
        </div>
      </div>

      <div 
        class="bg-white border border-slate-200 rounded-[6px] p-3 flex items-center justify-between cursor-pointer hover:border-emerald-300 transition-colors"
        @click="statusFilter = 'Dispatched'"
      >
        <div>
          <span class="text-xs text-emerald-600 font-medium block">Đã xuất trả hoàn tất</span>
          <span class="text-lg font-bold text-emerald-700 font-mono tabular-nums">{{ stats.dispatched }}</span>
        </div>
        <div class="w-9 h-9 rounded-[6px] bg-emerald-50 flex items-center justify-center text-emerald-600">
          <i class="pi pi-check-circle text-base"></i>
        </div>
      </div>
    </div>

    <!-- 3. Bảng Dữ Liệu & Bộ Lọc (Single Surface Flat Container) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden flex flex-col">
      <!-- Bộ lọc -->
      <div class="p-3 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2 flex-wrap">
          <IconField iconPosition="left">
            <InputIcon class="pi pi-search text-xs text-slate-400" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo mã phiếu, mã PO, NCC, số RMA..."
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

          <Button
            v-if="searchQuery || statusFilter"
            label="Xóa lọc"
            icon="pi pi-filter-slash"
            size="small"
            text
            severity="secondary"
            class="!rounded-[6px] !text-xs !py-1"
            @click="resetFilters"
          />
        </div>

        <span class="text-xs text-slate-500 font-medium">
          Hiển thị <strong>{{ filteredReturns.length }}</strong> phiếu
        </span>
      </div>

      <!-- Bảng Danh Sách Trả Hàng Mua -->
      <CommonDataTable
        :value="filteredReturns"
        :columns="tableColumns"
        :loading="loading"
        :rows="10"
        emptyIcon="pi pi-replay"
        emptyTitle="Không có phiếu trả hàng mua nào"
        emptySubtitle="Chưa có dữ liệu trả hàng mua hoặc không tìm thấy phiếu phù hợp với bộ lọc."
      >
        <!-- Mã Phiếu -->
        <template #col-code="{ data }">
          <span
            class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
            @click="$router.push(`/purchase/returns/${data.id}`)"
            title="Nhấp để xem chi tiết phiếu trả hàng"
          >
            {{ data.code }}
          </span>
        </template>

        <!-- Đơn PO -->
        <template #col-po_code="{ data }">
          <span class="font-mono text-xs font-semibold text-slate-800 bg-slate-100 px-2 py-0.5 rounded-[6px] border border-slate-200 inline-block whitespace-nowrap">
            {{ data.po_code || (data.purchase_order_id ? `PO #${data.purchase_order_id}` : '—') }}
          </span>
        </template>

        <!-- Nhà Cung Cấp -->
        <template #col-supplier_id="{ data }">
          <span class="text-xs text-slate-800 font-medium">
            {{ getSupplierName(data.supplier_id) }}
          </span>
        </template>

        <!-- Kho Xuất Trả -->
        <template #col-warehouse_id="{ data }">
          <span class="text-xs text-slate-600 font-medium">
            {{ getWarehouseName(data.warehouse_id) }}
          </span>
        </template>

        <!-- Số RMA -->
        <template #col-rma_number="{ data }">
          <span v-if="data.rma_number" class="font-mono text-xs text-amber-700 bg-amber-50 px-2 py-0.5 rounded-[6px] border border-amber-200 inline-block">
            {{ data.rma_number }}
          </span>
          <span v-else class="text-xs text-slate-400 italic">Chưa có</span>
        </template>

        <!-- Lý Do -->
        <template #col-reason="{ data }">
          <span class="text-xs text-slate-700">
            {{ getReasonLabel(data.reason) }}
          </span>
        </template>

        <!-- Tổng Giá Trị Trả -->
        <template #col-total_amount="{ data }">
          <span class="text-xs font-bold font-mono text-rose-600">
            {{ formatCurrency(data.total_amount || 0) }}
          </span>
        </template>

        <!-- Trạng Thái -->
        <template #col-status="{ data }">
          <Tag
            :severity="getStatusSeverity(data.status)"
            :value="getStatusLabel(data.status)"
            class="!rounded-[6px] !text-[11px] !font-semibold !px-2 !py-0.5"
          />
        </template>

        <!-- Ngày Tạo -->
        <template #col-created_at="{ data }">
          <span class="text-xs text-slate-500 font-mono">
            {{ formatDate(data.created_at) }}
          </span>
        </template>

        <!-- Cột Thao Tác (Xem chi tiết, không dùng tick xác nhận tắt) -->
        <Column header="Thao tác" alignHeader="right" class="w-28 text-right">
          <template #body="{ data }">
            <div class="flex items-center justify-end gap-1">
              <Button
                icon="pi pi-eye"
                size="small"
                text
                severity="secondary"
                v-tooltip.top="'Xem chi tiết & Xử lý phiếu trả'"
                class="!w-7 !h-7 !rounded-[6px] !p-0"
                @click="$router.push(`/purchase/returns/${data.id}`)"
              />
              <Button
                v-if="String(data.status).toUpperCase() === 'APPROVED'"
                icon="pi pi-truck"
                size="small"
                text
                severity="warning"
                v-tooltip.top="'Xuất kho trả NCC ngay'"
                class="!w-7 !h-7 !rounded-[6px] !p-0 !text-amber-600"
                @click="$router.push(`/purchase/returns/${data.id}`)"
              />
            </div>
          </template>
        </Column>
      </CommonDataTable>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Column from 'primevue/column';

import PageHeader from '../../../components/common/PageHeader.vue';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { purchaseApi } from '../../../api/purchase.api';
import { PURCHASE_RETURN_STATUS, PURCHASE_RETURN_REASONS, DEFAULT_SUPPLIERS } from '../../../constants/purchase.constant';
import { useWarehouses } from '../../../composables/useWarehouses';
import { formatCurrency } from '../../../utils/format';

const router = useRouter();
const toast = useToast();

const returns = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('');

const { warehouses, fetchWarehouses } = useWarehouses();

const tableColumns = [
  { field: 'code', header: 'Mã Phiếu Trả', width: '130px' },
  { field: 'po_code', header: 'Đơn Mua (PO)', width: '120px' },
  { field: 'supplier_id', header: 'Nhà Cung Cấp', minWidth: '180px' },
  { field: 'warehouse_id', header: 'Kho Xuất Trả', minWidth: '150px' },
  { field: 'rma_number', header: 'Số RMA', width: '120px' },
  { field: 'reason', header: 'Lý Do Trả', minWidth: '160px' },
  { field: 'total_amount', header: 'Giá Trị Trả', width: '130px', align: 'right' },
  { field: 'status', header: 'Trạng Thái', width: '120px', align: 'center' },
  { field: 'created_at', header: 'Ngày Tạo', width: '130px' }
];

const statusOptions = [
  { label: 'Tất cả trạng thái', value: '' },
  { label: 'Bản nháp', value: 'Draft' },
  { label: 'Chờ duyệt', value: 'Submitted' },
  { label: 'Đã duyệt', value: 'Approved' },
  { label: 'Đã xuất trả', value: 'Dispatched' },
  { label: 'Đã hủy', value: 'Cancelled' }
];

const stats = computed(() => {
  const all = returns.value;
  return {
    total: all.length,
    draft: all.filter((r) => String(r.status).toUpperCase() === 'DRAFT').length,
    submitted: all.filter((r) => String(r.status).toUpperCase() === 'SUBMITTED').length,
    approved: all.filter((r) => String(r.status).toUpperCase() === 'APPROVED').length,
    dispatched: all.filter((r) => String(r.status).toUpperCase() === 'DISPATCHED').length
  };
});

const filteredReturns = computed(() => {
  return returns.value.filter((item) => {
    const q = searchQuery.value.trim().toLowerCase();
    const matchesSearch =
      !q ||
      String(item.code || '').toLowerCase().includes(q) ||
      String(item.po_code || '').toLowerCase().includes(q) ||
      String(item.rma_number || '').toLowerCase().includes(q) ||
      getSupplierName(item.supplier_id).toLowerCase().includes(q);

    const matchesStatus =
      !statusFilter.value ||
      String(item.status).toUpperCase() === String(statusFilter.value).toUpperCase();

    return matchesSearch && matchesStatus;
  });
});

function getSupplierName(supplierId) {
  const found = DEFAULT_SUPPLIERS.find((s) => s.id === Number(supplierId));
  return found ? found.name : `Nhà cung cấp #${supplierId}`;
}

function getWarehouseName(warehouseId) {
  const found = warehouses.value.find((w) => w.id === Number(warehouseId));
  return found ? found.name : `Kho #${warehouseId}`;
}

function getReasonLabel(reasonCode) {
  const found = PURCHASE_RETURN_REASONS.find((r) => r.value === reasonCode);
  return found ? found.label : reasonCode || 'Chưa xác định';
}

function getStatusSeverity(status) {
  const key = String(status || '').toUpperCase();
  return PURCHASE_RETURN_STATUS[key]?.severity || 'secondary';
}

function getStatusLabel(status) {
  const key = String(status || '').toUpperCase();
  return PURCHASE_RETURN_STATUS[key]?.label || status;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  try {
    const d = new Date(dateStr);
    return d.toLocaleDateString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric'
    });
  } catch {
    return dateStr;
  }
}

function resetFilters() {
  searchQuery.value = '';
  statusFilter.value = '';
}

async function loadReturns() {
  loading.value = true;
  try {
    const res = await purchaseApi.getReturns();
    if (res && res.data) {
      returns.value = res.data;
    } else if (Array.isArray(res)) {
      returns.value = res;
    } else {
      returns.value = [];
    }
  } catch (err) {
    console.error('Lỗi tải danh sách phiếu trả hàng:', err);
    returns.value = [];
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  fetchWarehouses();
  loadReturns();
});
</script>
