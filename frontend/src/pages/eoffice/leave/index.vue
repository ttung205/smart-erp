<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Nghỉ Phép" subtitle="Đơn của bạn, đơn của nhân viên bạn quản lý và đơn đang chờ bạn duyệt">
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="load" />
        <Button label="Lịch nghỉ" icon="pi pi-calendar" size="small" severity="secondary" outlined @click="router.push('/eoffice/leave/calendar')" />
        <Button v-if="canManageLeave" label="Quản lý phép năm" icon="pi pi-sliders-h" size="small" severity="secondary" outlined @click="router.push('/eoffice/leave/balances')" />
        <Button label="Tạo đơn nghỉ" icon="pi pi-plus" size="small" @click="router.push('/eoffice/leave/new')" />
      </template>
    </PageHeader>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <div v-for="tile in balanceTiles" :key="tile.label" class="bg-white border border-slate-200 rounded-[6px] px-4 py-3">
        <div class="text-[11px] text-slate-500">{{ tile.label }}</div>
        <div class="text-xl font-bold font-mono tabular-nums" :class="tile.color">{{ formatDays(tile.value) }}</div>
        <div class="text-[11px] text-slate-400">ngày · phép năm {{ year }} của tôi</div>
      </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 flex flex-wrap items-center gap-3">
      <SelectButton v-model="scope" :options="scopeOptions" option-label="label" option-value="value" :allow-empty="false" size="small" @change="changeScope">
        <template #option="{ option }">
          <span>{{ option.label }}</span>
          <span v-if="option.value === 'to_approve' && pendingCount" class="ml-1 px-1.5 rounded-[4px] bg-amber-100 text-amber-700 font-mono text-[11px]">{{ pendingCount }}</span>
        </template>
      </SelectButton>
      <InputText v-model="search" placeholder="Tìm mã đơn, người đề nghị..." size="small" class="w-60" />
      <Select v-model="statusFilter" :options="statusOptions" option-label="label" option-value="value" show-clear placeholder="Tất cả trạng thái" size="small" class="w-44" @change="load" />
      <Select v-model="year" :options="yearOptions" size="small" class="w-28" @change="load" />
      <span class="ml-auto text-xs text-slate-500">Tổng: <strong class="font-mono">{{ requests.length }}</strong> đơn</span>
    </div>

    <CommonDataTable
      :value="requests"
      :columns="columns"
      :loading="loading"
      :rows="20"
      :search-query="search"
      :search-fields="['code', 'user_name']"
      empty-icon="pi pi-calendar-minus"
      :empty-title="emptyTitle"
      row-hover
      @row-click="event => router.push(`/eoffice/leave/${event.data.id}`)"
    >
      <template #col-code="{ data }">
        <span class="font-mono text-xs font-semibold text-sky-700">{{ data.code }}</span>
      </template>
      <template #col-user_name="{ data }">
        <span class="text-xs" :class="isMine(data) ? 'text-slate-500' : 'text-slate-900'">{{ isMine(data) ? 'Tôi' : data.user_name }}</span>
      </template>
      <template #col-leave_type="{ data }">
        <span class="text-xs text-slate-800">{{ data.leave_type?.name }}</span>
      </template>
      <template #col-period="{ data }">
        <span class="text-xs text-slate-800">{{ formatPeriod(data) }}</span>
      </template>
      <template #col-total_days="{ data }">
        <div class="text-right font-mono text-xs font-semibold">{{ formatDays(data.total_days) }}</div>
      </template>
      <template #col-approver="{ data }">
        <span class="text-xs text-slate-700">{{ lastStep(data)?.approver_name || '—' }}</span>
      </template>
      <template #col-status="{ data }">
        <Tag :value="LEAVE_STATUS[data.status]?.label" :severity="LEAVE_STATUS[data.status]?.severity" />
      </template>

      <Column header="Thao tác" class="text-center" style="width: 170px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1" @click.stop>
            <Button v-tooltip.top="'Xem chi tiết'" icon="pi pi-eye" text size="small" severity="info" class="!w-7 !h-7 !p-0" @click="router.push(`/eoffice/leave/${data.id}`)" />
            <template v-if="myPendingStep(data)">
              <Button v-tooltip.top="'Duyệt'" icon="pi pi-check" text size="small" severity="success" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'APPROVED')" />
              <Button v-tooltip.top="'Trả lại để bổ sung'" icon="pi pi-reply" text size="small" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'RETURNED')" />
              <Button v-tooltip.top="'Từ chối'" icon="pi pi-times" text size="small" severity="danger" class="!w-7 !h-7 !p-0" @click="openDecision(data, 'REJECTED')" />
            </template>
            <template v-else-if="isMine(data)">
              <Button v-if="isEditable(data)" v-tooltip.top="'Gửi duyệt'" icon="pi pi-send" text size="small" class="!w-7 !h-7 !p-0" :loading="busyId === data.id" @click="submit(data)" />
              <Button v-if="data.status === 'DRAFT'" v-tooltip.top="'Xóa đơn nháp'" icon="pi pi-trash" text size="small" severity="danger" class="!w-7 !h-7 !p-0" @click="remove(data)" />
              <Button v-if="canCancel(data)" v-tooltip.top="'Hủy đơn'" icon="pi pi-times-circle" text size="small" severity="danger" class="!w-7 !h-7 !p-0" @click="openCancel(data)" />
            </template>
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <LeaveDecisionDialog v-model:visible="decision.visible" :request="decision.request" :decision="decision.type" @done="load" />

    <Dialog v-model:visible="cancelDialog.visible" modal :header="`Xác Nhận Hủy Đơn ${cancelDialog.request?.code || ''}`" :style="{ width: '440px' }">
      <div class="flex flex-col gap-2 text-xs">
        <p class="m-0 text-slate-600">Số ngày phép của đơn sẽ được hoàn lại vào quỹ phép.</p>
        <label class="font-semibold text-slate-700">Lý do hủy</label>
        <Textarea v-model="cancelDialog.reason" rows="3" auto-resize class="w-full text-sm" />
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="cancelDialog.visible = false" />
        <Button label="Hủy đơn" severity="danger" size="small" :loading="busyId === cancelDialog.request?.id" @click="confirmCancel" />
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
import LeaveDecisionDialog from '../components/LeaveDecisionDialog.vue';
import { eofficeApi } from '../../../api/eoffice.api';
import { formatDate } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';
import { storage } from '../../../utils/storage';
import { LEAVE_STATUS, SESSION_LABELS, formatDays, toIsoDate } from '../constants';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const currentUser = parseJwt(storage.getToken());
const currentUserId = Number(currentUser?.sub);
const canManageLeave = hasPermission('eoffice.manage', currentUser);
const currentYear = new Date().getFullYear();

const scopeOptions = [
  { label: 'Tất cả', value: 'all' },
  { label: 'Của tôi', value: 'mine' },
  { label: 'Chờ tôi duyệt', value: 'to_approve' },
  { label: 'Nhân viên của tôi', value: 'team' }
];
const requests = ref([]);
const balance = ref(null);
const pendingCount = ref(0);
const loading = ref(false);
const busyId = ref(null);
const search = ref('');
const scope = ref(scopeOptions.some(option => option.value === route.query.scope) ? route.query.scope : 'all');
const statusFilter = ref(null);
const year = ref(currentYear);
const yearOptions = [currentYear - 1, currentYear, currentYear + 1];
const statusOptions = Object.entries(LEAVE_STATUS).map(([value, { label }]) => ({ value, label }));
const cancelDialog = reactive({ visible: false, request: null, reason: '' });
const decision = reactive({ visible: false, request: null, type: 'APPROVED' });

const columns = [
  { field: 'code', header: 'Mã đơn', width: '130px' },
  { field: 'user_name', header: 'Người đề nghị', width: '200px' },
  { field: 'leave_type', header: 'Loại nghỉ', width: '160px', sortable: false },
  { field: 'period', header: 'Thời gian nghỉ', sortable: false },
  { field: 'total_days', header: 'Số ngày', width: '80px', align: 'right' },
  { field: 'approver', header: 'Người duyệt', width: '190px', sortable: false },
  { field: 'status', header: 'Trạng thái', width: '110px', align: 'center' }
];

const emptyTitle = computed(() => ({
  mine: 'Bạn chưa có đơn nghỉ nào',
  to_approve: 'Không có đơn nào chờ bạn duyệt',
  team: 'Nhân viên của bạn chưa gửi đơn nghỉ nào'
}[scope.value] || 'Chưa có đơn nghỉ phép'));

const balanceTiles = computed(() => [
  { label: 'Phép đầu kỳ', value: balance.value?.opening_days, color: 'text-slate-900' },
  { label: 'Đã nghỉ', value: balance.value?.used_days, color: 'text-slate-900' },
  { label: 'Đang chờ duyệt', value: balance.value?.pending_days, color: 'text-amber-600' },
  { label: 'Còn lại', value: balance.value?.remaining_days, color: 'text-emerald-600' }
]);

const formatPeriod = item => {
  const start = `${formatDate(item.start_date)}${item.start_session !== 'FULL' ? ` (${SESSION_LABELS[item.start_session]})` : ''}`;
  if (item.start_date === item.end_date) return start;
  return `${start} → ${formatDate(item.end_date)}${item.end_session !== 'FULL' ? ` (${SESSION_LABELS[item.end_session]})` : ''}`;
};
const isMine = item => Number(item.user_id) === currentUserId;
const lastStep = item => item.approval_steps?.[item.approval_steps.length - 1];
const myPendingStep = item => item.approval_steps?.find(step => Number(step.approver_id) === currentUserId && step.status === 'PENDING');
const isEditable = item => ['DRAFT', 'RETURNED'].includes(item.status);
const canCancel = item => item.status === 'PENDING' || (item.status === 'APPROVED' && item.start_date > toIsoDate(new Date()));

const load = async () => {
  loading.value = true;
  try {
    const [list, myBalance] = await Promise.all([
      eofficeApi.getLeaveRequests({ scope: scope.value, status: statusFilter.value, year: year.value, per_page: 200 }),
      eofficeApi.getMyLeaveBalance(year.value)
    ]);
    requests.value = list.data || [];
    pendingCount.value = list.pending_count || 0;
    balance.value = myBalance.data;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được đơn nghỉ', detail: error.message, life: 4000 });
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

const submit = item => runAction(item, () => eofficeApi.submitLeaveRequest(item.id), 'Đã gửi đơn đi duyệt');
const remove = item => {
  if (!confirm(`Xóa đơn nháp ${item.code}?`)) return;
  runAction(item, () => eofficeApi.deleteLeaveRequest(item.id), 'Đã xóa đơn nháp');
};
const openCancel = item => Object.assign(cancelDialog, { visible: true, request: item, reason: '' });
const confirmCancel = async () => {
  const ok = await runAction(cancelDialog.request, () => eofficeApi.cancelLeaveRequest(cancelDialog.request.id, cancelDialog.reason), 'Đã hủy đơn');
  if (ok) cancelDialog.visible = false;
};
const openDecision = (item, type) => Object.assign(decision, { visible: true, request: item, type });

onMounted(load);
</script>
