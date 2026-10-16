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
        v-if="chat.unreadTotal > 0"
        class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-rose-600 text-white text-[10px] font-bold flex items-center justify-center border-2 border-white shadow-xs"
      >
        {{ chat.unreadTotal > 99 ? '99+' : chat.unreadTotal }}
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
              v-if="chat.unreadTotal > 0"
              class="px-1.5 py-0.2 text-[10px] font-bold bg-rose-600 text-white rounded-[4px]"
            >
              {{ chat.unreadTotal }}
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

      <!-- TAB 2: TIN NHẮN NỘI BỘ (dùng chung component với trang /chat) -->
      <div v-if="activeTab === 'chat'" class="flex-1 flex flex-col min-h-0">
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
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import { useChatStore } from '../../stores/chatStore';
import Button from 'primevue/button';
import ChatConversationList from '../chat/ChatConversationList.vue';
import ChatConversationView from '../chat/ChatConversationView.vue';
import ChatInfoPanel from '../chat/ChatInfoPanel.vue';
import ChatUserPickerDialog from '../chat/ChatUserPickerDialog.vue';

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
const chat = useChatStore();
const toast = useToast();

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
  if (visible.value) chat.init();
}

function openFullScreenChat() {
  const id = chat.activeId;
  visible.value = false;
  router.push(id ? `/chat/${id}` : '/chat');
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

// --- TAB 2: TIN NHẮN NỘI BỘ ---
const infoOpen = ref(false);
const picker = ref(null);
const creating = ref(false);

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

// Widget đóng hoặc chuyển tab thì không còn "đang xem" hội thoại nào (để tin mới được tính chưa đọc)
watch([visible, activeTab], ([isVisible, tab]) => {
  if (!isVisible || tab !== 'chat') {
    infoOpen.value = false;
    if (!isChatRoute.value) chat.openConversation(null);
  }
});

// Đăng nhập/đăng xuất: khởi động lại kết nối chat theo token mới
watch(() => props.currentUser?.sub ?? props.currentUser?.id, () => chat.init());

function formatCurrentTime() {
  const date = new Date();
  return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}

onMounted(() => chat.init());
</script>
