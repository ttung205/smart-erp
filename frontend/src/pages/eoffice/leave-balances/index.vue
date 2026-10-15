<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Quản Lý Phép Năm" subtitle="Đặt ngày đầu kỳ, số phép đầu kỳ và theo dõi số phép còn lại của từng nhân viên">
      <template #actions>
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined @click="router.push('/eoffice/leave')" />
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="load" />
      </template>
    </PageHeader>

    <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 flex flex-wrap items-center gap-3">
      <InputText v-model="search" placeholder="Tìm theo tên, email, phòng ban..." size="small" class="w-72" />
      <Select v-model="year" :options="yearOptions" size="small" class="w-28" @change="load" />
      <span class="ml-auto text-xs text-slate-500">Tổng: <strong class="font-mono">{{ rows.length }}</strong> nhân viên</span>
    </div>

    <CommonDataTable
      :value="rows"
      :columns="columns"
      :loading="loading"
      :rows="50"
      :rows-per-page-options="[20, 50, 100]"
      :search-query="search"
      :search-fields="['user_name', 'email', 'department_name']"
      default-sort-field="stt"
      :default-sort-order="1"
      empty-icon="pi pi-users"
      empty-title="Chưa có nhân viên"
    >
      <template #col-user_name="{ data }">
        <div class="flex flex-col">
          <span class="text-xs text-slate-900">{{ data.user_name }}</span>
          <span class="text-[11px] text-slate-500">{{ data.department_name || 'Chưa gán phòng ban' }}</span>
        </div>
      </template>
      <template #col-period_start="{ data }">
        <span class="font-mono text-xs">{{ formatDate(data.period_start) }}</span>
      </template>
      <template #col-opening_days="{ data }">
        <div class="text-right font-mono text-xs">{{ formatDays(data.opening_days) }}</div>
      </template>
      <template #col-used_days="{ data }">
        <div class="text-right font-mono text-xs">{{ formatDays(data.used_days) }}</div>
      </template>
      <template #col-pending_days="{ data }">
        <div class="text-right font-mono text-xs" :class="data.pending_days > 0 ? 'text-amber-600' : 'text-slate-400'">{{ formatDays(data.pending_days) }}</div>
      </template>
      <template #col-remaining_days="{ data }">
        <div class="text-right font-mono text-xs font-bold" :class="data.remaining_days > 0 ? 'text-emerald-600' : 'text-rose-600'">{{ formatDays(data.remaining_days) }}</div>
      </template>

      <Column header="Thao tác" class="text-center" style="width: 110px">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1">
            <Button v-tooltip.top="'Xem các đơn nghỉ'" icon="pi pi-eye" text size="small" class="!w-7 !h-7 !p-0" @click="openHistory(data)" />
            <Button v-tooltip.top="'Sửa đầu kỳ'" icon="pi pi-pencil" text size="small" severity="secondary" class="!w-7 !h-7 !p-0" @click="openEdit(data)" />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <Dialog v-model:visible="edit.visible" modal :header="`Cập Nhật Phép Đầu Kỳ · ${edit.row?.user_name || ''}`" :style="{ width: '460px' }">
      <div class="flex flex-col gap-4 text-xs">
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Ngày đầu kỳ <span class="text-rose-500">*</span></label>
          <DatePicker v-model="edit.period_start" date-format="dd/mm/yy" show-icon fluid :min-date="yearStart" :max-date="yearEnd" />
          <small class="text-slate-500">Ngày áp dụng hệ thống, hoặc ngày vào làm với nhân viên mới. Chỉ đơn phép năm từ ngày này trở đi được trừ vào quỹ.</small>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Phép đầu kỳ (ngày) <span class="text-rose-500">*</span></label>
          <div class="flex gap-2">
            <InputNumber v-model="edit.opening_days" :min="0" :max="365" :step="0.5" :min-fraction-digits="0" :max-fraction-digits="1" locale="vi-VN" show-buttons fluid class="flex-1" />
            <Button label="Tính theo tỷ lệ" icon="pi pi-calculator" size="small" severity="secondary" outlined :loading="edit.calculating" @click="applyProRata" />
          </div>
          <small class="text-slate-500">Nhân viên mới: 12 ngày ÷ 12 × số tháng làm việc còn lại trong năm (vào làm trước hoặc đúng ngày 15 thì tính cả tháng đó).</small>
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="font-semibold text-slate-700">Ghi chú</label>
          <InputText v-model="edit.note" fluid placeholder="Ví dụ: Số dư chuyển từ bảng chấm công cũ" />
        </div>
      </div>
      <template #footer>
        <Button label="Hủy bỏ" text severity="secondary" size="small" @click="edit.visible = false" />
        <Button label="Lưu" icon="pi pi-check" size="small" :loading="edit.saving" @click="saveEdit" />
      </template>
    </Dialog>

    <Dialog v-model:visible="history.visible" modal :header="`Đơn Nghỉ Năm ${year} · ${history.row?.user_name || ''}`" :style="{ width: '760px' }">
      <CommonDataTable
        :value="history.requests"
        :columns="historyColumns"
        :loading="history.loading"
        :paginator="false"
        empty-icon="pi pi-calendar"
        empty-title="Chưa có đơn nghỉ nào trong năm"
      >
        <template #col-code="{ data }"><span class="font-mono text-xs font-semibold text-sky-700">{{ data.code }}</span></template>
        <template #col-leave_type="{ data }">
          <span class="text-xs">{{ data.leave_type?.name }}</span>
          <span v-if="data.leave_type?.deducts_balance && data.start_date < history.row?.period_start" class="block text-[11px] text-slate-400">Trước đầu kỳ, không trừ quỹ</span>
        </template>
        <template #col-period="{ data }"><span class="text-xs">{{ formatPeriod(data) }}</span></template>
        <template #col-total_days="{ data }"><div class="text-right font-mono text-xs">{{ formatDays(data.total_days) }}</div></template>
        <template #col-status="{ data }"><Tag :value="LEAVE_STATUS[data.status]?.label" :severity="LEAVE_STATUS[data.status]?.severity" /></template>
      </CommonDataTable>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import { eofficeApi } from '../../../api/eoffice.api';
import { formatDate } from '../../../utils/format';
import { LEAVE_STATUS, SESSION_LABELS, formatDays, fromIsoDate, toIsoDate } from '../constants';

const router = useRouter();
const toast = useToast();
const currentYear = new Date().getFullYear();

const rows = ref([]);
const loading = ref(false);
const search = ref('');
const year = ref(currentYear);
const yearOptions = [currentYear - 1, currentYear, currentYear + 1];
const yearStart = computed(() => new Date(year.value, 0, 1));
const yearEnd = computed(() => new Date(year.value, 11, 31));
const edit = reactive({ visible: false, row: null, period_start: null, opening_days: 0, note: '', saving: false, calculating: false });
const history = reactive({ visible: false, row: null, requests: [], loading: false });

const columns = [
  { field: 'stt', header: 'STT', width: '64px' },
  { field: 'user_name', header: 'Tên nhân viên' },
  { field: 'period_start', header: 'Ngày đầu kỳ', width: '130px' },
  { field: 'opening_days', header: 'Phép đầu kỳ', width: '120px', align: 'right' },
  { field: 'used_days', header: 'Đã nghỉ (đã duyệt)', width: '150px', align: 'right' },
  { field: 'pending_days', header: 'Chờ duyệt', width: '110px', align: 'right' },
  { field: 'remaining_days', header: 'Phép còn lại', width: '120px', align: 'right' }
];
const historyColumns = [
  { field: 'code', header: 'Mã đơn', width: '130px' },
  { field: 'leave_type', header: 'Loại nghỉ', sortable: false },
  { field: 'period', header: 'Thời gian', sortable: false },
  { field: 'total_days', header: 'Số ngày', width: '80px', align: 'right' },
  { field: 'status', header: 'Trạng thái', width: '110px', align: 'center' }
];

const formatPeriod = item => {
  const start = `${formatDate(item.start_date)}${item.start_session !== 'FULL' ? ` (${SESSION_LABELS[item.start_session]})` : ''}`;
  if (item.start_date === item.end_date) return start;
  return `${start} → ${formatDate(item.end_date)}${item.end_session !== 'FULL' ? ` (${SESSION_LABELS[item.end_session]})` : ''}`;
};

const load = async () => {
  loading.value = true;
  try {
    const data = (await eofficeApi.getLeaveBalances(year.value)).data || [];
    rows.value = data.map((row, index) => ({ ...row, stt: index + 1 }));
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được quỹ phép', detail: error.message, life: 5000 });
  } finally {
    loading.value = false;
  }
};

const openEdit = row => Object.assign(edit, {
  visible: true, row, period_start: fromIsoDate(row.period_start), opening_days: row.opening_days, note: row.note || '', saving: false
});

const applyProRata = async () => {
  if (!edit.period_start) return;
  edit.calculating = true;
  try {
    edit.opening_days = (await eofficeApi.getProRataLeave(toIsoDate(edit.period_start))).data.opening_days;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tính được phép theo tỷ lệ', detail: error.message, life: 4000 });
  } finally {
    edit.calculating = false;
  }
};

const saveEdit = async () => {
  if (!edit.period_start || edit.opening_days === null) {
    toast.add({ severity: 'warn', summary: 'Thiếu thông tin', detail: 'Vui lòng nhập ngày đầu kỳ và số phép đầu kỳ.', life: 3500 });
    return;
  }
  edit.saving = true;
  try {
    const saved = (await eofficeApi.updateLeaveBalance(edit.row.user_id, {
      year: year.value, period_start: toIsoDate(edit.period_start), opening_days: edit.opening_days, note: edit.note
    })).data;
    Object.assign(edit.row, saved);
    toast.add({ severity: 'success', summary: 'Đã cập nhật phép đầu kỳ', detail: edit.row.user_name, life: 3000 });
    edit.visible = false;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không lưu được', detail: error.message, life: 5000 });
  } finally {
    edit.saving = false;
  }
};

const openHistory = async row => {
  Object.assign(history, { visible: true, row, requests: [], loading: true });
  try {
    history.requests = (await eofficeApi.getUserLeaveRequests(row.user_id, year.value)).data || [];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được đơn nghỉ', detail: error.message, life: 4000 });
  } finally {
    history.loading = false;
  }
};

onMounted(load);
</script>
