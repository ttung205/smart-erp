<template>
  <div class="space-y-4">
    <PageHeader title="Yêu Cầu Nhập Hàng Trả Lại" subtitle="Theo dõi kiểm tra chất lượng và luồng nhập kho hàng bán trả lại">
      <template #actions>
        <Button v-if="canCreate" label="Tạo yêu cầu" icon="pi pi-plus" size="small" @click="router.push('/sale-return-request/create')" />
      </template>
    </PageHeader>
    <div class="space-y-2">
      <Toolbar class="!border-slate-200 !rounded-[6px]">
        <template #start>
          <IconField><InputIcon class="pi pi-search" /><InputText v-model="search" placeholder="Tìm mã hoặc khách hàng..." size="small" /></IconField>
        </template>
        <template #end><Select v-model="status" :options="statusOptions" option-label="label" option-value="value" placeholder="Tất cả trạng thái" show-clear size="small" /></template>
      </Toolbar>
      <CommonDataTable
        :value="filteredRows"
        :columns="tableColumns"
        :loading="loading"
        :rows="20"
        :rows-per-page-options="[10, 20, 50]"
        row-hover
        empty-icon="pi pi-replay"
        empty-title="Chưa có yêu cầu nhập trả"
        empty-subtitle="Không tìm thấy yêu cầu phù hợp với điều kiện lọc."
        @row-click="openRow"
      >
        <template #col-request_number="{ data }"><span class="font-mono font-semibold text-sky-700">{{ data.request_number }}</span></template>
        <template #col-documents="{ data }">{{ data.documents?.map(d => d.invoice_number).join(', ') }}</template>
        <template #col-total_amount="{ data }"><span class="font-semibold">{{ money(data.total_amount) }}</span></template>
        <template #col-status="{ data }"><Tag :value="statusMeta(data.status).label" :severity="statusMeta(data.status).severity" /></template>
        <template #col-created_at="{ data }">{{ dateTime(data.created_at) }}</template>
        <template #col-actions="{ data }"><Button icon="pi pi-eye" text size="small" @click.stop="router.push(`/sale-return-request/${data.id}`)" /></template>
      </CommonDataTable>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toolbar from 'primevue/toolbar';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import { salesApi } from '../../api/sales.api';
import { storage } from '../../utils/storage';
import { parseJwt } from '../../utils/jwt';
import { hasPermission } from '../../utils/rbac';
import { saleReturnStatusOptions, statusMeta } from './status';

const router = useRouter();
const currentUser = parseJwt(storage.getToken());
const canCreate = hasPermission('sale_return.create', currentUser) || hasPermission('sale_return.manage', currentUser);
const rows = ref([]); const loading = ref(false); const search = ref(''); const status = ref(null);
const statusOptions = saleReturnStatusOptions;
const tableColumns = [
  { field: 'request_number', header: 'Mã yêu cầu', width: '150px' },
  { field: 'customer_name', header: 'Khách hàng', width: '200px' },
  { field: 'documents', header: 'Chứng từ bán', sortable: false, width: '180px' },
  { field: 'reason', header: 'Lý do trả', sortable: false },
  { field: 'total_amount', header: 'Giá trị yêu cầu', width: '150px', align: 'right' },
  { field: 'status', header: 'Trạng thái', width: '150px', align: 'center' },
  { field: 'created_at', header: 'Ngày tạo', width: '160px' },
  { field: 'actions', header: '', sortable: false, width: '60px', align: 'center' }
];
const filteredRows = computed(() => rows.value.filter(row => (!status.value || row.status === status.value) &&
  (!search.value || `${row.request_number} ${row.customer_name}`.toLowerCase().includes(search.value.toLowerCase()))));
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
const dateTime = value => value ? new Date(value).toLocaleString('vi-VN') : '';
const openRow = event => router.push(`/sale-return-request/${event.data.id}`);
onMounted(async () => { loading.value = true; try { rows.value = (await salesApi.getSaleReturnRequests()).data || []; } finally { loading.value = false; } });
</script>
