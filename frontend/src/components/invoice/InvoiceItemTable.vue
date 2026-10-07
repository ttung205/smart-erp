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

      <!-- 6. Đơn vị tính (Unit) -->
      <template #col-unit="{ data }">
        <span class="text-xs text-slate-700 text-center block">{{ data.unit || 'Cái' }}</span>
      </template>

      <!-- 7. Số Lượng (Lấy trực tiếp từ Yêu Cầu Giao Hàng) -->
      <template #col-quantity="{ data }">
        <span class="text-xs font-bold text-sky-800 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-[4px] block text-right font-mono" :title="`Số lượng xuất theo yêu cầu giao: ${data.quantity || data.requested_quantity}`">
          {{ data.quantity != null ? data.quantity : (data.requested_quantity || 0) }}
        </span>
      </template>

      <!-- 8. Giá bán thực tế (Selling Price / Unit Price) - Read Only -->
      <template #col-selling_price="{ data }">
        <span class="text-xs font-bold text-slate-800 block text-right">
          {{ formatCurrency(data.unit_price || data.selling_price) }}
        </span>
      </template>

      <!-- 9. Thuế VAT % -->
      <template #col-vat_percent="{ data }">
        <span class="text-xs text-slate-600 text-center block">
          {{ data.vat_percent || 0 }}%
        </span>
      </template>

      <!-- 10. Lead Time (Giao hàng) -->
      <template #col-lead_time="{ data }">
        <span class="text-xs text-slate-700 truncate block px-1" :title="data.lead_time">
          {{ data.lead_time || '-' }}
        </span>
      </template>

      <!-- 11. NGÀY HẸN GIAO (Delivery Date) -->
      <template #col-delivery_date="{ data }">
        <span class="text-xs text-slate-700 block text-center font-mono" :title="data.delivery_date">
          {{ data.delivery_date ? formatDate(data.delivery_date) : '-' }}
        </span>
      </template>

      <!-- 12. Thành tiền (Subtotal) -->
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
import ItemDataTable from '../common/ItemDataTable.vue';
import { formatCurrency, formatDate } from '../../utils/format';

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

defineEmits(['update:modelValue', 'summary-change']);

// Cột mặt hàng chứng từ bán hàng: Chỉ cho phép chỉnh sửa ITEM DESCRIPTION, tất cả các cột khác đều là Read-only
const invoiceColumns = [
  { field: 'sku', header: 'INTERNAL CODE', width: '150px', frozen: true, class: '!border-r-2 !border-r-slate-300 shadow-[4px_0_8px_-3px_rgba(0,0,0,0.12)]', headerClass: '!border-r-2 !border-r-slate-300 shadow-[4px_0_8px_-3px_rgba(0,0,0,0.12)]' },
  { field: 'designation', header: 'DESIGNATION', width: '160px' },
  { field: 'brand', header: 'BRAND', width: '110px' },
  { field: 'description', header: 'ITEM DESCRIPTION', width: '280px' },
  { field: 'com', header: 'COM', width: '110px', align: 'center' },
  { field: 'unit', header: 'UNIT', width: '80px', align: 'center' },
  { field: 'quantity', header: 'SỐ LƯỢNG', width: '110px', align: 'right' },
  { field: 'selling_price', header: 'SELLING PRICE', width: '150px', align: 'right' },
  { field: 'vat_percent', header: 'VAT %', width: '85px', align: 'center' },
  { field: 'lead_time', header: 'LEAD TIME', width: '110px' },
  { field: 'delivery_date', header: 'HẸN GIAO', width: '120px', align: 'center' },
  { field: 'subtotal', header: 'THÀNH TIỀN', width: '160px', align: 'right' }
];
</script>

<style scoped>
.invoice-item-table-container :deep(.p-datatable) {
  font-size: 13px;
}
</style>
