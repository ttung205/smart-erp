<template>
  <div class="flex h-[calc(100vh-7rem)] min-h-[480px] bg-white border border-slate-200 rounded-[6px] overflow-hidden">
    <!-- Danh sách hội thoại -->
    <aside class="w-[320px] shrink-0 border-r border-slate-200" :class="{ 'hidden md:block': chat.activeId }">
      <ChatConversationList @select="select" @new-direct="picker = 'direct'" @new-group="picker = 'group'" />
    </aside>

    <!-- Hội thoại -->
    <section class="flex-1 min-w-0" :class="{ 'hidden md:block': !chat.activeId }">
      <ChatConversationView
        v-if="chat.activeConversation"
        :key="chat.activeConversation.id"
        :conv="chat.activeConversation"
        :info-open="infoOpen"
        show-back
        @toggle-info="infoOpen = !infoOpen"
        @back="select(null)"
      />
      <div v-else class="h-full flex flex-col items-center justify-center gap-3 text-center bg-slate-50 px-6">
        <i class="pi pi-comments text-4xl text-slate-300"></i>
        <div class="text-sm font-semibold text-slate-700">Tin nhắn nội bộ</div>
        <p class="text-xs text-slate-500 m-0 max-w-sm">Chọn một hội thoại bên trái, hoặc bắt đầu trò chuyện với đồng nghiệp / tạo nhóm chat mới.</p>
        <div class="flex gap-2">
          <Button label="Chat với đồng nghiệp" icon="pi pi-user-plus" size="small" outlined @click="picker = 'direct'" />
          <Button label="Tạo nhóm" icon="pi pi-users" size="small" @click="picker = 'group'" />
        </div>
      </div>
    </section>

    <!-- Thông tin -->
    <aside v-if="infoOpen && chat.activeConversation" class="w-[300px] shrink-0 border-l border-slate-200 hidden lg:block">
      <ChatInfoPanel :conv="chat.activeConversation" @close="infoOpen = false" @open-direct="openDirect" />
    </aside>

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
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import ChatConversationList from '../../components/chat/ChatConversationList.vue';
import ChatConversationView from '../../components/chat/ChatConversationView.vue';
import ChatInfoPanel from '../../components/chat/ChatInfoPanel.vue';
import ChatUserPickerDialog from '../../components/chat/ChatUserPickerDialog.vue';
import { useChatStore } from '../../stores/chatStore';

const chat = useChatStore();
const route = useRoute();
const router = useRouter();
const toast = useToast();
const infoOpen = ref(false);
const picker = ref(null);
const creating = ref(false);

function select(id) {
  router.replace(id ? `/chat/${id}` : '/chat');
}

// URL /chat/:id là nguồn của hội thoại đang mở (mở lại được bằng link, nút back trình duyệt)
watch(() => route.params.id, async (id) => {
  const conversationId = Number(id) || null;
  await chat.openConversation(conversationId);
  if (conversationId && !chat.activeId) {
    toast.add({ severity: 'warn', summary: 'Không mở được hội thoại', detail: 'Hội thoại không tồn tại hoặc bạn không còn là thành viên.', life: 3500 });
    router.replace('/chat');
  }
});

// Bị xoá khỏi nhóm / nhóm bị giải tán khi đang mở
watch(() => chat.activeId, (id) => {
  if (!id && route.params.id) router.replace('/chat');
});

async function openDirect(userId) {
  try {
    const id = await chat.openDirect(userId);
    picker.value = null;
    select(id);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  }
}

async function createGroup({ title, userIds }) {
  creating.value = true;
  try {
    const id = await chat.createGroup(title, userIds);
    picker.value = null;
    select(id);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Không tạo được nhóm', detail: e.message, life: 4000 });
  } finally {
    creating.value = false;
  }
}

onMounted(async () => {
  await chat.init();
  await chat.openConversation(Number(route.params.id) || null);
});

// Rời trang thì không còn "đang xem" hội thoại nào, để tin mới được tính là chưa đọc
onBeforeUnmount(() => chat.openConversation(null));
</script>
