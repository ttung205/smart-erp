<template>
  <Dialog :visible="visible" modal :header="title" :style="{ width: '380px' }" @update:visible="emit('update:visible', $event)">
    <form class="flex flex-col gap-2 pt-1" @submit.prevent="submit">
      <label class="text-xs font-semibold text-slate-700">{{ label }}</label>
      <InputText v-model="value" :maxlength="maxlength" :placeholder="placeholder" class="w-full !text-sm" autofocus />
      <span v-if="hint" class="text-[11px] text-slate-400">{{ hint }}</span>
    </form>
    <template #footer>
      <Button label="Huỷ" text size="small" severity="secondary" @click="emit('update:visible', false)" />
      <Button label="Lưu" size="small" :loading="saving" :disabled="required && !value.trim()" @click="submit" />
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const props = defineProps({
  visible: Boolean,
  title: { type: String, default: '' },
  label: { type: String, default: '' },
  initial: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  hint: { type: String, default: '' },
  maxlength: { type: Number, default: 120 },
  required: Boolean,
  saving: Boolean
});

const emit = defineEmits(['update:visible', 'submit']);
const value = ref('');

watch(() => props.visible, (open) => {
  if (open) value.value = props.initial;
});

function submit() {
  if (props.required && !value.value.trim()) return;
  emit('submit', value.value.trim());
}
</script>
