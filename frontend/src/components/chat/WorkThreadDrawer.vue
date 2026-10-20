<template>
  <Drawer
    :visible="visible"
    position="right"
    :style="{ width: '520px', maxWidth: '100vw' }"
    :pt="{
      root: { class: '!border-l !border-slate-200 !shadow-2xl !p-0' },
      header: { class: '!hidden' },
      content: { class: '!p-0 flex flex-col h-full bg-slate-50' }
    }"
    @update:visible="emit('update:visible', $event)"
  >
    <!-- Drawer Custom Header -->
    <header class="h-14 px-4 flex items-center justify-between bg-white border-b border-slate-200 shrink-0">
      <div class="flex items-center gap-2 min-w-0">
        <div class="w-8 h-8 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center font-bold text-xs shrink-0">
          <i class="pi pi-comments"></i>
        </div>
        <div class="min-w-0">
          <div class="flex items-center gap-1.5">
            <span class="text-xs px-1.5 py-0.5 font-bold uppercase rounded bg-slate-100 text-slate-700 tracking-wide">
              {{ subjectType }}
            </span>
            <span class="text-sm font-bold text-slate-900 truncate">
              {{ subjectCode ? `#${subjectCode}` : `#${subjectId}` }}
            </span>
          </div>
          <div v-if="subjectTitle" class="text-[11px] text-slate-500 truncate max-w-[320px]">
            {{ subjectTitle }}
          </div>
        </div>
      </div>

      <div class="flex items-center gap-1">
        <Button
          icon="pi pi-times"
          text
          rounded
          severity="secondary"
          class="!w-8 !h-8 !text-slate-500"
          v-tooltip.bottom="'Đóng trao đổi'"
          @click="emit('update:visible', false)"
        />
      </div>
    </header>

    <!-- Content / Chat View -->
    <main class="flex-1 min-h-0 relative">
      <div v-if="loading" class="h-full flex flex-col items-center justify-center gap-2 text-slate-400">
        <i class="pi pi-spin pi-spinner text-2xl"></i>
        <span class="text-xs">Đang mở luồng trao đổi...</span>
      </div>

      <div v-else-if="error" class="h-full flex flex-col items-center justify-center gap-3 p-6 text-center">
        <i class="pi pi-exclamation-triangle text-3xl text-amber-500"></i>
        <div class="text-sm font-semibold text-slate-800">Không thể mở trao đổi chứng từ</div>
        <p class="text-xs text-slate-500 m-0 max-w-xs">{{ error }}</p>
        <Button label="Thử lại" size="small" outlined @click="loadThread" />
      </div>

      <ChatConversationView
        v-else-if="conversation"
        :key="conversation.id"
        :conv="conversation"
        compact
        :show-back="false"
      />
    </main>
  </Drawer>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import ChatConversationView from './ChatConversationView.vue';
import { useChatStore } from '../../stores/chatStore';

const props = defineProps({
  visible: { type: Boolean, default: false },
  subjectType: { type: String, required: true },
  subjectId: { type: [Number, String], required: true },
  subjectCode: { type: String, default: null },
  subjectTitle: { type: String, default: null }
});

const emit = defineEmits(['update:visible']);

const chat = useChatStore();
const loading = ref(false);
const error = ref(null);
const conversationId = ref(null);

const conversation = computed(() => {
  return conversationId.value ? chat.conversationMap.get(conversationId.value) || null : null;
});

async function loadThread() {
  if (!props.subjectType || !props.subjectId) return;
  loading.value = true;
  error.value = null;

  try {
    await chat.init();
    const id = await chat.openWorkThread(
      props.subjectType,
      Number(props.subjectId),
      props.subjectCode,
      props.subjectTitle
    );
    conversationId.value = id;
    await chat.openConversation(id);
  } catch (err) {
    console.error('Lỗi mở Work Thread:', err);
    error.value = err.message || 'Lỗi kết nối tới hệ thống chat.';
  } finally {
    loading.value = false;
  }
}

watch(
  () => props.visible,
  (val) => {
    if (val) {
      loadThread();
    }
  },
  { immediate: true }
);
</script>
