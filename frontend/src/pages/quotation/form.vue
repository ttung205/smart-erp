<template>
  <Dialog
    :visible="visible"
    modal
    header="Lập Báo Giá Khách Hàng (Quotation)"
    :style="{ width: '880px' }"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div class="flex flex-col gap-4 pt-1">
      <!-- 1. Thông tin chung -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Mã báo giá <span class="text-red-500">*</span></label>
          <InputText
            v-model="form.quotation_number"
            class="w-full rounded-[6px]"
            size="small"
            placeholder="QUOT-2026-XXXX"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Khách hàng B2B <span class="text-red-500">*</span></label>
          <Select
            v-model="form.customer_id"
            :options="customerOptions"
            optionLabel="name"
            optionValue="id"
            placeholder="Chọn khách hàng..."
            class="w-full rounded-[6px]"
            size="small"
            @change="onCustomerChange"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Hiệu lực đến ngày</label>
          <InputText
            v-model="form.valid_until"
            type="date"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>
      </div>

      <!-- 2. Danh sách sản phẩm báo giá -->
      <div class="border border-slate-200 rounded-[6px] p-3 flex flex-col gap-2 bg-slate-50/50">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-800 uppercase tracking-wider">Danh Sách Mặt Hàng Báo Giá</span>
          <Button
            label="Thêm dòng hàng"
            icon="pi pi-plus"
            size="small"
            text
            class="!text-xs !py-1 !px-2 rounded-[6px] !text-sky-600 hover:!bg-sky-50"
            @click="addItemRow"
          />
        </div>

        <div class="space-y-2">
          <div
            v-for="(item, idx) in form.items"
            :key="idx"
            class="flex items-center gap-2 bg-white p-2 border border-slate-200 rounded-[6px]"
          >
            <!-- Chọn SP -->
            <div class="flex-[3]">
              <Select
                v-model="item.product_id"
                :options="productOptions"
                optionLabel="label"
                optionValue="id"
                placeholder="Chọn sản phẩm..."
                class="w-full rounded-[6px]"
                size="small"
                @change="onProductSelect(item)"
              />
            </div>

            <!-- Số lượng -->
            <div class="w-24">
              <InputNumber
                v-model="item.quantity"
                :min="1"
                placeholder="Số lượng"
                class="w-full rounded-[6px]"
                size="small"
              />
            </div>

            <!-- Đơn giá -->
            <div class="flex-[2]">
              <InputNumber
                v-model="item.unit_price"
                placeholder="Đơn giá"
                currency="VND"
                locale="vi-VN"
                class="w-full rounded-[6px]"
                size="small"
              />
            </div>

            <!-- Chiết khấu % -->
            <div class="w-20">
              <InputNumber
                v-model="item.discount_percent"
                prefix="CK "
                suffix="%"
                :min="0"
                :max="100"
                class="w-full rounded-[6px]"
                size="small"
              />
            </div>

            <!-- Thành tiền -->
            <div class="w-32 text-right font-mono font-semibold text-xs text-slate-800">
              {{ formatCurrency(calculateLineTotal(item)) }}
            </div>

            <!-- Nút xóa dòng -->
            <Button
              icon="pi pi-trash"
              text
              severity="danger"
              size="small"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              :disabled="form.items.length <= 1"
              @click="removeItemRow(idx)"
            />
          </div>
        </div>
      </div>

      <!-- 3. Ghi chú & Tổng tiền -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Điều khoản & Ghi chú</label>
          <Textarea
            v-model="form.notes"
            rows="2"
            class="w-full rounded-[6px] text-xs"
            placeholder="Ghi chú điều khoản thanh toán, giao hàng, bảo hành..."
          />
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 space-y-1.5 text-xs">
          <div class="flex justify-between text-slate-600">
            <span>Tiền hàng (chưa VAT):</span>
            <span class="font-mono font-semibold text-slate-800">{{ formatCurrency(subTotal) }}</span>
          </div>
          <div class="flex justify-between text-slate-600">
            <span>Thuế GTGT (VAT 10%):</span>
            <span class="font-mono font-semibold text-slate-800">{{ formatCurrency(vatAmount) }}</span>
          </div>
          <div class="flex justify-between border-t border-slate-200 pt-2 text-sm font-bold text-slate-900">
            <span>Tổng cộng báo giá:</span>
            <span class="font-mono text-sky-700">{{ formatCurrency(grandTotal) }}</span>
          </div>
        </div>
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
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Lưu & Phát hành Báo Giá"
          icon="pi pi-check"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
          @click="submitQuotation"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  customers: {
    type: Array,
    default: () => []
  },
  products: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:visible', 'submit']);

const customerOptions = computed(() => {
  return props.customers.map((c) => ({
    id: c.id,
    name: c.name || c.code
  }));
});

const productOptions = computed(() => {
  return props.products.map((p) => ({
    id: p.id,
    sku: p.sku,
    name: p.name,
    price: p.price || 0,
    label: `${p.sku} - ${p.name}`
  }));
});

const generateQuotationNumber = () => {
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `QUOT-2026-${rand}`;
};

const form = ref({
  quotation_number: generateQuotationNumber(),
  customer_id: null,
  customer_name: '',
  valid_until: '2026-10-30',
  notes: 'Báo giá dự án cung cấp thiết bị văn phòng. Giá đã bao gồm vận chuyển nội thành.',
  items: []
});

watch(
  () => props.visible,
  (val) => {
    if (val) {
      form.value = {
        quotation_number: generateQuotationNumber(),
        customer_id: props.customers[0]?.id || 1,
        customer_name: props.customers[0]?.name || 'Tập đoàn Công nghệ FPT',
        valid_until: '2026-10-30',
        notes: 'Báo giá đã bao gồm thuế GTGT 10% và bảo hành chính hãng 24 tháng.',
        items: [
          {
            product_id: props.products[0]?.id || 1,
            product_sku: props.products[0]?.sku || 'LAP-DELL-5520',
            product_name: props.products[0]?.name || 'Dell Precision 5520 Workstation',
            quantity: 5,
            unit_price: props.products[0]?.price || 26500000,
            discount_percent: 5,
            vat_percent: 10
          }
        ]
      };
    }
  }
);

const onCustomerChange = () => {
  const found = props.customers.find((c) => c.id === form.value.customer_id);
  if (found) {
    form.value.customer_name = found.name;
  }
};

const onProductSelect = (item) => {
  const p = props.products.find((prod) => prod.id === item.product_id);
  if (p) {
    item.product_sku = p.sku;
    item.product_name = p.name;
    item.unit_price = p.price || 25000000;
  }
};

const addItemRow = () => {
  const firstProd = props.products[0];
  form.value.items.push({
    product_id: firstProd?.id || 1,
    product_sku: firstProd?.sku || 'SKU-NEW',
    product_name: firstProd?.name || 'Sản phẩm mới',
    quantity: 1,
    unit_price: firstProd?.price || 10000000,
    discount_percent: 0,
    vat_percent: 10
  });
};

const removeItemRow = (idx) => {
  if (form.value.items.length > 1) {
    form.value.items.splice(idx, 1);
  }
};

const calculateLineTotal = (item) => {
  const qty = item.quantity || 0;
  const price = item.unit_price || 0;
  const disc = item.discount_percent || 0;
  const discounted = price * (1 - disc / 100);
  return qty * discounted;
};

const subTotal = computed(() => {
  return form.value.items.reduce((acc, item) => acc + calculateLineTotal(item), 0);
});

const vatAmount = computed(() => {
  return subTotal.value * 0.1;
});

const grandTotal = computed(() => {
  return subTotal.value + vatAmount.value;
});

const submitQuotation = () => {
  if (!form.value.customer_id) {
    alert('Vui lòng chọn khách hàng!');
    return;
  }

  const payload = {
    id: form.value.quotation_number,
    quotation_number: form.value.quotation_number,
    customer_id: form.value.customer_id,
    customer_name: form.value.customer_name,
    quotation_date: new Date().toISOString().split('T')[0],
    valid_until: form.value.valid_until,
    status: 'NEGOTIATING',
    notes: form.value.notes,
    total_amount: grandTotal.value,
    items: form.value.items.map((i, index) => ({
      id: index + 1,
      product_id: i.product_id,
      product_sku: i.product_sku,
      product_name: i.product_name,
      quantity: i.quantity,
      unit_price: i.unit_price,
      discount_percent: i.discount_percent,
      vat_percent: i.vat_percent || 10,
      total_line: calculateLineTotal(i) * 1.1,
      won: true,
      won_quantity: i.quantity
    }))
  };

  emit('submit', payload);
  emit('update:visible', false);
};
</script>
