<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="`Chi Tiết Đối Soát Xuất Hóa Đơn - Chứng Từ: ${document?.invoice_number || ''}`"
    :style="{ width: '1100px' }"
    class="rounded-[8px]"
  >
    <div v-if="document" class="space-y-4 text-xs pt-1">
      <!-- 1. Thông tin chung & Khách hàng -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3">
        <div>
          <div class="text-slate-500 text-[11px]">Số chứng từ bán / xuất kho:</div>
          <div class="font-mono font-bold text-sm text-indigo-700 mt-0.5">{{ document.invoice_number }}</div>
          <div class="text-slate-500 text-[11px] mt-2">Ngày xuất kho / ghi sổ:</div>
          <div class="font-mono text-slate-800 font-semibold mt-0.5">{{ formatDate(document.warehouse_posted_at) }}</div>
        </div>

        <div>
          <div class="text-slate-500 text-[11px]">Đơn vị mua hàng (Khách hàng):</div>
          <div class="font-bold text-slate-800 mt-0.5 truncate" :title="document.customer_name">{{ document.customer_name }}</div>
          <div class="text-slate-500 text-[11px] mt-2">Mã số thuế (MST):</div>
          <div class="font-mono font-bold text-slate-800 mt-0.5">{{ document.customer_tax_code || '---' }}</div>
        </div>

        <div>
          <div class="text-slate-500 text-[11px]">Trạng thái xuất hóa đơn:</div>
          <div class="mt-1">
            <Badge :severity="document.status_severity" :value="document.status_text" class="text-xs px-2.5 py-1" />
          </div>
          <div v-if="document.linked_vat_invoices && document.linked_vat_invoices.length > 0" class="mt-2 text-[11px]">
            <span class="text-slate-500">Các HĐ GTGT đã lập: </span>
            <span class="font-mono font-bold text-emerald-700">
              {{ document.linked_vat_invoices.join(', ') }}
            </span>
          </div>
        </div>
      </div>

      <!-- 2. Thẻ tóm tắt giá trị -->
      <div class="grid grid-cols-3 gap-3">
        <div class="bg-indigo-50/50 border border-indigo-200 rounded-[6px] p-2.5 flex justify-between items-center font-mono">
          <div>
            <div class="text-[11px] font-sans text-indigo-700 font-semibold">Tổng Trị Giá Xuất Kho:</div>
            <div class="text-sm font-bold text-indigo-900 mt-0.5">{{ formatCurrency(document.total_issued_amount) }}</div>
          </div>
          <i class="pi pi-box text-indigo-400 text-lg"></i>
        </div>

        <div class="bg-emerald-50/50 border border-emerald-200 rounded-[6px] p-2.5 flex justify-between items-center font-mono">
          <div>
            <div class="text-[11px] font-sans text-emerald-700 font-semibold">Giá Trị Đã Xuất Hóa Đơn:</div>
            <div class="text-sm font-bold text-emerald-900 mt-0.5">{{ formatCurrency(document.total_invoiced_amount) }}</div>
          </div>
          <i class="pi pi-check-circle text-emerald-400 text-lg"></i>
        </div>

        <div class="bg-amber-50/50 border border-amber-200 rounded-[6px] p-2.5 flex justify-between items-center font-mono">
          <div>
            <div class="text-[11px] font-sans text-amber-700 font-semibold">Giá Trị Chưa Xuất Hóa Đơn:</div>
            <div class="text-sm font-bold text-amber-900 mt-0.5">{{ formatCurrency(document.total_unbilled_amount) }}</div>
          </div>
          <i class="pi pi-clock text-amber-400 text-lg"></i>
        </div>
      </div>

      <!-- 3. Bảng chi tiết đối soát từng mã hàng -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 flex items-center justify-between text-[11px]">
          <span class="font-bold text-slate-700 uppercase">
            Danh Sách Chi Tiết Mặt Hàng & Tiến Độ Xuất Hóa Đơn
          </span>
          <span class="text-slate-500 font-mono">
            Tổng cộng: <strong>{{ document.items?.length || 0 }}</strong> mã hàng
          </span>
        </div>

        <table class="w-full text-xs text-left">
          <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 text-[11px] uppercase tracking-wider">
            <tr>
              <th class="p-2 w-10 text-center">STT</th>
              <th class="p-2 w-28">Mã SKU</th>
              <th class="p-2">Tên Mặt Hàng</th>
              <th class="p-2 text-right w-24">Đơn Giá</th>
              <th class="p-2 text-right w-20">SL Xuất</th>
              <th class="p-2 text-right w-20">Đã HĐ</th>
              <th class="p-2 text-right w-20">Chưa HĐ</th>
              <th class="p-2 text-right w-28">Trị Giá Xuất</th>
              <th class="p-2 text-right w-28">Đã Xuất HĐ</th>
              <th class="p-2 text-right w-28">Chưa Xuất HĐ</th>
              <th class="p-2 text-center w-28">Trạng Thái</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-mono">
            <tr
              v-for="(it, idx) in document.items"
              :key="it.id || idx"
              class="hover:bg-slate-50/80 transition-colors"
            >
              <td class="p-2 text-center text-slate-400">{{ idx + 1 }}</td>
              <td class="p-2 font-bold text-indigo-700">{{ it.sku }}</td>
              <td class="p-2 font-sans font-medium text-slate-800">{{ it.product_name }}</td>
              <td class="p-2 text-right text-slate-700">{{ formatCurrency(it.unit_price) }}</td>

              <!-- Số lượng -->
              <td class="p-2 text-right font-semibold text-slate-800">{{ formatNumber(it.issued_quantity || it.quantity) }}</td>
              <td class="p-2 text-right font-semibold text-emerald-700">{{ formatNumber(it.invoiced_quantity || 0) }}</td>
              <td class="p-2 text-right font-semibold" :class="(it.remaining_invoice_quantity || 0) > 0 ? 'text-amber-700' : 'text-slate-400'">
                {{ formatNumber(it.remaining_invoice_quantity || 0) }}
              </td>

              <!-- Thành tiền -->
              <td class="p-2 text-right text-slate-800">{{ formatCurrency((it.issued_quantity || it.quantity) * it.unit_price) }}</td>
              <td class="p-2 text-right text-emerald-700">{{ formatCurrency((it.invoiced_quantity || 0) * it.unit_price) }}</td>
              <td class="p-2 text-right font-bold" :class="(it.remaining_invoice_quantity || 0) > 0 ? 'text-amber-700' : 'text-slate-400'">
                {{ formatCurrency((it.remaining_invoice_quantity || 0) * it.unit_price) }}
              </td>

              <!-- Trạng thái từng mã -->
              <td class="p-2 text-center">
                <Badge
                  v-if="(it.remaining_invoice_quantity || 0) === 0"
                  severity="success"
                  value="Đã xuất đủ"
                  class="text-[10px]"
                />
                <Badge
                  v-else-if="(it.invoiced_quantity || 0) === 0"
                  severity="danger"
                  value="Chưa xuất"
                  class="text-[10px]"
                />
                <Badge
                  v-else
                  severity="warn"
                  :value="`Đã xuất ${it.invoiced_quantity}/${it.issued_quantity || it.quantity}`"
                  class="text-[10px]"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <div class="text-xs text-slate-500 font-sans">
          <span v-if="document && document.total_remaining_qty > 0" class="text-amber-700 font-semibold">
            Chứng từ này còn {{ document.total_remaining_qty }} sản phẩm chưa xuất HĐ GTGT.
          </span>
          <span v-else class="text-emerald-700 font-semibold">
            Chứng từ này đã hoàn tất xuất đủ 100% hóa đơn GTGT.
          </span>
        </div>

        <div class="flex gap-2">
          <Button
            label="Đóng"
            icon="pi pi-times"
            size="small"
            outlined
            severity="secondary"
            class="rounded-[6px]"
            @click="$emit('update:visible', false)"
          />
          <Button
            v-if="document && document.total_remaining_qty > 0"
            label="Lập Hóa Đơn VAT"
            icon="pi pi-receipt"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white rounded-[6px] border-none"
            @click="$emit('create-invoice', document)"
          />
        </div>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import { formatCurrency, formatNumber, formatDate } from '../../utils/format';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  document: {
    type: Object,
    default: null
  }
});

defineEmits(['update:visible', 'create-invoice']);
</script>
