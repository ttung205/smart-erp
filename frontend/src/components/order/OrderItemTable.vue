<template>
  <div class="order-item-table-container">
    <ItemDataTable
      :model-value="modelValue"
      :columns="orderColumns"
      :readonly="readonly"
      v-bind="$attrs"
      @update:model-value="$emit('update:modelValue', $event)"
      @summary-change="$emit('summary-change', $event)"
    >
      <!-- 1. SKU (Internal Code) - Cố định (Frozen) -->
      <template #col-sku="{ data }">
        <span class="font-mono text-xs font-bold text-sky-800 truncate block px-1" :title="data.sku">
          {{ data.sku || '-' }}
        </span>
      </template>

      <!-- 2. Quy cách (Designation) -->
      <template #col-designation="{ data }">
        <span class="font-mono text-xs text-rose-700 font-semibold truncate block px-1" :title="data.designation">
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
        <span v-if="readonly" class="text-xs text-slate-800 block px-1 truncate" :title="data.description">
          {{ data.description || '-' }}
        </span>
        <InputText
          v-else
          v-model="data.description"
          class="w-full text-xs text-slate-800 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          placeholder="Mô tả sản phẩm..."
          :title="data.description"
          @change="notifyChange && notifyChange()"
        />
      </template>

      <!-- 5. Xuất xứ (COM) -->
      <template #col-com="{ data, notifyChange }">
        <span v-if="readonly" class="text-xs text-slate-700 block px-1 text-center" :title="data.com">
          {{ data.com || '-' }}
        </span>
        <InputText
          v-else
          v-model="data.com"
          class="w-full text-xs text-slate-700 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          placeholder="Xuất xứ..."
          :title="data.com"
          @change="notifyChange && notifyChange()"
        />
      </template>

      <!-- 6. Tồn kho khả dụng (Stock) -->
      <template #col-stock="{ data }">
        <span
          class="inline-block font-mono text-xs font-bold px-2 py-0.5 rounded-[4px]"
          :class="(data.stock || 0) > 0 ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-slate-400 bg-slate-50 border border-slate-200'"
          :title="`Tổng tồn khả dụng: ${data.stock || 0}`"
        >
          {{ data.stock || 0 }}
        </span>
      </template>

      <!-- 7. Đơn vị tính (Unit) -->
      <template #col-unit="{ data, notifyChange }">
        <span v-if="readonly" class="text-xs text-slate-700 block text-center">
          {{ data.unit || 'Cái' }}
        </span>
        <InputText
          v-else
          v-model="data.unit"
          class="w-full text-xs text-center !py-1.5 !px-1 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          placeholder="Cái"
          @change="notifyChange && notifyChange()"
        />
      </template>

      <!-- 8. Số lượng (Quantity) -->
      <template #col-quantity="{ data, notifyChange }">
        <span v-if="readonly" class="font-mono text-xs font-bold text-slate-800 block text-right px-1">
          {{ data.quantity }}
        </span>
        <InputNumber
          v-else
          v-model="data.quantity"
          :min="1"
          class="w-full text-xs font-mono font-bold"
          inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          @update:modelValue="onQuantityChange(data, notifyChange)"
        />
      </template>

      <!-- 9. Chiết khấu % (Discount) -->
      <template #col-discount_percent="{ data, notifyChange }">
        <span v-if="readonly" class="font-mono text-xs font-bold text-emerald-700 block text-right px-1">
          {{ data.discount_percent || 0 }}%
        </span>
        <InputNumber
          v-else
          v-model="data.discount_percent"
          :min="0"
          :max="100"
          :minFractionDigits="0"
          :maxFractionDigits="1"
          suffix="%"
          class="w-full text-xs font-mono font-bold"
          inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-emerald-500 !text-emerald-700 bg-white"
          @update:modelValue="onDiscountChange(data, notifyChange)"
        />
      </template>

      <!-- 10. Giá bán thực tế (Selling Price) -->
      <template #col-selling_price="{ data, notifyChange }">
        <span v-if="readonly" class="font-mono text-xs font-bold text-sky-700 block text-right px-1">
          {{ formatCurrency(data.selling_price) }}
        </span>
        <InputNumber
          v-else
          v-model="data.selling_price"
          :min="0"
          currency="VND"
          locale="vi-VN"
          class="w-full text-xs font-mono font-bold"
          inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 !text-sky-700 bg-white"
          @update:modelValue="onSellingPriceChange(data, notifyChange)"
        />
      </template>

      <!-- 11. Giá niêm yết (Gross Price - Snapshot) -->
      <template #col-gross_price="{ data }">
        <span
          class="font-mono text-xs font-semibold text-slate-700 px-2 py-1 bg-slate-50 border border-slate-100 rounded-[4px] block text-right"
          :title="`Giá niêm yết gốc tại thời điểm tạo đơn: ${formatCurrency(data.gross_price)}`"
        >
          {{ formatCurrency(data.gross_price) }}
        </span>
      </template>

      <!-- 12. Thuế VAT % -->
      <template #col-vat_percent="{ data, notifyChange }">
        <span v-if="readonly" class="font-mono text-xs font-semibold text-slate-700 block text-center">
          {{ data.vat_percent || 0 }}%
        </span>
        <InputNumber
          v-else
          v-model="data.vat_percent"
          :min="0"
          :max="100"
          suffix="%"
          class="w-full text-xs font-mono"
          inputClass="!py-1.5 !px-1 text-center text-xs font-mono font-semibold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          @update:modelValue="notifyChange && notifyChange()"
        />
      </template>

      <!-- 13. Lead Time (Giao hàng) -->
      <template #col-lead_time="{ data, notifyChange }">
        <span v-if="readonly" class="text-xs text-slate-700 block px-1 text-center" :title="data.lead_time">
          {{ data.lead_time || '-' }}
        </span>
        <InputText
          v-else
          v-model="data.lead_time"
          class="w-full text-xs text-slate-700 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          placeholder="VD: Có sẵn..."
          :title="data.lead_time"
          @change="notifyChange && notifyChange()"
        />
      </template>

      <!-- 14. NGÀY HẸN GIAO (Delivery Date) - Nghiệp vụ đặc thù do OrderItemTable quyết định -->
      <template #col-delivery_date="{ data, notifyChange }">
        <span v-if="readonly" class="font-mono text-xs font-semibold text-slate-800 block text-center">
          {{ data.delivery_date ? formatDate(data.delivery_date) : '-' }}
        </span>
        <DatePicker
          v-else
          :modelValue="parseDate(data.delivery_date)"
          @update:modelValue="(val) => { data.delivery_date = formatDateToISO(val); notifyChange && notifyChange(); }"
          dateFormat="dd/mm/yy"
          placeholder="dd/mm/yyyy"
          class="w-full text-xs font-mono"
          inputClass="!py-1 !px-2 text-xs font-mono text-center rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          showIcon
          fluid
        />
      </template>

      <!-- 15. Thành tiền (Subtotal) -->
      <template #col-subtotal="{ data }">
        <span class="font-mono text-xs font-bold text-slate-900 num-tabular px-2 py-1 bg-slate-50 border border-slate-100 rounded-[4px] block text-right">
          {{ formatCurrency(data.subtotal || (data.quantity * data.selling_price)) }}
        </span>
      </template>

      <!-- Tiện ích riêng cho Đơn bán hàng: Hẹn ngày giao hàng loạt -->
      <template #extra-actions="{ items }">
        <Button
          v-if="!readonly"
          label="Hẹn ngày hàng loạt"
          icon="pi pi-calendar-plus"
          size="small"
          outlined
          :class="ITEM_TABLE_ACTION_CLASS"
          title="Áp dụng một ngày hẹn giao chung cho tất cả mặt hàng trong đơn hàng này"
          @click="openBulkDateDialog(items)"
        />
      </template>
    </ItemDataTable>

    <!-- Dialog áp dụng ngày hẹn giao đồng loạt cho toàn bộ dòng trong đơn bán -->
    <Dialog
      v-model:visible="showBulkDialog"
      modal
      header="Hẹn Ngày Giao Đồng Loạt Cho Đơn Bán Hàng"
      :style="{ width: '380px' }"
    >
      <div class="space-y-3 pt-2">
        <p class="text-xs text-slate-600 m-0">
          Chọn ngày giao để áp dụng cho tất cả <strong>{{ orderItems.length }}</strong> mặt hàng trong đơn hàng:
        </p>
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-semibold text-slate-700">Ngày hẹn giao hàng (dd/mm/yyyy):</label>
          <DatePicker
            :modelValue="parseDate(bulkDate)"
            @update:modelValue="(val) => { bulkDate = formatDateToISO(val); }"
            dateFormat="dd/mm/yy"
            placeholder="dd/mm/yyyy"
            class="w-full text-xs font-mono"
            inputClass="w-full text-xs text-slate-800 p-2 rounded-[6px] border border-slate-300 focus:border-sky-500 font-mono"
            showIcon
            fluid
          />
        </div>
      </div>
      <template #footer>
        <div class="flex justify-end gap-2">
          <Button label="Hủy" text size="small" @click="showBulkDialog = false" />
          <Button
            label="Áp dụng cho tất cả"
            icon="pi pi-check"
            size="small"
            class="!bg-sky-600 !text-white"
            @click="applyBulkDate"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import DatePicker from 'primevue/datepicker';
import ItemDataTable from '../common/ItemDataTable.vue';
import { formatCurrency, formatDate } from '../../utils/format';
import { ITEM_TABLE_ACTION_CLASS } from '../../constants/itemTable.constant';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  readonly: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue', 'summary-change']);

// Cột mặt hàng đặc thù do OrderItemTable định nghĩa cho Đơn bán hàng (Sales Order)
const orderColumns = [
  { field: 'sku', header: 'INTERNAL CODE', width: '160px', frozen: true, class: '!border-r-2 !border-r-slate-300 shadow-[4px_0_8px_-3px_rgba(0,0,0,0.12)]', headerClass: '!border-r-2 !border-r-slate-300 shadow-[4px_0_8px_-3px_rgba(0,0,0,0.12)]' },
  { field: 'designation', header: 'DESIGNATION', width: '160px' },
  { field: 'brand', header: 'BRAND', width: '100px' },
  { field: 'description', header: 'ITEM DESCRIPTION', width: '300px' },
  { field: 'com', header: 'COM', width: '120px' },
  { field: 'stock', header: 'STOCK', width: '85px', align: 'center' },
  { field: 'unit', header: 'UNIT', width: '85px', align: 'center' },
  { field: 'quantity', header: 'QTY', width: '90px', align: 'right' },
  { field: 'discount_percent', header: 'CK %', width: '85px', align: 'right' },
  { field: 'selling_price', header: 'SELLING PRICE', width: '155px', align: 'right' },
  { field: 'gross_price', header: 'GROSS PRICE', width: '140px', align: 'right' },
  { field: 'vat_percent', header: 'VAT %', width: '80px', align: 'center' },
  { field: 'lead_time', header: 'LEAD TIME', width: '120px' },
  { field: 'delivery_date', header: 'NGÀY HẸN GIAO', width: '145px' },
  { field: 'subtotal', header: 'SUBTOTAL', width: '160px', align: 'right' }
];

// Helper parse string/date cho DatePicker
const parseDate = (val) => {
  if (!val) return null;
  if (val instanceof Date) return val;
  const str = String(val).trim();
  const matchIso = str.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
  if (matchIso) {
    return new Date(Number(matchIso[1]), Number(matchIso[2]) - 1, Number(matchIso[3]));
  }
  const matchVn = str.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
  if (matchVn) {
    return new Date(Number(matchVn[3]), Number(matchVn[2]) - 1, Number(matchVn[1]));
  }
  const d = new Date(val);
  return isNaN(d.getTime()) ? null : d;
};

// Helper format Date sang YYYY-MM-DD lưu trữ
const formatDateToISO = (d) => {
  if (!d) return '';
  if (typeof d === 'string') return d;
  if (d instanceof Date && !isNaN(d.getTime())) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
  }
  return String(d);
};

// Tính toán 2 chiều giá bán & chiết khấu %
const onDiscountChange = (row, notifyChange) => {
  const gross = Number(row.gross_price) || 0;
  const disc = Number(row.discount_percent) || 0;
  row.selling_price = Math.round(gross * (1 - disc / 100));
  row.subtotal = (Number(row.quantity) || 1) * row.selling_price;
  notifyChange && notifyChange();
};

const onSellingPriceChange = (row, notifyChange) => {
  const gross = Number(row.gross_price) || 0;
  const selling = Number(row.selling_price) || 0;
  if (gross > 0) {
    const calculatedDisc = ((gross - selling) / gross) * 100;
    row.discount_percent = Math.max(0, Math.round(calculatedDisc * 10) / 10);
  } else {
    row.discount_percent = 0;
  }
  row.subtotal = (Number(row.quantity) || 1) * selling;
  notifyChange && notifyChange();
};

const onQuantityChange = (row, notifyChange) => {
  const qty = Number(row.quantity) || 1;
  const selling = Number(row.selling_price) || 0;
  row.subtotal = qty * selling;
  notifyChange && notifyChange();
};

// Quản lý Dialog Hẹn Ngày Giao Hàng Loạt
const showBulkDialog = ref(false);
const bulkDate = ref('');
const orderItems = ref([]);

const openBulkDateDialog = (items) => {
  orderItems.value = items || [];
  bulkDate.value = new Date().toISOString().split('T')[0];
  showBulkDialog.value = true;
};

const applyBulkDate = () => {
  if (bulkDate.value && orderItems.value.length > 0) {
    orderItems.value.forEach((it) => {
      it.delivery_date = bulkDate.value;
    });
    emit('update:modelValue', [...orderItems.value]);
    emit('summary-change');
  }
  showBulkDialog.value = false;
};
</script>
