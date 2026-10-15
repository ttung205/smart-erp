<template>
  <div class="space-y-5 pb-10">
    <div class="flex flex-wrap justify-between items-center gap-3">
      <h1 class="text-xl font-semibold m-0">Điều chỉnh hóa đơn {{ invoice?.invoice_number || '' }}</h1>
      <div class="flex gap-2">
        <Button label="Quay lại" icon="pi pi-arrow-left" severity="secondary" outlined @click="router.push('/vat-invoice/adjustments')" />
        <Button v-if="!isDetail && canCreate" label="Gửi yêu cầu điều chỉnh hóa đơn" icon="pi pi-check" :loading="saving" :disabled="!valid" @click="submit" />
        <Button v-if="isDetail && canApprove && adjustment?.status === 'DRAFT'" label="Duyệt yêu cầu" icon="pi pi-check" :loading="saving" @click="approve" />
        <Button v-if="isDetail && canApprove && ['APPROVED', 'PENDING_FINANCE'].includes(adjustment?.status)" label="Phát hành hóa đơn điều chỉnh" icon="pi pi-send" :loading="saving" @click="issue" />
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-md p-5 space-y-4">
      <h2 class="text-lg font-medium m-0">Thông tin chung</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <label class="text-sm">Khách hàng <InputText :model-value="invoice?.customer_name || ''" disabled fluid class="mt-1" /></label>
        <label class="text-sm">Số hóa đơn gốc *
          <Select v-if="!isDetail" v-model="invoiceId" :options="invoiceOptions" option-label="label" option-value="value" filter fluid placeholder="Chọn hóa đơn" class="mt-1" @change="loadInvoice" />
          <InputText v-else :model-value="invoice?.invoice_number || ''" disabled fluid class="mt-1" />
        </label>
        <label class="text-sm">Chiều điều chỉnh * <Select v-model="direction" :options="directions" option-label="label" option-value="value" :disabled="isDetail" fluid class="mt-1" /></label>
        <label class="text-sm">Loại nghiệp vụ * <Select v-model="type" :options="types" option-label="label" option-value="value" :disabled="isDetail" fluid class="mt-1" /></label>
        <label class="text-sm">Ngày điều chỉnh * <InputText v-model="date" type="date" :disabled="isDetail" fluid class="mt-1" /></label>
        <label class="text-sm">Tiền tệ <InputText :model-value="invoice?.currency || 'VND'" disabled fluid class="mt-1" /></label>
      </div>
      <label class="block text-sm">Lý do điều chỉnh * <Textarea v-model="reason" :disabled="isDetail" rows="3" fluid class="mt-1" /></label>
      <div v-if="adjustment" class="text-sm text-slate-600">Số yêu cầu: <strong>{{ adjustment.adjustment_number }}</strong> · Trạng thái: <strong>{{ statusLabel(adjustment.status) }}</strong>
        <span v-if="adjustment.issued_invoice"> · Hóa đơn VAT: <strong>{{ adjustment.issued_invoice.invoice_series }} / {{ adjustment.issued_invoice.invoice_number }}</strong></span>
        <span v-if="adjustment.sale_return_id"> · Phiếu trả hàng: {{ adjustment.sale_return?.return_number }}</span>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-md p-5 space-y-3">
      <div class="flex justify-between items-center"><h2 class="text-lg font-medium m-0">Dòng điều chỉnh</h2><Tag :value="`${selectedRows.length} dòng chọn`" /></div>
      <div v-if="!invoice" class="text-sm text-slate-500 py-8 text-center">Chọn hóa đơn gốc để xem các dòng hàng.</div>
      <ItemDataTable v-else v-model="rows" :columns="itemColumns" :show-toolbar="false" :show-add-row="false" :show-catalog-search="false" :show-column-setting="false" :show-export="false" :show-delete="false" :table-style="{ minWidth: '1080px' }">
        <template #prepend-columns="{ notifyChange }">
          <Column header="Chọn" style="width: 55px; min-width: 55px" class="text-center" frozen>
            <template #body="{ data }"><input v-model="data.selected" type="checkbox" :disabled="isDetail" @change="notifyChange" /></template>
          </Column>
        </template>
        <template #col-sku="{ data }"><span class="font-mono">{{ data.sku }}</span></template>
        <template #col-product_name="{ data }">{{ data.product_name }}</template>
        <template #col-quantity="{ data }"><span class="font-mono">{{ data.quantity }}</span><small v-if="data.effectiveQuantity !== data.quantity" class="block text-slate-500">Còn {{ data.effectiveQuantity }}</small></template>
        <template #col-unit_price="{ data }">{{ money(data.unit_price) }}</template>
        <template #col-vat_percent="{ data }">{{ effectiveVatPercent(data) }}%</template>
        <template #col-vat_amount="{ data }">{{ money(data.vat_amount) }}</template>
        <template #col-amount="{ data, notifyChange }"><InputNumber v-model="data.amount" :disabled="isDetail || !data.selected" :min="0" :max="type === 'VAT' ? (direction === 'DECREASE' ? effectiveVatPercent(data) : 100 - effectiveVatPercent(data)) : undefined" :max-fraction-digits="2" :suffix="type === 'VAT' ? '%' : undefined" fluid @update:model-value="notifyChange" /></template>
        <template #col-total_delta="{ data }"><span class="font-mono">{{ money(rowDelta(data)) }}</span></template>
      </ItemDataTable>
      <div class="text-right text-sm space-y-1"><div>Chênh lệch tiền hàng: <strong>{{ money(totals.subtotal) }}</strong></div><div>Chênh lệch VAT: <strong>{{ money(totals.vat) }}</strong></div><div class="text-lg">Tổng điều chỉnh: <strong>{{ money(totals.total) }}</strong></div></div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Column from 'primevue/column';
import Textarea from 'primevue/textarea';
import Tag from 'primevue/tag';
import ItemDataTable from '../../../components/common/ItemDataTable.vue';
import { salesApi } from '../../../api/sales.api';
import { storage } from '../../../utils/storage';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';

const route = useRoute(); const router = useRouter(); const toast = useToast();
const user = parseJwt(storage.getToken());
const canCreate = hasPermission('finance.create', user);
const canApprove = hasPermission('finance.approve', user);
const isDetail = computed(() => route.params.id && route.params.id !== 'new');
const invoiceList = ref([]); const invoice = ref(null); const invoiceId = ref(null); const adjustment = ref(null);
const localToday = new Date();
const direction = ref('DECREASE'); const type = ref('PRICE');
const date = ref(`${localToday.getFullYear()}-${String(localToday.getMonth() + 1).padStart(2, '0')}-${String(localToday.getDate()).padStart(2, '0')}`);
const reason = ref('');
const rows = ref([]); const saving = ref(false);
const directions = [{ label: 'Tăng', value: 'INCREASE' }, { label: 'Giảm', value: 'DECREASE' }];
const types = computed(() => [...[{ label: 'Đơn giá', value: 'PRICE' }, { label: 'Thuế suất VAT (%)', value: 'VAT' }], ...(isDetail.value ? [{ label: 'Số lượng trả hàng', value: 'QUANTITY' }] : [])]);
const itemColumns = computed(() => [
  { field: 'sku', header: 'Mã hàng', width: '160px' },
  { field: 'product_name', header: 'Tên hàng', width: '230px' },
  { field: 'quantity', header: 'SL gốc', width: '95px', align: 'right' },
  { field: 'unit_price', header: 'Đơn giá gốc', width: '135px', align: 'right' },
  { field: 'vat_percent', header: 'VAT % hiện tại', width: '105px', align: 'right' },
  { field: 'vat_amount', header: 'VAT gốc', width: '135px', align: 'right' },
  { field: 'amount', header: type.value === 'PRICE' ? 'Chênh lệch đơn giá' : type.value === 'VAT' ? 'Chênh lệch VAT (%)' : 'SL giảm', width: '165px', align: 'right' },
  { field: 'total_delta', header: 'Chênh lệch tiền', width: '150px', align: 'right' }
]);
const invoiceOptions = computed(() => invoiceList.value.filter(item => !item.adjustment_id).map(item => ({ value: item.id, label: `${item.invoice_number} — ${item.customer_name || ''}` })));
const selectedRows = computed(() => rows.value.filter(row => row.selected && Number(row.amount) > 0));
const effectiveVatPercent = row => Number(row.effectiveVatPercent ?? row.vat_percent ?? 0);
const rowAmounts = row => {
  if (!row.selected) return { subtotal: 0, vat: 0 };
  if (isDetail.value) return { subtotal: row.storedSubtotal || 0, vat: row.storedVat || 0 };
  const sign = direction.value === 'DECREASE' ? -1 : 1;
  const amount = Number(row.amount || 0);
  const subtotal = type.value === 'PRICE' ? Math.round(sign * amount * row.effectiveQuantity * 100) / 100
    : type.value === 'QUANTITY' ? Math.round(sign * amount * row.unit_price * 100) / 100 : 0;
  const vat = type.value === 'VAT' ? Math.round(Number(row.effectiveSubtotal ?? row.subtotal) * sign * amount) / 100
    : Math.round(subtotal * effectiveVatPercent(row)) / 100;
  return { subtotal, vat };
};
const rowDelta = row => { const value = rowAmounts(row); return value.subtotal + value.vat; };
const totals = computed(() => { const subtotal = rows.value.reduce((sum, row) => sum + rowAmounts(row).subtotal, 0); const vat = rows.value.reduce((sum, row) => sum + rowAmounts(row).vat, 0); return { subtotal, vat, total: subtotal + vat }; });
const valid = computed(() => invoiceId.value && date.value && reason.value.trim() && selectedRows.value.length && Math.abs(totals.value.total) >= 0.01
  && (type.value !== 'VAT' || selectedRows.value.every(row => Number(row.amount) <= (direction.value === 'DECREASE' ? effectiveVatPercent(row) : 100 - effectiveVatPercent(row)))));
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: invoice.value?.currency || 'VND' }).format(value || 0);
const statusLabel = status => ({ DRAFT: 'Chờ duyệt', APPROVED: 'Đã duyệt, chờ phát hành', PENDING_FINANCE: 'Chờ đồng bộ kế toán', ISSUED: 'Đã phát hành' })[status] || status;
const loadInvoice = async () => {
  if (!invoiceId.value) { invoice.value = null; rows.value = []; return; }
  try {
    invoice.value = (await salesApi.getVatInvoiceById(invoiceId.value)).data;
    const adjustments = (await salesApi.getVatInvoiceAdjustments({ original_vat_invoice_id: invoiceId.value })).data || [];
    rows.value = (invoice.value.items || []).map(item => {
      const issuedLines = adjustments.filter(entry => entry.status === 'ISSUED')
        .flatMap(entry => entry.items || []).filter(line => line.original_vat_invoice_item_id === item.id);
      const sum = field => issuedLines.reduce((total, line) => total + Number(line[field] || 0), 0);
      return { ...item, effectiveQuantity: Number(item.quantity) + sum('quantity_delta'),
        effectiveSubtotal: Number(item.subtotal) + sum('subtotal_delta'),
        effectiveVatPercent: Number(item.vat_percent) + sum('vat_percent_delta'), selected: false, amount: 0 };
    });
  } catch (error) { toast.add({ severity: 'error', summary: 'Không tải được hóa đơn', detail: error.message, life: 5000 }); }
};
const submit = async () => {
  if (!valid.value) return;
  saving.value = true;
  try {
    const response = await salesApi.createVatInvoiceAdjustment({
      original_vat_invoice_id: invoiceId.value, direction: direction.value,
      adjustment_type: type.value, adjustment_date: date.value, reason: reason.value.trim(),
      items: selectedRows.value.map(row => ({ original_vat_invoice_item_id: row.id, amount: row.amount }))
    });
    toast.add({ severity: 'success', summary: 'Đã gửi yêu cầu điều chỉnh', life: 3000 });
    router.push(`/vat-invoice/adjustments/${response.data.id}`);
  } catch (error) { toast.add({ severity: 'error', summary: 'Không tạo được yêu cầu', detail: error.message, life: 6000 }); }
  finally { saving.value = false; }
};
const approve = async () => {
  saving.value = true;
  try {
    adjustment.value = (await salesApi.approveVatInvoiceAdjustment(adjustment.value.id)).data;
    toast.add({ severity: 'success', summary: 'Đã duyệt yêu cầu điều chỉnh', life: 4000 });
  } catch (error) { toast.add({ severity: 'error', summary: 'Chưa thể duyệt', detail: error.message, life: 6000 }); }
  finally { saving.value = false; }
};
const issue = async () => {
  saving.value = true;
  try {
    adjustment.value = (await salesApi.issueVatInvoiceAdjustment(adjustment.value.id)).data;
    toast.add({ severity: 'success', summary: 'Đã phát hành hóa đơn điều chỉnh', life: 4000 });
  } catch (error) { toast.add({ severity: 'error', summary: 'Chưa thể phát hành', detail: error.message, life: 6000 }); }
  finally { saving.value = false; }
};
onMounted(async () => {
  try {
    invoiceList.value = (await salesApi.getVatInvoices({ per_page: 200 })).data || [];
    if (isDetail.value) {
      adjustment.value = (await salesApi.getVatInvoiceAdjustment(route.params.id)).data;
      invoice.value = adjustment.value.original_invoice;
      invoiceId.value = invoice.value?.id;
      direction.value = adjustment.value.direction; type.value = adjustment.value.adjustment_type;
      date.value = adjustment.value.adjustment_date; reason.value = adjustment.value.reason;
      const previousAdjustments = (await salesApi.getVatInvoiceAdjustments({ original_vat_invoice_id: invoiceId.value })).data || [];
      rows.value = (invoice.value?.items || []).map(item => {
        const line = adjustment.value.items.find(entry => entry.original_vat_invoice_item_id === item.id);
        const previousLines = previousAdjustments.filter(entry => entry.status === 'ISSUED' && entry.id !== adjustment.value.id)
          .flatMap(entry => entry.items || []).filter(entry => entry.original_vat_invoice_item_id === item.id);
        const sum = field => previousLines.reduce((total, entry) => total + Number(entry[field] || 0), 0);
        return { ...item, effectiveQuantity: Number(item.quantity) + sum('quantity_delta'),
          effectiveSubtotal: Number(item.subtotal) + sum('subtotal_delta'),
          effectiveVatPercent: Number(item.vat_percent) + sum('vat_percent_delta'),
          selected: !!line, amount: line ? Math.abs(type.value === 'PRICE' ? line.unit_price_delta : type.value === 'VAT' ? line.vat_percent_delta : line.quantity_delta) : 0,
          storedSubtotal: line?.subtotal_delta || 0, storedVat: line?.vat_delta || 0 };
      });
    } else if (route.query.invoice) {
      invoiceId.value = Number(route.query.invoice); await loadInvoice();
    }
  } catch (error) { toast.add({ severity: 'error', summary: 'Không tải được dữ liệu điều chỉnh', detail: error.message, life: 6000 }); }
});
</script>
