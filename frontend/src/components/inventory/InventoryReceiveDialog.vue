<template>
  <Dialog 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    modal 
    header="Lập Phiếu Nhập Kho Hàng Hóa (Goods Receipt)" 
    :style="{ width: '480px' }"
  >
    <div class="py-2 flex flex-col gap-4 text-xs">
      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Chứng Từ (PO / Receipt Code) *</label>
        <InputText 
          v-model="form.referenceDoc" 
          placeholder="Ví dụ: PO-202609-0099" 
          class="!text-xs w-full font-mono !rounded-[6px]" 
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Kho Bãi Đích Nhập *</label>
        <Select 
          v-model="form.warehouseId" 
          :options="warehouseOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Chọn kho bãi..." 
          class="w-full !rounded-[6px]" 
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Nhập Kho *</label>
        <Select 
          v-model="form.productId" 
          :options="productOptions" 
          optionLabel="label" 
          optionValue="value" 
          filter
          filterPlaceholder="Tìm nhanh mã SKU hoặc tên sản phẩm..."
          placeholder="Chọn sản phẩm..." 
          class="w-full !rounded-[6px]" 
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Số Lượng Nhập Thực Tế *</label>
        <InputNumber 
          v-model="form.quantity" 
          :min="1" 
          :max="10000" 
          class="w-full font-mono !rounded-[6px]" 
          placeholder="Nhập số lượng..." 
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Ghi Chú Nghiệp Vụ</label>
        <InputText 
          v-model="form.notes" 
          placeholder="Ghi chú xuất xứ hoặc hợp đồng..." 
          class="!text-xs w-full !rounded-[6px]" 
        />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full pt-2">
        <Button label="Hủy Bỏ" text size="small" class="!rounded-[6px]" @click="$emit('update:visible', false)" />
        <Button 
          label="Xác Nhận Nhập Kho" 
          size="small" 
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]" 
          :loading="loading" 
          @click="$emit('submit', form)" 
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Button from 'primevue/button';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  form: {
    type: Object,
    default: () => ({})
  },
  warehouseOptions: {
    type: Array,
    default: () => []
  },
  productOptions: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'submit']);
</script>
