<template>
  <div class="flex flex-col min-h-0 h-full bg-white">
    <!-- Tìm kiếm & tạo mới -->
    <div class="p-2.5 border-b border-slate-200 flex flex-col gap-2 shrink-0">
      <div class="flex items-center gap-1.5">
        <IconField class="flex-1">
          <InputIcon class="pi pi-search" />
          <InputText v-model="keyword" placeholder="Tìm hội thoại..." class="w-full !text-xs !py-1.5" />
        </IconField>
        <Button icon="pi pi-user-plus" text size="small" class="!w-8 !h-8 !p-0 !rounded-[6px]" v-tooltip.bottom="'Chat với đồng nghiệp'" @click="emit('new-direct')" />
        <Button icon="pi pi-users" text size="small" class="!w-8 !h-8 !p-0 !rounded-[6px]" v-tooltip.bottom="'Tạo nhóm chat'" @click="emit('new-group')" />
        <Button icon="pi pi-ellipsis-v" text size="small" severity="secondary" class="!w-8 !h-8 !p-0 !rounded-[6px]" @click="settingsMenu.toggle($event)" />
        <Menu ref="settingsMenu" :model="settingsItems" popup />
      </div>
      <div class="flex items-center gap-1">
        <SelectButton v-model="filter" :options="tabs" option-label="label" option-value="value" size="small" class="[&_.p-togglebutton]:!rounded-[6px] [&_.p-togglebutton]:!text-[11px]" aria-label="Lọc hội thoại" />
        <span v-if="chat.connectionState !== 'connected'" class="ml-auto text-[10px] text-amber-600" :title="'Trạng thái kết nối: ' + chat.connectionState">
          {{ chat.connectionState === 'reconnecting' ? 'Đang kết nối lại…' : 'Mất kết nối realtime' }}
        </span>
      </div>
    </div>

    <!-- Danh sách -->
    <div class="flex-1 overflow-y-auto">
      <div v-if="chat.loadingConversations && !chat.conversations.length" class="p-6 text-center text-xs text-slate-400">Đang tải...</div>
      <div v-else-if="!visibleConversations.length" class="p-6 text-center text-xs text-slate-400">
        {{ keyword || filter !== 'all' ? 'Không có hội thoại phù hợp.' : 'Chưa có hội thoại nào. Bấm biểu tượng ở trên để bắt đầu.' }}
      </div>
      <div
        v-for="conv in visibleConversations"
        :key="conv.id"
        role="button"
        tabindex="0"
        class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left border-b border-slate-100 cursor-pointer transition-colors"
        :class="conv.id === chat.activeId ? 'bg-sky-50' : 'hover:bg-slate-50'"
        @click="emit('select', conv.id)"
        @keydown.enter="emit('select', conv.id)"
        @keydown.space.prevent="emit('select', conv.id)"
        @contextmenu.prevent="openContextMenu($event, conv)"
      >
        <ChatAvatar
          :name="chat.conversationName(conv)"
          :url="conv.avatar_url || ''"
          :group="conv.type === 'group'"
          :online="conv.type === 'direct' && chat.isOnline(chat.partnerOf(conv)?.user_id)"
          :size="compact ? 36 : 40"
        />
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1">
            <span class="text-xs text-slate-900 truncate" :class="conv.unread_count ? 'font-bold' : 'font-semibold'">{{ chat.conversationName(conv) }}</span>
            <i v-if="conv.pinned_at" class="pi pi-thumbtack text-[10px] text-slate-400"></i>
            <i v-if="conv.muted" class="pi pi-bell-slash text-[10px] text-slate-400"></i>
            <span class="ml-auto text-[10px] text-slate-400 shrink-0">{{ formatListTime(conv.last_message_at || conv.created_at) }}</span>
          </div>
          <div class="flex items-center gap-1 mt-0.5">
            <span class="text-[11px] truncate flex-1" :class="conv.unread_count ? 'text-slate-800 font-semibold' : 'text-slate-500'">
              <span v-if="typingText(conv)" class="inline-flex items-center gap-1.5 text-sky-600">
                <ChatTypingDots :size="4" />{{ typingText(conv) }}
              </span>
              <template v-else>{{ previewText(conv) }}</template>
            </span>
            <Tag
              v-if="conv.unread_count"
              :value="conv.unread_count > 99 ? '99+' : String(conv.unread_count)"
              severity="danger"
              class="!bg-red-600 !text-white !border-red-600 !rounded-full !font-bold !text-[10px] !w-4 !h-4 !min-w-4 !p-0 !justify-center shrink-0"
            />
          </div>
        </div>
      </div>
    </div>

    <ContextMenu ref="contextMenu" :model="contextItems" />
    <ChatPromptDialog
      v-model:visible="aliasDialog.visible"
      title="Đặt tên gợi nhớ"
      label="Tên gợi nhớ (chỉ mình bạn thấy)"
      hint="Để trống để xoá tên gợi nhớ."
      :maxlength="60"
      :initial="aliasDialog.initial"
      :saving="aliasDialog.saving"
      @submit="saveAlias"
    />
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Menu from 'primevue/menu';
import ContextMenu from 'primevue/contextmenu';
import ChatAvatar from './ChatAvatar.vue';
import ChatPromptDialog from './ChatPromptDialog.vue';
import ChatTypingDots from './ChatTypingDots.vue';
import { useChatStore } from '../../stores/chatStore';
import { formatListTime } from './chatFormat';

defineProps({ compact: Boolean });
const emit = defineEmits(['select', 'new-direct', 'new-group']);

const chat = useChatStore();
const toast = useToast();
const keyword = ref('');
const filter = ref('all');
const tabs = [
  { value: 'all', label: 'Tất cả' },
  { value: 'unread', label: 'Chưa đọc' },
  { value: 'group', label: 'Nhóm' }
];

const visibleConversations = computed(() => {
  const q = keyword.value.trim().toLowerCase();
  return chat.conversations.filter((c) => {
    if (filter.value === 'unread' && !c.unread_count) return false;
    if (filter.value === 'group' && c.type !== 'group') return false;
    return !q || chat.conversationName(c).toLowerCase().includes(q);
  });
});

function previewText(conv) {
  const last = conv.last_message;
  if (!last) return conv.type === 'group' ? 'Nhóm mới tạo' : 'Bắt đầu trò chuyện';
  if (last.type === 'system') return last.content;
  const who = last.sender_id === chat.meId ? 'Bạn' : conv.type === 'group' ? chat.memberName(conv, last.sender_id, last.sender_name) : '';
  return who ? `${who}: ${last.content}` : last.content;
}

function typingText(conv) {
  const names = chat.typingNames(conv.id);
  if (!names.length) return '';
  return conv.type === 'group' ? `${names[0]} đang soạn tin...` : 'Đang soạn tin...';
}

// ---------- Cài đặt ----------
const settingsMenu = ref(null);
const settingsItems = computed(() => [
  { label: 'Đánh dấu tất cả đã đọc', icon: 'pi pi-check-circle', command: () => chat.markAllRead() },
  {
    label: chat.soundEnabled ? 'Tắt âm báo tin mới' : 'Bật âm báo tin mới',
    icon: chat.soundEnabled ? 'pi pi-volume-off' : 'pi pi-volume-up',
    command: () => chat.setSoundEnabled(!chat.soundEnabled)
  }
]);

// ---------- Menu chuột phải ----------
const contextMenu = ref(null);
const contextTarget = ref(null);
const contextItems = computed(() => {
  const conv = contextTarget.value;
  if (!conv) return [];
  const items = [
    { label: conv.pinned_at ? 'Bỏ ghim hội thoại' : 'Ghim hội thoại', icon: 'pi pi-thumbtack', command: () => run(() => chat.setPinned(conv.id, !conv.pinned_at)) },
    { label: conv.muted ? 'Bật thông báo' : 'Tắt thông báo', icon: conv.muted ? 'pi pi-bell' : 'pi pi-bell-slash', command: () => run(() => chat.setMuted(conv.id, !conv.muted)) }
  ];
  if (conv.type === 'direct') items.push({ label: 'Đặt tên gợi nhớ', icon: 'pi pi-pencil', command: () => openAlias(conv) });
  if (conv.unread_count) items.unshift({ label: 'Đánh dấu đã đọc', icon: 'pi pi-check', command: () => chat.markRead(conv.id) });
  return items;
});

function openContextMenu(event, conv) {
  contextTarget.value = conv;
  contextMenu.value.show(event);
}

const aliasDialog = reactive({ visible: false, initial: '', userId: 0, saving: false });

function openAlias(conv) {
  const partner = chat.partnerOf(conv);
  if (!partner) return;
  Object.assign(aliasDialog, { visible: true, userId: partner.user_id, initial: chat.aliases[partner.user_id] || '' });
}

async function saveAlias(alias) {
  aliasDialog.saving = true;
  await run(() => chat.setAlias(aliasDialog.userId, alias));
  aliasDialog.saving = false;
  aliasDialog.visible = false;
}

async function run(action) {
  try {
    await action();
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  }
}
</script>
