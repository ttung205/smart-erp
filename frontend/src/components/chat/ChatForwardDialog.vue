<template>
  <Dialog :visible="visible" modal header="Chuyển tiếp tin nhắn" :style="{ width: '440px' }" @update:visible="emit('update:visible', $event)">
    <div class="flex flex-col gap-3 pt-1">
      <div v-if="message" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-[6px] text-xs text-slate-600 line-clamp-3">
        {{ message.content || `${message.attachments.length} tệp đính kèm` }}
      </div>
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="keyword" placeholder="Tìm hội thoại..." class="w-full !text-sm" />
      </IconField>
      <div class="border border-slate-200 rounded-[6px] max-h-[300px] overflow-y-auto">
        <div
          v-for="conv in candidates"
          :key="conv.id"
          role="button"
          tabindex="0"
          :aria-pressed="selected.includes(conv.id)"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-left hover:bg-slate-50 cursor-pointer border-b border-slate-50"
          @click="toggle(conv.id)"
          @keydown.enter="toggle(conv.id)"
          @keydown.space.prevent="toggle(conv.id)"
        >
          <ChatAvatar :name="chat.conversationName(conv)" :url="conv.avatar_url || ''" :group="conv.type === 'group'" :size="30" />
          <span class="flex-1 text-xs font-semibold text-slate-800 truncate">{{ chat.conversationName(conv) }}</span>
          <Checkbox :model-value="selected.includes(conv.id)" binary tabindex="-1" :aria-label="`Chọn ${chat.conversationName(conv)}`" @click.stop="toggle(conv.id)" />
        </div>
      </div>
      <span class="text-[11px] text-slate-400">Đã chọn {{ selected.length }}/10 hội thoại</span>
    </div>
    <template #footer>
      <Button label="Huỷ" text size="small" severity="secondary" @click="emit('update:visible', false)" />
      <Button label="Chuyển tiếp" size="small" :loading="saving" :disabled="!selected.length" @click="emit('confirm', selected)" />
    </template>
  </Dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import ChatAvatar from './ChatAvatar.vue';
import { useChatStore } from '../../stores/chatStore';

const props = defineProps({ visible: Boolean, message: { type: Object, default: null }, saving: Boolean });
const emit = defineEmits(['update:visible', 'confirm']);

const chat = useChatStore();
const keyword = ref('');
const selected = ref([]);

watch(() => props.visible, (open) => {
  if (open) {
    keyword.value = '';
    selected.value = [];
  }
});

const candidates = computed(() => {
  const q = keyword.value.trim().toLowerCase();
  return chat.conversations.filter((c) => !q || chat.conversationName(c).toLowerCase().includes(q));
});

function toggle(id) {
  if (selected.value.includes(id)) selected.value = selected.value.filter((x) => x !== id);
  else if (selected.value.length < 10) selected.value = [...selected.value, id];
}
</script>
