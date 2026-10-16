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
      <div v-if="admin" class="flex flex-col gap-1">
        <label for="price-index" class="font-semibold text-slate-700">Price index (hệ số nhân)</label>
        <InputNumber id="price-index" v-model="form.price_index" :min="0.0001" :min-fraction-digits="0" :max-fraction-digits="4" class="w-full" input-class="w-full" />
        <small class="text-slate-500">Ví dụ 10 nghĩa là nhân 10 khi tính gross price.</small>
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
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import { storage } from '../../utils/storage';
import { parseJwt } from '../../utils/jwt';
import { isSuperAdmin } from '../../utils/rbac';

const admin = isSuperAdmin(parseJwt(storage.getToken()));

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
  name: '', price_index: null
});

watch(
  () => props.brand,
  (newVal) => {
    if (newVal) {
      form.value = { name: newVal.name || '', ...(admin ? { price_index: newVal.price_index == null ? null : Number(newVal.price_index) } : {}) };
    } else {
      form.value = { name: '', ...(admin ? { price_index: null } : {}) };
    }
  },
  { immediate: true }
);
</script>
