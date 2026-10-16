<template>
  <ItemDataTable
    :model-value="rows"
    :columns="columns"
    readonly
    :show-toolbar="false"
    :show-column-setting="false"
    :show-export="false"
    :table-style="{ minWidth: selectable ? '1450px' : '1280px' }"
  >
    <template #col-sku="{ data }">
      <span class="font-mono text-xs font-semibold text-sky-800">{{ data.requestItem?.sku || data.sku || '—' }}</span>
    </template>
    <template #col-designation="{ data }">
      <span class="text-xs font-medium text-slate-800">{{ data.requestItem?.designation || data.designation || '—' }}</span>
    </template>
    <template #col-result_designation="{ data }">
      <span class="text-xs text-slate-700">{{ data.designation || '—' }}</span>
    </template>
    <template #col-brand="{ data }">
      <span class="text-xs text-slate-700">{{ data.brand || '—' }}</span>
    </template>
    <template #col-supplier_name="{ data }">
      <span class="text-xs text-slate-700">{{ data.supplier_name || '—' }}</span>
    </template>
    <template #col-inquiry_code="{ data }">
      <button
        v-if="selectable"
        type="button"
        class="font-mono text-xs font-semibold text-sky-700 hover:underline cursor-pointer"
        @click="emit('open-inquiry', data.inquiry_id)"
      >{{ data.inquiry_code }}</button>
      <span v-else class="font-mono text-xs font-semibold text-sky-700">{{ data.inquiry_code }}</span>
    </template>
    <template #col-lead_time="{ data }">
      <span class="text-xs text-slate-700">{{ data.lead_time || '—' }}</span>
    </template>
    <template #col-gross_price="{ data }">
      <span class="font-mono text-xs font-semibold text-sky-700 whitespace-nowrap">{{ formatPrice(data.gross_price) }}</span>
    </template>
    <template #col-result_status="{ data }">
      <span v-if="isApplied(data)" class="text-xs font-semibold text-emerald-700">
        <i class="pi pi-check-circle text-xs"></i> {{ data.requestItem?.selected_inquiry_item_id ? 'Đã chốt' : 'Đang áp dụng' }}
      </span>
      <button
        v-else-if="selectable && data.requestItem && !data.requestItem.selected_inquiry_item_id"
        type="button"
        class="text-xs font-semibold text-sky-700 hover:underline cursor-pointer"
        @click="emit('select-result', { itemId: data.request_item_id, inquiryItemId: data.id })"
      >Chốt báo giá này</button>
      <span v-else class="text-xs text-slate-400">—</span>
    </template>
    <template #empty>
      <div class="py-10 text-center text-xs text-slate-500">Chưa có kết quả từ phiếu hỏi hàng đã hoàn thành.</div>
    </template>
  </ItemDataTable>
</template>

<script setup>
import { computed } from 'vue';
import ItemDataTable from '../common/ItemDataTable.vue';
import { formatPrice } from '../../constants/inquiry.constant';

const props = defineProps({
  items: { type: Array, default: () => [] },
  results: { type: Array, default: () => [] },
  selectable: Boolean
});

const emit = defineEmits(['select-result', 'open-inquiry']);
const itemById = computed(() => new Map(props.items.map(item => [item.id, item])));
const rows = computed(() => props.results.map(result => ({ ...result, requestItem: itemById.value.get(result.request_item_id) })));

const columns = computed(() => [
  { field: 'sku', header: 'SKU yêu cầu', width: '135px' },
  { field: 'designation', header: 'Designation yêu cầu', width: '180px' },
  { field: 'result_designation', header: 'Designation báo giá', width: '180px' },
  { field: 'brand', header: 'Brand', width: '120px' },
  { field: 'supplier_name', header: 'Nhà cung cấp', width: '170px' },
  { field: 'inquiry_code', header: 'Phiếu hỏi hàng', width: '155px' },
  { field: 'lead_time', header: 'Lead time', width: '120px' },
  { field: 'gross_price', header: 'Giá gross', width: '165px' },
  { field: 'result_status', header: 'Kết quả', width: props.selectable ? '170px' : '145px' }
]);

function isApplied(result) {
  const item = result.requestItem;
  if (!item) return false;
  if (item.selected_inquiry_item_id) return Number(item.selected_inquiry_item_id) === Number(result.id);
  if (item.gross_price == null) return false;
  const options = rows.value.filter(row => row.request_item_id === item.id);
  const matching = options.find(row => Number(row.gross_price) === Number(item.gross_price));
  return Number((matching || options[0])?.id) === Number(result.id);
}
</script>
