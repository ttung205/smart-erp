<template>
  <Dialog
    :visible="visible"
    modal
    header="Hủy Khóa Giữ Chỗ Tồn Kho (Release Reservation)"
    :style="{ width: '500px' }"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="py-2 flex flex-col gap-4">
      <p class="text-xs text-slate-500 mb-0 leading-relaxed">
        Giải phóng lượng hàng đang bị khóa giữ chỗ (Reserved) khi Đơn bán hàng (SO) bị hủy, giảm số lượng hoặc hết hạn thanh toán. Lượng hàng này sẽ được hoàn trả trực tiếp về số dư <strong>Khả dụng (Available)</strong> để bán tiếp.
      </p>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Đơn Bán Hàng Cần Hủy Giữ *</label>
        <InputText
          v-model="form.orderId"
          placeholder="Ví dụ: SO-202609-0088"
          class="!text-xs w-full font-mono !rounded-[6px]"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Kho Bãi Đang Giữ Hàng *</label>
        <Select
          v-model="form.warehouseId"
          :options="warehouseOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Chọn kho..."
          class="w-full !rounded-[6px]"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Cần Hủy Giữ Chỗ *</label>
        <Select
          v-model="form.productId"
          :options="productOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Chọn mặt hàng..."
          class="w-full !rounded-[6px]"
        />
      </div>

      <div v-if="selectedStock" class="p-3 rounded-[6px] border text-xs" :class="selectedStock.quantity_reserved > 0 ? 'bg-amber-50/70 border-amber-200 text-amber-950' : 'bg-slate-50 border-slate-200 text-slate-700'">
        <div class="flex justify-between items-center mb-1.5">
          <span class="text-slate-600">Tồn kho trên kệ (On-Hand):</span>
          <span class="font-mono font-bold text-slate-800">{{ selectedStock.quantity_on_hand }}</span>
        </div>
        <div class="flex justify-between items-center mb-1.5">
          <span class="text-slate-600">Đang bị khóa giữ chỗ (Reserved):</span>
          <span class="font-mono font-bold text-amber-700">{{ selectedStock.quantity_reserved }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-slate-600">Khả dụng hiện tại (Available):</span>
          <span class="font-mono font-bold text-emerald-700">{{ selectedStock.available }}</span>
        </div>
        <div v-if="selectedStock.quantity_reserved === 0" class="mt-2.5 text-[11px] leading-relaxed text-rose-700 border-t border-rose-200/80 pt-2">
          <span>Mặt hàng này hiện không có số lượng nào đang bị khóa giữ chỗ (Reserved = 0).</span>
        </div>
      </div>

      <div class="flex flex-col gap-1.5">
        <div class="flex justify-between items-center mb-1">
          <label class="block text-xs font-semibold text-slate-700">Số Lượng Hủy Giữ Chỗ *</label>
          <span v-if="selectedStock && selectedStock.quantity_reserved > 0" class="text-[11px] text-amber-700 font-medium">
            (Tối đa giải phóng: {{ selectedStock.quantity_reserved }})
          </span>
        </div>
        <InputNumber
          v-model="form.quantity"
          :min="1"
          :max="selectedStock?.quantity_reserved || 10000"
          class="w-full font-mono !rounded-[6px]"
          placeholder="Nhập số lượng hủy..."
        />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full pt-2">
        <Button label="Đóng" text size="small" class="!rounded-[6px]" @click="$emit('update:visible', false)" />
        <Button
          label="Xác Nhận Hủy Giữ Chỗ"
          size="small"
          class="!bg-amber-600 hover:!bg-amber-700 !text-white !border-amber-600 !rounded-[6px]"
          :loading="loading"
          :disabled="!selectedStock || selectedStock.quantity_reserved === 0"
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
  selectedStock: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
})

defineEmits(['update:visible', 'submit'])
</script>
