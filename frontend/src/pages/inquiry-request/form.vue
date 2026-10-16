<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header -->
    <PageHeader
      :title="isEditMode ? `Sửa Yêu Cầu Hỏi Hàng ${code}` : 'Lập Yêu Cầu Hỏi Hàng'"
      subtitle="Nhập thông tin chung và các item cần phòng mua hàng hỏi nhà cung cấp"
    >
      <template #actions>
        <Button
          label="Quay lại"
          icon="pi pi-arrow-left"
          size="small"
          text
          severity="secondary"
          class="!rounded-[6px] !text-xs !py-1.5"
          @click="router.push(isEditMode ? `/sale/item-inquiries/${requestId}` : '/sale/item-inquiries')"
        />
        <Button
          label="Lưu nháp"
          icon="pi pi-save"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
          :loading="saving"
          :disabled="loading"
          @click="saveRequest"
        />
      </template>
    </PageHeader>

    <div v-if="loading" class="bg-white border border-slate-200 rounded-[6px] p-6 text-xs text-slate-500">Đang tải...</div>
    <template v-else>
      <!-- 2. Thông tin chung -->
      <InquiryRequestInfoForm v-model="form" :customers="customers" :users="users" />

      <!-- 3. Item yêu cầu -->
      <InquiryRequestItemTable v-model="form.items" />

      <!-- 4. Tài liệu đính kèm (yêu cầu mới: lưu tạm, tải lên sau khi lưu nháp) -->
      <FormAttachmentUpload
        v-model="attachments"
        title="Tài Liệu Đính Kèm Yêu Cầu Hỏi Hàng"
        subtitle="Bản vẽ, thông số kỹ thuật hoặc yêu cầu của khách hàng gửi kèm cho phòng mua hàng"
        accept=".pdf,.png,.jpg,.jpeg,.docx,.xlsx"
        :max-file-size="10485760"
        :upload-handler="uploadAttachments"
        :remove-handler="removeAttachment"
        :download-handler="downloadAttachment"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import PageHeader from '../../components/common/PageHeader.vue';
import FormAttachmentUpload from '../../components/common/FormAttachmentUpload.vue';
import InquiryRequestInfoForm from '../../components/inquiry-request/InquiryRequestInfoForm.vue';
import InquiryRequestItemTable from '../../components/inquiry-request/InquiryRequestItemTable.vue';
import { itemInquiryApi } from '../../api/item-inquiry.api';
import { catalogApi } from '../../api/catalog.api';
import { identityApi } from '../../api/identity.api';
import { INQUIRY_ITEM_FIELDS, createBlankRequestItem } from '../../constants/inquiry.constant';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const requestId = computed(() => route.params.id);
const isEditMode = computed(() => !!requestId.value);
const code = ref('');
const loading = ref(false);
const saving = ref(false);
const customers = ref([]);
const users = ref([]);

const form = ref({ customer_id: null, assignee_id: null, watcher_id: null, due_date: '', description: '', items: [createBlankRequestItem()] });
const attachments = ref([]);

// Yêu cầu đã có id thì tải lên ngay; yêu cầu mới giữ File lại để tải lên sau khi lưu
const uploadAttachments = async files => {
  if (!isEditMode.value) return files.map(file => ({ id: null, file_name: file.name, size: file.size, uploaded_at: 'Chờ lưu nháp', _file: file }));
  const uploaded = [];
  for (const file of files) uploaded.push((await itemInquiryApi.upload(requestId.value, file)).data);
  return uploaded;
};

const removeAttachment = async attachment => {
  if (attachment.id) await itemInquiryApi.deleteAttachment(requestId.value, attachment.id);
};

const downloadAttachment = async attachment => {
  if (attachment.id) return itemInquiryApi.download(requestId.value, attachment);
  const url = URL.createObjectURL(attachment._file);
  const link = document.createElement('a');
  link.href = url;
  link.download = attachment.file_name;
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
};

const validate = () => {
  if (!form.value.customer_id || !form.value.assignee_id) return 'Chọn khách hàng và nhân viên phụ trách.';
  if (!form.value.items.length || form.value.items.some(item => !item.designation?.trim() || Number(item.quantity) <= 0)) return 'Nhập designation và số lượng cho từng item.';
  return '';
};

const saveRequest = async () => {
  const error = validate();
  if (error) {
    toast.add({ severity: 'warn', summary: 'Thiếu thông tin', detail: error, life: 4000 });
    return;
  }
  saving.value = true;
  try {
    const payload = {
      ...form.value,
      due_date: form.value.due_date || null,
      items: form.value.items.map(item => ({ ...Object.fromEntries(INQUIRY_ITEM_FIELDS.map(field => [field.key, item[field.key]])), quantity: Number(item.quantity) }))
    };
    const response = isEditMode.value ? await itemInquiryApi.update(requestId.value, payload) : await itemInquiryApi.create(payload);
    toast.add({ severity: 'success', summary: 'Đã lưu', detail: 'Đã lưu yêu cầu hỏi hàng nháp.', life: 3000 });
    // Yêu cầu đã tạo xong: lỗi tải tệp chỉ cảnh báo để không tạo trùng yêu cầu khi lưu lại
    for (const attachment of attachments.value.filter(item => item._file)) {
      try {
        await itemInquiryApi.upload(response.data.id, attachment._file);
      } catch (e) {
        toast.add({ severity: 'warn', summary: `Chưa tải được ${attachment.file_name}`, detail: e.message, life: 5000 });
      }
    }
    await router.push(`/sale/item-inquiries/${response.data.id}`);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  } finally {
    saving.value = false;
  }
};

const loadRequest = async () => {
  const detail = (await itemInquiryApi.request(requestId.value)).data;
  // Chỉ yêu cầu nháp mới được sửa
  if (detail.status !== 'DRAFT') {
    await router.replace(`/sale/item-inquiries/${requestId.value}`);
    return;
  }
  code.value = detail.code;
  form.value = {
    customer_id: detail.customer_id,
    assignee_id: detail.assignee_id,
    watcher_id: detail.watcher_id,
    due_date: detail.due_date?.slice(0, 10) || '',
    description: detail.description || '',
    items: detail.items.map(item => ({ ...item }))
  };
  attachments.value = detail.attachments || [];
};

onMounted(async () => {
  loading.value = true;
  try {
    const [customerResponse, userResponse] = await Promise.all([catalogApi.getCustomers(), identityApi.getUsers()]);
    customers.value = customerResponse.data || [];
    users.value = userResponse.data || [];
    if (isEditMode.value) await loadRequest();
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  } finally {
    loading.value = false;
  }
});
</script>
