<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Lập Chứng Từ Bán Hàng Từ Yêu Cầu Giao: #${deliveryRequest?.id || ''}`"
    :style="{ width: '960px' }"
    class="rounded-[8px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="deliveryRequest" class="flex flex-col gap-4 pt-1">
      <!-- 1. Header Context Banner -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Mã YC Giao Hàng:</span>
          <span class="font-bold text-sky-700 font-mono">#{{ deliveryRequest.id }} ({{ deliveryRequest.request_number || 'DR' }})</span>
        </div>
        <div>
          <span class="text-slate-500 block">Số Đơn Hàng (SO):</span>
          <span class="font-bold text-slate-800 font-mono">{{ deliveryRequest.customer_order_number || deliveryRequest.order_number || deliveryRequest.order_id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Khách Hàng:</span>
          <span class="font-bold text-slate-800">{{ deliveryRequest.customer_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Kho Xuất Hàng:</span>
          <span class="font-semibold text-slate-700">{{ deliveryRequest.warehouse_name || 'Kho Tổng Miền Bắc (Hà Nội)' }}</span>
        </div>
      </div>

      <!-- 2. Form Thông Tin Chứng Từ Bán Hàng -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-3 space-y-3">
        <div class="text-xs font-bold text-indigo-900 uppercase tracking-wide flex items-center gap-1.5 border-b border-slate-100 pb-2">
          <i class="pi pi-file-edit text-indigo-600"></i>
          <span>Thông Tin Chứng Từ Bán Hàng</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <!-- Số chứng từ -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-slate-700">
              Số chứng từ
            </label>
            <InputText
              v-model="form.invoice_number"
              readonly
              class="w-full rounded-[6px] bg-slate-50 font-mono text-xs font-bold text-indigo-700"
              size="small"
              placeholder="Tự sinh khi lưu (INV-mã khách-ngày)"
            />
          </div>

          <!-- Ngày chứng từ (Ngày tạo chứng từ) -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-slate-700">
              Ngày chứng từ (Ngày tạo) <span class="text-rose-500">*</span>
            </label>
            <InputText
              v-model="form.invoice_date"
              type="date"
              class="w-full rounded-[6px] text-xs font-mono"
              size="small"
            />
          </div>

          <!-- Người tạo chứng từ -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-slate-700">
              Người tạo chứng từ <span class="text-rose-500">*</span>
            </label>
            <InputText
              v-model="form.created_by"
              class="w-full rounded-[6px] text-xs"
              size="small"
              placeholder="Tên người tạo..."
            />
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <!-- Đơn vị tiền tệ (Lấy từ đơn hàng) -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-slate-700">
              Đơn vị tiền tệ (Lấy từ đơn)
            </label>
            <Select
              v-model="form.currency"
              :options="currencyOptions"
              class="w-full rounded-[6px] text-xs font-mono font-semibold"
              size="small"
            />
          </div>

          <!-- Tỉ giá (Lấy từ đơn hàng, cho phép sửa) -->
          <div class="flex flex-col gap-1">
            <div class="flex items-center justify-between">
              <label class="text-xs font-semibold text-slate-700">
                Tỉ giá quy đổi
              </label>
              <span class="text-[10px] text-slate-400">Cho phép điều chỉnh</span>
            </div>
            <InputNumber
              v-model="form.exchange_rate"
              :min="0.0001"
              :maxFractionDigits="4"
              class="w-full rounded-[6px] text-xs font-mono"
              size="small"
              placeholder="1.0"
            />
          </div>

          <!-- Điều khoản thanh toán -->
          <div class="flex flex-col gap-1">
            <label class="text-xs font-semibold text-slate-700">
              Điều khoản thanh toán
            </label>
            <Select
              v-model="form.payment_terms"
              :options="paymentTermsOptions"
              class="w-full rounded-[6px] text-xs"
              size="small"
            />
          </div>
        </div>

        <!-- Ghi chú chứng từ -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Ghi chú chứng từ</label>
          <InputText
            v-model="form.notes"
            class="w-full rounded-[6px] text-xs"
            size="small"
            placeholder="Nhập ghi chú hoặc yêu cầu giao nhận đặc biệt..."
          />
        </div>
      </div>

      <!-- 3. Bảng Chi Tiết Mặt Hàng (Chỉ cho phép sửa Description và Quantity xuất) -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-2 border-b border-slate-200 text-xs font-semibold text-slate-800 flex justify-between items-center">
          <div class="flex items-center gap-2">
            <i class="pi pi-list text-slate-600"></i>
            <span>Bảng Mặt Hàng Xuất Hóa Đơn (Chỉ cho chỉnh sửa Mô tả & Số lượng xuất)</span>
          </div>
          <span class="text-[11px] text-indigo-700 font-normal bg-indigo-50 border border-indigo-200 px-2 py-0.5 rounded">
            Khởi tạo từ số lượng yêu cầu giao hàng
          </span>
        </div>

        <DataTable
          :value="form.items"
          showGridlines
          responsiveLayout="scroll"
          class="clean-datatable text-xs"
        >
          <Column header="#" style="width: 40px" class="text-center">
            <template #body="{ index }">
              <span class="font-mono text-slate-400">{{ index + 1 }}</span>
            </template>
          </Column>

          <Column field="sku" header="SKU" style="width: 120px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-sky-700">{{ data.sku }}</span>
            </template>
          </Column>

          <Column field="product_name" header="Tên Sản Phẩm" style="min-width: 160px">
            <template #body="{ data }">
              <span class="font-semibold text-slate-800">{{ data.product_name }}</span>
            </template>
          </Column>

          <!-- CỘT 1 ĐƯỢC PHÉP ĐIỀU CHỈNH: Item Description -->
          <Column header="Mô Tả Chi Tiết (Description)" style="min-width: 200px">
            <template #body="{ data }">
              <InputText
                v-model="data.description"
                placeholder="Mô tả / ghi chú mặt hàng..."
                class="w-full text-xs rounded py-1 px-2 border-slate-300 focus:border-indigo-500"
                size="small"
              />
            </template>
          </Column>

          <Column field="unit" header="ĐVT" style="width: 70px" class="text-center">
            <template #body="{ data }">
              <span class="text-slate-600">{{ data.unit || 'Cái' }}</span>
            </template>
          </Column>

          <!-- Cột Yêu Cầu Xuất ở Yêu Cầu Giao Hàng (Readonly) -->
          <Column header="Yêu Cầu Xuất" style="width: 110px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded">
                {{ data.requested_quantity }}
              </span>
            </template>
          </Column>

          <!-- CỘT 2 ĐƯỢC PHÉP ĐIỀU CHỈNH: Số lượng xuất thực tế trên chứng từ -->
          <Column header="SL Xuất Thực Tế" style="width: 120px" class="text-center">
            <template #body="{ data }">
              <InputNumber
                v-model="data.quantity"
                :min="0"
                :max="data.requested_quantity * 2"
                :maxFractionDigits="2"
                class="w-full text-center font-mono font-bold text-indigo-700"
                inputClass="text-center font-mono font-bold text-indigo-700 py-1 px-1 text-xs"
                size="small"
              />
            </template>
          </Column>

          <Column header="Đơn Giá" style="width: 120px" class="text-right">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-700">{{ formatCurrency(data.unit_price) }}</span>
            </template>
          </Column>

          <Column header="Thành Tiền" style="width: 130px" class="text-right">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-indigo-700">
                {{ formatCurrency((data.quantity || 0) * (data.unit_price || 0)) }}
              </span>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- 4. Banner Quy Tắc Tồn Kho & Tổng Cộng -->
      <div class="flex flex-col md:flex-row justify-between items-center gap-3 p-3 bg-slate-50 border border-slate-200 rounded-[6px] text-xs">
        <div class="flex items-center gap-2 text-slate-600">
          <i class="pi pi-info-circle text-sky-600 text-sm"></i>
          <span>
            <strong>Quy tắc tồn kho:</strong> Khi tạo chứng từ, hệ thống sẽ <strong>giữ chỗ tồn kho</strong> (Reserve). Khi phòng Logistics ghi sổ xác nhận hàng ra khỏi kho mới <strong>trừ tồn thật</strong>.
          </span>
        </div>
        <div class="flex items-center gap-3 shrink-0">
          <span class="text-slate-500">Tổng giá trị chứng từ:</span>
          <strong class="text-base font-bold font-mono text-indigo-700">
            {{ formatCurrency(calculatedTotal) }}
          </strong>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center pt-2">
        <Button
          label="Đóng / Hủy"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />

        <div class="flex gap-2">
          <!-- Nút Lưu Chứng Từ (Chờ gửi kho) -->
          <Button
            label="Lưu Chứng Từ (Chờ Gửi Kho)"
            icon="pi pi-save"
            size="small"
            class="!bg-slate-700 hover:!bg-slate-800 !text-white !border-slate-700 rounded-[6px] !text-xs !font-semibold"
            :loading="submitting"
            @click="handleSubmit(false)"
          />

          <!-- Nút Lưu & Gửi Xuống Kho Ngay -->
          <Button
            label="Lưu & Gửi Xuống Kho"
            icon="pi pi-send"
            size="small"
            class="!bg-indigo-600 hover:!bg-indigo-700 !text-white !border-indigo-600 rounded-[6px] !text-xs !font-bold shadow-sm"
            :loading="submitting"
            @click="handleSubmit(true)"
          />
        </div>
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
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  deliveryRequest: {
    type: Object,
    default: null
  },
  currentUser: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:visible', 'create-invoice']);

const submitting = ref(false);

const paymentTermsOptions = [
  'Thanh toán ngay (Tiền mặt / CK)',
  'Công nợ 15 ngày',
  'Công nợ 30 ngày',
  'Công nợ 45 ngày',
  'Công nợ 60 ngày'
];

const currencyOptions = ['VND', 'USD', 'EUR', 'JPY'];

const form = ref({
  invoice_number: '',
  invoice_date: '',
  currency: 'VND',
  exchange_rate: 1.0,
  created_by: 'admin',
  payment_terms: 'Công nợ 30 ngày',
  notes: '',
  items: []
});

watch(
  () => [props.deliveryRequest, props.visible],
  ([newDr, isVis]) => {
    if (newDr && isVis) {
      const today = new Date().toISOString().split('T')[0];
      const user = props.currentUser || JSON.parse(localStorage.getItem('user') || '{}');
      const order = newDr.order || {};

      form.value = {
        invoice_number: '',
        invoice_date: today,
        currency: order.currency || newDr.currency || 'VND',
        exchange_rate: Number(order.exchange_rate) || 1.0,
        created_by: user.name || user.email || 'admin',
        payment_terms: order.payment_terms || 'Công nợ 30 ngày',
        notes: newDr.notes || `Xuất theo YC giao hàng #${newDr.id} (${newDr.customer_order_number || newDr.order_number || ''})`,
        items: (newDr.items || []).map((it) => {
          const reqQty = Number(it.quantity || it.request_quantity || 1);
          return {
            delivery_request_item_id: it.id,
            order_item_id: it.order_item_id,
            sku: it.sku || it.product_sku,
            product_name: it.product_name || it.sku,
            description: it.description || it.product_name || '',
            unit: it.unit || it.orderItem?.unit || 'Cái',
            requested_quantity: reqQty,
            quantity: reqQty, // Khởi tạo bằng số lượng yêu cầu xuất
            unit_price: Number(it.unit_price || it.orderItem?.selling_price || 0)
          };
        })
      };
    }
  },
  { immediate: true }
);

const calculatedTotal = computed(() => {
  return form.value.items.reduce((acc, it) => acc + (Number(it.quantity) || 0) * (Number(it.unit_price) || 0), 0);
});

const handleSubmit = (sendImmediately = false) => {
  // Số chứng từ do server cấp (INV-{mã khách}-{ddmmyy}), không gửi số từ trình duyệt
  const payload = {
    delivery_request_id: props.deliveryRequest.id,
    delivery_request_number: props.deliveryRequest.request_number || props.deliveryRequest.id,
    order_number: props.deliveryRequest.order_number || props.deliveryRequest.order_id,
    customer_name: props.deliveryRequest.customer_name,
    warehouse_name: props.deliveryRequest.warehouse_name,
    invoice_date: form.value.invoice_date,
    currency: form.value.currency,
    exchange_rate: form.value.exchange_rate,
    created_by: form.value.created_by,
    payment_terms: form.value.payment_terms,
    notes: form.value.notes,
    total_amount: calculatedTotal.value,
    send_to_warehouse: sendImmediately,
    items: form.value.items
  };

  emit('create-invoice', payload);
};
</script>
