<template>
  <div class="space-y-4 pb-8">
    <PageHeader title="Bảng Lương" subtitle="Khóa công, tính lương, duyệt và chi lương theo tháng">
      <template #actions>
        <Button label="Làm mới" icon="pi pi-refresh" size="small" severity="secondary" outlined :loading="loading" @click="loadAll" />
        <Button v-if="canManage" label="Tạo kỳ lương" icon="pi pi-plus" size="small" @click="createDialog.visible = true" />
      </template>
    </PageHeader>

    <div class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
      <aside class="bg-white border border-slate-200 rounded-[6px] self-start">
        <div class="px-4 py-3 border-b border-slate-200 text-sm font-semibold text-slate-900">Kỳ lương</div>
        <div v-if="!periods.length" class="p-4 text-xs text-slate-400">Chưa có kỳ lương nào.</div>
        <button v-for="item in periods" :key="item.id" type="button"
          class="w-full text-left px-4 py-3 border-t border-slate-100 flex items-center justify-between gap-2 hover:bg-slate-50 cursor-pointer bg-transparent"
          :class="item.id === selectedId ? '!bg-sky-50' : ''" @click="select(item.id)">
          <span class="font-mono text-sm font-semibold text-slate-900">{{ pad(item.month) }}/{{ item.year }}</span>
          <Tag :value="PAYROLL_STATUS[item.status]?.label" :severity="PAYROLL_STATUS[item.status]?.severity" />
        </button>
      </aside>

      <main v-if="period" class="space-y-4 min-w-0">
        <div class="bg-white border border-slate-200 rounded-[6px] px-4 py-3 flex flex-wrap items-center gap-2">
          <div class="mr-auto">
            <div class="text-sm font-bold text-slate-900 flex items-center gap-2">
              Bảng lương tháng {{ pad(period.month) }}/{{ period.year }}
              <Tag :value="PAYROLL_STATUS[period.status]?.label" :severity="PAYROLL_STATUS[period.status]?.severity" />
            </div>
            <div class="text-[11px] text-slate-500">
              Công {{ period.attendance_locked_at ? `đã khóa lúc ${formatDateTime(period.attendance_locked_at)}` : 'chưa khóa' }}
              <template v-if="period.calculated_at"> · tính lương lúc {{ formatDateTime(period.calculated_at) }}</template>
            </div>
          </div>

          <template v-if="canManage && open">
            <Button v-if="!period.attendance_locked_at" label="Khóa công" icon="pi pi-lock" size="small" severity="secondary" :loading="busy === 'lock'" @click="lock" />
            <Button v-else label="Mở khóa công" icon="pi pi-lock-open" size="small" severity="secondary" outlined :loading="busy === 'unlock'" @click="askUnlock" />
            <Button label="Thưởng / khấu trừ" icon="pi pi-plus-circle" size="small" severity="secondary" outlined @click="openAdjustments" />
            <Button :label="period.status === 'DRAFT' && !slips.length ? 'Tính lương' : 'Tính lại'" icon="pi pi-calculator" size="small" :disabled="!period.attendance_locked_at" :loading="busy === 'calc'" @click="calculate" />
          </template>
          <Button v-if="canManage && period.status === 'CALCULATED'" label="Gửi duyệt" icon="pi pi-send" size="small" severity="success" :loading="busy === 'submit'" @click="submit" />
          <template v-if="canApprove && period.status === 'PENDING_APPROVAL'">
            <Button label="Trả lại" icon="pi pi-reply" size="small" severity="secondary" outlined @click="decide('return')" />
            <Button label="Duyệt bảng lương" icon="pi pi-check" size="small" severity="success" @click="decide('approve')" />
          </template>
          <Button v-if="canPay && period.status === 'APPROVED'" label="Chi lương" icon="pi pi-wallet" size="small" severity="success" @click="openPay" />
          <Button v-if="slips.length" label="Xuất Excel" icon="pi pi-file-excel" size="small" severity="secondary" outlined @click="exportExcel" />
        </div>

        <div v-if="period.status === 'RETURNED' && period.decision_comment" class="bg-rose-50 border border-rose-200 rounded-[6px] px-4 py-3 text-xs text-rose-800 flex gap-2">
          <i class="pi pi-reply mt-0.5"></i><div><strong>Bị trả lại:</strong> {{ period.decision_comment }}</div>
        </div>
        <div v-if="period.status === 'DRAFT' && slips.length" class="bg-amber-50 border border-amber-200 rounded-[6px] px-4 py-3 text-xs text-amber-900 flex gap-2">
          <i class="pi pi-exclamation-triangle mt-0.5"></i><div>Dữ liệu đầu vào đã thay đổi sau lần tính trước (điều chỉnh hoặc mở khóa công). Hãy khóa công và tính lại trước khi gửi duyệt.</div>
        </div>
        <div v-if="period.finance_status === 'FAILED' || (period.finance_status === 'JOURNAL_POSTED' && period.status !== 'PAID')" class="bg-rose-50 border border-rose-200 rounded-[6px] px-4 py-3 text-xs text-rose-800 flex gap-2">
          <i class="pi pi-times-circle mt-0.5"></i>
          <div><strong>Hạch toán chưa hoàn tất:</strong> {{ period.finance_error || 'Bút toán chi phí đã ghi, phiếu chi chưa ghi sổ.' }} Bấm "Chi lương" để thử lại (các bước đã ghi sẽ không bị ghi trùng).</div>
        </div>
        <div v-if="period.status === 'PAID'" class="bg-emerald-50 border border-emerald-200 rounded-[6px] px-4 py-3 text-xs text-emerald-800 flex gap-2">
          <i class="pi pi-check-circle mt-0.5"></i>
          <div>Đã chi lương lúc {{ formatDateTime(period.paid_at) }} · phiếu chi <strong class="font-mono">{{ period.finance_reference }}</strong> đã ghi sổ ở phân hệ Tài chính.</div>
        </div>
        <div v-if="warnings.length" class="bg-amber-50 border border-amber-200 rounded-[6px] px-4 py-3 text-xs text-amber-900">
          <div class="font-semibold mb-1">Cần lưu ý khi tính lương:</div>
          <div v-for="warning in warnings" :key="warning">• {{ warning }}</div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
          <div v-for="tile in tiles" :key="tile.label" class="bg-white border border-slate-200 rounded-[6px] px-4 py-3">
            <div class="text-[11px] text-slate-500">{{ tile.label }}</div>
            <div class="text-lg font-bold font-mono tabular-nums" :class="tile.color">{{ tile.value }}</div>
          </div>
        </div>

        <SelectButton v-model="view" :options="views" option-label="label" option-value="value" :allow-empty="false" size="small" />

        <template v-if="view === 'slips'">
          <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 flex items-center gap-3">
            <InputText v-model="search" placeholder="Tìm tên, mã nhân viên..." size="small" class="w-60" />
            <span class="ml-auto text-xs text-slate-500">{{ slips.length }} phiếu lương</span>
          </div>
          <CommonDataTable :value="slips" :columns="slipColumns" :loading="detailLoading" :rows="25" :search-query="search" :search-fields="['user_name', 'employee_code']"
            empty-icon="pi pi-wallet" empty-title="Chưa tính lương cho kỳ này" default-sort-field="employee_code" :default-sort-order="1" row-hover @row-click="event => openSlip(event.data)">
            <template #col-employee_code="{ data }"><span class="font-mono text-xs font-semibold text-sky-700">{{ data.employee_code }}</span></template>
            <template #col-paid_days="{ data }"><div class="text-right font-mono text-xs">{{ formatDays(data.paid_days) }}/{{ formatDays(data.standard_days) }}</div></template>
            <template #col-ot_amount="{ data }"><div class="text-right font-mono text-xs">{{ formatNumber(data.ot_amount) }}</div></template>
            <template #col-gross_income="{ data }"><div class="text-right font-mono text-xs">{{ formatNumber(data.gross_income) }}</div></template>
            <template #col-insurance_amount="{ data }"><div class="text-right font-mono text-xs">{{ formatNumber(data.insurance_amount) }}</div></template>
            <template #col-pit_amount="{ data }"><div class="text-right font-mono text-xs">{{ formatNumber(data.pit_amount) }}</div></template>
            <template #col-penalty_amount="{ data }"><div class="text-right font-mono text-xs" :class="data.penalty_amount ? 'text-rose-600' : 'text-slate-400'">{{ formatNumber(data.penalty_amount) }}</div></template>
            <template #col-net_pay="{ data }"><div class="text-right font-mono text-xs font-bold text-emerald-700">{{ formatNumber(data.net_pay) }}</div></template>
          </CommonDataTable>
        </template>

        <section v-else-if="view === 'history'" class="bg-white border border-slate-200 rounded-[6px]">
          <div class="px-5 py-3 border-b border-slate-200 text-sm font-semibold text-slate-900">Lịch sử xử lý</div>
          <ol class="p-4 flex flex-col gap-3 text-xs m-0 list-none">
            <li v-if="!period.approval_histories?.length" class="text-slate-400">Chưa có lịch sử.</li>
            <li v-for="item in period.approval_histories" :key="item.id" class="border-l-2 border-slate-200 pl-3">
              <div class="font-semibold text-slate-800">{{ PAYROLL_HISTORY_LABELS[item.action] || item.action }} <span class="font-normal text-slate-500">· {{ item.actor_name }}</span></div>
              <div class="text-[11px] text-slate-400">{{ formatDateTime(item.created_at) }}</div>
              <div v-if="item.comment" class="text-slate-600 mt-0.5">{{ item.comment }}</div>
            </li>
          </ol>
        </section>

        <section v-else class="bg-white border border-slate-200 rounded-[6px]">
          <div class="px-5 py-3 border-b border-slate-200 text-sm font-semibold text-slate-900 flex items-center gap-2">
            Quy tắc tính lương
            <span class="text-[11px] font-normal text-amber-700">Các giá trị mặc định là giả định ban đầu, kế toán cần đối chiếu với quy định hiện hành trước khi dùng.</span>
          </div>
          <div class="p-4 flex flex-col gap-4 text-xs">
            <div v-for="rule in rules" :key="rule.rule_key" class="border border-slate-200 rounded-[6px] p-3">
              <div class="flex items-center justify-between gap-3 mb-1">
                <div><strong class="font-mono text-slate-900">{{ rule.rule_key }}</strong> <span class="text-slate-500">· {{ rule.description }}</span></div>
                <Button v-if="canManage" label="Lưu" size="small" severity="secondary" outlined :loading="busy === 'rule-' + rule.rule_key" @click="saveRule(rule)" />
              </div>
              <Textarea v-model="ruleDrafts[rule.rule_key]" rows="4" auto-resize fluid class="!font-mono !text-xs" :disabled="!canManage" />
            </div>
            <p class="m-0 text-slate-400">Thay đổi quy tắc chỉ áp dụng cho lần tính lương tiếp theo; bảng lương đã gửi duyệt không bị ảnh hưởng.</p>
          </div>
        </section>
      </main>
      <main v-else class="bg-white border border-slate-200 rounded-[6px] p-8 text-center text-xs text-slate-400">Chọn một kỳ lương ở bên trái hoặc tạo kỳ mới.</main>
    </div>

    <Dialog v-model:visible="createDialog.visible" modal header="Tạo kỳ lương" :style="{ width: '360px' }">
      <div class="flex flex-col gap-2 text-xs">
        <label class="font-semibold text-slate-700">Tháng</label>
        <DatePicker v-model="createDialog.date" view="month" date-format="mm/yy" show-icon fluid />
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="createDialog.visible = false" />
        <Button label="Tạo" size="small" :loading="busy === 'create'" @click="createPeriod" />
      </template>
    </Dialog>

    <Dialog v-model:visible="reasonDialog.visible" modal :header="reasonDialog.title" :style="{ width: '460px' }">
      <div class="flex flex-col gap-2 text-xs">
        <p v-if="reasonDialog.hint" class="m-0 text-slate-600">{{ reasonDialog.hint }}</p>
        <label class="font-semibold text-slate-700">{{ reasonDialog.label }}<span v-if="reasonDialog.required" class="text-rose-500"> *</span></label>
        <Textarea v-model="reasonDialog.text" rows="3" auto-resize fluid />
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="reasonDialog.visible = false" />
        <Button :label="reasonDialog.button" :severity="reasonDialog.severity" size="small" :loading="busy === 'reason'" @click="confirmReason" />
      </template>
    </Dialog>

    <Dialog v-model:visible="payDialog.visible" modal header="Chi lương" :style="{ width: '480px' }">
      <div class="flex flex-col gap-3 text-xs">
        <div class="bg-slate-50 border border-slate-200 rounded-[6px] px-3 py-2">
          Tổng thực nhận kỳ {{ pad(period?.month) }}/{{ period?.year }}: <strong class="font-mono text-emerald-700 text-sm">{{ formatCurrency(period?.total_net) }}</strong>
        </div>
        <label class="font-semibold text-slate-700">Chi từ tài khoản ngân hàng <span class="text-rose-500">*</span></label>
        <Select v-model="payDialog.bankAccountId" :options="payDialog.accounts" option-label="label" option-value="id" fluid placeholder="Chọn tài khoản..." :loading="payDialog.loading" />
        <p class="m-0 text-slate-500">Hệ thống sẽ ghi nhận chi phí lương (Nợ 642 / Có 334), các khoản giữ lại (bảo hiểm, thuế TNCN, phạt) và lập phiếu chi ngân hàng. Tài khoản của bạn cần quyền ghi sổ ở phân hệ Tài chính.</p>
      </div>
      <template #footer>
        <Button label="Đóng" text severity="secondary" size="small" @click="payDialog.visible = false" />
        <Button label="Xác nhận chi lương" severity="success" size="small" :disabled="!payDialog.bankAccountId" :loading="busy === 'pay'" @click="pay" />
      </template>
    </Dialog>

    <Dialog v-model:visible="adjustDialog.visible" modal header="Thưởng / khấu trừ thủ công" :style="{ width: '720px' }">
      <div class="flex flex-col gap-3 text-xs">
        <div v-if="canEditAdjustments" class="grid grid-cols-1 md:grid-cols-[1.4fr_1fr_1fr_auto] gap-2 items-end">
          <div class="flex flex-col gap-1"><label class="font-semibold text-slate-700">Nhân viên</label>
            <Select v-model="adjustDialog.form.user_id" :options="slips.length ? slips : []" option-label="user_name" option-value="user_id" filter fluid placeholder="Chọn nhân viên..." />
          </div>
          <div class="flex flex-col gap-1"><label class="font-semibold text-slate-700">Loại</label>
            <Select v-model="adjustDialog.form.type" :options="adjustmentTypes" option-label="label" option-value="value" fluid />
          </div>
          <div class="flex flex-col gap-1"><label class="font-semibold text-slate-700">Số tiền</label><InputNumber v-model="adjustDialog.form.amount" :min="0" fluid /></div>
          <Button label="Thêm" icon="pi pi-plus" size="small" :loading="busy === 'adjust'" @click="addAdjustment" />
          <div class="flex flex-col gap-1 md:col-span-3"><label class="font-semibold text-slate-700">Lý do <span class="text-rose-500">*</span></label><InputText v-model="adjustDialog.form.reason" fluid /></div>
          <div v-if="adjustDialog.form.type === 'BONUS'" class="flex items-center gap-2 pb-2"><ToggleSwitch v-model="adjustDialog.form.taxable" /><span>Chịu thuế</span></div>
        </div>
        <p v-if="!slips.length" class="m-0 text-amber-700">Cần tính lương một lần để có danh sách nhân viên, sau đó thêm điều chỉnh và tính lại.</p>
        <div class="border border-slate-200 rounded-[6px] overflow-hidden">
          <div v-if="!adjustDialog.items.length" class="p-3 text-slate-400">Chưa có điều chỉnh nào.</div>
          <div v-for="item in adjustDialog.items" :key="item.id" class="px-3 py-2 border-t first:border-t-0 border-slate-100 flex items-center gap-3">
            <Tag :value="item.type === 'BONUS' ? 'Thưởng' : 'Khấu trừ'" :severity="item.type === 'BONUS' ? 'success' : 'danger'" />
            <span class="w-44 truncate">{{ nameOfUser(item.user_id) }}</span>
            <span class="font-mono font-semibold">{{ formatNumber(item.amount) }}</span>
            <span class="flex-1 text-slate-600 truncate">{{ item.reason }}<span v-if="item.type === 'BONUS' && !item.taxable" class="text-slate-400"> · không chịu thuế</span></span>
            <Button v-if="canEditAdjustments" icon="pi pi-trash" text size="small" severity="danger" @click="removeAdjustment(item)" />
          </div>
        </div>
      </div>
    </Dialog>

    <PayrollSlipDialog v-model:visible="slipDialog.visible" :slip="slipDialog.slip" :period-label="period ? `${pad(period.month)}/${period.year}` : ''" />
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import * as XLSX from 'xlsx';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import ToggleSwitch from 'primevue/toggleswitch';
import CommonDataTable from '../../../components/common/CommonDataTable.vue';
import PageHeader from '../../../components/common/PageHeader.vue';
import PayrollSlipDialog from '../components/PayrollSlipDialog.vue';
import { payrollApi } from '../../../api/attendance.api';
import { formatCurrency, formatDateTime, formatNumber } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { hasPermission } from '../../../utils/rbac';
import { storage } from '../../../utils/storage';
import { formatDays } from '../constants';
import { PAYROLL_HISTORY_LABELS, PAYROLL_STATUS } from '../attendance-constants';

const toast = useToast();
const user = parseJwt(storage.getToken());
const canManage = hasPermission('payroll.manage', user);
const canApprove = hasPermission('payroll.approve', user);
const canPay = hasPermission('payroll.pay', user);

const periods = ref([]);
const period = ref(null);
const slips = ref([]);
const rules = ref([]);
const ruleDrafts = reactive({});
const warnings = ref([]);
const selectedId = ref(null);
const loading = ref(false);
const detailLoading = ref(false);
const busy = ref('');
const search = ref('');
const view = ref('slips');
const createDialog = reactive({ visible: false, date: new Date() });
const reasonDialog = reactive({ visible: false, kind: '', title: '', label: '', hint: '', text: '', required: true, button: 'Xác nhận', severity: 'primary' });
const payDialog = reactive({ visible: false, loading: false, accounts: [], bankAccountId: null });
const adjustDialog = reactive({ visible: false, items: [], form: { user_id: null, type: 'BONUS', amount: null, reason: '', taxable: true } });
const slipDialog = reactive({ visible: false, slip: null });

const views = [
  { label: 'Phiếu lương', value: 'slips' },
  { label: 'Lịch sử', value: 'history' },
  { label: 'Quy tắc tính lương', value: 'rules' }
];
const adjustmentTypes = [{ label: 'Thưởng', value: 'BONUS' }, { label: 'Khấu trừ', value: 'DEDUCTION' }];
const slipColumns = [
  { field: 'employee_code', header: 'Mã NV', width: '95px' },
  { field: 'user_name', header: 'Họ tên' },
  { field: 'paid_days', header: 'Công', width: '90px', align: 'right', sortable: false },
  { field: 'ot_amount', header: 'OT', width: '100px', align: 'right', sortable: false },
  { field: 'gross_income', header: 'Thu nhập', width: '120px', align: 'right', sortable: false },
  { field: 'insurance_amount', header: 'Bảo hiểm', width: '105px', align: 'right', sortable: false },
  { field: 'pit_amount', header: 'Thuế TNCN', width: '105px', align: 'right', sortable: false },
  { field: 'penalty_amount', header: 'Phạt', width: '85px', align: 'right', sortable: false },
  { field: 'net_pay', header: 'Thực nhận', width: '125px', align: 'right', sortable: false }
];

const pad = value => String(value ?? '').padStart(2, '0');
const open = computed(() => ['DRAFT', 'CALCULATED', 'RETURNED'].includes(period.value?.status));
const canEditAdjustments = computed(() => canManage && open.value);
const tiles = computed(() => [
  { label: 'Số nhân viên', value: period.value?.employee_count ?? 0, color: 'text-slate-900' },
  { label: 'Công chuẩn', value: formatDays(period.value?.standard_days), color: 'text-slate-900' },
  { label: 'Tổng thu nhập', value: formatNumber(period.value?.total_gross), color: 'text-slate-900' },
  { label: 'Bảo hiểm + thuế TNCN', value: formatNumber(Number(period.value?.total_insurance || 0) + Number(period.value?.total_pit || 0)), color: 'text-amber-600' },
  { label: 'Tổng thực nhận', value: formatNumber(period.value?.total_net), color: 'text-emerald-600' }
]);

const failure = error => (error.data?.errors ? Object.values(error.data.errors).flat()[0] : error.message);
const nameOfUser = userId => slips.value.find(slip => Number(slip.user_id) === Number(userId))?.user_name || `#${userId}`;

const loadPeriods = async () => {
  periods.value = (await payrollApi.getPeriods()).data || [];
};
const loadRules = async () => {
  rules.value = (await payrollApi.getRules()).data || [];
  rules.value.forEach(rule => { ruleDrafts[rule.rule_key] = JSON.stringify(rule.value, null, 2); });
};
const loadPeriod = async id => {
  detailLoading.value = true;
  try {
    const result = await payrollApi.getPeriod(id);
    period.value = result.data;
    slips.value = result.slips || [];
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được kỳ lương', detail: failure(error), life: 5000 });
  } finally {
    detailLoading.value = false;
  }
};

const loadAll = async () => {
  loading.value = true;
  try {
    await Promise.all([loadPeriods(), loadRules()]);
    const id = selectedId.value || periods.value[0]?.id;
    if (id) await select(id, true);
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được dữ liệu lương', detail: failure(error), life: 5000 });
  } finally {
    loading.value = false;
  }
};

const select = async (id, keepWarnings = false) => {
  selectedId.value = id;
  if (!keepWarnings) warnings.value = [];
  await loadPeriod(id);
};

const run = async (name, action, success) => {
  busy.value = name;
  try {
    const result = await action();
    if (success) toast.add({ severity: 'success', summary: success, life: 3000 });
    await Promise.all([loadPeriods(), loadPeriod(selectedId.value)]);
    return result;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Thao tác thất bại', detail: failure(error), life: 7000 });
    await Promise.all([loadPeriods(), selectedId.value ? loadPeriod(selectedId.value) : null]).catch(() => {});
    return null;
  } finally {
    busy.value = '';
  }
};

const createPeriod = async () => {
  busy.value = 'create';
  try {
    const { data } = await payrollApi.createPeriod(createDialog.date.getFullYear(), createDialog.date.getMonth() + 1);
    createDialog.visible = false;
    await loadPeriods();
    await select(data.id);
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tạo được kỳ lương', detail: failure(error), life: 5000 });
  } finally {
    busy.value = '';
  }
};

const lock = async () => {
  const result = await run('lock', () => payrollApi.lockAttendance(period.value.id), 'Đã khóa công');
  const summary = result?.summary;
  if (summary?.missing_punch_days) {
    warnings.value = [`Còn ${summary.missing_punch_days} ngày thiếu chấm công chưa được bổ sung; các ngày này đang tính 0 công. Có thể mở khóa, bổ sung rồi khóa lại.`];
  }
};
const calculate = async () => {
  const result = await run('calc', () => payrollApi.calculate(period.value.id), 'Đã tính lương');
  warnings.value = result?.warnings || [];
};
const submit = () => run('submit', () => payrollApi.submit(period.value.id), 'Đã gửi bảng lương đi duyệt');

const askReason = options => Object.assign(reasonDialog, { visible: true, text: '', required: true, hint: '', severity: 'primary', ...options });
const askUnlock = () => askReason({ kind: 'unlock', title: 'Mở khóa công', label: 'Lý do mở khóa', hint: 'Phiếu lương đã tính sẽ bị xóa và phải tính lại sau khi khóa công lần nữa.', button: 'Mở khóa', severity: 'danger' });
const decide = kind => askReason(kind === 'approve'
  ? { kind, title: 'Duyệt bảng lương', label: 'Ý kiến', required: false, button: 'Duyệt', severity: 'success' }
  : { kind, title: 'Trả lại bảng lương', label: 'Lý do trả lại', button: 'Trả lại', severity: 'danger' });

const confirmReason = async () => {
  const text = reasonDialog.text.trim();
  if (reasonDialog.required && text.length < 3) {
    toast.add({ severity: 'warn', summary: 'Thiếu lý do', detail: 'Vui lòng nhập lý do.', life: 3000 });
    return;
  }
  const id = period.value.id;
  const actions = {
    unlock: [() => payrollApi.unlockAttendance(id, text), 'Đã mở khóa công'],
    approve: [() => payrollApi.approve(id, text || undefined), 'Đã duyệt bảng lương'],
    return: [() => payrollApi.returnPeriod(id, text), 'Đã trả lại bảng lương']
  };
  const [action, success] = actions[reasonDialog.kind];
  busy.value = 'reason';
  try {
    await action();
    toast.add({ severity: 'success', summary: success, life: 3000 });
    reasonDialog.visible = false;
    warnings.value = [];
    await Promise.all([loadPeriods(), loadPeriod(id)]);
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Thao tác thất bại', detail: failure(error), life: 7000 });
  } finally {
    busy.value = '';
  }
};

const openPay = async () => {
  Object.assign(payDialog, { visible: true, loading: true, accounts: [], bankAccountId: null });
  try {
    const { data } = await payrollApi.getBankAccounts();
    payDialog.accounts = (data?.bank_accounts || []).map(account => ({ id: account.id, label: `${account.code} · ${account.name || account.bank_name || ''}`.trim() }));
    payDialog.bankAccountId = payDialog.accounts[0]?.id || null;
  } catch {
    toast.add({ severity: 'warn', summary: 'Không lấy được danh sách tài khoản ngân hàng', detail: 'Cần quyền xem phân hệ Tài chính.', life: 5000 });
  } finally {
    payDialog.loading = false;
  }
};
const pay = async () => {
  const result = await run('pay', () => payrollApi.pay(period.value.id, payDialog.bankAccountId), 'Đã chi lương và hạch toán');
  if (result) payDialog.visible = false;
};

const openAdjustments = async () => {
  adjustDialog.visible = true;
  adjustDialog.items = (await payrollApi.getAdjustments(period.value.id)).data || [];
};
const addAdjustment = async () => {
  const form = adjustDialog.form;
  if (!form.user_id || !form.amount || form.reason.trim().length < 3) {
    toast.add({ severity: 'warn', summary: 'Chưa đủ thông tin', detail: 'Chọn nhân viên, nhập số tiền và lý do.', life: 3500 });
    return;
  }
  busy.value = 'adjust';
  try {
    await payrollApi.addAdjustment(period.value.id, { ...form, taxable: form.type === 'BONUS' ? form.taxable : undefined });
    Object.assign(form, { amount: null, reason: '' });
    adjustDialog.items = (await payrollApi.getAdjustments(period.value.id)).data || [];
    await Promise.all([loadPeriods(), loadPeriod(period.value.id)]);
    toast.add({ severity: 'success', summary: 'Đã thêm điều chỉnh, cần tính lại bảng lương', life: 3500 });
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không thêm được điều chỉnh', detail: failure(error), life: 6000 });
  } finally {
    busy.value = '';
  }
};
const removeAdjustment = async item => {
  try {
    await payrollApi.removeAdjustment(item.id);
    adjustDialog.items = (await payrollApi.getAdjustments(period.value.id)).data || [];
    await Promise.all([loadPeriods(), loadPeriod(period.value.id)]);
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không xóa được điều chỉnh', detail: failure(error), life: 6000 });
  }
};

const saveRule = async rule => {
  let value;
  try {
    value = JSON.parse(ruleDrafts[rule.rule_key]);
  } catch {
    toast.add({ severity: 'warn', summary: 'JSON không hợp lệ', detail: `Quy tắc ${rule.rule_key} chưa đúng định dạng.`, life: 4000 });
    return;
  }
  busy.value = `rule-${rule.rule_key}`;
  try {
    await payrollApi.updateRule(rule.rule_key, value);
    toast.add({ severity: 'success', summary: `Đã lưu quy tắc ${rule.rule_key}`, detail: 'Áp dụng từ lần tính lương sau.', life: 3500 });
    await loadRules();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không lưu được quy tắc', detail: failure(error), life: 6000 });
  } finally {
    busy.value = '';
  }
};

const openSlip = async slip => {
  try {
    slipDialog.slip = (await payrollApi.getSlip(slip.id)).data;
    slipDialog.visible = true;
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không mở được phiếu lương', detail: failure(error), life: 5000 });
  }
};

const exportExcel = () => {
  const header = ['Mã NV', 'Họ tên', 'Lương cơ bản', 'Công chuẩn', 'Công hưởng lương', 'Lương theo công', 'Phụ cấp', 'Giờ OT', 'Tiền OT', 'Thưởng', 'Tổng thu nhập',
    'Bảo hiểm', 'Thu nhập chịu thuế', 'Thuế TNCN', 'Phạt', 'Khấu trừ khác', 'Thực nhận'];
  const body = slips.value.map(slip => [
    slip.employee_code, slip.user_name, slip.base_salary, slip.standard_days, slip.paid_days, slip.salary_amount, slip.allowance_amount,
    Number(slip.ot_hours_weekday) + Number(slip.ot_hours_weekend) + Number(slip.ot_hours_holiday), slip.ot_amount, slip.bonus_amount, slip.gross_income,
    slip.insurance_amount, slip.taxable_income, slip.pit_amount, slip.penalty_amount, slip.deduction_amount, slip.net_pay
  ]);
  const sheet = XLSX.utils.aoa_to_sheet([header, ...body]);
  const book = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(book, sheet, `Luong ${pad(period.value.month)}-${period.value.year}`);
  XLSX.writeFile(book, `bang-luong-${period.value.year}-${pad(period.value.month)}.xlsx`);
};

onMounted(loadAll);
</script>
