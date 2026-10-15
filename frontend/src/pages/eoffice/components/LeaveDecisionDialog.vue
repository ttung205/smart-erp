<template>
  <Dialog :visible="visible" modal :header="title" :style="{ width: '460px' }" @update:visible="emit('update:visible', $event)">
    <div class="flex flex-col gap-3 text-xs">
      <div class="bg-slate-50 border border-slate-200 rounded-[6px] px-3 py-2">
        <div class="font-mono font-semibold text-sky-700">{{ request?.code }}</div>
        <div class="text-slate-800">
          {{ request?.user_name }} · {{ request?.leave_type?.name }} · {{ formatDays(request?.total_days) }} ngày
        </div>
      </div>
      <label class="font-semibold text-slate-700">
        Ý kiến<span v-if="decision !== 'APPROVED'" class="text-rose-500"> *</span>
      </label>
      <Textarea
        v-model="comment"
        rows="3"
        auto-resize
        class="w-full text-sm"
        :placeholder="decision === 'APPROVED' ? 'Không bắt buộc' : 'Nêu rõ lý do để người tạo đơn nắm được'"
      />
    </div>
    <template #footer>
      <Button label="Đóng" text severity="secondary" size="small" @click="emit('update:visible', false)" />
      <Button :label="meta.button" :severity="meta.severity" size="small" :loading="saving" @click="confirm" />
    </template>
  </Dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import { eofficeApi } from '../../../api/eoffice.api';
import { parseJwt } from '../../../utils/jwt';
import { storage } from '../../../utils/storage';
import { formatDays } from '../constants';

const props = defineProps({
  visible: { type: Boolean, default: false },
  request: { type: Object, default: null },
  decision: { type: String, default: 'APPROVED' }
});
const emit = defineEmits(['update:visible', 'done']);

const toast = useToast();
const comment = ref('');
const saving = ref(false);

const DECISIONS = {
  APPROVED: { title: 'Duyệt Đơn', button: 'Duyệt', severity: 'success', done: 'Đã duyệt đơn' },
  RETURNED: { title: 'Trả Lại Đơn Để Bổ Sung', button: 'Trả lại', severity: 'info', done: 'Đã trả lại đơn' },
  REJECTED: { title: 'Từ Chối Đơn', button: 'Từ chối', severity: 'danger', done: 'Đã từ chối đơn' }
};
const meta = computed(() => DECISIONS[props.decision]);
const title = computed(() => `${meta.value.title} ${props.request?.code || ''}`);

watch(() => props.visible, visible => {
  if (visible) comment.value = '';
});

const confirm = async () => {
  if (props.decision !== 'APPROVED' && !comment.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Thiếu ý kiến', detail: 'Vui lòng nhập lý do khi từ chối hoặc trả lại đơn.', life: 3500 });
    return;
  }
  const currentUserId = Number(parseJwt(storage.getToken())?.sub);
  const step = props.request?.approval_steps?.find(item => Number(item.approver_id) === currentUserId && item.status === 'PENDING');
  if (!step) return;

  saving.value = true;
  try {
    await eofficeApi.decideApproval(step.id, props.decision, comment.value);
    toast.add({ severity: 'success', summary: meta.value.done, detail: props.request.code, life: 3000 });
    emit('update:visible', false);
    emit('done');
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Thao tác thất bại', detail: error.message, life: 5000 });
  } finally {
    saving.value = false;
  }
};
</script>
