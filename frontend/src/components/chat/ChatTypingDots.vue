<template>
  <span class="inline-flex items-center gap-[3px]" aria-label="Đang soạn tin">
    <span v-for="index in 3" :key="index" class="rounded-full bg-current opacity-70 animate-bounce" :class="[size === 4 ? 'w-1 h-1' : 'w-1.5 h-1.5', index === 1 ? '[animation-delay:-320ms]' : index === 2 ? '[animation-delay:-160ms]' : '']"></span>
  </span>
</template>

<script setup>
defineProps({ size: { type: Number, default: 6 } });
</script>
