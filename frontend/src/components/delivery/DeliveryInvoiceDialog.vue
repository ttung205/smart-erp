<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Lập Chứng Từ Bán Hàng Từ Yêu Cầu Giao: #${deliveryRequest?.id || ''}`"
    :style="{ width: '840px' }"
    class="rounded-[8px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="deliveryRequest" class="flex flex-col gap-4 pt-1">
      <!-- 1. Header Context -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">ID yêu cầu:</span>
          <span class="font-bold text-sky-700 font-mono">#{{ deliveryRequest.id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Số đơn hàng:</span>
          <span class="font-bold text-slate-800 font-mono">{{ deliveryRequest.customer_order_number || deliveryRequest.order_number || deliveryRequest.order_id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Khách hàng:</span>
          <span class="font-bold text-slate-800">{{ deliveryRequest.customer_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Kho xuất:</span>
          <span class="font-semibold text-slate-700">{{ deliveryRequest.warehouse_name || 'Kho Tổng Miền Bắc' }}</span>
        </div>
      </div>

      <!-- 2. Form chứng từ -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Mã chứng từ / Hóa đơn <span class="text-red-500">*</span></label>
          <InputText
            v-model="form.invoice_number"
            class="w-full rounded-[6px]"
            size="small"
            placeholder="INV-2026-XXXX"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Ngày lập chứng từ</label>
          <InputText
            v-model="form.invoice_date"
            type="date"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Điều khoản thanh toán</label>
          <Select
            v-model="form.payment_terms"
            :options="paymentTermsOptions"
            placeholder="Chọn điều khoản..."
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>
      </div>

      <!-- 3. Bảng các mặt hàng xuất hóa đơn -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 text-xs font-semibold text-slate-700">
          Danh Sách Mặt Hàng Xuất Hóa Đơn & Lệnh Xuất Kho
        </div>

        <DataTable
          :value="form.items"
          showGridlines
          responsiveLayout="scroll"
          class="clean-datatable"
        >
          <Column header="#" style="width: 40px" class="text-center">
            <template #body="{ index }">
              <span class="font-mono text-slate-400">{{ index + 1 }}</span>
            </template>
          </Column>

          <Column field="sku" header="SKU" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-sky-700">{{ data.sku || data.product_sku }}</span>
            </template>
          </Column>

          <Column field="product_name" header="Tên mặt hàng"></Column>

          <Column header="Số lượng xuất" style="width: 110px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-slate-800">{{ data.quantity }}</span>
            </template>
          </Column>

          <Column header="Đơn giá (VNĐ)" style="width: 130px" class="text-right">
            <template #body="{ data }">
              <span class="font-mono text-xs">{{ formatCurrency(data.unit_price) }}</span>
            </template>
          </Column>

          <Column header="Thành tiền" style="width: 140px" class="text-right">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-indigo-700">
                {{ formatCurrency((data.quantity || 0) * (data.unit_price || 0)) }}
              </span>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- 4. Tổng kết tiền hóa đơn -->
      <div class="flex justify-end p-2 bg-slate-50 border border-slate-200 rounded-[6px] text-xs font-mono">
        <div class="flex gap-4">
          <span class="text-slate-500">Tổng giá trị chứng từ:</span>
          <strong class="text-sm font-bold text-indigo-700">{{ formatCurrency(calculatedTotal) }} VNĐ</strong>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 pt-2">
        <Button
          label="Hủy"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Xác Nhận Lập Chứng Từ Bán"
          icon="pi pi-check"
          size="small"
          class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 rounded-[6px]"
          @click="submitInvoice"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  deliveryRequest: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:visible', 'create-invoice']);

const paymentTermsOptions = ['Thanh toán ngay (Tiền mặt / CK)', 'Công nợ 15 ngày', 'Công nợ 30 ngày', 'Công nợ 45 ngày'];

const form = ref({
  invoice_number: '',
  invoice_date: '',
  payment_terms: 'Công nợ 30 ngày',
  items: []
});

const generateRandomInvoiceNumber = () => {
  const rand = Math.floor(100 + Math.random() * 900);
  return `INV-2026-${rand}`;
};

watch(
  () => props.deliveryRequest,
  (newDr) => {
    if (newDr) {
      const today = new Date().toISOString().split('T')[0];
      form.value = {
        invoice_number: generateRandomInvoiceNumber(),
        invoice_date: today,
        payment_terms: 'Công nợ 30 ngày',
        items: (newDr.items || []).map((it) => ({
          sku: it.sku || it.product_sku,
          product_name: it.product_name,
          quantity: it.quantity,
          unit_price: it.unit_price || 0
        }))
      };
    }
  },
  { immediate: true }
);

const calculatedTotal = computed(() => {
  return form.value.items.reduce((acc, it) => acc + (it.quantity || 0) * (it.unit_price || 0), 0);
});

const formatCurrency = (val) => {
  return new Intl.NumberFormat('vi-VN').format(val || 0);
};

const submitInvoice = () => {
  if (!form.value.invoice_number) {
    alert('Vui lòng nhập mã chứng từ hóa đơn!');
    return;
  }

  const payload = {
    invoice_number: form.value.invoice_number,
    delivery_request_number: props.deliveryRequest.request_number || props.deliveryRequest.id,
    order_number: props.deliveryRequest.order_number || props.deliveryRequest.order_id,
    customer_name: props.deliveryRequest.customer_name,
    warehouse_name: props.deliveryRequest.warehouse_name,
    invoice_date: form.value.invoice_date,
    payment_terms: form.value.payment_terms,
    total_amount: calculatedTotal.value,
    items: form.value.items
  };

  emit('create-invoice', payload);
  emit('update:visible', false);
};
</script>
