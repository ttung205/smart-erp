<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Chi Tiết Báo Giá & Chọn Item Win: ${quotation?.quotation_number || ''}`"
    :style="{ width: '960px' }"
    class="rounded-[8px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="quotation" class="flex flex-col gap-4 pt-1">
      <!-- 1. Header Info Bar -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Khách hàng:</span>
          <span class="font-bold text-slate-800 text-sm">{{ quotation.customer_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Ngày lập:</span>
          <span class="font-semibold text-slate-700">{{ quotation.quotation_date || quotation.created_at || 'Hôm nay' }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Hạn hiệu lực:</span>
          <span class="font-semibold text-slate-700">{{ quotation.valid_until || '30 ngày' }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Trạng thái:</span>
          <Tag
            :severity="getStatusSeverity(quotation.status)"
            :value="getStatusLabel(quotation.status)"
            class="text-[11px] px-2 py-0.5 rounded-[4px]"
          />
        </div>
      </div>

      <!-- 2. Hướng dẫn chọn Item Win -->
      <div class="bg-sky-50 border border-sky-200 rounded-[6px] p-2.5 flex items-center justify-between text-xs text-sky-800">
        <div class="flex items-center gap-2">
          <i class="pi pi-check-circle text-sky-600 text-base"></i>
          <span>
            <strong>Chọn Item Win (Trúng thầu / Khách chốt mua):</strong>
            Tích chọn các mặt hàng khách hàng đồng ý mua và điều chỉnh số lượng chốt để sinh Đơn Bán Hàng (Sales Order).
          </span>
        </div>
        <div class="font-semibold text-sky-900 shrink-0">
          Đã chọn: <span class="text-sm font-bold text-indigo-700">{{ selectedWonItems.length }}</span> / {{ items.length }} mặt hàng
        </div>
      </div>

      <!-- 3. Bảng các mặt hàng với Checkbox chọn Win -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <DataTable
          :value="items"
          showGridlines
          class="clean-datatable text-xs"
          responsiveLayout="scroll"
        >
          <!-- Cột Checkbox Win -->
          <Column header="Trúng (Win)" style="width: 80px" class="text-center">
            <template #body="{ data }">
              <div class="flex justify-center">
                <input
                  type="checkbox"
                  v-model="data.won"
                  class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
                />
              </div>
            </template>
          </Column>

          <!-- Mã SKU -->
          <Column field="sku" header="Mã SKU" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-slate-700">{{ data.sku }}</span>
            </template>
          </Column>

          <!-- Tên sản phẩm / Quy cách -->
          <Column field="designation" header="Tên Sản Phẩm">
            <template #body="{ data }">
              <div class="flex flex-col gap-0.5">
                <span class="font-semibold text-slate-900 text-xs">{{ data.designation }}</span>
                <span v-if="data.brand" class="text-[11px] text-slate-400">Thương hiệu: {{ data.brand }}</span>
              </div>
            </template>
          </Column>

          <!-- SL Báo giá -->
          <Column header="SL Báo Giá" class="text-right" style="width: 90px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-600">{{ data.quantity }}</span>
            </template>
          </Column>

          <!-- SL Chốt Thầu (Won Qty) -->
          <Column header="SL Chốt Mua" class="text-center" style="width: 110px">
            <template #body="{ data }">
              <InputNumber
                v-model="data.won_quantity"
                :disabled="!data.won"
                :min="1"
                :max="data.quantity"
                class="w-20 rounded-[6px]"
                size="small"
              />
            </template>
          </Column>

          <!-- Đơn Giá -->
          <Column header="Đơn Giá" class="text-right" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-700">{{ formatCurrency(data.selling_price) }}</span>
            </template>
          </Column>

          <!-- Thành Tiền Chốt -->
          <Column header="Thành Tiền Chốt" class="text-right" style="width: 140px">
            <template #body="{ data }">
              <span
                class="font-mono text-xs font-bold"
                :class="data.won ? 'text-indigo-600' : 'text-slate-400 line-through'"
              >
                {{ formatCurrency(getLineAmount(data)) }}
              </span>
            </template>
          </Column>

          <template #empty>
            <div class="py-6 text-center text-slate-400 text-xs italic">
              Báo giá này chưa có mặt hàng nào.
            </div>
          </template>
        </DataTable>
      </div>

      <!-- 4. Tổng kết giá trị Item Win -->
      <div class="flex items-center justify-between p-3 bg-slate-50 border border-slate-200 rounded-[6px]">
        <div class="text-xs text-slate-600">
          <span>Ghi chú báo giá: </span>
          <span class="italic text-slate-800">{{ quotation.notes || 'Không có ghi chú' }}</span>
        </div>
        <div class="text-right">
          <span class="text-xs text-slate-500 mr-2">Tổng giá trị đơn hàng chốt (chưa VAT):</span>
          <span class="text-base font-bold font-mono text-indigo-700">{{ formatCurrency(totalWonAmount) }}</span>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full pt-2">
        <Button
          label="Đóng"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px] !text-xs"
          @click="$emit('update:visible', false)"
        />

        <div class="flex gap-2">
          <Button
            label="Chọn tất cả Win"
            icon="pi pi-check-square"
            text
            size="small"
            class="rounded-[6px] !text-xs !text-slate-600"
            @click="selectAllItems(true)"
          />
          <Button
            label="Tạo Đơn Bán Hàng Từ Items Win"
            icon="pi pi-shopping-cart"
            size="small"
            :disabled="selectedWonItems.length === 0"
            class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 rounded-[6px] !font-semibold !text-xs shadow-sm"
            @click="confirmGenerateSO"
          />
        </div>
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputNumber from 'primevue/inputnumber';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  quotation: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:visible', 'create-so']);

const items = ref([]);

const getLineAmount = (item) => {
  const qty = Number(item.won_quantity || item.quantity || 0);
  const price = Number(item.selling_price || 0);
  const disc = Number(item.discount_percent || 0);
  return qty * price * (1 - disc / 100);
};

watch(
  () => props.quotation,
  (newQ) => {
    if (newQ && newQ.items) {
      items.value = newQ.items.map((item) => ({
        ...item,
        sku: item.sku || item.product_sku || '',
        designation: item.designation || item.product_name || item.description || '',
        selling_price: Number(item.selling_price || item.unit_price || item.gross_price || 0),
        won: item.won ?? item.is_won ?? true,
        won_quantity: Number(item.won_quantity || item.quantity || 1)
      }));
    } else {
      items.value = [];
    }
  },
  { immediate: true }
);

const selectAllItems = (state) => {
  items.value.forEach((item) => {
    item.won = state;
  });
};

const selectedWonItems = computed(() => {
  return items.value.filter((i) => i.won && i.won_quantity > 0);
});

const totalWonAmount = computed(() => {
  return selectedWonItems.value.reduce((acc, i) => acc + getLineAmount(i), 0);
});

const getStatusLabel = (st) => {
  const map = {
    DRAFT: 'Bản nháp',
    PENDING_APPROVAL: 'Chờ duyệt',
    APPROVED: 'Đã duyệt',
    CONVERTED: 'Đã sinh đơn bán (SO)',
    REJECTED: 'Từ chối'
  };
  return map[st] || st;
};

const getStatusSeverity = (st) => {
  const map = {
    DRAFT: 'secondary',
    PENDING_APPROVAL: 'warn',
    APPROVED: 'success',
    CONVERTED: 'info',
    REJECTED: 'danger'
  };
  return map[st] || 'secondary';
};

const confirmGenerateSO = () => {
  if (selectedWonItems.value.length === 0) {
    alert('Vui lòng chọn ít nhất 1 mặt hàng thắng thầu (Win) để tạo đơn bán!');
    return;
  }

  const payload = {
    quotation_id: props.quotation.id || props.quotation.quotation_number,
    quotation_number: props.quotation.quotation_number,
    customer_id: props.quotation.customer_id,
    customer_name: props.quotation.customer_name,
    items: selectedWonItems.value.map((i) => ({
      product_id: i.product_id || i.id,
      sku: i.sku,
      product_sku: i.sku,
      designation: i.designation,
      product_name: i.designation,
      quantity: i.won_quantity,
      won_quantity: i.won_quantity,
      unit_price: i.selling_price,
      selling_price: i.selling_price,
      discount_percent: i.discount_percent || 0,
      subtotal: getLineAmount(i),
      amount: getLineAmount(i)
    })),
    total_amount: totalWonAmount.value * 1.1
  };

  emit('create-so', payload);
  emit('update:visible', false);
};
</script>
