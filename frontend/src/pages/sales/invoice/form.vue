<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Chi Tiết Chứng Từ Bán Hàng: ${invoice?.invoice_number || ''}`"
    :style="{ width: '800px' }"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="invoice" class="flex flex-col gap-4 pt-1">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span class="text-slate-500 block">Mã chứng từ:</span>
          <span class="font-bold text-indigo-700 font-mono text-sm">{{ invoice.invoice_number }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Đơn bán hàng:</span>
          <span class="font-bold text-slate-800 font-mono">{{ invoice.order_id }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Kho xuất:</span>
          <span class="font-semibold text-slate-700">{{ invoice.warehouse_name }}</span>
        </div>
        <div>
          <span class="text-slate-500 block">Hạn thanh toán:</span>
          <span class="font-medium text-slate-700">{{ invoice.payment_terms }}</span>
        </div>
      </div>

      <!-- Danh sách mặt hàng -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 text-xs font-semibold text-slate-700">
          Danh Sách Mặt Hàng Theo Chứng Từ
        </div>
        <DataTable
          :value="invoice.items"
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
              <span class="font-mono text-xs font-bold text-slate-900">{{ data.quantity }}</span>
            </template>
          </Column>

          <Column header="Đơn Giá" class="text-right" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-700">{{ formatCurrency(data.unit_price) }}</span>
            </template>
          </Column>

          <Column header="Thành Tiền" class="text-right" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-indigo-700">{{ formatCurrency(data.quantity * data.unit_price) }}</span>
            </template>
          </Column>
        </DataTable>
      </div>

      <div class="flex justify-between items-center bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div>
          <span>Trạng thái kế toán: </span>
          <strong :class="invoice.accounting_status === 'POSTED' ? 'text-emerald-700' : 'text-amber-700'">
            {{ invoice.accounting_status === 'POSTED' ? 'Đã ghi sổ kế toán (Đã trừ tồn kho)' : 'Chưa ghi sổ (Chưa trừ tồn kho)' }}
          </strong>
        </div>
        <div class="text-right">
          <span class="text-slate-500 mr-2">Tổng giá trị:</span>
          <span class="text-base font-bold font-mono text-indigo-700">{{ formatCurrency(invoice.total_amount) }}</span>
        </div>
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
import { formatCurrency } from '../../../utils/format';

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
</script>
