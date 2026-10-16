<template>
  <Dialog :visible="visible" modal :header="header" :style="{ width: '460px' }" @update:visible="emit('update:visible', $event)">
    <div class="flex flex-col gap-3 pt-1">
      <div v-if="mode === 'group'" class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-slate-700">Tên nhóm</label>
        <InputText v-model="title" maxlength="120" placeholder="VD: Dự án kho Bình Dương" class="w-full !text-sm" />
      </div>

      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="keyword" placeholder="Tìm theo tên hoặc email..." class="w-full !text-sm" autofocus />
      </IconField>

      <div v-if="multiple && selected.length" class="flex flex-wrap gap-1">
        <span v-for="user in selected" :key="user.id" class="inline-flex items-center gap-1 pl-2 pr-1 py-0.5 text-[11px] bg-sky-50 border border-sky-200 text-sky-800 rounded-full">
          {{ user.name }}
          <button type="button" class="w-4 h-4 rounded-full hover:bg-sky-200 flex items-center justify-center cursor-pointer" @click="toggle(user)">
            <i class="pi pi-times text-[8px]"></i>
          </button>
        </span>
      </div>

      <div class="border border-slate-200 rounded-[6px] max-h-[320px] overflow-y-auto">
        <div class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400 bg-slate-50 border-b border-slate-100">
          {{ keyword.trim() ? 'Kết quả tìm kiếm' : 'Đồng nghiệp gần đây & cùng phòng ban' }}
        </div>
        <div v-if="loading" class="p-4 text-center text-xs text-slate-400">Đang tải...</div>
        <div v-else-if="!candidates.length" class="p-4 text-center text-xs text-slate-400">Không tìm thấy người phù hợp.</div>
        <button
          v-for="user in candidates"
          :key="user.id"
          type="button"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-left hover:bg-slate-50 cursor-pointer border-b border-slate-50"
          :disabled="excludeIds.includes(user.id)"
          :class="{ 'opacity-50 cursor-not-allowed': excludeIds.includes(user.id) }"
          @click="choose(user)"
        >
          <ChatAvatar :name="user.name" :online="user.online" :size="32" />
          <div class="flex-1 min-w-0">
            <div class="text-xs font-semibold text-slate-800 truncate">{{ user.name }}</div>
            <div class="text-[11px] text-slate-400 truncate">{{ [user.department_name, user.email].filter(Boolean).join(' · ') }}</div>
          </div>
          <span v-if="excludeIds.includes(user.id)" class="text-[10px] text-slate-400">Đã trong nhóm</span>
          <i v-else-if="multiple" class="pi" :class="isSelected(user) ? 'pi-check-square text-sky-600' : 'pi-stop text-slate-300'"></i>
        </button>
      </div>
    </div>

    <template v-if="multiple" #footer>
      <Button label="Huỷ" text size="small" severity="secondary" @click="emit('update:visible', false)" />
      <Button :label="confirmLabel" size="small" :loading="saving" :disabled="!canConfirm" @click="confirm" />
    </template>
  </Dialog>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import ChatAvatar from './ChatAvatar.vue';
import { chatApi } from '../../api/chat.api';

const props = defineProps({
  visible: Boolean,
  /** direct: chọn 1 người; group: tạo nhóm; add: thêm thành viên */
  mode: { type: String, default: 'direct' },
  excludeIds: { type: Array, default: () => [] },
  saving: Boolean
});

const emit = defineEmits(['update:visible', 'pick', 'confirm']);

const keyword = ref('');
const title = ref('');
const selected = ref([]);
const candidates = ref([]);
const loading = ref(false);
let timer = null;
let requestSeq = 0;

const multiple = computed(() => props.mode !== 'direct');
const header = computed(() => ({ direct: 'Chat với đồng nghiệp', group: 'Tạo nhóm chat', add: 'Thêm thành viên' })[props.mode]);
const confirmLabel = computed(() => (props.mode === 'group' ? `Tạo nhóm (${selected.value.length + 1} người)` : `Thêm ${selected.value.length} người`));
const canConfirm = computed(() => selected.value.length > 0 && (props.mode !== 'group' || title.value.trim()));

watch(() => props.visible, (open) => {
  if (!open) return;
  keyword.value = '';
  title.value = '';
  selected.value = [];
  load();
});

watch(keyword, () => {
  clearTimeout(timer);
  timer = setTimeout(load, 250);
});

async function load() {
  const seq = ++requestSeq;
  loading.value = true;
  try {
    const q = keyword.value.trim();
    const users = q ? await chatApi.searchUsers(q, 30) : await chatApi.contacts();
    if (seq === requestSeq) candidates.value = users;
  } catch {
    if (seq === requestSeq) candidates.value = [];
  } finally {
    if (seq === requestSeq) loading.value = false;
  }
}

const isSelected = (user) => selected.value.some((u) => u.id === user.id);

function toggle(user) {
  selected.value = isSelected(user) ? selected.value.filter((u) => u.id !== user.id) : [...selected.value, user];
}

function choose(user) {
  if (props.excludeIds.includes(user.id)) return;
  if (multiple.value) toggle(user);
  else emit('pick', user.id);
}

function confirm() {
  if (canConfirm.value) emit('confirm', { title: title.value.trim(), userIds: selected.value.map((u) => u.id) });
}
</script>
