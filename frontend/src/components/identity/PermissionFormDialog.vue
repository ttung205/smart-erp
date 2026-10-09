<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="isEditing ? 'Chỉnh Sửa Thông Tin Quyền Hạn' : 'Định Nghĩa Quyền Hạn Mới'"
    :style="{ width: '480px' }"
  >
    <div class="py-2 flex flex-col gap-4 text-xs">
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Quyền (Code) *</label>
        <InputText
          v-model="form.code"
          placeholder="Ví dụ: inventory.transfer, order.approve"
          size="small"
          class="!rounded-[6px] font-mono"
          :disabled="isEditing"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Phân Hệ (Module) *</label>
        <Select
          v-model="form.module"
          :options="availableModules"
          optionLabel="label"
          optionValue="value"
          placeholder="Chọn phân hệ áp dụng..."
          size="small"
          class="w-full !rounded-[6px]"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Diễn Giải Quyền Hạn *</label>
        <InputText
          v-model="form.description"
          placeholder="Mô tả quyền hạn được cấp và phạm vi ảnh hưởng..."
          size="small"
          class="!rounded-[6px]"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full pt-2">
        <Button
          label="Hủy Bỏ"
          text
          size="small"
          severity="secondary"
          class="!rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          :label="isEditing ? 'Lưu Thay Đổi' : 'Thêm Quyền'"
          size="small"
          :loading="loading"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
          @click="$emit('save', form)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  isEditing: {
    type: Boolean,
    default: false
  },
  form: {
    type: Object,
    default: () => ({})
  },
  availableModules: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'save']);
</script>
