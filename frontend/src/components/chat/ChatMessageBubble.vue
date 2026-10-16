<template>
  <!-- Tin hệ thống -->
  <div v-if="message.type === 'system'" class="flex justify-center my-2 px-4">
    <span class="px-3 py-1 text-[11px] text-slate-500 bg-slate-100 border border-slate-200 rounded-[6px] text-center">{{ message.content }}</span>
  </div>

  <div v-else :id="`chat-msg-${message.id}`" class="group flex gap-2 px-3 transition-colors duration-700" :class="[mine ? 'flex-row-reverse' : '', showSender ? 'mt-2' : 'mt-0.5']">
    <div class="w-8 shrink-0">
      <ChatAvatar v-if="!mine && showSender" :name="senderName" :size="32" />
    </div>

    <div class="flex flex-col max-w-[75%] min-w-0" :class="mine ? 'items-end' : 'items-start'">
      <span v-if="!mine && showSender && conv.type === 'group'" class="text-[11px] text-slate-500 mb-0.5 px-1">{{ senderName }}</span>

      <div class="flex items-center gap-1" :class="mine ? 'flex-row-reverse' : ''">
        <!-- Nội dung -->
        <div
          class="rounded-[6px] text-[13px] leading-relaxed break-words min-w-0"
          :class="bubbleClass"
        >
          <span v-if="message.recalled" class="italic text-slate-400 px-3 py-2 block">Tin nhắn đã được thu hồi</span>
          <template v-else>
            <div v-if="message.forwarded_from_name" class="px-3 pt-2 text-[11px] italic" :class="mine ? 'text-sky-100' : 'text-slate-400'">
              <i class="pi pi-reply text-[10px] -scale-x-100"></i> Chuyển tiếp từ {{ message.forwarded_from_name }}
            </div>

            <div
              v-if="message.reply_to"
              role="button"
              tabindex="0"
              class="mx-2 mt-2 px-2 py-1 text-[11px] rounded-[6px] border block max-w-full cursor-pointer"
              :class="mine ? 'bg-sky-700 border-sky-500 text-sky-50' : 'bg-slate-50 border-slate-200 text-slate-600'"
              @click="emit('jump', message.reply_to.id)"
              @keydown.enter="emit('jump', message.reply_to.id)"
            >
              <span class="font-semibold block" :class="mine ? 'text-white' : 'text-sky-700'">{{ message.reply_to.sender_name }}</span>
              <span class="line-clamp-2">{{ message.reply_to.recalled ? 'Tin nhắn đã được thu hồi' : message.reply_to.content || '[Tệp đính kèm]' }}</span>
            </div>

            <p v-if="message.content" class="m-0 px-3 py-2 whitespace-pre-wrap">
              <template v-for="(seg, i) in segments" :key="i">
                <a v-if="seg.type === 'link'" :href="seg.value" target="_blank" rel="noopener noreferrer" class="underline break-all" :class="mine ? 'text-white' : 'text-sky-700'">{{ seg.value }}</a>
                <span v-else-if="seg.type === 'mention'" class="font-semibold" :class="mine ? 'text-white' : 'text-sky-700'">{{ seg.value }}</span>
                <template v-else>{{ seg.value }}</template>
              </template>
            </p>

            <div v-if="images.length" class="flex flex-wrap gap-1 p-1">
              <ChatImageThumb v-for="att in images" :key="att.id" :attachment="att" :size="images.length === 1 ? 220 : 110" @open="openImage" />
            </div>
            <div v-if="files.length" class="flex flex-col gap-1 p-1.5">
              <div
                v-for="att in files"
                :key="att.id"
                role="button"
                tabindex="0"
                class="flex items-center gap-2 px-2.5 py-2 bg-white border border-slate-200 rounded-[6px] hover:border-sky-300 cursor-pointer min-w-[200px]"
                @click="download(att)"
                @keydown.enter="download(att)"
              >
                <i :class="fileIcon(att.content_type, att.name)" class="text-lg"></i>
                <span class="flex-1 min-w-0">
                  <span class="block text-xs font-semibold text-slate-800 truncate">{{ att.name }}</span>
                  <span class="block text-[10px] text-slate-400">{{ formatFileSize(att.size) }}</span>
                </span>
                <i class="pi pi-download text-xs text-slate-400"></i>
              </div>
            </div>
          </template>
        </div>

        <!-- Thanh thao tác khi rê chuột -->
        <div v-if="!message.recalled" class="opacity-0 group-hover:opacity-100 focus-within:opacity-100 flex items-center gap-0.5 transition-opacity shrink-0">
          <Button icon="pi pi-face-smile" text severity="secondary" size="small" :class="ACTION_CLASS" v-tooltip.top="'Thả cảm xúc'" @click="reactionPanel.toggle($event)" />
          <Button icon="pi pi-reply" text severity="secondary" size="small" :class="ACTION_CLASS" v-tooltip.top="'Trả lời'" @click="emit('reply', message)" />
          <Button icon="pi pi-ellipsis-h" text severity="secondary" size="small" :class="ACTION_CLASS" v-tooltip.top="'Thêm'" @click="moreMenu.toggle($event)" />
        </div>
        <Button
          v-else-if="mine"
          icon="pi pi-trash"
          text
          severity="secondary"
          size="small"
          :class="[ACTION_CLASS, 'opacity-0 group-hover:opacity-100']"
          v-tooltip.top="'Xoá phía tôi'"
          @click="hide"
        />
      </div>

      <!-- Cảm xúc -->
      <div v-if="message.reactions.length" class="flex flex-wrap gap-1 -mt-1.5 px-2 relative z-[1]">
        <Button
          v-for="r in message.reactions"
          :key="r.emoji"
          :label="r.users.length > 1 ? `${r.emoji} ${r.users.length}` : r.emoji"
          outlined
          severity="secondary"
          size="small"
          class="!px-1.5 !py-0 !h-6 !text-[11px] !rounded-[6px] !bg-white shadow-xs"
          :class="r.users.some((u) => u.id === chat.meId) ? '!border-sky-300 !text-sky-700' : '!border-slate-200 !text-slate-600'"
          v-tooltip.top="r.users.map((u) => u.name).join(', ')"
          @click="react(r.emoji)"
        />
      </div>

      <span class="text-[10px] text-slate-400 mt-0.5 px-1">
        {{ formatClock(message.created_at) }}
        <template v-if="message.edited_at && !message.recalled"> · đã sửa</template>
        <template v-if="message.pinned_at"> · <i class="pi pi-thumbtack text-[9px]"></i> đã ghim</template>
      </span>

      <!-- Avatar những người đã xem tới tin này -->
      <div v-if="readers.length" class="flex items-center gap-0.5 mt-0.5 px-1" :title="'Đã xem: ' + readers.map((r) => r.name).join(', ')">
        <ChatAvatar v-for="r in readers.slice(0, 5)" :key="r.user_id" :name="r.name" :size="14" />
        <span v-if="readers.length > 5" class="text-[10px] text-slate-400">+{{ readers.length - 5 }}</span>
      </div>
    </div>

    <Popover ref="reactionPanel">
      <div class="flex gap-1">
        <Button v-for="emoji in REACTIONS" :key="emoji" :label="emoji" text size="small" class="!w-9 !h-9 !p-0 !text-lg !rounded-[6px]" @click="react(emoji)" />
      </div>
    </Popover>
    <Menu ref="moreMenu" :model="menuItems" popup />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import Popover from 'primevue/popover';
import ChatAvatar from './ChatAvatar.vue';
import ChatImageThumb from './ChatImageThumb.vue';
import { useChatStore, REACTIONS, RECALL_WINDOW_MS } from '../../stores/chatStore';
import { chatApi } from '../../api/chat.api';
import { downloadAuthFile } from './useAuthBlob';
import { toSegments, formatClock, formatFileSize, fileIcon } from './chatFormat';

const props = defineProps({
  message: { type: Object, required: true },
  conv: { type: Object, required: true },
  mine: Boolean,
  showSender: Boolean,
  /** Mình là trưởng/phó nhóm và có quyền cao hơn người gửi: được thu hồi tin của họ */
  canModerate: Boolean,
  readers: { type: Array, default: () => [] }
});

const emit = defineEmits(['reply', 'edit', 'forward', 'jump']);

const ACTION_CLASS = '!w-7 !h-7 !p-0 !rounded-[6px] !text-slate-500 hover:!text-sky-600';

const chat = useChatStore();
const toast = useToast();
const confirm = useConfirm();
const reactionPanel = ref(null);
const moreMenu = ref(null);

const senderName = computed(() => (props.message.sender ? chat.memberName(props.conv, props.message.sender.id, props.message.sender.name) : ''));

const bubbleClass = computed(() => {
  if (props.message.recalled) return 'bg-white border border-dashed border-slate-300';
  return props.mine ? 'bg-sky-600 text-white' : 'bg-white border border-slate-200 text-slate-800';
});

const segments = computed(() => {
  const names = (props.message.mentions || []).map((id) => props.conv.members.find((m) => m.user_id === id)?.name);
  return toSegments(props.message.content, names);
});

const images = computed(() => props.message.attachments.filter((a) => a.is_image));
const files = computed(() => props.message.attachments.filter((a) => !a.is_image));

const canRecall = computed(() =>
  (props.mine && Date.now() - new Date(props.message.created_at).getTime() < RECALL_WINDOW_MS) || (!props.mine && props.canModerate));

const menuItems = computed(() => {
  const items = [
    { label: 'Chuyển tiếp', icon: 'pi pi-share-alt', command: () => emit('forward', props.message) },
    {
      label: props.message.pinned_at ? 'Bỏ ghim' : 'Ghim tin nhắn',
      icon: 'pi pi-thumbtack',
      command: () => run(() => chat.setMessagePinned(props.message.id, !props.message.pinned_at))
    }
  ];
  if (props.message.content) items.push({ label: 'Sao chép', icon: 'pi pi-copy', command: copy });
  if (props.mine && props.message.content) items.push({ label: 'Sửa tin nhắn', icon: 'pi pi-pencil', command: () => emit('edit', props.message) });
  items.push({ separator: true });
  if (canRecall.value) items.push({ label: 'Thu hồi', icon: 'pi pi-undo', class: 'text-rose-600', command: recall });
  items.push({ label: 'Xoá phía tôi', icon: 'pi pi-trash', command: hide });
  return items;
});

async function run(action) {
  try {
    await action();
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  }
}

function react(emoji) {
  reactionPanel.value?.hide();
  run(() => chat.react(props.message.id, emoji));
}

function recall() {
  confirm.require({
    header: 'Thu hồi tin nhắn',
    message: 'Tin nhắn sẽ bị thu hồi với mọi người trong cuộc trò chuyện. Tiếp tục?',
    acceptLabel: 'Thu hồi',
    rejectLabel: 'Huỷ',
    acceptClass: 'p-button-danger p-button-sm',
    accept: () => run(() => chat.recall(props.message.id))
  });
}

const hide = () => run(() => chat.hide(props.conv.id, props.message.id));

async function copy() {
  await navigator.clipboard?.writeText(props.message.content);
  toast.add({ severity: 'info', summary: 'Đã sao chép', life: 1500 });
}

const download = (att) => run(() => downloadAuthFile(chatApi.attachmentUrl(att.id), att.name));
const openImage = (url) => window.open(url, '_blank', 'noopener');
</script>
