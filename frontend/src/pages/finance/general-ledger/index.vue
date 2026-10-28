<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Sổ cái" subtitle="Phát sinh Nợ, Có từ hóa đơn VAT, hóa đơn điều chỉnh và chứng từ thu chi đã ghi sổ">
      <template #actions><Button v-if="canApprove" label="Đồng bộ hóa đơn VAT cũ" icon="pi pi-sync" size="small" outlined :loading="syncing" @click="syncInvoices" /></template>
    </PageHeader>
    <div class="bg-white border border-slate-200 rounded-md p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
      <label class="text-sm font-medium">Tài khoản
        <Select v-model="accountId" :options="accountOptions" option-label="label" option-value="value" filter fluid placeholder="Chọn tài khoản" class="mt-1" />
      </label>
      <label class="text-sm font-medium">Từ ngày <InputText v-model="from" type="date" fluid class="mt-1" /></label>
      <label class="text-sm font-medium">Đến ngày <InputText v-model="to" type="date" fluid class="mt-1" /></label>
      <label class="text-sm font-medium">Tiền tệ <Select v-model="currency" :options="currencies" fluid class="mt-1" /></label>
      <Button label="Xem sổ" icon="pi pi-search" :disabled="!accountId" :loading="loading" @click="loadLedger(true)" />
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div v-for="card in cards" :key="card.label" class="bg-white border border-slate-200 rounded-md p-4">
        <div class="text-xs uppercase text-slate-500">{{ card.label }}</div>
        <div class="mt-1 text-lg font-bold font-mono">{{ money(card.value) }}</div>
      </div>
    </div>
    <CommonDataTable :value="rows" :columns="columns" :rows="rowsPerPage" :first="(page - 1) * rowsPerPage"
      :total-records="totalRecords" :loading="loading" lazy empty-title="Chưa có phát sinh cho tài khoản này" @page="changePage">
      <template #col-entry_date="{ data }">{{ formatDate(data.entry_date) }}</template>
      <template #col-document_number="{ data }"><span class="font-mono font-semibold text-sky-700">{{ data.document_number }}</span></template>
      <template #col-counter_account_code="{ data }"><span class="font-mono">{{ data.counter_account_code }}</span></template>
      <template #col-debit="{ data }"><div class="text-right font-mono">{{ data.debit ? money(data.debit) : '' }}</div></template>
      <template #col-credit="{ data }"><div class="text-right font-mono">{{ data.credit ? money(data.credit) : '' }}</div></template>
      <template #col-balance="{ data }"><div class="text-right font-mono font-bold">{{ money(data.balance) }}</div></template>
    </CommonDataTable>
    <p class="text-xs text-slate-500">Số dư thể hiện theo Nợ trừ Có, riêng cho tiền tệ đã chọn.</p>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import PageHeader from '../../../components/common/PageHeader.vue';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { financeApi } from '../../../api/finance.api';
import { salesApi } from '../../../api/sales.api';
import { storage } from '../../../utils/storage';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';
import { formatDate } from '../../../utils/format';

const toast = useToast();
const accounts = ref([]);
const canApprove = hasPermission('finance.approve', parseJwt(storage.getToken()));
const accountId = ref(null);
const from = ref('');
const to = ref('');
const currency = ref('VND');
const currencies = ['VND', 'USD', 'EUR', 'GBP', 'JPY', 'CNY', 'KRW', 'SGD', 'THB', 'AUD', 'CAD', 'CHF', 'HKD'];
const rows = ref([]);
const loading = ref(false);
const syncing = ref(false);
const page = ref(1);
const rowsPerPage = ref(20);
const totalRecords = ref(0);
const summary = ref({ opening_balance: 0, total_debit: 0, total_credit: 0, closing_balance: 0 });
const accountOptions = computed(() => accounts.value.filter(item => item.is_postable).map(item => ({ value: item.id, label: `${item.code} — ${item.name}` })));
const cards = computed(() => [
  { label: 'Dư đầu kỳ', value: summary.value.opening_balance },
  { label: 'Phát sinh Nợ', value: summary.value.total_debit },
  { label: 'Phát sinh Có', value: summary.value.total_credit },
  { label: 'Dư cuối kỳ', value: summary.value.closing_balance }
]);
const columns = [
  { field: 'entry_date', header: 'Ngày', width: '110px' },
  { field: 'document_number', header: 'Số chứng từ', width: '170px' },
  { field: 'description', header: 'Diễn giải' },
  { field: 'counter_account_code', header: 'TK đối ứng', width: '110px' },
  { field: 'debit', header: 'Phát sinh Nợ', width: '150px', align: 'right' },
  { field: 'credit', header: 'Phát sinh Có', width: '150px', align: 'right' },
  { field: 'balance', header: 'Số dư', width: '150px', align: 'right' }
];
const money = value => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 2 }).format(Number(value || 0));
const loadLedger = async (resetPage = false) => {
  if (!accountId.value) return;
  if (resetPage) page.value = 1;
  loading.value = true;
  try {
    const result = await financeApi.getGeneralLedger({
      account_id: accountId.value,
      from: from.value,
      to: to.value,
      currency: currency.value,
      page: page.value,
      per_page: rowsPerPage.value
    });
    rows.value = result.data || [];
    summary.value = result.summary || summary.value;
    totalRecords.value = result.meta?.total || 0;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được sổ cái', detail: error.message, life: 5000 });
  } finally { loading.value = false; }
};
const changePage = event => {
  page.value = event.page + 1;
  rowsPerPage.value = event.rows;
  loadLedger();
};
const syncInvoices = async () => {
  syncing.value = true;
  try {
    const result = await salesApi.syncVatInvoicesToFinance();
    toast.add({ severity: result.failed?.length ? 'warn' : 'success', summary: `Đã đồng bộ ${result.synced} hóa đơn VAT`,
      detail: result.failed?.length ? `Lỗi: ${result.failed.join(', ')}` : undefined, life: 6000 });
    await loadLedger();
  } catch (error) { toast.add({ severity: 'error', summary: 'Không đồng bộ được hóa đơn cũ', detail: error.message, life: 6000 }); }
  finally { syncing.value = false; }
};
onMounted(async () => {
  try {
    accounts.value = (await financeApi.getAccounts()).data || [];
    accountId.value = accounts.value.find(item => item.code === '1311')?.id || accountOptions.value[0]?.value || null;
    await loadLedger();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được tài khoản', detail: error.message, life: 5000 });
  }
});
</script>
