<template>
  <Dialog
    :visible="visible"
    modal
    header="Tạo Đơn Bán Hàng Mới"
    :style="{ width: '640px' }"
    :closable="!loading"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="flex flex-col gap-4 pt-1">
      <div class="grid grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Mã đơn hàng <span class="text-red-600">*</span></label>
          <InputText
            v-model="order.order_number"
            class="w-full rounded-[6px]"
            size="small"
            placeholder="SO-2026-XXXX"
          />
        </div>
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Ngày tạo đơn</label>
          <InputText
            v-model="order.order_date"
            type="date"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-slate-700">Khách hàng B2B <span class="text-red-600">*</span></label>
        <Select
          v-model="order.customer_id"
          :options="customerOptions"
          optionLabel="name"
          optionValue="id"
          placeholder="Chọn khách hàng doanh nghiệp..."
          class="w-full rounded-[6px]"
          size="small"
        />
      </div>

      <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 flex flex-col gap-2">
        <span class="text-xs font-semibold text-slate-900">Danh sách mặt hàng:</span>
        <div
          v-for="(item, idx) in order.items"
          :key="idx"
          class="flex items-center gap-2"
        >
          <InputText
            v-model="item.name"
            placeholder="Tên sản phẩm..."
            class="flex-[2] rounded-[6px]"
            size="small"
          />
          <InputNumber
            v-model="item.quantity"
            placeholder="SL"
            :min="1"
            class="w-20 rounded-[6px]"
            size="small"
          />
          <InputNumber
            v-model="item.unit_price"
            placeholder="Đơn giá"
            currency="VND"
            locale="vi-VN"
            class="flex-1 rounded-[6px]"
            size="small"
          />
          <div class="text-[13px] font-semibold text-slate-900 min-w-[120px] text-right num-tabular">
            {{ formatCurrency((item.quantity || 0) * (item.unit_price || 0)) }}
          </div>
        </div>
      </div>

      <div class="flex justify-between items-center pt-2 border-t border-slate-200">
        <span class="text-sm font-semibold text-slate-700">Tổng giá trị đơn hàng:</span>
        <span class="text-lg font-bold text-indigo-600 num-tabular">{{ formatCurrency(totalAmount) }}</span>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Hủy bỏ"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          :disabled="loading"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Tạo đơn hàng"
          icon="pi pi-check"
          size="small"
          :loading="loading"
          class="rounded-[6px]"
          @click="$emit('submit', { ...order, total_amount: totalAmount })"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { reactive, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Button from 'primevue/button';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  loading: {
    type: Boolean,
    default: false
  },
  customers: {
    type: Array,
    default: () => []
  }
});

defineEmits(['update:visible', 'submit']);

const customerOptions = computed(() => {
  return props.customers.map((c) => ({ id: c.id, name: `${c.code} - ${c.name}` }));
});

const order = reactive({
  order_number: '',
  customer_id: null,
  order_date: new Date().toISOString().split('T')[0],
  items: [
    {
      name: 'MacBook Air M3 15 inch (16GB/512GB)',
      quantity: 2,
      unit_price: 32990000
    }
  ]
});

watch(
  () => props.visible,
  (val) => {
    if (val) {
      order.order_number = `SO-2026-${Math.floor(1000 + Math.random() * 9000)}`;
      if (props.customers.length > 0) {
        order.customer_id = props.customers[0].id;
      }
    }
  }
);

const totalAmount = computed(() => {
  return order.items.reduce((sum, item) => {
    return sum + (item.quantity || 0) * (item.unit_price || 0);
  }, 0);
});
</script>
