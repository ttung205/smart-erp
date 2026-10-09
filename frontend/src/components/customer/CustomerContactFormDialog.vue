<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="isEdit ? 'Chỉnh Sửa Thông Tin Người Liên Hệ' : 'Thêm Người Liên Hệ Mới'"
    :style="{ width: '560px' }"
    class="rounded-[8px]"
  >
    <div class="flex flex-col gap-3.5 pt-2 text-xs">
      <div class="grid grid-cols-2 gap-3">
        <!-- Họ tên -->
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide">
            Họ và tên <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="formData.name"
            placeholder="VD: Nguyễn Văn A..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>

        <!-- Chức vụ / Phòng ban -->
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide">
            Chức vụ / Phòng ban
          </label>
          <InputText
            v-model="formData.position"
            placeholder="VD: Trưởng phòng Mua hàng..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <!-- Số điện thoại -->
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide">
            Số điện thoại
          </label>
          <InputText
            v-model="formData.phone"
            placeholder="VD: 0912 345 678..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>

        <!-- Email -->
        <div class="flex flex-col gap-1.5">
          <label class="font-bold text-slate-700 uppercase tracking-wide">
            Email công việc
          </label>
          <InputText
            v-model="formData.email"
            placeholder="VD: anva@longson.vn..."
            class="w-full text-xs rounded-[6px] h-9 px-3"
          />
        </div>
      </div>

      <!-- Ghi chú -->
      <div class="flex flex-col gap-1.5">
        <label class="font-bold text-slate-700 uppercase tracking-wide">
          Ghi chú thêm
        </label>
        <InputText
          v-model="formData.notes"
          placeholder="VD: Phụ trách mảng thiết bị áp lực, tiếp nhận báo giá..."
          class="w-full text-xs rounded-[6px] h-9 px-3"
        />
      </div>

      <!-- Đặt làm liên hệ chính -->
      <div class="pt-2">
        <label class="flex items-center gap-2 cursor-pointer text-slate-700 select-none">
          <input
            type="checkbox"
            v-model="formData.is_primary"
            class="w-4 h-4 rounded text-sky-600 border-slate-300"
          />
          <span class="font-semibold text-xs">Đặt làm người liên hệ chính của khách hàng</span>
        </label>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 pt-3">
        <Button
          label="Hủy"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Xác nhận"
          icon="pi pi-check"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px] !px-4"
          @click="$emit('save', formData)"
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
  isEdit: {
    type: Boolean,
    default: false
  },
  contact: {
    type: Object,
    default: () => ({
      name: '',
      position: '',
      phone: '',
      email: '',
      notes: '',
      is_primary: false
    })
  }
});

const emit = defineEmits(['update:visible', 'save']);

const formData = ref({ ...props.contact });

watch(
  () => props.contact,
  (newVal) => {
    formData.value = { ...newVal };
  },
  { deep: true, immediate: true }
);
</script>
