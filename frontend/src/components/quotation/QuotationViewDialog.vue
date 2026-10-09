<template>
  <Dialog :visible="visible" modal :header="`Xem Báo Giá: ${quotation?.quotation_number || ''}`"
    :style="{ width: '1050px' }" class="rounded-[6px]" @update:visible="$emit('update:visible', $event)">
    <div v-if="quotation" class="flex flex-col gap-3.5 pt-1 text-xs">
      <!-- 1. Header Info Cards -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-2.5 bg-slate-50 border border-slate-200 rounded-[6px] p-3">
        <div>
          <span class="text-slate-400 block text-[11px]">Khách hàng:</span>
          <strong class="text-slate-800 text-xs">{{ quotation.customer_name }}</strong>
        </div>
        <div>
          <span class="text-slate-400 block text-[11px]">Người liên hệ:</span>
          <strong class="text-slate-700 text-xs">{{ quotation.contact_name || 'Mặc định' }}</strong>
        </div>
        <div>
          <span class="text-slate-400 block text-[11px]">Ngày tạo báo giá:</span>
          <span class="font-mono text-slate-700">{{ formatDate(quotation.created_at || quotation.quotation_date)
            }}</span>
        </div>
        <div>
          <span class="text-slate-400 block text-[11px]">Trạng thái:</span>
          <Badge :severity="getStatusMeta(quotation.status).severity" :value="getStatusMeta(quotation.status).label" />
        </div>
      </div>

      <!-- 2. Danh sách mặt hàng -->
      <div class="border border-slate-200 rounded-[6px] overflow-hidden">
        <DataTable :value="quotation.items" showGridlines class="clean-datatable" responsiveLayout="scroll">
          <Column header="#" style="width: 40px" class="text-center">
            <template #body="{ index }">
              <span class="font-mono text-slate-400">{{ index + 1 }}</span>
            </template>
          </Column>

          <Column field="sku" header="Internal Code (SKU)" style="width: 150px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-semibold text-sky-700">{{ data.sku }}</span>
            </template>
          </Column>

          <Column field="designation" header="Designation" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-indigo-700">{{ data.designation }}</span>
            </template>
          </Column>

          <Column field="brand" header="Brand" style="width: 90px"></Column>

          <Column field="description" header="Mô tả sản phẩm"></Column>

          <Column header="Số Lượng" class="text-right" style="width: 80px">
            <template #body="{ data }">
              <span class="font-mono font-bold">{{ data.quantity }}</span>
            </template>
          </Column>

          <Column header="Đơn Giá Bán" class="text-right" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs text-slate-800">{{ formatCurrency(data.selling_price) }}</span>
            </template>
          </Column>

          <Column header="Thành Tiền" class="text-right" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-sky-700">{{ formatCurrency(data.subtotal || (data.quantity *
                data.selling_price)) }}</span>
            </template>
          </Column>
        </DataTable>
      </div>

      <!-- 3. Tổng kết -->
      <div class="flex justify-between items-center bg-slate-50 border border-slate-200 rounded-[6px] p-3">
        <div class="text-slate-500">
          <span>Người phụ trách: <strong class="text-slate-700">{{ quotation.created_by }}</strong></span>
        </div>
        <div class="text-right">
          <span class="text-slate-500 mr-2">Tổng giá trị báo giá (đã gồm VAT):</span>
          <span class="text-base font-bold font-mono text-sky-700">{{ formatCurrency(quotation.total_amount) }}</span>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-between items-center w-full">
        <Button label="Đóng" icon="pi pi-times" text size="small" severity="secondary" class="rounded-[6px]"
          @click="$emit('update:visible', false)" />

        <div class="flex gap-2">
          <Button label="Xem & Xuất PDF" icon="pi pi-file-pdf" size="small" severity="secondary" outlined
            class="rounded-[6px] !text-xs !py-1 !px-2.5" @click="showPdfPreview = true" />
          <Button label="Chỉnh sửa báo giá" icon="pi pi-pencil" size="small" severity="info" outlined
            class="rounded-[6px] !text-xs !py-1 !px-2.5" @click="goToEdit" />
        </div>
      </div>
    </template>
  </Dialog>

  <!-- Dialog Preview & Xuất PDF / Gửi Email -->
  <QuotationPdfPreviewDialog
    v-model:visible="showPdfPreview"
    :quotation="quotation"
  />
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import QuotationPdfPreviewDialog from './QuotationPdfPreviewDialog.vue';
import { formatCurrency, formatDate } from '../../utils/format';

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  quotation: {
    type: Object,
    default: null
  }
});

const emit = defineEmits(['update:visible']);
const router = useRouter();
const showPdfPreview = ref(false);

const getStatusMeta = (st) => {
  const map = {
    DRAFT: { label: 'Bản nháp', severity: 'secondary' },
    PENDING_APPROVAL: { label: 'Chờ duyệt', severity: 'warn' },
    APPROVED: { label: 'Đã duyệt', severity: 'success' },
    CONVERTED: { label: 'Đã tạo đơn', severity: 'info' }
  };
  return map[st] || { label: st, severity: 'secondary' };
};

const goToEdit = () => {
  emit('update:visible', false);
  if (props.quotation) {
    router.push(`/quotation/${props.quotation.id}/edit`);
  }
};
</script>
