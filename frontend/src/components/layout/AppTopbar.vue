<template>
  <header class="h-14 bg-white border-b border-slate-200 px-6 flex items-center justify-end sticky top-0 z-40">
    <!-- Right: Current User Widget -->
    <div class="flex items-center gap-3">
      <div v-if="currentUser" class="flex items-center gap-2 px-2 py-1 rounded-[6px] border border-slate-200 bg-white">
        <Avatar :label="currentUser.name ? currentUser.name.charAt(0) : 'U'" shape="circle"
          class="!bg-sky-600 !text-white !font-bold !text-xs !w-7 !h-7" />
        <div class="flex flex-col leading-tight">
          <span class="text-xs font-semibold text-slate-800">{{ currentUser.name }}</span>
          <span class="text-[10px] text-slate-400">{{ currentUser.department }}</span>
        </div>
        <Button icon="pi pi-sign-out" text severity="danger" size="small" @click="$emit('logout')"
          v-tooltip.bottom="'Đăng xuất'" class="!w-7 !h-7 !p-0 ml-1" />
      </div>
      <Tag v-else severity="secondary" icon="pi pi-lock" value="Chưa đăng nhập"
        class="!text-[11px] !px-2 !py-1 !rounded-[6px]" />
    </div>
  </header>
</template>

<script setup>
import Tag from 'primevue/tag';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';

defineProps({
  currentUser: {
    type: Object,
    default: null
  },
  health: {
    type: Object,
    default: () => ({})
  }
});

defineEmits(['refresh-health', 'logout']);
</script>
