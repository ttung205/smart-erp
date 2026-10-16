<template>
  <div class="h-[calc(100vh-5rem)] flex flex-col bg-white border border-slate-200 rounded-[6px] overflow-hidden">
    <!-- Main Chat Workspace -->
    <div class="flex-1 flex min-h-0">
      <!-- 1. LEFT SIDEBAR: DANH SÁCH HỘI THOẠI & PHÒNG BAN -->
      <aside class="w-80 border-r border-slate-200 bg-white flex flex-col shrink-0">
        <!-- Sidebar Header & Action -->
        <div class="p-3 border-b border-slate-200 flex items-center justify-between gap-2">
          <div class="flex items-center gap-1.5 min-w-0">
            <Button
              icon="pi pi-arrow-left"
              text
              size="small"
              class="!w-7 !h-7 !p-0 !text-slate-600 hover:!text-slate-900 shrink-0"
              v-tooltip.bottom="'Quay lại trang trước'"
              @click="handleExitChat"
            />
            <span class="font-bold text-sm text-slate-800 truncate">Tin nhắn nội bộ</span>
            <span v-if="chatStore.totalUnreadCount > 0"
              class="px-1.5 py-0.5 text-[11px] font-semibold bg-rose-50 border border-rose-200 text-rose-600 rounded-[6px] shrink-0">
              {{ chatStore.totalUnreadCount }} mới
            </span>
          </div>

          <div class="flex items-center gap-1">
            <Button
              icon="pi pi-plus"
              text
              size="small"
              class="!w-8 !h-8 !p-0"
              v-tooltip.bottom="'Tạo cuộc trò chuyện mới'"
              @click="$router.push('/chat/create')"
            />
            <Button
              icon="pi pi-user-plus"
              text
              size="small"
              class="!w-8 !h-8 !p-0"
              v-tooltip.bottom="'Nhắn tin cho đồng nghiệp'"
              @click="openNewChatDialog"
            />
            <Button
              icon="pi pi-refresh"
              text
              size="small"
              class="!w-8 !h-8 !p-0"
              :loading="chatStore.loading.conversations"
              v-tooltip.bottom="'Tải lại danh sách'"
              @click="chatStore.fetchConversations"
            />
          </div>
        </div>

        <!-- Search Bar -->
        <div class="p-3 border-b border-slate-200">
          <div class="relative w-full">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Tìm kênh hoặc người dùng..."
              class="w-full pl-8 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 focus:bg-white text-slate-700"
            />
          </div>
        </div>

        <!-- Conversations List -->
        <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
          <!-- Section A: Kênh phòng ban -->
          <div class="py-2">
            <div class="px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              Kênh phòng ban
            </div>

            <div v-if="filteredChannels.length === 0" class="px-3 py-2 text-xs text-slate-400">
              Không tìm thấy kênh phù hợp.
            </div>

            <div
              v-for="channel in filteredChannels"
              :key="channel.id"
              class="px-3 py-2.5 flex items-start gap-2.5 cursor-pointer transition-colors border-l-2"
              :class="chatStore.activeConversationId === channel.id
                ? 'bg-sky-50/70 border-sky-600 text-slate-900 font-medium'
                : 'border-transparent hover:bg-slate-50 text-slate-700'"
              @click="chatStore.selectConversation(channel.id)"
            >
              <div class="w-8 h-8 rounded-[6px] bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0 text-slate-600 font-bold text-xs">
                #
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1 leading-tight">
                  <span class="text-xs truncate font-semibold" :class="chatStore.activeConversationId === channel.id ? 'text-sky-700' : 'text-slate-800'">
                    {{ channel.name }}
                  </span>
                  <span v-if="channel.last_message" class="text-[10px] text-slate-400 shrink-0">
                    {{ formatShortTime(channel.last_message.created_at) }}
                  </span>
                </div>

                <div class="flex items-center justify-between gap-1 mt-1">
                  <p class="text-[11px] text-slate-500 truncate">
                    <span v-if="channel.last_message">
                      <span class="font-medium text-slate-600">{{ channel.last_message.sender_name }}:</span>
                      {{ channel.last_message.content }}
                    </span>
                    <span v-else class="text-slate-400 italic">Chưa có tin nhắn</span>
                  </p>

                  <span
                    v-if="channel.unread_count > 0"
                    class="px-1.5 py-0.2 text-[10px] font-bold bg-sky-600 text-white rounded-[6px] shrink-0"
                  >
                    {{ channel.unread_count }}
                  </span>
                </div>
              </div>
            </div>
          </div>

          <!-- Section B: Tin nhắn trực tiếp 1-1 -->
          <div class="py-2">
            <div class="px-3 py-1 flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400">
              <span>Tin nhắn trực tiếp</span>
              <button
                type="button"
                class="hover:text-sky-600 cursor-pointer"
                @click="openNewChatDialog"
                title="Bắt đầu cuộc trò chuyện mới"
              >
                <i class="pi pi-plus text-[10px]"></i>
              </button>
            </div>

            <div v-if="filteredDirects.length === 0" class="px-3 py-2 text-xs text-slate-400">
              Chưa có cuộc hội thoại 1-1 nào.
            </div>

            <div
              v-for="dm in filteredDirects"
              :key="dm.id"
              class="px-3 py-2.5 flex items-start gap-2.5 cursor-pointer transition-colors border-l-2"
              :class="chatStore.activeConversationId === dm.id
                ? 'bg-sky-50/70 border-sky-600 text-slate-900 font-medium'
                : 'border-transparent hover:bg-slate-50 text-slate-700'"
              @click="chatStore.selectConversation(dm.id)"
            >
              <!-- Avatar kèm chấm trạng thái online -->
              <div class="relative shrink-0">
                <Avatar
                  :label="getDirectPartnerName(dm).charAt(0)"
                  shape="circle"
                  class="!bg-slate-200 !text-slate-700 !font-bold !text-xs !w-8 !h-8"
                />
                <span
                  class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-white"
                  :class="isPartnerOnline(dm) ? 'bg-emerald-500' : 'bg-slate-300'"
                ></span>
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1 leading-tight">
                  <span class="text-xs truncate font-semibold" :class="chatStore.activeConversationId === dm.id ? 'text-sky-700' : 'text-slate-800'">
                    {{ getDirectPartnerName(dm) }}
                  </span>
                  <span v-if="dm.last_message" class="text-[10px] text-slate-400 shrink-0">
                    {{ formatShortTime(dm.last_message.created_at) }}
                  </span>
                </div>

                <div class="flex items-center justify-between gap-1 mt-1">
                  <p class="text-[11px] text-slate-500 truncate">
                    <span v-if="dm.last_message">{{ dm.last_message.content }}</span>
                    <span v-else class="text-slate-400 italic">Bắt đầu trò chuyện</span>
                  </p>

                  <span
                    v-if="dm.unread_count > 0"
                    class="px-1.5 py-0.2 text-[10px] font-bold bg-sky-600 text-white rounded-[6px] shrink-0"
                  >
                    {{ dm.unread_count }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </aside>

      <!-- 2. RIGHT CHAT WINDOW: KHUNG CHAT & DÒNG TIN NHẮN -->
      <section class="flex-1 flex flex-col bg-slate-50 min-w-0">
        <!-- Chat Header -->
        <header
          class="h-14 px-4 bg-white border-b border-slate-200 flex items-center justify-between shrink-0">
          <div v-if="chatStore.activeConversation" class="flex items-center gap-2.5 min-w-0">
            <div v-if="chatStore.activeConversation.type === 'Channel'"
              class="w-8 h-8 rounded-[6px] bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-sm text-slate-700 shrink-0">
              #
            </div>
            <Avatar v-else
              :label="getDirectPartnerName(chatStore.activeConversation).charAt(0)"
              shape="circle"
              class="!bg-slate-200 !text-slate-700 !font-bold !text-xs !w-8 !h-8 shrink-0"
            />

            <div class="flex flex-col min-w-0">
              <span class="text-sm font-bold text-slate-800 truncate">
                {{ chatStore.activeConversation.type === 'Channel' ? chatStore.activeConversation.name : getDirectPartnerName(chatStore.activeConversation) }}
              </span>
              <span class="text-[11px] text-slate-400 truncate">
                <template v-if="chatStore.activeConversation.type === 'Channel'">
                  {{ chatStore.activeConversation.description || 'Kênh phòng ban nội bộ' }}
                </template>
                <template v-else>
                  {{ isPartnerOnline(chatStore.activeConversation) ? 'Đang trực tuyến' : 'Ngoại tuyến' }}
                </template>
              </span>
            </div>
          </div>
          <div v-else class="flex items-center gap-2 min-w-0">
            <span class="text-xs font-semibold text-slate-500">Chọn cuộc trò chuyện bên trái để bắt đầu nhắn tin</span>
          </div>

          <div class="flex items-center gap-1">
            <Button
              v-if="chatStore.activeConversation"
              icon="pi pi-refresh"
              text
              size="small"
              class="!w-8 !h-8 !p-0"
              v-tooltip.bottom="'Tải lại tin nhắn'"
              @click="refreshMessages"
            />
            <Button
              icon="pi pi-times"
              text
              size="small"
              severity="secondary"
              class="!w-8 !h-8 !p-0"
              v-tooltip.bottom="'Thoát toàn màn hình'"
              @click="handleExitChat"
            />
          </div>
        </header>

        <!-- Messages Stream -->
        <div
          ref="messagesContainer"
          class="flex-1 overflow-y-auto p-4 flex flex-col gap-3"
        >
          <!-- Loading state -->
          <div v-if="chatStore.loading.messages" class="flex justify-center py-6">
            <i class="pi pi-spin pi-spinner text-sky-600 text-xl"></i>
          </div>

          <!-- Empty State -->
          <div v-else-if="chatStore.activeMessages.length === 0" class="flex-1 flex flex-col items-center justify-center text-center p-6 text-slate-400">
            <p class="text-xs">Chưa có tin nhắn nào trong cuộc hội thoại này.</p>
            <p class="text-[11px] mt-1 text-slate-400">Hãy gửi tin nhắn đầu tiên bên dưới.</p>
          </div>

          <!-- Message Items -->
          <template v-else>
            <div
              v-for="msg in chatStore.activeMessages"
              :key="msg.id"
              class="w-full flex"
              :class="getMessageWrapperClass(msg)"
            >
              <!-- Case A: SYSTEM BOT MESSAGE -->
              <div
                v-if="msg.message_type === 'SYSTEMBOT'"
                class="w-full max-w-2xl mx-auto my-1 bg-white border border-slate-200 rounded-[6px] p-3"
              >
                <div class="flex items-center justify-between border-b border-slate-100 pb-1.5 mb-2">
                  <div class="flex items-center gap-1.5">
                    <span class="px-1.5 py-0.5 text-[10px] font-semibold bg-slate-100 border border-slate-200 text-slate-700 rounded-[6px]">
                      Hệ thống
                    </span>
                    <span class="text-xs font-bold text-slate-800">{{ msg.sender_name }}</span>
                  </div>
                  <span class="text-[10px] text-slate-400">{{ formatFullTime(msg.created_at) }}</span>
                </div>

                <p class="text-xs text-slate-700 leading-relaxed font-normal">
                  {{ msg.content }}
                </p>

                <!-- Dữ liệu chi tiết đính kèm nếu có -->
                <div v-if="msg.attachment_url" class="mt-2 pt-2 border-t border-slate-100 text-xs">
                  <a :href="msg.attachment_url" target="_blank" class="text-sky-600 hover:underline">
                    Xem tệp đính kèm
                  </a>
                </div>
              </div>

              <!-- Case B: USER MESSAGE (MY MESSAGE) -->
              <div
                v-else-if="isMyMessage(msg)"
                class="flex flex-col items-end max-w-[70%]"
              >
                <div class="bg-sky-50 border border-sky-200 rounded-[6px] px-3 py-2 text-xs text-slate-800 leading-relaxed shadow-xs">
                  <p class="whitespace-pre-wrap break-words">{{ msg.content }}</p>
                </div>
                <div class="flex items-center gap-1 mt-1 text-[10px] text-slate-400">
                  <span>{{ formatShortTime(msg.created_at) }}</span>
                  <span v-if="msg.read_by && msg.read_by.length > 1" class="text-sky-600">Đã xem</span>
                </div>
              </div>

              <!-- Case C: COLLEAGUE MESSAGE (OTHER USER) -->
              <div
                v-else
                class="flex items-start gap-2 max-w-[70%]"
              >
                <Avatar
                  :label="msg.sender_name ? msg.sender_name.charAt(0) : 'U'"
                  shape="circle"
                  class="!bg-slate-200 !text-slate-700 !font-bold !text-xs !w-7 !h-7 shrink-0 mt-0.5"
                />

                <div class="flex flex-col items-start">
                  <span class="text-[11px] font-semibold text-slate-600 mb-0.5 ml-1">
                    {{ msg.sender_name }}
                  </span>
                  <div class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 text-xs text-slate-800 leading-relaxed shadow-xs">
                    <p class="whitespace-pre-wrap break-words">{{ msg.content }}</p>
                  </div>
                  <span class="text-[10px] text-slate-400 mt-1 ml-1">{{ formatShortTime(msg.created_at) }}</span>
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Typing Indicator Banner -->
        <div v-if="chatStore.getTypingText" class="px-4 py-1 text-[11px] italic text-slate-500 bg-white border-t border-slate-100">
          {{ chatStore.getTypingText }}
        </div>

        <!-- Send Error Banner -->
        <div v-if="sendError" class="px-4 py-1.5 bg-rose-50 border-t border-rose-200 text-rose-600 text-xs flex items-center justify-between">
          <span>{{ sendError }}</span>
          <button type="button" @click="sendError = ''" class="text-rose-500 hover:text-rose-700 text-xs ml-2 cursor-pointer font-bold">&times;</button>
        </div>

        <!-- Chat Input Bar -->
        <footer class="p-3 bg-white border-t border-slate-200">
          <form @submit.prevent="handleSendMessage" class="flex items-center gap-2">
            <input
              ref="messageInput"
              v-model="inputContent"
              type="text"
              placeholder="Nhập nội dung tin nhắn... (Nhấn Enter để gửi)"
              class="flex-1 px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 focus:bg-white text-slate-800"
              @input="onInputTyping"
            />

            <Button
              type="submit"
              icon="pi pi-send"
              :disabled="!inputContent.trim()"
              :loading="chatStore.loading.sending"
              class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-none !rounded-[6px] !px-4 !py-2 !text-xs !font-semibold"
              label="Gửi"
            />
          </form>
        </footer>
      </section>
    </div>

    <!-- 3. DIALOG: BẮT ĐẦU TRÒ CHUYỆN VỚI ĐỒNG NGHIỆP -->
    <Dialog
      v-model:visible="newChatDialogVisible"
      modal
      header="Tin nhắn cho đồng nghiệp"
      class="w-full max-w-md !rounded-[6px]"
    >
      <div class="flex flex-col gap-3 pt-1">
        <div class="relative w-full">
          <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
          <input
            v-model="userSearchQuery"
            type="text"
            placeholder="Tìm theo tên hoặc email nhân viên..."
            class="w-full pl-8 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 text-slate-700"
          />
        </div>

        <div v-if="loadingUsers" class="flex justify-center py-4">
          <i class="pi pi-spin pi-spinner text-sky-600"></i>
        </div>

        <div v-else class="max-h-64 overflow-y-auto divide-y divide-slate-100 border border-slate-200 rounded-[6px]">
          <div
            v-for="user in filteredUsers"
            :key="user.id"
            class="p-2.5 flex items-center justify-between hover:bg-slate-50 cursor-pointer transition-colors"
            @click="handleSelectColleague(user)"
          >
            <div class="flex items-center gap-2.5">
              <div class="relative">
                <Avatar
                  :label="user.name ? user.name.charAt(0) : 'U'"
                  shape="circle"
                  class="!bg-slate-200 !text-slate-700 !font-bold !text-xs !w-8 !h-8"
                />
                <span
                  class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-white"
                  :class="chatStore.isUserOnline(user.id) ? 'bg-emerald-500' : 'bg-slate-300'"
                ></span>
              </div>
              <div class="flex flex-col">
                <span class="text-xs font-semibold text-slate-800">{{ user.name }}</span>
                <span class="text-[11px] text-slate-400">{{ user.department || user.email }}</span>
              </div>
            </div>

            <Button
              icon="pi pi-comments"
              text
              size="small"
              class="!w-7 !h-7 !p-0"
              v-tooltip.left="'Nhắn tin'"
            />
          </div>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useChatStore } from '../../stores/chatStore';
import { identityApi } from '../../api/identity.api';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const router = useRouter();
const chatStore = useChatStore();

function handleExitChat() {
  if (window.history.length > 1) {
    router.back();
  } else {
    router.push('/dashboard');
  }
}

// Local UI state
const searchQuery = ref('');
const inputContent = ref('');
const sendError = ref('');
const messagesContainer = ref(null);
const messageInput = ref(null);

// New Chat Dialog state
const newChatDialogVisible = ref(false);
const usersList = ref([]);
const loadingUsers = ref(false);
const userSearchQuery = ref('');

let typingTimer = null;

// Filtered lists
const filteredChannels = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  return chatStore.conversations.filter(c => {
    if (c.type !== 'Channel') return false;
    if (!query) return true;
    return c.name.toLowerCase().includes(query) || (c.description && c.description.toLowerCase().includes(query));
  });
});

const filteredDirects = computed(() => {
  const query = searchQuery.value.trim().toLowerCase();
  return chatStore.conversations.filter(c => {
    if (c.type !== 'Direct') return false;
    if (!query) return true;
    const partnerName = getDirectPartnerName(c).toLowerCase();
    return partnerName.includes(query);
  });
});

const filteredUsers = computed(() => {
  const query = userSearchQuery.value.trim().toLowerCase();
  const currentUserId = Number(props.currentUser?.id || props.currentUser?.sub || 0);

  return usersList.value.filter(u => {
    if (Number(u.id) === currentUserId) return false; // Không tự chat với chính mình
    if (!query) return true;
    return (u.name && u.name.toLowerCase().includes(query)) ||
           (u.email && u.email.toLowerCase().includes(query));
  });
});

onMounted(async () => {
  // 1. Kết nối SignalR Hub
  await chatStore.initHub();

  // 2. Tải danh sách hội thoại
  await chatStore.fetchConversations();

  // 3. Cuộn xuống cuối
  scrollToBottom();
});

onUnmounted(() => {
  if (typingTimer) clearTimeout(typingTimer);
});

// Tự động cuộn xuống khi có tin nhắn mới
watch(
  () => chatStore.activeMessages.length,
  () => {
    nextTick(() => {
      scrollToBottom();
    });
  }
);

// Cuộn xuống khi chuyển hội thoại
watch(
  () => chatStore.activeConversationId,
  () => {
    nextTick(() => {
      scrollToBottom();
      if (messageInput.value) {
        messageInput.value.focus();
      }
    });
  }
);

function scrollToBottom() {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
  }
}

async function handleSendMessage() {
  const text = inputContent.value.trim();
  if (!text) return;

  sendError.value = '';
  inputContent.value = '';
  chatStore.sendTyping(false);

  try {
    await chatStore.sendMessage(text);
  } catch (err) {
    console.error('Không thể gửi tin nhắn:', err);
    inputContent.value = text;
    sendError.value = err.message || 'Không thể gửi tin nhắn. Vui lòng kiểm tra kết nối dịch vụ chat (cổng 8009).';
  }
}

function onInputTyping() {
  chatStore.sendTyping(true);

  if (typingTimer) clearTimeout(typingTimer);
  typingTimer = setTimeout(() => {
    chatStore.sendTyping(false);
  }, 2000);
}

async function refreshMessages() {
  if (chatStore.activeConversationId) {
    await chatStore.fetchMessages(chatStore.activeConversationId);
  }
}

async function openNewChatDialog() {
  newChatDialogVisible.value = true;
  userSearchQuery.value = '';
  if (usersList.value.length === 0) {
    loadingUsers.value = true;
    try {
      const res = await identityApi.getUsers();
      usersList.value = res.data || [];
    } catch (err) {
      console.error('Lỗi tải danh sách người dùng:', err);
    } finally {
      loadingUsers.value = false;
    }
  }
}

async function handleSelectColleague(user) {
  newChatDialogVisible.value = false;
  try {
    await chatStore.startDirectChat(user.id, user.name);
  } catch (err) {
    console.error('Lỗi bắt đầu chat:', err);
  }
}

function isMyMessage(msg) {
  const currentUserId = Number(props.currentUser?.id || props.currentUser?.sub || 0);
  return Number(msg.sender_id) === currentUserId;
}

function getMessageWrapperClass(msg) {
  if (msg.message_type === 'SYSTEMBOT') return 'justify-center';
  return isMyMessage(msg) ? 'justify-end' : 'justify-start';
}

function getDirectPartnerName(conv) {
  if (!conv) return '';
  const currentUserId = Number(props.currentUser?.id || props.currentUser?.sub || 0);
  const otherMember = conv.members?.find(m => Number(m.user_id) !== currentUserId);
  return otherMember ? (otherMember.user_name || `Người dùng #${otherMember.user_id}`) : conv.name;
}

function isPartnerOnline(conv) {
  if (!conv || conv.type !== 'Direct') return false;
  const currentUserId = Number(props.currentUser?.id || props.currentUser?.sub || 0);
  const otherMember = conv.members?.find(m => Number(m.user_id) !== currentUserId);
  return otherMember ? chatStore.isUserOnline(otherMember.user_id) : false;
}

function formatShortTime(isoString) {
  if (!isoString) return '';
  try {
    const date = new Date(isoString);
    return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
  } catch {
    return '';
  }
}

function formatFullTime(isoString) {
  if (!isoString) return '';
  try {
    const date = new Date(isoString);
    return date.toLocaleString('vi-VN', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit'
    });
  } catch {
    return '';
  }
}
</script>
