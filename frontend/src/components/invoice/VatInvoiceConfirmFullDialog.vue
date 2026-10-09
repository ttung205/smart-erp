<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    header="Xác Nhận Xuất Toàn Bộ Hóa Đơn VAT"
    :style="{ width: '480px' }"
    class="rounded-[8px]"
  >
    <div v-if="invoice" class="space-y-3 pt-1 text-xs text-slate-700">
      <div class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-[6px] p-3 text-amber-900">
        <i class="pi pi-exclamation-triangle text-amber-600 text-xl mt-0.5 shrink-0"></i>
        <div>
          <span class="font-bold text-amber-900 block mb-1">Xuất toàn bộ mặt hàng sang HĐ GTGT</span>
          <p class="m-0 leading-relaxed text-[11px] text-amber-800">
            Bạn có chắc chắn muốn lập Hóa Đơn GTGT cho <strong>toàn bộ {{ invoice.items?.length || 0 }} mặt hàng còn lại</strong> của chứng từ <strong>{{ invoice.invoice_number }}</strong>?
          </p>
        </div>
      </div>

      <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 space-y-1.5 font-mono">
        <div class="flex justify-between">
          <span class="text-slate-500 font-sans">Số HĐ GTGT tự động cấp:</span>
          <strong class="text-indigo-700 text-sm">{{ nextInvoiceInfo?.next_number || '00000001' }}</strong>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500 font-sans">Ký hiệu (Series):</span>
          <strong class="text-slate-800">{{ nextInvoiceInfo?.series || '1C26TBB' }}</strong>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500 font-sans">Khách hàng:</span>
          <strong class="text-slate-800 font-sans truncate max-w-[220px]">{{ invoice.customer_name }}</strong>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500 font-sans">Tổng tiền thanh toán:</span>
          <strong class="text-emerald-700 text-sm">{{ formatCurrency(invoice.total_amount) }}</strong>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Xác Nhận Xuất Hóa Đơn"
          icon="pi pi-check"
          size="small"
          :loading="submitting"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white rounded-[6px] !font-semibold border-none"
          @click="$emit('submit')"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import { formatCurrency } from '../../utils/format';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  invoice: {
    type: Object,
    default: null
  },
  nextInvoiceInfo: {
    type: Object,
    default: () => ({ next_number: '00000001', series: '1C26TBB' })
  },
  submitting: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'submit']);
</script>
