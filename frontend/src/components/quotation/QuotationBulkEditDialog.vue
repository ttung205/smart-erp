<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    header="Thiết Lập Giá Trị Hàng Loạt Cho Báo Giá"
    :style="{ width: '560px' }"
    class="rounded-[8px]"
  >
    <div class="space-y-4 pt-1">
      <!-- Phạm vi áp dụng -->
      <div class="p-3 bg-slate-50 border border-slate-200 rounded-[6px] text-xs space-y-2">
        <span class="font-semibold text-slate-700 block">Phạm vi áp dụng:</span>
        <div class="flex items-center gap-4">
          <label
            class="flex items-center gap-2 cursor-pointer font-medium"
            :class="selectedCount > 0 ? 'text-indigo-700' : 'text-slate-400'"
          >
            <input
              type="radio"
              value="selected"
              v-model="bulkScope"
              :disabled="selectedCount === 0"
              class="text-indigo-600 focus:ring-indigo-500"
            />
            <span>Áp dụng cho <strong>{{ selectedCount }}</strong> mặt hàng đang chọn</span>
          </label>

          <label class="flex items-center gap-2 cursor-pointer font-medium text-slate-700">
            <input
              type="radio"
              value="all"
              v-model="bulkScope"
              class="text-indigo-600 focus:ring-indigo-500"
            />
            <span>Tất cả <strong>{{ items.length }}</strong> mặt hàng</span>
          </label>
        </div>
      </div>

      <p class="text-[11px] text-slate-500 italic m-0">
        * Điền giá trị vào các ô bạn muốn thay đổi. Các ô để trống sẽ giữ nguyên giá trị hiện tại của từng dòng mặt hàng.
      </p>

      <!-- Grid 6 trường thông tin -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 text-xs">
        <!-- 1. Chiết khấu % -->
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700 flex items-center justify-between">
            <span>Chiết khấu (CK %)</span>
            <span class="text-[10px] text-emerald-600 font-normal">Tự tính lại giá bán & TT</span>
          </label>
          <InputNumber
            v-model="bulkForm.discount_percent"
            :min="0"
            :max="100"
            :minFractionDigits="0"
            :maxFractionDigits="1"
            suffix="%"
            placeholder="VD: 80%"
            class="w-full text-xs font-mono font-bold"
            inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[6px] border-slate-300 text-emerald-700"
          />
        </div>

        <!-- 2. Thuế VAT % -->
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700">Thuế VAT %</label>
          <InputNumber
            v-model="bulkForm.vat_percent"
            :min="0"
            :max="100"
            suffix="%"
            placeholder="VD: 8% hoặc 10%"
            class="w-full text-xs font-mono"
            inputClass="!py-1.5 !px-2 text-center text-xs font-mono rounded-[6px] border-slate-300"
          />
        </div>

        <!-- 3. Số lượng (QTY) -->
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700 flex items-center justify-between">
            <span>Số lượng (QTY)</span>
            <span class="text-[10px] text-sky-600 font-normal">Tự tính lại thành tiền</span>
          </label>
          <InputNumber
            v-model="bulkForm.quantity"
            :min="1"
            placeholder="VD: 10"
            class="w-full text-xs font-mono font-bold"
            inputClass="!py-1.5 !px-2 text-right text-xs font-mono font-bold rounded-[6px] border-slate-300"
          />
        </div>

        <!-- 4. Lead Time (Thời gian giao hàng) -->
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700">Lead Time (Giao hàng)</label>
          <InputText
            v-model="bulkForm.lead_time"
            placeholder="VD: Có sẵn, 2-3 tuần..."
            class="w-full text-xs rounded-[6px] !py-1.5 !px-2 border-slate-300"
          />
        </div>

        <!-- 5. Xuất xứ (COM) -->
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700">Xuất xứ (COM)</label>
          <InputText
            v-model="bulkForm.com"
            placeholder="VD: Germany, China, Japan..."
            class="w-full text-xs rounded-[6px] !py-1.5 !px-2 border-slate-300"
          />
        </div>

        <!-- 6. Đơn vị tính (UNIT) -->
        <div class="flex flex-col gap-1">
          <label class="font-semibold text-slate-700">Đơn vị tính (UNIT)</label>
          <InputText
            v-model="bulkForm.unit"
            placeholder="VD: Cái, Bộ, Mét, Gói..."
            class="w-full text-xs rounded-[6px] !py-1.5 !px-2 border-slate-300"
          />
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <Button
          label="Xóa form nhập"
          icon="pi pi-refresh"
          text
          size="small"
          severity="secondary"
          class="!text-xs"
          @click="resetForm"
        />
        <div class="flex gap-2">
          <Button
            label="Hủy bỏ"
            icon="pi pi-times"
            text
            size="small"
            severity="secondary"
            @click="$emit('update:visible', false)"
          />
          <Button
            label="Áp dụng hàng loạt"
            icon="pi pi-check"
            size="small"
            class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 !font-semibold rounded-[6px]"
            @click="handleApply"
          />
        </div>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  items: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:visible', 'apply']);

const bulkScope = ref('selected');
const bulkForm = ref({
  discount_percent: null,
  vat_percent: null,
  quantity: null,
  lead_time: '',
  com: '',
  unit: ''
});

const selectedCount = computed(() => {
  return (props.items || []).filter((it) => it._selected).length;
});

const resetForm = () => {
  bulkForm.value = {
    discount_percent: null,
    vat_percent: null,
    quantity: null,
    lead_time: '',
    com: '',
    unit: ''
  };
};

// Khi mở dialog, tự động chọn scope phù hợp và reset form
watch(
  () => props.visible,
  (newVal) => {
    if (newVal) {
      bulkScope.value = selectedCount.value > 0 ? 'selected' : 'all';
      resetForm();
    }
  }
);

const handleApply = () => {
  const scope = bulkScope.value;
  let targetList = [];
  if (scope === 'selected' && selectedCount.value > 0) {
    targetList = props.items.filter((it) => it._selected);
  } else {
    targetList = props.items;
  }

  if (targetList.length === 0) {
    alert('Không có mặt hàng nào để áp dụng!');
    return;
  }

  let hasChange = false;

  targetList.forEach((row) => {
    // 1. Chiết khấu %
    if (bulkForm.value.discount_percent !== null && bulkForm.value.discount_percent !== undefined && bulkForm.value.discount_percent !== '') {
      const disc = Number(bulkForm.value.discount_percent);
      row.discount_percent = disc;
      const gross = Number(row.gross_price) || Number(row.selling_price) || 0;
      row.selling_price = Math.round(gross * (1 - disc / 100));
      row.subtotal = (Number(row.quantity) || 1) * row.selling_price;
      hasChange = true;
    }

    // 2. Thuế VAT %
    if (bulkForm.value.vat_percent !== null && bulkForm.value.vat_percent !== undefined && bulkForm.value.vat_percent !== '') {
      row.vat_percent = Number(bulkForm.value.vat_percent);
      hasChange = true;
    }

    // 3. Số lượng (QTY)
    if (bulkForm.value.quantity !== null && bulkForm.value.quantity !== undefined && bulkForm.value.quantity !== '' && Number(bulkForm.value.quantity) > 0) {
      const qty = Number(bulkForm.value.quantity);
      row.quantity = qty;
      row.subtotal = qty * (Number(row.selling_price) || 0);
      hasChange = true;
    }

    // 4. Lead Time
    if (bulkForm.value.lead_time !== null && bulkForm.value.lead_time !== undefined && bulkForm.value.lead_time !== '') {
      row.lead_time = String(bulkForm.value.lead_time).trim();
      hasChange = true;
    }

    // 5. COM (Xuất xứ)
    if (bulkForm.value.com !== null && bulkForm.value.com !== undefined && bulkForm.value.com !== '') {
      row.com = String(bulkForm.value.com).trim();
      hasChange = true;
    }

    // 6. UNIT (Đơn vị tính)
    if (bulkForm.value.unit !== null && bulkForm.value.unit !== undefined && bulkForm.value.unit !== '') {
      row.unit = String(bulkForm.value.unit).trim();
      hasChange = true;
    }
  });

  if (hasChange) {
    emit('apply', props.items);
  }

  emit('update:visible', false);
};
</script>
