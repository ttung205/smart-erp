<template>
  <Dialog 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    modal 
    header="Thử Nghiệm Khóa Giữ Chỗ Tồn Kho (Sales Order Reserve)" 
    :style="{ width: '480px' }"
  >
    <div class="py-2 flex flex-col gap-4 text-xs">
      <p class="text-xs text-slate-500 mb-0 leading-relaxed">
        Form này giả lập sự kiện <code class="font-mono text-sky-700 bg-sky-50 px-1 py-0.5 rounded-[4px]">SalesOrderCreated</code> từ Sales Service. Thử nhập số lượng lớn hơn <strong>Available</strong> để kiểm tra cơ chế chặn bán âm!
      </p>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Đơn Bán Hàng (SO Code) *</label>
        <InputText 
          v-model="form.orderId" 
          placeholder="Ví dụ: SO-202609-0088" 
          class="!text-xs w-full font-mono !rounded-[6px]" 
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Kho Bãi Xuất Hàng *</label>
        <Select 
          v-model="form.warehouseId" 
          :options="warehouseOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Chọn kho xuất..." 
          class="w-full !rounded-[6px]" 
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Cần Giữ Chỗ *</label>
        <Select 
          v-model="form.productId" 
          :options="productOptions" 
          optionLabel="label" 
          optionValue="value" 
          placeholder="Chọn mặt hàng..." 
          class="w-full !rounded-[6px]" 
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Số Lượng Giữ Chỗ *</label>
        <InputNumber 
          v-model="form.quantity" 
          :min="1" 
          :max="10000" 
          class="w-full font-mono !rounded-[6px]" 
          placeholder="Nhập số lượng..." 
        />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full pt-2">
        <Button label="Hủy Bỏ" text size="small" class="!rounded-[6px]" @click="$emit('update:visible', false)" />
        <Button 
          label="Khóa Giữ Hàng (Reserve)" 
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
