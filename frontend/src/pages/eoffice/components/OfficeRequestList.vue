<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="title" :subtitle="`Đơn của tôi và các đề nghị đang chờ xử lý`">
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="load" />
        <Button :label="`Tạo ${shortTitle}`" icon="pi pi-plus" size="small" @click="router.push(`${base}/new`)" />
      </template>
    </PageHeader>

    <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 flex flex-wrap gap-2 items-center">
      <SelectButton v-model="scope" :options="scopes" option-label="label" option-value="value" :allow-empty="false" size="small" @change="load" />
      <InputText v-model="search" size="small" placeholder="Tìm mã, mục đích, người đề nghị..." class="w-72" />
      <Select v-model="status" :options="statuses" option-label="label" option-value="value" show-clear placeholder="Tất cả trạng thái" size="small" class="w-44" @change="load" />
      <span class="ml-auto text-xs text-slate-500">Tổng: <strong class="font-mono">{{ rows.length }}</strong></span>
    </div>

    <CommonDataTable :value="rows" :columns="columns" :loading="loading" :rows="20" :search-query="search"
      :search-fields="['code', 'purpose', 'user_name']" empty-icon="pi pi-file" :empty-title="`Chưa có ${shortTitle.toLowerCase()} nào`"
      row-hover @row-click="event => router.push(`${base}/${event.data.id}`)">
      <template #col-code="{ data }"><span class="font-mono text-xs font-semibold text-sky-700">{{ data.code }}</span></template>
      <template #col-purpose="{ data }"><span class="text-xs text-slate-800">{{ data.purpose }}</span></template>
      <template #col-total_amount="{ data }"><span class="font-mono text-xs font-semibold">{{ formatCurrency(data.total_amount) }}</span></template>
      <template #col-status="{ data }"><Tag :value="statusMeta[data.status]?.label || data.status" :severity="statusMeta[data.status]?.severity" /></template>
      <Column header="Thao tác" style="width: 105px" class="text-center">
        <template #body="{ data }">
          <div class="flex justify-center gap-1" @click.stop>
            <Button icon="pi pi-eye" text size="small" severity="info" v-tooltip.top="'Xem chi tiết'" @click="router.push(`${base}/${data.id}`)" />
            <Button v-if="isOwner(data) && data.status === 'DRAFT'" icon="pi pi-trash" text size="small" severity="danger" v-tooltip.top="'Xóa nháp'" @click="remove(data)" />
          </div>
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
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import { eofficeApi } from '../../../api/eoffice.api';
import { formatCurrency } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { storage } from '../../../utils/storage';

const props = defineProps({ kind: { type: String, required: true } });
const router = useRouter();
const route = useRoute();
const toast = useToast();
const rows = ref([]);
const loading = ref(false);
const search = ref('');
const scope = ref(['all', 'mine', 'team', 'to_approve'].includes(route.query.scope) ? route.query.scope : 'all');
const status = ref(null);
const currentUserId = Number(parseJwt(storage.getToken())?.sub);
const base = computed(() => `/eoffice/${props.kind}-requests`);
const title = computed(() => props.kind === 'purchase' ? 'Đề Nghị Mua Sắm' : 'Đề Nghị Thanh Toán');
const shortTitle = computed(() => props.kind === 'purchase' ? 'đề nghị mua sắm' : 'đề nghị thanh toán');
const scopes = [
  { label: 'Tất cả', value: 'all' },
  { label: 'Của tôi', value: 'mine' },
  { label: 'Chờ tôi duyệt', value: 'to_approve' },
  { label: 'Nhân viên của tôi', value: 'team' }
];
const statusMeta = {
  DRAFT: { label: 'Nháp', severity: 'secondary' },
  PENDING: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'success' },
  REJECTED: { label: 'Từ chối', severity: 'danger' },
  RETURNED: { label: 'Trả lại', severity: 'info' },
  CANCELLED: { label: 'Đã hủy', severity: 'secondary' }
};
const columns = [
  { field: 'code', header: 'Mã đề nghị', width: '145px' },
  { field: 'user_name', header: 'Người đề nghị', width: '180px' },
  { field: 'purpose', header: 'Mục đích' },
  { field: 'total_amount', header: 'Tổng tiền dự kiến', width: '160px', align: 'right' },
  { field: 'status', header: 'Trạng thái', width: '120px' }
];
const isOwner = row => Number(row.user_id) === currentUserId;
const load = async () => {
  loading.value = true;
  try {
    rows.value = (await eofficeApi.getOfficeRequests(props.kind, { scope: scope.value, status: status.value, per_page: 100 })).data || [];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được đề nghị', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};
const remove = async row => {
  if (!window.confirm(`Xóa đơn nháp ${row.code}?`)) return;
  try {
    await eofficeApi.deleteOfficeRequest(props.kind, row.id);
    await load();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không xóa được', detail: error.message, life: 4000 });
  }
};
onMounted(load);
</script>
