<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="`Chi Tiết Mặt Hàng & Lập Hóa Đơn VAT - Chứng Từ: ${invoice?.invoice_number || ''}`"
    :style="{ width: '1050px' }"
    class="rounded-[8px]"
  >
    <div v-if="invoice" class="space-y-3.5 text-xs pt-1">
      <!-- 1. Thông tin hóa đơn & Bên mua hàng (Khách hàng không cho sửa) -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3">
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Số hóa đơn GTGT <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.invoice_number"
            class="w-full text-xs font-mono font-bold text-indigo-700 rounded-[6px] h-8 px-2.5 border-slate-300"
            placeholder="VD: 00000001"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Ký hiệu hóa đơn (Series) <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.invoice_series"
            class="w-full text-xs font-mono font-bold rounded-[6px] h-8 px-2.5 border-slate-300"
            placeholder="VD: 1C26TBB"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px]">
            Ngày hóa đơn <span class="text-red-500">*</span>
          </label>
          <InputText
            v-model="form.invoice_date"
            type="date"
            class="w-full text-xs font-mono rounded-[6px] h-8 px-2.5 border-slate-300"
          />
        </div>

        <!-- Thông tin khách hàng (Readonly, disabled, tự động) -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px] flex justify-between">
            <span>Đơn vị mua hàng</span>
            <span class="text-[10px] text-slate-400 font-normal lowercase">(tự động)</span>
          </label>
          <InputText
            :value="invoice.customer_name"
            class="w-full text-xs rounded-[6px] h-8 px-2.5 border-slate-200 !bg-slate-100 font-semibold text-slate-700"
            readonly
            disabled
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px] flex justify-between">
            <span>Mã số thuế (MST)</span>
            <span class="text-[10px] text-slate-400 font-normal lowercase">(tự động)</span>
          </label>
          <InputText
            :value="invoice.customer_tax_code || '---'"
            class="w-full text-xs font-mono font-bold rounded-[6px] h-8 px-2.5 border-slate-200 !bg-slate-100 text-slate-700"
            readonly
            disabled
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-700 uppercase tracking-wide text-[11px] flex justify-between">
            <span>Địa chỉ</span>
            <span class="text-[10px] text-slate-400 font-normal lowercase">(tự động)</span>
          </label>
          <InputText
            :value="invoice.customer_address || '---'"
            class="w-full text-xs rounded-[6px] h-8 px-2.5 border-slate-200 !bg-slate-100 text-slate-700"
            readonly
            disabled
          />
        </div>
      </div>

      <!-- 2. Bảng danh sách mã hàng có tick chọn & chỉnh số lượng xuất ít một -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 flex items-center justify-between text-[11px]">
          <span class="font-bold text-slate-700 uppercase">
            Danh Sách Mã Hàng (Tick Chọn Mặt Hàng & Chỉnh Số Lượng Xuất HĐ)
          </span>
          <span class="text-emerald-700 font-semibold">
            Chuyển TK 1312 ➔ TK 1311
          </span>
        </div>

        <table class="w-full text-xs text-left">
          <thead class="bg-slate-50 text-slate-700 border-b border-slate-200 text-[11px] uppercase tracking-wider">
            <tr>
              <th class="p-2 w-10 text-center">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  class="rounded cursor-pointer accent-indigo-600"
                  @change="toggleSelectAll($event.target.checked)"
                />
              </th>
              <th class="p-2 w-10 text-center">STT</th>
              <th class="p-2 w-28">Mã SKU</th>
              <th class="p-2">Tên Mặt Hàng</th>
              <th class="p-2 text-right w-20">SL Đã Xuất</th>
              <th class="p-2 text-right w-20">Đã HĐ</th>
              <th class="p-2 text-right w-20">Còn Lại</th>
              <th class="p-2 text-center w-28">SL Xuất Lần Này</th>
              <th class="p-2 text-right w-24">Đơn Giá</th>
              <th class="p-2 text-right w-28">Thành Tiền</th>
              <th class="p-2 text-center w-16">Thuế VAT</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 font-mono">
            <tr
              v-for="(it, idx) in invoice.items"
              :key="it.id || idx"
              class="hover:bg-slate-50/80 transition-colors"
              :class="it._selected ? 'bg-indigo-50/30' : ''"
            >
              <!-- Checkbox chọn item -->
              <td class="p-2 text-center">
                <input
                  type="checkbox"
                  v-model="it._selected"
                  class="rounded cursor-pointer accent-indigo-600"
                  @change="onItemSelectionChange(it)"
                />
              </td>
              <td class="p-2 text-center text-slate-400">{{ idx + 1 }}</td>
              <td class="p-2 font-bold text-indigo-700">{{ it.sku }}</td>
              <td class="p-2 font-sans font-medium text-slate-800">{{ it.product_name }}</td>
              <td class="p-2 text-right text-slate-600">{{ formatNumber(it.issued_quantity || it.quantity) }}</td>
              <td class="p-2 text-right text-emerald-700">{{ formatNumber(it.invoiced_quantity || 0) }}</td>
              <td class="p-2 text-right">
                <span class="font-bold text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 inline-block">
                  {{ formatNumber(it.remaining_invoice_quantity || it.quantity) }}
                </span>
              </td>
              
              <!-- Input số lượng xuất lần này (cho phép xuất ít một) -->
              <td class="p-2 text-center font-sans">
                <input
                  type="number"
                  v-model.number="it.invoice_now_quantity"
                  :min="0"
                  :max="it.remaining_invoice_quantity || it.quantity"
                  class="w-20 text-center font-mono font-bold text-xs h-7 rounded border px-1 transition-all"
                  :class="it._selected ? 'border-indigo-400 bg-white text-indigo-900 ring-1 ring-indigo-200' : 'border-slate-300 bg-slate-50 text-slate-500'"
                  @input="onQtyChange(it)"
                />
              </td>

              <td class="p-2 text-right text-slate-700">{{ formatCurrency(it.unit_price) }} đ</td>
              <td class="p-2 text-right font-bold text-slate-900">
                {{ formatCurrency((it._selected ? Number(it.invoice_now_quantity || 0) : 0) * it.unit_price) }} đ
              </td>
              <td class="p-2 text-center text-indigo-700 font-semibold">{{ it.vat_rate || it.vat_percent || 10 }}%</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- 3. Tổng kết hóa đơn xuất lần này -->
      <div class="flex justify-between items-end">
        <div class="text-xs text-slate-500">
          Đã chọn: <strong class="text-indigo-700 font-mono">{{ selectedItemsCount }}</strong> mặt hàng để xuất hóa đơn GTGT.
        </div>

        <div class="w-80 bg-slate-50 border border-slate-200 rounded-[6px] p-3 space-y-1.5 text-xs font-mono">
          <div class="flex justify-between text-slate-600">
            <span>Cộng tiền hàng:</span>
            <span class="font-bold text-slate-800">{{ formatCurrency(partialSubtotal) }} đ</span>
          </div>
          <div class="flex justify-between text-indigo-700">
            <span>Tiền thuế GTGT (10%):</span>
            <span class="font-bold">{{ formatCurrency(partialVatTotal) }} đ</span>
          </div>
          <div class="border-t border-slate-200 pt-1.5 flex justify-between font-bold text-emerald-800 text-sm">
            <span>Tổng thanh toán HĐ:</span>
            <span>{{ formatCurrency(partialTotalAmount) }} đ</span>
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
        <Button
          label="Xác Nhận Xuất Hóa Đơn GTGT"
          icon="pi pi-check"
          size="small"
          :loading="submitting"
          :disabled="selectedItemsCount === 0 || partialTotalAmount <= 0"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white rounded-[6px] !font-semibold border-none"
          @click="$emit('submit', { form, invoice, partialSubtotal, partialVatTotal, partialTotalAmount })"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { computed } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import { formatCurrency, formatNumber } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  invoice: {
    type: Object,
    default: null
  },
  form: {
    type: Object,
    default: () => ({
      invoice_number: '',
      invoice_series: '1C26TBB',
      invoice_date: new Date().toISOString().substring(0, 10)
    })
  },
  submitting: {
    type: Boolean,
    default: false
  }
});

defineEmits(['update:visible', 'submit']);

const isAllSelected = computed(() => {
  if (!props.invoice?.items || props.invoice.items.length === 0) return false;
  return props.invoice.items.every((it) => it._selected);
});

const selectedItemsCount = computed(() => {
  if (!props.invoice?.items) return 0;
  return props.invoice.items.filter((it) => it._selected && Number(it.invoice_now_quantity) > 0).length;
});

const partialSubtotal = computed(() => {
  if (!props.invoice?.items) return 0;
  return props.invoice.items.reduce((sum, it) => {
    if (it._selected) {
      const qty = Number(it.invoice_now_quantity || 0);
      return sum + qty * Number(it.unit_price || 0);
    }
    return sum;
  }, 0);
});

const partialVatTotal = computed(() => {
  return Math.round(partialSubtotal.value * 0.1);
});

const partialTotalAmount = computed(() => {
  return partialSubtotal.value + partialVatTotal.value;
});

const toggleSelectAll = (checked) => {
  if (!props.invoice?.items) return;
  props.invoice.items.forEach((it) => {
    it._selected = checked;
    if (checked && (!it.invoice_now_quantity || it.invoice_now_quantity <= 0)) {
      it.invoice_now_quantity = it.remaining_invoice_quantity || it.quantity;
    } else if (!checked) {
      it.invoice_now_quantity = 0;
    }
  });
};

const onItemSelectionChange = (item) => {
  if (item._selected && (!item.invoice_now_quantity || item.invoice_now_quantity <= 0)) {
    item.invoice_now_quantity = item.remaining_invoice_quantity || item.quantity;
  } else if (!item._selected) {
    item.invoice_now_quantity = 0;
  }
};

const onQtyChange = (item) => {
  if (Number(item.invoice_now_quantity) > 0) {
    item._selected = true;
  } else {
    item._selected = false;
  }
};
</script>
