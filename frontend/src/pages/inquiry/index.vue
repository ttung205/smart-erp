<template>
  <div class="flex flex-col gap-4">
    <!-- 1. Page Header -->
    <PageHeader title="Hỏi Hàng Nhà Cung Cấp" subtitle="Phiếu hỏi hàng lập từ các yêu cầu của kinh doanh" />

    <!-- 2. Toolbar lọc & tìm kiếm -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-3 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-2">
        <IconField>
          <InputIcon class="pi pi-search text-xs" />
          <InputText v-model="searchQuery" placeholder="Tìm mã phiếu, yêu cầu, nhà cung cấp..." size="small" class="w-80 !rounded-[6px] !text-xs !py-1.5" />
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
        <span class="text-xs text-slate-500">Tổng số: <strong class="text-slate-800">{{ filteredInquiries.length }}</strong> phiếu</span>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" text class="!rounded-[6px] !text-xs !text-slate-600 hover:!bg-slate-100" :loading="loading" @click="loadData" />
      </div>
    </div>

    <!-- 3. Danh sách phiếu hỏi hàng -->
    <CommonDataTable
      :value="filteredInquiries"
      :columns="SUPPLIER_INQUIRY_COLUMNS"
      :loading="loading"
      :rows="10"
      emptyIcon="pi pi-search"
      emptyTitle="Chưa có phiếu hỏi hàng"
      emptySubtitle="Phiếu được lập từ nút “Xử lý” trên yêu cầu hỏi hàng."
    >
      <template #col-code="{ data }">
        <span
          class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[6px] border border-sky-200 cursor-pointer hover:underline inline-block whitespace-nowrap"
          @click="openInquiry(data)"
        >
          {{ data.code }}
        </span>
      </template>
      <template #col-request_code="{ data }">
        <span class="font-mono text-xs font-semibold text-sky-700 cursor-pointer hover:underline" @click="router.push(`/purchase/item-inquiries/${data.request_id}`)">
          {{ data.request_code }}
        </span>
      </template>
      <template #col-status="{ data }">
        <Tag
          :value="getStatusMeta(SUPPLIER_INQUIRY_STATUS, data.status).label"
          :severity="getStatusMeta(SUPPLIER_INQUIRY_STATUS, data.status).severity"
          class="!rounded-[6px]"
        />
      </template>
      <template #col-created_at="{ data }">{{ formatDate(data.created_at) }}</template>

      <Column header="Thao tác" :sortable="false" style="width: 110px" class="text-center">
        <template #body="{ data }">
          <Button label="Mở phiếu" icon="pi pi-eye" text size="small" class="!rounded-[6px] !text-xs" @click="openInquiry(data)" />
        </template>
      </Column>
    </CommonDataTable>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
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
import { formatDate } from '../../utils/format';
import { SUPPLIER_INQUIRY_COLUMNS, SUPPLIER_INQUIRY_STATUS, getStatusMeta, toStatusOptions } from '../../constants/inquiry.constant';

const router = useRouter();
const toast = useToast();

const inquiries = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const statusFilter = ref('');
const statusOptions = toStatusOptions(SUPPLIER_INQUIRY_STATUS);

const filteredInquiries = computed(() => {
  const keyword = searchQuery.value.trim().toLowerCase();
  return inquiries.value.filter(inquiry =>
    (!statusFilter.value || inquiry.status === statusFilter.value) &&
    (!keyword || `${inquiry.code} ${inquiry.request_code} ${inquiry.customer_name} ${inquiry.supplier_name}`.toLowerCase().includes(keyword))
  );
});

const openInquiry = inquiry => router.push(`/purchase/inquiries/${inquiry.id}`);

const loadData = async () => {
  loading.value = true;
  try {
    inquiries.value = (await itemInquiryApi.allInquiries()).data || [];
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

onMounted(loadData);
</script>
