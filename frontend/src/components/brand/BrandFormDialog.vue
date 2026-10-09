<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="brand ? 'Chỉnh Sửa Thương Hiệu' : 'Thêm Mới Thương Hiệu'"
    :style="{ width: '420px' }"
    class="rounded-[6px]"
  >
    <div class="flex flex-col gap-3 pt-1 text-xs">
      <div class="flex flex-col gap-1">
        <label for="brand-name" class="font-semibold text-slate-700">Tên thương hiệu <span class="text-red-600">*</span></label>
        <InputText
          id="brand-name"
          v-model="form.name"
          placeholder="Ví dụ: LG, SKF, Toshiba, Samsung..."
          class="w-full rounded-[6px]"
          size="small"
          @keyup.enter="$emit('save', form)"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 pt-2">
        <Button
          label="Hủy bỏ"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          :label="brand ? 'Cập Nhật' : 'Lưu Thương Hiệu'"
          icon="pi pi-check"
          size="small"
          :loading="saving"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
          @click="$emit('save', form)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  brand: {
    type: Object,
    default: null
  },
  saving: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'save']);

const form = ref({
  name: ''
});

watch(
  () => props.brand,
  (newVal) => {
    if (newVal) {
      form.value = { name: newVal.name || '' };
    } else {
      form.value = { name: '' };
    }
  },
  { immediate: true }
);
</script>
