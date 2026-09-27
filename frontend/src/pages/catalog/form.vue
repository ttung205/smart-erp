<template>
  <Dialog
    :visible="visible"
    modal
    header="Thêm Mới Sản Phẩm"
    :style="{ width: '520px' }"
    :closable="!loading"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="flex flex-col gap-3 pt-1">
      <div class="flex flex-col gap-1">
        <label for="sku" class="text-xs font-semibold text-slate-700">Mã SKU <span class="text-red-600">*</span></label>
        <InputText
          id="sku"
          v-model="form.sku"
          placeholder="Ví dụ: PROD-DELL-5520"
          class="w-full rounded-[6px]"
          size="small"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label for="name" class="text-xs font-semibold text-slate-700">Tên sản phẩm <span class="text-red-600">*</span></label>
        <InputText
          id="name"
          v-model="form.name"
          placeholder="Ví dụ: Laptop Dell XPS 15 9520"
          class="w-full rounded-[6px]"
          size="small"
        />
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label for="gross_price" class="text-xs font-semibold text-slate-700">Giá niêm yết (VNĐ)</label>
          <InputNumber
            id="gross_price"
            v-model="form.gross_price"
            class="w-full rounded-[6px]"
            size="small"
            currency="VND"
            locale="vi-VN"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label for="cost_price" class="text-xs font-semibold text-slate-700">Giá vốn (VNĐ)</label>
          <InputNumber
            id="cost_price"
            v-model="form.cost_price"
            class="w-full rounded-[6px]"
            size="small"
            currency="VND"
            locale="vi-VN"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label for="min_stock" class="text-xs font-semibold text-slate-700">Định mức tồn an toàn</label>
        <InputNumber
          id="min_stock"
          v-model="form.min_stock"
          class="w-full rounded-[6px]"
          size="small"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy bỏ"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          :disabled="loading"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Lưu sản phẩm"
          icon="pi pi-check"
          size="small"
          :loading="loading"
          class="rounded-[6px]"
          @click="$emit('submit', form)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { reactive, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import { DEFAULT_PRODUCT_FORM } from '../../constants/forms.constant';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  loading: {
    type: Boolean,
    default: false
  },
  initialData: {
    type: Object,
    default: null
  }
});

defineEmits(['update:visible', 'submit']);

const form = reactive({ ...DEFAULT_PRODUCT_FORM });

watch(
  () => props.initialData,
  (newVal) => {
    if (newVal) {
      Object.assign(form, newVal);
    } else {
      Object.assign(form, DEFAULT_PRODUCT_FORM);
    }
  },
  { immediate: true }
);
</script>
