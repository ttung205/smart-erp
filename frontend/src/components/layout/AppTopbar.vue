<template>
  <header class="h-14 bg-white border-b border-slate-200 px-6 flex items-center justify-between sticky top-0 z-40">
    <!-- Left: Breadcrumb / System Scope indicator -->
    <div class="flex items-center">
      <div class="flex items-center gap-2 text-[13px] font-semibold text-slate-600">
        <i class="pi pi-server text-slate-400"></i>
        <span>Hệ Thống Phân Tán Smart ERP</span>
      </div>
    </div>

    <!-- Center/Right: Compact System Health Link -->
    <div class="flex items-center gap-2">
      <router-link
        to="/service-status"
        class="flex items-center gap-2 px-2.5 py-1 rounded-[6px] border text-xs font-medium transition-colors cursor-pointer"
        :class="isAllOnline ? 'text-emerald-700 bg-emerald-50/60 border-emerald-200 hover:bg-emerald-50' : 'text-amber-700 bg-amber-50/60 border-amber-200 hover:bg-amber-50'"
        v-tooltip.bottom="'Xem trang chi tiết Trạng thái dịch vụ'"
      >
        <span class="w-2 h-2 rounded-full" :class="isAllOnline ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'"></span>
        <span>Hệ thống: {{ onlineCount }}/{{ totalCount }} Dịch vụ Online</span>
        <i class="pi pi-arrow-up-right text-[10px] text-slate-400"></i>
      </router-link>
    </div>

    <!-- Right: Current User Widget -->
    <div class="flex items-center gap-3">
      <div v-if="currentUser" class="flex items-center gap-2 px-2 py-1 rounded-[6px] border border-slate-200 bg-white">
        <Avatar
          :label="currentUser.name ? currentUser.name.charAt(0) : 'U'"
          shape="circle"
          class="!bg-sky-600 !text-white !font-bold !text-xs !w-7 !h-7"
        />
        <div class="flex flex-col leading-tight">
          <span class="text-xs font-semibold text-slate-800">{{ currentUser.name }}</span>
          <span class="text-[10px] text-slate-400">{{ currentUser.department }}</span>
        </div>
        <Button
          icon="pi pi-sign-out"
          text
          severity="danger"
          size="small"
          @click="$emit('logout')"
          v-tooltip.bottom="'Đăng xuất'"
          class="!w-7 !h-7 !p-0 ml-1"
        />
      </div>
      <Tag
        v-else
        severity="secondary"
        icon="pi pi-lock"
        value="Chưa đăng nhập"
        class="!text-[11px] !px-2 !py-1 !rounded-[6px]"
      />
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue';
import Tag from 'primevue/tag';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  },
  health: {
    type: Object,
    required: true
  }
});

defineEmits(['refresh-health', 'logout']);

const totalCount = computed(() => {
  if (!props.health) return 5;
  const count = Object.keys(props.health).length;
  return count > 0 ? count : 5;
});

const onlineCount = computed(() => {
  if (!props.health) return 0;
  return Object.values(props.health).filter(Boolean).length;
});

const isAllOnline = computed(() => {
  return totalCount.value > 0 && onlineCount.value === totalCount.value;
});
</script>
