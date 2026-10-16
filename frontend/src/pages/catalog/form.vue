<template>
  <Dialog
    :visible="visible"
    modal
    header="Thêm Mới Sản Phẩm Catalog"
    :style="{ width: '640px' }"
    :closable="!loading"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="flex flex-col gap-3 pt-1">
      <div class="grid grid-cols-2 gap-3">
        <!-- SKU -->
        <div class="flex flex-col gap-1">
          <label for="sku" class="text-xs font-semibold text-slate-700">Mã SKU <span class="text-red-600">*</span></label>
          <InputText
            id="sku"
            v-model="form.sku"
            placeholder="Ví dụ: XQ530-LG"
            class="w-full rounded-[6px] font-mono"
            size="small"
          />
        </div>

        <!-- Designation -->
        <div class="flex flex-col gap-1">
          <label for="designation" class="text-xs font-semibold text-slate-700">Designation (Quy cách)</label>
          <InputText
            id="designation"
            v-model="form.designation"
            placeholder="Ví dụ: XQ530"
            class="w-full rounded-[6px] font-mono text-rose-700"
            size="small"
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <!-- Brand -->
        <div class="flex flex-col gap-1">
          <label for="brand" class="text-xs font-semibold text-slate-700">Thương hiệu (Brand)</label>
          <InputText
            id="brand"
            v-model="form.brand"
            placeholder="Ví dụ: LG, SKF, Toshiba..."
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <!-- Category -->
        <div class="flex flex-col gap-1">
          <label for="category" class="text-xs font-semibold text-slate-700">Danh mục (Category)</label>
          <InputText
            id="category"
            v-model="form.category"
            placeholder="Ví dụ: Tủ lạnh, Vòng bi..."
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>
      </div>

      <!-- Item Description -->
      <div class="flex flex-col gap-1">
        <label for="description" class="text-xs font-semibold text-slate-700">Mô tả sản phẩm (Item Description) <span class="text-red-600">*</span></label>
        <InputText
          id="description"
          v-model="form.description"
          placeholder="Ví dụ: Tủ lạnh LG Ngăn đá dưới 350 lít"
          class="w-full rounded-[6px]"
          size="small"
        />
      </div>

      <div class="grid grid-cols-3 gap-3">
        <!-- COM (Xuất xứ) -->
        <div class="flex flex-col gap-1">
          <label for="com" class="text-xs font-semibold text-slate-700">Xuất xứ (COM)</label>
          <InputText
            id="com"
            v-model="form.com"
            placeholder="Việt Nam"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <!-- Unit -->
        <div class="flex flex-col gap-1">
          <label for="unit" class="text-xs font-semibold text-slate-700">Đơn vị tính (Unit)</label>
          <InputText
            id="unit"
            v-model="form.unit"
            placeholder="Cái"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <!-- Gross Price -->
        <div class="flex flex-col gap-1">
          <label for="gross_price" class="text-xs font-semibold text-slate-700">Giá gross</label>
          <InputNumber
            id="gross_price"
            v-model="form.gross_price"
            class="w-full rounded-[6px]"
            size="small"
            currency="VND"
            locale="vi-VN"
          />
        </div>
      </div>

      <!-- Specs -->
      <div class="flex flex-col gap-1">
        <label for="spec" class="text-xs font-semibold text-slate-700">Thông số kỹ thuật (Specs)</label>
        <Textarea
          id="spec"
          v-model="form.spec"
          rows="2"
          placeholder="Dung tích: 350L; Inverter; Điện áp: 220V..."
          class="w-full rounded-[6px] text-xs"
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
          class="rounded-[6px] !bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600"
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
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';

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
    default: () => ({})
  }
});

defineEmits(['update:visible', 'submit']);

const form = reactive({
  sku: '',
  designation: '',
  brand: '',
  category: '',
  description: '',
  com: 'Việt Nam',
  unit: 'Cái',
  gross_price: 0,
  spec: ''
});

watch(
  () => props.initialData,
  (val) => {
    if (val) {
      Object.assign(form, {
        sku: val.sku || '',
        designation: val.designation || '',
        brand: val.brand || '',
        category: val.category || '',
        description: val.description || val.name || '',
        com: val.com || 'Việt Nam',
        unit: val.unit || 'Cái',
        gross_price: val.gross_price || 0,
        spec: val.spec || ''
      });
    }
  },
  { immediate: true }
);
</script>
