<template>
  <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3">
    <div class="flex items-center gap-2 pb-3 border-b border-slate-200">
      <i class="pi pi-paperclip text-sky-600 text-sm"></i>
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">Tài liệu đính kèm</span>
    </div>

    <div class="flex flex-wrap items-center gap-2">
      <Button
        v-for="attachment in attachments"
        :key="attachment.id"
        :label="attachment.file_name"
        icon="pi pi-download"
        size="small"
        outlined
        class="!rounded-[6px] !text-xs !border-slate-300 !text-slate-700 hover:!bg-slate-100"
        @click="emit('download', attachment)"
      />
      <span v-if="!attachments.length" class="text-xs text-slate-400">Chưa có tài liệu</span>

      <template v-if="uploadable">
        <input ref="fileInput" type="file" accept=".pdf,.png,.jpg,.jpeg,.docx,.xlsx" class="hidden" @change="onFileChange" />
        <Button
          label="Tải tài liệu"
          icon="pi pi-upload"
          size="small"
          text
          class="!rounded-[6px] !text-xs"
          :loading="uploading"
          @click="fileInput.click()"
        />
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';

defineProps({
  attachments: { type: Array, default: () => [] },
  uploadable: Boolean,
  uploading: Boolean
});

const emit = defineEmits(['upload', 'download']);
const fileInput = ref(null);

function onFileChange(event) {
  const file = event.target.files?.[0];
  if (file) emit('upload', file);
  event.target.value = '';
}
</script>
