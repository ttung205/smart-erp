<template>
  <div class="border-t border-slate-200 bg-white shrink-0">
    <!-- Đang trả lời / đang sửa -->
    <div v-if="replyTo || editing" class="flex items-center gap-2 px-3 pt-2">
      <div class="flex-1 min-w-0 px-2 py-1 border border-sky-200 bg-sky-50 rounded-[6px]">
        <div class="text-[11px] font-semibold text-sky-700">
          {{ editing ? 'Đang sửa tin nhắn' : `Trả lời ${replyName}` }}
        </div>
        <div v-if="replyTo" class="text-[11px] text-slate-500 truncate">{{ replyTo.content || '[Tệp đính kèm]' }}</div>
      </div>
      <Button icon="pi pi-times" text severity="secondary" size="small" class="!w-7 !h-7 !p-0 !rounded-[6px]" v-tooltip.top="'Huỷ'" @click="cancelMode" />
    </div>

    <!-- Tệp chờ gửi -->
    <div v-if="files.length" class="flex flex-wrap gap-1.5 px-3 pt-2">
      <span v-for="(file, i) in files" :key="i" class="inline-flex items-center gap-1.5 pl-2 pr-1 py-1 text-[11px] bg-slate-50 border border-slate-200 rounded-[6px] max-w-[220px]">
        <i :class="fileIcon(file.type, file.name)"></i>
        <span class="truncate">{{ file.name }}</span>
        <span class="text-slate-400 shrink-0">{{ formatFileSize(file.size) }}</span>
        <Button icon="pi pi-times" text severity="secondary" size="small" class="!w-5 !h-5 !p-0 !rounded-[6px] shrink-0" @click="files.splice(i, 1)" />
      </span>
    </div>

    <div class="relative flex items-end gap-1 p-2">
      <!-- Gợi ý nhắc tên -->
      <div v-if="mentionOpen" class="absolute bottom-full left-2 mb-1 w-64 max-h-56 overflow-y-auto bg-white border border-slate-200 rounded-[6px] shadow-sm z-20" role="listbox">
        <div
          v-for="(option, i) in mentionOptions"
          :key="option.id"
          role="option"
          :aria-selected="i === mentionIndex"
          class="w-full flex items-center gap-2 px-2.5 py-1.5 text-xs cursor-pointer"
          :class="i === mentionIndex ? 'bg-sky-50' : 'hover:bg-slate-50'"
          @mousedown.prevent="applyMention(option)"
        >
          <ChatAvatar :name="option.name" :group="option.id === 'all'" :size="22" />
          <span class="truncate">{{ option.name }}</span>
        </div>
      </div>

      <template v-if="!editing">
        <FileUpload ref="fileInput" mode="basic" multiple custom-upload choose-icon="pi pi-paperclip" choose-label=" " :choose-button-props="{ text: true, severity: 'secondary', size: 'small', class: TOOL_CLASS, 'aria-label': 'Đính kèm tệp' }" @select="onPickFiles" />
      </template>
      <Button icon="pi pi-face-smile" text severity="secondary" :class="TOOL_CLASS" v-tooltip.top="'Biểu tượng cảm xúc'" @click="emojiPanel.toggle($event)" />

      <Textarea
        ref="textarea"
        v-model="text"
        rows="1"
        auto-resize
        :placeholder="placeholder"
        class="flex-1 !resize-none !text-[13px] !rounded-[6px] max-h-36"
        @keydown="onKeydown"
        @input="onInput"
        @paste="onPaste"
        @click="detectMention"
      />

      <Button
        :icon="editing ? 'pi pi-check' : 'pi pi-send'"
        size="small"
        :loading="sending"
        :disabled="!canSend"
        class="!w-9 !h-9 !p-0 !rounded-[6px]"
        @click="submit"
      />
    </div>

    <Popover ref="emojiPanel">
      <div class="grid grid-cols-8 gap-0.5 w-72">
        <Button v-for="emoji in EMOJIS" :key="emoji" :label="emoji" text size="small" class="!w-9 !h-9 !p-0 !text-lg !rounded-[6px]" @click="insertText(emoji)" />
      </div>
    </Popover>
  </div>
</template>

<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import Popover from 'primevue/popover';
import Textarea from 'primevue/textarea';
import FileUpload from 'primevue/fileupload';
import ChatAvatar from './ChatAvatar.vue';
import { useChatStore } from '../../stores/chatStore';
import { fileIcon, formatFileSize } from './chatFormat';

const MAX_FILES = 10;
const MAX_FILE_BYTES = 20 * 1024 * 1024;
const TOOL_CLASS = '!w-9 !h-9 !p-0 !rounded-[6px]';
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
const textareaEl = () => textarea.value?.$el;
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
    const el = textareaEl();
    if (!el) return;
    el.focus();
    el.setSelectionRange(el.value.length, el.value.length);
  });
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
  const el = textareaEl();
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
  const el = textareaEl();
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
  const el = textareaEl();
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
  textareaEl()?.focus();
}

function onPickFiles(event) {
  addFiles(event.files);
  fileInput.value?.clear();
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
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Không gửi được tin nhắn', detail: e.message, life: 4000 });
  } finally {
    sending.value = false;
    textareaEl()?.focus();
  }
}

defineExpose({ addFiles, focus: () => textareaEl()?.focus() });
</script>
