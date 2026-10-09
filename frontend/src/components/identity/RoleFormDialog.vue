<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="isEditing ? 'Chỉnh Sửa Thông Tin Vai Trò' : 'Thêm Vai Trò Mới'"
    :style="{ width: '460px' }"
  >
    <div class="py-2 flex flex-col gap-4 text-xs">
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Vai Trò (Định danh hệ thống) *</label>
        <InputText
          v-model="form.code"
          placeholder="Ví dụ: INVENTORY_CLERK"
          size="small"
          class="!rounded-[6px] font-mono uppercase"
          :disabled="isEditing"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Tên Hiển Thị Vai Trò *</label>
        <InputText
          v-model="form.name"
          placeholder="Ví dụ: Nhân viên thủ kho"
          size="small"
          class="!rounded-[6px]"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mô Tả Nhiệm Vụ & Thẩm Quyền</label>
        <InputText
          v-model="form.description"
          placeholder="Mô tả phạm vi trách nhiệm nghiệp vụ..."
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
          :label="isEditing ? 'Lưu Thay Đổi' : 'Tạo Vai Trò'"
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
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'save']);
</script>
