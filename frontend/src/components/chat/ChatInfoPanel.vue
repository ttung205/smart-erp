<template>
  <div class="flex flex-col min-h-0 h-full bg-white">
    <div class="h-12 px-3 flex items-center justify-between border-b border-slate-200 shrink-0">
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wide">{{ isGroup ? 'Thông tin nhóm' : 'Thông tin hội thoại' }}</span>
      <button type="button" class="w-7 h-7 rounded-full hover:bg-slate-100 text-slate-500 cursor-pointer" @click="emit('close')"><i class="pi pi-times text-xs"></i></button>
    </div>

    <div class="flex-1 overflow-y-auto">
      <!-- Ảnh & tên -->
      <div class="flex flex-col items-center gap-2 px-4 py-5 border-b border-slate-100">
        <div class="relative">
          <ChatAvatar :name="chat.conversationName(conv)" :url="conv.avatar_url || ''" :group="isGroup" :size="72" :online="!isGroup && chat.isOnline(partner?.user_id)" />
          <button
            v-if="isGroup && canManage"
            type="button"
            class="absolute -bottom-1 -right-1 w-7 h-7 rounded-full bg-white border border-slate-200 shadow-sm text-slate-600 hover:text-sky-600 cursor-pointer"
            title="Đổi ảnh nhóm"
            @click="avatarMenu.toggle($event)"
          >
            <i class="pi pi-camera text-xs"></i>
          </button>
          <input ref="avatarInput" type="file" accept=".jpg,.jpeg,.png,.webp" class="hidden" @change="onAvatarPicked" />
          <Menu ref="avatarMenu" :model="avatarItems" popup />
        </div>
        <div class="flex items-center gap-1">
          <span class="text-sm font-bold text-slate-900 text-center">{{ chat.conversationName(conv) }}</span>
          <button v-if="isGroup && canManage" type="button" class="w-6 h-6 rounded-full hover:bg-slate-100 text-slate-400 cursor-pointer" title="Đổi tên nhóm" @click="renameDialog = true">
            <i class="pi pi-pencil text-[11px]"></i>
          </button>
          <button v-if="!isGroup && partner" type="button" class="w-6 h-6 rounded-full hover:bg-slate-100 text-slate-400 cursor-pointer" title="Đặt tên gợi nhớ" @click="openAlias(partner.user_id)">
            <i class="pi pi-pencil text-[11px]"></i>
          </button>
        </div>
        <span v-if="isGroup" class="text-[11px] text-slate-500">{{ conv.members.length }} thành viên</span>
        <span v-else-if="partnerInfo" class="text-[11px] text-slate-500 text-center">{{ [partnerInfo.department_name, partnerInfo.email].filter(Boolean).join(' · ') }}</span>
      </div>

      <!-- Tuỳ chọn riêng -->
      <div class="flex justify-center gap-6 py-3 border-b border-slate-100">
        <button type="button" class="flex flex-col items-center gap-1 text-[11px] text-slate-600 cursor-pointer" @click="run(() => chat.setMuted(conv.id, !conv.muted))">
          <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="pi" :class="conv.muted ? 'pi-bell' : 'pi-bell-slash'"></i></span>
          {{ conv.muted ? 'Bật thông báo' : 'Tắt thông báo' }}
        </button>
        <button type="button" class="flex flex-col items-center gap-1 text-[11px] text-slate-600 cursor-pointer" @click="run(() => chat.setPinned(conv.id, !conv.pinned_at))">
          <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="pi pi-thumbtack"></i></span>
          {{ conv.pinned_at ? 'Bỏ ghim' : 'Ghim hội thoại' }}
        </button>
        <button v-if="isGroup && canManage" type="button" class="flex flex-col items-center gap-1 text-[11px] text-slate-600 cursor-pointer" @click="addDialog = true">
          <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center"><i class="pi pi-user-plus"></i></span>
          Thêm thành viên
        </button>
      </div>

      <!-- Thành viên -->
      <div v-if="isGroup" class="py-2">
        <div class="px-4 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-400">Thành viên</div>
        <div v-for="member in conv.members" :key="member.user_id" class="flex items-center gap-2.5 px-4 py-2 hover:bg-slate-50">
          <ChatAvatar :name="member.name" :size="32" :online="chat.isOnline(member.user_id)" />
          <div class="flex-1 min-w-0">
            <div class="text-xs font-semibold text-slate-800 truncate">
              {{ chat.displayName(member.user_id, member.name) }}
              <span v-if="member.user_id === chat.meId" class="font-normal text-slate-400">(Bạn)</span>
            </div>
            <div class="text-[10px]" :class="member.is_active ? 'text-slate-400' : 'text-rose-500'">
              {{ member.is_active ? roleLabel(member.role) : 'Đã ngừng hoạt động' }}
            </div>
          </div>
          <button
            v-if="member.user_id !== chat.meId"
            type="button"
            class="w-7 h-7 rounded-full hover:bg-slate-200 text-slate-500 cursor-pointer"
            @click="openMemberMenu($event, member)"
          >
            <i class="pi pi-ellipsis-h text-xs"></i>
          </button>
        </div>
        <Menu ref="memberMenu" :model="memberItems" popup />
      </div>

      <!-- Rời / giải tán -->
      <div v-if="isGroup" class="px-4 py-3 border-t border-slate-100 flex flex-col gap-1.5">
        <Button label="Rời nhóm" icon="pi pi-sign-out" text size="small" severity="danger" class="!justify-start !text-xs" @click="startLeave" />
        <Button v-if="conv.my_role === 'owner'" label="Giải tán nhóm" icon="pi pi-trash" text size="small" severity="danger" class="!justify-start !text-xs" @click="confirmDissolve" />
      </div>
    </div>

    <ChatPromptDialog
      v-model:visible="renameDialog"
      title="Đổi tên nhóm"
      label="Tên nhóm"
      required
      :initial="conv.title || ''"
      :saving="busy"
      @submit="(title) => run(async () => { await chat.rename(conv.id, title); renameDialog = false; })"
    />
    <ChatPromptDialog
      v-model:visible="aliasDialog.visible"
      title="Đặt tên gợi nhớ"
      label="Tên gợi nhớ (chỉ mình bạn thấy)"
      hint="Để trống để xoá tên gợi nhớ."
      :maxlength="60"
      :initial="aliasDialog.initial"
      :saving="busy"
      @submit="(alias) => run(async () => { await chat.setAlias(aliasDialog.userId, alias); aliasDialog.visible = false; })"
    />
    <ChatUserPickerDialog
      v-model:visible="addDialog"
      mode="add"
      :exclude-ids="conv.members.map((m) => m.user_id)"
      :saving="busy"
      @confirm="({ userIds }) => run(async () => { await chat.addMembers(conv.id, userIds); addDialog = false; })"
    />

    <!-- Trưởng nhóm rời nhóm: chọn trưởng nhóm mới -->
    <Dialog v-model:visible="leaveDialog.visible" modal header="Chọn trưởng nhóm mới" :style="{ width: '400px' }">
      <p class="text-xs text-slate-600 mt-0">Bạn là trưởng nhóm. Hãy chọn người nhận quyền trưởng nhóm trước khi rời.</p>
      <Select
        v-model="leaveDialog.newOwnerId"
        :options="otherMembers"
        option-label="name"
        option-value="user_id"
        placeholder="Chọn thành viên"
        filter
        class="w-full"
      />
      <template #footer>
        <Button label="Huỷ" text size="small" severity="secondary" @click="leaveDialog.visible = false" />
        <Button label="Chuyển quyền & rời nhóm" size="small" severity="danger" :disabled="!leaveDialog.newOwnerId" :loading="busy" @click="leave(leaveDialog.newOwnerId)" />
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import Menu from 'primevue/menu';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import ChatAvatar from './ChatAvatar.vue';
import ChatPromptDialog from './ChatPromptDialog.vue';
import ChatUserPickerDialog from './ChatUserPickerDialog.vue';
import { useChatStore } from '../../stores/chatStore';
import { chatApi } from '../../api/chat.api';

const props = defineProps({ conv: { type: Object, required: true } });
const emit = defineEmits(['close', 'open-direct']);

const chat = useChatStore();
const toast = useToast();
const confirm = useConfirm();
const busy = ref(false);
const renameDialog = ref(false);
const addDialog = ref(false);
const aliasDialog = reactive({ visible: false, userId: 0, initial: '' });
const leaveDialog = reactive({ visible: false, newOwnerId: null });
const partnerInfo = ref(null);

const isGroup = computed(() => props.conv.type === 'group');
const canManage = computed(() => ['owner', 'deputy'].includes(props.conv.my_role));
const partner = computed(() => chat.partnerOf(props.conv));
const otherMembers = computed(() => props.conv.members.filter((m) => m.user_id !== chat.meId));

watch(() => partner.value?.user_id, async (userId) => {
  partnerInfo.value = userId ? await chatApi.user(userId).catch(() => null) : null;
}, { immediate: true });

const roleLabel = (role) => ({ owner: 'Trưởng nhóm', deputy: 'Phó nhóm', member: 'Thành viên' })[role];

async function run(action) {
  busy.value = true;
  try {
    await action();
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: e.message, life: 4000 });
  } finally {
    busy.value = false;
  }
}

function openAlias(userId) {
  Object.assign(aliasDialog, { visible: true, userId, initial: chat.aliases[userId] || '' });
}

// ---------- Ảnh nhóm ----------
const avatarMenu = ref(null);
const avatarInput = ref(null);
const avatarItems = computed(() => [
  { label: 'Chọn ảnh mới', icon: 'pi pi-upload', command: () => avatarInput.value.click() },
  ...(props.conv.avatar_url ? [{ label: 'Xoá ảnh nhóm', icon: 'pi pi-trash', command: () => run(() => chat.removeAvatar(props.conv.id)) }] : [])
]);

function onAvatarPicked(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (file) run(() => chat.setAvatar(props.conv.id, file));
}

// ---------- Thành viên ----------
const memberMenu = ref(null);
const memberTarget = ref(null);

const memberItems = computed(() => {
  const m = memberTarget.value;
  if (!m) return [];
  const myRole = props.conv.my_role;
  const items = [
    { label: 'Nhắn tin riêng', icon: 'pi pi-comment', command: () => emit('open-direct', m.user_id) },
    { label: 'Đặt tên gợi nhớ', icon: 'pi pi-pencil', command: () => openAlias(m.user_id) }
  ];
  if (myRole === 'owner') {
    items.push({ separator: true });
    items.push(m.role === 'deputy'
      ? { label: 'Gỡ quyền phó nhóm', icon: 'pi pi-user-minus', command: () => run(() => chat.setRole(props.conv.id, m.user_id, 'member')) }
      : { label: 'Bổ nhiệm phó nhóm', icon: 'pi pi-star', command: () => run(() => chat.setRole(props.conv.id, m.user_id, 'deputy')) });
    items.push({ label: 'Chuyển quyền trưởng nhóm', icon: 'pi pi-crown', command: () => confirmTransfer(m) });
  }
  if (myRole === 'owner' || (myRole === 'deputy' && m.role === 'member')) {
    items.push({ label: 'Xoá khỏi nhóm', icon: 'pi pi-user-minus', class: 'text-rose-600', command: () => confirmRemove(m) });
  }
  return items;
});

function openMemberMenu(event, member) {
  memberTarget.value = member;
  memberMenu.value.toggle(event);
}

function confirmTransfer(member) {
  confirm.require({
    header: 'Chuyển quyền trưởng nhóm',
    message: `Chuyển quyền trưởng nhóm cho ${member.name}? Bạn sẽ trở thành thành viên thường.`,
    acceptLabel: 'Chuyển quyền',
    rejectLabel: 'Huỷ',
    accept: () => run(() => chat.transferOwner(props.conv.id, member.user_id))
  });
}

function confirmRemove(member) {
  confirm.require({
    header: 'Xoá khỏi nhóm',
    message: `Xoá ${member.name} khỏi nhóm "${props.conv.title}"?`,
    acceptLabel: 'Xoá',
    rejectLabel: 'Huỷ',
    acceptClass: 'p-button-danger p-button-sm',
    accept: () => run(() => chat.removeMember(props.conv.id, member.user_id))
  });
}

// ---------- Rời / giải tán ----------
function startLeave() {
  if (props.conv.my_role === 'owner' && otherMembers.value.length) {
    Object.assign(leaveDialog, { visible: true, newOwnerId: null });
    return;
  }
  confirm.require({
    header: 'Rời nhóm',
    message: otherMembers.value.length ? `Rời nhóm "${props.conv.title}"?` : 'Bạn là thành viên cuối cùng, rời nhóm sẽ giải tán nhóm. Tiếp tục?',
    acceptLabel: 'Rời nhóm',
    rejectLabel: 'Huỷ',
    acceptClass: 'p-button-danger p-button-sm',
    accept: () => leave(null)
  });
}

function leave(newOwnerId) {
  run(async () => {
    await chat.leave(props.conv.id, newOwnerId);
    leaveDialog.visible = false;
    toast.add({ severity: 'info', summary: 'Đã rời nhóm', life: 2500 });
  });
}

function confirmDissolve() {
  confirm.require({
    header: 'Giải tán nhóm',
    message: `Giải tán nhóm "${props.conv.title}"? Toàn bộ tin nhắn và tệp của nhóm sẽ bị xoá với mọi thành viên.`,
    acceptLabel: 'Giải tán',
    rejectLabel: 'Huỷ',
    acceptClass: 'p-button-danger p-button-sm',
    accept: () => run(() => chat.dissolve(props.conv.id))
  });
}
</script>
