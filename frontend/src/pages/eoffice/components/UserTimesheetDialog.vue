<template>
  <Dialog :visible="visible" modal :header="`Bảng công ${employee?.user_name || ''} · tháng ${monthLabel(month)}`" :style="{ width: '860px' }" @update:visible="emit('update:visible', $event)">
    <div class="flex flex-col gap-3 text-xs">
      <div class="flex flex-wrap items-center gap-4 text-slate-600">
        <span>Công: <strong class="font-mono text-emerald-700">{{ formatDays(summary.work_days) }}</strong></span>
        <span>Đi trễ: <strong class="font-mono">{{ summary.late_count || 0 }}</strong> lần</span>
        <span>Thiếu chấm công: <strong class="font-mono" :class="summary.missing_punch_days ? 'text-rose-600' : ''">{{ summary.missing_punch_days || 0 }}</strong></span>
        <span>Vắng: <strong class="font-mono" :class="summary.absent_days ? 'text-rose-600' : ''">{{ summary.absent_days || 0 }}</strong></span>
        <span v-if="!canManage" class="text-slate-400">Chỉ xem (không có quyền quản lý chấm công)</span>
      </div>

      <div class="border border-slate-200 rounded-[6px] overflow-auto max-h-[52vh]">
        <table class="w-full text-xs">
          <thead class="bg-slate-50 text-slate-600 sticky top-0">
            <tr>
              <th class="text-left px-3 py-2">Ngày</th><th class="px-2">Vào</th><th class="px-2">Ra</th><th class="px-2">Trễ</th><th class="px-2">Sớm</th><th class="px-2">OT</th>
              <th class="px-2 text-right">Công</th><th class="px-2 text-left">Trạng thái</th><th class="px-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="day in days" :key="day.work_date" class="border-t border-slate-100" :class="['WEEKEND', 'HOLIDAY', 'OUT_OF_EMPLOYMENT'].includes(day.status) ? 'text-slate-400' : ''">
              <td class="px-3 py-1.5 font-mono">{{ weekdayOf(day.work_date) }} {{ formatDate(day.work_date) }}</td>
              <td class="px-2 text-center font-mono">{{ formatTime(day.first_in) }}</td>
              <td class="px-2 text-center font-mono">{{ formatTime(day.last_out) }}</td>
              <td class="px-2 text-center font-mono">{{ formatMinutes(day.late_minutes) }}</td>
              <td class="px-2 text-center font-mono">{{ formatMinutes(day.early_minutes) }}</td>
              <td class="px-2 text-center font-mono">{{ formatMinutes(day.ot_minutes) }}</td>
              <td class="px-2 text-right font-mono font-semibold">{{ formatDays(day.work_value) }}</td>
              <td class="px-2"><Tag :value="DAY_STATUS[day.status]?.label || day.status" :severity="DAY_STATUS[day.status]?.severity" /></td>
              <td class="px-2 whitespace-nowrap">
                <Button v-tooltip.top="'Xem / hủy log chấm công'" icon="pi pi-list" text size="small" severity="info" class="!w-7 !h-7 !p-0" @click="openPunches(day)" />
                <Button v-if="canManage && !isOff(day)" v-tooltip.top="'Thêm công'" icon="pi pi-plus-circle" text size="small" severity="success" class="!w-7 !h-7 !p-0" @click="openAdd(day)" />
                <Button v-if="canManage && !isOff(day)" v-tooltip.top="'Xóa công của ngày'" icon="pi pi-trash" text size="small" severity="danger" class="!w-7 !h-7 !p-0" @click="openClear(day)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <Dialog v-model:visible="punchDialog.visible" modal :header="`Log chấm công ${formatDate(punchDialog.day?.work_date)}`" :style="{ width: '520px' }" append-to="body">
      <div class="flex flex-col gap-2 text-xs">
        <p v-if="!punchDialog.punches.length" class="m-0 text-slate-400">Không có log chấm công trong ngày.</p>
        <div v-for="punch in punchDialog.punches" :key="punch.id" class="flex items-center gap-3 border border-slate-200 rounded-[6px] px-3 py-2" :class="punch.voided_at ? 'opacity-60' : ''">
          <span class="font-mono font-semibold" :class="punch.voided_at ? 'line-through' : ''">{{ formatTime(punch.punched_at) }}</span>
          <span class="text-slate-600">{{ PUNCH_SOURCES[punch.source] || punch.source }}</span>
          <span v-if="punch.voided_at" class="text-rose-600">đã hủy: {{ punch.void_reason }}</span>
          <Button v-else-if="canManage" label="Hủy log" size="small" text severity="danger" class="ml-auto" @click="askVoid(punch)" />
        </div>
      </div>
    </Dialog>

    <Dialog v-model:visible="actionDialog.visible" modal :header="actionDialog.title" :style="{ width: '440px' }" append-to="body">
      <div class="flex flex-col gap-3 text-xs">
        <template v-if="actionDialog.kind === 'add'">
          <div class="grid grid-cols-2 gap-3">
            <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Giờ vào</label><InputText v-model="actionDialog.in" type="time" fluid /></div>
            <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Giờ ra</label><InputText v-model="actionDialog.out" type="time" fluid /></div>
          </div>
        </template>
        <p v-else-if="actionDialog.kind === 'clear'" class="m-0 text-slate-600">Mọi log chấm công trong ngày sẽ bị đánh dấu hủy (log gốc vẫn được lưu để đối chiếu). Ngày sẽ tính lại và có thể thành vắng.</p>
        <p v-else class="m-0 text-slate-600">Log sẽ bị đánh dấu hủy và ngày được tính lại.</p>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Lý do (bắt buộc, được ghi vào nhật ký) <span class="text-rose-500">*</span></label>
          <Textarea v-model="actionDialog.reason" rows="3" auto-resize fluid placeholder="Ví dụ: Nhân viên quên chấm công, quản lý xác nhận qua email" />
        </div>
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="actionDialog.visible = false" />
        <Button label="Xác nhận" size="small" :severity="actionDialog.kind === 'add' ? 'success' : 'danger'" :loading="actionDialog.saving" @click="runAction" />
      </template>
    </Dialog>
  </Dialog>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import { attendanceApi } from '../../../api/attendance.api';
import { formatDate } from '../../../utils/format';
import { formatDays } from '../constants';
import { DAY_STATUS, PUNCH_SOURCES, formatMinutes, formatTime, monthLabel, weekdayOf } from '../attendance-constants';

const props = defineProps({
  visible: { type: Boolean, default: false },
  employee: { type: Object, default: null },
  month: { type: String, required: true },
  canManage: { type: Boolean, default: false }
});
const emit = defineEmits(['update:visible', 'changed']);

const toast = useToast();
const days = ref([]);
const summary = ref({});
const punchDialog = reactive({ visible: false, day: null, punches: [] });
const actionDialog = reactive({ visible: false, kind: 'add', title: '', day: null, punch: null, in: '', out: '', reason: '', saving: false });

const isOff = day => ['WEEKEND', 'HOLIDAY', 'OUT_OF_EMPLOYMENT'].includes(day.status);
const dateOf = day => String(day.work_date).substring(0, 10);

const load = async () => {
  if (!props.employee) return;
  try {
    const { data } = await attendanceApi.getTimesheet(props.employee.user_id, props.month);
    days.value = data.days || [];
    summary.value = data.summary || {};
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được bảng công', detail: error.message, life: 4000 });
  }
};

watch(() => [props.visible, props.employee?.user_id, props.month], () => {
  if (props.visible) load();
});

const loadPunches = async day => {
  try {
    punchDialog.punches = (await attendanceApi.getPunches(props.employee.user_id, dateOf(day))).data || [];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được log chấm công', detail: error.message, life: 4000 });
  }
};
const openPunches = async day => {
  Object.assign(punchDialog, { visible: true, day, punches: [] });
  await loadPunches(day);
};

const openAdd = day => Object.assign(actionDialog, { visible: true, kind: 'add', title: `Thêm công ${formatDate(day.work_date)}`, day, in: '08:00', out: '17:00', reason: '' });
const openClear = day => Object.assign(actionDialog, { visible: true, kind: 'clear', title: `Xóa công ${formatDate(day.work_date)}`, day, reason: '' });
const askVoid = punch => Object.assign(actionDialog, { visible: true, kind: 'void', title: `Hủy log ${formatTime(punch.punched_at)}`, day: punchDialog.day, punch, reason: '' });

const runAction = async () => {
  if (actionDialog.reason.trim().length < 5) {
    toast.add({ severity: 'warn', summary: 'Thiếu lý do', detail: 'Nhập lý do tối thiểu 5 ký tự.', life: 3500 });
    return;
  }
  actionDialog.saving = true;
  try {
    const base = { user_id: props.employee.user_id, date: dateOf(actionDialog.day), reason: actionDialog.reason };
    if (actionDialog.kind === 'add') await attendanceApi.addManualWork({ ...base, in: actionDialog.in || undefined, out: actionDialog.out || undefined });
    else if (actionDialog.kind === 'clear') await attendanceApi.clearDay(base);
    else await attendanceApi.voidPunch(actionDialog.punch.id, actionDialog.reason);
    toast.add({ severity: 'success', summary: 'Đã cập nhật công và ghi nhật ký', life: 3000 });
    actionDialog.visible = false;
    await load();
    if (punchDialog.visible) await loadPunches(punchDialog.day);
    emit('changed');
  } catch (error) {
    const detail = error.data?.errors ? Object.values(error.data.errors).flat()[0] : error.message;
    toast.add({ severity: 'error', summary: 'Không cập nhật được', detail, life: 6000 });
  } finally {
    actionDialog.saving = false;
  }
};
</script>
