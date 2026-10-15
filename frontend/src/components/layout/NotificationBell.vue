<template>
  <div>
    <OverlayBadge v-if="state.unreadCount > 0" :value="badgeLabel" severity="danger" size="small">
      <Button icon="pi pi-bell" text rounded severity="secondary" aria-label="Thông báo"
        class="!w-8 !h-8" @click="toggle" />
    </OverlayBadge>
    <Button v-else icon="pi pi-bell" text rounded severity="secondary" aria-label="Thông báo"
      class="!w-8 !h-8" @click="toggle" />

    <Popover ref="panel" class="!p-0">
      <div class="w-[380px] flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b border-slate-200">
          <div class="flex items-center gap-2">
            <span class="text-sm font-semibold text-slate-900">Thông báo</span>
            <i v-tooltip.bottom="state.connected ? 'Đang nhận realtime' : 'Mất kết nối realtime'"
              class="pi pi-circle-fill text-[8px]" :class="state.connected ? 'text-emerald-500' : 'text-slate-300'" />
          </div>
          <Button label="Đánh dấu đã đọc" text size="small" :disabled="state.unreadCount === 0"
            class="!text-xs !px-2 !py-1" @click="markAllRead" />
        </div>

        <div class="px-4 py-2 border-b border-slate-200">
          <SelectButton v-model="filter" :options="filters" option-label="label" option-value="value"
            :allow-empty="false" size="small" class="w-full" />
        </div>

        <div class="max-h-[420px] overflow-y-auto" @scroll="onScroll">
          <div v-if="state.loading && state.items.length === 0" class="flex flex-col gap-3 p-4">
            <Skeleton v-for="i in 3" :key="i" height="2.75rem" />
          </div>

          <div v-else-if="visibleItems.length === 0" class="flex flex-col items-center gap-2 py-10 text-slate-400">
            <i class="pi pi-inbox text-2xl" />
            <span class="text-xs">Không có thông báo</span>
          </div>

          <div v-for="item in visibleItems" :key="item.id" role="button" tabindex="0"
            class="flex gap-3 px-4 py-3 border-b border-slate-100 cursor-pointer hover:bg-slate-50"
            :class="item.status === 'UNREAD' ? 'bg-indigo-50/40' : ''"
            @click="open(item)" @keydown.enter="open(item)">
            <div class="w-8 h-8 shrink-0 rounded-full flex items-center justify-center"
              :class="item.category === 'task' ? 'bg-amber-50 text-amber-600' : 'bg-sky-50 text-sky-600'">
              <i :class="iconFor(item)" class="text-sm" />
            </div>
            <div class="flex-1 min-w-0 flex flex-col gap-1">
              <div class="flex items-start gap-2">
                <span class="flex-1 text-xs leading-snug"
                  :class="item.status === 'UNREAD' ? 'font-semibold text-slate-900' : 'text-slate-700'">
                  {{ item.title }}
                </span>
                <span v-if="item.status === 'UNREAD'" class="mt-1 w-2 h-2 shrink-0 rounded-full bg-indigo-600" />
              </div>
              <span v-if="item.body" class="text-[11px] text-slate-500 line-clamp-2">{{ item.body }}</span>
              <div class="flex items-center gap-2">
                <span class="text-[10px] text-slate-400">{{ timeAgo(item.created_at) }}</span>
                <Tag v-if="item.category === 'task'" :value="item.resolved_at ? 'Đã xử lý' : 'Cần xử lý'"
                  :severity="item.resolved_at ? 'secondary' : 'warn'" class="!text-[10px] !px-1.5 !py-0" />
              </div>
            </div>
          </div>

          <div v-if="state.loading && state.items.length > 0" class="py-3 text-center text-[11px] text-slate-400">
            Đang tải...
          </div>
        </div>
      </div>
    </Popover>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import OverlayBadge from 'primevue/overlaybadge';
import Popover from 'primevue/popover';
import SelectButton from 'primevue/selectbutton';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import { useNotifications } from '../../composables/useNotifications';
import { storage } from '../../utils/storage';

const router = useRouter();
const toast = useToast();
const { state, connect, disconnect, loadMore, markRead, markAllRead, onNew } = useNotifications();

const panel = ref(null);
const filter = ref('all');
const filters = [
  { label: 'Tất cả', value: 'all' },
  { label: 'Chưa đọc', value: 'unread' },
  { label: 'Cần xử lý', value: 'task' }
];

const badgeLabel = computed(() => (state.unreadCount > 99 ? '99+' : String(state.unreadCount)));

const visibleItems = computed(() => {
  if (filter.value === 'unread') return state.items.filter((item) => item.status === 'UNREAD');
  if (filter.value === 'task') return state.items.filter((item) => item.category === 'task' && !item.resolved_at);
  return state.items;
});

const ICONS = {
  Quotation: 'pi pi-file-edit',
  SalesOrder: 'pi pi-shopping-cart',
  Delivery: 'pi pi-truck',
  WarehouseDispatch: 'pi pi-box',
  LeaveRequest: 'pi pi-calendar',
  Product: 'pi pi-box'
};

function iconFor(item) {
  const prefix = Object.keys(ICONS).find((key) => item.type.startsWith(key));
  return prefix ? ICONS[prefix] : 'pi pi-bell';
}

function timeAgo(iso) {
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000));
  if (seconds < 60) return 'Vừa xong';
  if (seconds < 3600) return `${Math.floor(seconds / 60)} phút trước`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)} giờ trước`;
  if (seconds < 7 * 86400) return `${Math.floor(seconds / 86400)} ngày trước`;
  return new Date(iso).toLocaleDateString('vi-VN');
}

function toggle(event) {
  panel.value.toggle(event);
}

function onScroll(event) {
  const el = event.target;
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 40) loadMore();
}

async function open(item) {
  panel.value.hide();
  markRead(item).catch(() => {});
  if (item.link) router.push(item.link);
}

let unsubscribe = null;

onMounted(() => {
  connect(storage.getToken());
  unsubscribe = onNew((item) => {
    toast.add({
      severity: item.category === 'task' ? 'warn' : 'info',
      summary: item.title,
      detail: item.body,
      life: 6000
    });
  });
});

onUnmounted(() => {
  unsubscribe?.();
  disconnect();
});
</script>
