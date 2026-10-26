<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Chấm Công Của Tôi" subtitle="Bảng công theo tháng, giờ vào/ra và các ngày cần bổ sung">
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="load" />
        <Button label="Yêu cầu của tôi" icon="pi pi-list" size="small" severity="secondary" outlined @click="router.push('/eoffice/attendance-requests')" />
        <Button label="Tạo yêu cầu" icon="pi pi-plus" size="small" @click="router.push('/eoffice/attendance-requests/new')" />
      </template>
    </PageHeader>

    <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 flex flex-wrap items-center gap-3">
      <Button icon="pi pi-chevron-left" size="small" text severity="secondary" @click="changeMonth(-1)" />
      <strong class="text-sm font-mono">Tháng {{ monthLabel(month) }}</strong>
      <Button icon="pi pi-chevron-right" size="small" text severity="secondary" :disabled="month >= currentMonth()" @click="changeMonth(1)" />
      <span v-if="workMode" class="text-xs text-slate-500">Hình thức làm việc: <strong class="text-slate-800">{{ workModeLabel }}</strong></span>
      <div class="ml-auto flex items-center gap-2">
        <span v-if="!canCheckInWeb" class="text-[11px] text-slate-400">Nhân viên làm tại văn phòng chấm công bằng máy chấm công</span>
        <Button v-if="canCheckInWeb" label="Chấm công ngay" icon="pi pi-clock" size="small" severity="success" :loading="checking" @click="checkIn" />
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
      <div v-for="tile in tiles" :key="tile.label" class="bg-white border border-slate-200 rounded-[6px] px-4 py-3">
        <div class="text-[11px] text-slate-500">{{ tile.label }}</div>
        <div class="text-xl font-bold font-mono tabular-nums" :class="tile.color">{{ tile.value }}</div>
        <div class="text-[11px] text-slate-400">{{ tile.unit }}</div>
      </div>
    </div>

    <div v-if="summary.missing_punch_days > 0 || summary.absent_days > 0" class="bg-amber-50 border border-amber-200 rounded-[6px] px-4 py-3 text-xs text-amber-900 flex gap-2">
      <i class="pi pi-exclamation-triangle mt-0.5"></i>
      <div>
        Bạn có <strong>{{ summary.missing_punch_days }}</strong> ngày thiếu chấm công và <strong>{{ summary.absent_days }}</strong> ngày vắng chưa giải trình.
        Hãy gửi yêu cầu bổ sung/bù công trong hạn để được tính công. Bấm biểu tượng <i class="pi pi-file-edit"></i> ở từng ngày.
      </div>
    </div>

    <CommonDataTable
      :value="days"
      :columns="columns"
      :loading="loading"
      :rows="35"
      :paginator="false"
      empty-icon="pi pi-calendar"
      empty-title="Chưa có dữ liệu công trong tháng này"
      row-hover
      @row-click="event => openDay(event.data)"
    >
      <template #col-work_date="{ data }">
        <span class="text-xs font-mono" :class="isOffDay(data) ? 'text-slate-400' : 'text-slate-900'">
          {{ weekdayOf(data.work_date) }} {{ formatDate(data.work_date) }}
        </span>
      </template>
      <template #col-first_in="{ data }"><span class="font-mono text-xs">{{ formatTime(data.first_in) }}</span></template>
      <template #col-last_out="{ data }"><span class="font-mono text-xs">{{ formatTime(data.last_out) }}</span></template>
      <template #col-late_minutes="{ data }">
        <span class="font-mono text-xs" :class="data.late_minutes ? 'text-rose-600' : 'text-slate-400'">{{ formatMinutes(data.late_minutes) }}</span>
      </template>
      <template #col-early_minutes="{ data }">
        <span class="font-mono text-xs" :class="data.early_minutes ? 'text-rose-600' : 'text-slate-400'">{{ formatMinutes(data.early_minutes) }}</span>
      </template>
      <template #col-ot_minutes="{ data }">
        <span class="font-mono text-xs" :class="data.ot_minutes ? 'text-emerald-700 font-semibold' : 'text-slate-400'">{{ formatMinutes(data.ot_minutes) }}</span>
      </template>
      <template #col-work_value="{ data }">
        <div class="text-right font-mono text-xs font-semibold">{{ formatDays(data.work_value) }}</div>
      </template>
      <template #col-status="{ data }">
        <Tag :value="DAY_STATUS[data.status]?.label || data.status" :severity="DAY_STATUS[data.status]?.severity" />
      </template>
      <template #col-note="{ data }"><span class="text-[11px] text-slate-500">{{ data.note || '' }}</span></template>

      <Column header="" style="width: 90px" class="text-center">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1" @click.stop>
            <Button v-tooltip.top="'Xem log chấm công'" icon="pi pi-eye" text size="small" severity="info" class="!w-7 !h-7 !p-0" @click="openDay(data)" />
            <Button v-if="FIXABLE_STATUSES.includes(data.status)" v-tooltip.top="'Gửi yêu cầu sửa công'" icon="pi pi-file-edit" text size="small" class="!w-7 !h-7 !p-0" @click="createRequestFor(data)" />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <Dialog v-model:visible="dayDialog.visible" modal :header="`Chi tiết ngày ${formatDate(dayDialog.day?.work_date)}`" :style="{ width: '520px' }">
      <div v-if="dayDialog.day" class="flex flex-col gap-3 text-xs">
        <div class="flex items-center gap-2">
          <Tag :value="DAY_STATUS[dayDialog.day.status]?.label" :severity="DAY_STATUS[dayDialog.day.status]?.severity" />
          <span class="text-slate-600">Công: <strong class="font-mono">{{ formatDays(dayDialog.day.work_value) }}</strong></span>
          <span class="text-slate-600">Giờ làm: <strong class="font-mono">{{ formatMinutes(dayDialog.day.worked_minutes) }}</strong></span>
        </div>
        <p v-if="dayDialog.day.note" class="m-0 text-slate-600">{{ dayDialog.day.note }}</p>
        <div class="border border-slate-200 rounded-[6px] overflow-hidden">
          <div class="px-3 py-2 bg-slate-50 font-semibold text-slate-700">Log chấm công</div>
          <div v-if="dayDialog.loading" class="p-3 text-slate-400">Đang tải...</div>
          <div v-else-if="!dayDialog.punches.length" class="p-3 text-slate-400">Không có log chấm công trong ngày.</div>
          <div v-for="punch in dayDialog.punches" :key="punch.id" class="px-3 py-2 border-t border-slate-100 flex items-center gap-3" :class="punch.voided_at ? 'opacity-50 line-through' : ''">
            <span class="font-mono font-semibold">{{ formatTime(punch.punched_at) }}</span>
            <span class="text-slate-600">{{ PUNCH_SOURCES[punch.source] || punch.source }}</span>
            <span v-if="punch.voided_at" class="text-rose-600 no-underline">đã hủy: {{ punch.void_reason }}</span>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="dayDialog.visible = false" />
        <Button v-if="dayDialog.day && FIXABLE_STATUSES.includes(dayDialog.day.status)" label="Gửi yêu cầu sửa công" icon="pi pi-file-edit" size="small" @click="createRequestFor(dayDialog.day)" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import { attendanceApi } from '../../../api/attendance.api';
import { formatDate } from '../../../utils/format';
import { formatDays } from '../constants';
import {
  DAY_STATUS, FIXABLE_STATUSES, PUNCH_SOURCES, WORK_MODES, currentMonth, formatMinutes, formatTime, monthLabel, shiftMonth, weekdayOf
} from '../attendance-constants';

const router = useRouter();
const toast = useToast();

const month = ref(currentMonth());
const days = ref([]);
const summary = ref({});
const workMode = ref(null);
const canCheckInWeb = ref(false);
const loading = ref(false);
const checking = ref(false);
const dayDialog = reactive({ visible: false, day: null, punches: [], loading: false });

const columns = [
  { field: 'work_date', header: 'Ngày', width: '150px' },
  { field: 'first_in', header: 'Giờ vào', width: '80px', sortable: false },
  { field: 'last_out', header: 'Giờ ra', width: '80px', sortable: false },
  { field: 'late_minutes', header: 'Đi trễ', width: '75px', sortable: false },
  { field: 'early_minutes', header: 'Về sớm', width: '75px', sortable: false },
  { field: 'ot_minutes', header: 'OT', width: '75px', sortable: false },
  { field: 'work_value', header: 'Công', width: '70px', align: 'right', sortable: false },
  { field: 'status', header: 'Trạng thái', width: '160px', sortable: false },
  { field: 'note', header: 'Ghi chú', sortable: false }
];

const workModeLabel = computed(() => WORK_MODES.find(mode => mode.value === workMode.value)?.label || workMode.value);
const tiles = computed(() => [
  { label: 'Tổng công', value: formatDays(summary.value.work_days), unit: 'ngày công', color: 'text-emerald-600' },
  { label: 'Đi trễ', value: summary.value.late_count || 0, unit: `lần · ${summary.value.late_minutes || 0} phút`, color: 'text-slate-900' },
  { label: 'Thiếu chấm công', value: summary.value.missing_punch_days || 0, unit: 'ngày', color: summary.value.missing_punch_days ? 'text-rose-600' : 'text-slate-900' },
  { label: 'Vắng', value: summary.value.absent_days || 0, unit: 'ngày', color: summary.value.absent_days ? 'text-rose-600' : 'text-slate-900' },
  { label: 'Nghỉ phép', value: summary.value.leave_paid_days || 0, unit: 'ngày có lương', color: 'text-sky-600' },
  { label: 'Nghỉ không lương', value: summary.value.leave_unpaid_days || 0, unit: 'ngày', color: 'text-slate-900' }
]);

const isOffDay = day => ['WEEKEND', 'HOLIDAY', 'OUT_OF_EMPLOYMENT'].includes(day.status);

const load = async () => {
  loading.value = true;
  try {
    const { data } = await attendanceApi.getMyTimesheet(month.value);
    days.value = data.days || [];
    summary.value = data.summary || {};
    workMode.value = data.work_mode;
    canCheckInWeb.value = !!data.can_check_in_web;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được bảng công', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const changeMonth = delta => {
  month.value = shiftMonth(month.value, delta);
  load();
};

const checkIn = async () => {
  checking.value = true;
  try {
    await attendanceApi.checkIn();
    toast.add({ severity: 'success', summary: 'Đã ghi nhận chấm công', life: 2500 });
    month.value = currentMonth();
    await load();
  } catch (error) {
    toast.add({ severity: 'warn', summary: 'Chưa chấm công được', detail: error.message, life: 5000 });
  } finally {
    checking.value = false;
  }
};

const openDay = async day => {
  Object.assign(dayDialog, { visible: true, day, punches: [], loading: true });
  try {
    const { data } = await attendanceApi.getPunches(undefined, String(day.work_date).substring(0, 10));
    dayDialog.punches = data || [];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được log chấm công', detail: error.message, life: 4000 });
  } finally {
    dayDialog.loading = false;
  }
};

const createRequestFor = day => {
  const type = day.status === 'MISSING_PUNCH' ? 'ADD_PUNCH' : 'MAKEUP';
  router.push({ path: '/eoffice/attendance-requests/new', query: { type, date: String(day.work_date).substring(0, 10) } });
};

onMounted(load);
</script>
