<template>
  <header class="h-14 bg-white border-b border-slate-200 px-6 flex items-center justify-between sticky top-0 z-40">
    <!-- Left: Breadcrumb / System Scope indicator -->
    <div class="flex items-center">
      <div class="flex items-center gap-2 text-[13px] font-semibold text-slate-600">
        <i class="pi pi-server text-slate-400"></i>
        <span>Hệ Thống Phân Tán Smart ERP</span>
      </div>
    </div>

    <!-- Center/Right: Microservices Status -->
    <div class="flex items-center gap-2">
      <Tag
        :severity="health.identity ? 'success' : 'danger'"
        :value="'Identity :8001 • ' + (health.identity ? 'Sẵn sàng' : 'Mất kết nối')"
        class="!text-[11px] !px-2 !py-1 !rounded-[6px]"
      />
      <Tag
        :severity="health.catalog ? 'success' : 'danger'"
        :value="'Catalog :8002 • ' + (health.catalog ? 'Sẵn sàng' : 'Mất kết nối')"
        class="!text-[11px] !px-2 !py-1 !rounded-[6px]"
      />
      <Tag
        :severity="health.inventory ? 'success' : 'danger'"
        :value="'Inventory :8004 • ' + (health.inventory ? 'Sẵn sàng' : 'Mất kết nối')"
        class="!text-[11px] !px-2 !py-1 !rounded-[6px]"
      />
      <Button
        icon="pi pi-refresh"
        text
        size="small"
        severity="secondary"
        @click="$emit('refresh-health')"
        v-tooltip.bottom="'Kiểm tra lại trạng thái API'"
        class="!w-7 !h-7 !p-0"
      />
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
    required: true
  }
});

defineEmits(['refresh-health', 'logout']);
</script>
