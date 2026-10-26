<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Yêu Cầu Chấm Công" subtitle="Bổ sung công, bù công, làm việc từ xa, công tác và làm thêm giờ">
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="load" />
        <Button label="Chấm công của tôi" icon="pi pi-clock" size="small" severity="secondary" outlined @click="router.push('/eoffice/attendance')" />
        <Button label="Tạo yêu cầu" icon="pi pi-plus" size="small" @click="router.push('/eoffice/attendance-requests/new')" />
      </template>
    </PageHeader>

    <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 flex flex-wrap items-center gap-3">
      <SelectButton v-model="scope" :options="scopeOptions" option-label="label" option-value="value" :allow-empty="false" size="small" @change="changeScope">
        <template #option="{ option }">
          <span>{{ option.label }}</span>
          <span v-if="option.value === 'to_approve' && pendingCount" class="ml-1 px-1.5 rounded-[4px] bg-amber-100 text-amber-700 font-mono text-[11px]">{{ pendingCount }}</span>
        </template>
      </SelectButton>
      <InputText v-model="search" placeholder="Tìm mã yêu cầu, người gửi..." size="small" class="w-60" />
      <Select v-model="typeFilter" :options="REQUEST_TYPE_OPTIONS" option-label="label" option-value="value" show-clear placeholder="Mọi loại" size="small" class="w-44" @change="load" />
      <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" show-clear placeholder="Tất cả trạng thái" size="small" class="w-44" @change="load" />
      <span class="ml-auto text-xs text-slate-500">Tổng: <strong class="font-mono">{{ requests.length }}</strong> yêu cầu</span>
    </div>

    <CommonDataTable
      :value="requests"
      :columns="columns"
      :loading="loading"
      :rows="20"
      :search-query="search"
      :search-fields="['code', 'user_name']"
      empty-icon="pi pi-clock"
      :empty-title="emptyTitle"
      row-hover
      @row-click="event => router.push(`/eoffice/attendance-requests/${event.data.id}`)"
    >
      <template #col-code="{ data }"><span class="font-mono text-xs font-semibold text-sky-700">{{ data.code }}</span></template>
      <template #col-user_name="{ data }">
        <span class="text-xs" :class="isMine(data) ? 'text-slate-500' : 'text-slate-900'">{{ isMine(data) ? 'Tôi' : data.user_name }}</span>
      </template>
      <template #col-type="{ data }"><span class="text-xs text-slate-800">{{ REQUEST_TYPES[data.type]?.label }}</span></template>
      <template #col-period="{ data }"><span class="text-xs text-slate-800">{{ formatPeriod(data) }}</span></template>
      <template #col-approver="{ data }"><span class="text-xs text-slate-700">{{ lastStep(data)?.approver_name || '—' }}</span></template>
      <template #col-status="{ data }">
        <Tag :value="REQUEST_STATUS[data.status]?.label" :severity="REQUEST_STATUS[data.status]?.severity" />
      </template>

      <Column header="Thao tác" class="text-center" style="width: 170px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1" @click.stop>
            <Button v-tooltip.top="'Xem chi tiết'" icon="pi pi-eye" text size="small" severity="info" class="!w-7 !h-7 !p-0" @click="router.push(`/eoffice/attendance-requests/${data.id}`)" />
            <template v-if="myPendingStep(data)">
              <Button v-tooltip.top="'Duyệt'" icon="pi pi-check" text size="small" severity="success" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'APPROVED')" />
              <Button v-tooltip.top="'Trả lại'" icon="pi pi-reply" text size="small" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'RETURNED')" />
              <Button v-tooltip.top="'Từ chối'" icon="pi pi-times" text size="small" severity="danger" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'REJECTED')" />
            </template>
            <template v-else-if="isMine(data)">
              <Button v-if="isEditable(data)" v-tooltip.top="'Gửi duyệt'" icon="pi pi-send" text size="small" class="!w-7 !h-7 !p-0" :loading="busyId === data.id" @click="submit(data)" />
              <Button v-if="data.status === 'DRAFT'" v-tooltip.top="'Xóa nháp'" icon="pi pi-trash" text size="small" severity="danger" class="!w-7 !h-7 !p-0" @click="remove(data)" />
              <Button v-if="['PENDING', 'APPROVED'].includes(data.status)" v-tooltip.top="'Hủy yêu cầu'" icon="pi pi-times-circle" text size="small" severity="danger" class="!w-7 !h-7 !p-0" @click="openCancel(data)" />
            </template>
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <AttendanceDecisionDialog v-model:visible="decision.visible" :request="decision.request" :decision="decision.type" @done="load" />

    <Dialog v-model:visible="cancelDialog.visible" modal :header="`Hủy Yêu Cầu ${cancelDialog.request?.code || ''}`" :style="{ width: '440px' }">
      <div class="flex flex-col gap-2 text-xs">
        <p class="m-0 text-slate-600">Yêu cầu đã duyệt sẽ mất hiệu lực và bảng công được tính lại (không hủy được nếu kỳ công đã khóa).</p>
        <label class="font-semibold text-slate-700">Lý do hủy</label>
        <Textarea v-model="cancelDialog.reason" rows="3" auto-resize class="w-full text-sm" />
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="cancelDialog.visible = false" />
        <Button label="Hủy yêu cầu" severity="danger" size="small" :loading="busyId === cancelDialog.request?.id" @click="confirmCancel" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import AttendanceDecisionDialog from '../components/AttendanceDecisionDialog.vue';
import { attendanceApi } from '../../../api/attendance.api';
import { formatDate } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { storage } from '../../../utils/storage';
import { REQUEST_STATUS, REQUEST_TYPES, REQUEST_TYPE_OPTIONS, formatTime } from '../attendance-constants';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const currentUserId = Number(parseJwt(storage.getToken())?.sub);

const scopeOptions = [
  { label: 'Tất cả', value: 'all' },
  { label: 'Của tôi', value: 'mine' },
  { label: 'Chờ tôi duyệt', value: 'to_approve' },
  { label: 'Nhân viên của tôi', value: 'team' }
];
const statusOptions = Object.entries(REQUEST_STATUS).map(([value, { label }]) => ({ value, label }));
const requests = ref([]);
const pendingCount = ref(0);
const loading = ref(false);
const busyId = ref(null);
const search = ref('');
const scope = ref(scopeOptions.some(option => option.value === route.query.scope) ? route.query.scope : 'all');
const typeFilter = ref(null);
const statusFilter = ref(null);
const cancelDialog = reactive({ visible: false, request: null, reason: '' });
const decision = reactive({ visible: false, request: null, type: 'APPROVED' });

const columns = [
  { field: 'code', header: 'Mã yêu cầu', width: '130px' },
  { field: 'user_name', header: 'Người gửi', width: '200px' },
  { field: 'type', header: 'Loại', width: '160px', sortable: false },
  { field: 'period', header: 'Thời gian', sortable: false },
  { field: 'approver', header: 'Người duyệt', width: '190px', sortable: false },
  { field: 'status', header: 'Trạng thái', width: '110px', align: 'center' }
];

const emptyTitle = computed(() => ({
  mine: 'Bạn chưa có yêu cầu chấm công nào',
  to_approve: 'Không có yêu cầu nào chờ bạn duyệt',
  team: 'Nhân viên của bạn chưa gửi yêu cầu nào'
}[scope.value] || 'Chưa có yêu cầu chấm công'));

const formatPeriod = item => {
  const range = item.start_date === item.end_date ? formatDate(item.start_date) : `${formatDate(item.start_date)} → ${formatDate(item.end_date)}`;
  return item.requested_in || item.requested_out ? `${range} · ${formatTime(item.requested_in)} - ${formatTime(item.requested_out)}` : range;
};
const isMine = item => Number(item.user_id) === currentUserId;
const lastStep = item => item.approval_steps?.[item.approval_steps.length - 1];
const myPendingStep = item => item.approval_steps?.find(step => Number(step.approver_id) === currentUserId && step.status === 'PENDING');
const isEditable = item => ['DRAFT', 'RETURNED'].includes(item.status);

const load = async () => {
  loading.value = true;
  try {
    const list = await attendanceApi.getRequests({ scope: scope.value, status: statusFilter.value, type: typeFilter.value, per_page: 200 });
    requests.value = list.data || [];
    pendingCount.value = list.pending_count || 0;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được yêu cầu', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const changeScope = () => {
  router.replace({ query: { ...route.query, scope: scope.value === 'all' ? undefined : scope.value } });
  load();
};

const runAction = async (item, action, successMessage) => {
  busyId.value = item.id;
  try {
    await action();
    toast.add({ severity: 'success', summary: successMessage, detail: item.code, life: 3000 });
    await load();
    return true;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Thao tác thất bại', detail: error.message, life: 5000 });
    return false;
  } finally {
    busyId.value = null;
  }
};

const submit = item => runAction(item, () => attendanceApi.submitRequest(item.id), 'Đã gửi yêu cầu đi duyệt');
const remove = item => {
  if (!confirm(`Xóa yêu cầu nháp ${item.code}?`)) return;
  runAction(item, () => attendanceApi.deleteRequest(item.id), 'Đã xóa yêu cầu nháp');
};
const openCancel = item => Object.assign(cancelDialog, { visible: true, request: item, reason: '' });
const confirmCancel = async () => {
  const ok = await runAction(cancelDialog.request, () => attendanceApi.cancelRequest(cancelDialog.request.id, cancelDialog.reason), 'Đã hủy yêu cầu');
  if (ok) cancelDialog.visible = false;
};
const openDecision = (item, type) => Object.assign(decision, { visible: true, request: item, type });

onMounted(load);
</script>
