<template>
  <div v-if="!isChatRoute">
    <button
      v-if="!visible"
      type="button"
      class="fixed bottom-[54px] right-6 z-40 w-12 h-12 rounded-full bg-sky-600 hover:bg-sky-700 text-white flex items-center justify-center border border-sky-500 shadow-md cursor-pointer transition-transform hover:scale-105 active:scale-95"
      @click="toggleOpen"
      v-tooltip.left="'Tin nhắn nội bộ'"
    >
      <i class="pi pi-comments text-lg"></i>
      <span
        v-if="chat.unreadTotal > 0"
        class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-rose-600 text-white text-[10px] font-bold flex items-center justify-center border-2 border-white shadow-xs"
      >
        {{ chat.unreadTotal > 99 ? '99+' : chat.unreadTotal }}
      </span>
    </button>

    <div
      v-if="visible"
      class="fixed bottom-[54px] right-6 w-[420px] max-w-[calc(100vw-2rem)] h-[560px] max-h-[calc(100vh-5rem)] bg-white border border-slate-200 rounded-[6px] shadow-xl z-50 flex flex-col overflow-hidden"
    >
      <header class="h-12 bg-white border-b border-slate-200 px-3 flex items-center justify-between shrink-0 select-none">
        <span class="text-xs font-semibold text-slate-800">Tin nhắn nội bộ</span>
        <div class="flex items-center gap-1">
          <Button
            icon="pi pi-window-maximize"
            text
            size="small"
            class="!w-7 !h-7 !p-0"
            v-tooltip.bottom="'Mở toàn màn hình'"
            @click="openFullScreenChat"
          />
          <Button
            icon="pi pi-times"
            text
            size="small"
            severity="secondary"
            class="!w-7 !h-7 !p-0"
            v-tooltip.bottom="'Đóng'"
            @click="visible = false"
          />
        </div>
      </header>

      <div class="flex-1 flex flex-col min-h-0">
        <ChatInfoPanel v-if="chat.activeConversation && infoOpen" :conv="chat.activeConversation" @close="infoOpen = false" @open-direct="openDirect" />
        <ChatConversationView
          v-else-if="chat.activeConversation"
          :key="chat.activeConversation.id"
          :conv="chat.activeConversation"
          compact
          @back="closeConversation"
          @toggle-info="infoOpen = true"
        />
        <ChatConversationList v-else compact @select="openConversation" @new-direct="picker = 'direct'" @new-group="picker = 'group'" />
      </div>
    </div>

    <ChatUserPickerDialog
      :visible="!!picker"
      :mode="picker || 'direct'"
      :saving="creating"
      @update:visible="(v) => !v && (picker = null)"
      @pick="openDirect"
      @confirm="createGroup"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useChatStore } from '../../stores/chatStore';
import Button from 'primevue/button';
import ChatConversationList from '../chat/ChatConversationList.vue';
import ChatConversationView from '../chat/ChatConversationView.vue';
import ChatInfoPanel from '../chat/ChatInfoPanel.vue';
import ChatUserPickerDialog from '../chat/ChatUserPickerDialog.vue';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const route = useRoute();
const router = useRouter();
const chat = useChatStore();
const toast = useToast();

const isChatRoute = computed(() => route.path?.startsWith('/chat') || false);
const visible = ref(false);
const infoOpen = ref(false);
const picker = ref(null);
const creating = ref(false);

function toggleOpen() {
  visible.value = !visible.value;
  if (visible.value) chat.init();
}

function openFullScreenChat() {
  const id = chat.activeId;
  visible.value = false;
  router.push(id ? `/chat/${id}` : '/chat');
}

function openConversation(id) {
  infoOpen.value = false;
  chat.openConversation(id);
}

function closeConversation() {
  infoOpen.value = false;
  chat.openConversation(null);
}

async function openDirect(userId) {
  try {
    const id = await chat.openDirect(userId);
    picker.value = null;
    openConversation(id);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  }
}

async function createGroup({ title, userIds }) {
  creating.value = true;
  try {
    const id = await chat.createGroup(title, userIds);
    picker.value = null;
    openConversation(id);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Không tạo được nhóm', detail: e.message, life: 4000 });
  } finally {
    creating.value = false;
  }
}

watch(visible, (isVisible) => {
  if (!isVisible) {
    infoOpen.value = false;
    if (!isChatRoute.value) chat.openConversation(null);
  }
});

watch(() => props.currentUser?.sub ?? props.currentUser?.id, () => chat.init());

onMounted(() => chat.init());
</script>
