<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="request ? `${title} #${request.code}` : `Lập ${title.toLowerCase()}`">
      <template #actions>
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined @click="router.push(base)" />
        <Button v-if="editable" label="Lưu nháp" icon="pi pi-save" size="small" severity="secondary" outlined :loading="saving" @click="save(false)" />
        <Button v-if="editable" label="Gửi duyệt" icon="pi pi-send" size="small" :loading="saving" @click="save(true)" />
        <Button v-if="isOwner && request?.status === 'PENDING'" label="Hủy đơn" icon="pi pi-times-circle" size="small" severity="danger" outlined @click="cancel" />
        <template v-if="myPendingStep">
          <Button label="Từ chối" size="small" severity="danger" outlined @click="openDecision('REJECTED')" />
          <Button label="Trả lại" size="small" severity="secondary" outlined @click="openDecision('RETURNED')" />
          <Button label="Duyệt" size="small" severity="success" @click="openDecision('APPROVED')" />
        </template>
      </template>
    </PageHeader>

    <div v-if="request" class="bg-white border border-slate-200 rounded-[6px] p-3 flex flex-wrap items-center gap-4 text-xs">
      <Tag :value="statuses[request.status]?.label" :severity="statuses[request.status]?.severity" />
      <span>Người đề nghị: <strong>{{ request.user_name }}</strong></span>
      <span v-if="request.submitted_at">Gửi lúc: {{ formatDateTime(request.submitted_at) }}</span>
      <span v-if="pendingStep">Chờ: <strong>{{ pendingStep.approver_name }}</strong></span>
    </div>

    <section class="bg-white border border-slate-200 rounded-[6px]">
      <div class="px-4 py-3 border-b border-slate-200 font-semibold text-sm text-slate-900">Thông tin đề nghị</div>
      <div class="p-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-xs">
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Nhóm đề nghị</label>
          <InputText :model-value="title" disabled fluid />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Loại chi tiết <span class="text-rose-500">*</span></label>
          <Select v-model="form.category" :options="categories" option-label="label" option-value="value" placeholder="Chọn loại chi tiết" fluid :disabled="!editable" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Đơn vị cung cấp</label>
          <InputText v-model="form.supplier_name" fluid :disabled="!editable" placeholder="Nếu đã xác định" />
        </div>
        <div class="flex flex-col gap-1.5 lg:col-span-2">
          <label class="font-semibold text-slate-700">Mục đích / nội dung <span class="text-rose-500">*</span></label>
          <Textarea v-model="form.purpose" rows="3" auto-resize fluid :disabled="!editable" :placeholder="kind === 'purchase' ? 'Mua để phục vụ công việc nào?' : 'Thanh toán cho công việc, đối tượng nào?'" />
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Ghi chú</label>
          <Textarea v-model="form.notes" rows="3" auto-resize fluid :disabled="!editable" />
        </div>
      </div>
    </section>

    <section class="space-y-2">
      <div class="flex items-center justify-between">
        <h2 class="m-0 text-sm font-semibold text-slate-900">{{ kind === 'purchase' ? 'Danh mục mua sắm' : 'Danh mục khoản chi' }}</h2>
        <span class="text-xs text-slate-500">Số tiền do hệ thống tính từ số lượng × đơn giá</span>
      </div>
      <ItemDataTable v-model="form.items" :columns="itemColumns" :readonly="!editable" :create-empty-row="newItem"
        :show-catalog-search="false" :show-column-setting="false" :show-export="false" add-row-label="Thêm dòng">
        <template #summary>
          <span class="text-xs font-semibold text-slate-800">{{ form.items.length }} khoản · Tổng tiền dự kiến: <strong class="font-mono text-sky-700">{{ formatCurrency(totalAmount) }}</strong></span>
        </template>
        <template #col-item_name="{ data, notifyChange }">
          <InputText v-model="data.item_name" fluid size="small" :disabled="!editable" @change="notifyChange" />
        </template>
        <template #col-unit="{ data, notifyChange }">
          <InputText v-model="data.unit" fluid size="small" :disabled="!editable" @change="notifyChange" />
        </template>
        <template #col-quantity="{ data, notifyChange }">
          <InputNumber v-model="data.quantity" :min="0.01" :max-fraction-digits="2" fluid size="small" :disabled="!editable" @update:model-value="notifyChange" />
        </template>
        <template #col-unit_price="{ data, notifyChange }">
          <InputNumber v-model="data.unit_price" mode="currency" currency="VND" locale="vi-VN" :min="0" fluid size="small" :disabled="!editable" @update:model-value="notifyChange" />
        </template>
        <template #col-subtotal="{ data }">
          <span class="block text-right font-mono text-xs font-semibold">{{ formatCurrency(Number(data.quantity || 0) * Number(data.unit_price || 0)) }}</span>
        </template>
        <template #col-notes="{ data, notifyChange }">
          <InputText v-model="data.notes" fluid size="small" :disabled="!editable" @change="notifyChange" />
        </template>
      </ItemDataTable>
    </section>

    <FormAttachmentUpload v-model="attachments" :record-id="request?.id" :readonly="!editable"
      :title="kind === 'payment' ? 'Chứng từ thanh toán' : 'Tệp đính kèm'"
      :subtitle="kind === 'payment' ? 'Cần ít nhất một hóa đơn hoặc chứng từ trước khi gửi duyệt' : 'Báo giá và tài liệu liên quan'"
      accept=".pdf,.png,.jpg,.jpeg,.doc,.docx,.xls,.xlsx" :max-file-size="10485760"
      file-types-label="PDF, Word, Excel, Ảnh"
      :upload-handler="uploadFiles" :remove-handler="removeFile" :download-handler="downloadFile" />

    <section v-if="request" class="bg-white border border-slate-200 rounded-[6px]">
      <div class="px-4 py-3 border-b border-slate-200 font-semibold text-sm text-slate-900">Lịch sử xử lý</div>
      <div class="p-4 space-y-2 text-xs">
        <div v-for="history in request.approval_histories" :key="history.id" class="flex flex-wrap gap-2 text-slate-700">
          <span class="font-semibold">{{ history.actor_name }}</span>
          <span>{{ actionLabels[history.action] || history.action }}</span>
          <span v-if="history.comment">· {{ history.comment }}</span>
          <span class="text-slate-400">{{ formatDateTime(history.created_at) }}</span>
        </div>
      </div>
    </section>

    <Dialog v-model:visible="decision.visible" modal :header="`${decisionLabels[decision.type]} ${request?.code || ''}`" :style="{ width: '440px' }">
      <div class="flex flex-col gap-2 text-xs">
        <label class="font-semibold text-slate-700">Ý kiến {{ decision.type === 'APPROVED' ? '(không bắt buộc)' : '*' }}</label>
        <Textarea v-model="decision.comment" rows="3" auto-resize />
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="decision.visible = false" />
        <Button :label="decisionLabels[decision.type]" size="small" :loading="saving" @click="confirmDecision" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import PageHeader from '../../../components/common/PageHeader.vue';
import ItemDataTable from '../../../components/common/ItemDataTable.vue';
import FormAttachmentUpload from '../../../components/common/FormAttachmentUpload.vue';
import { eofficeApi } from '../../../api/eoffice.api';
import { formatCurrency, formatDateTime } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { storage } from '../../../utils/storage';

const props = defineProps({ kind: { type: String, required: true } });
const route = useRoute();
const router = useRouter();
const toast = useToast();
const request = ref(null);
const attachments = ref([]);
const saving = ref(false);
const currentUserId = Number(parseJwt(storage.getToken())?.sub);
const newItem = () => ({ item_name: '', unit: props.kind === 'purchase' ? 'Cái' : 'Lần', quantity: 1, unit_price: 0, notes: '' });
const form = reactive({ category: null, supplier_name: '', purpose: '', notes: '', items: [newItem()] });
const base = computed(() => `/eoffice/${props.kind}-requests`);
const title = computed(() => props.kind === 'purchase' ? 'Đề Nghị Mua Sắm' : 'Đề Nghị Thanh Toán');
const categories = computed(() => props.kind === 'purchase'
  ? [{ label: 'Đồ dùng văn phòng', value: 'OFFICE_SUPPLIES' }, { label: 'Thiết bị', value: 'EQUIPMENT' }, { label: 'Khác', value: 'OTHER' }]
  : [{ label: 'Tiếp khách', value: 'HOSPITALITY' }, { label: 'Công tác', value: 'TRAVEL' }, { label: 'Khác', value: 'OTHER' }]);
const isOwner = computed(() => !request.value || Number(request.value.user_id) === currentUserId);
const editable = computed(() => isOwner.value && (!request.value || ['DRAFT', 'RETURNED'].includes(request.value.status)));
const pendingStep = computed(() => request.value?.approval_steps?.find(step => step.status === 'PENDING'));
const myPendingStep = computed(() => pendingStep.value && Number(pendingStep.value.approver_id) === currentUserId);
const totalAmount = computed(() => form.items.reduce((sum, row) => sum + Number(row.quantity || 0) * Number(row.unit_price || 0), 0));
const itemColumns = [
  { field: 'item_name', header: 'Tên hàng hóa / khoản chi *', width: '300px' },
  { field: 'unit', header: 'Đơn vị *', width: '90px' },
  { field: 'quantity', header: 'Số lượng *', width: '110px' },
  { field: 'unit_price', header: 'Đơn giá dự kiến *', width: '160px' },
  { field: 'subtotal', header: 'Thành tiền', width: '160px' },
  { field: 'notes', header: 'Ghi chú', width: '180px' }
];
const statuses = {
  DRAFT: { label: 'Nháp', severity: 'secondary' }, PENDING: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'success' }, REJECTED: { label: 'Từ chối', severity: 'danger' },
  RETURNED: { label: 'Trả lại', severity: 'info' }, CANCELLED: { label: 'Đã hủy', severity: 'secondary' }
};
const actionLabels = { CREATED: 'tạo đơn', SUBMITTED: 'gửi duyệt', APPROVED: 'duyệt', REJECTED: 'từ chối', RETURNED: 'trả lại', CANCELLED: 'hủy', AUTO_APPROVED: 'tự duyệt' };
const decisionLabels = { APPROVED: 'Duyệt', RETURNED: 'Trả lại', REJECTED: 'Từ chối' };
const decision = reactive({ visible: false, type: 'APPROVED', comment: '' });
const notifyError = error => toast.add({ severity: 'error', summary: 'Không thực hiện được', detail: error.message, life: 5000 });
const load = async () => {
  if (!route.params.id) return;
  try {
    request.value = (await eofficeApi.getOfficeRequest(props.kind, route.params.id)).data;
    attachments.value = request.value.attachments || [];
    Object.assign(form, {
      category: request.value.category,
      supplier_name: request.value.supplier_name || '',
      purpose: request.value.purpose,
      notes: request.value.notes || '',
      items: request.value.items.map(item => ({ ...item, item_name: item.name, quantity: Number(item.quantity), unit_price: Number(item.unit_price) }))
    });
  } catch (error) {
    notifyError(error);
  }
};
const payload = () => ({
  category: form.category, supplier_name: form.supplier_name, purpose: form.purpose, notes: form.notes,
  items: form.items.map(item => ({ name: item.item_name, unit: item.unit, quantity: Number(item.quantity), unit_price: Number(item.unit_price), notes: item.notes }))
});
const ensureSaved = async () => {
  if (!form.category || !form.purpose.trim() || form.items.some(item => !item.item_name?.trim() || !item.unit?.trim() || Number(item.quantity) <= 0)) {
    throw new Error('Vui lòng nhập loại chi tiết, mục đích và các dòng khoản chi hợp lệ.');
  }
  if (request.value) {
    request.value = (await eofficeApi.updateOfficeRequest(props.kind, request.value.id, payload())).data;
  } else {
    request.value = (await eofficeApi.createOfficeRequest(props.kind, payload())).data;
    await router.replace(`${base.value}/${request.value.id}`);
  }
  return request.value;
};
const save = async submit => {
  saving.value = true;
  try {
    const saved = await ensureSaved();
    if (submit) {
      request.value = (await eofficeApi.submitOfficeRequest(props.kind, saved.id)).data;
      toast.add({ severity: 'success', summary: 'Đã gửi duyệt', detail: saved.code, life: 3000 });
    } else {
      toast.add({ severity: 'success', summary: 'Đã lưu nháp', detail: saved.code, life: 3000 });
    }
  } catch (error) {
    notifyError(error);
  } finally {
    saving.value = false;
  }
};
const uploadFiles = async files => {
  const saved = await ensureSaved();
  const uploaded = [];
  for (const file of files) uploaded.push((await eofficeApi.uploadOfficeAttachment(props.kind, saved.id, file)).data);
  return uploaded;
};
const removeFile = async file => {
  await eofficeApi.removeOfficeAttachment(props.kind, request.value.id, file.id);
};
const downloadFile = async file => {
  const response = await fetch(`/api/v1/eoffice/${props.kind}-requests/${request.value.id}/attachments/${file.id}`, {
    headers: { Authorization: `Bearer ${storage.getToken()}` }
  });
  if (!response.ok) throw new Error('Không tải được tệp.');
  const url = URL.createObjectURL(await response.blob());
  const link = document.createElement('a');
  link.href = url;
  link.download = file.file_name;
  link.click();
  URL.revokeObjectURL(url);
};
const cancel = async () => {
  if (!window.confirm('Hủy đề nghị đang chờ duyệt?')) return;
  try {
    request.value = (await eofficeApi.cancelOfficeRequest(props.kind, request.value.id, '')).data;
  } catch (error) {
    notifyError(error);
  }
};
const openDecision = type => Object.assign(decision, { visible: true, type, comment: '' });
const confirmDecision = async () => {
  if (decision.type !== 'APPROVED' && !decision.comment.trim()) {
    toast.add({ severity: 'warn', summary: 'Cần nhập lý do', life: 3000 });
    return;
  }
  saving.value = true;
  try {
    await eofficeApi.decideApproval(myPendingStep.value.id, decision.type, decision.comment);
    decision.visible = false;
    await load();
  } catch (error) {
    notifyError(error);
  } finally {
    saving.value = false;
  }
};
onMounted(load);
</script>
