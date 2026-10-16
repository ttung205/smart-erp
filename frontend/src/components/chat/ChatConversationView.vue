<template>
  <div class="relative flex flex-col min-h-0 h-full bg-slate-50" @dragover.prevent="dragging = true" @dragleave.self="dragging = false" @drop.prevent="onDrop">
    <!-- Header -->
    <header class="h-14 px-3 flex items-center gap-2.5 bg-white border-b border-slate-200 shrink-0">
      <button v-if="compact || showBack" type="button" class="w-7 h-7 rounded-full hover:bg-slate-100 text-slate-500 cursor-pointer" :class="{ 'md:hidden': !compact }" @click="emit('back')">
        <i class="pi pi-arrow-left text-xs"></i>
      </button>
      <ChatAvatar :name="title" :url="conv.avatar_url || ''" :group="isGroup" :size="36" :online="!isGroup && chat.isOnline(partner?.user_id)" />
      <div class="flex-1 min-w-0">
        <div class="text-sm font-bold text-slate-900 truncate">{{ title }}</div>
        <div class="text-[11px] truncate" :class="typingLabel ? 'text-sky-600 italic' : 'text-slate-500'">{{ typingLabel || subtitle }}</div>
      </div>
      <button type="button" class="head-btn" :class="{ 'text-sky-600 bg-sky-50': searchOpen }" title="Tìm tin nhắn" @click="toggleSearch"><i class="pi pi-search"></i></button>
      <button type="button" class="head-btn" :class="{ 'text-sky-600 bg-sky-50': infoOpen }" title="Thông tin" @click="emit('toggle-info')"><i class="pi pi-info-circle"></i></button>
      <slot name="header-actions" />
    </header>

    <!-- Tìm tin nhắn -->
    <div v-if="searchOpen" class="bg-white border-b border-slate-200 shrink-0">
      <div class="p-2">
        <IconField>
          <InputIcon class="pi pi-search" />
          <InputText ref="searchInput" v-model="searchQuery" placeholder="Tìm trong hội thoại (ít nhất 2 ký tự)..." class="w-full !text-xs !py-1.5" @keydown.esc="toggleSearch" />
        </IconField>
      </div>
      <div v-if="searchQuery.trim().length >= 2" class="max-h-56 overflow-y-auto border-t border-slate-100">
        <div v-if="searching" class="p-3 text-center text-xs text-slate-400">Đang tìm...</div>
        <div v-else-if="!searchHits.length" class="p-3 text-center text-xs text-slate-400">Không có kết quả.</div>
        <button v-for="hit in searchHits" :key="hit.id" type="button" class="w-full px-3 py-2 text-left hover:bg-slate-50 border-b border-slate-50 cursor-pointer" @click="jumpTo(hit.id)">
          <div class="flex justify-between text-[10px] text-slate-400">
            <span>{{ hit.sender ? chat.memberName(conv, hit.sender.id, hit.sender.name) : '' }}</span>
            <span>{{ formatListTime(hit.created_at) }}</span>
          </div>
          <div class="text-xs text-slate-700 line-clamp-2">{{ hit.content }}</div>
        </button>
      </div>
    </div>

    <!-- Tin ghim -->
    <div v-if="pinned.length" class="bg-white border-b border-slate-200 shrink-0">
      <button type="button" class="w-full flex items-center gap-2 px-3 py-1.5 text-left cursor-pointer hover:bg-slate-50" @click="pinsExpanded = !pinsExpanded">
        <i class="pi pi-thumbtack text-sky-600 text-xs"></i>
        <span class="flex-1 min-w-0 text-xs text-slate-700 truncate">
          <span class="font-semibold">{{ pinned[0].sender ? chat.memberName(conv, pinned[0].sender.id, pinned[0].sender.name) : '' }}:</span>
          {{ pinned[0].content || '[Tệp đính kèm]' }}
        </span>
        <span v-if="pinned.length > 1" class="text-[10px] text-slate-400 shrink-0">{{ pinned.length }} tin ghim</span>
        <i class="pi text-[10px] text-slate-400" :class="pinsExpanded ? 'pi-chevron-up' : 'pi-chevron-down'"></i>
      </button>
      <div v-if="pinsExpanded" class="border-t border-slate-100">
        <div v-for="pin in pinned" :key="pin.id" class="flex items-center gap-2 px-3 py-1.5 hover:bg-slate-50">
          <button type="button" class="flex-1 min-w-0 text-left text-xs text-slate-700 truncate cursor-pointer" @click="jumpTo(pin.id)">
            <span class="font-semibold">{{ pin.sender ? chat.memberName(conv, pin.sender.id, pin.sender.name) : '' }}:</span> {{ pin.content || '[Tệp đính kèm]' }}
          </button>
          <button type="button" class="text-[11px] text-slate-400 hover:text-rose-600 cursor-pointer shrink-0" @click="run(() => chat.setMessagePinned(pin.id, false))">Bỏ ghim</button>
        </div>
      </div>
    </div>

    <!-- Danh sách tin -->
    <div ref="scroller" class="flex-1 overflow-y-auto py-2" @scroll="onScroll">
      <div v-if="bucket?.loadingOlder" class="py-2 text-center text-[11px] text-slate-400"><i class="pi pi-spin pi-spinner text-xs"></i> Đang tải tin cũ...</div>
      <div v-else-if="bucket && bucket.loaded && !bucket.hasMore" class="py-3 text-center text-[11px] text-slate-400">Bắt đầu cuộc trò chuyện</div>
      <div v-if="!bucket?.loaded" class="py-10 text-center text-xs text-slate-400">Đang tải tin nhắn...</div>

      <template v-for="row in rows" :key="row.key">
        <div v-if="row.kind === 'day'" class="flex items-center gap-3 px-4 my-3">
          <span class="flex-1 h-px bg-slate-200"></span>
          <span class="text-[10px] font-semibold text-slate-400 uppercase">{{ row.label }}</span>
          <span class="flex-1 h-px bg-slate-200"></span>
        </div>
        <ChatMessageBubble
          v-else
          :class="{ 'chat-highlight': highlightId === row.message.id }"
          :message="row.message"
          :conv="conv"
          :mine="row.message.sender?.id === chat.meId"
          :show-sender="row.showSender"
          :can-moderate="canModerate(row.message)"
          :readers="readersByMessage.get(row.message.id) || []"
          @reply="startReply"
          @edit="startEdit"
          @forward="(m) => (forwardTarget = m)"
          @jump="jumpTo"
        />
      </template>
    </div>

    <button
      v-if="showJumpToBottom"
      type="button"
      class="absolute right-4 bottom-24 w-9 h-9 rounded-full bg-white border border-slate-200 shadow-md text-slate-600 hover:text-sky-600 cursor-pointer z-10"
      title="Tin mới nhất"
      @click="scrollToBottom(true)"
    >
      <i class="pi pi-arrow-down text-xs"></i>
    </button>

    <ChatComposer ref="composer" :conv="conv" :reply-to="replyTo" :editing="editing" @cancel="clearMode" @sent="onSent" />

    <div v-if="dragging" class="absolute inset-0 bg-sky-50/90 border-2 border-dashed border-sky-400 rounded-[6px] flex items-center justify-center z-30 pointer-events-none">
      <span class="text-sm font-semibold text-sky-700"><i class="pi pi-upload mr-2"></i>Thả tệp để gửi kèm</span>
    </div>

    <ChatForwardDialog
      :visible="!!forwardTarget"
      :message="forwardTarget"
      :saving="forwarding"
      @update:visible="(v) => !v && (forwardTarget = null)"
      @confirm="forward"
    />
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import ChatAvatar from './ChatAvatar.vue';
import ChatMessageBubble from './ChatMessageBubble.vue';
import ChatComposer from './ChatComposer.vue';
import ChatForwardDialog from './ChatForwardDialog.vue';
import { useChatStore } from '../../stores/chatStore';
import { chatApi } from '../../api/chat.api';
import { dayKey, formatDayLabel, formatListTime } from './chatFormat';

const GROUP_GAP_MS = 5 * 60 * 1000;
const ROLE_RANK = { member: 1, deputy: 2, owner: 3 };

const props = defineProps({
  conv: { type: Object, required: true },
  compact: Boolean,
  /** Trang /chat trên màn hình hẹp: hiện nút quay về danh sách */
  showBack: Boolean,
  infoOpen: Boolean
});
const emit = defineEmits(['back', 'toggle-info']);

const chat = useChatStore();
const toast = useToast();
const scroller = ref(null);
const composer = ref(null);
const replyTo = ref(null);
const editing = ref(null);
const forwardTarget = ref(null);
const forwarding = ref(false);
const highlightId = ref(null);
const showJumpToBottom = ref(false);
const pinsExpanded = ref(false);
const dragging = ref(false);

const isGroup = computed(() => props.conv.type === 'group');
const partner = computed(() => chat.partnerOf(props.conv));
const title = computed(() => chat.conversationName(props.conv));
const bucket = computed(() => chat.messages[props.conv.id]);
const pinned = computed(() => chat.pins[props.conv.id] || []);

const subtitle = computed(() => {
  if (isGroup.value) {
    const onlineCount = props.conv.members.filter((m) => m.user_id !== chat.meId && chat.isOnline(m.user_id)).length;
    return `${props.conv.members.length} thành viên${onlineCount ? ` · ${onlineCount} đang online` : ''}`;
  }
  if (partner.value && !partner.value.is_active) return 'Đã ngừng hoạt động';
  return chat.isOnline(partner.value?.user_id) ? 'Đang hoạt động' : 'Ngoại tuyến';
});

const typingLabel = computed(() => {
  const names = chat.typingNames(props.conv.id);
  if (!names.length) return '';
  if (!isGroup.value) return 'Đang soạn tin...';
  return names.length === 1 ? `${names[0]} đang soạn tin...` : `${names.length} người đang soạn tin...`;
});

/** Dòng hiển thị: chèn mốc ngày, gộp tin liên tiếp của cùng người trong 5 phút. */
const rows = computed(() => {
  const result = [];
  let prev = null;
  for (const message of bucket.value?.items || []) {
    const key = dayKey(message.created_at);
    if (!prev || dayKey(prev.created_at) !== key) result.push({ kind: 'day', key: `day-${key}`, label: formatDayLabel(message.created_at) });
    const sameRun = prev && prev.type !== 'system' && message.type !== 'system' && prev.sender?.id === message.sender?.id
      && dayKey(prev.created_at) === key && new Date(message.created_at) - new Date(prev.created_at) < GROUP_GAP_MS;
    result.push({ kind: 'message', key: `m-${message.id}`, message, showSender: !sameRun });
    prev = message;
  }
  return result;
});

/** Avatar "đã xem": đặt dưới tin cuối mà mỗi người đã đọc (không tính tin của chính họ). */
const readersByMessage = computed(() => {
  const map = new Map();
  const items = bucket.value?.items || [];
  for (const member of props.conv.members) {
    if (member.user_id === chat.meId || !member.last_read_message_id) continue;
    let anchor = null;
    for (let i = items.length - 1; i >= 0; i--) {
      const m = items[i];
      if (m.id <= member.last_read_message_id && m.type !== 'system' && m.sender?.id !== member.user_id) {
        anchor = m;
        break;
      }
    }
    if (!anchor) continue;
    if (!map.has(anchor.id)) map.set(anchor.id, []);
    map.get(anchor.id).push({ user_id: member.user_id, name: chat.displayName(member.user_id, member.name) });
  }
  return map;
});

function canModerate(message) {
  if (!isGroup.value || !message.sender) return false;
  const myRank = ROLE_RANK[props.conv.my_role] || 1;
  const sender = props.conv.members.find((m) => m.user_id === message.sender.id);
  return myRank >= ROLE_RANK.deputy && myRank > (ROLE_RANK[sender?.role] || 1);
}

// ---------- Cuộn ----------
function nearBottom() {
  const el = scroller.value;
  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 120;
}

function scrollToBottom(smooth = false) {
  nextTick(() => {
    const el = scroller.value;
    if (el) el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
  });
}

async function onScroll() {
  const el = scroller.value;
  if (!el) return;
  showJumpToBottom.value = !nearBottom();
  if (el.scrollTop < 80 && bucket.value?.hasMore && !bucket.value.loadingOlder) {
    const previousHeight = el.scrollHeight;
    const added = await chat.loadOlder(props.conv.id);
    // Giữ nguyên vị trí đang đọc sau khi chèn tin cũ lên trên
    if (added) nextTick(() => (el.scrollTop = el.scrollHeight - previousHeight + el.scrollTop));
  }
}

watch(() => bucket.value?.items.length, (length, oldLength) => {
  if (!length) return;
  const last = bucket.value.items[length - 1];
  if (!oldLength || last.sender?.id === chat.meId || nearBottom()) scrollToBottom(!!oldLength);
});

async function jumpTo(messageId) {
  const found = await chat.ensureMessageLoaded(props.conv.id, messageId);
  if (!found) {
    toast.add({ severity: 'info', summary: 'Tin nhắn không còn hoặc đã bị ẩn.', life: 2500 });
    return;
  }
  pinsExpanded.value = false;
  await nextTick();
  document.getElementById(`chat-msg-${messageId}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  highlightId.value = messageId;
  setTimeout(() => (highlightId.value = null), 1600);
}

// ---------- Trả lời / sửa / chuyển tiếp ----------
function startReply(message) {
  editing.value = null;
  replyTo.value = message;
}

function startEdit(message) {
  replyTo.value = null;
  editing.value = message;
}

function clearMode() {
  replyTo.value = null;
  editing.value = null;
}

function onSent() {
  clearMode();
  scrollToBottom(true);
}

async function forward(conversationIds) {
  forwarding.value = true;
  try {
    await chat.forward(forwardTarget.value.id, conversationIds);
    toast.add({ severity: 'success', summary: `Đã chuyển tiếp tới ${conversationIds.length} hội thoại`, life: 2500 });
    forwardTarget.value = null;
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Không chuyển tiếp được', detail: e.message, life: 4000 });
  } finally {
    forwarding.value = false;
  }
}

function onDrop(event) {
  dragging.value = false;
  const files = [...(event.dataTransfer?.files || [])];
  if (files.length) composer.value?.addFiles(files);
}

// ---------- Tìm kiếm ----------
const searchOpen = ref(false);
const searchQuery = ref('');
const searchHits = ref([]);
const searching = ref(false);
const searchInput = ref(null);
let searchTimer = null;

function toggleSearch() {
  searchOpen.value = !searchOpen.value;
  searchQuery.value = '';
  searchHits.value = [];
  if (searchOpen.value) nextTick(() => searchInput.value?.$el?.focus());
}

watch(searchQuery, (q) => {
  clearTimeout(searchTimer);
  if (q.trim().length < 2) return;
  searchTimer = setTimeout(async () => {
    searching.value = true;
    try {
      searchHits.value = await chatApi.search(props.conv.id, q.trim());
    } catch {
      searchHits.value = [];
    } finally {
      searching.value = false;
    }
  }, 300);
});

async function run(action) {
  try {
    await action();
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  }
}

// ---------- Đánh dấu đã đọc khi quay lại cửa sổ ----------
const onFocus = () => {
  if (document.visibilityState === 'visible') chat.markRead(props.conv.id);
};

watch(() => props.conv.id, () => {
  clearMode();
  searchOpen.value = false;
  pinsExpanded.value = false;
  scrollToBottom();
});

onMounted(() => {
  scrollToBottom();
  window.addEventListener('focus', onFocus);
  document.addEventListener('visibilitychange', onFocus);
});

onBeforeUnmount(() => {
  window.removeEventListener('focus', onFocus);
  document.removeEventListener('visibilitychange', onFocus);
});
</script>

<style scoped>
.head-btn {
  width: 32px;
  height: 32px;
  border-radius: 9999px;
  color: rgb(100 116 139);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.head-btn:hover {
  background: rgb(241 245 249);
  color: rgb(2 132 199);
}
:deep(.chat-highlight) {
  animation: chat-flash 1.6s ease-out;
}
@keyframes chat-flash {
  0%,
  40% {
    background: rgb(224 242 254);
  }
  100% {
    background: transparent;
  }
}
</style>
