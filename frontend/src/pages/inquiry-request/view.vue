<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header -->
    <PageHeader :title="`Yêu Cầu Hỏi Hàng ${request?.code || ''}`" :subtitle="request?.customer_name || 'Thông tin yêu cầu và kết quả hỏi hàng'">
      <template #title>
        <span>Yêu Cầu Hỏi Hàng {{ request?.code || '' }}</span>
        <Tag
          v-if="request"
          :value="getStatusMeta(INQUIRY_REQUEST_STATUS, request.status).label"
          :severity="getStatusMeta(INQUIRY_REQUEST_STATUS, request.status).severity"
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
          @click="router.push(`${basePath}/item-inquiries`)"
        />

        <!-- Kinh doanh: sửa / gửi nháp, đóng khi đã có phản hồi -->
        <template v-if="!isPurchase && canCreate && request?.status === 'DRAFT'">
          <Button
            label="Sửa nháp"
            icon="pi pi-pencil"
            size="small"
            outlined
            class="!border-slate-300 !text-slate-700 hover:!bg-slate-100 !rounded-[6px] !text-xs !py-1.5"
            @click="router.push(`/sale/item-inquiries/${request.id}/edit`)"
          />
          <Button
            label="Gửi yêu cầu"
            icon="pi pi-send"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
            :loading="busy"
            @click="runAction(() => itemInquiryApi.submit(request.id), 'Đã gửi yêu cầu cho phòng mua hàng.')"
          />
        </template>
        <Button
          v-if="!isPurchase && request?.status === 'RESPONDED'"
          label="Đóng yêu cầu"
          icon="pi pi-check"
          size="small"
          outlined
          class="!border-slate-300 !text-slate-700 hover:!bg-slate-100 !rounded-[6px] !text-xs !py-1.5"
          :loading="busy"
          @click="runAction(() => itemInquiryApi.close(request.id), 'Đã đóng yêu cầu.')"
        />

        <!-- Mua hàng: xử lý -> sang form phiếu hỏi hàng nhà cung cấp -->
        <Button
          v-if="isPurchase && PROCESSABLE_STATUSES.includes(request?.status)"
          :label="request.inquiries?.length ? 'Lập thêm phiếu hỏi hàng' : 'Xử lý'"
          icon="pi pi-arrow-right"
          iconPos="right"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !py-1.5"
          @click="router.push(`/purchase/inquiries/create?requestId=${request.id}`)"
        />
      </template>
    </PageHeader>

    <div v-if="loading && !request" class="bg-white border border-slate-200 rounded-[6px] p-6 text-xs text-slate-500">Đang tải...</div>
    <template v-else-if="request">
      <!-- 2. Thông tin chung -->
      <InquiryRequestInfoPanel :request="request" :users="users" :linkable="isPurchase" @open-inquiry="openInquiry" />

      <!-- 3. Item yêu cầu + kết quả hỏi hàng -->
      <div class="flex flex-col gap-1">
        <span class="px-1 text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
          <i class="pi pi-box text-sky-600 text-xs"></i>
          Item yêu cầu ({{ request.items.length }})
        </span>
        <InquiryRequestItemTable
          :modelValue="request.items"
          readonly
          :results="request.results || []"
          :selectable="isPurchase"
          @select-result="selectResult"
          @open-inquiry="openInquiry"
        />
      </div>

      <!-- 4. Tài liệu đính kèm -->
      <InquiryRequestAttachments
        :attachments="request.attachments || []"
        :uploadable="!isPurchase && canCreate && request.status === 'DRAFT'"
        :uploading="busy"
        @upload="uploadAttachment"
        @download="downloadAttachment"
      />
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import InquiryRequestInfoPanel from '../../components/inquiry-request/InquiryRequestInfoPanel.vue';
import InquiryRequestItemTable from '../../components/inquiry-request/InquiryRequestItemTable.vue';
import InquiryRequestAttachments from '../../components/inquiry-request/InquiryRequestAttachments.vue';
import { itemInquiryApi } from '../../api/item-inquiry.api';
import { identityApi } from '../../api/identity.api';
import { storage } from '../../utils/storage';
import { parseJwt } from '../../utils/jwt';
import { hasPermission } from '../../utils/rbac';
import { INQUIRY_REQUEST_STATUS, getStatusMeta } from '../../constants/inquiry.constant';

const PROCESSABLE_STATUSES = ['WAITING', 'IN_PROGRESS', 'RESPONDED'];

const route = useRoute();
const router = useRouter();
const toast = useToast();

const isPurchase = computed(() => route.path.startsWith('/purchase/'));
const basePath = computed(() => (isPurchase.value ? '/purchase' : '/sale'));
const canCreate = computed(() => hasPermission('item-inquiry.create', parseJwt(storage.getToken()) || {}));

const request = ref(null);
const users = ref([]);
const loading = ref(false);
const busy = ref(false);

const notifyError = e => toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });

const loadRequest = async () => {
  loading.value = true;
  try {
    request.value = (await itemInquiryApi.request(route.params.id)).data;
  } catch (e) {
    notifyError(e);
  } finally {
    loading.value = false;
  }
};

const runAction = async (action, message) => {
  busy.value = true;
  try {
    await action();
    toast.add({ severity: 'success', summary: 'Thành công', detail: message, life: 3000 });
    await loadRequest();
  } catch (e) {
    notifyError(e);
  } finally {
    busy.value = false;
  }
};

const selectResult = ({ itemId, inquiryItemId }) =>
  runAction(() => itemInquiryApi.select(request.value.id, itemId, inquiryItemId), 'Đã chốt báo giá và cập nhật Product.');

const uploadAttachment = file => runAction(() => itemInquiryApi.upload(request.value.id, file), 'Đã tải tài liệu.');

const downloadAttachment = async attachment => {
  try {
    await itemInquiryApi.download(request.value.id, attachment);
  } catch (e) {
    notifyError(e);
  }
};

const openInquiry = id => router.push(`/purchase/inquiries/${id}`);

onMounted(async () => {
  // Quay về từ phiếu hỏi hàng NCC vừa hoàn thành
  if (route.query.completed) {
    toast.add({ severity: 'success', summary: 'Hoàn thành hỏi hàng', detail: `Phiếu ${route.query.completed} đã hoàn thành, kết quả đã cập nhật vào bảng item.`, life: 5000 });
    router.replace({ query: {} });
  }
  try {
    users.value = (await identityApi.getUsers()).data || [];
  } catch {
    // Tên nhân viên sẽ hiển thị bằng ID
  }
  await loadRequest();
});

watch(() => route.params.id, id => id && loadRequest());
</script>
