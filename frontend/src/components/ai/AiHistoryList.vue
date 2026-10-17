<template>
  <div class="overflow-y-auto">
    <div v-if="ai.loadingHistory && !ai.history.length" class="p-6 text-center text-xs text-slate-500">
      <i class="pi pi-spin pi-spinner"></i>
    </div>
    <div v-else-if="!ai.history.length" class="p-6 text-center text-xs text-slate-500">Chưa có cuộc trò chuyện nào.</div>
    <div
      v-for="c in ai.history"
      :key="c.id"
      class="group px-3 py-2.5 border-b border-slate-100 cursor-pointer flex items-center gap-2"
      :class="c.id === ai.conversationId ? 'bg-violet-50' : 'hover:bg-slate-50'"
      @click="emit('select', c.id)"
    >
      <i class="pi pi-comment text-xs" :class="c.id === ai.conversationId ? 'text-violet-600' : 'text-slate-400'"></i>
      <div class="flex-1 min-w-0">
        <div class="text-xs text-slate-800 truncate">{{ c.title }}</div>
        <div class="text-[10px] text-slate-400">{{ formatTime(c.updated_at) }}</div>
      </div>
      <button
        type="button"
        class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-rose-600 cursor-pointer"
        v-tooltip.left="'Xóa'"
        @click.stop="remove(c.id)"
      >
        <i class="pi pi-trash text-xs"></i>
      </button>
    </div>
  </div>
</template>

<script setup>
import { useToast } from 'primevue/usetoast';
import { useAiStore } from '../../stores/aiStore';

const emit = defineEmits(['select', 'removed']);
const ai = useAiStore();
const toast = useToast();

// updated_at dạng 'YYYY-MM-DD HH:mm:ss' -> 'dd/mm/yyyy HH:mm'
const formatTime = (value) => {
  if (!value) return '';
  const [d, t = ''] = value.split(' ');
  return `${d.split('-').reverse().join('/')} ${t.slice(0, 5)}`;
};

async function remove(id) {
  try {
    await ai.removeConversation(id);
    emit('removed', id);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  }
}
</script>
