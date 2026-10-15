<template>
  <div class="space-y-4 pb-10">
    <div class="flex flex-wrap items-center justify-between gap-3 bg-white border border-slate-200 rounded-lg px-5 py-3 shadow-sm">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" label="Quay lại" size="small" severity="secondary" outlined @click="router.push('/sale-return-request')" />
        <div>
          <div class="flex items-center gap-2"><h1 class="m-0 text-base font-bold">{{ isCreate ? 'Tạo yêu cầu nhập hàng trả lại' : form.request_number }}</h1><Tag v-if="!isCreate" :value="statusMeta(form.status).label" :severity="statusMeta(form.status).severity" /></div>
          <p class="m-0 mt-1 text-xs text-slate-500">Chọn chứng từ đã hoàn thành, đánh giá chất lượng và theo dõi luồng nhập kho</p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button v-if="isCreate" label="Tạo yêu cầu" icon="pi pi-save" size="small" :loading="saving" @click="createRequest" />
        <Button v-if="can('sale_return.technical') && form.status === 'PENDING_INSPECTION'" label="Bắt đầu kiểm tra" icon="pi pi-search" size="small" :loading="saving" @click="runAction('start-inspection')" />
        <Button v-if="can('sale_return.technical') && ['PENDING_INSPECTION','INSPECTING'].includes(form.status)" label="Từ chối" icon="pi pi-times" size="small" severity="danger" outlined @click="rejectRequest" />
        <Button v-if="can('sale_return.technical') && form.status === 'INSPECTING'" label="Lưu đánh giá" icon="pi pi-check-square" size="small" severity="info" :loading="saving" @click="saveInspection" />
        <Button v-if="can('sale_return.technical') && form.status === 'INSPECTING'" label="Nhập kho kỹ thuật" icon="pi pi-inbox" size="small" severity="success" :loading="saving" @click="receiveTechnical" />
        <Button v-if="can('sale_return.technical') && ['TECHNICAL_RECEIVED','MAIN_REJECTED'].includes(form.status)" label="Yêu cầu nhập kho chính" icon="pi pi-send" size="small" :loading="saving" @click="requestMain" />
        <Button v-if="can('sale_return.approve') && form.status === 'PENDING_MAIN_APPROVAL'" label="Duyệt nhập kho chính" icon="pi pi-check" size="small" severity="success" :loading="saving" @click="runAction('approve-main')" />
        <Button v-if="can('sale_return.approve') && form.status === 'PENDING_MAIN_APPROVAL'" label="Kho chính từ chối" icon="pi pi-times" size="small" severity="danger" outlined @click="rejectMain" />
        <Button v-if="can('sale_return.post') && form.status === 'PENDING_POSTING'" label="Ghi sổ & lập phiếu" icon="pi pi-book" size="small" severity="success" :loading="saving" @click="postReturn" />
        <Button v-if="form.sale_return" :label="`Xem phiếu ${form.sale_return.return_number}`" icon="pi pi-file-check" size="small" outlined @click="router.push(`/sale-return/${form.sale_return.id}`)" />
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700">Khách hàng <span class="text-red-500">*</span></label>
          <Select v-if="isCreate" v-model="customerId" :options="customers" option-label="display" option-value="id" filter placeholder="Chọn khách hàng" @change="onCustomerChange" />
          <InputText v-else :value="`${form.customer_code} - ${form.customer_name}`" readonly />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700">Chứng từ bán hàng <span class="text-red-500">*</span></label>
          <MultiSelect v-if="isCreate" v-model="selectedInvoiceIds" :options="invoiceOptions" option-label="display" option-value="id" filter display="chip" placeholder="Chọn một hoặc nhiều chứng từ" @change="onInvoicesChange" />
          <InputText v-else :value="form.documents?.map(d => d.invoice_number).join(', ')" readonly />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700">Lý do trả hàng <span class="text-red-500">*</span></label>
          <Select v-if="isCreate" v-model="form.reason" :options="reasonOptions" editable placeholder="Chọn hoặc nhập lý do trả hàng" />
          <InputText v-else v-model="form.reason" readonly />
        </div>
        <div v-if="!isCreate" class="grid grid-cols-2 gap-3">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700">Kho kỹ thuật</label>
            <Select
              v-if="can('sale_return.technical') && form.status === 'INSPECTING'"
              v-model="technicalWarehouseId"
              :options="technicalWarehouses"
              option-label="display"
              option-value="id"
              placeholder="Chọn kho kỹ thuật"
              empty-message="Không có kho kỹ thuật đang hoạt động"
              fluid
            />
            <InputText v-else :value="form.technical_warehouse_name || 'Chưa chọn'" readonly />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-slate-700">Kho chính</label>
            <Select
              v-if="can('sale_return.technical') && ['TECHNICAL_RECEIVED','MAIN_REJECTED'].includes(form.status)"
              v-model="mainWarehouseId"
              :options="salesWarehouses"
              option-label="display"
              option-value="id"
              placeholder="Chọn kho chính"
              empty-message="Không có kho bán đang hoạt động"
              fluid
            />
            <InputText v-else :value="form.sales_warehouse_name || (form.status === 'INSPECTING' ? 'Chọn sau khi nhập kho kỹ thuật' : 'Chưa chọn')" readonly />
          </div>
        </div>
        <div class="lg:col-span-2 flex flex-col gap-1.5">
          <label class="text-xs font-bold text-slate-700">Ghi chú thêm</label>
          <Textarea v-model="form.notes" rows="2" :readonly="!isCreate" placeholder="Nhập ghi chú (nếu có)..." />
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <div class="bg-white border border-slate-200 rounded-[6px] px-5 py-3 flex items-center justify-between"><h2 class="m-0 text-sm font-bold">Danh sách mã yêu cầu trả lại</h2><span class="text-xs text-slate-500">{{ items.length }} mặt hàng</span></div>
      <ItemDataTable
        v-model="form.items"
        :columns="requestItemColumns"
        :show-toolbar="false"
        :show-add-row="false"
        :show-catalog-search="false"
        :show-column-setting="false"
        :show-export="false"
        :show-delete="false"
        :readonly="true"
        scroll-height="520px"
        :table-style="{ minWidth: '1480px' }"
      >
        <template #col-invoice_number="{ data }"><span class="font-mono text-sky-700">{{ data.invoice_number }}</span></template>
        <template #col-sku="{ data }"><span class="font-mono text-xs font-bold text-sky-800">{{ data.sku }}</span></template>
        <template #col-product_name="{ data }"><span class="text-xs text-slate-800">{{ data.product_name }}</span></template>
        <template #col-unit="{ data }"><span class="text-xs text-slate-800">{{ data.unit }}</span></template>
        <template #col-requested_quantity="{ data, notifyChange }">
          <InputNumber v-if="isCreate" v-model="data.requested_quantity" :min="1" :max="data.available_return_quantity" show-buttons button-layout="horizontal" :step="1" fluid @update:model-value="notifyChange" />
          <span v-else>{{ data.requested_quantity }}</span>
        </template>
        <template #col-quality_status="{ data, notifyChange }">
          <Select v-if="can('sale_return.technical') && form.status === 'INSPECTING'" v-model="data.quality_status" :options="qualityOptions" option-label="label" option-value="value" fluid @change="notifyChange" />
          <Tag v-else :value="qualityLabel(data.quality_status)" :severity="data.quality_status === 'ELIGIBLE' ? 'success' : data.quality_status === 'INELIGIBLE' ? 'danger' : 'secondary'" />
        </template>
        <template #col-unit_price="{ data }">{{ money(data.unit_price) }}</template>
        <template #col-line_total="{ data }"><span class="font-semibold">{{ money((data.requested_quantity || 0) * data.unit_price) }}</span></template>
        <template #col-actions="{ data }"><Button v-if="isCreate" icon="pi pi-times" text severity="danger" size="small" @click="removeItem(data)" /></template>
        <template #empty><div class="py-8 text-center text-slate-500">Chọn chứng từ bán hàng để hiển thị các mã đã giao.</div></template>
      </ItemDataTable>
      <div class="bg-white border border-slate-200 rounded-[6px] px-5 py-4 flex justify-end"><div class="text-right"><div class="text-xs uppercase font-bold text-slate-500">Tổng tiền yêu cầu</div><div class="text-xl font-bold text-emerald-600">{{ money(totalAmount) }}</div></div></div>
    </div>

    <FormAttachmentUpload v-model="form.attachments" :record-id="form.id" section-type="sale_return_request" title="Hình ảnh sản phẩm - Phiếu đánh giá chất lượng" :readonly="(!isCreate && !can('sale_return.technical')) || ['REJECTED','COMPLETED'].includes(form.status)" />

    <div v-if="!isCreate" class="bg-white border border-slate-200 rounded-lg p-5 shadow-sm">
      <h2 class="m-0 mb-3 text-sm font-bold">Lịch sử xử lý</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3 text-xs">
        <div v-for="entry in auditEntries" :key="entry.label" class="border border-slate-200 rounded p-3"><div class="font-bold text-slate-700">{{ entry.label }}</div><div class="mt-1">{{ entry.name || '—' }}</div><div class="text-slate-500">{{ dateTime(entry.at) }}</div></div>
      </div>
      <Message v-if="form.rejection_reason" severity="error" class="mt-3">{{ form.rejection_reason }}</Message>
      <Message v-if="form.main_rejection_reason" severity="error" class="mt-3">{{ form.main_rejection_reason }}</Message>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button'; import InputNumber from 'primevue/inputnumber'; import InputText from 'primevue/inputtext'; import Message from 'primevue/message'; import MultiSelect from 'primevue/multiselect'; import Select from 'primevue/select'; import Tag from 'primevue/tag'; import Textarea from 'primevue/textarea';
import FormAttachmentUpload from '../../components/common/FormAttachmentUpload.vue';
import ItemDataTable from '../../components/common/ItemDataTable.vue';
import { catalogApi } from '../../api/catalog.api'; import { inventoryApi } from '../../api/inventory.api'; import { salesApi } from '../../api/sales.api'; import { statusMeta } from './status';
import { storage } from '../../utils/storage'; import { parseJwt } from '../../utils/jwt'; import { hasPermission } from '../../utils/rbac';

const route = useRoute(); const router = useRouter(); const toast = useToast();
const currentUser = parseJwt(storage.getToken()); const can = permission => hasPermission(permission, currentUser) || hasPermission('sale_return.manage', currentUser);
const isCreate = computed(() => !route.params.id); const saving = ref(false); const customers = ref([]); const invoices = ref([]); const selectedInvoiceIds = ref([]); const customerId = ref(null); const warehouses = ref([]); const technicalWarehouseId = ref(null); const mainWarehouseId = ref(null);
const form = ref({ reason: null, notes: '', items: [], attachments: [], status: null, documents: [] });
const reasonOptions = ['Hàng lỗi kỹ thuật', 'Giao sai hàng', 'Hàng hư hỏng', 'Giao dư số lượng', 'Bảo hành/đổi hàng', 'Khác'];
const qualityOptions = [{ label: 'Đủ điều kiện', value: 'ELIGIBLE' }, { label: 'Không đủ điều kiện', value: 'INELIGIBLE' }];
const requestItemColumns = computed(() => [
  { field: 'invoice_number', header: 'Số chứng từ', width: '145px', frozen: true },
  { field: 'sku', header: 'Mã hàng', width: '160px' },
  { field: 'product_name', header: 'Tên sản phẩm', width: '260px' },
  { field: 'brand', header: 'Thương hiệu', width: '120px' },
  { field: 'unit', header: 'ĐVT', width: '70px', align: 'center' },
  { field: 'delivered_quantity', header: 'Đã giao', width: '85px', align: 'right' },
  { field: 'previously_returned_quantity', header: 'Đã/đang trả', width: '100px', align: 'right' },
  { field: 'requested_quantity', header: 'SL trả', width: '130px', align: 'right' },
  ...(!isCreate.value ? [{ field: 'quality_status', header: 'Chất lượng', width: '145px', align: 'center' }] : []),
  { field: 'unit_price', header: 'Đơn giá', width: '120px', align: 'right' },
  { field: 'line_total', header: 'Thành tiền', width: '135px', align: 'right' },
  ...(isCreate.value ? [{ field: 'actions', header: '', width: '55px', align: 'center' }] : [])
]);
const invoiceOptions = computed(() => invoices.value.map(invoice => ({ ...invoice, display: `${invoice.invoice_number} - ${invoice.customer_name}` })));
const items = computed(() => form.value.items || []);
const totalAmount = computed(() => items.value.reduce((sum, item) => sum + (Number(item.requested_quantity) || 0) * (Number(item.unit_price) || 0), 0));
const technicalWarehouses = computed(() => warehouses.value.filter(item => item.is_active && !item.can_sell).map(item => ({ ...item, display: `${item.code} - ${item.name}` })));
const salesWarehouses = computed(() => warehouses.value.filter(item => item.is_active && item.can_sell).map(item => ({ ...item, display: `${item.code} - ${item.name}` })));
const auditEntries = computed(() => [
  { label: 'Người tạo', name: form.value.created_by_name, at: form.value.created_at }, { label: 'Bắt đầu kiểm tra', name: form.value.inspection_started_by_name, at: form.value.inspection_started_at },
  { label: 'Đánh giá chất lượng', name: form.value.inspected_by_name, at: form.value.inspected_at }, { label: 'Nhập kho kỹ thuật', name: form.value.technical_received_by_name, at: form.value.technical_received_at },
  { label: 'Yêu cầu kho chính', name: form.value.main_requested_by_name, at: form.value.main_requested_at }, { label: 'Duyệt kho chính', name: form.value.main_approved_by_name, at: form.value.main_approved_at },
  { label: 'Kho chính từ chối', name: form.value.main_rejected_by_name, at: form.value.main_rejected_at }, { label: 'Ghi sổ', name: form.value.posted_by_name, at: form.value.posted_at }
]);
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
const dateTime = value => value ? new Date(value).toLocaleString('vi-VN') : '';
const qualityLabel = value => ({ PENDING: 'Chưa đánh giá', ELIGIBLE: 'Đủ điều kiện', INELIGIBLE: 'Không đủ điều kiện' }[value] || value);
const notifyError = error => toast.add({ severity: 'error', summary: 'Không thể thực hiện', detail: error?.data?.error?.message || error?.message || 'Có lỗi xảy ra.', life: 5000 });
const hydrateItems = () => {
  const quantities = new Map(items.value.map(item => [item.invoice_item_id || item.id, item.requested_quantity]));
  form.value.items = invoices.value.filter(invoice => selectedInvoiceIds.value.includes(invoice.id)).flatMap(invoice => invoice.items.map(item => ({ ...item, invoice_number: invoice.invoice_number, invoice_item_id: item.id, requested_quantity: quantities.get(item.id) || item.available_return_quantity })));
};
const onCustomerChange = async () => { selectedInvoiceIds.value = []; form.value.items = []; await loadEligibleInvoices(customerId.value); };
const onInvoicesChange = () => { const selected = invoices.value.filter(invoice => selectedInvoiceIds.value.includes(invoice.id)); if (selected.length && !customerId.value) { customerId.value = selected[0].customer_id; invoices.value = invoices.value.filter(invoice => invoice.customer_id === customerId.value); selectedInvoiceIds.value = selected.filter(invoice => invoice.customer_id === customerId.value).map(invoice => invoice.id); } hydrateItems(); };
const removeItem = item => { form.value.items = items.value.filter(row => (row.invoice_item_id || row.id) !== (item.invoice_item_id || item.id)); };
const loadEligibleInvoices = async id => { invoices.value = (await salesApi.getEligibleReturnInvoices(id ? { customer_id: id } : {})).data || []; };
const loadRequest = async () => { form.value = (await salesApi.getSaleReturnRequest(route.params.id)).data; form.value.items = (form.value.items || []).map(item => ({ ...item, accepted_quantity: Number(item.accepted_quantity) || (item.quality_status === 'ELIGIBLE' ? item.requested_quantity : 0) })); technicalWarehouseId.value = form.value.technical_warehouse_id; mainWarehouseId.value = form.value.sales_warehouse_id; };
const createRequest = async () => { if (!customerId.value || !selectedInvoiceIds.value.length || !form.value.reason || !items.value.length) return notifyError(new Error('Vui lòng nhập đủ khách hàng, chứng từ, lý do và mặt hàng.')); saving.value = true; try { const response = await salesApi.createSaleReturnRequest({ customer_id: customerId.value, invoice_ids: selectedInvoiceIds.value, reason: form.value.reason, notes: form.value.notes, items: items.value.map(item => ({ invoice_item_id: item.invoice_item_id, requested_quantity: item.requested_quantity })), attachment_ids: (form.value.attachments || []).map(item => item.id).filter(Boolean) }); toast.add({ severity: 'success', summary: 'Thành công', detail: response.message, life: 3000 }); router.replace(`/sale-return-request/${response.data.id}`); } catch (error) { notifyError(error); } finally { saving.value = false; } };
const runAction = async (action, data = {}, method = 'POST') => { saving.value = true; try { const response = await salesApi.saleReturnAction(form.value.id, action, data, method); form.value = response.data; toast.add({ severity: 'success', summary: 'Thành công', detail: response.message, life: 3000 }); } catch (error) { notifyError(error); } finally { saving.value = false; } };
const rejectRequest = () => { const reason = window.prompt('Nhập lý do từ chối yêu cầu:'); if (reason) runAction('reject', { reason }); };
const saveInspection = () => runAction('inspection', { items: items.value.map(item => ({ id: item.id, quality_status: item.quality_status })) }, 'PUT');
const receiveTechnical = () => { const warehouse = technicalWarehouses.value.find(item => item.id === technicalWarehouseId.value); if (!warehouse) return notifyError(new Error('Vui lòng chọn kho kỹ thuật.')); runAction('receive-technical', { warehouse_id: warehouse.id, warehouse_name: warehouse.name }); };
const requestMain = () => { const warehouse = salesWarehouses.value.find(item => item.id === mainWarehouseId.value); if (!warehouse) return notifyError(new Error('Vui lòng chọn kho chính.')); runAction('request-main-warehouse', { warehouse_id: warehouse.id, warehouse_name: warehouse.name }); };
const rejectMain = () => { const reason = window.prompt('Nhập lý do kho chính từ chối:'); if (reason) runAction('reject-main', { reason }); };
const postReturn = async () => {
  if (!window.confirm('Ghi sổ sẽ chuyển tồn kho kỹ thuật sang kho chính và lập phiếu trả hàng. Tiếp tục?')) return;
  saving.value = true;
  try {
    const response = await salesApi.saleReturnAction(form.value.id, 'post');
    toast.add({ severity: 'success', summary: 'Thành công', detail: response.message, life: 3000 });
    router.push(`/sale-return/${response.data.id}`);
  } catch (error) {
    notifyError(error);
  } finally {
    saving.value = false;
  }
};
onMounted(async () => { try { const [customerResponse, warehouseResponse] = await Promise.all([catalogApi.getCustomers({ per_page: 500 }), inventoryApi.getWarehouses()]); const customerRows = customerResponse.data || []; customers.value = customerRows.map(item => ({ ...item, display: `${item.code} - ${item.name}` })); warehouses.value = warehouseResponse.data || []; if (isCreate.value) await loadEligibleInvoices(); else await loadRequest(); } catch (error) { notifyError(error); } });
</script>
