<template>
  <div class="min-h-[480px] flex flex-col items-center justify-center p-8 text-center bg-white border border-slate-200 rounded-[6px]">
    <div class="w-16 h-16 rounded-full bg-red-50 border border-red-200 flex items-center justify-center mb-4">
      <i class="pi pi-lock text-red-600 text-2xl"></i>
    </div>

    <h2 class="text-xl font-bold text-slate-900 m-0 mb-2">Truy Cập Bị Từ Chối (403 Forbidden)</h2>
    <p class="text-sm text-slate-500 max-w-md m-0 mb-6 leading-relaxed">
      Tài khoản của bạn không có quyền xem trang này. Bạn cần quyền <strong class="font-mono text-red-600 bg-red-50 px-1.5 py-0.5 rounded border border-red-200">{{ requiredPermission }}</strong> để truy cập.
    </p>

    <div class="flex items-center gap-3">
      <Button
        label="Về trang được phép"
        icon="pi pi-home"
        size="small"
        class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
        @click="$router.push('/')"
      />
      <Button
        label="Đăng nhập tài khoản khác"
        icon="pi pi-sign-out"
        severity="secondary"
        size="small"
        class="!rounded-[6px]"
        @click="handleLogout"
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import Button from 'primevue/button';
import { storage } from '../../utils/storage';

const route = useRoute();
const router = useRouter();

const requiredPermission = computed(() => {
  return route.query.permission || 'quyền tương ứng';
});

const handleLogout = () => {
  storage.clear();
  router.push('/login');
};
</script>
