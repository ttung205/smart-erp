<template>
  <Dialog
    :visible="visible"
    modal
    header="Tạo Đơn Bán Hàng Mới"
    :style="{ width: '640px' }"
    :closable="!loading"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="sales-form-body">
      <div class="form-row">
        <div class="form-field">
          <label class="field-label">Mã đơn hàng <span class="text-danger">*</span></label>
          <InputText
            v-model="order.order_number"
            class="w-full"
            size="small"
            placeholder="SO-2026-XXXX"
          />
        </div>
        <div class="form-field">
          <label class="field-label">Ngày tạo đơn</label>
          <InputText
            v-model="order.order_date"
            type="date"
            class="w-full"
            size="small"
          />
        </div>
      </div>

      <div class="form-field">
        <label class="field-label">Khách hàng B2B <span class="text-danger">*</span></label>
        <Select
          v-model="order.customer_id"
          :options="customerOptions"
          optionLabel="name"
          optionValue="id"
          placeholder="Chọn khách hàng doanh nghiệp..."
          class="w-full"
          size="small"
        />
      </div>

      <div class="order-items-box">
        <span class="items-header-title">Danh sách mặt hàng:</span>
        <div
          v-for="(item, idx) in order.items"
          :key="idx"
          class="item-row"
        >
          <InputText
            v-model="item.name"
            placeholder="Tên sản phẩm..."
            class="flex-2"
            size="small"
          />
          <InputNumber
            v-model="item.quantity"
            placeholder="SL"
            :min="1"
            class="w-20"
            size="small"
          />
          <InputNumber
            v-model="item.unit_price"
            placeholder="Đơn giá"
            currency="VND"
            locale="vi-VN"
            class="flex-1"
            size="small"
          />
          <div class="item-subtotal num-tabular">
            {{ formatCurrency((item.quantity || 0) * (item.unit_price || 0)) }}
          </div>
        </div>
      </div>

      <div class="order-summary-row">
        <span class="text-sm font-semibold text-body">Tổng giá trị đơn hàng:</span>
        <span class="text-lg font-bold text-primary num-tabular">{{ formatCurrency(totalAmount) }}</span>
      </div>
    </div>

    <template #footer>
      <div class="dialog-footer-actions">
        <Button
          label="Hủy bỏ"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          :disabled="loading"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Tạo đơn hàng"
          icon="pi pi-check"
          size="small"
          :loading="loading"
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

<style scoped>
.sales-form-body {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-top: 4px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-body);
}

.text-danger {
  color: #b91c1c;
}

.w-full {
  width: 100%;
}

.order-items-box {
  background-color: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.items-header-title {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-main);
}

.item-row {
  display: flex;
  align-items: center;
  gap: 8px;
}

.flex-2 {
  flex: 2;
}

.flex-1 {
  flex: 1;
}

.w-20 {
  width: 80px;
}

.item-subtotal {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-main);
  min-width: 120px;
  text-align: right;
}

.order-summary-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 8px;
  border-top: 1px solid var(--border-subtle);
}

.dialog-footer-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
</style>
