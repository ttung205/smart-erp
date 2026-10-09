<template>
  <Dialog
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    modal
    :header="`Chi Tiết Lệnh Xuất Kho: ${invoice?.invoice_number || ''}`"
    :style="{ width: '850px' }"
    class="rounded-[8px]"
  >
    <div v-if="invoice" class="space-y-3 pt-1">
      <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div><span class="text-slate-500 block">Mã chứng từ:</span><strong class="font-mono text-indigo-700">{{ invoice.invoice_number }}</strong></div>
        <div><span class="text-slate-500 block">Đơn bán hàng:</span><strong class="font-mono text-slate-800">{{ invoice.customer_order_number || invoice.order_number }}</strong></div>
        <div><span class="text-slate-500 block">Khách hàng:</span><strong class="text-slate-800">{{ invoice.customer_name }}</strong></div>
        <div><span class="text-slate-500 block">Kho xuất:</span><strong class="text-slate-700">{{ invoice.warehouse_name }}</strong></div>
      </div>

      <!-- Thông tin điều phối kho -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3 bg-white border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block text-[11px]">Nhân viên soạn hàng:</span>
          <strong class="text-slate-800">{{ invoice.picker_name || 'Chưa phân công' }}</strong>
        </div>
        <div>
          <span class="text-slate-500 block text-[11px]">Người đóng gói:</span>
          <strong class="text-slate-800">{{ invoice.packer_name || 'Chưa phân công' }}</strong>
        </div>
        <div>
          <span class="text-slate-500 block text-[11px]">Phương thức & Vận chuyển:</span>
          <strong class="text-slate-800">{{ getShippingMethodLabel(invoice.shipping_method) }}</strong>
          <span v-if="invoice.shipper_name" class="block text-[11px] text-slate-600">
            {{ invoice.shipper_name }} {{ invoice.shipping_note ? ' - ' + invoice.shipping_note : '' }}
          </span>
        </div>
      </div>

      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <DataTable :value="invoice.items" showGridlines class="clean-datatable text-xs" responsiveLayout="scroll">
          <Column field="sku" header="SKU" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono font-semibold text-sky-700">{{ data.sku }}</span>
            </template>
          </Column>
          <Column field="product_name" header="Tên Mặt Hàng"></Column>
          <Column field="description" header="Mô Tả Chi Tiết">
            <template #body="{ data }">
              <span class="text-slate-600 italic">{{ data.description || '---' }}</span>
            </template>
          </Column>
          <Column header="SL Yêu Cầu" style="width: 100px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono text-slate-600">{{ data.requested_quantity || data.quantity }}</span>
            </template>
          </Column>
          <Column header="SL Xuất Kho" style="width: 110px" class="text-center">
            <template #body="{ data }">
              <span class="font-mono font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-200">
                {{ data.quantity }}
              </span>
            </template>
          </Column>
        </DataTable>
      </div>
    </div>
    <template #footer>
      <Button label="Đóng" text size="small" severity="secondary" @click="$emit('update:visible', false)" />
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
  invoice: {
    type: Object,
    default: null
  }
});

defineEmits(['update:visible']);

const getShippingMethodLabel = (method) => {
  switch (method) {
    case 'WAREHOUSE_DELIVERY':
      return 'Kho Tự Vận Chuyển';
    case 'THIRD_PARTY_CARRIER':
      return 'Gửi Hãng Chuyển Phát';
    case 'CUSTOMER_PICKUP':
      return 'Khách Tự Lấy Tại Kho';
    default:
      return 'Chưa Chọn Phương Thức';
  }
};
</script>
