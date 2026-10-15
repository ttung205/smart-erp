<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="pageTitle">
      <template #title>
        {{ pageTitle }}
        <Tag v-if="request" :value="LEAVE_STATUS[request.status]?.label" :severity="LEAVE_STATUS[request.status]?.severity" />
      </template>
      <template #actions>
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined @click="router.push('/eoffice/leave')" />
        <template v-if="isOwner && editable">
          <Button label="Lưu nháp" icon="pi pi-save" size="small" severity="secondary" :loading="saving === 'draft'" :disabled="!!saving" @click="save(false)" />
          <Button label="Gửi duyệt" icon="pi pi-send" size="small" :loading="saving === 'submit'" :disabled="!!saving" @click="save(true)" />
        </template>
        <Button v-if="isOwner && canCancel" label="Hủy đơn" icon="pi pi-times-circle" size="small" severity="danger" outlined :loading="saving === 'cancel'" @click="cancel" />
        <template v-if="myPendingStep">
          <Button label="Từ chối" icon="pi pi-times" size="small" severity="danger" outlined @click="openDecision('REJECTED')" />
          <Button label="Trả lại" icon="pi pi-reply" size="small" severity="secondary" outlined @click="openDecision('RETURNED')" />
          <Button label="Duyệt" icon="pi pi-check" size="small" severity="success" @click="openDecision('APPROVED')" />
        </template>
      </template>
    </PageHeader>

    <div v-if="request && !isOwner" class="bg-white border border-slate-200 rounded-[6px] px-4 py-3 text-xs text-slate-700 flex flex-wrap gap-x-6 gap-y-1">
      <span>Người đề nghị: <strong class="text-slate-900">{{ request.user_name }}</strong></span>
      <span>Gửi lúc: <strong class="text-slate-900">{{ formatDateTime(request.submitted_at) }}</strong></span>
    </div>

    <LeaveDecisionDialog v-model:visible="decision.visible" :request="request" :decision="decision.type" @done="load" />

    <div v-if="returnedComment" class="bg-sky-50 border border-sky-200 rounded-[6px] px-4 py-3 text-xs text-sky-800 flex gap-2">
      <i class="pi pi-reply mt-0.5"></i>
      <div><strong>Đơn bị trả lại:</strong> {{ returnedComment }}</div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-4">
      <section class="bg-white border border-slate-200 rounded-[6px]">
        <div class="px-5 py-3 border-b border-slate-200 text-sm font-semibold text-slate-900">Thông tin nghỉ phép</div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <label class="font-semibold text-slate-700">Loại nghỉ <span class="text-rose-500">*</span></label>
            <Select v-model="form.leave_type_id" :options="leaveTypes" option-label="name" option-value="id" placeholder="Chọn loại nghỉ..." fluid :disabled="!editable">
              <template #option="{ option }">
                <div class="flex items-center justify-between w-full gap-3">
                  <span>{{ option.name }}</span>
                  <span class="text-[11px] text-slate-500">
                    {{ option.deducts_balance ? 'Trừ phép năm' : option.is_paid ? 'Có lương' : 'Không lương' }}{{ option.max_days_per_request ? ` · tối đa ${formatDays(option.max_days_per_request)} ngày` : '' }}
                  </span>
                </div>
              </template>
            </Select>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="font-semibold text-slate-700">Từ ngày <span class="text-rose-500">*</span></label>
            <div class="flex gap-2">
              <DatePicker v-model="form.start_date" date-format="dd/mm/yy" show-icon fluid :disabled="!editable" class="flex-1" />
              <Select v-model="form.start_session" :options="startSessionOptions" option-label="label" option-value="value" class="w-36" :disabled="!editable" />
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="font-semibold text-slate-700">Đến ngày <span class="text-rose-500">*</span></label>
            <div class="flex gap-2">
              <DatePicker v-model="form.end_date" date-format="dd/mm/yy" show-icon fluid :min-date="form.start_date" :disabled="!editable" class="flex-1" />
              <Select v-if="!singleDay" v-model="form.end_session" :options="endSessionOptions" option-label="label" option-value="value" class="w-36" :disabled="!editable" />
            </div>
          </div>

          <div class="md:col-span-2 flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-[6px] px-3 py-2">
            <i class="pi pi-calendar text-sky-600"></i>
            <span class="text-slate-600">Số ngày nghỉ tính công:</span>
            <strong class="font-mono text-sm text-slate-900">{{ totalDays === null ? '—' : formatDays(totalDays) }}</strong>
            <span class="text-[11px] text-slate-400">(đã trừ thứ 7, chủ nhật và ngày lễ)</span>
          </div>

          <div class="flex flex-col gap-1.5 md:col-span-2">
            <label class="font-semibold text-slate-700">Lý do <span class="text-rose-500">*</span></label>
            <Textarea v-model="form.reason" rows="3" auto-resize fluid :disabled="!editable" placeholder="Ví dụ: Về quê giải quyết việc gia đình..." />
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="font-semibold text-slate-700">Người nhận bàn giao</label>
            <Select v-model="form.handover_user_id" :options="handoverOptions" option-label="name" option-value="id" filter show-clear fluid :disabled="!editable" placeholder="Chọn đồng nghiệp..." />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="font-semibold text-slate-700">Nội dung bàn giao</label>
            <InputText v-model="form.handover_note" fluid :disabled="!editable" placeholder="Công việc cần theo dõi khi vắng mặt" />
          </div>
        </div>
      </section>

      <aside class="flex flex-col gap-4">
        <section v-if="isOwner" class="bg-white border border-slate-200 rounded-[6px]">
          <div class="px-4 py-3 border-b border-slate-200 text-sm font-semibold text-slate-900">Quỹ phép năm {{ balanceYear }}</div>
          <div class="p-4 grid grid-cols-2 gap-3 text-xs">
            <div><div class="text-slate-500">Phép đầu kỳ</div><div class="font-mono font-bold text-slate-900">{{ formatDays(balance?.opening_days) }}</div></div>
            <div><div class="text-slate-500">Đã nghỉ</div><div class="font-mono font-bold text-slate-900">{{ formatDays(balance?.used_days) }}</div></div>
            <div><div class="text-slate-500">Chờ duyệt</div><div class="font-mono font-bold text-amber-600">{{ formatDays(balance?.pending_days) }}</div></div>
            <div><div class="text-slate-500">Còn lại</div><div class="font-mono font-bold text-emerald-600">{{ formatDays(balance?.remaining_days) }}</div></div>
          </div>
          <p v-if="balance?.period_start && !balance.period_start.endsWith('-01-01')" class="m-0 px-4 pb-3 text-[11px] text-slate-500">
            Kỳ phép tính từ {{ formatDate(balance.period_start) }}.
          </p>
          <p v-if="selectedType && !selectedType.deducts_balance" class="m-0 px-4 pb-3 text-[11px] text-slate-500">
            {{ selectedType.name }} không trừ vào quỹ phép năm.
          </p>
        </section>

        <section v-if="request" class="bg-white border border-slate-200 rounded-[6px]">
          <div class="px-4 py-3 border-b border-slate-200 text-sm font-semibold text-slate-900">Lịch sử xử lý</div>
          <ol class="m-0 p-4 flex flex-col gap-3 list-none">
            <li v-for="history in request.approval_histories" :key="history.id" class="flex gap-2 text-xs">
              <i :class="historyIcon(history.action)"></i>
              <div class="flex flex-col">
                <span class="text-slate-800"><strong>{{ history.actor_name }}</strong> · {{ APPROVAL_ACTION_LABELS[history.action] || history.action }}</span>
                <span v-if="history.comment" class="text-slate-600">“{{ history.comment }}”</span>
                <span class="text-[11px] text-slate-400">{{ formatDateTime(history.created_at) }}</span>
              </div>
            </li>
            <li v-if="pendingStep" class="flex gap-2 text-xs">
              <i class="pi pi-clock text-amber-500 mt-0.5"></i>
              <span class="text-slate-600">Đang chờ <strong>{{ pendingStep.approver_name }}</strong> duyệt</span>
            </li>
          </ol>
        </section>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import PageHeader from '../../../components/common/PageHeader.vue';
import LeaveDecisionDialog from '../components/LeaveDecisionDialog.vue';
import { eofficeApi } from '../../../api/eoffice.api';
import { identityApi } from '../../../api/identity.api';
import { formatDate, formatDateTime } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { storage } from '../../../utils/storage';
import { APPROVAL_ACTION_LABELS, LEAVE_STATUS, SESSION_OPTIONS, formatDays, fromIsoDate, toIsoDate } from '../constants';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const currentUser = parseJwt(storage.getToken());

const request = ref(null);
const leaveTypes = ref([]);
const users = ref([]);
const balance = ref(null);
const totalDays = ref(null);
const saving = ref(null);
const form = reactive({
  leave_type_id: null,
  start_date: null,
  start_session: 'FULL',
  end_date: null,
  end_session: 'FULL',
  reason: '',
  handover_user_id: null,
  handover_note: ''
});

const requestId = computed(() => route.params.id);
const isOwner = computed(() => !request.value || Number(request.value.user_id) === Number(currentUser?.sub));
const editable = computed(() => isOwner.value && (!request.value || ['DRAFT', 'RETURNED'].includes(request.value.status)));
const canCancel = computed(() => request.value && (request.value.status === 'PENDING'
  || (request.value.status === 'APPROVED' && request.value.start_date > toIsoDate(new Date()))));
const pageTitle = computed(() => request.value ? `Đơn Xin Nghỉ Phép #${request.value.code}` : 'Tạo Đơn Xin Nghỉ Phép');
const singleDay = computed(() => !form.end_date || toIsoDate(form.start_date) === toIsoDate(form.end_date));
const selectedType = computed(() => leaveTypes.value.find(type => type.id === form.leave_type_id));
const balanceYear = computed(() => (form.start_date || new Date()).getFullYear());
const startSessionOptions = computed(() => singleDay.value ? SESSION_OPTIONS : SESSION_OPTIONS.filter(option => option.value !== 'MORNING'));
const endSessionOptions = SESSION_OPTIONS.filter(option => option.value !== 'AFTERNOON');
const handoverOptions = computed(() => users.value.filter(user => Number(user.id) !== Number(currentUser?.sub)));
const pendingStep = computed(() => request.value?.approval_steps?.find(step => step.status === 'PENDING'));
const myPendingStep = computed(() => pendingStep.value && Number(pendingStep.value.approver_id) === Number(currentUser?.sub));
const decision = reactive({ visible: false, type: 'APPROVED' });
const openDecision = type => Object.assign(decision, { visible: true, type });
const returnedComment = computed(() => {
  if (request.value?.status !== 'RETURNED') return null;
  return [...(request.value.approval_steps || [])].reverse().find(step => step.status === 'RETURNED')?.comment;
});

const historyIcon = action => ({
  APPROVED: 'pi pi-check-circle text-emerald-600 mt-0.5',
  AUTO_APPROVED: 'pi pi-check-circle text-emerald-600 mt-0.5',
  REJECTED: 'pi pi-times-circle text-rose-600 mt-0.5',
  RETURNED: 'pi pi-reply text-sky-600 mt-0.5',
  CANCELLED: 'pi pi-ban text-slate-500 mt-0.5',
  SUBMITTED: 'pi pi-send text-sky-600 mt-0.5'
}[action] || 'pi pi-circle text-slate-400 mt-0.5');

const payload = () => ({
  leave_type_id: form.leave_type_id,
  start_date: toIsoDate(form.start_date),
  start_session: form.start_session,
  end_date: toIsoDate(form.end_date || form.start_date),
  end_session: singleDay.value ? form.start_session : form.end_session,
  reason: form.reason,
  handover_user_id: form.handover_user_id,
  handover_user_name: users.value.find(user => user.id === form.handover_user_id)?.name || null,
  handover_note: form.handover_note
});

const refreshTotalDays = async () => {
  if (!form.start_date) {
    totalDays.value = null;
    return;
  }
  const { start_date, end_date, start_session, end_session } = payload();
  if (end_date < start_date) {
    totalDays.value = null;
    return;
  }
  try {
    totalDays.value = (await eofficeApi.previewLeaveDays({ start_date, end_date, start_session, end_session })).data.total_days;
  } catch {
    totalDays.value = null;
  }
};

const loadBalance = async () => {
  try {
    balance.value = (await eofficeApi.getMyLeaveBalance(balanceYear.value)).data;
  } catch {
    balance.value = null;
  }
};

const fillForm = data => {
  Object.assign(form, {
    leave_type_id: data.leave_type_id,
    start_date: fromIsoDate(data.start_date),
    start_session: data.start_session,
    end_date: fromIsoDate(data.end_date),
    end_session: data.end_session,
    reason: data.reason,
    handover_user_id: data.handover_user_id,
    handover_note: data.handover_note || ''
  });
  totalDays.value = data.total_days;
};

const save = async submit => {
  if (!form.leave_type_id || !form.start_date || !form.reason.trim()) {
    toast.add({ severity: 'warn', summary: 'Thiếu thông tin', detail: 'Vui lòng chọn loại nghỉ, ngày nghỉ và nhập lý do.', life: 3500 });
    return;
  }
  saving.value = submit ? 'submit' : 'draft';
  try {
    let saved;
    if (request.value) {
      saved = (await eofficeApi.updateLeaveRequest(request.value.id, payload())).data;
      if (submit) saved = (await eofficeApi.submitLeaveRequest(saved.id)).data;
    } else {
      saved = (await eofficeApi.createLeaveRequest({ ...payload(), submit })).data;
    }
    toast.add({ severity: 'success', summary: submit ? 'Đã gửi đơn đi duyệt' : 'Đã lưu nháp', detail: saved.code, life: 3000 });
    const target = submit ? '/eoffice/leave' : `/eoffice/leave/${saved.id}`;
    if (route.path === target) await load();
    else await router.push(target);
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không lưu được đơn', detail: error.message, life: 5000 });
  } finally {
    saving.value = null;
  }
};

const cancel = async () => {
  const reason = prompt(`Lý do hủy đơn ${request.value.code}:`);
  if (reason === null) return;
  saving.value = 'cancel';
  try {
    request.value = (await eofficeApi.cancelLeaveRequest(request.value.id, reason)).data;
    toast.add({ severity: 'success', summary: 'Đã hủy đơn', detail: request.value.code, life: 3000 });
    await loadBalance();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không hủy được đơn', detail: error.message, life: 5000 });
  } finally {
    saving.value = null;
  }
};

const load = async () => {
  if (!requestId.value || requestId.value === 'new') {
    request.value = null;
    return;
  }
  try {
    request.value = (await eofficeApi.getLeaveRequest(requestId.value)).data;
    fillForm(request.value);
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được đơn nghỉ', detail: error.message, life: 4000 });
  }
};

watch(() => [form.start_date, form.end_date, form.start_session, form.end_session], () => {
  if (form.start_date && (!form.end_date || form.end_date < form.start_date)) form.end_date = form.start_date;
  if (!singleDay.value && form.start_session === 'MORNING') form.start_session = 'FULL';
  if (editable.value) refreshTotalDays();
});
watch(balanceYear, loadBalance);
watch(requestId, load);

onMounted(async () => {
  const [types] = await Promise.allSettled([eofficeApi.getLeaveTypes(), load(), loadBalance()]);
  leaveTypes.value = types.status === 'fulfilled' ? types.value.data : [];
  if (!form.leave_type_id) form.leave_type_id = leaveTypes.value.find(type => type.code === 'ANNUAL')?.id ?? null;
  try {
    users.value = (await identityApi.getUsers()).data || [];
  } catch {
    users.value = [];
  }
});
</script>
