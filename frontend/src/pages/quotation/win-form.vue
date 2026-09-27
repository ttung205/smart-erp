<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Chi Tiết Báo Giá & Chọn Item Win: ${quotation?.quotation_number || ''}`"
    :style="{ width: '920px' }"
    class="rounded-[6px]"
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
          <span class="font-semibold text-slate-700">{{ quotation.quotation_date }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Hạn hiệu lực:</span>
          <span class="font-semibold text-slate-700">{{ quotation.valid_until }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Trạng thái:</span>
          <Tag
            :severity="getStatusSeverity(quotation.status)"
            :value="getStatusLabel(quotation.status)"
            class="text-[11px] px-2 py-0.5 rounded-[6px]"
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
          class="clean-datatable"
          responsiveLayout="scroll"
        >
          <!-- Cột Checkbox Win -->
          <Column header="Trúng (Win)" style="width: 80px" class="text-center">
            <template #body="{ data }">
              <input
                type="checkbox"
                v-model="data.won"
                class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 cursor-pointer"
              />
            </template>
          </Column>

          <Column field="product_sku" header="Mã SKU" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-slate-700">{{ data.product_sku }}</span>
            </template>
          </Column>

          <Column field="product_name" header="Tên Sản Phẩm">
            <template #body="{ data }">
              <span class="font-semibold text-slate-900 text-xs">{{ data.product_name }}</span>
            </template>
          </Column>

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

          <Column header="Đơn Giá" class="text-right" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-700">{{ formatCurrency(data.unit_price) }}</span>
            </template>
          </Column>

          <Column header="Thành Tiền Chốt" class="text-right" style="width: 140px">
            <template #body="{ data }">
              <span
                class="font-mono text-xs font-bold"
                :class="data.won ? 'text-indigo-600' : 'text-slate-400 line-through'"
              >
                {{ formatCurrency((data.won_quantity || data.quantity) * data.unit_price * (1 - (data.discount_percent || 0) / 100)) }}
              </span>
            </template>
          </Column>
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
      <div class="flex justify-between items-center w-full">
        <Button
          label="Đóng"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
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
            class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 rounded-[6px] !font-semibold"
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

watch(
  () => props.quotation,
  (newQ) => {
    if (newQ && newQ.items) {
      items.value = newQ.items.map((item) => ({
        ...item,
        won: item.won ?? true,
        won_quantity: item.won_quantity || item.quantity
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
  return selectedWonItems.value.reduce((acc, i) => {
    const discounted = i.unit_price * (1 - (i.discount_percent || 0) / 100);
    return acc + (i.won_quantity || i.quantity) * discounted;
  }, 0);
});

const getStatusLabel = (st) => {
  const map = {
    DRAFT: 'Dự thảo',
    SENT: 'Đã gửi khách',
    NEGOTIATING: 'Đang đàm phán',
    CONVERTED: 'Đã sinh đơn bán (SO)',
    REJECTED: 'Từ chối'
  };
  return map[st] || st;
};

const getStatusSeverity = (st) => {
  const map = {
    DRAFT: 'secondary',
    SENT: 'info',
    NEGOTIATING: 'warn',
    CONVERTED: 'success',
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
    quotation_id: props.quotation.quotation_number,
    customer_id: props.quotation.customer_id,
    customer_name: props.quotation.customer_name,
    items: selectedWonItems.value.map((i) => ({
      product_id: i.product_id,
      product_sku: i.product_sku,
      product_name: i.product_name,
      quantity: i.won_quantity,
      unit_price: i.unit_price,
      discount_percent: i.discount_percent || 0,
      amount: i.won_quantity * i.unit_price * (1 - (i.discount_percent || 0) / 100)
    })),
    total_amount: totalWonAmount.value * 1.1
  };

  emit('create-so', payload);
  emit('update:visible', false);
};
</script>
