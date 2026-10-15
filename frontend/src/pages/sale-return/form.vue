<template>
  <div class="space-y-4 pb-10">
    <div class="flex items-center justify-between bg-white border border-slate-200 rounded-lg px-5 py-3 shadow-sm">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" label="Quay lại" size="small" severity="secondary" outlined @click="router.push('/sale-return')" />
        <div><div class="flex items-center gap-2"><h1 class="m-0 text-base font-bold">{{ form.return_number || 'Phiếu trả hàng bán' }}</h1><Tag value="Đã lập phiếu" severity="success" /></div><p class="m-0 mt-1 text-xs text-slate-500">Phiếu chỉ đọc, được tạo từ yêu cầu {{ form.request_number }}</p></div>
      </div>
      <div class="flex gap-2">
        <Button v-if="form.sale_return_request_id" label="Xem yêu cầu nguồn" icon="pi pi-external-link" size="small" outlined @click="router.push(`/sale-return-request/${form.sale_return_request_id}`)" />
        <Button v-if="canCreateAdjustment" label="Tạo hóa đơn điều chỉnh" icon="pi pi-file-edit" size="small" :loading="creatingAdjustment" @click="createAdjustment" />
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <Info label="Số phiếu" :value="form.return_number" mono />
        <Info label="Mã yêu cầu" :value="form.request_number" mono />
        <Info label="Ngày lập phiếu" :value="form.return_date" />
        <Info label="Người ghi sổ" :value="form.created_by_name" />
        <Info label="Khách hàng" :value="`${form.customer_code || ''} - ${form.customer_name || ''}`" class="xl:col-span-2" />
        <Info label="Kho kỹ thuật" :value="form.technical_warehouse_name" />
        <Info label="Kho chính đã nhập" :value="form.sales_warehouse_name" />
        <Info label="Chứng từ bán nguồn" :value="form.request?.documents?.map(item => item.invoice_number).join(', ')" class="xl:col-span-2" />
        <Info label="Lý do trả hàng" :value="form.request?.reason" />
        <Info label="Ghi chú" :value="form.request?.notes || '—'" />
      </div>
    </div>

    <div class="space-y-2">
      <div class="bg-white border border-slate-200 rounded-[6px] px-5 py-3"><h2 class="m-0 text-sm font-bold">Hàng hóa đã nhập kho chính</h2></div>
      <ItemDataTable
        :model-value="form.items || []"
        :columns="returnItemColumns"
        :show-toolbar="false"
        :show-add-row="false"
        :show-catalog-search="false"
        :show-column-setting="false"
        :show-export="false"
        :show-delete="false"
        :readonly="true"
        :table-style="{ minWidth: '1100px' }"
      >
        <template #col-invoice_number="{ data }"><span class="font-mono text-sky-700">{{ data.invoice_number }}</span></template>
        <template #col-sku="{ data }"><span class="font-mono text-xs font-bold text-sky-800">{{ data.sku }}</span></template>
        <template #col-product_name="{ data }"><span class="text-xs text-slate-800">{{ data.product_name }}</span></template>
        <template #col-unit="{ data }"><span class="text-xs text-slate-800">{{ data.unit }}</span></template>
        <template #col-quantity="{ data }"><span class="font-mono text-xs">{{ data.quantity }}</span></template>
        <template #col-unit_price="{ data }">{{ money(data.unit_price) }}</template>
        <template #col-line_total="{ data }"><span class="font-semibold">{{ money(data.line_total) }}</span></template>
      </ItemDataTable>
      <div class="bg-white border border-slate-200 rounded-[6px] px-5 py-4 flex justify-end"><div class="text-right"><div class="text-xs font-bold uppercase text-slate-500">Tổng giá trị nhập lại</div><div class="text-xl font-bold text-emerald-600">{{ money(form.total_amount) }}</div></div></div>
    </div>

    <FormAttachmentUpload v-if="form.request" v-model="form.request.attachments" :record-id="form.sale_return_request_id" section-type="sale_return_request" title="Hình ảnh và phiếu đánh giá chất lượng" readonly />
  </div>
</template>

<script setup>
import { defineComponent, h, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button'; import Tag from 'primevue/tag';
import FormAttachmentUpload from '../../components/common/FormAttachmentUpload.vue'; import ItemDataTable from '../../components/common/ItemDataTable.vue'; import { salesApi } from '../../api/sales.api';
import { storage } from '../../utils/storage'; import { parseJwt } from '../../utils/jwt'; import { hasPermission } from '../../utils/rbac';
const Info = defineComponent({ props: { label: String, value: [String, Number], mono: Boolean }, setup: props => () => h('div', { class: 'flex flex-col gap-1' }, [h('span', { class: 'text-xs font-bold text-slate-500 uppercase' }, props.label), h('div', { class: ['min-h-9 px-3 py-2 rounded border border-slate-200 bg-slate-50 text-sm text-slate-800', props.mono && 'font-mono font-semibold'] }, props.value || '—')]) });
const route = useRoute(); const router = useRouter(); const toast = useToast(); const form = ref({});
const canCreateAdjustment = hasPermission('finance.create', parseJwt(storage.getToken()));
const creatingAdjustment = ref(false);
const returnItemColumns = [
  { field: 'invoice_number', header: 'Chứng từ bán', width: '150px' },
  { field: 'sku', header: 'Mã hàng', width: '150px' },
  { field: 'product_name', header: 'Tên sản phẩm', width: '260px' },
  { field: 'brand', header: 'Thương hiệu', width: '120px' },
  { field: 'unit', header: 'ĐVT', width: '70px', align: 'center' },
  { field: 'quantity', header: 'Số lượng nhập', width: '110px', align: 'right' },
  { field: 'unit_price', header: 'Đơn giá', width: '130px', align: 'right' },
  { field: 'line_total', header: 'Thành tiền', width: '140px', align: 'right' }
];
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
const createAdjustment = async () => {
  creatingAdjustment.value = true;
  try {
    const result = await salesApi.createReturnVatInvoiceAdjustments(form.value.id);
    if (!result.data?.length) throw new Error('Không có dòng hàng đã xuất hóa đơn VAT để điều chỉnh.');
    toast.add({ severity: 'success', summary: `Đã tạo ${result.data.length} yêu cầu điều chỉnh giảm số lượng`, life: 4000 });
    router.push(`/vat-invoice/adjustments/${result.data[0].id}`);
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tạo được hóa đơn điều chỉnh', detail: error.message, life: 6000 });
  } finally { creatingAdjustment.value = false; }
};
onMounted(async () => { try { form.value = (await salesApi.getSaleReturn(route.params.id)).data; } catch (error) { toast.add({ severity: 'error', summary: 'Không tải được phiếu', detail: error?.data?.error?.message || error.message, life: 5000 }); } });
</script>
