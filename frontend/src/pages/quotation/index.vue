<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Báo Giá Khách Hàng (Quotations)"
      subtitle="Quản lý các bản báo giá B2B, theo dõi tiến trình phê duyệt và liên kết đơn bán hàng"
      badge="Stage 1: Quotation"
      badge-severity="info"
    >
      <template #actions>
        <Button
          label="Lập Báo Giá Mới"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
          @click="goToCreate"
        />
      </template>
    </PageHeader>

    <!-- Toolbar Filter -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo mã báo giá hoặc tên khách hàng..."
              class="w-72 rounded-[6px]"
              size="small"
            />
          </IconField>
          <Select
            v-model="statusFilter"
            :options="statusOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc trạng thái..."
            class="w-48 rounded-[6px]"
            size="small"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs text-slate-500">
          <span>Tổng số: <strong class="text-slate-800">{{ quotations.length }}</strong> bản báo giá</span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table using CommonDataTable -->
    <CommonDataTable
      :value="filteredQuotations"
      :columns="tableColumns"
      :rows="8"
      emptyIcon="pi pi-file-edit"
      emptyTitle="Chưa có bản báo giá nào"
      emptySubtitle="Nhấn nút 'Lập Báo Giá Mới' ở trên để chuyển sang màn hình lập báo giá."
    >
      <!-- Custom column: id -->
      <template #col-id="{ data }">
        <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200 inline-block whitespace-nowrap">
          #{{ data.id }}
        </span>
      </template>

      <!-- Custom column: quotation_number -->
      <template #col-quotation_number="{ data }">
        <span
          class="font-mono text-xs font-semibold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
          @click="openViewDialog(data)"
        >
          {{ data.quotation_number }}
        </span>
      </template>

      <!-- Custom column: customer_name -->
      <template #col-customer_name="{ data }">
        <div class="flex flex-col">
          <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
          <span v-if="data.contact_name" class="text-[11px] text-slate-400">LH: {{ data.contact_name }}</span>
        </div>
      </template>

      <!-- Custom column: created_at -->
      <template #col-created_at="{ data }">
        <span class="text-slate-600 text-xs font-mono whitespace-nowrap">{{ formatDate(data.created_at || data.quotation_date) }}</span>
      </template>

      <!-- Custom column: items_count -->
      <template #col-items_count="{ data }">
        <div class="text-center">
          <span class="text-xs font-mono font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded-[4px] inline-block whitespace-nowrap">
            {{ data.items?.length || 0 }} items
          </span>
        </div>
      </template>

      <!-- Custom column: total_amount -->
      <template #col-total_amount="{ data }">
        <div class="text-right">
          <span class="font-mono text-xs font-bold text-slate-900 num-tabular whitespace-nowrap">
            {{ formatCurrency(data.total_amount) }}
          </span>
        </div>
      </template>

      <!-- Custom column: status -->
      <template #col-status="{ data }">
        <div class="text-center whitespace-nowrap">
          <Badge
            :severity="getStatusMeta(data.status).severity"
            :value="getStatusMeta(data.status).label"
          />
        </div>
      </template>

      <!-- Column Thao Tác (Actions) -->
      <Column header="Thao Tác" class="text-center whitespace-nowrap" headerClass="whitespace-nowrap" style="width: 110px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
            <!-- Nút View báo giá qua dialog -->
            <Button
              icon="pi pi-eye"
              outlined
              size="small"
              severity="secondary"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xem chi tiết báo giá'"
              @click="openViewDialog(data)"
            />

            <!-- Nút Chỉnh sửa báo giá sang trang riêng form.vue -->
            <Button
              icon="pi pi-pencil"
              outlined
              size="small"
              severity="info"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Chỉnh sửa báo giá (Trang riêng)'"
              @click="goToEdit(data)"
            />

            <!-- Nút Tạo đơn bán (SO) / Chọn item win nếu đã duyệt -->
            <Button
              v-if="data.status === 'APPROVED'"
              icon="pi pi-cart-plus"
              outlined
              size="small"
              severity="success"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Vào báo giá để chọn Item Win và tạo Đơn bán (SO)'"
              @click="goToEdit(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- View Quotation Modal Dialog -->
    <QuotationViewDialog
      v-model:visible="showViewDialog"
      :quotation="selectedQuotation"
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
import Select from 'primevue/select';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import QuotationViewDialog from '../../components/quotation/QuotationViewDialog.vue';

import { salesApi } from '../../api/sales.api';
import { formatCurrency, formatDate } from '../../utils/format';

const router = useRouter();
const toast = useToast();

const quotations = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);
const showViewDialog = ref(false);
const selectedQuotation = ref(null);

// Cấu hình các cột cho CommonDataTable (đã thêm cột ID, căn chỉnh chiều rộng hợp lý)
const tableColumns = [
  { field: 'id', header: 'ID', sortable: true, width: '70px', align: 'center' },
  { field: 'quotation_number', header: 'Mã Báo Giá', sortable: true, width: '230px' },
  { field: 'customer_name', header: 'Khách Hàng', sortable: true },
  { field: 'created_at', header: 'Ngày Tạo', sortable: true, width: '120px', align: 'center' },
  { field: 'items_count', header: 'Số Item', sortable: false, width: '95px', align: 'center' },
  { field: 'total_amount', header: 'Tổng Số Tiền', sortable: true, width: '160px', align: 'right' },
  { field: 'status', header: 'Trạng Thái', sortable: true, width: '130px', align: 'center' }
];

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Bản nháp', value: 'DRAFT' },
  { label: 'Chờ duyệt', value: 'PENDING_APPROVAL' },
  { label: 'Đã duyệt', value: 'APPROVED' },
  { label: 'Đã tạo đơn', value: 'CONVERTED' }
];

const getStatusMeta = (st) => {
  const map = {
    DRAFT: { label: 'Bản nháp', severity: 'secondary' },
    PENDING_APPROVAL: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt', severity: 'info' },
    CONVERTED: { label: 'Đã tạo đơn', severity: 'success' },
    REJECTED: { label: 'Từ chối', severity: 'danger' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const loadQuotations = async () => {
  loading.value = true;
  try {
    const res = await salesApi.getQuotations();
    quotations.value = res.data || [];
  } catch (err) {
    console.error('Lỗi tải danh sách báo giá:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadQuotations();
});

const filteredQuotations = computed(() => {
  return quotations.value.filter((q) => {
    const qNum = q.quotation_number || '';
    const cName = q.customer_name || '';
    const matchSearch =
      !searchQuery.value ||
      qNum.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      cName.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchStatus = !statusFilter.value || q.status === statusFilter.value;
    return matchSearch && matchStatus;
  });
});

const goToCreate = () => {
  router.push('/quotation/create');
};

const goToEdit = (quotation) => {
  router.push(`/quotation/${quotation.id}/edit`);
};

const openViewDialog = (q) => {
  selectedQuotation.value = q;
  showViewDialog.value = true;
};
</script>
