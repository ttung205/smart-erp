<template>
  <Dialog
    :visible="visible"
    modal
    header="Thêm Mới Sản Phẩm"
    :style="{ width: '520px' }"
    :closable="!loading"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="form-content">
      <div class="form-field">
        <label for="sku" class="field-label">Mã SKU <span class="text-danger">*</span></label>
        <InputText
          id="sku"
          v-model="form.sku"
          placeholder="Ví dụ: PROD-DELL-5520"
          class="w-full"
          size="small"
        />
      </div>

      <div class="form-field">
        <label for="name" class="field-label">Tên sản phẩm <span class="text-danger">*</span></label>
        <InputText
          id="name"
          v-model="form.name"
          placeholder="Ví dụ: Laptop Dell XPS 15 9520"
          class="w-full"
          size="small"
        />
      </div>

      <div class="form-row">
        <div class="form-field">
          <label for="gross_price" class="field-label">Giá niêm yết (VNĐ)</label>
          <InputNumber
            id="gross_price"
            v-model="form.gross_price"
            class="w-full"
            size="small"
            currency="VND"
            locale="vi-VN"
          />
        </div>
        <div class="form-field">
          <label for="cost_price" class="field-label">Giá vốn (VNĐ)</label>
          <InputNumber
            id="cost_price"
            v-model="form.cost_price"
            class="w-full"
            size="small"
            currency="VND"
            locale="vi-VN"
          />
        </div>
      </div>

      <div class="form-field">
        <label for="min_stock" class="field-label">Định mức tồn an toàn</label>
        <InputNumber
          id="min_stock"
          v-model="form.min_stock"
          class="w-full"
          size="small"
        />
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer-actions">
        <Button
          label="Hủy bỏ"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          :disabled="loading"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Lưu sản phẩm"
          icon="pi pi-check"
          size="small"
          :loading="loading"
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

<style scoped>
.form-content {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding-top: 4px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-body);
}

.text-danger {
  color: #b91c1c;
}

.w-full {
  width: 100%;
}

.dialog-footer-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
