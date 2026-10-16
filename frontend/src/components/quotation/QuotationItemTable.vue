<template>
  <div class="quotation-item-table-container">
    <ItemDataTable
      ref="itemTableRef"
      :model-value="modelValue"
      :columns="quotationColumns"
      v-bind="$attrs"
      @update:model-value="$emit('update:modelValue', $event)"
      @summary-change="$emit('summary-change', $event)"
    >
      <!-- Cột Checkbox Chọn Dòng - Luôn hiển thị để tiện chọn dòng và thao tác hàng loạt -->
      <template #prepend-columns="{ items, notifyChange }">
        <Column style="width: 44px; min-width: 44px" class="text-center" frozen>
          <template #header>
            <div class="flex justify-center items-center w-full">
              <input
                type="checkbox"
                :checked="items.length > 0 && items.every((it) => it._selected)"
                @change="(e) => { items.forEach((it) => it._selected = e.target.checked); notifyChange && notifyChange(); }"
                class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                title="Chọn tất cả các dòng"
              />
            </div>
          </template>
          <template #body="{ data }">
            <div class="flex justify-center items-center w-full">
              <input
                type="checkbox"
                v-model="data._selected"
                class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                title="Chọn dòng này"
              />
            </div>
          </template>
        </Column>
      </template>

      <!-- 1. SKU (Internal Code) -->
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
        <InputText
          v-model="data.description"
          class="w-full text-xs text-slate-800 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          placeholder="Tên / mô tả sản phẩm..."
          :title="data.description"
          @change="notifyChange && notifyChange()"
        />
      </template>

      <!-- 5. Xuất xứ (COM) -->
      <template #col-com="{ data, notifyChange }">
        <InputText
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
        <InputText
          v-model="data.unit"
          class="w-full text-xs text-center !py-1.5 !px-1 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          placeholder="Cái"
          @change="notifyChange && notifyChange()"
        />
      </template>

      <!-- 8. Số lượng (Quantity) -->
      <template #col-quantity="{ data, notifyChange }">
        <InputNumber
          v-model="data.quantity"
          :min="1"
          class="w-full text-xs font-mono font-bold"
          inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          @update:modelValue="onQuantityChange(data, notifyChange)"
        />
      </template>

      <!-- 9. Chiết khấu % (Discount) -->
      <template #col-discount_percent="{ data, notifyChange }">
        <InputNumber
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
        <InputNumber
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
          :title="`Giá niêm yết Catalog: ${formatCurrency(data.gross_price)}`"
        >
          {{ formatCurrency(data.gross_price) }}
        </span>
      </template>

      <!-- 12. Thuế VAT % -->
      <template #col-vat_percent="{ data, notifyChange }">
        <InputNumber
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
        <InputText
          v-model="data.lead_time"
          class="w-full text-xs text-slate-700 !py-1.5 !px-2 rounded-[4px] !border-slate-200 hover:!border-slate-300 focus:!border-sky-500 bg-white"
          placeholder="VD: Có sẵn..."
          :title="data.lead_time"
          @change="notifyChange && notifyChange()"
        />
      </template>

      <!-- 14. Thành tiền (Subtotal) -->
      <template #col-subtotal="{ data }">
        <span class="font-mono text-xs font-bold text-slate-900 num-tabular px-2 py-1 bg-slate-50 border border-slate-100 rounded-[4px] block text-right">
          {{ formatCurrency(data.subtotal || (data.quantity * data.selling_price)) }}
        </span>
      </template>

      <!-- 15. Trạng thái mặt hàng (Status: WIN / LOSE / PENDING) - Nghiệp vụ đặc thù Báo giá -->
      <template #col-status="{ data }">
        <div class="text-center">
          <Badge
            :severity="getItemStatusSeverity(data.status)"
            :value="getItemStatusLabel(data.status)"
          />
        </div>
      </template>

      <!-- Thao tác nghiệp vụ đặc thù của Báo Giá -->
      <template #extra-actions="{ items }">
        <!-- Nút Set Hàng Loạt (Chiết khấu, VAT, Lead Time, QTY, COM, UNIT) -->
        <Button
          :label="getSelectedCount(items) > 0 ? `Set hàng loạt (${getSelectedCount(items)})` : 'Set hàng loạt'"
          icon="pi pi-bolt"
          size="small"
          outlined
          :class="ITEM_TABLE_ACTION_CLASS"
          title="Thiết lập hàng loạt Chiết khấu %, VAT %, Lead Time, QTY, COM, UNIT cho các mặt hàng"
          @click="openBulkEditDialog(items)"
        />

        <template v-if="computedShowStatus">
          <!-- Nút Đổi trạng thái WIN / LOSE / PENDING -->
          <Button
            :label="getSelectedCount(items) > 0 ? `Đổi trạng thái (${getSelectedCount(items)})` : 'Đổi trạng thái'"
            icon="pi pi-sliders-h"
            size="small"
            outlined
            :class="ITEM_TABLE_ACTION_CLASS"
            @click="openStatusDialog(items)"
          />

          <!-- Nút Tạo Đơn Bán Hàng (SO) -->
          <Button
            label="Tạo đơn bán (SO)"
            icon="pi pi-shopping-cart"
            size="small"
            outlined
            :class="ITEM_TABLE_ACTION_CLASS"
            @click="handleCreateOrderAction(items)"
          />
        </template>
      </template>
    </ItemDataTable>

    <!-- Modal Thiết Lập Giá Trị Hàng Loạt (Chiết khấu, VAT, Lead Time, QTY, COM, UNIT) -->
    <QuotationBulkEditDialog
      v-model:visible="showBulkEditDialog"
      :items="targetItems"
      @apply="onBulkEditApplied"
    />

    <!-- Modal Đổi Trạng Thái Mặt Hàng (Win / Lose / Pending) - Nghiệp vụ đặc thù Báo giá -->
    <Dialog
      v-model:visible="showStatusDialog"
      modal
      header="Thiết Lập Trạng Thái Mặt Hàng (Win / Lose / Pending)"
      :style="{ width: '450px' }"
      class="rounded-[6px]"
    >
      <div class="space-y-4 pt-1">
        <p class="text-xs text-slate-600 m-0">
          Áp dụng trạng thái thầu cho <strong>{{ getSelectedCount(targetItems) }}</strong> mặt hàng đã chọn trong báo giá:
        </p>

        <div class="flex flex-col gap-2">
          <label
            class="flex items-start gap-2.5 p-3 rounded-[6px] border cursor-pointer transition-colors"
            :class="newStatusToSet === 'WIN' ? 'bg-emerald-50 border-emerald-400 text-emerald-900' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
          >
            <input type="radio" value="WIN" v-model="newStatusToSet" class="mt-0.5 text-emerald-600 focus:ring-emerald-500 cursor-pointer" />
            <div class="flex flex-col">
              <span class="font-bold text-xs flex items-center gap-1.5 text-emerald-700">
                <i class="pi pi-check-circle"></i> WIN - Trúng thầu (Khách chốt mua)
              </span>
              <span class="text-[11px] text-slate-500 mt-0.5">
                Mặt hàng khách đồng ý mua, đủ điều kiện để tạo Đơn Bán Hàng (Sales Order).
              </span>
            </div>
          </label>

          <label
            class="flex items-start gap-2.5 p-3 rounded-[6px] border cursor-pointer transition-colors"
            :class="newStatusToSet === 'PENDING' ? 'bg-amber-50 border-amber-400 text-amber-900' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
          >
            <input type="radio" value="PENDING" v-model="newStatusToSet" class="mt-0.5 text-amber-600 focus:ring-amber-500 cursor-pointer" />
            <div class="flex flex-col">
              <span class="font-bold text-xs flex items-center gap-1.5 text-amber-700">
                <i class="pi pi-clock"></i> PENDING - Chờ phản hồi (Đang chào giá)
              </span>
              <span class="text-[11px] text-slate-500 mt-0.5">
                Mặt hàng đang trong quá trình thương thảo, chờ khách hàng chốt hoặc duyệt giá.
              </span>
            </div>
          </label>

          <label
            class="flex items-start gap-2.5 p-3 rounded-[6px] border cursor-pointer transition-colors"
            :class="newStatusToSet === 'LOSE' ? 'bg-rose-50 border-rose-400 text-rose-900' : 'border-slate-200 hover:bg-slate-50 text-slate-700'"
          >
            <input type="radio" value="LOSE" v-model="newStatusToSet" class="mt-0.5 text-rose-600 focus:ring-rose-500 cursor-pointer" />
            <div class="flex flex-col">
              <span class="font-bold text-xs flex items-center gap-1.5 text-rose-700">
                <i class="pi pi-times-circle"></i> LOSE - Trượt thầu (Thua / Không mua)
              </span>
              <span class="text-[11px] text-slate-500 mt-0.5">
                Khách hàng từ chối mặt hàng này hoặc chọn đối thủ cạnh tranh khác.
              </span>
            </div>
          </label>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button label="Hủy bỏ" icon="pi pi-times" text size="small" severity="secondary" @click="showStatusDialog = false" />
          <Button label="Cập nhật trạng thái" icon="pi pi-check" size="small" class="!bg-emerald-600 hover:!bg-emerald-700 !text-white" @click="applyNewStatus" />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Dialog from 'primevue/dialog';
import Badge from 'primevue/badge';
import ItemDataTable from '../common/ItemDataTable.vue';
import QuotationBulkEditDialog from './QuotationBulkEditDialog.vue';
import { formatCurrency } from '../../utils/format';
import { ITEM_TABLE_ACTION_CLASS } from '../../constants/itemTable.constant';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  isApproved: {
    type: Boolean,
    default: false
  },
  quotationStatus: {
    type: String,
    default: ''
  }
});

const emit = defineEmits(['update:modelValue', 'summary-change', 'create-order']);

const itemTableRef = ref(null);

const computedShowStatus = computed(() => {
  return props.isApproved || props.quotationStatus === 'APPROVED';
});

// Cột mặt hàng đặc thù do QuotationItemTable định nghĩa cho Báo Giá
const quotationColumns = computed(() => {
  const cols = [
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
    { field: 'subtotal', header: 'SUBTOTAL', width: '160px', align: 'right' }
  ];

  if (computedShowStatus.value) {
    cols.push({ field: 'status', header: 'STATUS', width: '110px', align: 'center' });
  }

  return cols;
});

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

// Target items dùng chung cho các dialog thao tác
const targetItems = ref([]);

const getSelectedCount = (items) => {
  return (items || []).filter((it) => it._selected).length;
};

// =========================================================================
// 1. Quản lý Dialog Thiết Lập Giá Trị Hàng Loạt (CK, VAT, QTY, LeadTime, COM, Unit)
// =========================================================================
const showBulkEditDialog = ref(false);

const openBulkEditDialog = (items) => {
  targetItems.value = items || [];
  if (targetItems.value.length === 0) {
    alert('Danh sách mặt hàng đang trống, không thể thiết lập hàng loạt!');
    return;
  }
  showBulkEditDialog.value = true;
};

const onBulkEditApplied = (updatedItems) => {
  emit('update:modelValue', [...updatedItems]);
  emit('summary-change');
};

// =========================================================================
// 2. Trạng thái thầu (WIN / LOSE / PENDING)
// =========================================================================
const showStatusDialog = ref(false);
const newStatusToSet = ref('WIN');

const openStatusDialog = (items) => {
  targetItems.value = items || [];
  if (getSelectedCount(targetItems.value) === 0) {
    alert('Vui lòng tích chọn ít nhất 1 dòng mặt hàng trong bảng trước khi đổi trạng thái!');
    return;
  }
  showStatusDialog.value = true;
};

const applyNewStatus = () => {
  const targetStatus = newStatusToSet.value;
  (targetItems.value || []).forEach((it) => {
    if (it._selected) {
      it.status = targetStatus;
      it.won = targetStatus === 'WIN';
      it.is_won = targetStatus === 'WIN';
    }
  });
  emit('update:modelValue', [...targetItems.value]);
  showStatusDialog.value = false;
};

const handleCreateOrderAction = (items) => {
  const selectedWinItems = (items || []).filter((it) => it._selected && it.status === 'WIN');
  if (selectedWinItems.length > 0) {
    emit('create-order', selectedWinItems);
    return;
  }
  const allWinItems = (items || []).filter((it) => it.status === 'WIN');
  if (allWinItems.length === 0) {
    alert('Không có mặt hàng nào có trạng thái WIN! Vui lòng chọn các mặt hàng và nhấn "Đổi trạng thái" sang WIN trước khi tạo đơn bán hàng.');
    return;
  }
  emit('create-order', allWinItems);
};

// Helpers hiển thị status badge
const getItemStatusLabel = (st) => {
  if (st === 'WIN') return 'WIN';
  if (st === 'LOSE') return 'LOSE';
  return 'PENDING';
};

const getItemStatusSeverity = (st) => {
  if (st === 'WIN') return 'success';
  if (st === 'LOSE') return 'danger';
  return 'warn';
};
</script>
