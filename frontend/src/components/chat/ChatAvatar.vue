<template>
  <div class="relative shrink-0" :style="{ width: `${size}px`, height: `${size}px` }">
    <img v-if="objectUrl" :src="objectUrl" :alt="name" class="w-full h-full rounded-full object-cover border border-slate-200" />
    <div
      v-else
      class="w-full h-full rounded-full flex items-center justify-center font-semibold text-white select-none"
      :class="color"
      :style="{ fontSize: `${Math.round(size * 0.38)}px` }"
    >
      <i v-if="group && !initials" class="pi pi-users" :style="{ fontSize: `${Math.round(size * 0.4)}px` }"></i>
      <span v-else>{{ initials }}</span>
    </div>
    <span
      v-if="online"
      class="absolute bottom-0 right-0 rounded-full bg-emerald-500 border border-slate-200"
      :style="{ width: `${Math.max(8, size * 0.28)}px`, height: `${Math.max(8, size * 0.28)}px` }"
    ></span>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useAuthBlob } from './useAuthBlob';

const props = defineProps({
  name: { type: String, default: '' },
  url: { type: String, default: '' },
  size: { type: Number, default: 36 },
  online: Boolean,
  group: Boolean
});

const COLORS = ['bg-sky-600', 'bg-emerald-600', 'bg-violet-600', 'bg-amber-600', 'bg-rose-600', 'bg-teal-600', 'bg-indigo-600', 'bg-orange-600'];

const { objectUrl } = useAuthBlob(() => props.url);

const initials = computed(() => {
  const words = props.name.trim().split(/\s+/).filter(Boolean);
  if (!words.length) return '';
  return (words.length === 1 ? words[0][0] : words[0][0] + words[words.length - 1][0]).toUpperCase();
});

const color = computed(() => {
  let hash = 0;
  for (const ch of props.name) hash = (hash * 31 + ch.charCodeAt(0)) | 0;
  return COLORS[Math.abs(hash) % COLORS.length];
});
</script>
