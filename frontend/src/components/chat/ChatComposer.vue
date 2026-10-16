<template>
  <div class="border-t border-slate-200 bg-white shrink-0">
    <!-- Đang trả lời / đang sửa -->
    <div v-if="replyTo || editing" class="flex items-center gap-2 px-3 pt-2">
      <div class="flex-1 min-w-0 px-2 py-1 border-l-2 border-sky-500 bg-slate-50 rounded-r-[4px]">
        <div class="text-[11px] font-semibold text-sky-700">
          {{ editing ? 'Đang sửa tin nhắn' : `Trả lời ${replyName}` }}
        </div>
        <div v-if="replyTo" class="text-[11px] text-slate-500 truncate">{{ replyTo.content || '[Tệp đính kèm]' }}</div>
      </div>
      <button type="button" class="w-6 h-6 rounded-full hover:bg-slate-100 text-slate-400 cursor-pointer" title="Huỷ" @click="cancelMode">
        <i class="pi pi-times text-[11px]"></i>
      </button>
    </div>

    <!-- Tệp chờ gửi -->
    <div v-if="files.length" class="flex flex-wrap gap-1.5 px-3 pt-2">
      <span v-for="(file, i) in files" :key="i" class="inline-flex items-center gap-1.5 pl-2 pr-1 py-1 text-[11px] bg-slate-50 border border-slate-200 rounded-[6px] max-w-[220px]">
        <i :class="fileIcon(file.type, file.name)"></i>
        <span class="truncate">{{ file.name }}</span>
        <span class="text-slate-400 shrink-0">{{ formatFileSize(file.size) }}</span>
        <button type="button" class="w-4 h-4 rounded-full hover:bg-slate-200 cursor-pointer shrink-0" @click="files.splice(i, 1)"><i class="pi pi-times text-[8px]"></i></button>
      </span>
    </div>

    <div class="relative flex items-end gap-1 p-2">
      <!-- Gợi ý nhắc tên -->
      <div v-if="mentionOpen" class="absolute bottom-full left-2 mb-1 w-64 max-h-56 overflow-y-auto bg-white border border-slate-200 rounded-[6px] shadow-lg z-20">
        <button
          v-for="(option, i) in mentionOptions"
          :key="option.id"
          type="button"
          class="w-full flex items-center gap-2 px-2.5 py-1.5 text-left text-xs cursor-pointer"
          :class="i === mentionIndex ? 'bg-sky-50' : 'hover:bg-slate-50'"
          @mousedown.prevent="applyMention(option)"
        >
          <ChatAvatar :name="option.name" :group="option.id === 'all'" :size="22" />
          <span class="truncate">{{ option.name }}</span>
        </button>
      </div>

      <template v-if="!editing">
        <button type="button" class="tool-btn" title="Đính kèm tệp" @click="fileInput.click()"><i class="pi pi-paperclip"></i></button>
        <input ref="fileInput" type="file" multiple class="hidden" @change="onPickFiles" />
      </template>
      <button type="button" class="tool-btn" title="Biểu tượng cảm xúc" @click="emojiPanel.toggle($event)"><i class="pi pi-face-smile"></i></button>

      <textarea
        ref="textarea"
        v-model="text"
        rows="1"
        :placeholder="placeholder"
        class="flex-1 resize-none px-3 py-2 text-[13px] bg-slate-50 border border-slate-200 rounded-[8px] focus:outline-none focus:border-sky-500 focus:bg-white max-h-36"
        @keydown="onKeydown"
        @input="onInput"
        @paste="onPaste"
        @click="detectMention"
      ></textarea>

      <Button
        :icon="editing ? 'pi pi-check' : 'pi pi-send'"
        size="small"
        :loading="sending"
        :disabled="!canSend"
        class="!w-9 !h-9 !p-0 !rounded-full"
        @click="submit"
      />
    </div>

    <Popover ref="emojiPanel">
      <div class="grid grid-cols-8 gap-0.5 w-72">
        <button v-for="emoji in EMOJIS" :key="emoji" type="button" class="w-8 h-8 text-lg rounded hover:bg-slate-100 cursor-pointer" @click="insertText(emoji)">
          {{ emoji }}
        </button>
      </div>
    </Popover>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import ChatAvatar from './ChatAvatar.vue';
import { useChatStore } from '../../stores/chatStore';
import { fileIcon, formatFileSize } from './chatFormat';

const MAX_FILES = 10;
const MAX_FILE_BYTES = 20 * 1024 * 1024;
const EMOJIS = ['😀', '😂', '😊', '😍', '🥰', '😘', '😎', '🤔', '😅', '😭', '😡', '😱', '👍', '👎', '👏', '🙏', '💪', '🤝', '👌', '✌️', '❤️', '💔', '🔥', '🎉', '✅', '❌', '⚠️', '📌', '📎', '📦', '💰', '⏰'];

const props = defineProps({
  conv: { type: Object, required: true },
  replyTo: { type: Object, default: null },
  editing: { type: Object, default: null }
});
const emit = defineEmits(['cancel', 'sent']);

const chat = useChatStore();
const toast = useToast();
const text = ref('');
const files = ref([]);
const sending = ref(false);
const textarea = ref(null);
const fileInput = ref(null);
const emojiPanel = ref(null);

const placeholder = computed(() => (props.conv.type === 'group' ? 'Nhập tin nhắn, @ để nhắc tên...' : `Nhắn tin tới ${chat.conversationName(props.conv)}...`));
const replyName = computed(() => props.replyTo?.sender ? chat.memberName(props.conv, props.replyTo.sender.id, props.replyTo.sender.name) : '');
const canSend = computed(() => !sending.value && (text.value.trim().length > 0 || (!props.editing && files.value.length > 0)));

watch(() => props.editing, (message) => {
  text.value = message ? message.content : '';
  focusEnd();
});
watch(() => props.replyTo, (message) => message && focusEnd());
watch(() => props.conv.id, () => {
  text.value = '';
  files.value = [];
  mentionOpen.value = false;
});

function focusEnd() {
  nextTick(() => {
    const el = textarea.value;
    if (!el) return;
    el.focus();
    el.setSelectionRange(el.value.length, el.value.length);
    autosize();
  });
}

function autosize() {
  const el = textarea.value;
  if (!el) return;
  el.style.height = 'auto';
  el.style.height = `${Math.min(el.scrollHeight, 144)}px`;
}

function cancelMode() {
  if (props.editing) text.value = '';
  emit('cancel');
}

// ---------- Nhắc tên ----------
const mentionOpen = ref(false);
const mentionQuery = ref('');
const mentionStart = ref(-1);
const mentionIndex = ref(0);

const mentionOptions = computed(() => {
  const q = mentionQuery.value.toLowerCase();
  const members = props.conv.members
    .filter((m) => m.user_id !== chat.meId)
    .map((m) => ({ id: m.user_id, name: m.name }))
    .filter((m) => m.name.toLowerCase().includes(q));
  const all = 'tất cả'.includes(q) ? [{ id: 'all', name: 'Tất cả' }] : [];
  return [...all, ...members].slice(0, 8);
});

function detectMention() {
  if (props.conv.type !== 'group') return;
  const el = textarea.value;
  const before = text.value.slice(0, el.selectionStart);
  const match = before.match(/(?:^|\s)@([^\s@]{0,30})$/);
  if (match) {
    mentionStart.value = before.length - match[1].length - 1;
    mentionQuery.value = match[1];
    mentionIndex.value = 0;
    mentionOpen.value = mentionOptions.value.length > 0;
  } else {
    mentionOpen.value = false;
  }
}

function applyMention(option) {
  const el = textarea.value;
  const end = el.selectionStart;
  const insert = `@${option.name} `;
  text.value = text.value.slice(0, mentionStart.value) + insert + text.value.slice(end);
  mentionOpen.value = false;
  nextTick(() => {
    const position = mentionStart.value + insert.length;
    el.focus();
    el.setSelectionRange(position, position);
  });
}

/** Người được nhắc = thành viên có "@Tên" còn nằm trong nội dung lúc gửi. */
function collectMentions(content) {
  if (props.conv.type !== 'group') return { mentions: [], mentionAll: false };
  const mentions = props.conv.members.filter((m) => m.user_id !== chat.meId && content.includes(`@${m.name}`)).map((m) => m.user_id);
  return { mentions, mentionAll: content.includes('@Tất cả') };
}

// ---------- Nhập liệu ----------
function onInput() {
  autosize();
  detectMention();
  if (!props.editing && text.value.trim()) chat.sendTyping(props.conv.id);
}

function onKeydown(event) {
  if (mentionOpen.value) {
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const count = mentionOptions.value.length;
      mentionIndex.value = (mentionIndex.value + (event.key === 'ArrowDown' ? 1 : count - 1)) % count;
      return;
    }
    if (event.key === 'Enter' || event.key === 'Tab') {
      event.preventDefault();
      applyMention(mentionOptions.value[mentionIndex.value]);
      return;
    }
    if (event.key === 'Escape') {
      mentionOpen.value = false;
      return;
    }
  }
  if (event.key === 'Escape' && (props.replyTo || props.editing)) cancelMode();
  if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
    event.preventDefault();
    submit();
  }
}

function insertText(value) {
  const el = textarea.value;
  const start = el?.selectionStart ?? text.value.length;
  text.value = text.value.slice(0, start) + value + text.value.slice(start);
  nextTick(() => {
    el?.focus();
    el?.setSelectionRange(start + value.length, start + value.length);
  });
}

// ---------- Tệp ----------
function addFiles(list) {
  if (props.editing) return;
  for (const file of list) {
    if (files.value.length >= MAX_FILES) {
      toast.add({ severity: 'warn', summary: `Tối đa ${MAX_FILES} tệp mỗi tin`, life: 3000 });
      break;
    }
    if (file.size > MAX_FILE_BYTES) {
      toast.add({ severity: 'warn', summary: 'Tệp quá lớn', detail: `${file.name} vượt quá 20 MB.`, life: 3000 });
      continue;
    }
    files.value.push(file);
  }
  textarea.value?.focus();
}

function onPickFiles(event) {
  addFiles([...event.target.files]);
  event.target.value = '';
}

function onPaste(event) {
  const pasted = [...(event.clipboardData?.files || [])];
  if (!pasted.length) return;
  event.preventDefault();
  // Ảnh dán từ clipboard thường tên "image.png": đặt tên theo thời điểm để dễ phân biệt
  addFiles(pasted.map((f) => (f.name === 'image.png' ? new File([f], `anh-${Date.now()}.png`, { type: f.type }) : f)));
}

// ---------- Gửi ----------
async function submit() {
  if (!canSend.value) return;
  const content = text.value.trim();
  sending.value = true;
  try {
    if (props.editing) {
      await chat.edit(props.editing.id, content);
    } else {
      const { mentions, mentionAll } = collectMentions(content);
      await chat.send(props.conv.id, { content, replyToId: props.replyTo?.id, mentions, mentionAll, files: files.value });
      files.value = [];
    }
    text.value = '';
    emit('sent');
    nextTick(autosize);
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Không gửi được tin nhắn', detail: e.message, life: 4000 });
  } finally {
    sending.value = false;
    textarea.value?.focus();
  }
}

defineExpose({ addFiles, focus: () => textarea.value?.focus() });
</script>

<style scoped>
.tool-btn {
  width: 34px;
  height: 34px;
  border-radius: 9999px;
  color: rgb(100 116 139);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tool-btn:hover {
  background: rgb(241 245 249);
  color: rgb(2 132 199);
}
</style>
