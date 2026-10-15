<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Đối Trừ Công Nợ" subtitle="Phân bổ tiền đã nhận vào các hóa đơn khách hàng còn nợ">
      <template #actions><Button label="Làm mới" icon="pi pi-refresh" size="small" outlined :loading="loading" @click="loadData" /></template>
    </PageHeader>

    <div class="bg-white border border-slate-200 rounded-[6px] p-5 space-y-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold mb-1">Phiếu thu còn chờ đối trừ</label>
          <Select v-model="voucherId" :options="voucherOptions" option-label="label" option-value="value" filter fluid placeholder="Chọn phiếu thu" @change="selectVoucher" />
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1">Khách hàng</label>
          <Select v-model="customerId" :options="customerOptions" option-label="label" option-value="value" filter fluid placeholder="Chọn khách hàng" @change="prepareInvoices" />
        </div>
      </div>

      <div v-if="selectedVoucher" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div v-for="card in summaryCards" :key="card.label" class="border border-slate-200 rounded-[6px] p-3">
          <div class="text-[11px] uppercase text-slate-500">{{ card.label }}</div>
          <div class="mt-1 font-mono text-lg font-bold" :class="card.color">{{ formatCurrency(card.value) }}</div>
        </div>
      </div>

      <div v-if="invoiceRows.length" class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="font-semibold text-slate-700">Hóa đơn chưa thanh toán</div>
          <Button label="Phân bổ tự động" icon="pi pi-bolt" size="small" severity="secondary" outlined @click="autoAllocate" />
        </div>
        <div class="border border-slate-200 rounded-[6px] overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-600"><tr><th class="px-3 py-2 text-left">Hóa đơn</th><th class="px-3 py-2 text-left">Ngày</th><th class="px-3 py-2 text-left">Hạn thanh toán</th><th class="px-3 py-2 text-right">Còn nợ</th><th class="px-3 py-2 text-right w-60">Đối trừ lần này</th></tr></thead>
            <tbody>
              <tr v-for="row in invoiceRows" :key="row.receivable_id" class="border-t border-slate-200">
                <td class="px-3 py-2 font-mono font-semibold text-sky-700">{{ row.invoice_number }}</td>
                <td class="px-3 py-2">{{ formatDate(row.invoice_date) }}</td>
                <td class="px-3 py-2">{{ formatDate(row.due_date) }}</td>
                <td class="px-3 py-2 text-right font-mono">{{ formatCurrency(row.outstanding_amount) }}</td>
                <td class="px-3 py-2"><InputNumber v-model="row.amount" mode="currency" currency="VND" locale="vi-VN" :min="0" :max="row.outstanding_amount" fluid /></td>
              </tr>
            </tbody>
            <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold"><tr><td colspan="4" class="px-3 py-2 text-right">Tổng đối trừ</td><td class="px-3 py-2 text-right font-mono" :class="allocationTotal > availableAmount ? 'text-red-600' : 'text-sky-700'">{{ formatCurrency(allocationTotal) }}</td></tr></tfoot>
          </table>
        </div>
        <div class="flex justify-end"><Button label="Xác nhận đối trừ" icon="pi pi-check" :loading="saving" :disabled="!canSubmit" @click="submitAllocation" /></div>
      </div>

      <div v-else-if="voucherId && customerId" class="text-center py-10 text-slate-500">Khách hàng không còn hóa đơn chưa thanh toán.</div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import PageHeader from '../../../components/common/PageHeader.vue';
import { financeApi } from '../../../api/finance.api';
import { formatCurrency, formatDate } from '../../../utils/format';

const toast = useToast();
const loading = ref(false); const saving = ref(false);
const vouchers = ref([]); const receivables = ref([]);
const voucherId = ref(null); const customerId = ref(null); const invoiceRows = ref([]);
const selectedVoucher = computed(() => vouchers.value.find(item => item.id === voucherId.value));
const availableAmount = computed(() => Number(selectedVoucher.value?.unallocated_amount || 0));
const allocationTotal = computed(() => invoiceRows.value.reduce((sum, row) => sum + Number(row.amount || 0), 0));
const canSubmit = computed(() => allocationTotal.value > 0 && allocationTotal.value <= availableAmount.value + 0.01);
const voucherOptions = computed(() => vouchers.value.map(item => ({ value: item.id, label: `${item.voucher_number} · ${formatDate(item.voucher_date)} · chưa phân bổ ${formatCurrency(item.unallocated_amount)}` })));
const customerOptions = computed(() => { const map = new Map(); receivables.value.filter(item => item.outstanding_amount > 0).forEach(item => map.set(item.customer_id, { value: item.customer_id, label: item.customer_name })); return [...map.values()]; });
const summaryCards = computed(() => [{ label: 'Số tiền phiếu thu', value: selectedVoucher.value?.total_amount, color: 'text-slate-800' }, { label: 'Đã đối trừ', value: selectedVoucher.value?.allocated_amount, color: 'text-emerald-700' }, { label: 'Còn chờ đối trừ', value: availableAmount.value, color: 'text-amber-700' }]);

const prepareInvoices = () => { invoiceRows.value = receivables.value.filter(item => item.customer_id === customerId.value && item.outstanding_amount > 0).sort((a, b) => String(a.due_date || '').localeCompare(String(b.due_date || ''))).map(item => ({ receivable_id: item.id, invoice_number: item.invoice_number, invoice_date: item.invoice_date, due_date: item.due_date, outstanding_amount: Number(item.outstanding_amount), amount: 0 })); };
const selectVoucher = () => { const voucher = selectedVoucher.value; customerId.value = voucher?.party_type === 'CUSTOMER' ? voucher.party_id : null; if (customerId.value) prepareInvoices(); else invoiceRows.value = []; };
const autoAllocate = () => { let remaining = availableAmount.value; invoiceRows.value.forEach(row => { row.amount = Math.min(row.outstanding_amount, remaining); remaining -= row.amount; }); };
const loadData = async () => { loading.value = true; try { const [voucherResponse, receivableResponse] = await Promise.all([financeApi.getVouchers(), financeApi.getCustomerReceivables({ per_page: 200 })]); vouchers.value = (voucherResponse.data || []).filter(item => ['CASH_RECEIPT', 'BANK_RECEIPT'].includes(item.voucher_type) && item.status === 'POSTED' && item.unallocated_amount > 0); receivables.value = receivableResponse.data || []; if (!vouchers.value.some(item => item.id === voucherId.value)) { voucherId.value = null; customerId.value = null; invoiceRows.value = []; } } finally { loading.value = false; } };
const submitAllocation = async () => { const allocations = invoiceRows.value.filter(row => Number(row.amount) > 0).map(row => ({ receivable_id: row.receivable_id, amount: Number(row.amount) })); saving.value = true; try { await financeApi.allocateVoucher(voucherId.value, allocations); toast.add({ severity: 'success', summary: 'Đối trừ thành công', detail: `Đã phân bổ ${formatCurrency(allocationTotal.value)} vào ${allocations.length} hóa đơn.`, life: 3500 }); await loadData(); } catch (error) { toast.add({ severity: 'error', summary: 'Không thể đối trừ', detail: error.message, life: 4000 }); } finally { saving.value = false; } };
onMounted(loadData);
</script>
