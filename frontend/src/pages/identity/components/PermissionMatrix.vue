<template>
  <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3 shadow-none">
    <div class="flex justify-between items-center pb-2 border-b border-slate-200">
      <span class="text-sm font-bold text-slate-900">Ma trận quyền hạn (RBAC)</span>
      <Tag
        severity="secondary"
        :value="systemPermissions.length + ' Quyền hệ thống'"
        class="text-[11px] px-2 py-0.5 rounded-[6px]"
      />
    </div>

    <p class="text-xs text-slate-500 m-0">
      Hệ thống kiểm tra quyền hạn phi trạng thái (Stateless Authorization) trực tiếp từ thông tin trong JWT Token:
    </p>

    <div v-if="currentUser" class="flex flex-wrap gap-1.5">
      <Tag
        v-for="perm in systemPermissions"
        :key="perm.code"
        :severity="currentUser.permissions && currentUser.permissions.includes(perm.code) ? 'success' : 'secondary'"
        class="text-[11px] px-2 py-1 rounded-[6px]"
      >
        <template #default>
          <div class="flex items-center gap-1.5">
            <i :class="currentUser.permissions && currentUser.permissions.includes(perm.code) ? 'pi pi-check' : 'pi pi-times'"></i>
            <span>{{ perm.code }}</span>
          </div>
        </template>
      </Tag>
    </div>

    <div v-else class="p-6 text-center text-xs text-slate-500">
      Chưa có thông tin phân quyền.
    </div>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag';
import { SYSTEM_PERMISSIONS } from '../../../constants/permissions.constant';

defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const systemPermissions = SYSTEM_PERMISSIONS;
</script>
