<template>
  <div class="p-8 text-center text-slate-500">
    <i class="pi pi-spin pi-spinner text-2xl text-sky-600 mb-2"></i>
    <p>Đang chuyển hướng sang phân hệ Bán hàng...</p>
  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

onMounted(() => {
  router.replace('/sales/quotation');
});
</script>
