<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Chi Tiết Yêu Cầu Giao Hàng: ${deliveryRequest?.id || ''}`"
    :style="{ width: '740px' }"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="deliveryRequest" class="flex flex-col gap-4 pt-1">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Lệnh giao:</span>
          <span class="font-bold text-sky-700 font-mono text-sm">{{ deliveryRequest.id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Đơn bán hàng:</span>
          <span class="font-bold text-indigo-700 font-mono">{{ deliveryRequest.order_id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Kho xuất hàng:</span>
          <span class="font-semibold text-slate-800">{{ deliveryRequest.warehouse_name }}</span>
        </div>
      </div>

      <div class="space-y-1.5 text-xs bg-slate-50 border border-slate-200 rounded-[6px] p-3">
        <div><span class="text-slate-500">Khách hàng nhận:</span> <strong class="text-slate-800 ml-1">{{ deliveryRequest.customer_name }}</strong></div>
        <div><span class="text-slate-500">Số điện thoại liên hệ:</span> <strong class="font-mono text-slate-800 ml-1">{{ deliveryRequest.contact_phone }}</strong></div>
        <div><span class="text-slate-500">Địa chỉ giao hàng:</span> <strong class="text-slate-800 ml-1">{{ deliveryRequest.shipping_address }}</strong></div>
        <div><span class="text-slate-500">Hẹn ngày giao:</span> <strong class="font-mono text-sky-700 ml-1">{{ deliveryRequest.expected_delivery_date }}</strong></div>
      </div>

      <!-- Danh sách mặt hàng -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 text-xs font-semibold text-slate-700">
          Danh Sách Mặt Hàng Xuất Giao
        </div>
        <DataTable
          :value="deliveryRequest.items"
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

          <Column header="Số Lượng Giao" class="text-center" style="width: 120px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-100">
                {{ data.quantity }} chiếc
              </span>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2">
        <Button
          label="Đóng"
          icon="pi pi-times"
          text
          size="small"
          severity="secondary"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';

defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  deliveryRequest: {
    type: Object,
    default: null
  }
});

defineEmits(['update:visible']);
</script>
