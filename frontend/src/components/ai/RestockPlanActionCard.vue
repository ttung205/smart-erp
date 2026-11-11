<template>
  <div class="mt-3 rounded-[6px] border border-slate-200 bg-white p-4 text-slate-700">
    <div class="flex items-start justify-between gap-2 border-b border-slate-200 pb-3">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-2">
          <Tag value="Trợ lý AI" severity="info" class="rounded-[6px]" />
          <span class="text-xs text-slate-500">Dữ liệu đến {{ action.as_of }} · dự trữ {{ action.target_cover_days }} ngày</span>
        </div>
        <span class="text-sm font-semibold text-slate-900">{{ action.action_title }}</span>
      </div>
      <Button icon="pi pi-times" severity="secondary" text size="small" aria-label="Đóng thẻ" @click="$emit('dismiss')" />
    </div>

    <div v-if="!groups.length" class="pt-3">
      <Message severity="warn" :closable="false">
        Các mã cần nhập đều chưa từng có đơn mua nên chưa xác định được nhà cung cấp. Hãy lập PO tại màn hình Đơn mua hàng.
      </Message>
    </div>

    <div v-else class="flex flex-col gap-4 pt-3">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="flex flex-col gap-2">
          <label class="text-xs text-slate-500">Nhà cung cấp</label>
          <Select v-model="selectedKey" :options="supplierOptions" option-label="label" option-value="value" size="small" class="w-full" />
        </div>
        <div class="flex flex-col gap-2">
          <label class="text-xs text-slate-500">Kho nhận hàng</label>
          <Select v-model="warehouseId" :options="sellableWarehouses" option-label="label" option-value="value" size="small" class="w-full" />
        </div>
      </div>

      <DataTable :value="quantities" size="small" data-key="product_id" class="text-xs">
        <Column header="Sản phẩm">
          <template #body="{ data }">
            <div class="flex flex-col">
              <span class="font-medium text-slate-900">{{ data.sku }}</span>
              <span class="text-slate-500">{{ data.product_name }}</span>
            </div>
          </template>
        </Column>
        <Column header="Mức độ">
          <template #body="{ data }">
            <Tag :value="data.urgency === 'urgent' ? 'Khẩn cấp' : 'Cảnh báo'" :severity="data.urgency === 'urgent' ? 'danger' : 'warn'" class="rounded-[6px]" />
          </template>
        </Column>
        <Column header="Đủ bán" class="text-right">
          <template #body="{ data }">{{ data.cover_days }} ngày</template>
        </Column>
        <Column header="SL đặt">
          <template #body="{ data }">
            <InputNumber
              v-model="data.quantity"
              :min="0"
              :disabled="isCreated"
              size="small"
              input-class="w-20 text-right"
              :aria-label="`Số lượng đặt ${data.sku}`"
            />
            <div class="pt-1 text-slate-500">Đề xuất {{ data.suggested_quantity }} {{ data.unit }}</div>
          </template>
        </Column>
        <Column header="Đơn giá" class="text-right">
          <template #body="{ data }">{{ formatMoney(data.unit_cost) }}</template>
        </Column>
        <Column header="Thành tiền" class="text-right">
          <template #body="{ data }">{{ formatMoney(data.unit_cost * (data.quantity || 0)) }}</template>
        </Column>
      </DataTable>

      <div class="flex flex-col items-end gap-1 text-xs">
        <span>Tạm tính: <strong class="text-slate-900">{{ formatMoney(subtotal) }}</strong></span>
        <span>Thuế GTGT {{ TAX_RATE }}%: {{ formatMoney(subtotal * TAX_RATE / 100) }}</span>
        <span class="text-sm">Tổng cộng: <strong class="text-slate-900">{{ formatMoney(subtotal * (1 + TAX_RATE / 100)) }}</strong></span>
      </div>

      <Message v-if="action.unassigned?.length" severity="secondary" :closable="false">
        Chưa có lịch sử mua nên chưa gom vào PO: {{ action.unassigned.map((u) => `${u.sku} (${u.suggested_quantity})`).join(', ') }}.
      </Message>

      <Message v-if="isCreated" severity="success" :closable="false">
        Đã tạo đơn mua nháp {{ created[selectedKey] }} cho {{ selectedGroup.supplier_name }}.
        <router-link to="/purchase/orders" class="font-medium text-indigo-600">Mở danh sách đơn mua</router-link>
      </Message>

      <Message v-if="!action.can_execute" severity="warn" :closable="false" icon="pi pi-lock">
        Tài khoản chưa có quyền purchase.create nên chỉ xem được kế hoạch. Liên hệ quản trị để được cấp quyền tạo đơn mua.
      </Message>

      <div class="flex justify-end gap-2 border-t border-slate-200 pt-3">
        <Button label="Bỏ qua" severity="secondary" outlined size="small" @click="$emit('dismiss')" />
        <Button
          :label="`Tạo PO nháp cho ${selectedGroup.supplier_code}`"
          icon="pi pi-shopping-cart"
          size="small"
          :loading="submitting"
          :disabled="!action.can_execute || isCreated || !orderLines.length || !warehouseId"
          @click="createOrder"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputNumber from 'primevue/inputnumber';
import Message from 'primevue/message';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { purchaseApi } from '../../api/purchase.api';
import { useWarehouses } from '../../composables/useWarehouses';

const props = defineProps({
  action: { type: Object, required: true }
});
defineEmits(['dismiss']);

const TAX_RATE = 10;
const toast = useToast();
const { warehouses, fetchWarehouses } = useWarehouses();

const groupKey = (g) => `${g.supplier_id}-${g.currency}`;
const groups = computed(() => props.action.groups || []);
const selectedKey = ref(groups.value[0] ? groupKey(groups.value[0]) : null);
const selectedGroup = computed(() => groups.value.find((g) => groupKey(g) === selectedKey.value) || {});
// Mã PO đã tạo theo từng nhóm NCC: tạo xong nhóm này vẫn chọn được nhóm khác
const created = reactive({});
const isCreated = computed(() => Boolean(created[selectedKey.value]));
const submitting = ref(false);
const warehouseId = ref(null);

// Số lượng người dùng chỉnh, giữ riêng theo từng nhóm để đổi NCC qua lại không mất
const edited = reactive(Object.fromEntries(groups.value.map((g) => [
  groupKey(g), g.lines.map((line) => ({ ...line, quantity: line.suggested_quantity }))
])));
const quantities = computed(() => edited[selectedKey.value] || []);
const orderLines = computed(() => quantities.value.filter((line) => line.quantity > 0));
const subtotal = computed(() => orderLines.value.reduce((sum, line) => sum + line.unit_cost * line.quantity, 0));

const supplierOptions = computed(() => groups.value.map((g) => ({
  value: groupKey(g),
  label: `${g.supplier_code} · ${g.supplier_name} · ${g.lines.length} mã · ${g.currency}${created[groupKey(g)] ? ' · đã tạo PO' : ''}`
})));

// Hàng mua về nhập vào kho được phép bán
const sellableWarehouses = computed(() => warehouses.value
  .filter((w) => w.is_active && w.can_sell)
  .map((w) => ({ value: w.id, label: `${w.name} (${w.code})` })));

watch(sellableWarehouses, (list) => {
  if (!warehouseId.value && list.length) warehouseId.value = list[0].value;
}, { immediate: true });

onMounted(() => fetchWarehouses());

function formatMoney(value) {
  const currency = selectedGroup.value.currency || 'VND';
  return new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency,
    maximumFractionDigits: currency === 'VND' ? 0 : 2
  }).format(value || 0);
}

async function createOrder() {
  const group = selectedGroup.value;
  submitting.value = true;
  try {
    const res = await purchaseApi.createOrder({
      supplier_id: Number(group.supplier_id),
      warehouse_id: Number(warehouseId.value),
      order_date: new Date().toISOString(),
      currency: group.currency,
      payment_terms: group.payment_terms,
      notes: `Lập từ kế hoạch nhập kho của Trợ lý AI (dữ liệu đến ${props.action.as_of})`,
      items: orderLines.value.map((line) => ({
        product_id: Number(line.product_id),
        sku: line.sku,
        product_name: line.product_name,
        unit: line.unit,
        unit_cost: Number(line.unit_cost),
        quantity_ordered: Number(line.quantity),
        tax_rate: TAX_RATE
      }))
    });
    const order = res?.data || res;
    created[selectedKey.value] = order?.code || `#${order?.id}`;
    toast.add({
      severity: 'success',
      summary: 'Đã tạo đơn mua nháp',
      detail: `Đơn ${created[selectedKey.value]} gồm ${orderLines.value.length} mã hàng cho ${group.supplier_name}.`,
      life: 5000
    });
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: 'Không tạo được đơn mua',
      detail: error.message || `Tạo đơn mua cho ${group.supplier_name} thất bại. Kiểm tra quyền và thử lại.`,
      life: 6000
    });
  } finally {
    submitting.value = false;
  }
}
</script>
