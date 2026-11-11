<template>
  <div class="mt-3 rounded-[6px] border border-slate-200 bg-white p-4 text-slate-700">
    <div class="flex items-start justify-between gap-2 border-b border-slate-200 pb-3">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <Tag value="Trợ lý AI" severity="info" class="rounded-[6px]" />
          <span class="text-xs text-slate-500">SKU {{ action.sku }}</span>
        </div>
        <span class="text-sm font-semibold text-slate-900">{{ action.product_name }}</span>
      </div>
      <Button v-if="!createdCode" icon="pi pi-times" severity="secondary" text size="small" aria-label="Đóng thẻ" @click="$emit('dismiss')" />
    </div>

    <div v-if="createdCode" class="flex flex-col gap-3 pt-3">
      <Message severity="success" :closable="false">
        Đã tạo đơn mua nháp {{ createdCode }} cho {{ selectedSupplier?.supplier_name }}.
        <router-link to="/purchase/orders" class="font-medium text-indigo-600">Mở danh sách đơn mua</router-link>
      </Message>
    </div>

    <div v-else class="flex flex-col gap-4 pt-3">
      <Message v-if="action.suggested_quantity" severity="info" :closable="false">
        Mô hình dự báo đề xuất nhập {{ action.suggested_quantity }} {{ unit }}, tính theo tốc độ bán thực tế và định mức tồn an toàn.
      </Message>

      <div class="flex flex-col gap-2">
        <label class="text-xs text-slate-500">Nhà cung cấp (theo lịch sử giá)</label>
        <Select v-model="selectedSupplierId" :options="supplierOptions" option-label="label" option-value="value" size="small" class="w-full" />
      </div>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-xs text-slate-500">Kho nhận hàng</label>
          <Select v-model="warehouseId" :options="sellableWarehouses" option-label="label" option-value="value" size="small" class="w-full" />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-xs text-slate-500">Số lượng đặt ({{ unit }})</label>
          <InputNumber v-model="quantity" :min="1" show-buttons button-layout="horizontal" size="small" input-class="w-full text-center" fluid />
        </div>
      </div>

      <div class="flex flex-col gap-2">
        <label class="text-xs text-slate-500">Ghi chú đơn mua</label>
        <InputText v-model="notes" size="small" class="w-full" />
      </div>

      <div class="flex flex-col items-end gap-1 border-t border-slate-200 pt-3 text-xs">
        <span>Đơn giá: {{ formatMoney(unitPrice) }}<template v-if="selectedSupplier?.expected_margin_pct != null"> · biên lãi kỳ vọng {{ selectedSupplier.expected_margin_pct }}%</template></span>
        <span>Thuế GTGT {{ TAX_RATE }}%: {{ formatMoney(subtotal * TAX_RATE / 100) }}</span>
        <span class="text-sm">Tổng cộng: <strong class="text-slate-900">{{ formatMoney(subtotal * (1 + TAX_RATE / 100)) }}</strong></span>
      </div>

      <Message v-if="!action.can_execute" severity="warn" :closable="false" icon="pi pi-lock">
        Tài khoản chưa có quyền purchase.create nên chỉ xem được đề xuất. Liên hệ quản trị để được cấp quyền tạo đơn mua.
      </Message>

      <div class="flex justify-end gap-2">
        <Button label="Bỏ qua" severity="secondary" outlined size="small" @click="$emit('dismiss')" />
        <Button
          label="Tạo PO nháp"
          icon="pi pi-shopping-cart"
          size="small"
          :loading="submitting"
          :disabled="!action.can_execute || !selectedSupplier || !warehouseId || !quantity"
          @click="createOrder"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { purchaseApi } from '../../api/purchase.api';
import { useWarehouses } from '../../composables/useWarehouses';

const props = defineProps({
  action: { type: Object, required: true }
});
const emit = defineEmits(['order-created', 'dismiss']);

const TAX_RATE = 10;
const toast = useToast();
const { warehouses, fetchWarehouses } = useWarehouses();

const unit = props.action.unit || 'Bộ';
const selectedSupplierId = ref(props.action.default_supplier_id || props.action.suppliers?.[0]?.supplier_id);
const quantity = ref(props.action.default_quantity || 10);
const notes = ref('Lập từ đề xuất đặt mua nhanh của Trợ lý AI');
const warehouseId = ref(null);
const submitting = ref(false);
const createdCode = ref('');

const selectedSupplier = computed(() =>
  props.action.suppliers?.find((s) => s.supplier_id === selectedSupplierId.value) || props.action.suppliers?.[0]);
const currency = computed(() => selectedSupplier.value?.currency || 'VND');
const unitPrice = computed(() => Number(selectedSupplier.value?.latest_price || 0));
const subtotal = computed(() => unitPrice.value * (quantity.value || 0));

const supplierOptions = computed(() => (props.action.suppliers || []).map((s) => ({
  value: s.supplier_id,
  label: `${s.supplier_code} · ${s.supplier_name} · ${formatMoney(s.latest_price, s.currency)}${s.is_best_price ? ' · giá tốt nhất' : ''}`
})));

// Hàng mua về nhập vào kho được phép bán
const sellableWarehouses = computed(() => warehouses.value
  .filter((w) => w.is_active && w.can_sell)
  .map((w) => ({ value: w.id, label: `${w.name} (${w.code})` })));

watch(sellableWarehouses, (list) => {
  if (!warehouseId.value && list.length) warehouseId.value = list[0].value;
}, { immediate: true });

onMounted(() => fetchWarehouses());

function formatMoney(value, code = currency.value) {
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: code || 'VND',
    maximumFractionDigits: code === 'VND' || !code ? 0 : 2
  }).format(value || 0);
}

async function createOrder() {
  const supplier = selectedSupplier.value;
  submitting.value = true;
  try {
    const res = await purchaseApi.createOrder({
      supplier_id: Number(supplier.supplier_id),
      warehouse_id: Number(warehouseId.value),
      order_date: new Date().toISOString(),
      currency: currency.value,
      payment_terms: supplier.payment_terms || 'Thỏa thuận',
      notes: notes.value,
      items: [{
        product_id: Number(props.action.product_id),
        sku: props.action.sku,
        product_name: props.action.product_name,
        unit,
        unit_cost: unitPrice.value,
        quantity_ordered: Number(quantity.value),
        tax_rate: TAX_RATE
      }]
    });
    const order = res?.data || res;
    createdCode.value = order?.code || `#${order?.id}`;
    toast.add({
      severity: 'success',
      summary: 'Đã tạo đơn mua nháp',
      detail: `Đơn ${createdCode.value}: ${quantity.value} ${unit} ${props.action.sku} từ ${supplier.supplier_name}.`,
      life: 5000
    });
    emit('order-created', order);
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Không tạo được đơn mua',
      detail: error.message || `Tạo đơn mua ${props.action.sku} thất bại. Kiểm tra quyền và thử lại.`,
      life: 6000
    });
  } finally {
    submitting.value = false;
  }
}
</script>
