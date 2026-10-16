<template>
  <Dialog
    :visible="visible"
    modal
    :header="`Lịch sử gross price — ${sku}`"
    :style="{ width: '760px' }"
    class="rounded-[6px]"
    @update:visible="emit('update:visible', $event)"
  >
    <div v-if="loading" class="py-6 text-center text-xs text-slate-500">Đang tải...</div>
    <div v-else-if="error" class="py-6 text-center text-xs text-red-600">{{ error }}</div>
    <div v-else class="flex flex-col gap-3 pt-1">
      <div class="flex items-center gap-4 text-xs">
        <span class="text-slate-500">Designation: <strong class="text-slate-800">{{ product?.designation || '—' }}</strong></span>
        <span class="text-slate-500">Gross price hiện tại: <strong class="font-mono text-sky-700">{{ formatPrice(product?.gross_price) }}</strong></span>
      </div>

      <DataTable :value="histories" class="clean-datatable" showGridlines size="small">
        <Column header="Thời điểm" style="width: 150px">
          <template #body="{ data }"><span class="text-xs">{{ formatDateTime(data.created_at) }}</span></template>
        </Column>
        <Column header="Gross price cũ" class="text-right" style="width: 140px">
          <template #body="{ data }"><span class="font-mono text-xs text-slate-500">{{ formatPrice(data.old_gross_price) }}</span></template>
        </Column>
        <Column header="Gross price mới" class="text-right" style="width: 140px">
          <template #body="{ data }"><span class="font-mono text-xs font-semibold text-sky-700">{{ formatPrice(data.new_gross_price) }}</span></template>
        </Column>
        <Column header="Nguồn" style="width: 150px">
          <template #body="{ data }">
            <span class="text-xs">{{ SOURCE_LABELS[data.source] || data.source }}</span>
            <span v-if="data.reference" class="block font-mono text-[11px] text-slate-500">{{ data.reference }}</span>
          </template>
        </Column>
        <Column header="Người cập nhật">
          <template #body="{ data }"><span class="text-xs">{{ data.changed_by_name || '—' }}</span></template>
        </Column>
        <template #empty>
          <div class="py-4 text-center text-xs text-slate-400">Chưa có thay đổi giá nào được ghi nhận.</div>
        </template>
      </DataTable>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { catalogApi } from '../../api/catalog.api';
import { formatDateTime } from '../../utils/format';
import { formatPrice } from '../../constants/inquiry.constant';

const props = defineProps({
  visible: Boolean,
  sku: { type: String, default: '' }
});

const emit = defineEmits(['update:visible']);

const SOURCE_LABELS = { INQUIRY: 'Hỏi hàng', MANUAL: 'Sửa thủ công', IMPORT: 'Import Excel', CREATE: 'Tạo sản phẩm' };

const loading = ref(false);
const error = ref('');
const product = ref(null);
const histories = ref([]);

watch(() => [props.visible, props.sku], async ([visible, sku]) => {
  if (!visible || !sku) return;
  loading.value = true;
  error.value = '';
  try {
    const data = (await catalogApi.getProductPriceHistory(sku)).data;
    product.value = data.product;
    histories.value = data.histories || [];
  } catch (e) {
    error.value = e.message || 'Không tải được lịch sử giá.';
    histories.value = [];
  } finally {
    loading.value = false;
  }
});
</script>
