<template>
  <div class="invoice-item-table-container">
    <ItemDataTable
      :model-value="modelValue"
      :columns="invoiceColumns"
      :show-add-row="false"
      :show-catalog-search="false"
      :show-actions-column="false"
      v-bind="$attrs"
      @update:model-value="$emit('update:modelValue', $event)"
      @summary-change="$emit('summary-change', $event)"
    >
      <!-- 1. SKU (Internal Code) - Cố định (Frozen) -->
      <template #col-sku="{ data }">
        <span class="text-xs font-bold text-sky-800 truncate block px-1" :title="data.sku">
          {{ data.sku || '-' }}
        </span>
      </template>

      <!-- 2. Quy cách (Designation) -->
      <template #col-designation="{ data }">
        <span class="text-xs text-rose-700 font-semibold truncate block px-1" :title="data.designation">
          {{ data.designation || '-' }}
        </span>
      </template>

      <!-- 3. Hãng (Brand) -->
      <template #col-brand="{ data }">
        <span class="text-xs font-semibold text-slate-800 truncate block px-1" :title="data.brand">
          {{ data.brand || '-' }}
        </span>
      </template>

      <!-- 4. Mô tả (Description) -->
      <template #col-description="{ data, notifyChange }">
        <InputText
          v-if="!readonly"
          v-model="data.description"
          class="w-full text-xs text-slate-800 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          placeholder="Mô tả sản phẩm..."
          :title="data.description"
          @change="notifyChange && notifyChange()"
        />
        <span v-else class="text-xs text-slate-800 truncate block px-1" :title="data.description">
          {{ data.description || '-' }}
        </span>
      </template>

      <!-- 5. Xuất xứ (COM) -->
      <template #col-com="{ data }">
        <span class="text-xs text-slate-700 truncate block px-1 text-center" :title="data.com">
          {{ data.com || 'Việt Nam' }}
        </span>
      </template>

      <!-- 6. Tình trạng Tồn Kho Thực Tế & Khả Dụng tại Kho Xuất -->
      <template #col-stock="{ data }">
        <div v-if="stockMap && stockMap[data.sku]" class="flex flex-col items-center gap-0.5 text-[11px] py-0.5">
          <div class="flex items-center gap-1 text-[10px]">
            <span class="text-slate-500">Tồn: <strong>{{ stockMap[data.sku].quantity_on_hand }}</strong></span>
            <span class="text-slate-300">•</span>
            <span class="text-amber-600 font-semibold">Giữ: {{ stockMap[data.sku].quantity_reserved }}</span>
          </div>
          <span
            class="inline-block text-[11px] font-bold px-1.5 py-0.2 rounded-[4px]"
            :class="stockMap[data.sku].available >= (data.quantity || 0) ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-red-700 bg-red-50 border border-red-200'"
            :title="`Tồn On-Hand: ${stockMap[data.sku].quantity_on_hand}, Giữ chỗ: ${stockMap[data.sku].quantity_reserved}, Khả dụng: ${stockMap[data.sku].available}`"
          >
            Khả dụng: {{ stockMap[data.sku].available }}
          </span>
        </div>
        <div v-else class="text-center">
          <span class="text-slate-400 text-xs">—</span>
        </div>
      </template>

      <!-- 7. Đơn vị tính (Unit) -->
      <template #col-unit="{ data }">
        <span class="text-xs text-slate-700 text-center block">{{ data.unit || 'Cái' }}</span>
      </template>

      <!-- 8. SL Trên Đơn Hàng Gốc (Order Quantity) -->
      <template #col-order_quantity="{ data }">
        <span class="text-xs font-semibold text-slate-700 bg-slate-50 border border-slate-200 px-2 py-0.5 rounded-[4px] block text-right" :title="`Số lượng đã đặt trên đơn bán hàng: ${data.order_quantity || data.quantity}`">
          {{ data.order_quantity != null ? data.order_quantity : (data.requested_quantity || data.quantity) }}
        </span>
      </template>

      <!-- 9. SL Yêu Cầu Giao (Requested Quantity) -->
      <template #col-requested_quantity="{ data }">
        <span class="text-xs font-bold text-sky-800 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-[4px] block text-right" :title="`Số lượng yêu cầu giao tại phiếu yêu cầu giao: ${data.requested_quantity || data.quantity}`">
          {{ data.requested_quantity != null ? data.requested_quantity : data.quantity }}
        </span>
      </template>

      <!-- 10. SL Xuất Trên Chứng Từ / Giữ Chỗ (Invoice Quantity - Editable) -->
      <template #col-quantity="{ data, notifyChange }">
        <InputNumber
          v-if="!readonly"
          v-model="data.quantity"
          :min="0"
          :max="data.requested_quantity ? data.requested_quantity * 2 : 999999"
          :maxFractionDigits="2"
          class="w-full text-xs font-bold"
          inputClass="!py-1.5 !px-2 text-right text-xs font-bold rounded-[4px] !border-indigo-300 hover:!border-indigo-400 focus:!border-indigo-600 !text-indigo-700 bg-indigo-50/40"
          @update:modelValue="onQuantityChange(data, notifyChange)"
        />
        <span v-else class="text-xs font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded-[4px] block text-right">
          {{ data.quantity }}
        </span>
      </template>

      <!-- 11. Chiết khấu % (Discount) -->
      <template #col-discount_percent="{ data, notifyChange }">
        <InputNumber
          v-if="!readonly"
          v-model="data.discount_percent"
          :min="0"
          :max="100"
          :minFractionDigits="0"
          :maxFractionDigits="1"
          suffix="%"
          class="w-full text-xs font-bold"
          inputClass="!py-1.5 !px-2 text-right text-xs font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-emerald-500 !text-emerald-700 bg-white"
          @update:modelValue="onDiscountChange(data, notifyChange)"
        />
        <span v-else class="text-xs font-semibold text-emerald-700 block text-right">
          {{ data.discount_percent || 0 }}%
        </span>
      </template>

      <!-- 12. Giá bán thực tế (Selling Price / Unit Price) -->
      <template #col-selling_price="{ data, notifyChange }">
        <InputNumber
          v-if="!readonly"
          v-model="data.unit_price"
          :min="0"
          currency="VND"
          locale="vi-VN"
          class="w-full text-xs font-bold"
          inputClass="!py-1.5 !px-2 text-right text-xs font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 !text-sky-700 bg-white"
          @update:modelValue="onSellingPriceChange(data, notifyChange)"
        />
        <span v-else class="text-xs font-bold text-slate-800 block text-right">
          {{ formatCurrency(data.unit_price || data.selling_price) }}
        </span>
      </template>

      <!-- 13. Giá niêm yết (Gross Price) -->
      <template #col-gross_price="{ data }">
        <span
          class="text-xs font-semibold text-slate-600 px-2 py-1 bg-slate-50 border border-slate-100 rounded-[4px] block text-right"
          :title="`Giá niêm yết gốc tại thời điểm tạo đơn: ${formatCurrency(data.gross_price)}`"
        >
          {{ formatCurrency(data.gross_price || data.unit_price) }}
        </span>
      </template>

      <!-- 14. Thuế VAT % -->
      <template #col-vat_percent="{ data }">
        <span class="text-xs text-slate-600 text-center block">
          {{ data.vat_percent || 0 }}%
        </span>
      </template>

      <!-- 15. Lead Time (Giao hàng) -->
      <template #col-lead_time="{ data }">
        <span class="text-xs text-slate-700 truncate block px-1" :title="data.lead_time">
          {{ data.lead_time || '-' }}
        </span>
      </template>

      <!-- 16. NGÀY HẸN GIAO (Delivery Date) -->
      <template #col-delivery_date="{ data }">
        <span class="text-xs text-slate-700 block text-center" :title="data.delivery_date">
          {{ data.delivery_date || '-' }}
        </span>
      </template>

      <!-- 17. Thành tiền (Subtotal) -->
      <template #col-subtotal="{ data }">
        <span class="text-xs font-bold text-indigo-700 num-tabular px-2 py-1 bg-indigo-50/50 border border-indigo-100 rounded-[4px] block text-right">
          {{ formatCurrency(data.subtotal || ((data.quantity || 0) * (data.unit_price || data.selling_price || 0))) }}
        </span>
      </template>
    </ItemDataTable>
  </div>
</template>

<script setup>
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import ItemDataTable from '../common/ItemDataTable.vue';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  stockMap: {
    type: Object,
    default: () => ({})
  },
  readonly: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue', 'summary-change']);

// Cột mặt hàng chứng từ bán hàng: Đầy đủ các cột của Đơn bán hàng + Các cột đặc thù chứng từ bán
const invoiceColumns = [
  { field: 'sku', header: 'INTERNAL CODE', width: '150px', frozen: true, class: '!border-r-2 !border-r-slate-300 shadow-[4px_0_8px_-3px_rgba(0,0,0,0.12)]', headerClass: '!border-r-2 !border-r-slate-300 shadow-[4px_0_8px_-3px_rgba(0,0,0,0.12)]' },
  { field: 'designation', header: 'DESIGNATION', width: '150px' },
  { field: 'brand', header: 'BRAND', width: '100px' },
  { field: 'description', header: 'ITEM DESCRIPTION', width: '260px' },
  { field: 'com', header: 'COM', width: '110px', align: 'center' },
  { field: 'stock', header: 'TỒN KHO THỰC TẾ', width: '160px', align: 'center' },
  { field: 'unit', header: 'UNIT', width: '75px', align: 'center' },
  { field: 'order_quantity', header: 'SL ĐƠN', width: '90px', align: 'right' },
  { field: 'requested_quantity', header: 'SL YÊU CẦU', width: '100px', align: 'right' },
  { field: 'quantity', header: 'SL XUẤT (INV)', width: '120px', align: 'right' },
  { field: 'discount_percent', header: 'CK %', width: '85px', align: 'right' },
  { field: 'selling_price', header: 'SELLING PRICE', width: '145px', align: 'right' },
  { field: 'gross_price', header: 'GROSS PRICE', width: '135px', align: 'right' },
  { field: 'vat_percent', header: 'VAT %', width: '80px', align: 'center' },
  { field: 'lead_time', header: 'LEAD TIME', width: '110px' },
  { field: 'delivery_date', header: 'HẸN GIAO', width: '120px', align: 'center' },
  { field: 'subtotal', header: 'THÀNH TIỀN', width: '155px', align: 'right' }
];

// Tính toán 2 chiều giá bán & chiết khấu %
const onDiscountChange = (row, notifyChange) => {
  const gross = Number(row.gross_price) || Number(row.unit_price) || 0;
  const disc = Number(row.discount_percent) || 0;
  const price = Math.round(gross * (1 - disc / 100));
  row.unit_price = price;
  row.selling_price = price;
  row.subtotal = (Number(row.quantity) || 0) * price;
  notifyChange && notifyChange();
};

const onSellingPriceChange = (row, notifyChange) => {
  const gross = Number(row.gross_price) || 0;
  const selling = Number(row.unit_price) || 0;
  row.selling_price = selling;
  if (gross > 0) {
    const calculatedDisc = ((gross - selling) / gross) * 100;
    row.discount_percent = Math.max(0, Math.round(calculatedDisc * 10) / 10);
  } else {
    row.discount_percent = 0;
  }
  row.subtotal = (Number(row.quantity) || 0) * selling;
  notifyChange && notifyChange();
};

const onQuantityChange = (row, notifyChange) => {
  const qty = Number(row.quantity) || 0;
  const price = Number(row.unit_price || row.selling_price) || 0;
  row.subtotal = qty * price;
  notifyChange && notifyChange();
};
</script>

<style scoped>
.invoice-item-table-container :deep(.p-datatable) {
  font-size: 13px;
}
</style>
