<template>
  <div class="space-y-3 pb-8">
    <PageHeader
      title="Sổ Cái Bán Hàng & Ghi Sổ Trừ Kho (Accounting Ledger & Stock Deduction)"
      subtitle="Chốt sổ kế toán sau khi giao hàng thành công: Ghi nhận doanh thu, giá vốn và kích hoạt trừ tồn kho On-Hand thực tế"
      badge="Stage 5: Final Post"
      badge-severity="success"
    />

    <!-- Explanatory Enterprise Rule Banner -->
    <div class="bg-indigo-50/70 border border-indigo-200 rounded-[6px] p-3 text-xs text-indigo-900 flex items-start gap-3">
      <div class="w-7 h-7 rounded-[4px] bg-indigo-100 text-indigo-700 flex items-center justify-center shrink-0">
        <i class="pi pi-book text-sm"></i>
      </div>
      <div class="space-y-0.5">
        <div class="font-bold text-indigo-900 text-xs uppercase tracking-wide">
          Chính Sách Đối Soát & Ghi Sổ Trừ Kho (Accounting Posting Policy)
        </div>
        <p class="m-0 text-indigo-800 text-[11px] leading-relaxed">
          Theo quy chế kế toán ERP: Hàng hóa chỉ chính thức trừ tồn vật lý (On-Hand) trên <code>Inventory Service (:8004)</code> khi bộ phận Kế toán kiểm tra đối soát biên bản giao nhận và bấm <strong>"Ghi Sổ & Trừ Kho"</strong>. Chứng từ sau khi ghi sổ sẽ được khóa bất biến.
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
              placeholder="Tìm theo mã chứng từ, khách hàng..."
              class="w-72 rounded-[6px]"
              size="small"
            />
          </IconField>
          <Select
            v-model="filterStatus"
            :options="filterOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="Lọc trạng thái sổ..."
            class="w-48 rounded-[6px]"
            size="small"
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2 text-xs">
          <span class="text-amber-700 font-semibold bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-[4px]">
            Chờ ghi sổ: {{ pendingCount }}
          </span>
          <span class="text-emerald-700 font-semibold bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-[4px]">
            Đã ghi sổ: {{ postedCount }}
          </span>
        </div>
      </template>
    </Toolbar>

    <!-- Data Table using CommonDataTable -->
    <CommonDataTable
      :value="filteredInvoices"
      :columns="tableColumns"
      :rows="8"
      :paginator="filteredInvoices.length > 8"
      emptyIcon="pi pi-calculator"
      emptyTitle="Chưa có chứng từ nào sẵn sàng ghi sổ"
      emptySubtitle="Chứng từ cần được chuyển qua kho và giao hàng thành công trước khi kế toán ghi sổ."
    >
      <!-- Custom column: invoice_number -->
      <template #col-invoice_number="{ data }">
        <span class="font-mono text-xs font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-[4px] border border-indigo-200">
          {{ data.invoice_number }}
        </span>
      </template>

      <!-- Custom column: customer_name -->
      <template #col-customer_name="{ data }">
        <span class="font-semibold text-slate-800 text-xs">{{ data.customer_name }}</span>
      </template>

      <!-- Custom column: warehouse_name -->
      <template #col-warehouse_name="{ data }">
        <span class="text-slate-600 text-xs">{{ data.warehouse_name }}</span>
      </template>

      <!-- Custom column: warehouse_status -->
      <template #col-warehouse_status="{ data }">
        <div class="text-center">
          <Badge
            v-if="data.warehouse_status === 'DELIVERED'"
            severity="success"
            value="Đã giao"
          />
          <Badge
            v-else
            severity="warn"
            value="Đang xử lý"
          />
        </div>
      </template>

      <!-- Custom column: total_amount -->
      <template #col-total_amount="{ data }">
        <div class="text-right">
          <span class="font-mono text-xs font-bold text-slate-900 num-tabular">
            {{ formatCurrency(data.total_amount) }}
          </span>
        </div>
      </template>

      <!-- Custom column: accounting_status -->
      <template #col-accounting_status="{ data }">
        <div class="text-center">
          <div v-if="data.accounting_status === 'POSTED'" class="flex flex-col items-center gap-0.5">
            <Badge
              severity="success"
              value="Đã ghi sổ"
            />
            <span class="text-[10px] font-mono text-slate-400">{{ data.posted_at }}</span>
          </div>
          <Badge
            v-else
            severity="warn"
            value="Chưa ghi sổ"
          />
        </div>
      </template>

      <!-- Column Thao Tác Kế Toán -->
      <Column header="Thao Tác" class="text-center" style="width: 100px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1">
            <Button
              v-if="data.accounting_status !== 'POSTED'"
              icon="pi pi-file-edit"
              outlined
              size="small"
              severity="success"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              :disabled="data.warehouse_status !== 'DELIVERED'"
              v-tooltip.bottom="data.warehouse_status === 'DELIVERED' ? 'Ghi sổ kế toán & trừ tồn kho' : 'Cần đợi kho hoàn tất giao hàng'"
              @click="openAccountingDialog(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Modals -->
    <AccountingPostForm
      v-model:visible="showConfirmDialog"
      :invoice="selectedInvoice"
      :loading="loadingPosting"
      @confirm="handleConfirmPost"
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

import AccountingPostForm from './form.vue';
import { salesApi } from '../../api/sales.api';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const toast = useToast();

const invoices = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const filterStatus = ref(null);
const showConfirmDialog = ref(false);
const selectedInvoice = ref(null);
const loadingPosting = ref(false);

const tableColumns = [
  { field: 'invoice_number', header: 'Mã Chứng Từ (INV)', sortable: true, width: '160px' },
  { field: 'customer_name', header: 'Khách Hàng B2B', sortable: true },
  { field: 'warehouse_name', header: 'Kho Xuất Trừ', sortable: false },
  { field: 'warehouse_status', header: 'Tình Trạng Giao', sortable: true, width: '150px', align: 'center' },
  { field: 'total_amount', header: 'Tổng Doanh Thu', sortable: true, width: '140px', align: 'right' },
  { field: 'accounting_status', header: 'Trạng Thái Sổ Cái', sortable: true, width: '180px', align: 'center' }
];

const filterOptions = [
  { label: 'Tất cả chứng từ', value: null },
  { label: 'Chờ ghi sổ (Chưa trừ kho)', value: 'UNPOSTED' },
  { label: 'Đã ghi sổ & trừ kho', value: 'POSTED' }
];

const loadInvoices = async () => {
  loading.value = true;
  try {
    const res = await salesApi.getInvoices();
    invoices.value = (res.data || []).map((inv) => ({
      ...inv,
      order_id: inv.order_number || inv.order_id
    }));
  } catch (err) {
    console.error('Lỗi tải chứng từ kế toán:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadInvoices();
});

const pendingCount = computed(() => {
  return invoices.value.filter((i) => i.accounting_status !== 'POSTED').length;
});

const postedCount = computed(() => {
  return invoices.value.filter((i) => i.accounting_status === 'POSTED').length;
});

const filteredInvoices = computed(() => {
  return invoices.value.filter((inv) => {
    const invNum = String(inv.invoice_number || '');
    const cName = String(inv.customer_name || '');
    const matchSearch =
      !searchQuery.value ||
      invNum.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      cName.toLowerCase().includes(searchQuery.value.toLowerCase());
    const matchStatus = !filterStatus.value || inv.accounting_status === filterStatus.value;
    return matchSearch && matchStatus;
  });
});

const openAccountingDialog = (inv) => {
  selectedInvoice.value = inv;
  showConfirmDialog.value = true;
};

const handleConfirmPost = async ({ invoice, note }) => {
  loadingPosting.value = true;
  try {
    const posterName = props.currentUser?.name || 'Kế toán trưởng';
    await salesApi.postAccounting(invoice.id, posterName);

    invoice.accounting_status = 'POSTED';
    invoice.posted_by = posterName;

    toast.add({
      severity: 'success',
      summary: 'Ghi Sổ Kế Toán Thành Công!',
      detail: `Chứng từ ${invoice.invoice_number} đã được ghi sổ kế toán thành công.`,
      life: 5000
    });

    showConfirmDialog.value = false;
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi ghi sổ kế toán',
      detail: err.message || 'Không thể kết nối đến Sales Service.',
      life: 5000
    });
  } finally {
    loadingPosting.value = false;
  }
};
</script>
