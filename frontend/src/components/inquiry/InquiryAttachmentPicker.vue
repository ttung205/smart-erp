<template>
  <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3">
    <div class="flex items-center gap-2 pb-3 border-b border-slate-200">
      <i class="pi pi-paperclip text-sky-600 text-sm"></i>
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Tài liệu gửi kèm email (từ yêu cầu)</span>
    </div>

    <span v-if="!attachments.length" class="text-xs text-slate-400">Yêu cầu không có tài liệu</span>
    <label v-for="attachment in attachments" :key="attachment.id" class="flex items-center gap-2 text-xs text-slate-700">
      <Checkbox
        :modelValue="modelValue"
        @update:modelValue="value => emit('update:modelValue', value)"
        :value="attachment.id"
        :disabled="disabled"
      />
      <span>{{ attachment.file_name }}</span>
    </label>
  </div>
</template>

<script setup>
import Checkbox from 'primevue/checkbox';

defineProps({
  // Danh sách id tài liệu được chọn
  modelValue: { type: Array, default: () => [] },
  attachments: { type: Array, default: () => [] },
  disabled: Boolean
});

const emit = defineEmits(['update:modelValue']);
</script>
