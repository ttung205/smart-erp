<template>
  <Dialog :visible="visible" modal :header="header" :style="{ width: '480px' }" @update:visible="emit('update:visible', $event)">
    <div class="flex flex-col gap-3 pt-1">
      <div v-if="mode === 'group'" class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-slate-700">Tên nhóm</label>
        <InputText v-model="title" maxlength="120" placeholder="VD: Dự án kho Bình Dương" class="w-full !text-sm" />
      </div>

      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="keyword" placeholder="Tìm theo tên, email hoặc phòng ban..." class="w-full !text-sm" autofocus />
      </IconField>

      <div v-if="multiple && selected.length" class="flex flex-wrap gap-1 max-h-20 overflow-y-auto">
        <Tag v-for="user in selected" :key="user.id" :value="user.name" severity="info" class="!rounded-[6px]">
          <template #default>
            <span>{{ user.name }}</span>
            <Button icon="pi pi-times" text size="small" class="!w-5 !h-5 !p-0 !rounded-[6px]" :aria-label="`Bỏ chọn ${user.name}`" @click="toggle(user)" />
          </template>
        </Tag>
      </div>

      <div class="border border-slate-200 rounded-[6px] h-[360px] overflow-y-auto">
        <div v-if="loading" class="p-4 text-center text-xs text-slate-400">Đang tải danh bạ...</div>
        <div v-else-if="error" class="p-4 text-center text-xs text-rose-600">{{ error }}</div>
        <div v-else-if="!groups.length" class="p-4 text-center text-xs text-slate-400">Không tìm thấy người phù hợp.</div>

        <template v-for="group in groups" :key="group.name">
          <div class="sticky top-0 z-[1] flex items-center justify-between px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 bg-slate-50 border-b border-slate-100">
            <span>{{ group.name }} ({{ group.users.length }})</span>
            <Button
              v-if="multiple && group.selectable.length"
              :label="group.selectable.every(isSelected) ? 'Bỏ chọn cả phòng' : 'Chọn cả phòng'"
              text size="small" class="!p-0 !text-[11px] !rounded-[6px] normal-case tracking-normal"
              @click="toggleGroup(group)"
            />
          </div>
          <div
            v-for="user in group.users"
            :key="user.id"
            role="button"
            :tabindex="excluded(user) ? -1 : 0"
            :aria-disabled="excluded(user)"
            :aria-pressed="multiple ? isSelected(user) : undefined"
            class="w-full flex items-center gap-2.5 px-3 py-2 text-left border-b border-slate-50"
            :class="excluded(user) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-slate-50 cursor-pointer'"
            @click="choose(user)"
            @keydown.enter="choose(user)"
            @keydown.space.prevent="choose(user)"
          >
            <ChatAvatar :name="user.name" :online="chat.isOnline(user.id)" :size="32" />
            <div class="flex-1 min-w-0">
              <div class="text-xs font-semibold text-slate-800 truncate">{{ user.name }}</div>
              <div class="text-[11px] text-slate-400 truncate">{{ user.email }}</div>
            </div>
            <span v-if="excluded(user)" class="text-[10px] text-slate-400">Đã trong nhóm</span>
            <Checkbox v-else-if="multiple" :model-value="isSelected(user)" binary tabindex="-1" :aria-label="`Chọn ${user.name}`" @click.stop="choose(user)" />
          </div>
        </template>
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
import Checkbox from 'primevue/checkbox';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import ChatAvatar from './ChatAvatar.vue';
import { chatApi } from '../../api/chat.api';
import { useChatStore } from '../../stores/chatStore';

const NO_DEPARTMENT = 'Chưa có phòng ban';

const props = defineProps({
  visible: Boolean,
  /** direct: chọn 1 người; group: tạo nhóm; add: thêm thành viên */
  mode: { type: String, default: 'direct' },
  excludeIds: { type: Array, default: () => [] },
  saving: Boolean
});

const emit = defineEmits(['update:visible', 'pick', 'confirm']);

const chat = useChatStore();
const keyword = ref('');
const title = ref('');
const selected = ref([]);
const users = ref([]);
const loading = ref(false);
const error = ref('');

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

/** Tải toàn bộ nhân sự đang làm việc một lần, sau đó lọc ngay trên trình duyệt. */
async function load() {
  loading.value = true;
  error.value = '';
  try {
    users.value = await chatApi.searchUsers('', 1000);
  } catch (e) {
    users.value = [];
    error.value = e.message || 'Không tải được danh bạ.';
  } finally {
    loading.value = false;
  }
}

// Tìm không phân biệt dấu: "nguyen" khớp "Nguyễn"
const fold = (value) => (value || '').normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase();

const groups = computed(() => {
  const q = fold(keyword.value.trim());
  const map = new Map();
  for (const user of users.value) {
    if (q && ![user.name, user.email, user.department_name].some((v) => fold(v).includes(q))) continue;
    const name = user.department_name || NO_DEPARTMENT;
    if (!map.has(name)) map.set(name, []);
    map.get(name).push(user);
  }
  return [...map.entries()]
    .sort(([a], [b]) => (a === NO_DEPARTMENT) - (b === NO_DEPARTMENT) || a.localeCompare(b, 'vi'))
    .map(([name, list]) => ({ name, users: list, selectable: list.filter((u) => !excluded(u)) }));
});

const excluded = (user) => props.excludeIds.includes(user.id);
const isSelected = (user) => selected.value.some((u) => u.id === user.id);

function toggle(user) {
  selected.value = isSelected(user) ? selected.value.filter((u) => u.id !== user.id) : [...selected.value, user];
}

function toggleGroup(group) {
  const ids = new Set(group.selectable.map((u) => u.id));
  selected.value = group.selectable.every(isSelected)
    ? selected.value.filter((u) => !ids.has(u.id))
    : [...selected.value, ...group.selectable.filter((u) => !isSelected(u))];
}

function choose(user) {
  if (excluded(user)) return;
  if (multiple.value) toggle(user);
  else emit('pick', user.id);
}

function confirm() {
  if (canConfirm.value) emit('confirm', { title: title.value.trim(), userIds: selected.value.map((u) => u.id) });
}
</script>
