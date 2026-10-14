<template>
  <div class="space-y-4">
    <PageHeader title="Yêu Cầu Nhập Hàng Trả Lại" subtitle="Theo dõi kiểm tra chất lượng và luồng nhập kho hàng bán trả lại">
      <template #actions>
        <Button v-if="canCreate" label="Tạo yêu cầu" icon="pi pi-plus" size="small" @click="router.push('/sale-return-request/create')" />
      </template>
    </PageHeader>
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm">
      <Toolbar class="!border-0 !border-b !border-slate-200 !rounded-b-none">
        <template #start>
          <IconField><InputIcon class="pi pi-search" /><InputText v-model="search" placeholder="Tìm mã hoặc khách hàng..." size="small" /></IconField>
        </template>
        <template #end><Select v-model="status" :options="statusOptions" option-label="label" option-value="value" placeholder="Tất cả trạng thái" show-clear size="small" /></template>
      </Toolbar>
      <DataTable :value="filteredRows" :loading="loading" paginator :rows="20" striped-rows row-hover @row-click="openRow">
        <Column field="request_number" header="Mã yêu cầu" sortable><template #body="{ data }"><span class="font-mono font-semibold text-sky-700">{{ data.request_number }}</span></template></Column>
        <Column field="customer_name" header="Khách hàng" sortable />
        <Column header="Chứng từ bán"><template #body="{ data }">{{ data.documents?.map(d => d.invoice_number).join(', ') }}</template></Column>
        <Column field="reason" header="Lý do trả" />
        <Column field="total_amount" header="Giá trị yêu cầu" sortable><template #body="{ data }"><span class="font-semibold">{{ money(data.total_amount) }}</span></template></Column>
        <Column field="status" header="Trạng thái" sortable><template #body="{ data }"><Tag :value="statusMeta(data.status).label" :severity="statusMeta(data.status).severity" /></template></Column>
        <Column field="created_at" header="Ngày tạo" sortable><template #body="{ data }">{{ dateTime(data.created_at) }}</template></Column>
        <Column header=""><template #body="{ data }"><Button icon="pi pi-eye" text size="small" @click.stop="router.push(`/sale-return-request/${data.id}`)" /></template></Column>
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Toolbar from 'primevue/toolbar';
import PageHeader from '../../components/common/PageHeader.vue';
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
const filteredRows = computed(() => rows.value.filter(row => (!status.value || row.status === status.value) &&
  (!search.value || `${row.request_number} ${row.customer_name}`.toLowerCase().includes(search.value.toLowerCase()))));
const money = value => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value || 0);
const dateTime = value => value ? new Date(value).toLocaleString('vi-VN') : '';
const openRow = event => router.push(`/sale-return-request/${event.data.id}`);
onMounted(async () => { loading.value = true; try { rows.value = (await salesApi.getSaleReturnRequests()).data || []; } finally { loading.value = false; } });
</script>
