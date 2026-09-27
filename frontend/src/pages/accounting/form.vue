<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Xác Nhận Ghi Sổ Kế Toán: ${invoice?.invoice_number || ''}`"
    :style="{ width: '760px' }"
    class="rounded-[6px]"
    @update:visible="$emit('update:visible', $event)"
  >
    <div v-if="invoice" class="flex flex-col gap-4 pt-1">
      <!-- Cảnh báo nghiệp vụ ghi sổ -->
      <div class="bg-red-50 border border-red-200 rounded-[6px] p-3 text-xs text-red-900 flex items-start gap-2.5">
        <i class="pi pi-exclamation-triangle text-red-600 text-lg mt-0.5 shrink-0"></i>
        <div>
          <span class="font-bold text-red-800 uppercase tracking-wide block mb-1">
            Quy trình Ghi Sổ & Khóa Chứng Từ (Post General Ledger)
          </span>
          <p class="m-0 leading-relaxed text-red-700">
            Hành động này sẽ <strong>chính thức trừ tồn kho vật lý (On-Hand)</strong> trên <code>Inventory Service (:8004)</code> cho kho <strong>{{ invoice.warehouse_name }}</strong>, đồng thời khóa chứng từ kế toán vĩnh viễn. Không thể hoàn tác sau khi đã ghi sổ.
          </p>
        </div>
      </div>

      <!-- Thông tin đối soát -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-slate-50 border border-slate-200 rounded-[6px] p-3 text-xs">
        <div class="space-y-1.5">
          <div><span class="text-slate-500">Mã chứng từ:</span> <strong class="font-mono text-indigo-700 ml-1">{{ invoice.invoice_number }}</strong></div>
          <div><span class="text-slate-500">Đơn bán hàng:</span> <strong class="font-mono text-slate-800 ml-1">{{ invoice.order_id }}</strong></div>
          <div><span class="text-slate-500">Khách hàng:</span> <strong class="text-slate-800 ml-1">{{ invoice.customer_name }}</strong></div>
        </div>
        <div class="space-y-1.5">
          <div><span class="text-slate-500">Kho xuất trừ:</span> <strong class="text-slate-800 ml-1">{{ invoice.warehouse_name }}</strong></div>
          <div>
            <span class="text-slate-500">Tình trạng giao:</span>
            <Tag
              severity="success"
              value="Đã giao hàng thành công"
              icon="pi pi-check"
              class="text-[10px] ml-1 px-1.5 py-0.5 rounded-[4px]"
            />
          </div>
          <div>
            <span class="text-slate-500">Tổng doanh thu:</span>
            <strong class="font-mono text-emerald-700 text-sm ml-1">{{ formatCurrency(invoice.total_amount) }}</strong>
          </div>
        </div>
      </div>

      <!-- Danh sách mặt hàng trừ kho -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <div class="bg-slate-100 px-3 py-1.5 border-b border-slate-200 text-xs font-semibold text-slate-700 flex justify-between items-center">
          <span>Chi Tiết Mặt Hàng Trừ Kho Vật Lý (Issue Stock)</span>
          <span class="font-mono text-[11px] text-slate-500">API: POST /api/v1/inventory/stocks/issue</span>
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

          <Column header="Số Lượng Trừ Kho" class="text-center" style="width: 150px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-red-600 bg-red-50 border border-red-200 px-2 py-0.5 rounded-[4px]">
                -{{ data.quantity }} chiếc
              </span>
            </template>
          </Column>

          <Column header="Đơn Giá Ghi Nhận" class="text-right" style="width: 150px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-700">{{ formatCurrency(data.unit_price) }}</span>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- Ghi chú của Kế toán -->
      <div class="flex flex-col gap-1">
        <label class="text-xs font-semibold text-slate-700">Ghi chú đối soát sổ cái kế toán</label>
        <InputText
          v-model="accountingNote"
          class="w-full rounded-[6px]"
          size="small"
          placeholder="Biên bản giao nhận đã ký đầy đủ, đối soát công nợ hợp lệ, duyệt xuất kho chính thức."
        />
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
          :disabled="loading"
          class="rounded-[6px]"
          @click="$emit('update:visible', false)"
        />
        <Button
          label="Xác Nhận Ghi Sổ & Trừ Kho"
          icon="pi pi-file-edit"
          size="small"
          :loading="loading"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 rounded-[6px] !font-semibold"
          @click="confirmPost"
        />
      </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import InputText from 'primevue/inputtext';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  invoice: {
    type: Object,
    default: null
  },
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:visible', 'confirm']);

const accountingNote = ref('Biên bản giao nhận đã ký đầy đủ, đối soát công nợ hợp lệ, duyệt xuất kho chính thức.');

const confirmPost = () => {
  emit('confirm', {
    invoice: props.invoice,
    note: accountingNote.value
  });
};
</script>
