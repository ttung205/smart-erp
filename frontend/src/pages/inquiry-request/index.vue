<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header -->
    <PageHeader title="Yêu Cầu Hỏi Hàng" subtitle="Yêu cầu hỏi giá từ kinh doanh gửi phòng mua hàng và kết quả phản hồi">
      <template #actions>
        <Button
          v-if="!isPurchase && canCreate"
          label="Tạo yêu cầu"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px] !text-xs !font-semibold !px-3 !py-1.5"
          @click="router.push('/sale/item-inquiries/create')"
        />
      </template>
    </PageHeader>

    <!-- 2. Toolbar lọc & tìm kiếm -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <IconField>
          <InputIcon class="pi pi-search text-xs" />
          <InputText v-model="searchQuery" placeholder="Tìm mã yêu cầu, khách hàng..." size="small" class="w-72 !rounded-[6px] !text-xs !py-1.5" />
        </IconField>
        <Select
          v-model="statusFilter"
          :options="statusOptions"
          optionLabel="label"
          optionValue="value"
          size="small"
          class="w-48 !rounded-[6px] !text-xs"
        />
      </div>
      <div class="flex items-center gap-2">
        <span class="text-xs text-slate-500">Tổng số: <strong class="text-slate-800">{{ filteredRequests.length }}</strong> yêu cầu</span>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" text class="!rounded-[6px] !text-xs !text-slate-600 hover:!bg-slate-100" :loading="loading" @click="loadData" />
      </div>
    </div>

    <!-- 3. Danh sách yêu cầu -->
    <CommonDataTable
      :value="filteredRequests"
      :columns="INQUIRY_REQUEST_COLUMNS"
      :loading="loading"
      :rows="10"
      emptyIcon="pi pi-search"
      emptyTitle="Chưa có yêu cầu hỏi hàng"
      emptySubtitle="Không có yêu cầu nào phù hợp với bộ lọc hiện tại."
    >
      <template #col-code="{ data }">
        <span
          class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
          @click="openRequest(data)"
        >
          {{ data.code }}
        </span>
      </template>
      <template #col-status="{ data }">
        <Tag
          :value="getStatusMeta(INQUIRY_REQUEST_STATUS, data.status).label"
          :severity="getStatusMeta(INQUIRY_REQUEST_STATUS, data.status).severity"
          class="!rounded-[6px]"
        />
      </template>
      <template #col-due_date="{ data }">{{ formatDate(data.due_date) }}</template>
      <template #col-created_at="{ data }">{{ formatDate(data.created_at) }}</template>

      <Column header="Thao tác" :sortable="false" style="width: 110px" class="text-center">
        <template #body="{ data }">
          <Button label="Xem" icon="pi pi-eye" text size="small" class="!rounded-[6px] !text-xs" @click="openRequest(data)" />
        </template>
      </Column>
    </CommonDataTable>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import { itemInquiryApi } from '../../api/item-inquiry.api';
import { storage } from '../../utils/storage';
import { parseJwt } from '../../utils/jwt';
import { hasPermission } from '../../utils/rbac';
import { formatDate } from '../../utils/format';
import { INQUIRY_REQUEST_COLUMNS, INQUIRY_REQUEST_STATUS, getStatusMeta, toStatusOptions } from '../../constants/inquiry.constant';

const route = useRoute();
const router = useRouter();
const toast = useToast();

// Cùng một trang dùng cho cả menu Kinh doanh (/sale) và Mua hàng (/purchase)
const isPurchase = computed(() => route.path.startsWith('/purchase/'));
const basePath = computed(() => (isPurchase.value ? '/purchase' : '/sale'));
const canCreate = computed(() => hasPermission('item-inquiry.create', parseJwt(storage.getToken()) || {}));

const requests = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('');
const statusOptions = toStatusOptions(INQUIRY_REQUEST_STATUS);

const filteredRequests = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase();
  return requests.value.filter(request =>
    (!statusFilter.value || request.status === statusFilter.value) &&
    (!keyword || `${request.code} ${request.customer_name}`.toLowerCase().includes(keyword))
  );
});

const openRequest = request => router.push(`${basePath.value}/item-inquiries/${request.id}`);

const loadData = async () => {
  loading.value = true;
  try {
    requests.value = (await itemInquiryApi.requests()).data || [];
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

onMounted(loadData);
</script>
