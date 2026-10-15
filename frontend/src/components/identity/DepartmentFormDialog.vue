<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="isEditing ? 'Chỉnh Sửa Thông Tin Phòng Ban' : 'Thêm Phòng Ban Mới'"
    :style="{ width: '480px' }"
  >
    <div class="py-2 flex flex-col gap-4 text-xs">
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Phòng Ban (Code) *</label>
        <InputText
          v-model="form.code"
          placeholder="Ví dụ: RND, HR, SALES..."
          size="small"
          class="!rounded-[6px] font-mono uppercase"
          :disabled="isEditing"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Tên Đơn Vị / Phòng Ban *</label>
        <InputText
          v-model="form.name"
          placeholder="Ví dụ: Phòng Nghiên Cứu & Phát Triển..."
          size="small"
          class="!rounded-[6px]"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mô Tả & Trách Nhiệm</label>
        <InputText
          v-model="form.description"
          placeholder="Mô tả chức năng nhiệm vụ của phòng ban..."
          size="small"
          class="!rounded-[6px]"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Trưởng Phòng</label>
        <Select
          v-model="form.manager_id"
          :options="users"
          optionLabel="name"
          optionValue="id"
          placeholder="Chọn trưởng phòng..."
          filter
          showClear
          size="small"
          class="w-full !rounded-[6px]"
        >
          <template #option="{ option }">
            <div class="flex flex-col">
              <span class="text-xs text-slate-800">{{ option.name }}</span>
              <span class="text-[11px] text-slate-500">{{ option.email }}</span>
            </div>
          </template>
        </Select>
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
          :label="isEditing ? 'Lưu Thay Đổi' : 'Tạo Phòng Ban'"
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
import Select from 'primevue/select';

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
  users: {
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
