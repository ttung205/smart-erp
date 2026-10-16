<template>
  <div
    role="button"
    tabindex="0"
    class="block overflow-hidden rounded-[6px] border border-slate-200 bg-slate-100 cursor-zoom-in"
    :style="{ width: `${size}px`, height: `${size}px` }"
    :title="attachment.name"
    @click="open"
    @keydown.enter="open"
  >
    <img v-if="objectUrl" :src="objectUrl" :alt="attachment.name" class="w-full h-full object-cover" />
    <span v-else-if="failed" class="w-full h-full flex items-center justify-center text-[10px] text-slate-400">Lỗi tải ảnh</span>
    <span v-else class="w-full h-full flex items-center justify-center"><i class="pi pi-spin pi-spinner text-slate-400"></i></span>
  </div>
</template>

<script setup>
import { useAuthBlob } from './useAuthBlob';
import { chatApi } from '../../api/chat.api';

const props = defineProps({ attachment: { type: Object, required: true }, size: { type: Number, default: 140 } });
const emit = defineEmits(['open']);
const { objectUrl, failed } = useAuthBlob(() => chatApi.attachmentUrl(props.attachment.id));

const open = () => objectUrl.value && emit('open', objectUrl.value);
</script>
