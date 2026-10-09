<template>
  <Dialog 
    :visible="visible" 
    modal 
    header="Lập Phiếu Điều Chuyển Kho Nội Bộ (Stock Transfer)" 
    :style="{ width: '520px' }"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="py-2 flex flex-col gap-4">
      <p class="text-xs text-slate-500 mb-0 leading-relaxed">
        Điều chuyển cân đối hàng hóa giữa các kho chi nhánh, tự động cập nhật sổ cái thẻ kho cho cả kho nguồn và kho đích.
      </p>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Lệnh Điều Chuyển *</label>
        <InputText 
          v-model="form.transferDoc" 
          placeholder="Ví dụ: TRF-202609-0012" 
          class="!text-xs w-full font-mono !rounded-[6px]" 
        />
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Kho Nguồn (Xuất Đi) *</label>
          <Select 
            v-model="form.sourceWarehouseId" 
            :options="warehouseOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn kho nguồn..." 
            class="w-full !rounded-[6px]" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Kho Đích (Nhận Về) *</label>
          <Select 
            v-model="form.targetWarehouseId" 
            :options="warehouseOptions" 
            optionLabel="label" 
            optionValue="value" 
            placeholder="Chọn kho đích..." 
            class="w-full !rounded-[6px]" 
          />
        </div>
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Điều Chuyển *</label>
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
        <label class="block text-xs font-semibold text-slate-700">Số Lượng Điều Chuyển *</label>
        <InputNumber 
          v-model="form.quantity" 
          :min="1" 
          :max="10000" 
          class="w-full font-mono !rounded-[6px]" 
          placeholder="Nhập số lượng..." 
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Ghi Chú & Phương Tiện</label>
        <InputText 
          v-model="form.notes" 
          placeholder="Lý do điều chuyển, xe vận tải..." 
          class="!text-xs w-full !rounded-[6px]" 
        />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full pt-2">
        <Button label="Hủy Bỏ" text size="small" class="!rounded-[6px]" @click="$emit('update:visible', false)" />
        <Button 
          label="Xác Nhận Điều Chuyển" 
          size="small" 
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]" 
          :loading="loading" 
          @click="$emit('submit')" 
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Select from 'primevue/select'

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  form: {
    type: Object,
    required: true
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
})

defineEmits(['update:visible', 'submit'])
</script>
