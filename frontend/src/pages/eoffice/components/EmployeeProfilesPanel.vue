<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center gap-2">
      <Button label="Đồng bộ nhân viên từ danh sách tài khoản" icon="pi pi-sync" size="small" severity="secondary" outlined :loading="syncing" :disabled="!canManage" @click="sync" />
      <Button label="Ca làm việc" icon="pi pi-clock" size="small" severity="secondary" outlined @click="shiftsVisible = true" />
      <InputText v-model="search" placeholder="Tìm tên, mã nhân viên..." size="small" class="w-60" />
      <span class="ml-auto text-xs text-slate-500">{{ profiles.length }} hồ sơ</span>
    </div>

    <CommonDataTable :value="profiles" :columns="columns" :loading="loading" :rows="25" :search-query="search" :search-fields="['user_name', 'employee_code']"
      empty-icon="pi pi-id-card" empty-title="Chưa có hồ sơ nhân sự" default-sort-field="employee_code" :default-sort-order="1">
      <template #col-employee_code="{ data }"><span class="font-mono text-xs font-semibold text-sky-700">{{ data.employee_code }}</span></template>
      <template #col-work_mode="{ data }"><span class="text-xs">{{ WORK_MODES.find(mode => mode.value === data.work_mode)?.label }}</span></template>
      <template #col-shift="{ data }"><span class="text-xs">{{ data.shift?.name || '—' }}</span></template>
      <template #col-hire_date="{ data }"><span class="text-xs">{{ formatDate(data.hire_date) }}</span></template>
      <template #col-base_salary="{ data }">
        <span class="font-mono text-xs" :class="data.base_salary ? '' : 'text-rose-500'">{{ canSeeSalary ? (data.base_salary ? formatNumber(data.base_salary) : 'Chưa nhập') : '••••••' }}</span>
      </template>
      <template #col-is_active="{ data }"><Tag :value="data.is_active ? 'Đang làm' : 'Ngừng'" :severity="data.is_active ? 'success' : 'secondary'" /></template>
      <Column header="" style="width: 60px" class="text-center">
        <template #body="{ data }">
          <Button v-if="canManage" v-tooltip.top="'Sửa hồ sơ'" icon="pi pi-pencil" text size="small" class="!w-7 !h-7 !p-0" @click="edit(data)" />
        </template>
      </Column>
    </CommonDataTable>

    <Dialog v-model:visible="editor.visible" modal :header="`Hồ sơ ${editor.form.user_name || ''}`" :style="{ width: '640px' }">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Mã nhân viên (khớp mã trên máy chấm công)</label><InputText v-model="editor.form.employee_code" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Hình thức làm việc</label>
          <Select v-model="editor.form.work_mode" :options="WORK_MODES" option-label="label" option-value="value" fluid />
        </div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Ngày vào làm</label><DatePicker v-model="editor.form.hire_date" date-format="dd/mm/yy" show-icon fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Ngày nghỉ việc</label><DatePicker v-model="editor.form.termination_date" date-format="dd/mm/yy" show-icon fluid show-button-bar /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Ca làm việc</label>
          <Select v-model="editor.form.shift_id" :options="shifts" option-label="name" option-value="id" fluid show-clear placeholder="Ca mặc định" />
        </div>
        <div class="flex items-center gap-2 pt-5"><ToggleSwitch v-model="editor.form.is_active" /><span class="font-semibold text-slate-700">Đang làm việc</span></div>
        <template v-if="canSeeSalary">
          <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Lương cơ bản (VND/tháng)</label><InputNumber v-model="editor.form.base_salary" :min="0" fluid /></div>
          <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Phụ cấp cố định (VND/tháng)</label><InputNumber v-model="editor.form.fixed_allowance" :min="0" fluid /></div>
          <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Lương đóng bảo hiểm (để trống = lương cơ bản)</label><InputNumber v-model="editor.form.insurance_salary" :min="0" fluid /></div>
          <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Số người phụ thuộc</label><InputNumber v-model="editor.form.dependents" :min="0" :max="20" fluid /></div>
          <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Ngân hàng</label><InputText v-model="editor.form.bank_name" fluid /></div>
          <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Số tài khoản</label><InputText v-model="editor.form.bank_account" fluid /></div>
        </template>
        <p v-else class="md:col-span-2 m-0 text-slate-400">Bạn không có quyền xem/sửa thông tin lương (cần payroll.manage).</p>
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="editor.visible = false" />
        <Button label="Lưu" size="small" :loading="editor.saving" @click="save" />
      </template>
    </Dialog>

    <Dialog v-model:visible="shiftsVisible" modal header="Ca làm việc" :style="{ width: '720px' }">
      <div class="flex flex-col gap-3 text-xs">
        <div v-for="shift in shifts" :key="shift.id" class="border border-slate-200 rounded-[6px] px-3 py-2 flex items-center gap-3">
          <div class="flex-1">
            <div class="font-semibold text-slate-900">{{ shift.name }} <span class="font-mono text-slate-400">({{ shift.code }})</span>
              <Tag v-if="shift.is_default" value="Mặc định" severity="info" class="ml-1" />
            </div>
            <div class="text-slate-600">{{ formatTime(shift.start_time) }}–{{ formatTime(shift.end_time) }} · nghỉ trưa {{ formatTime(shift.break_start) }}–{{ formatTime(shift.break_end) }} · ân hạn {{ shift.grace_minutes }}' ·
              đủ công từ {{ shift.full_day_minutes }}', nửa công từ {{ shift.half_day_minutes }}' · {{ SATURDAY_MODES.find(mode => mode.value === shift.saturday_mode)?.label }}</div>
          </div>
          <Button v-if="canManage" icon="pi pi-pencil" text size="small" @click="editShift(shift)" />
        </div>
        <Button v-if="canManage" label="Thêm ca" icon="pi pi-plus" size="small" severity="secondary" outlined class="self-start" @click="editShift(null)" />
      </div>
    </Dialog>

    <Dialog v-model:visible="shiftEditor.visible" modal :header="shiftEditor.id ? 'Sửa ca làm việc' : 'Thêm ca làm việc'" :style="{ width: '560px' }">
      <div class="grid grid-cols-2 gap-4 text-xs">
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Mã ca</label><InputText v-model="shiftEditor.form.code" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Tên ca</label><InputText v-model="shiftEditor.form.name" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Giờ vào</label><InputText v-model="shiftEditor.form.start_time" type="time" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Giờ ra</label><InputText v-model="shiftEditor.form.end_time" type="time" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Bắt đầu nghỉ trưa</label><InputText v-model="shiftEditor.form.break_start" type="time" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Hết nghỉ trưa</label><InputText v-model="shiftEditor.form.break_end" type="time" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Ân hạn đi trễ (phút)</label><InputNumber v-model="shiftEditor.form.grace_minutes" :min="0" :max="120" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Thứ 7</label><Select v-model="shiftEditor.form.saturday_mode" :options="SATURDAY_MODES" option-label="label" option-value="value" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Số phút làm để đủ 1 công</label><InputNumber v-model="shiftEditor.form.full_day_minutes" :min="60" :max="720" fluid /></div>
        <div class="flex flex-col gap-1.5"><label class="font-semibold text-slate-700">Số phút làm để được 0,5 công</label><InputNumber v-model="shiftEditor.form.half_day_minutes" :min="30" :max="720" fluid /></div>
        <div class="flex items-center gap-2"><ToggleSwitch v-model="shiftEditor.form.is_default" /><span class="font-semibold text-slate-700">Ca mặc định</span></div>
        <div class="flex items-center gap-2"><ToggleSwitch v-model="shiftEditor.form.is_active" /><span class="font-semibold text-slate-700">Đang sử dụng</span></div>
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="shiftEditor.visible = false" />
        <Button label="Lưu ca" size="small" :loading="shiftEditor.saving" @click="saveShift" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import { attendanceApi } from '../../../api/attendance.api';
import { formatDate, formatNumber } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';
import { storage } from '../../../utils/storage';
import { fromIsoDate, toIsoDate } from '../constants';
import { SATURDAY_MODES, WORK_MODES, formatTime } from '../attendance-constants';

const props = defineProps({ canManage: { type: Boolean, default: false } });
const toast = useToast();
const user = parseJwt(storage.getToken());
const canSeeSalary = hasPermission('payroll.manage', user);

const profiles = ref([]);
const shifts = ref([]);
const loading = ref(false);
const syncing = ref(false);
const search = ref('');
const shiftsVisible = ref(false);
const editor = reactive({ visible: false, saving: false, id: null, form: {} });
const shiftEditor = reactive({ visible: false, saving: false, id: null, form: {} });

const columns = [
  { field: 'employee_code', header: 'Mã NV', width: '100px' },
  { field: 'user_name', header: 'Họ tên' },
  { field: 'work_mode', header: 'Hình thức', width: '130px', sortable: false },
  { field: 'shift', header: 'Ca', width: '140px', sortable: false },
  { field: 'hire_date', header: 'Ngày vào', width: '110px', sortable: false },
  { field: 'base_salary', header: 'Lương cơ bản', width: '130px', align: 'right', sortable: false },
  { field: 'is_active', header: 'Trạng thái', width: '110px', align: 'center', sortable: false }
];

const failure = error => (error.data?.errors ? Object.values(error.data.errors).flat()[0] : error.message);

const load = async () => {
  loading.value = true;
  try {
    const [profileResult, shiftResult] = await Promise.all([attendanceApi.getProfiles(), attendanceApi.getShifts()]);
    profiles.value = profileResult.data || [];
    shifts.value = shiftResult.data || [];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được hồ sơ nhân sự', detail: error.message, life: 4000 });
  } finally {
    loading.value = false;
  }
};

const sync = async () => {
  syncing.value = true;
  try {
    const { data } = await attendanceApi.syncProfiles();
    toast.add({ severity: 'success', summary: `Đã tạo ${data.created} hồ sơ mới`, life: 3000 });
    await load();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không đồng bộ được', detail: failure(error), life: 5000 });
  } finally {
    syncing.value = false;
  }
};

const edit = profile => {
  Object.assign(editor, {
    visible: true, id: profile.id,
    form: {
      ...profile, hire_date: fromIsoDate(profile.hire_date), termination_date: fromIsoDate(profile.termination_date),
      base_salary: profile.base_salary ?? 0, fixed_allowance: profile.fixed_allowance ?? 0, insurance_salary: profile.insurance_salary ?? null
    }
  });
};

const save = async () => {
  editor.saving = true;
  const form = editor.form;
  const body = {
    employee_code: form.employee_code, work_mode: form.work_mode, shift_id: form.shift_id || null, is_active: form.is_active,
    hire_date: toIsoDate(form.hire_date), termination_date: toIsoDate(form.termination_date)
  };
  if (canSeeSalary) {
    Object.assign(body, {
      base_salary: form.base_salary ?? 0, fixed_allowance: form.fixed_allowance ?? 0, insurance_salary: form.insurance_salary || null,
      dependents: form.dependents ?? 0, bank_name: form.bank_name || null, bank_account: form.bank_account || null
    });
  }
  try {
    await attendanceApi.updateProfile(editor.id, body);
    toast.add({ severity: 'success', summary: 'Đã lưu hồ sơ', life: 2500 });
    editor.visible = false;
    await load();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không lưu được hồ sơ', detail: failure(error), life: 6000 });
  } finally {
    editor.saving = false;
  }
};

const editShift = shift => {
  Object.assign(shiftEditor, {
    visible: true, id: shift?.id || null,
    form: shift
      ? { ...shift, start_time: formatTime(shift.start_time), end_time: formatTime(shift.end_time), break_start: shift.break_start ? formatTime(shift.break_start) : '', break_end: shift.break_end ? formatTime(shift.break_end) : '' }
      : { code: '', name: '', start_time: '08:00', end_time: '17:00', break_start: '12:00', break_end: '13:00', grace_minutes: 10, full_day_minutes: 420, half_day_minutes: 240, saturday_mode: 'OFF', is_default: false, is_active: true }
  });
};

const saveShift = async () => {
  shiftEditor.saving = true;
  const form = shiftEditor.form;
  try {
    await attendanceApi.saveShift({ ...form, break_start: form.break_start || null, break_end: form.break_end || null }, shiftEditor.id);
    toast.add({ severity: 'success', summary: 'Đã lưu ca làm việc', life: 2500 });
    shiftEditor.visible = false;
    await load();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không lưu được ca', detail: failure(error), life: 6000 });
  } finally {
    shiftEditor.saving = false;
  }
};

onMounted(load);
</script>
