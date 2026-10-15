<template>
  <div class="space-y-4 pb-8">
    <PageHeader
      title="Công Nợ Khách Hàng"
      subtitle="Theo dõi các khoản phải thu phát sinh từ hóa đơn GTGT đã phát hành"
    >
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" outlined :loading="loading" @click="loadReceivables" />
      </template>
    </PageHeader>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
      <div v-for="card in summaryCards" :key="card.label" class="bg-white border border-slate-200 rounded-[6px] p-4">
        <div class="flex items-center justify-between gap-3">
          <div class="min-w-0">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ card.label }}</div>
            <div class="mt-1 text-lg font-bold font-mono tabular-nums" :class="card.valueClass">{{ formatCurrency(card.value) }}</div>
          </div>
          <div class="w-9 h-9 rounded-[6px] flex items-center justify-center shrink-0" :class="card.iconClass">
            <i :class="card.icon"></i>
          </div>
        </div>
      </div>
    </div>

    <Toolbar class="!border-slate-200 !rounded-[6px]">
      <template #start>
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText v-model="searchQuery" placeholder="Tìm số hóa đơn, khách hàng, mã số thuế..." class="w-80" size="small" />
        </IconField>
      </template>
      <template #end>
        <Select
          v-model="statusFilter"
          :options="statusOptions"
          option-label="label"
          option-value="value"
          placeholder="Tất cả trạng thái"
          class="w-48"
          size="small"
        />
      </template>
    </Toolbar>

    <CommonDataTable
      :value="receivables"
      :columns="tableColumns"
      :loading="loading"
      :rows="20"
      :rows-per-page-options="[10, 20, 50]"
      empty-icon="pi pi-wallet"
      empty-title="Chưa có công nợ khách hàng"
      empty-subtitle="Công nợ sẽ tự động phát sinh khi hóa đơn GTGT được phát hành."
    >
      <template #col-invoice_number="{ data }">
        <span class="font-mono font-semibold text-sky-700">{{ data.invoice_number }}</span>
      </template>
      <template #col-customer_name="{ data }">
        <div class="flex flex-col">
          <span class="font-semibold text-slate-800">{{ data.customer_name }}</span>
          <span v-if="data.customer_tax_code" class="text-[10px] text-slate-400 font-mono">MST: {{ data.customer_tax_code }}</span>
        </div>
      </template>
      <template #col-invoice_date="{ data }">{{ formatDate(data.invoice_date) }}</template>
      <template #col-due_date="{ data }">
        <div class="flex flex-col">
          <span :class="data.effective_status === 'OVERDUE' ? 'font-semibold text-red-600' : ''">{{ formatDate(data.due_date) }}</span>
          <span v-if="data.overdue_days" class="text-[10px] text-red-500">Quá hạn {{ data.overdue_days }} ngày</span>
        </div>
      </template>
      <template #col-original_amount="{ data }"><div class="text-right font-mono">{{ formatCurrency(data.original_amount) }}</div></template>
      <template #col-paid_amount="{ data }"><div class="text-right font-mono text-emerald-700">{{ formatCurrency(data.paid_amount) }}</div></template>
      <template #col-outstanding_amount="{ data }"><div class="text-right font-mono font-bold text-amber-700">{{ formatCurrency(data.outstanding_amount) }}</div></template>
      <template #col-effective_status="{ data }">
        <Tag :value="statusMeta(data.effective_status).label" :severity="statusMeta(data.effective_status).severity" />
      </template>
    </CommonDataTable>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toolbar from 'primevue/toolbar';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import PageHeader from '../../components/common/PageHeader.vue';
import { financeApi } from '../../api/finance.api';
import { formatCurrency, formatDate } from '../../utils/format';

const toast = useToast();
const receivables = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref(null);
const summary = ref({ total_receivable: 0, total_paid: 0, total_outstanding: 0, overdue_outstanding: 0 });
let searchTimer;

const tableColumns = [
  { field: 'invoice_number', header: 'Hóa đơn GTGT', width: '175px' },
  { field: 'customer_name', header: 'Khách hàng', width: '230px' },
  { field: 'invoice_date', header: 'Ngày hóa đơn', width: '115px', align: 'center' },
  { field: 'due_date', header: 'Hạn thanh toán', width: '130px', align: 'center' },
  { field: 'original_amount', header: 'Phải thu', width: '145px', align: 'right' },
  { field: 'paid_amount', header: 'Đã thu', width: '140px', align: 'right' },
  { field: 'outstanding_amount', header: 'Còn nợ', width: '145px', align: 'right' },
  { field: 'effective_status', header: 'Trạng thái', width: '145px', align: 'center' }
];

const statusOptions = [
  { label: 'Tất cả trạng thái', value: null },
  { label: 'Chưa thanh toán', value: 'OPEN' },
  { label: 'Đã thu một phần', value: 'PARTIALLY_PAID' },
  { label: 'Quá hạn', value: 'OVERDUE' },
  { label: 'Đã thanh toán', value: 'PAID' }
];

const summaryCards = computed(() => [
  { label: 'Tổng phải thu', value: summary.value.total_receivable, icon: 'pi pi-file', valueClass: 'text-slate-900', iconClass: 'bg-slate-100 text-slate-600' },
  { label: 'Đã thu', value: summary.value.total_paid, icon: 'pi pi-check-circle', valueClass: 'text-emerald-700', iconClass: 'bg-emerald-50 text-emerald-600' },
  { label: 'Còn phải thu', value: summary.value.total_outstanding, icon: 'pi pi-wallet', valueClass: 'text-amber-700', iconClass: 'bg-amber-50 text-amber-600' },
  { label: 'Nợ quá hạn', value: summary.value.overdue_outstanding, icon: 'pi pi-exclamation-triangle', valueClass: 'text-red-700', iconClass: 'bg-red-50 text-red-600' }
]);

const statusMeta = status => ({
  OPEN: { label: 'Chưa thanh toán', severity: 'warn' },
  PARTIALLY_PAID: { label: 'Đã thu một phần', severity: 'info' },
  OVERDUE: { label: 'Quá hạn', severity: 'danger' },
  PAID: { label: 'Đã thanh toán', severity: 'success' }
}[status] || { label: status || 'Chưa xác định', severity: 'secondary' });

const loadReceivables = async () => {
  loading.value = true;
  try {
    const params = { per_page: 200 };
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (statusFilter.value) params.status = statusFilter.value;

    const response = await financeApi.getCustomerReceivables(params);
    receivables.value = response.data || [];
    summary.value = response.summary || summary.value;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được công nợ', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

watch([searchQuery, statusFilter], () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(loadReceivables, 300);
});

onMounted(loadReceivables);
onBeforeUnmount(() => clearTimeout(searchTimer));
</script>
