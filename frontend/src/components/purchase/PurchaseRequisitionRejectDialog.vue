<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    header="Từ Chối Đề Nghị Mua Sắm"
    :style="{ width: '450px' }"
    class="!rounded-[6px]"
  >
    <div class="flex flex-col gap-3 pt-1 text-xs">
      <p class="text-slate-600 m-0">
        Vui lòng nhập lý do từ chối phê duyệt phiếu <strong class="text-slate-900 font-mono">{{ prNumber }}</strong>:
      </p>
      <InputText
        v-model="reason"
        placeholder="Lý do từ chối (VD: Tồn kho còn đủ, vượt ngân sách quý...)"
        size="small"
        class="!rounded-[6px] !text-xs"
      />
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy bỏ"
          icon="pi pi-times"
          size="small"
          text
          severity="secondary"
          class="!rounded-[6px] !text-xs"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Xác nhận Từ chối"
          icon="pi pi-times-circle"
          size="small"
          severity="danger"
          class="!bg-rose-600 hover:!bg-rose-700 !text-white !border-rose-600 !rounded-[6px] !text-xs"
          :loading="loading"
          @click="$emit('confirm', reason)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  prNumber: {
    type: String,
    default: ''
  },
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'confirm']);

const reason = ref('');

watch(
  () => props.visible,
  (val) => {
    if (val) reason.value = '';
  }
);
</script>
