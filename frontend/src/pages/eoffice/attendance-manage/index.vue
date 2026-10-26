<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Quản Lý Chấm Công" subtitle="Bảng công toàn công ty, nhập file máy chấm công, hồ sơ nhân sự và nhật ký điều chỉnh">
      <template #actions>
        <Button v-if="canManage && tab === 'summary'" label="Tính lại bảng công tháng" icon="pi pi-calculator" size="small" severity="secondary" outlined :loading="recalculating" @click="recalculate" />
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="reloadCurrent" />
      </template>
    </PageHeader>

    <SelectButton v-model="tab" :options="tabs" option-label="label" option-value="value" :allow-empty="false" size="small" @change="reloadCurrent" />

    <template v-if="tab === 'summary' || tab === 'audit'">
      <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 flex flex-wrap items-center gap-3">
        <Button icon="pi pi-chevron-left" size="small" text severity="secondary" @click="changeMonth(-1)" />
        <strong class="text-sm font-mono">Tháng {{ monthLabel(month) }}</strong>
        <Button icon="pi pi-chevron-right" size="small" text severity="secondary" :disabled="month >= currentMonth()" @click="changeMonth(1)" />
        <InputText v-if="tab === 'summary'" v-model="search" placeholder="Tìm tên, mã nhân viên..." size="small" class="w-60" />
        <span v-if="tab === 'summary'" class="ml-auto text-xs text-slate-500">{{ rows.length }} nhân viên có dữ liệu công</span>
      </div>
    </template>

    <template v-if="tab === 'summary'">
      <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        <div v-for="tile in tiles" :key="tile.label" class="bg-white border border-slate-200 rounded-[6px] px-4 py-3">
          <div class="text-[11px] text-slate-500">{{ tile.label }}</div>
          <div class="text-xl font-bold font-mono tabular-nums" :class="tile.color">{{ tile.value }}</div>
        </div>
      </div>

      <CommonDataTable :value="rows" :columns="columns" :loading="loading" :rows="25" :search-query="search" :search-fields="['user_name', 'employee_code']"
        empty-icon="pi pi-users" empty-title="Chưa có dữ liệu công trong tháng này" default-sort-field="employee_code" :default-sort-order="1" row-hover @row-click="event => openTimesheet(event.data)">
        <template #col-employee_code="{ data }"><span class="font-mono text-xs font-semibold text-sky-700">{{ data.employee_code }}</span></template>
        <template #col-work_days="{ data }"><div class="text-right font-mono text-xs font-semibold text-emerald-700">{{ formatDays(data.work_days) }}</div></template>
        <template #col-late_count="{ data }"><div class="text-right font-mono text-xs">{{ data.late_count }}</div></template>
        <template #col-late_minutes="{ data }"><div class="text-right font-mono text-xs">{{ data.late_minutes }}</div></template>
        <template #col-absent_days="{ data }"><div class="text-right font-mono text-xs" :class="data.absent_days ? 'text-rose-600 font-semibold' : 'text-slate-400'">{{ data.absent_days }}</div></template>
        <template #col-missing_punch_days="{ data }"><div class="text-right font-mono text-xs" :class="data.missing_punch_days ? 'text-rose-600 font-semibold' : 'text-slate-400'">{{ data.missing_punch_days }}</div></template>
        <template #col-leave_paid_days="{ data }"><div class="text-right font-mono text-xs">{{ data.leave_paid_days }}</div></template>
        <template #col-leave_unpaid_days="{ data }"><div class="text-right font-mono text-xs">{{ data.leave_unpaid_days }}</div></template>
        <Column header="" style="width: 60px" class="text-center">
          <template #body="{ data }"><Button v-tooltip.top="'Xem bảng công chi tiết'" icon="pi pi-calendar" text size="small" severity="info" class="!w-7 !h-7 !p-0" @click.stop="openTimesheet(data)" /></template>
        </Column>
      </CommonDataTable>
    </template>

    <AttendanceImportPanel v-else-if="tab === 'import'" @imported="onImported" />

    <EmployeeProfilesPanel v-else-if="tab === 'profiles'" :can-manage="canManage" />

    <CommonDataTable v-else :value="auditLogs" :columns="auditColumns" :loading="loading" :rows="20" empty-icon="pi pi-history" empty-title="Chưa có điều chỉnh thủ công nào trong tháng này">
      <template #col-created_at="{ data }"><span class="text-xs">{{ formatDateTime(data.created_at) }}</span></template>
      <template #col-work_date="{ data }"><span class="text-xs font-mono">{{ formatDate(data.work_date) }}</span></template>
      <template #col-user_id="{ data }"><span class="text-xs">{{ nameOf(data.user_id) }}</span></template>
      <template #col-action="{ data }"><Tag :value="AUDIT_ACTIONS[data.action] || data.action" :severity="data.action === 'ADD_WORK' ? 'success' : 'danger'" /></template>
      <template #col-change="{ data }"><span class="text-xs text-slate-600">{{ DAY_STATUS[data.before?.status]?.label || '—' }} → {{ DAY_STATUS[data.after?.status]?.label || '—' }}</span></template>
    </CommonDataTable>

    <UserTimesheetDialog v-model:visible="timesheet.visible" :employee="timesheet.employee" :month="month" :can-manage="canManage" @changed="loadSummary" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import AttendanceImportPanel from '../components/AttendanceImportPanel.vue';
import EmployeeProfilesPanel from '../components/EmployeeProfilesPanel.vue';
import UserTimesheetDialog from '../components/UserTimesheetDialog.vue';
import { attendanceApi } from '../../../api/attendance.api';
import { formatDate, formatDateTime } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';
import { storage } from '../../../utils/storage';
import { formatDays } from '../constants';
import { AUDIT_ACTIONS, DAY_STATUS, currentMonth, monthLabel, shiftMonth } from '../attendance-constants';

const toast = useToast();
const user = parseJwt(storage.getToken());
const canManage = hasPermission('attendance.manage', user) || hasPermission('eoffice.manage', user);

// Người chỉ có attendance.view xem được mọi dữ liệu nhưng không có tab nhập file (thao tác ghi)
const tabs = [
  { label: 'Tổng hợp công tháng', value: 'summary' },
  ...(canManage ? [{ label: 'Nhập file máy chấm công', value: 'import' }] : []),
  { label: 'Hồ sơ nhân sự & ca', value: 'profiles' },
  { label: 'Nhật ký điều chỉnh', value: 'audit' }
];
const tab = ref('summary');
const month = ref(currentMonth());
const rows = ref([]);
const auditLogs = ref([]);
const names = ref({});
const search = ref('');
const loading = ref(false);
const recalculating = ref(false);
const timesheet = reactive({ visible: false, employee: null });

const columns = [
  { field: 'employee_code', header: 'Mã NV', width: '100px' },
  { field: 'user_name', header: 'Họ tên' },
  { field: 'work_days', header: 'Công', width: '80px', align: 'right' },
  { field: 'late_count', header: 'Lần trễ', width: '85px', align: 'right' },
  { field: 'late_minutes', header: 'Phút trễ', width: '90px', align: 'right' },
  { field: 'absent_days', header: 'Vắng', width: '75px', align: 'right' },
  { field: 'missing_punch_days', header: 'Thiếu chấm', width: '100px', align: 'right' },
  { field: 'leave_paid_days', header: 'Nghỉ phép', width: '95px', align: 'right' },
  { field: 'leave_unpaid_days', header: 'Nghỉ KL', width: '85px', align: 'right' }
];
const auditColumns = [
  { field: 'created_at', header: 'Thời gian', width: '150px', sortable: false },
  { field: 'work_date', header: 'Ngày công', width: '110px', sortable: false },
  { field: 'user_id', header: 'Nhân viên', width: '200px', sortable: false },
  { field: 'action', header: 'Thao tác', width: '150px', sortable: false },
  { field: 'change', header: 'Thay đổi', width: '200px', sortable: false },
  { field: 'actor_name', header: 'Người thực hiện', width: '180px', sortable: false },
  { field: 'reason', header: 'Lý do', sortable: false }
];

const total = key => rows.value.reduce((sum, row) => sum + Number(row[key] || 0), 0);
const tiles = computed(() => [
  { label: 'Tổng công', value: formatDays(total('work_days')), color: 'text-emerald-600' },
  { label: 'Lượt đi trễ', value: total('late_count'), color: 'text-slate-900' },
  { label: 'Ngày thiếu chấm công', value: total('missing_punch_days'), color: total('missing_punch_days') ? 'text-rose-600' : 'text-slate-900' },
  { label: 'Ngày vắng', value: total('absent_days'), color: total('absent_days') ? 'text-rose-600' : 'text-slate-900' },
  { label: 'Nghỉ phép / không lương', value: `${total('leave_paid_days')} / ${total('leave_unpaid_days')}`, color: 'text-sky-600' }
]);

const nameOf = userId => names.value[userId] || `#${userId}`;

const loadSummary = async () => {
  loading.value = true;
  try {
    rows.value = (await attendanceApi.getSummary(month.value)).data || [];
    names.value = Object.fromEntries(rows.value.map(row => [row.user_id, row.user_name]));
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được tổng hợp công', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const loadAudit = async () => {
  loading.value = true;
  try {
    const [audit, summary] = await Promise.all([attendanceApi.getAuditLogs({ month: month.value }), attendanceApi.getSummary(month.value).catch(() => ({ data: [] }))]);
    auditLogs.value = audit.data || [];
    names.value = { ...names.value, ...Object.fromEntries((summary.data || []).map(row => [row.user_id, row.user_name])) };
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được nhật ký', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const reloadCurrent = () => {
  if (tab.value === 'summary') loadSummary();
  else if (tab.value === 'audit') loadAudit();
};

const changeMonth = delta => {
  month.value = shiftMonth(month.value, delta);
  reloadCurrent();
};

const recalculate = async () => {
  if (!confirm(`Tính lại bảng công tháng ${monthLabel(month.value)} cho toàn bộ nhân viên? Ngày đã khóa công được giữ nguyên.`)) return;
  recalculating.value = true;
  try {
    await attendanceApi.recalculate(month.value);
    toast.add({ severity: 'success', summary: 'Đã tính lại bảng công', life: 3000 });
    await loadSummary();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tính lại được', detail: error.message, life: 5000 });
  } finally {
    recalculating.value = false;
  }
};

const openTimesheet = employee => Object.assign(timesheet, { visible: true, employee });
const onImported = () => {
  month.value = currentMonth();
};

onMounted(loadSummary);
</script>
