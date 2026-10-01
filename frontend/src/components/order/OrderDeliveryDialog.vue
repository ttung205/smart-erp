<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Lập Yêu Cầu Giao Hàng Từ Đơn Bán: ${order?.id || ''}`"
    :style="{ width: '800px' }"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="order" class="flex flex-col gap-4 pt-1">
      <!-- 1. Header context -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Mã đơn bán:</span>
          <span class="font-bold text-indigo-700 font-mono text-sm">{{ order.id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Khách hàng:</span>
          <span class="font-bold text-slate-800">{{ order.customer_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Nguồn gốc:</span>
          <span class="font-mono text-slate-600">{{ order.quotation_id || 'Đơn trực tiếp' }}</span>
        </div>
      </div>

      <!-- 2. Form điều phối vận chuyển -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Mã yêu cầu giao <span class="text-red-500">*</span></label>
          <InputText
            v-model="form.request_number"
            class="w-full rounded-[6px]"
            size="small"
            placeholder="DR-2026-XXXX"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Kho xuất hàng <span class="text-red-500">*</span></label>
          <Select
            v-model="form.warehouse_id"
            :options="warehouseOptions"
            optionLabel="label"
            optionValue="id"
            placeholder="Chọn kho xuất..."
            class="w-full rounded-[6px]"
            size="small"
            @change="onWarehouseChange"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Ngày hẹn giao hàng</label>
          <InputText
            v-model="form.expected_delivery_date"
            type="date"
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Số điện thoại liên hệ</label>
          <InputText
            v-model="form.contact_phone"
            class="w-full rounded-[6px]"
            size="small"
            placeholder="09xx xxx xxx"
          />
        </div>

        <div class="col-span-2 flex flex-col gap-1">
          <label class="text-xs font-semibold text-slate-700">Địa chỉ giao hàng thực tế</label>
          <InputText
            v-model="form.shipping_address"
            class="w-full rounded-[6px]"
            size="small"
            placeholder="Địa chỉ công trình / nhà máy..."
          />
        </div>
      </div>

      <!-- 3. Bảng phân phối số lượng giao -->
      <div>
        <span class="text-xs font-semibold text-slate-700 block mb-2">Chi tiết số lượng xuất kho:</span>
        <div class="border border-slate-200 rounded-[6px] overflow-hidden">
          <DataTable :value="form.items" showGridlines class="clean-datatable" responsiveLayout="scroll">
            <Column header="#" style="width: 40px" class="text-center">
              <template #body="{ index }">
                <span class="font-mono text-slate-400">{{ index + 1 }}</span>
              </template>
            </Column>

            <Column field="product_sku" header="Mã SKU" style="width: 140px">
              <template #body="{ data }">
                <span class="font-mono text-xs font-semibold text-sky-700">{{ data.product_sku }}</span>
              </template>
            </Column>

            <Column field="product_name" header="Tên mặt hàng"></Column>

            <Column field="order_quantity" header="Số lượng đơn" style="width: 100px" class="text-right">
              <template #body="{ data }">
                <span class="font-mono text-slate-600">{{ data.order_quantity }}</span>
              </template>
            </Column>

            <Column header="SL giao đợt này" style="width: 120px" class="text-right">
              <template #body="{ data }">
                <InputNumber
                  v-model="data.quantity"
                  :min="1"
                  :max="data.order_quantity"
                  inputClass="w-20 text-right text-xs py-1 px-2 border rounded"
                />
              </template>
            </Column>
          </DataTable>
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
          label="Tạo Yêu Cầu Giao Hàng"
          icon="pi pi-check"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 rounded-[6px]"
          @click="submitDeliveryRequest"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  order: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:visible', 'create-delivery-request']);

const warehouseOptions = ref([
  { id: 1, name: 'Kho Tổng Miền Bắc (Hà Nội)', label: 'WH-HN-01: Kho Tổng Hà Nội' },
  { id: 2, name: 'Kho Trung Chuyển Đà Nẵng', label: 'WH-DN-01: Kho Đà Nẵng' },
  { id: 3, name: 'Kho Phía Nam (TP. Hồ Chí Minh)', label: 'WH-HCM-01: Kho TP.HCM' }
]);

const generateRequestNumber = () => {
  const dateStr = new Date().toISOString().slice(0, 10).replace(/-/g, '');
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `DR-${dateStr}-${rand}`;
};

const form = ref({
  request_number: '',
  warehouse_id: 1,
  warehouse_name: 'Kho Tổng Miền Bắc (Hà Nội)',
  expected_delivery_date: '',
  contact_phone: '0988 123 456',
  shipping_address: 'Tòa nhà FPT, Phố Duy Tân, Dịch Vọng Hậu, Cầu Giấy, Hà Nội',
  items: []
});

watch(
  () => props.order,
  (newOrder) => {
    if (newOrder) {
      const tomorrow = new Date();
      tomorrow.setDate(tomorrow.getDate() + 2);

      form.value = {
        request_number: generateRequestNumber(),
        warehouse_id: warehouseOptions.value[0]?.id || 1,
        warehouse_name: warehouseOptions.value[0]?.name || 'Kho Tổng Miền Bắc (Hà Nội)',
        expected_delivery_date: tomorrow.toISOString().split('T')[0],
        contact_phone: '0988 123 456',
        shipping_address: 'Tòa nhà văn phòng đối tác B2B, Hà Nội',
        items: (newOrder.items || []).map((i) => ({
          product_id: i.product_id,
          product_sku: i.product_sku,
          product_name: i.product_name,
          order_quantity: i.quantity,
          quantity: i.quantity,
          unit_price: i.unit_price
        }))
      };
    }
  },
  { immediate: true }
);

const onWarehouseChange = () => {
  const found = warehouseOptions.value.find((w) => w.id === form.value.warehouse_id);
  if (found) {
    form.value.warehouse_name = found.name;
  }
};

const submitDeliveryRequest = () => {
  const payload = {
    id: form.value.request_number,
    request_number: form.value.request_number,
    order_id: props.order.id,
    customer_id: props.order.customer_id,
    customer_name: props.order.customer_name,
    warehouse_id: form.value.warehouse_id,
    warehouse_name: form.value.warehouse_name,
    request_date: new Date().toISOString().split('T')[0],
    expected_delivery_date: form.value.expected_delivery_date,
    contact_phone: form.value.contact_phone,
    shipping_address: form.value.shipping_address,
    status: 'APPROVED',
    items: form.value.items
  };

  emit('create-delivery-request', payload);
  emit('update:visible', false);
};
</script>
