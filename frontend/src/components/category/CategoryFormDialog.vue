<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="category ? 'Chỉnh Sửa Danh Mục' : 'Thêm Mới Danh Mục'"
    :style="{ width: '460px' }"
    class="rounded-[6px]"
  >
    <div class="flex flex-col gap-3 pt-1 text-xs">
      <div class="flex flex-col gap-1">
        <label for="cat-name" class="font-semibold text-slate-700">Tên danh mục <span class="text-red-600">*</span></label>
        <InputText
          id="cat-name"
          v-model="form.name"
          placeholder="Ví dụ: Vòng bi & Bạc đạn, Động cơ điện..."
          class="w-full rounded-[6px]"
          size="small"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="cat-desc" class="font-semibold text-slate-700">Mô tả chi tiết</label>
        <Textarea
          id="cat-desc"
          v-model="form.description"
          rows="3"
          placeholder="Mô tả phạm vi sản phẩm thuộc nhóm này..."
          class="w-full rounded-[6px] text-xs"
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
          :label="category ? 'Lưu thay đổi' : 'Tạo danh mục'"
          icon="pi pi-check"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px] !text-xs font-semibold"
          :loading="saving"
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
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  category: {
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
  name: '',
  description: ''
});

watch(
  () => props.category,
  (newVal) => {
    if (newVal) {
      form.value = {
        name: newVal.name || '',
        description: newVal.description || ''
      };
    } else {
      form.value = {
        name: '',
        description: ''
      };
    }
  },
  { immediate: true }
);
</script>
