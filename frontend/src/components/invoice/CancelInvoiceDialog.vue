<template>
  <Dialog :visible="visible" modal :header="`Hủy Chứng Từ ${invoice?.invoice_number || ''}`" :style="{ width: '460px' }" @update:visible="emit('update:visible', $event)">
    <div class="flex flex-col gap-3 text-xs">
      <div class="bg-amber-50 border border-amber-200 rounded-[6px] px-3 py-2 text-amber-800">
        Hàng đang giữ tại kho sẽ được nhả lại. Yêu cầu giao <b>{{ invoice?.delivery_request_number }}</b> về 0 và đóng lại;
        số lượng chưa giao quay về đơn bán để lập yêu cầu giao mới.
      </div>
      <label class="font-semibold text-slate-700">Lý do hủy<span class="text-rose-500"> *</span></label>
      <Textarea v-model="reason" rows="3" auto-resize class="w-full text-sm" placeholder="Nêu rõ lý do hủy chứng từ" />
    </div>
    <template #footer>
      <Button label="Đóng" text severity="secondary" size="small" @click="emit('update:visible', false)" />
      <Button label="Hủy chứng từ" severity="danger" size="small" :loading="saving" @click="submit" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Textarea from 'primevue/textarea';
import { salesApi } from '../../api/sales.api';

const props = defineProps({
  visible: { type: Boolean, default: false },
  invoice: { type: Object, default: null }
});
const emit = defineEmits(['update:visible', 'done']);

const toast = useToast();
const reason = ref('');
const saving = ref(false);

watch(() => props.visible, visible => {
  if (visible) reason.value = '';
});

const submit = async () => {
  if (!reason.value.trim()) {
    toast.add({ severity: 'warn', summary: 'Thiếu lý do', detail: 'Vui lòng nhập lý do hủy chứng từ.', life: 3500 });
    return;
  }
  saving.value = true;
  try {
    const res = await salesApi.cancelInvoice(props.invoice.id, reason.value.trim());
    toast.add({ severity: 'success', summary: 'Đã hủy chứng từ', detail: res.message, life: 3500 });
    emit('update:visible', false);
    emit('done');
  } catch (error) {
    toast.add({ severity: 'error', summary: 'Không hủy được chứng từ', detail: error.message, life: 5000 });
  } finally {
    saving.value = false;
  }
};
</script>
