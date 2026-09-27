<template>
  <div class="flex justify-between items-start gap-4 mb-1">
    <div class="flex flex-col gap-0.5">
      <h1 class="text-base font-bold text-slate-900 leading-tight m-0 tracking-tight">{{ title }}</h1>
      <p v-if="subtitle" class="text-xs text-slate-500 leading-normal m-0">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="flex items-center gap-2 shrink-0">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  }
});
</script>
