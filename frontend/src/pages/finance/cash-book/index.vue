<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="isBank ? 'Sổ Tiền Gửi Ngân Hàng' : 'Sổ Quỹ'" :subtitle="isBank ? 'Theo dõi thu, chi và số dư tài khoản ngân hàng' : 'Theo dõi thu, chi và tồn quỹ tiền mặt theo chứng từ đã ghi sổ'"><template #actions><Button label="Làm mới" icon="pi pi-refresh" size="small" outlined @click="load" /></template></PageHeader>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3"><div v-for="item in cards" :key="item.label" class="bg-white border border-slate-200 rounded-[6px] p-4"><div class="text-xs uppercase text-slate-500">{{ item.label }}</div><div class="mt-1 text-xl font-bold font-mono" :class="item.color">{{ formatCurrency(item.value) }}</div></div></div>
    <CommonDataTable :value="rows" :columns="columns" :loading="loading" :rows="20" empty-icon="pi pi-wallet" empty-title="Chưa có phát sinh thu chi">
      <template #col-voucher_number="{ data }"><span class="font-mono font-semibold text-sky-700">{{ data.voucher_number }}</span></template>
      <template #col-voucher_date="{ data }">{{ formatDate(data.voucher_date) }}</template>
      <template #col-receipt="{ data }"><div class="text-right font-mono text-emerald-700">{{ data.receipt ? formatCurrency(data.receipt) : '' }}</div></template>
      <template #col-payment="{ data }"><div class="text-right font-mono text-red-600">{{ data.payment ? formatCurrency(data.payment) : '' }}</div></template>
      <template #col-balance="{ data }"><div class="text-right font-mono font-bold">{{ formatCurrency(data.balance) }}</div></template>
    </CommonDataTable>
  </div>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue'; import { useRoute } from 'vue-router'; import Button from 'primevue/button';
import CommonDataTable from '../../../components/common/CommonDataTable.vue'; import PageHeader from '../../../components/common/PageHeader.vue'; import { financeApi } from '../../../api/finance.api'; import { formatCurrency, formatDate } from '../../../utils/format';
const route = useRoute(); const isBank = computed(() => route.meta.bookChannel === 'BANK'); const rows = ref([]); const summary = ref({ opening_balance: 0, closing_balance: 0 }); const loading = ref(false);
const columns = [{ field: 'voucher_date', header: 'Ngày', width: '110px' }, { field: 'voucher_number', header: 'Số chứng từ', width: '160px' }, { field: 'party_name', header: 'Đối tượng', width: '220px' }, { field: 'description', header: 'Diễn giải' }, { field: 'receipt', header: 'Thu', width: '150px', align: 'right' }, { field: 'payment', header: 'Chi', width: '150px', align: 'right' }, { field: 'balance', header: 'Tồn quỹ', width: '160px', align: 'right' }];
const cards = computed(() => [{ label: 'Tồn đầu', value: summary.value.opening_balance, color: 'text-slate-800' }, { label: 'Phát sinh thu', value: rows.value.reduce((sum, row) => sum + row.receipt, 0), color: 'text-emerald-700' }, { label: 'Tồn cuối', value: summary.value.closing_balance, color: 'text-sky-700' }]);
const load = async () => { loading.value = true; try { const response = isBank.value ? await financeApi.getBankBook() : await financeApi.getCashBook(); rows.value = response.data || []; summary.value = response.summary || summary.value; } finally { loading.value = false; } }; onMounted(load);
</script>
