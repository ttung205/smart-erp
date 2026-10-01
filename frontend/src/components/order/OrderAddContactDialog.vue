<template>
  <Dialog
    :visible="visible"
    modal
    header="Thêm Người Liên Hệ Khách Hàng"
    :style="{ width: '450px' }"
    class="rounded-[8px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="flex flex-col gap-3 pt-2 text-xs">
      <div class="flex flex-col gap-1">
        <label class="font-semibold text-slate-700">Họ và tên người nhận hàng / liên hệ <span class="text-rose-500">*</span></label>
        <InputText
          v-model="contactForm.name"
          placeholder="Ví dụ: Nguyễn Văn Hùng"
          class="w-full text-xs rounded-[6px] h-9 px-3"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label class="font-semibold text-slate-700">Chức vụ / Bộ phận</label>
        <InputText
          v-model="contactForm.position"
          placeholder="Ví dụ: Thủ kho, Trưởng phòng vật tư..."
          class="w-full text-xs rounded-[6px] h-9 px-3"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label class="font-semibold text-slate-700">Số điện thoại liên lạc</label>
        <InputText
          v-model="contactForm.phone"
          placeholder="09xx xxx xxx"
          class="w-full text-xs rounded-[6px] h-9 px-3"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label class="font-semibold text-slate-700">Email nhận thông báo</label>
        <InputText
          v-model="contactForm.email"
          placeholder="email@doanhnghiep.com"
          class="w-full text-xs rounded-[6px] h-9 px-3"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 pt-2">
        <Button
          label="Hủy"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Lưu thông tin"
          icon="pi pi-check"
          size="small"
          class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 rounded-[6px]"
          @click="handleSave"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { reactive, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:visible', 'save']);

const contactForm = reactive({
  name: '',
  position: '',
  phone: '',
  email: ''
});

watch(
  () => props.visible,
  (val) => {
    if (val) {
      contactForm.name = '';
      contactForm.position = '';
      contactForm.phone = '';
      contactForm.email = '';
    }
  }
);

const handleSave = () => {
  if (!contactForm.name.trim()) {
    alert('Vui lòng nhập họ và tên người liên hệ!');
    return;
  }
  emit('save', { ...contactForm });
  emit('update:visible', false);
};
</script>
