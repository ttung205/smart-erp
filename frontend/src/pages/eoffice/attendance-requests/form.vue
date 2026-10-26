<template>
  <div class="space-y-4 pb-8">
    <PageHeader :title="pageTitle">
      <template #title>
        {{ pageTitle }}
        <Tag v-if="request" :value="REQUEST_STATUS[request.status]?.label" :severity="REQUEST_STATUS[request.status]?.severity" />
      </template>
      <template #actions>
        <Button label="Quay lại" icon="pi pi-arrow-left" size="small" severity="secondary" outlined @click="router.push('/eoffice/attendance-requests')" />
        <template v-if="isOwner && editable">
          <Button label="Lưu nháp" icon="pi pi-save" size="small" severity="secondary" :loading="saving === 'draft'" :disabled="!!saving" @click="save(false)" />
          <Button label="Gửi duyệt" icon="pi pi-send" size="small" :loading="saving === 'submit'" :disabled="!!saving" @click="save(true)" />
        </template>
        <Button v-if="isOwner && canCancel" label="Hủy yêu cầu" icon="pi pi-times-circle" size="small" severity="danger" outlined :loading="saving === 'cancel'" @click="cancel" />
        <template v-if="myPendingStep">
          <Button label="Từ chối" icon="pi pi-times" size="small" severity="danger" outlined @click="openDecision('REJECTED')" />
          <Button label="Trả lại" icon="pi pi-reply" size="small" severity="secondary" outlined @click="openDecision('RETURNED')" />
          <Button label="Duyệt" icon="pi pi-check" size="small" severity="success" @click="openDecision('APPROVED')" />
        </template>
      </template>
    </PageHeader>

    <AttendanceDecisionDialog v-model:visible="decision.visible" :request="request" :decision="decision.type" @done="load" />

    <div v-if="request && !isOwner" class="bg-white border border-slate-200 rounded-[6px] px-4 py-3 text-xs text-slate-700 flex flex-wrap gap-x-6 gap-y-1">
      <span>Người gửi: <strong class="text-slate-900">{{ request.user_name }}</strong></span>
      <span>Gửi lúc: <strong class="text-slate-900">{{ formatDateTime(request.submitted_at) }}</strong></span>
    </div>
    <div v-if="returnedComment" class="bg-sky-50 border border-sky-200 rounded-[6px] px-4 py-3 text-xs text-sky-800 flex gap-2">
      <i class="pi pi-reply mt-0.5"></i>
      <div><strong>Yêu cầu bị trả lại:</strong> {{ returnedComment }}</div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-4">
      <section class="bg-white border border-slate-200 rounded-[6px]">
        <div class="px-5 py-3 border-b border-slate-200 text-sm font-semibold text-slate-900">Nội dung yêu cầu</div>
        <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div class="flex flex-col gap-1.5 md:col-span-2">
            <label class="font-semibold text-slate-700">Loại yêu cầu <span class="text-rose-500">*</span></label>
            <Select v-model="form.type" :options="REQUEST_TYPE_OPTIONS" option-label="label" option-value="value" fluid :disabled="!editable || !!request">
              <template #option="{ option }">
                <div class="flex flex-col">
                  <span>{{ option.label }}</span>
                  <span class="text-[11px] text-slate-500">{{ REQUEST_TYPES[option.value].hint }}</span>
                </div>
              </template>
            </Select>
            <span class="text-[11px] text-slate-500">{{ REQUEST_TYPES[form.type]?.hint }}</span>
          </div>

          <div class="flex flex-col gap-1.5">
            <label class="font-semibold text-slate-700">{{ isRange ? 'Từ ngày' : 'Ngày' }} <span class="text-rose-500">*</span></label>
            <DatePicker v-model="form.start_date" date-format="dd/mm/yy" show-icon fluid :disabled="!editable" />
          </div>
          <div v-if="isRange" class="flex flex-col gap-1.5">
            <label class="font-semibold text-slate-700">Đến ngày <span class="text-rose-500">*</span></label>
            <DatePicker v-model="form.end_date" date-format="dd/mm/yy" show-icon fluid :min-date="form.start_date" :disabled="!editable" />
          </div>

          <template v-if="usesTimes">
            <div class="flex flex-col gap-1.5">
              <label class="font-semibold text-slate-700">{{ timeLabels[0] }} <span v-if="needsBothTimes" class="text-rose-500">*</span></label>
              <InputText v-model="form.requested_in" type="time" fluid :disabled="!editable" />
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="font-semibold text-slate-700">{{ timeLabels[1] }} <span v-if="needsBothTimes" class="text-rose-500">*</span></label>
              <InputText v-model="form.requested_out" type="time" fluid :disabled="!editable" />
            </div>
          </template>

          <div class="flex flex-col gap-1.5 md:col-span-2">
            <label class="font-semibold text-slate-700">Lý do <span class="text-rose-500">*</span></label>
            <Textarea v-model="form.reason" rows="3" auto-resize fluid :disabled="!editable" placeholder="Nêu rõ lý do để người duyệt xem xét..." />
          </div>

          <div v-if="rulesHint" class="md:col-span-2 bg-slate-50 border border-slate-200 rounded-[6px] px-3 py-2 text-[11px] text-slate-600 flex gap-2">
            <i class="pi pi-info-circle mt-0.5 text-sky-600"></i>
            <span>{{ rulesHint }}</span>
          </div>
        </div>
      </section>

      <aside class="bg-white border border-slate-200 rounded-[6px] self-start">
        <div class="px-5 py-3 border-b border-slate-200 text-sm font-semibold text-slate-900">Lịch sử xử lý</div>
        <div v-if="!request?.approval_histories?.length" class="p-5 text-xs text-slate-400">Chưa có lịch sử. Yêu cầu sẽ đi qua quản lý trực tiếp của bạn.</div>
        <ol v-else class="p-4 flex flex-col gap-3 text-xs m-0 list-none">
          <li v-for="item in request.approval_histories" :key="item.id" class="border-l-2 border-slate-200 pl-3">
            <div class="font-semibold text-slate-800">{{ APPROVAL_ACTION_LABELS[item.action] || item.action }} <span class="font-normal text-slate-500">· {{ item.actor_name }}</span></div>
            <div class="text-[11px] text-slate-400">{{ formatDateTime(item.created_at) }}</div>
            <div v-if="item.comment" class="text-slate-600 mt-0.5">{{ item.comment }}</div>
          </li>
        </ol>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Textarea from 'primevue/textarea';
import PageHeader from '../../../components/common/PageHeader.vue';
import AttendanceDecisionDialog from '../components/AttendanceDecisionDialog.vue';
import { attendanceApi } from '../../../api/attendance.api';
import { formatDateTime } from '../../../utils/format';
import { parseJwt } from '../../../utils/jwt';
import { storage } from '../../../utils/storage';
import { APPROVAL_ACTION_LABELS, fromIsoDate, toIsoDate } from '../constants';
import { REQUEST_STATUS, REQUEST_TYPES, REQUEST_TYPE_OPTIONS, formatTime } from '../attendance-constants';

const route = useRoute();
const router = useRouter();
const toast = useToast();
const currentUserId = Number(parseJwt(storage.getToken())?.sub);

const requestId = computed(() => route.params.id);
const request = ref(null);
const saving = ref('');
const decision = reactive({ visible: false, type: 'APPROVED' });
const queryType = REQUEST_TYPES[route.query.type] ? String(route.query.type) : 'ADD_PUNCH';
const form = reactive({
  type: queryType,
  start_date: route.query.date ? fromIsoDate(String(route.query.date)) : new Date(),
  end_date: route.query.date ? fromIsoDate(String(route.query.date)) : new Date(),
  requested_in: '',
  requested_out: '',
  reason: ''
});

const isRange = computed(() => ['REMOTE', 'BUSINESS_TRIP'].includes(form.type));
const usesTimes = computed(() => !isRange.value);
const needsBothTimes = computed(() => ['MAKEUP', 'OVERTIME'].includes(form.type));
const timeLabels = computed(() => form.type === 'OVERTIME' ? ['Bắt đầu làm thêm', 'Kết thúc làm thêm'] : ['Giờ vào', 'Giờ ra']);
const rulesHint = computed(() => ({
  ADD_PUNCH: 'Chỉ cần nhập giờ còn thiếu (giờ vào hoặc giờ ra). Gửi trong vòng 3 ngày làm việc kể từ ngày phát sinh; quá hạn hãy liên hệ HR.',
  MAKEUP: 'Nhập đủ giờ vào và giờ ra thực tế. Cần quản lý trực tiếp xác nhận bạn đã đi làm.',
  REMOTE: 'Ngày làm việc từ xa được duyệt sẽ tính 1 công, không cần chấm công. Nhân viên làm từ xa chấm công trên web để có dấu vết.',
  BUSINESS_TRIP: 'Ngày công tác được duyệt sẽ tính 1 công, không cần chấm công.',
  OVERTIME: 'Chỉ phần giờ làm thêm đã được duyệt và trùng với thời gian bạn có mặt thực tế mới được tính, làm tròn xuống 30 phút.'
}[form.type]));

const isOwner = computed(() => !request.value || Number(request.value.user_id) === currentUserId);
const editable = computed(() => !request.value || ['DRAFT', 'RETURNED'].includes(request.value.status));
const canCancel = computed(() => request.value && ['PENDING', 'APPROVED'].includes(request.value.status));
const myPendingStep = computed(() => request.value?.approval_steps?.find(step => Number(step.approver_id) === currentUserId && step.status === 'PENDING'));
const returnedComment = computed(() => {
  if (request.value?.status !== 'RETURNED') return '';
  return [...(request.value.approval_histories || [])].reverse().find(item => item.action === 'RETURNED')?.comment || '';
});
const pageTitle = computed(() => request.value ? `Yêu cầu ${request.value.code}` : 'Tạo Yêu Cầu Chấm Công');

const payload = () => ({
  type: form.type,
  start_date: toIsoDate(form.start_date),
  end_date: isRange.value ? toIsoDate(form.end_date || form.start_date) : undefined,
  requested_in: usesTimes.value && form.requested_in ? formatTime(form.requested_in) : undefined,
  requested_out: usesTimes.value && form.requested_out ? formatTime(form.requested_out) : undefined,
  reason: form.reason
});

const load = async () => {
  if (!requestId.value) return;
  try {
    const { data } = await attendanceApi.getRequest(requestId.value);
    request.value = data;
    Object.assign(form, {
      type: data.type, start_date: fromIsoDate(data.start_date), end_date: fromIsoDate(data.end_date),
      requested_in: data.requested_in ? formatTime(data.requested_in) : '', requested_out: data.requested_out ? formatTime(data.requested_out) : '',
      reason: data.reason
    });
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không tải được yêu cầu', detail: error.message, life: 4000 });
    router.replace('/eoffice/attendance-requests');
  }
};

const validate = () => {
  if (!form.start_date) return 'Vui lòng chọn ngày.';
  if (!form.reason.trim()) return 'Vui lòng nhập lý do.';
  if (usesTimes.value) {
    if (needsBothTimes.value && (!form.requested_in || !form.requested_out)) return 'Vui lòng nhập đủ giờ bắt đầu và kết thúc.';
    if (form.type === 'ADD_PUNCH' && !form.requested_in && !form.requested_out) return 'Nhập giờ vào hoặc giờ ra cần bổ sung.';
    if (form.requested_in && form.requested_out && form.requested_out <= form.requested_in) return 'Giờ kết thúc phải sau giờ bắt đầu.';
  }
  return '';
};

const save = async submit => {
  const problem = validate();
  if (problem) {
    toast.add({ severity: 'warn', summary: 'Chưa hợp lệ', detail: problem, life: 4000 });
    return;
  }
  saving.value = submit ? 'submit' : 'draft';
  try {
    let saved;
    if (request.value) {
      saved = (await attendanceApi.updateRequest(request.value.id, payload())).data;
      if (submit) saved = (await attendanceApi.submitRequest(saved.id)).data;
    } else {
      saved = (await attendanceApi.createRequest({ ...payload(), submit })).data;
    }
    toast.add({ severity: 'success', summary: submit ? 'Đã gửi yêu cầu đi duyệt' : 'Đã lưu nháp', detail: saved.code, life: 3000 });
    router.push('/eoffice/attendance-requests');
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không lưu được yêu cầu', detail: firstError(error), life: 6000 });
  } finally {
    saving.value = '';
  }
};

const cancel = async () => {
  const reason = window.prompt('Lý do hủy yêu cầu (có thể bỏ trống):', '');
  if (reason === null) return;
  saving.value = 'cancel';
  try {
    await attendanceApi.cancelRequest(request.value.id, reason);
    toast.add({ severity: 'success', summary: 'Đã hủy yêu cầu', detail: request.value.code, life: 3000 });
    await load();
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không hủy được yêu cầu', detail: firstError(error), life: 6000 });
  } finally {
    saving.value = '';
  }
};

const firstError = error => {
  const errors = error.data?.errors;
  return errors ? Object.values(errors).flat()[0] : error.message;
};
const openDecision = type => Object.assign(decision, { visible: true, type });

onMounted(load);
</script>
