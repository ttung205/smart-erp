<template>
  <div class="flex h-[calc(100vh-7rem)] min-h-[480px] bg-white border border-slate-200 rounded-[6px] overflow-hidden">
    <!-- Lịch sử hội thoại -->
    <aside class="w-[300px] shrink-0 border-r border-slate-200 flex flex-col" :class="{ 'hidden md:flex': ai.conversationId }">
      <div class="h-14 px-3 border-b border-slate-200 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2">
          <span class="w-8 h-8 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center">
            <i class="pi pi-sparkles text-sm"></i>
          </span>
          <div>
            <div class="text-sm font-semibold text-slate-800">Trợ lý AI</div>
            <div class="text-[10px] text-slate-500">{{ ai.asOf ? `Dữ liệu đến ${formatDate(ai.asOf)}` : 'Phân tích dữ liệu ERP' }}</div>
          </div>
        </div>
        <Button icon="pi pi-plus" size="small" outlined v-tooltip.bottom="'Cuộc trò chuyện mới'" @click="newConversation" />
      </div>
      <AiHistoryList class="flex-1" @select="select" @removed="onRemoved" />
    </aside>

    <!-- Hội thoại -->
    <section class="flex-1 min-w-0 flex flex-col">
      <div class="h-14 px-4 border-b border-slate-200 flex items-center gap-2 shrink-0">
        <Button v-if="ai.conversationId" icon="pi pi-arrow-left" text size="small" class="md:!hidden" @click="newConversation" />
        <div class="text-sm font-semibold text-slate-800 truncate">{{ title }}</div>
      </div>
      <AiConversationView class="flex-1 min-h-0" :current-user="currentUser" large />
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import { useAiStore } from '../../stores/aiStore';
import AiConversationView from '../../components/ai/AiConversationView.vue';
import AiHistoryList from '../../components/ai/AiHistoryList.vue';

defineProps({
  currentUser: { type: Object, default: null }
});

const ai = useAiStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();

const formatDate = (iso) => (iso ? iso.split('-').reverse().join('/') : '');
const title = computed(() => {
  if (!ai.conversationId) return 'Cuộc trò chuyện mới';
  return ai.history.find((c) => c.id === ai.conversationId)?.title || 'Cuộc trò chuyện';
});

function select(id) {
  router.push(`/ai/${id}`);
}

function newConversation() {
  ai.newConversation();
  if (route.params.id) router.push('/ai');
}

function onRemoved(id) {
  if (Number(route.params.id) === id) router.push('/ai');
}

async function openFromRoute() {
  const id = Number(route.params.id) || null;
  try {
    if (id) await ai.loadConversation(id);
    else if (route.params.id === undefined && ai.conversationId) router.replace(`/ai/${ai.conversationId}`);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Không mở được hội thoại', detail: e.message, life: 4000 });
    router.replace('/ai');
  }
}

// Gửi câu đầu tiên trong cuộc trò chuyện mới thì cập nhật URL theo id vừa tạo
watch(() => ai.conversationId, (id) => {
  if (id && Number(route.params.id) !== id) router.replace(`/ai/${id}`);
});
watch(() => route.params.id, openFromRoute);

onMounted(() => {
  ai.loadHistory().catch(() => {});
  openFromRoute();
});
</script>
