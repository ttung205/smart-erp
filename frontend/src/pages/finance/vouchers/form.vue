<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="pageTitle" subtitle="Chứng từ được lưu nháp trước khi ghi sổ và làm phát sinh tiền">
      <template #actions>
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined @click="router.back()" />
        <Button label="Lưu và ghi sổ" icon="pi pi-check" size="small" :loading="saving" @click="save" />
      </template>
    </PageHeader>

    <div class="bg-white border border-slate-200 rounded-[6px] p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-xs font-semibold mb-1">Ngày chứng từ</label>
        <DatePicker v-model="form.voucher_date" date-format="dd/mm/yy" fluid />
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1">{{ isCash ? 'Quỹ tiền mặt' : 'Tài khoản ngân hàng' }}</label>
        <Select v-model="sourceId" :options="sourceOptions" option-label="label" option-value="value" fluid />
      </div>
      <div v-if="isTransfer">
        <label class="block text-xs font-semibold mb-1">Tài khoản ngân hàng nhận</label>
        <Select v-model="destinationId" :options="sourceOptions.filter(item => item.value !== sourceId)" option-label="label" option-value="value" fluid />
      </div>

      <div v-if="isReceipt" class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1">Chọn các hóa đơn cần thu (không bắt buộc)</label>
        <MultiSelect
          v-model="selectedReceivableIds"
          :options="receivableOptions"
          option-label="label"
          option-value="value"
          option-disabled="disabled"
          display="chip"
          filter
          fluid
          placeholder="Có thể chọn nhiều hóa đơn của cùng một khách hàng"
          @change="syncAllocationRows"
        />
        <small class="text-slate-500">Sau khi chọn hóa đơn đầu tiên, hệ thống chỉ cho chọn thêm hóa đơn của cùng khách hàng.</small>
      </div>

      <div v-if="allocationRows.length" class="md:col-span-2 border border-slate-200 rounded-[6px] overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-slate-50 text-slate-600">
            <tr>
              <th class="px-3 py-2 text-left">Hóa đơn</th>
              <th class="px-3 py-2 text-left">Khách hàng</th>
              <th class="px-3 py-2 text-right">Còn nợ</th>
              <th class="px-3 py-2 text-right w-56">Thu lần này</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in allocationRows" :key="row.receivable_id" class="border-t border-slate-200">
              <td class="px-3 py-2 font-mono font-semibold text-sky-700">{{ row.invoice_number }}</td>
              <td class="px-3 py-2">{{ row.customer_name }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ formatCurrency(row.outstanding_amount) }}</td>
              <td class="px-3 py-2">
                <InputNumber v-model="row.amount" mode="currency" currency="VND" locale="vi-VN" :min="0" :max="row.outstanding_amount" fluid @update:model-value="updateAllocationTotal" />
              </td>
            </tr>
          </tbody>
          <tfoot class="border-t border-slate-200 bg-slate-50 font-semibold">
            <tr><td colspan="3" class="px-3 py-2 text-right">Tổng phân bổ</td><td class="px-3 py-2 text-right font-mono text-sky-700">{{ formatCurrency(allocationTotal) }}</td></tr>
          </tfoot>
        </table>
      </div>

      <div>
        <label class="block text-xs font-semibold mb-1">Người nộp/người nhận</label>
        <InputText v-model="form.party_name" fluid />
      </div>
      <div>
        <label class="block text-xs font-semibold mb-1">Số tiền</label>
        <InputNumber v-model="form.total_amount" mode="currency" currency="VND" locale="vi-VN" :min="0" :disabled="allocationRows.length > 0" fluid />
      </div>
      <div class="md:col-span-2">
        <label class="block text-xs font-semibold mb-1">Diễn giải</label>
        <Textarea v-model="form.description" rows="3" fluid />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import MultiSelect from 'primevue/multiselect';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import PageHeader from '../../../components/common/PageHeader.vue';
import { financeApi } from '../../../api/finance.api';
import { formatCurrency } from '../../../utils/format';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const type = computed(() => route.query.type || 'CASH_RECEIPT');
const isCash = computed(() => type.value.startsWith('CASH'));
const isReceipt = computed(() => type.value.includes('RECEIPT'));
const isTransfer = computed(() => type.value === 'INTERNAL_TRANSFER');
const saving = ref(false);
const master = ref({ cash_funds: [], bank_accounts: [] });
const receivables = ref([]);
const sourceId = ref(null);
const destinationId = ref(null);
const selectedReceivableIds = ref([]);
const allocationRows = ref([]);
const form = reactive({ voucher_date: new Date(), party_name: '', description: '', total_amount: 0 });

const pageTitle = computed(() => ({
  CASH_RECEIPT: 'Lập Phiếu Thu', CASH_PAYMENT: 'Lập Phiếu Chi',
  BANK_RECEIPT: 'Lập Chứng Từ Thu Ngân Hàng', BANK_PAYMENT: 'Lập Chứng Từ Chi Ngân Hàng',
  INTERNAL_TRANSFER: 'Chuyển Tiền Nội Bộ'
}[type.value]));

const sourceOptions = computed(() => (isCash.value ? master.value.cash_funds : master.value.bank_accounts).map(item => ({
  value: item.id,
  label: isCash.value ? `${item.code} - ${item.name}` : `${item.bank_name} - ${item.account_number}`
})));

const selectedCustomerId = computed(() => allocationRows.value[0]?.customer_id ?? null);
const receivableOptions = computed(() => receivables.value
  .filter(item => item.outstanding_amount > 0)
  .map(item => ({
    value: item.id,
    label: `${item.invoice_number} · ${item.customer_name} · còn ${formatCurrency(item.outstanding_amount)}`,
    disabled: selectedCustomerId.value !== null && item.customer_id !== selectedCustomerId.value
  })));
const allocationTotal = computed(() => allocationRows.value.reduce((sum, row) => sum + Number(row.amount || 0), 0));

const updateAllocationTotal = () => {
  form.total_amount = allocationTotal.value;
};

const syncAllocationRows = () => {
  const previousAmounts = new Map(allocationRows.value.map(row => [row.receivable_id, row.amount]));
  const selectedItems = selectedReceivableIds.value
    .map(id => receivables.value.find(item => item.id === id))
    .filter(Boolean);
  const customerId = selectedItems[0]?.customer_id;
  const validItems = selectedItems.filter(item => item.customer_id === customerId);
  selectedReceivableIds.value = validItems.map(item => item.id);
  allocationRows.value = validItems.map(item => ({
    receivable_id: item.id,
    customer_id: item.customer_id,
    invoice_number: item.invoice_number,
    customer_name: item.customer_name,
    outstanding_amount: Number(item.outstanding_amount),
    amount: previousAmounts.get(item.id) ?? Number(item.outstanding_amount)
  }));

  if (validItems.length) {
    form.party_name = validItems[0].customer_name;
    form.description = `Thu tiền ${validItems.length} hóa đơn: ${validItems.map(item => item.invoice_number).join(', ')}`;
  }
  updateAllocationTotal();
};

const save = async () => {
  const allocations = allocationRows.value
    .filter(row => Number(row.amount) > 0)
    .map(row => ({ receivable_id: row.receivable_id, amount: Number(row.amount) }));
  if (!sourceId.value || (isTransfer.value && !destinationId.value) || !form.total_amount || !form.description) {
    toast.add({ severity: 'warn', summary: 'Thiếu thông tin', detail: 'Vui lòng chọn đầy đủ nguồn tiền, nhập số tiền và diễn giải.', life: 3000 });
    return;
  }
  if (allocationRows.value.length && allocations.length !== allocationRows.value.length) {
    toast.add({ severity: 'warn', summary: 'Phân bổ chưa hợp lệ', detail: 'Số tiền thu của mỗi hóa đơn phải lớn hơn 0.', life: 3000 });
    return;
  }

  saving.value = true;
  try {
    const hasAllocations = allocations.length > 0;
    const payload = {
      voucher_type: type.value,
      channel: isCash.value ? 'CASH' : 'BANK',
      voucher_date: form.voucher_date.toISOString().slice(0, 10),
      party_type: hasAllocations ? 'CUSTOMER' : null,
      party_id: hasAllocations ? allocationRows.value[0].customer_id : null,
      party_name: form.party_name,
      description: form.description,
      total_amount: form.total_amount,
      currency: 'VND',
      cash_fund_id: isCash.value ? sourceId.value : null,
      bank_account_id: isCash.value ? null : sourceId.value,
      destination_bank_account_id: isTransfer.value ? destinationId.value : null,
      counter_account_code: hasAllocations ? '1311' : '138',
      allocations
    };
    const created = await financeApi.createVoucher(payload);
    await financeApi.postVoucher(created.data.id);
    toast.add({ severity: 'success', summary: 'Đã ghi sổ', detail: `Chứng từ ${created.data.voucher_number} đã đối trừ ${allocations.length} hóa đơn.`, life: 3000 });
    router.back();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể ghi sổ', detail: error.message, life: 4000 });
  } finally {
    saving.value = false;
  }
};

onMounted(async () => {
  const [masterResponse, receivableResponse] = await Promise.all([
    financeApi.getMasterData(), financeApi.getCustomerReceivables({ per_page: 200 })
  ]);
  master.value = masterResponse.data;
  receivables.value = receivableResponse.data || [];
  const options = isCash.value ? master.value.cash_funds : master.value.bank_accounts;
  sourceId.value = options[0]?.id || null;
  destinationId.value = options[1]?.id || null;
});
</script>
