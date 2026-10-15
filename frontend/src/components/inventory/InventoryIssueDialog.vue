<template>
  <Dialog
    :visible="visible"
    modal
    header="Lập Phiếu Xuất Kho Giao Hàng (Goods Issue / Delivery)"
    :style="{ width: '480px' }"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="py-2 flex flex-col gap-4">
      <p class="text-xs text-slate-500 mb-0 leading-relaxed">
        Ghi nhận xuất kho thực tế khi hàng rời khỏi kho để giao đến khách hàng, tự động trừ số lượng tồn trên kệ và giải phóng lượng đang giữ chỗ.
      </p>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Phiếu Xuất / Vận Đơn *</label>
        <InputText
          v-model="form.deliveryId"
          placeholder="Ví dụ: DEL-202609-0099"
          class="!text-xs w-full font-mono !rounded-[6px]"
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Mã Đơn Bán Hàng (SO Code)</label>
        <InputText
          v-model="form.orderId"
          placeholder="Ví dụ: SO-202609-0042"
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
        <label class="block text-xs font-semibold text-slate-700">Mặt Hàng Cần Xuất *</label>
        <Select
          v-model="form.productId"
          :options="productOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Chọn mặt hàng..."
          class="w-full !rounded-[6px]"
        />
      </div>

      <div v-if="selectedStock" class="p-3 rounded-[6px] border text-xs" :class="selectedStock.quantity_reserved > 0 ? 'bg-sky-50/60 border-sky-200 text-sky-950' : 'bg-amber-50/80 border-amber-200 text-amber-950'">
        <div class="flex justify-between items-center mb-1.5">
          <span class="text-slate-600">Tồn kho trên kệ (On-Hand):</span>
          <span class="font-mono font-bold text-slate-800">{{ selectedStock.quantity_on_hand }}</span>
        </div>
        <div class="flex justify-between items-center mb-1.5">
          <span class="text-slate-600">Đã khóa giữ chỗ (Reserved):</span>
          <span class="font-mono font-bold" :class="selectedStock.quantity_reserved > 0 ? 'text-sky-700' : 'text-rose-600'">
            {{ selectedStock.quantity_reserved }}
          </span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-slate-600">Khả dụng bán thêm (Available):</span>
          <span class="font-mono font-bold text-emerald-700">{{ selectedStock.available }}</span>
        </div>
        <div v-if="selectedStock.quantity_reserved === 0" class="mt-2.5 text-[11px] leading-relaxed text-amber-800 border-t border-amber-200/80 pt-2">
          <span><strong>Quy tắc SCM:</strong> Nghiệp vụ Xuất kho (Delivery) chỉ xuất các đơn đã <strong>Khóa giữ chỗ (SO Reserve)</strong>. Sản phẩm này đang có Reserved = 0 nên bạn cần bấm nút <em>"Giữ Chỗ (SO)"</em> trước hoặc chọn sản phẩm đã có lượng giữ chỗ.</span>
        </div>
      </div>

      <div class="flex flex-col gap-1.5">
        <div class="flex justify-between items-center mb-1">
          <label class="block text-xs font-semibold text-slate-700">Số Lượng Xuất Thực Tế *</label>
          <span v-if="selectedStock && selectedStock.quantity_reserved > 0" class="text-[11px] text-sky-700 font-medium">
            (Tối đa xuất: {{ selectedStock.quantity_reserved }})
          </span>
        </div>
        <InputNumber
          v-model="form.quantity"
          :min="1"
          :max="selectedStock?.quantity_reserved || 10000"
          class="w-full font-mono !rounded-[6px]"
          placeholder="Nhập số lượng xuất..."
        />
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="block text-xs font-semibold text-slate-700">Ghi Chú Vận Chuyển</label>
        <InputText
          v-model="form.notes"
          placeholder="Ghi chú đối tác vận chuyển, tài xế..."
          class="!text-xs w-full !rounded-[6px]"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 w-full pt-2">
        <Button label="Hủy Bỏ" text size="small" class="!rounded-[6px]" @click="$emit('update:visible', false)" />
        <Button
          label="Xác Nhận Xuất Kho"
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
