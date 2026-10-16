<template>
  <div v-if="!isChatRoute">
    <!-- 1. FLOATING CIRCULAR TRIGGER BUTTON (ẩn đi khi mở dialog hoặc khi đang ở trang /chat) -->
    <button
      v-if="!visible"
      type="button"
      class="fixed bottom-[54px] right-6 z-40 w-12 h-12 rounded-full bg-sky-600 hover:bg-sky-700 text-white flex items-center justify-center border border-sky-500 shadow-md cursor-pointer transition-transform hover:scale-105 active:scale-95"
      @click="toggleOpen"
      v-tooltip.left="'Trung tâm trao đổi & trợ lý'"
    >
      <i class="pi pi-comments text-lg"></i>

      <!-- Unread badge on the circular button -->
      <span
        v-if="chatStore.totalUnreadCount > 0"
        class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-rose-600 text-white text-[10px] font-bold flex items-center justify-center border-2 border-white shadow-xs"
      >
        {{ chatStore.totalUnreadCount > 99 ? '99+' : chatStore.totalUnreadCount }}
      </span>
    </button>

    <!-- 2. POPUP COMMUNICATION HUB PANEL (thay thế vị trí của nút khi mở) -->
    <div
      v-if="visible"
      class="fixed bottom-[54px] right-6 w-[420px] max-w-[calc(100vw-2rem)] h-[560px] max-h-[calc(100vh-5rem)] bg-white border border-slate-200 rounded-[6px] shadow-xl z-50 flex flex-col overflow-hidden"
    >
      <!-- Panel Top Bar with 2 Tabs and Header Actions -->
      <header class="h-12 bg-white border-b border-slate-200 px-3 flex items-center justify-between shrink-0 select-none">
        <!-- Two Flat Tabs -->
        <div class="flex items-center gap-1">
          <button
            type="button"
            class="px-3 py-1.5 text-xs font-semibold rounded-[4px] transition-colors cursor-pointer"
            :class="activeTab === 'assistant'
              ? 'bg-sky-50 text-sky-700 border border-sky-200'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
            @click="activeTab = 'assistant'"
          >
            Tra cứu nghiệp vụ
          </button>

          <button
            type="button"
            class="px-3 py-1.5 text-xs font-semibold rounded-[4px] transition-colors cursor-pointer flex items-center gap-1.5"
            :class="activeTab === 'chat'
              ? 'bg-sky-50 text-sky-700 border border-sky-200'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 border border-transparent'"
            @click="activeTab = 'chat'"
          >
            <span>Tin nhắn nội bộ</span>
            <span
              v-if="chatStore.totalUnreadCount > 0"
              class="px-1.5 py-0.2 text-[10px] font-bold bg-rose-600 text-white rounded-[4px]"
            >
              {{ chatStore.totalUnreadCount }}
            </span>
          </button>
        </div>

        <!-- Header Action Controls -->
        <div class="flex items-center gap-1">
          <Button
            v-if="activeTab === 'chat'"
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

      <!-- TAB 1: TRA CỨU NGHIỆP VỤ (AI ASSISTANT) -->
      <div v-show="activeTab === 'assistant'" class="flex-1 flex flex-col min-h-0 bg-slate-50">
        <!-- Message Stream -->
        <div ref="aiScrollContainer" class="flex-1 overflow-y-auto p-3 flex flex-col gap-2.5">
          <!-- Initial Info Box -->
          <div class="bg-white border border-slate-200 rounded-[6px] p-3 text-xs text-slate-700 shadow-xs">
            <div class="font-bold text-slate-900 mb-1">
              Xin chào, {{ userName }}
            </div>
            <p class="text-slate-600 m-0 leading-relaxed">
              Tôi là trợ lý tra cứu nghiệp vụ hệ thống ERP. Bạn có thể hỏi đáp về quy trình, mã hàng SKU, số liệu tồn kho hoặc kiểm tra phân quyền tài khoản.
            </p>
          </div>

          <!-- Quick Suggestion Chips -->
          <div class="flex flex-wrap gap-1.5 pt-1">
            <button
              v-for="suggestion in aiSuggestions"
              :key="suggestion"
              type="button"
              class="px-2.5 py-1 text-[11px] bg-white hover:bg-sky-50 border border-slate-200 hover:border-sky-300 text-slate-700 rounded-[4px] cursor-pointer transition-colors"
              @click="sendAiQuery(suggestion)"
            >
              {{ suggestion }}
            </button>
          </div>

          <!-- Conversational Messages with AI -->
          <div
            v-for="(msg, idx) in aiMessages"
            :key="idx"
            class="flex flex-col"
            :class="msg.role === 'user' ? 'items-end' : 'items-start'"
          >
            <div
              class="max-w-[85%] px-3 py-2 text-xs rounded-[6px] leading-relaxed shadow-xs"
              :class="msg.role === 'user'
                ? 'bg-sky-600 text-white'
                : 'bg-white border border-slate-200 text-slate-800'"
            >
              <p class="whitespace-pre-wrap break-words m-0">{{ msg.text }}</p>
            </div>
            <span class="text-[10px] text-slate-400 mt-0.5 px-1">
              {{ msg.time }}
            </span>
          </div>

          <!-- AI Loading Typing Indicator -->
          <div v-if="aiLoading" class="flex items-center gap-1.5 text-xs text-slate-400 px-2 py-1">
            <i class="pi pi-spin pi-spinner text-sky-600"></i>
            <span>Đang tra cứu dữ liệu...</span>
          </div>
        </div>

        <!-- AI Query Input Bar -->
        <footer class="p-2.5 bg-white border-t border-slate-200">
          <form @submit.prevent="handleAiSubmit" class="flex items-center gap-1.5">
            <input
              v-model="aiInput"
              type="text"
              placeholder="Nhập nội dung cần tra cứu..."
              class="flex-1 px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 focus:bg-white text-slate-800"
            />
            <Button
              type="submit"
              icon="pi pi-send"
              size="small"
              :disabled="!aiInput.trim() || aiLoading"
              class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-none !rounded-[6px] !w-8 !h-8 !p-0"
              v-tooltip.top="'Gửi câu hỏi'"
            />
          </form>
        </footer>
      </div>

      <!-- TAB 2: TIN NHẮN NỘI BỘ (REALTIME ENTERPRISE CHAT) -->
      <div v-show="activeTab === 'chat'" class="flex-1 flex flex-col min-h-0 bg-slate-50">
        <!-- VIEW A: DANH SÁCH HỘI THOẠI (KHI CHƯA CHỌN PHÒNG BAN/NGƯỜI DÙNG) -->
        <div v-if="!chatStore.activeConversationId" class="flex-1 flex flex-col min-h-0 bg-white">
          <!-- Search Header -->
          <div class="p-2.5 border-b border-slate-200 flex items-center justify-between gap-2">
            <div class="relative flex-1">
              <i class="pi pi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
              <input
                v-model="chatSearchQuery"
                type="text"
                placeholder="Tìm kênh hoặc người dùng..."
                class="w-full pl-7 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 focus:bg-white text-slate-800"
              />
            </div>
            <Button
              icon="pi pi-refresh"
              text
              size="small"
              class="!w-7 !h-7 !p-0"
              :loading="chatStore.loading.conversations"
              v-tooltip.bottom="'Tải lại danh sách'"
              @click="chatStore.fetchConversations"
            />
          </div>

          <!-- Conversation List Items -->
          <div class="flex-1 overflow-y-auto divide-y divide-slate-100">
            <!-- Channels Section -->
            <div class="py-1.5">
              <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                Kênh phòng ban
              </div>

              <div
                v-for="channel in filteredChannels"
                :key="channel.id"
                class="px-3 py-2 flex items-center gap-2.5 hover:bg-slate-50 cursor-pointer transition-colors"
                @click="openChatConversation(channel.id)"
              >
                <div class="w-7 h-7 rounded-[4px] bg-slate-100 border border-slate-200 flex items-center justify-center font-bold text-xs text-slate-600 shrink-0">
                  #
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1 leading-tight">
                    <span class="text-xs font-semibold text-slate-800 truncate">{{ channel.name }}</span>
                    <span v-if="channel.last_message" class="text-[10px] text-slate-400 shrink-0">
                      {{ formatShortTime(channel.last_message.created_at) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between gap-1 mt-0.5">
                    <p class="text-[11px] text-slate-500 truncate m-0">
                      <span v-if="channel.last_message">
                        <span class="font-medium text-slate-600">{{ channel.last_message.sender_name }}:</span>
                        {{ channel.last_message.content }}
                      </span>
                      <span v-else class="text-slate-400 italic">Chưa có tin nhắn</span>
                    </p>
                    <span
                      v-if="channel.unread_count > 0"
                      class="px-1.5 py-0.2 text-[10px] font-bold bg-sky-600 text-white rounded-[4px] shrink-0"
                    >
                      {{ channel.unread_count }}
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Direct Messages Section -->
            <div class="py-1.5">
              <div class="px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                Tin nhắn trực tiếp
              </div>

              <div v-if="filteredDirects.length === 0" class="px-3 py-2 text-xs text-slate-400 italic">
                Chưa có cuộc trò chuyện 1-1.
              </div>

              <div
                v-for="dm in filteredDirects"
                :key="dm.id"
                class="px-3 py-2 flex items-center gap-2.5 hover:bg-slate-50 cursor-pointer transition-colors"
                @click="openChatConversation(dm.id)"
              >
                <div class="relative shrink-0">
                  <Avatar
                    :label="getDirectPartnerName(dm).charAt(0)"
                    shape="circle"
                    class="!bg-slate-200 !text-slate-700 !font-bold !text-xs !w-7 !h-7"
                  />
                  <span
                    class="absolute bottom-0 right-0 w-2 h-2 rounded-full border border-white"
                    :class="isPartnerOnline(dm) ? 'bg-emerald-500' : 'bg-slate-300'"
                  ></span>
                </div>

                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-1 leading-tight">
                    <span class="text-xs font-semibold text-slate-800 truncate">{{ getDirectPartnerName(dm) }}</span>
                    <span v-if="dm.last_message" class="text-[10px] text-slate-400 shrink-0">
                      {{ formatShortTime(dm.last_message.created_at) }}
                    </span>
                  </div>
                  <div class="flex items-center justify-between gap-1 mt-0.5">
                    <p class="text-[11px] text-slate-500 truncate m-0">
                      <span v-if="dm.last_message">{{ dm.last_message.content }}</span>
                      <span v-else class="text-slate-400 italic">Bắt đầu trò chuyện</span>
                    </p>
                    <span
                      v-if="dm.unread_count > 0"
                      class="px-1.5 py-0.2 text-[10px] font-bold bg-sky-600 text-white rounded-[4px] shrink-0"
                    >
                      {{ dm.unread_count }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- VIEW B: KHUNG CHAT CHI TIẾT (KHI ĐANG CHỌN 1 HỘI THOẠI) -->
        <div v-else class="flex-1 flex flex-col min-h-0 bg-slate-50">
          <!-- Chat Sub-header -->
          <div class="h-10 px-3 bg-white border-b border-slate-200 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2 min-w-0">
              <Button
                icon="pi pi-arrow-left"
                text
                size="small"
                class="!w-6 !h-6 !p-0 !text-slate-600"
                v-tooltip.bottom="'Danh sách hội thoại'"
                @click="chatStore.activeConversationId = null"
              />

              <div class="flex flex-col min-w-0 leading-tight">
                <span class="text-xs font-bold text-slate-800 truncate">
                  {{ chatStore.activeConversation?.type === 'Channel' ? chatStore.activeConversation.name : getDirectPartnerName(chatStore.activeConversation) }}
                </span>
                <span class="text-[10px] text-slate-400 truncate">
                  {{ chatStore.activeConversation?.type === 'Channel' ? (chatStore.activeConversation.description || 'Kênh phòng ban') : (isPartnerOnline(chatStore.activeConversation) ? 'Trực tuyến' : 'Ngoại tuyến') }}
                </span>
              </div>
            </div>

            <Button
              icon="pi pi-refresh"
              text
              size="small"
              class="!w-6 !h-6 !p-0"
              :loading="chatStore.loading.messages"
              v-tooltip.bottom="'Tải lại tin nhắn'"
              @click="chatStore.fetchMessages(chatStore.activeConversationId)"
            />
          </div>

          <!-- Chat Messages Stream -->
          <div ref="chatScrollContainer" class="flex-1 overflow-y-auto p-3 flex flex-col gap-2.5">
            <div v-if="chatStore.loading.messages" class="flex justify-center py-6 text-slate-400">
              <i class="pi pi-spin pi-spinner text-sky-600"></i>
            </div>

            <div v-else-if="chatStore.activeMessages.length === 0" class="flex-1 flex flex-col items-center justify-center text-center p-4 text-slate-400 text-xs">
              <p class="m-0">Chưa có tin nhắn trong cuộc hội thoại này.</p>
              <p class="text-[11px] mt-1 text-slate-400">Hãy gửi tin nhắn đầu tiên bên dưới.</p>
            </div>

            <template v-else>
              <div
                v-for="msg in chatStore.activeMessages"
                :key="msg.id"
                class="w-full flex"
                :class="msg.message_type === 'SYSTEMBOT' ? 'justify-center' : (isMyMessage(msg) ? 'justify-end' : 'justify-start')"
              >
                <!-- Bot message -->
                <div
                  v-if="msg.message_type === 'SYSTEMBOT'"
                  class="w-full bg-white border border-slate-200 rounded-[6px] p-2.5 text-xs text-slate-700 shadow-xs"
                >
                  <div class="flex items-center justify-between border-b border-slate-100 pb-1 mb-1.5">
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                      Hệ thống
                    </span>
                    <span class="text-[10px] text-slate-400">{{ formatShortTime(msg.created_at) }}</span>
                  </div>
                  <p class="m-0 leading-relaxed">{{ msg.content }}</p>
                </div>

                <!-- My message -->
                <div
                  v-else-if="isMyMessage(msg)"
                  class="flex flex-col items-end max-w-[80%]"
                >
                  <div class="bg-sky-50 border border-sky-200 rounded-[6px] px-2.5 py-1.5 text-xs text-slate-800 leading-relaxed shadow-xs">
                    <p class="whitespace-pre-wrap break-words m-0">{{ msg.content }}</p>
                  </div>
                  <span class="text-[9px] text-slate-400 mt-0.5">{{ formatShortTime(msg.created_at) }}</span>
                </div>

                <!-- Partner message -->
                <div
                  v-else
                  class="flex items-start gap-1.5 max-w-[80%]"
                >
                  <Avatar
                    :label="msg.sender_name ? msg.sender_name.charAt(0) : 'U'"
                    shape="circle"
                    class="!bg-slate-200 !text-slate-700 !font-bold !text-[10px] !w-6 !h-6 shrink-0 mt-0.5"
                  />
                  <div class="flex flex-col items-start min-w-0">
                    <span class="text-[10px] font-semibold text-slate-500 mb-0.5 ml-0.5">{{ msg.sender_name }}</span>
                    <div class="bg-white border border-slate-200 rounded-[6px] px-2.5 py-1.5 text-xs text-slate-800 leading-relaxed shadow-xs">
                      <p class="whitespace-pre-wrap break-words m-0">{{ msg.content }}</p>
                    </div>
                    <span class="text-[9px] text-slate-400 mt-0.5 ml-0.5">{{ formatShortTime(msg.created_at) }}</span>
                  </div>
                </div>
              </div>
            </template>
          </div>

          <!-- Typing Indicator -->
          <div v-if="chatStore.getTypingText" class="px-3 py-1 text-[10px] italic text-slate-500 bg-white border-t border-slate-100">
            {{ chatStore.getTypingText }}
          </div>

          <!-- Error Banner if send failed -->
          <div v-if="chatSendError" class="px-3 py-1 bg-rose-50 border-t border-rose-200 text-rose-600 text-[11px] flex items-center justify-between">
            <span>{{ chatSendError }}</span>
            <button type="button" @click="chatSendError = ''" class="font-bold text-rose-500">&times;</button>
          </div>

          <!-- Chat Message Input Bar -->
          <footer class="p-2.5 bg-white border-t border-slate-200">
            <form @submit.prevent="handleSendChatMessage" class="flex items-center gap-1.5">
              <input
                ref="chatInputRef"
                v-model="chatInput"
                type="text"
                placeholder="Nhập tin nhắn..."
                class="flex-1 px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-[6px] focus:outline-none focus:border-sky-500 focus:bg-white text-slate-800"
              />
              <Button
                type="submit"
                icon="pi pi-send"
                size="small"
                :disabled="!chatInput.trim()"
                :loading="chatStore.loading.sending"
                class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-none !rounded-[6px] !w-8 !h-8 !p-0"
                v-tooltip.top="'Gửi'"
              />
            </form>
          </footer>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useChatStore } from '../../stores/chatStore';
import Button from 'primevue/button';
import Avatar from 'primevue/avatar';

const props = defineProps({
  userName: {
    type: String,
    default: 'người dùng'
  },
  currentUser: {
    type: Object,
    default: null
  }
});

const route = useRoute();
const router = useRouter();
const chatStore = useChatStore();

// Hide widget completely when user is already on the dedicated chat page
const isChatRoute = computed(() => {
  return route.path ? route.path.startsWith('/chat') : false;
});

// Panel State
const visible = ref(false);
const activeTab = ref('assistant'); // 'assistant' | 'chat'

// --- TAB 1: AI ASSISTANT STATE & ACTIONS ---
const aiInput = ref('');
const aiLoading = ref(false);
const aiScrollContainer = ref(null);
const aiMessages = ref([
  {
    role: 'system',
    text: 'Hệ thống đã sẵn sàng hỗ trợ. Bạn có thể đặt câu hỏi về danh mục sản phẩm, chính sách giá, quy trình mua bán hoặc phân quyền.',
    time: formatCurrentTime()
  }
]);

const aiSuggestions = [
  'Tra cứu mã SKU sản phẩm',
  'Kiểm tra quyền hạn RBAC của tôi',
  'Quy trình phê duyệt đơn mua (PO)'
];

function toggleOpen() {
  visible.value = !visible.value;
  if (visible.value) {
    if (chatStore.connectionState === 'DISCONNECTED') {
      chatStore.initHub();
      chatStore.fetchConversations();
    }
  }
}

function openFullScreenChat() {
  visible.value = false;
  router.push('/chat');
}

async function sendAiQuery(text) {
  aiInput.value = text;
  await handleAiSubmit();
}

async function handleAiSubmit() {
  const query = aiInput.value.trim();
  if (!query || aiLoading.value) return;

  aiMessages.value.push({
    role: 'user',
    text: query,
    time: formatCurrentTime()
  });

  aiInput.value = '';
  aiLoading.value = true;
  scrollAiToBottom();

  setTimeout(() => {
    let reply = '';
    const qLower = query.toLowerCase();

    if (qLower.includes('sku') || qLower.includes('sản phẩm')) {
      reply = 'Để tra cứu danh mục SKU, bạn có thể truy cập menu Phân Hệ Nghiệp Vụ > Danh mục sản phẩm (/catalog/product) hoặc dùng thanh tìm kiếm nhanh.';
    } else if (qLower.includes('quyền') || qLower.includes('rbac')) {
      const userRoles = props.currentUser?.roles || ['STAFF'];
      reply = `Tài khoản của bạn hiện có vai trò: ${userRoles.join(', ')}. Quyền hạn bao gồm xem dữ liệu chứng từ và gửi trao đổi nội bộ.`;
    } else if (qLower.includes('po') || qLower.includes('đơn mua')) {
      reply = 'Quy trình đơn mua PO: Yêu cầu mua sắm (PR) -> Phê duyệt PR -> Lập đơn đặt hàng (PO) -> Phê duyệt PO -> Nhập kho mua hàng (GRPO).';
    } else {
      reply = `Đã ghi nhận yêu cầu: "${query}". Hệ thống Smart ERP đang xử lý truy vấn dữ liệu nghiệp vụ liên quan.`;
    }

    aiMessages.value.push({
      role: 'system',
      text: reply,
      time: formatCurrentTime()
    });

    aiLoading.value = false;
    scrollAiToBottom();
  }, 600);
}

function scrollAiToBottom() {
  nextTick(() => {
    if (aiScrollContainer.value) {
      aiScrollContainer.value.scrollTop = aiScrollContainer.value.scrollHeight;
    }
  });
}

// --- TAB 2: CHAT REALTIME STATE & ACTIONS ---
const chatSearchQuery = ref('');
const chatInput = ref('');
const chatSendError = ref('');
const chatScrollContainer = ref(null);
const chatInputRef = ref(null);

const filteredChannels = computed(() => {
  const query = chatSearchQuery.value.trim().toLowerCase();
  return chatStore.conversations.filter(c => {
    if (c.type !== 'Channel') return false;
    if (!query) return true;
    return c.name.toLowerCase().includes(query) || (c.description && c.description.toLowerCase().includes(query));
  });
});

const filteredDirects = computed(() => {
  const query = chatSearchQuery.value.trim().toLowerCase();
  return chatStore.conversations.filter(c => {
    if (c.type !== 'Direct') return false;
    if (!query) return true;
    return getDirectPartnerName(c).toLowerCase().includes(query);
  });
});

function openChatConversation(convId) {
  chatStore.selectConversation(convId);
  nextTick(() => {
    scrollChatToBottom();
    if (chatInputRef.value) {
      chatInputRef.value.focus();
    }
  });
}

async function handleSendChatMessage() {
  const text = chatInput.value.trim();
  if (!text) return;

  chatSendError.value = '';
  chatInput.value = '';
  chatStore.sendTyping(false);

  try {
    await chatStore.sendMessage(text);
    scrollChatToBottom();
  } catch (err) {
    console.error('Lỗi gửi tin nhắn mini widget:', err);
    chatInput.value = text;
    chatSendError.value = err.message || 'Lỗi gửi tin nhắn. Hãy kiểm tra kết nối chat service.';
  }
}

function scrollChatToBottom() {
  nextTick(() => {
    if (chatScrollContainer.value) {
      chatScrollContainer.value.scrollTop = chatScrollContainer.value.scrollHeight;
    }
  });
}

watch(
  () => chatStore.activeMessages.length,
  () => {
    if (visible.value && activeTab.value === 'chat') {
      scrollChatToBottom();
    }
  }
);

function isMyMessage(msg) {
  const currentUserId = Number(props.currentUser?.id || props.currentUser?.sub || 0);
  return Number(msg.sender_id) === currentUserId;
}

function getDirectPartnerName(conv) {
  if (!conv) return '';
  const currentUserId = Number(props.currentUser?.id || props.currentUser?.sub || 0);
  const otherMember = conv.members?.find(m => Number(m.user_id) !== currentUserId);
  return otherMember ? (otherMember.user_name || `User #${otherMember.user_id}`) : conv.name;
}

function isPartnerOnline(conv) {
  if (!conv || conv.type !== 'Direct') return false;
  const currentUserId = Number(props.currentUser?.id || props.currentUser?.sub || 0);
  const otherMember = conv.members?.find(m => Number(m.user_id) !== currentUserId);
  return otherMember ? chatStore.isUserOnline(otherMember.user_id) : false;
}

function formatCurrentTime() {
  const date = new Date();
  return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
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

onMounted(() => {
  chatStore.initHub();
  chatStore.fetchConversations();
});
</script>
