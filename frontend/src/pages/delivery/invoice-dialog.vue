<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Lập Chứng Từ Bán Hàng Từ Yêu Cầu Giao: ${deliveryRequest?.id || ''}`"
    :style="{ width: '840px' }"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="deliveryRequest" class="flex flex-col gap-4 pt-1">
      <!-- 1. Header Context -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Lệnh giao hàng:</span>
          <span class="font-bold text-sky-700 font-mono">{{ deliveryRequest.id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Đơn bán hàng:</span>
          <span class="font-bold text-indigo-700 font-mono">{{ deliveryRequest.order_id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Khách hàng:</span>
          <span class="font-bold text-slate-800">{{ deliveryRequest.customer_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Kho xuất:</span>
          <span class="font-semibold text-slate-700">{{ deliveryRequest.warehouse_name }}</span>
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

      <!-- 3. Bảng mặt hàng chứng từ -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 text-xs font-semibold text-slate-700">
          Danh Sách Mặt Hàng Theo Chứng Từ
        </div>
        <DataTable
          :value="form.items"
          showGridlines
          class="clean-datatable"
          responsiveLayout="scroll"
        >
          <Column field="product_sku" header="SKU" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-slate-700">{{ data.product_sku }}</span>
            </template>
          </Column>

          <Column field="product_name" header="Tên Sản Phẩm">
            <template #body="{ data }">
              <span class="text-xs font-semibold text-slate-800">{{ data.product_name }}</span>
            </template>
          </Column>

          <Column header="Số Lượng" class="text-center" style="width: 100px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-sky-800">{{ data.quantity }}</span>
            </template>
          </Column>

          <Column header="Đơn Giá" class="text-right" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-700">{{ formatCurrency(data.unit_price) }}</span>
            </template>
          </Column>

          <Column header="Thành Tiền" class="text-right" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-indigo-700">{{ formatCurrency(data.quantity * data.unit_price) }}</span>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- 4. Chú ý nghiệp vụ & Tổng tiền -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
        <div class="bg-amber-50 border border-amber-200 rounded-[6px] p-2.5 text-xs text-amber-800 flex items-start gap-2">
          <i class="pi pi-info-circle text-amber-600 mt-0.5"></i>
          <div>
            <strong>Lưu ý nghiệp vụ kiểm soát tồn kho:</strong>
            <p class="m-0 mt-0.5">
              Sau khi lập, chứng từ sẽ được chuyển xuống Kho thực hiện nhặt hàng và bàn giao. Tồn kho <strong>chưa bị trừ</strong> cho đến khi bộ phận Kế toán kiểm tra và bấm "Ghi Sổ Kế Toán".
            </p>
          </div>
        </div>

        <div class="bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-right">
          <span class="text-xs text-slate-500 mr-2">Tổng giá trị chứng từ bán:</span>
          <span class="text-lg font-bold font-mono text-indigo-700">{{ formatCurrency(totalInvoiceAmount) }}</span>
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
          label="Phát Hành & Gửi Xuống Kho"
          icon="pi pi-send"
          size="small"
          class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 rounded-[6px] !font-semibold"
          @click="submitInvoice"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { formatCurrency } from '../../utils/format';

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

const paymentTermsOptions = ref([
  'Công nợ 30 ngày',
  'Công nợ 15 ngày',
  'Thanh toán chuyển khoản ngay',
  'Thanh toán khi nhận hàng (COD)'
]);

const generateInvoiceNumber = () => {
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `INV-2026-${rand}`;
};

const form = ref({
  invoice_number: generateInvoiceNumber(),
  invoice_date: new Date().toISOString().split('T')[0],
  payment_terms: 'Công nợ 30 ngày',
  items: []
});

watch(
  () => props.deliveryRequest,
  (newDr) => {
    if (newDr) {
      form.value = {
        invoice_number: generateInvoiceNumber(),
        invoice_date: new Date().toISOString().split('T')[0],
        payment_terms: 'Công nợ 30 ngày',
        items: (newDr.items || []).map((i) => ({
          product_id: i.product_id,
          product_sku: i.product_sku,
          product_name: i.product_name,
          quantity: i.quantity,
          unit_price: i.unit_price || 23500000
        }))
      };
    }
  },
  { immediate: true }
);

const totalInvoiceAmount = computed(() => {
  return form.value.items.reduce((acc, i) => acc + i.quantity * (i.unit_price || 0), 0);
});

const submitInvoice = () => {
  const payload = {
    id: form.value.invoice_number,
    invoice_number: form.value.invoice_number,
    delivery_request_id: props.deliveryRequest.id,
    order_id: props.deliveryRequest.order_id,
    customer_name: props.deliveryRequest.customer_name,
    warehouse_id: props.deliveryRequest.warehouse_id,
    warehouse_name: props.deliveryRequest.warehouse_name,
    invoice_date: form.value.invoice_date,
    payment_terms: form.value.payment_terms,
    total_amount: totalInvoiceAmount.value,
    warehouse_status: 'PENDING_WAREHOUSE', // PENDING_WAREHOUSE -> PICKING -> DISPATCHED -> DELIVERED
    accounting_status: 'UNPOSTED', // UNPOSTED -> POSTED
    posted_at: null,
    posted_by: null,
    items: form.value.items
  };

  emit('create-invoice', payload);
  emit('update:visible', false);
};
</script>
