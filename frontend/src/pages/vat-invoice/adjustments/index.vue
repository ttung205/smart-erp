<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Hóa đơn điều chỉnh" subtitle="Theo dõi yêu cầu điều chỉnh và hóa đơn đã phát hành">
      <template #actions><Button v-if="canCreate" label="Lập yêu cầu điều chỉnh" icon="pi pi-plus" size="small" @click="router.push('/vat-invoice/adjustments/new')" /></template>
    </PageHeader>
    <CommonDataTable :value="adjustments" :columns="columns" :rows="20" :loading="loading" empty-title="Chưa có hóa đơn điều chỉnh">
      <template #col-adjustment_number="{ data }"><button class="font-mono font-semibold text-sky-700" @click="router.push(`/vat-invoice/adjustments/${data.id}`)">{{ data.adjustment_number }}</button></template>
      <template #col-issued_invoice="{ data }"><span class="font-mono font-semibold">{{ data.issued_invoice ? `${data.issued_invoice.invoice_series} / ${data.issued_invoice.invoice_number}` : 'Chưa phát hành' }}</span></template>
      <template #col-original_vat_invoice_id="{ data }">{{ data.original_invoice?.invoice_number }}</template>
      <template #col-adjustment_type="{ data }">{{ typeLabel(data.adjustment_type) }}</template>
      <template #col-direction="{ data }"><Tag :value="data.direction === 'DECREASE' ? 'Giảm' : 'Tăng'" :severity="data.direction === 'DECREASE' ? 'warn' : 'success'" /></template>
      <template #col-status="{ data }"><Tag :value="statusLabel(data.status)" :severity="data.status === 'ISSUED' ? 'success' : 'warn'" /></template>
      <template #col-total_delta="{ data }"><div class="text-right font-mono font-semibold">{{ money(data.total_delta, data.original_invoice?.currency) }}</div></template>
      <template #col-actions="{ data }"><Button :label="data.status === 'ISSUED' ? 'Xem chi tiết' : 'Mở form'" icon="pi pi-eye" size="small" outlined @click="router.push(`/vat-invoice/adjustments/${data.id}`)" /></template>
    </CommonDataTable>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import PageHeader from '../../../components/common/PageHeader.vue';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { salesApi } from '../../../api/sales.api';
import { storage } from '../../../utils/storage';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';

const router = useRouter();
const toast = useToast();
const user = parseJwt(storage.getToken());
const canCreate = hasPermission('finance.create', user);
const adjustments = ref([]);
const loading = ref(false);
const columns = [
  { field: 'adjustment_number', header: 'Số điều chỉnh', width: '170px' },
  { field: 'issued_invoice', header: 'Số hóa đơn VAT', width: '200px' },
  { field: 'original_vat_invoice_id', header: 'Hóa đơn gốc', width: '150px' },
  { field: 'adjustment_date', header: 'Ngày điều chỉnh', width: '140px' },
  { field: 'adjustment_type', header: 'Loại nghiệp vụ', width: '150px' },
  { field: 'direction', header: 'Chiều', width: '100px' },
  { field: 'total_delta', header: 'Chênh lệch', width: '170px', align: 'right' },
  { field: 'status', header: 'Trạng thái', width: '150px' },
  { field: 'actions', header: 'Thao tác', width: '145px', sortable: false }
];
const typeLabel = type => ({ PRICE: 'Đơn giá', VAT: 'Thuế suất VAT (%)', QUANTITY: 'Số lượng' })[type] || type;
const statusLabel = status => ({ DRAFT: 'Chờ duyệt', APPROVED: 'Đã duyệt, chờ phát hành', PENDING_FINANCE: 'Chờ đồng bộ kế toán', ISSUED: 'Đã phát hành' })[status] || status;
const money = (value, currency = 'VND') => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: currency || 'VND' }).format(value || 0);
onMounted(async () => {
  loading.value = true;
  try { adjustments.value = (await salesApi.getVatInvoiceAdjustments()).data || []; }
  catch (error) { toast.add({ severity: 'error', summary: 'Không tải được hóa đơn điều chỉnh', detail: error.message, life: 5000 }); }
  finally { loading.value = false; }
});
</script>
