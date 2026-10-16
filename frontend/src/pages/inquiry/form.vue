<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header -->
    <PageHeader :title="form.code ? `Phiếu Hỏi Hàng ${form.code}` : 'Lập Phiếu Hỏi Hàng'" subtitle="Hỏi giá nhà cung cấp cho các item của yêu cầu hỏi hàng">
      <template #title>
        <span>{{ form.code ? `Phiếu Hỏi Hàng ${form.code}` : 'Lập Phiếu Hỏi Hàng' }}</span>
        <Tag
          :value="getStatusMeta(SUPPLIER_INQUIRY_STATUS, form.status).label"
          :severity="getStatusMeta(SUPPLIER_INQUIRY_STATUS, form.status).severity"
          class="!text-[11px] !rounded-[6px]"
        />
      </template>
      <template #actions>
        <Button
          label="Quay lại"
          icon="pi pi-arrow-left"
          size="small"
          text
          severity="secondary"
          class="!rounded-[6px] !text-xs !py-1.5"
          @click="router.push(isCreateMode ? `/purchase/item-inquiries/${requestId}` : '/purchase/inquiries')"
        />
        <Button
          v-if="isEditable"
          label="Lưu nháp"
          icon="pi pi-save"
          size="small"
          outlined
          class="!border-slate-300 !text-slate-700 hover:!bg-slate-100 !rounded-[6px] !text-xs !py-1.5"
          :loading="busy"
          @click="saveInquiry"
        />
        <Button
          v-if="canSendEmail"
          :label="emailStatus === 'FAILED' ? 'Gửi lại email' : 'Gửi email'"
          icon="pi pi-send"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
          :loading="busy"
          @click="sendEmail"
        />
        <Button
          v-if="form.status === 'SENT'"
          label="Set hoàn thành"
          icon="pi pi-check"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
          :loading="busy"
          @click="completeInquiry"
        />
        <Button
          v-if="needsProductSync"
          label="Cập nhật Product"
          icon="pi pi-refresh"
          size="small"
          outlined
          class="!rounded-[6px] !text-xs !py-1.5"
          :loading="busy"
          @click="syncProduct"
        />
        <Button
          v-if="form.status === 'COMPLETED'"
          label="Xem kết quả trên yêu cầu"
          icon="pi pi-arrow-right"
          iconPos="right"
          size="small"
          outlined
          class="!rounded-[6px] !text-xs !py-1.5"
          @click="router.push(`/purchase/item-inquiries/${requestId}`)"
        />
      </template>
    </PageHeader>

    <Message v-if="isEmailPending" severity="info" class="!rounded-[6px]">
      Email đang được gửi. Phiếu sẽ tự chuyển sang “Đã gửi” khi gửi thành công.
    </Message>

    <div v-if="loading" class="bg-white border border-slate-200 rounded-[6px] p-6 text-xs text-slate-500">Đang tải...</div>
    <template v-else>
      <!-- 2. Thông tin phiếu -->
      <InquiryInfoForm
        v-model:supplierId="form.supplier_id"
        :suppliers="suppliers"
        :request="request"
        :disabled="!!form.id"
        @open-request="router.push(`/purchase/item-inquiries/${requestId}`)"
      />

      <!-- 3. Item hỏi hàng -->
      <div class="flex flex-col gap-1">
        <span class="px-1 text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
          <i class="pi pi-box text-sky-600 text-xs"></i>
          Item hỏi hàng ({{ form.items.length }})
        </span>
        <InquiryItemTable v-model="form.items" :brands="brands" :readonly="!isEditable" />
      </div>

      <!-- 4. Tài liệu gửi kèm -->
      <InquiryAttachmentPicker v-model="form.attachment_ids" :attachments="request?.attachments || []" :disabled="!isEditable" />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Message from 'primevue/message';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import InquiryInfoForm from '../../components/inquiry/InquiryInfoForm.vue';
import InquiryItemTable from '../../components/inquiry/InquiryItemTable.vue';
import InquiryAttachmentPicker from '../../components/inquiry/InquiryAttachmentPicker.vue';
import { itemInquiryApi } from '../../api/item-inquiry.api';
import { catalogApi } from '../../api/catalog.api';
import { SUPPLIER_INQUIRY_STATUS, SUPPLIER_ITEM_FIELDS, getStatusMeta } from '../../constants/inquiry.constant';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const isCreateMode = computed(() => route.params.inquiryId === 'create' || !route.params.inquiryId);
const requestId = ref(Number(route.query.requestId || 0));
const request = ref(null);
const suppliers = ref([]);
const brands = ref([]);
const loading = ref(false);
const busy = ref(false);
const emailStatus = ref('');
let emailTimer;

const createEmptyForm = () => ({ supplier_id: null, status: 'DRAFT', attachment_ids: [], items: [] });
const form = ref(createEmptyForm());

// Đã đẩy email vào hàng đợi thì khóa chỉnh sửa
const isEditable = computed(() => form.value.status === 'DRAFT' && !form.value.email_job_id);
const isEmailPending = computed(() => !!emailStatus.value && !['SENT', 'FAILED'].includes(emailStatus.value));
const canSendEmail = computed(() => form.value.id && form.value.status === 'DRAFT' && (!form.value.email_job_id || emailStatus.value === 'FAILED'));

// Phiếu đã hoàn thành nhưng có item chưa ghi được giá sang yêu cầu (lỗi Catalog lần trước)
const needsProductSync = computed(() => form.value.status === 'COMPLETED' && form.value.items.some(item => {
  const requested = request.value?.items.find(x => x.id === item.request_item_id);
  return requested && requested.gross_price == null && !requested.selected_inquiry_item_id;
}));

const notifyError = e => toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
const notifySuccess = detail => toast.add({ severity: 'success', summary: 'Thành công', detail, life: 3000 });

// Item phiếu mới được sao từ item của yêu cầu, Brand text được khớp sang danh mục Brand
const buildItemsFromRequest = () => request.value.items.map(item => ({
  request_item_id: item.id,
  ...Object.fromEntries(SUPPLIER_ITEM_FIELDS.map(field => [field.key, item[field.key] ?? ''])),
  brand_id: brands.value.find(b => b.name?.toLowerCase() === item.brand?.toLowerCase())?.id || null,
  buying_price: 0,
  vat: 0,
  tax: 0
}));

const loadInquiry = async () => {
  loading.value = true;
  clearTimeout(emailTimer);
  try {
    if (!isCreateMode.value) {
      form.value = (await itemInquiryApi.inquiry(route.params.inquiryId)).data;
      requestId.value = form.value.request_id;
    }
    if (!requestId.value) throw new Error('Thiếu yêu cầu hỏi hàng nguồn.');
    request.value = (await itemInquiryApi.request(requestId.value)).data;
    if (isCreateMode.value) form.value = { ...createEmptyForm(), items: buildItemsFromRequest() };
    emailStatus.value = form.value.status === 'DRAFT' && form.value.email_job_id ? 'QUEUED' : '';
    if (emailStatus.value) emailTimer = setTimeout(checkEmailStatus, 0);
  } catch (e) {
    notifyError(e);
  } finally {
    loading.value = false;
  }
};

const saveInquiry = async () => {
  if (!form.value.supplier_id || form.value.items.some(item => !item.brand_id)) {
    toast.add({ severity: 'warn', summary: 'Thiếu thông tin', detail: 'Chọn nhà cung cấp và Brand cho từng item.', life: 4000 });
    return;
  }
  busy.value = true;
  try {
    const payload = {
      ...form.value,
      items: form.value.items.map(item => ({
        ...item,
        quantity: Number(item.quantity),
        buying_price: Number(item.buying_price || 0),
        vat: Number(item.vat || 0),
        tax: Number(item.tax || 0)
      }))
    };
    const saved = form.value.id ? await itemInquiryApi.updateInquiry(form.value.id, payload) : await itemInquiryApi.createInquiry(requestId.value, payload);
    notifySuccess('Đã lưu phiếu hỏi hàng nháp.');
    if (!form.value.id) await router.replace(`/purchase/inquiries/${saved.data.id}`);
    else await loadInquiry();
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = false;
  }
};

const sendEmail = async () => {
  busy.value = true;
  try {
    await itemInquiryApi.send(form.value.id);
    await loadInquiry();
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = false;
  }
};

// Hoàn thành xong quay về view yêu cầu để xem kết quả trên bảng item
const completeInquiry = async () => {
  busy.value = true;
  try {
    await itemInquiryApi.complete(form.value.id);
    await router.push({ path: `/purchase/item-inquiries/${requestId.value}`, query: { completed: form.value.code } });
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = false;
  }
};

const syncProduct = async () => {
  busy.value = true;
  try {
    await itemInquiryApi.complete(form.value.id);
    notifySuccess('Đã cập nhật Product.');
    await loadInquiry();
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = false;
  }
};

const checkEmailStatus = async () => {
  if (form.value.status !== 'DRAFT' || !form.value.email_job_id) return;
  try {
    const result = (await itemInquiryApi.syncEmail(form.value.id)).data;
    const inquiry = result.inquiry || result;
    emailStatus.value = result.email_status || inquiry.status;
    form.value = inquiry;
    if (emailStatus.value === 'SENT') notifySuccess('Email đã gửi thành công. Phiếu đã chuyển sang Đã gửi.');
    else if (emailStatus.value === 'FAILED') notifyError(new Error(result.email_error || 'Gửi email thất bại. Có thể gửi lại.'));
    else emailTimer = setTimeout(checkEmailStatus, 3000);
  } catch (e) {
    notifyError(e);
    emailTimer = setTimeout(checkEmailStatus, 5000);
  }
};

onMounted(async () => {
  await Promise.all([
    catalogApi.getSuppliers().then(response => { suppliers.value = response.data || []; }).catch(notifyError),
    catalogApi.getBrands().then(response => { brands.value = response.data || []; }).catch(notifyError)
  ]);
  await loadInquiry();
});

watch(() => route.params.inquiryId, id => id && loadInquiry());
onUnmounted(() => clearTimeout(emailTimer));
</script>
