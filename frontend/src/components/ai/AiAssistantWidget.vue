<template>
  <div v-if="allowed && !isAiRoute">
    <button
      v-if="!visible"
      type="button"
      class="fixed bottom-[118px] right-6 z-40 w-12 h-12 rounded-full bg-violet-600 hover:bg-violet-700 text-white flex items-center justify-center border border-violet-500 shadow-md cursor-pointer transition-transform hover:scale-105 active:scale-95"
      v-tooltip.left="'Trợ lý AI'"
      @click="open"
    >
      <i class="pi pi-sparkles text-lg"></i>
    </button>

    <div
      v-if="visible"
      class="fixed bottom-[54px] right-6 w-[460px] max-w-[calc(100vw-2rem)] h-[620px] max-h-[calc(100vh-5rem)] bg-white border border-slate-200 rounded-[6px] shadow-xl z-50 flex flex-col overflow-hidden"
    >
      <header class="h-12 border-b border-slate-200 px-3 flex items-center justify-between shrink-0 select-none">
        <div class="flex items-center gap-2 min-w-0">
          <span class="w-7 h-7 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center shrink-0">
            <i class="pi pi-sparkles text-xs"></i>
          </span>
          <div class="min-w-0">
            <div class="text-xs font-semibold text-slate-800">Trợ lý AI</div>
            <div class="text-[10px] text-slate-500 truncate">{{ ai.asOf ? `Dữ liệu đến ${formatDate(ai.asOf)}` : 'Hỏi về doanh số, tồn kho, công nợ...' }}</div>
          </div>
        </div>
        <div class="flex items-center gap-1">
          <Button icon="pi pi-history" text size="small" class="!w-7 !h-7 !p-0" v-tooltip.bottom="'Lịch sử'" @click="toggleHistory" />
          <Button icon="pi pi-plus" text size="small" class="!w-7 !h-7 !p-0" v-tooltip.bottom="'Cuộc trò chuyện mới'" @click="newConversation" />
          <Button icon="pi pi-window-maximize" text size="small" class="!w-7 !h-7 !p-0" v-tooltip.bottom="'Mở toàn màn hình'" @click="openFullScreen" />
          <Button icon="pi pi-times" text size="small" severity="secondary" class="!w-7 !h-7 !p-0" v-tooltip.bottom="'Đóng'" @click="visible = false" />
        </div>
      </header>

      <AiHistoryList v-if="showHistory" class="flex-1" @select="selectConversation" />
      <AiConversationView v-else ref="view" class="flex-1 min-h-0" :current-user="currentUser" />
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import { useAiStore } from '../../stores/aiStore';
import { hasPermission } from '../../utils/rbac';
import AiConversationView from './AiConversationView.vue';
import AiHistoryList from './AiHistoryList.vue';

const props = defineProps({
  currentUser: { type: Object, default: null }
});

const ai = useAiStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();
const visible = ref(false);
const showHistory = ref(false);
const view = ref(null);

const allowed = computed(() => hasPermission('ai.use', props.currentUser));
const isAiRoute = computed(() => route.path?.startsWith('/ai') || false);
const formatDate = (iso) => (iso ? iso.split('-').reverse().join('/') : '');

function open() {
  visible.value = true;
}

function newConversation() {
  ai.newConversation();
  showHistory.value = false;
}

async function toggleHistory() {
  showHistory.value = !showHistory.value;
  if (!showHistory.value) return;
  try {
    await ai.loadHistory();
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  }
}

async function selectConversation(id) {
  try {
    await ai.loadConversation(id);
    showHistory.value = false;
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  }
}

function openFullScreen() {
  visible.value = false;
  router.push(ai.conversationId ? `/ai/${ai.conversationId}` : '/ai');
}

// Đổi người đăng nhập thì xóa hội thoại đang hiển thị
watch(() => props.currentUser?.sub ?? props.currentUser?.id, () => {
  ai.reset();
  visible.value = false;
});
</script>
