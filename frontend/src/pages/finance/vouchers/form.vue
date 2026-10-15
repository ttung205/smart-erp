<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="pageTitle">
      <template #actions>
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined @click="router.back()" />
        <Button label="Tạo phiếu" icon="pi pi-check" size="small" :loading="saving" @click="save" />
      </template>
    </PageHeader>

    <section class="bg-white border border-slate-200 rounded-[6px] shadow-sm">
      <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <h2 class="text-sm font-bold text-slate-900 m-0">Thông tin chứng từ thu / chi</h2>
        <span class="px-2 py-1 rounded text-xs font-semibold text-white" :class="isReceipt ? 'bg-emerald-600' : 'bg-orange-500'">{{ voucherBadge }}</span>
      </div>

      <div class="document-layout">
        <div class="document-main">
          <div v-if="!isTransfer" class="form-row form-row-party">
          <div class="field-block">
            <label>Loại đối tượng</label>
            <Select v-model="partyType" :options="partyTypeOptions" option-label="label" option-value="value" fluid @change="changePartyType" />
          </div>
          <div v-if="partyType !== 'OTHER'" class="field-block">
            <label>Chọn đối tượng</label>
            <Select v-model="partyId" :options="partnerOptions" option-label="label" option-value="value" filter show-clear fluid :loading="loadingPartners" placeholder="Chọn trong danh mục..." @change="selectPartner">
              <template #empty><div class="p-3 text-sm text-slate-500">Chưa có đối tượng phù hợp.</div></template>
            </Select>
          </div>
          <div v-else class="field-block">
            <label>Chọn đối tượng</label>
            <InputText v-model="form.party_name" fluid placeholder="Nhập tên đối tượng..." />
          </div>
          <div class="field-block">
            <label>Mã đối tượng</label>
            <InputText v-model="form.party_code" fluid placeholder="Tự lấy mã" :disabled="partyType !== 'OTHER'" />
          </div>
          <div class="field-block">
            <label>Tên đối tượng <span class="text-red-500">*</span></label>
            <InputText v-model="form.party_name" fluid placeholder="Tên đối tượng nộp / nhận" :disabled="partyType !== 'OTHER'" />
          </div>
          </div>

          <div v-if="!isTransfer" class="form-row form-row-contact">
          <div class="field-block">
            <label>{{ isReceipt ? 'Người nộp tiền' : 'Người nhận tiền' }} <em>(Có thể sửa đổi)</em></label>
            <InputText v-model="form.contact_name" fluid :placeholder="isReceipt ? 'Họ và tên người nộp' : 'Họ và tên người nhận'" />
          </div>
          <div class="field-block">
            <label>Địa chỉ</label>
            <InputText v-model="form.party_address" fluid placeholder="Tự lấy theo đối tượng" />
          </div>
          </div>

          <div class="form-row" :class="isTransfer ? 'form-row-source' : 'form-row-business'">
          <div v-if="!isTransfer" class="field-block">
            <label>{{ isReceipt ? 'Lý do thu' : 'Lý do chi' }} <span class="text-red-500">*</span></label>
            <Select v-model="form.reason_code" :options="reasonOptions" option-label="label" option-value="value" fluid placeholder="Chọn lý do..." @change="applyReason" />
          </div>
          <div class="field-block">
            <label>Diễn giải chi tiết</label>
            <InputText v-model="form.description" fluid :placeholder="isReceipt ? 'Nội dung chi tiết nghiệp vụ thu...' : 'Nội dung chi tiết nghiệp vụ chi...'" @change="syncEmptyDescriptions" />
          </div>
          <div v-if="!isTransfer" class="field-block">
            <label>{{ isReceipt ? 'Nhân viên thu' : 'Nhân viên chi' }}</label>
            <Select
              v-model="form.employee_name"
              :options="financeEmployeeOptions"
              option-label="label"
              option-value="value"
              filter
              show-clear
              fluid
              :loading="loadingFinanceUsers"
              placeholder="Chọn nhân viên tài chính..."
            />
          </div>
          </div>

          <div v-if="!isCash" class="form-row" :class="isTransfer ? 'form-row-transfer' : 'form-row-source'">
          <div class="field-block">
            <label>{{ isCash ? 'Quỹ tiền mặt' : 'Tài khoản ngân hàng' }} <span class="text-red-500">*</span></label>
            <Select v-model="sourceId" :options="sourceOptions" option-label="label" option-value="value" fluid :loading="loadingMaster" placeholder="Chọn nguồn tiền..." @change="syncSourceAccount" />
          </div>
          <div v-if="isTransfer" class="field-block">
            <label>Tài khoản ngân hàng nhận <span class="text-red-500">*</span></label>
            <Select v-model="destinationId" :options="sourceOptions.filter(item => item.value !== sourceId)" option-label="label" option-value="value" fluid @change="syncSourceAccount" />
          </div>
          </div>
        </div>

        <aside class="document-dates">
          <div class="field-block">
            <label>Ngày hạch toán <span class="text-red-500">*</span></label>
            <DatePicker v-model="form.accounting_date" date-format="dd/mm/yy" show-icon fluid />
          </div>
          <div class="field-block">
            <label>Ngày chứng từ <span class="text-red-500">*</span></label>
            <DatePicker v-model="form.voucher_date" date-format="dd/mm/yy" show-icon fluid />
          </div>
          <div class="field-block">
            <label>Số chứng từ</label>
            <InputText disabled fluid placeholder="Để trống tự sinh số" />
            <small class="text-slate-500">Hệ thống tự sinh khi tạo phiếu.</small>
          </div>
        </aside>
      </div>
    </section>

    <section class="accounting-section">
      <ItemDataTable v-model="accountingLines" :columns="accountingColumns" :show-catalog-search="false" :show-column-setting="false" :show-export="false" :show-delete="false" add-row-label="Thêm dòng" :create-empty-row="createAccountingLine" scroll-height="310px" :table-style="{ minWidth: '1050px', width: '100%' }">
        <template #summary>
          <div class="flex items-center gap-5 flex-wrap">
            <strong class="text-sm text-slate-900">Định khoản hạch toán chi tiết ({{ accountingLines.length }})</strong>
            <span class="text-xs text-slate-500">Tổng số tiền: <b class="text-sky-600 text-sm">{{ formatMoney(totalAmount) }}</b></span>
          </div>
        </template>
        <template #extra-actions>
          <div class="currency-controls">
            <span class="whitespace-nowrap">Loại tiền:</span>
            <Select
              v-model="form.currency"
              :options="currencyOptions"
              option-label="label"
              option-value="value"
              class="currency-select"
              size="small"
              @change="changeCurrency"
            />
            <span class="whitespace-nowrap">Tỷ giá:</span>
            <InputNumber
              v-model="form.exchange_rate"
              :min="0"
              :min-fraction-digits="0"
              :max-fraction-digits="6"
              :disabled="form.currency === 'VND'"
              class="exchange-rate-input"
              input-class="!w-full text-right"
              placeholder="Nhập tỷ giá"
            />
          </div>
        </template>
        <template #col-description="{ data, notifyChange }"><InputText v-model="data.description" class="w-full table-input" placeholder="Diễn giải nghiệp vụ..." @change="notifyChange" /></template>
        <template #col-debit_account_code="{ data, notifyChange }"><Select v-model="data.debit_account_code" :options="accountOptions" option-label="label" option-value="value" filter fluid class="table-select" @change="notifyChange" /></template>
        <template #col-credit_account_code="{ data, notifyChange }"><Select v-model="data.credit_account_code" :options="accountOptions" option-label="label" option-value="value" filter fluid class="table-select" @change="notifyChange" /></template>
        <template #col-amount="{ data, notifyChange }"><InputNumber v-model="data.amount" :min="0" mode="currency" :currency="form.currency" locale="vi-VN" fluid input-class="text-right font-mono" @update:model-value="notifyChange" /></template>
        <template #col-party_name="{ data, notifyChange }"><InputText v-model="data.party_name" class="w-full table-input" placeholder="Tên đối tượng..." @change="notifyChange" /></template>
        <template #append-columns>
          <Column header="Xóa" style="width: 55px; min-width: 55px" class="text-center">
            <template #body="{ index }"><Button icon="pi pi-trash" severity="danger" text rounded size="small" :disabled="accountingLines.length === 1" @click="removeLine(index)" /></template>
          </Column>
        </template>
      </ItemDataTable>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DatePicker from 'primevue/datepicker';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import PageHeader from '../../../components/common/PageHeader.vue';
import ItemDataTable from '../../../components/common/ItemDataTable.vue';
import { financeApi } from '../../../api/finance.api';
import { catalogApi } from '../../../api/catalog.api';
import { identityApi } from '../../../api/identity.api';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const type = computed(() => route.query.type || 'CASH_RECEIPT');
const isCash = computed(() => type.value.startsWith('CASH'));
const isReceipt = computed(() => type.value.includes('RECEIPT'));
const isTransfer = computed(() => type.value === 'INTERNAL_TRANSFER');
const saving = ref(false);
const loadingMaster = ref(true);
const loadingPartners = ref(true);
const loadingFinanceUsers = ref(true);
const master = ref({ cash_funds: [], bank_accounts: [] });
const accounts = ref([]);
const receivables = ref([]);
const customers = ref([]);
const suppliers = ref([]);
const users = ref([]);
const sourceId = ref(null);
const destinationId = ref(null);
const partyType = ref(isReceipt.value ? 'CUSTOMER' : 'SUPPLIER');
const partyId = ref(null);

const form = reactive({ voucher_date: new Date(), accounting_date: new Date(), party_code: '', party_name: '', party_address: '', contact_name: '', reason_code: '', description: '', employee_name: '', currency: 'VND', exchange_rate: 1 });
const currencyOptions = [
  { label: 'VND', value: 'VND' },
  { label: 'USD', value: 'USD' },
  { label: 'EUR', value: 'EUR' },
  { label: 'GBP', value: 'GBP' },
  { label: 'JPY', value: 'JPY' },
  { label: 'CNY', value: 'CNY' },
  { label: 'KRW', value: 'KRW' },
  { label: 'SGD', value: 'SGD' },
  { label: 'THB', value: 'THB' },
  { label: 'AUD', value: 'AUD' },
  { label: 'CAD', value: 'CAD' },
  { label: 'CHF', value: 'CHF' },
  { label: 'HKD', value: 'HKD' }
];
const partyTypeOptions = [
  { label: 'Khách hàng', value: 'CUSTOMER' },
  { label: 'Nhà cung cấp', value: 'SUPPLIER' },
  { label: 'Đối tượng khác', value: 'OTHER' }
];
const receiptReasons = [
  { label: 'Thu tiền khách hàng', value: 'CUSTOMER_RECEIPT', account: '138' },
  { label: 'Thu hoàn ứng', value: 'REFUND_ADVANCE', account: '141' },
  { label: 'Thu khác', value: 'OTHER_RECEIPT', account: '711' }
];
const paymentReasons = [
  { label: 'Trả tiền nhà cung cấp', value: 'SUPPLIER_PAYMENT', account: '331' },
  { label: 'Chi phí quản lý', value: 'ADMIN_EXPENSE', account: '642' },
  { label: 'Chi khác', value: 'OTHER_PAYMENT', account: '811' }
];
const reasonOptions = computed(() => isReceipt.value ? receiptReasons : paymentReasons);
const pageTitle = computed(() => ({ CASH_RECEIPT: 'Lập Phiếu thu tiền mặt', CASH_PAYMENT: 'Lập Phiếu chi tiền mặt', BANK_RECEIPT: 'Lập Chứng từ thu ngân hàng', BANK_PAYMENT: 'Lập Chứng từ chi ngân hàng', INTERNAL_TRANSFER: 'Chuyển tiền nội bộ' }[type.value]));
const voucherBadge = computed(() => ({ CASH_RECEIPT: 'Phiếu thu tiền mặt', CASH_PAYMENT: 'Phiếu chi tiền mặt', BANK_RECEIPT: 'Thu tiền gửi', BANK_PAYMENT: 'Chi tiền gửi', INTERNAL_TRANSFER: 'Chuyển tiền nội bộ' }[type.value]));

const sourceItems = computed(() => isCash.value ? master.value.cash_funds : master.value.bank_accounts);
const sourceOptions = computed(() => sourceItems.value.map(item => ({ value: item.id, label: isCash.value ? `${item.code} - ${item.name}` : `${item.bank_name} - ${item.account_number}` })));
const accountOptions = computed(() => accounts.value.filter(item => item.is_postable).map(item => ({ value: item.code, label: `${item.code} - ${item.name}` })));
const partnerItems = computed(() => partyType.value === 'CUSTOMER' ? customers.value : suppliers.value);
const partnerOptions = computed(() => partnerItems.value.map(item => ({ value: Number(item.id), label: `${item.code || ''} - ${item.name}`.replace(/^ - /, '') })));
const financeEmployeeOptions = computed(() => users.value
  .filter(user => Number(user.is_active) === 1 && user.roles?.some(role => ['FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER'].includes(role.code)))
  .map(user => ({ value: user.name, label: `${user.name} - ${user.email}` })));
const accountingColumns = computed(() => [
  { field: 'description', header: 'Diễn giải', minWidth: '300px' },
  { field: 'debit_account_code', header: 'TK Nợ', width: '165px' },
  { field: 'credit_account_code', header: 'TK Có', width: '165px' },
  { field: 'amount', header: `Số tiền (${form.currency})`, width: '190px', align: 'right' },
  { field: 'party_name', header: 'Đối tượng', minWidth: '220px' }
]);

const selectedSourceAccount = () => sourceItems.value.find(item => Number(item.id) === Number(sourceId.value))?.account?.code || (isCash.value ? '1111' : '1121');
const selectedDestinationAccount = () => master.value.bank_accounts.find(item => Number(item.id) === Number(destinationId.value))?.account?.code || '1121';
const defaultCounterAccount = () => reasonOptions.value.find(item => item.value === form.reason_code)?.account || (isReceipt.value ? '138' : '331');
const createAccountingLine = () => ({
  id: Date.now() + Math.random(), description: form.description || '',
  debit_account_code: isTransfer.value ? selectedDestinationAccount() : (isReceipt.value ? selectedSourceAccount() : defaultCounterAccount()),
  credit_account_code: isTransfer.value ? selectedSourceAccount() : (isReceipt.value ? defaultCounterAccount() : selectedSourceAccount()),
  amount: 0, party_name: form.party_name || ''
});
const accountingLines = ref([createAccountingLine()]);
const totalAmount = computed(() => accountingLines.value.reduce((sum, line) => sum + Number(line.amount || 0), 0));
const formatMoney = amount => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: form.currency, maximumFractionDigits: 2 }).format(Number(amount || 0));

const normalizeList = response => Array.isArray(response?.data) ? response.data : (Array.isArray(response?.data?.data) ? response.data.data : []);
const dateValue = value => {
  const date = value instanceof Date ? value : new Date(value);
  const offsetDate = new Date(date.getTime() - date.getTimezoneOffset() * 60000);
  return offsetDate.toISOString().slice(0, 10);
};
const mergeFallbackCustomers = () => {
  const map = new Map(customers.value.map(item => [Number(item.id), item]));
  receivables.value.forEach(item => {
    const id = Number(item.customer_id);
    if (id && !map.has(id)) map.set(id, { id, code: item.customer_code || `KH-${id}`, name: item.customer_name, address: item.customer_address || '' });
  });
  customers.value = [...map.values()];
};

const changePartyType = () => {
  partyId.value = null;
  Object.assign(form, { party_code: '', party_name: '', party_address: '', contact_name: '' });
  accountingLines.value.forEach(line => { line.party_name = ''; });
};
const selectPartner = () => {
  const item = partnerItems.value.find(partner => Number(partner.id) === Number(partyId.value));
  if (!item) return;
  form.party_code = item.code || '';
  form.party_name = item.name || '';
  form.party_address = item.address || '';
  form.contact_name = '';
  accountingLines.value.forEach(line => { line.party_name = form.party_name; });
};
const applyReason = () => {
  const reason = reasonOptions.value.find(item => item.value === form.reason_code);
  if (!form.description || [...receiptReasons, ...paymentReasons].some(item => item.label === form.description)) form.description = reason?.label || '';
  accountingLines.value.forEach(line => {
    if (isReceipt.value) line.credit_account_code = reason?.account || '138';
    else line.debit_account_code = reason?.account || '331';
    if (!line.description) line.description = form.description;
  });
};
const syncSourceAccount = () => {
  accountingLines.value.forEach(line => {
    if (isTransfer.value) { line.debit_account_code = selectedDestinationAccount(); line.credit_account_code = selectedSourceAccount(); }
    else if (isReceipt.value) line.debit_account_code = selectedSourceAccount();
    else line.credit_account_code = selectedSourceAccount();
  });
};
const syncEmptyDescriptions = () => accountingLines.value.forEach(line => { if (!line.description) line.description = form.description; });
const removeLine = index => { if (accountingLines.value.length > 1) accountingLines.value.splice(index, 1); };
const changeCurrency = () => {
  form.exchange_rate = form.currency === 'VND' ? 1 : null;
};

const save = async () => {
  const missingParty = !isTransfer.value && ((partyType.value !== 'OTHER' && !partyId.value) || !form.party_name.trim());
  const invalidLines = accountingLines.value.some(line => !line.description?.trim() || !line.debit_account_code || !line.credit_account_code || Number(line.amount) <= 0);
  if (!sourceId.value || (isTransfer.value && !destinationId.value) || missingParty || (!isTransfer.value && !form.reason_code) || !form.description.trim() || totalAmount.value <= 0 || invalidLines || Number(form.exchange_rate) <= 0) {
    toast.add({ severity: 'warn', summary: 'Thiếu thông tin', detail: 'Vui lòng chọn đối tượng, nguồn tiền và nhập đầy đủ định khoản có số tiền lớn hơn 0.', life: 3500 });
    return;
  }
  saving.value = true;
  try {
    const payload = {
      voucher_type: type.value, channel: isCash.value ? 'CASH' : 'BANK', voucher_date: dateValue(form.voucher_date), accounting_date: dateValue(form.accounting_date),
      party_type: isTransfer.value ? null : partyType.value, party_id: isTransfer.value || partyType.value === 'OTHER' ? null : partyId.value,
      party_code: isTransfer.value ? null : form.party_code, party_name: isTransfer.value ? null : form.party_name, party_address: isTransfer.value ? null : form.party_address,
      contact_name: isTransfer.value ? null : form.contact_name, reason_code: isTransfer.value ? null : form.reason_code, employee_name: isTransfer.value ? null : form.employee_name,
      description: form.description, total_amount: totalAmount.value, currency: form.currency, exchange_rate: Number(form.exchange_rate),
      cash_fund_id: isCash.value ? sourceId.value : null, bank_account_id: isCash.value ? null : sourceId.value, destination_bank_account_id: isTransfer.value ? destinationId.value : null,
      allocations: [], lines: accountingLines.value.map(line => ({ description: line.description, debit_account_code: line.debit_account_code, credit_account_code: line.credit_account_code, amount: Number(line.amount) }))
    };
    const created = await financeApi.createVoucher(payload);
    toast.add({ severity: 'success', summary: 'Đã tạo phiếu nháp', detail: `Chứng từ ${created.data.voucher_number} đang chờ ghi sổ.`, life: 3000 });
    router.back();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thể tạo phiếu', detail: error.message, life: 4500 });
  } finally { saving.value = false; }
};

onMounted(async () => {
  const [masterResult, accountResult, receivableResult, customerResult, supplierResult, userResult] = await Promise.allSettled([
    financeApi.getMasterData(), financeApi.getAccounts(), financeApi.getCustomerReceivables({ per_page: 200 }), catalogApi.getCustomers(), catalogApi.getSuppliers(), identityApi.getUsers()
  ]);
  if (masterResult.status === 'fulfilled') master.value = masterResult.value.data || master.value;
  else toast.add({ severity: 'error', summary: 'Không tải được nguồn tiền', detail: masterResult.reason?.message || 'Finance service chưa sẵn sàng.', life: 4000 });
  if (accountResult.status === 'fulfilled') accounts.value = normalizeList(accountResult.value);
  if (receivableResult.status === 'fulfilled') receivables.value = normalizeList(receivableResult.value);
  if (customerResult.status === 'fulfilled') customers.value = normalizeList(customerResult.value);
  if (supplierResult.status === 'fulfilled') suppliers.value = normalizeList(supplierResult.value);
  if (userResult.status === 'fulfilled') users.value = normalizeList(userResult.value);
  else toast.add({ severity: 'warn', summary: 'Không tải được nhân viên tài chính', detail: userResult.reason?.message || 'Identity Service chưa sẵn sàng.', life: 4000 });
  mergeFallbackCustomers();
  loadingMaster.value = false;
  loadingPartners.value = false;
  loadingFinanceUsers.value = false;
  sourceId.value = sourceItems.value[0]?.id || null;
  destinationId.value = master.value.bank_accounts.find(item => Number(item.id) !== Number(sourceId.value))?.id || null;
  syncSourceAccount();
});
</script>

<style scoped>
.document-layout {
  display: grid;
  grid-template-columns: minmax(0, 3.1fr) minmax(0, 1fr);
  gap: 1.25rem;
  align-items: start;
  padding: 1.25rem;
}
.document-main {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.75rem;
}
.form-row {
  display: grid;
  min-width: 0;
  gap: 0.75rem;
  align-items: end;
}
.form-row-party { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr) minmax(0, 1fr) minmax(0, 2fr); }
.form-row-contact { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); }
.form-row-business { grid-template-columns: minmax(0, 4fr) minmax(0, 5fr) minmax(0, 3fr); }
.form-row-source { grid-template-columns: minmax(0, 1fr); }
.form-row-transfer { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.field-block { min-width: 0; }
.field-block :deep(.p-inputtext),
.field-block :deep(.p-select),
.field-block :deep(.p-datepicker) { width: 100%; min-width: 0; }
.field-block :deep(.p-select-label) { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.document-dates {
  display: flex;
  min-width: 0;
  flex-direction: column;
  gap: 0.75rem;
  padding: 0.875rem;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  background: #f8fafc;
}
.document-dates small { display: block; margin-top: 0.25rem; }
.field-block label { display: block; margin-bottom: 0.35rem; color: #334155; font-size: 0.75rem; font-weight: 700; }
.field-block label em { margin-left: 0.5rem; color: #94a3b8; font-size: 0.68rem; font-weight: 400; }
.table-input { padding-top: 0.4rem; padding-bottom: 0.4rem; font-size: 0.75rem; }
.currency-controls {
  display: flex;
  min-width: 0;
  max-width: 100%;
  flex-wrap: wrap;
  align-items: center;
  justify-content: flex-end;
  gap: 0.5rem;
  color: #475569;
  font-size: 0.75rem;
}
.currency-select { width: 7rem; min-width: 7rem; }
.exchange-rate-input { width: 8.5rem; min-width: 0; }
.exchange-rate-input :deep(.p-inputnumber-input) { width: 100%; min-width: 0; }
@media (max-width: 820px) {
  .document-layout { grid-template-columns: 1fr; }
  .document-dates { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 540px) {
  .form-row-party, .form-row-contact, .form-row-business, .form-row-transfer { grid-template-columns: 1fr; }
  .document-dates { grid-template-columns: 1fr; }
}
</style>
