<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="`Chi Tiết Hóa Đơn GTGT: Số ${invoice?.invoice_number || ''} - Ký Hiệu: ${invoice?.invoice_series || ''}`"
    :style="{ width: '850px' }"
    class="rounded-[8px]"
  >
    <div v-if="invoice" class="space-y-4 text-xs pt-1">
      <!-- Banner Hóa Đơn Điện Tử -->
      <div class="bg-gradient-to-r from-indigo-900 via-blue-900 to-indigo-800 text-white p-4 rounded-[6px] flex items-center justify-between">
        <div>
          <div class="text-[10px] uppercase tracking-wider text-indigo-200 font-semibold">HÓA ĐƠN GIÁ TRỊ GIA TĂNG (VAT INVOICE)</div>
          <div class="text-lg font-bold font-mono mt-0.5">SỐ: {{ invoice.invoice_number }}</div>
          <div class="text-xs text-indigo-200 mt-0.5">Ký hiệu: {{ invoice.invoice_series }} | Ngày lập: {{ formatDate(invoice.invoice_date) }}</div>
        </div>
        <div class="text-right">
          <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 text-[11px] font-semibold px-2.5 py-1 rounded-[4px]">
            ✓ Đã Ký Điện Tử & Hạch Toán TK 1311
          </span>
        </div>
      </div>

      <!-- Khách hàng info (Read-only) -->
      <div class="grid grid-cols-2 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3">
        <div>
          <div class="text-slate-500 text-[11px]">Đơn vị mua hàng (Khách hàng):</div>
          <div class="font-bold text-slate-800 mt-0.5">{{ invoice.customer_name }}</div>
          <div class="text-slate-500 text-[11px] mt-2">Mã số thuế (MST):</div>
          <div class="font-mono font-bold text-slate-800 mt-0.5">{{ invoice.customer_tax_code || 'Chưa cập nhật' }}</div>
        </div>
        <div>
          <div class="text-slate-500 text-[11px]">Địa chỉ:</div>
          <div class="text-slate-800 mt-0.5">{{ invoice.customer_address || 'Chưa cập nhật' }}</div>
          <div class="text-slate-500 text-[11px] mt-2">Chứng từ bán hàng gốc:</div>
          <div class="font-mono font-semibold text-indigo-700 mt-0.5">
            {{ invoice.sales_invoice_number || '---' }} (ĐH: {{ invoice.order_number || '---' }})
          </div>
        </div>
      </div>

      <!-- Danh sách mặt hàng -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <table class="w-full text-xs text-left">
          <thead class="bg-slate-100 text-slate-700 border-b border-slate-200 text-[11px] uppercase tracking-wider">
            <tr>
              <th class="p-2 w-10 text-center">STT</th>
              <th class="p-2 w-28">Mã hàng</th>
              <th class="p-2">Tên hàng hóa, dịch vụ</th>
              <th class="p-2 text-right w-20">Số lượng</th>
              <th class="p-2 text-right w-28">Đơn giá</th>
              <th class="p-2 text-right w-28">Thành tiền</th>
              <th class="p-2 text-center w-16">Thuế GTGT</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-mono">
            <tr v-for="(it, idx) in invoice.items" :key="it.id" class="hover:bg-slate-50">
              <td class="p-2 text-center text-slate-400">{{ idx + 1 }}</td>
              <td class="p-2 font-bold text-indigo-700">{{ it.sku }}</td>
              <td class="p-2 font-sans font-medium text-slate-800">{{ it.product_name }}</td>
              <td class="p-2 text-right font-semibold">{{ formatNumber(it.quantity) }}</td>
              <td class="p-2 text-right">{{ formatCurrency(it.unit_price) }}</td>
              <td class="p-2 text-right font-bold">{{ formatCurrency(it.subtotal) }}</td>
              <td class="p-2 text-center text-indigo-700 font-semibold">{{ it.vat_percent }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Bảng tổng tiền hóa đơn -->
      <div class="flex justify-end">
        <div class="w-72 bg-slate-50 border border-slate-200 rounded-[6px] p-3 space-y-1.5 text-xs font-mono">
          <div class="flex justify-between text-slate-600">
            <span>Cộng tiền hàng:</span>
            <span class="font-bold text-slate-800">{{ formatCurrency(invoice.subtotal) }} đ</span>
          </div>
          <div class="flex justify-between text-indigo-700">
            <span>Tiền thuế GTGT (VAT):</span>
            <span class="font-bold">{{ formatCurrency(invoice.vat_total) }} đ</span>
          </div>
          <div class="border-t border-slate-200 pt-1.5 flex justify-between font-bold text-emerald-800 text-sm">
            <span>Tổng thanh toán:</span>
            <span>{{ formatCurrency(invoice.total_amount) }} đ</span>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Đóng"
          icon="pi pi-times"
          size="small"
          outlined
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import { formatCurrency, formatNumber, formatDate } from '../../utils/format';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  invoice: {
    type: Object,
    default: null
  }
});

defineEmits(['update:visible']);
</script>
