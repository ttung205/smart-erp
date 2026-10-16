<template>
  <ItemDataTable
    v-model="items"
    :columns="columns"
    :readonly="readonly"
    :show-toolbar="false"
    :show-delete="!readonly"
    :show-add-row="false"
    :show-catalog-search="false"
    :show-column-setting="false"
    :show-export="false"
    :table-style="{ minWidth: readonly ? '2300px' : '1600px' }"
  >
    <!-- Các trường item: nhập liệu ở form, chỉ đọc ở view -->
    <template v-for="field in INQUIRY_ITEM_FIELDS" :key="field.key" #[`col-${field.key}`]="{ data, notifyChange }">
      <span v-if="readonly" class="text-xs">{{ data[field.key] ?? '—' }}</span>
      <InputText
        v-else
        v-model="data[field.key]"
        :type="field.key === 'quantity' ? 'number' : 'text'"
        :min="field.key === 'quantity' ? 0.001 : undefined"
        :step="field.key === 'quantity' ? 0.001 : undefined"
        size="small"
        class="w-full min-w-24 !text-xs !rounded-[6px]"
        @change="notifyChange()"
      />
    </template>

    <!-- Kết quả hỏi hàng trả về từ phiếu hỏi NCC đã hoàn thành -->
    <template #col-result_supplier="{ data }">
      <span v-if="appliedResult(data)" class="text-xs">{{ appliedResult(data).supplier_name }}</span>
      <span v-else class="text-xs text-slate-400">Chưa có kết quả</span>
    </template>

    <template #col-result_inquiry="{ data }">
      <span
        v-if="appliedResult(data)"
        class="font-mono text-xs font-semibold text-sky-700"
        :class="{ 'cursor-pointer hover:underline': selectable }"
        @click="selectable && emit('open-inquiry', appliedResult(data).inquiry_id)"
      >
        {{ appliedResult(data).inquiry_code }}
      </span>
      <span v-else class="text-xs text-slate-400">—</span>
    </template>

    <template #col-gross_price="{ data }">
      <span class="font-mono text-xs font-semibold text-sky-700 whitespace-nowrap">{{ formatPrice(data.gross_price) }}</span>
    </template>

    <template #col-result_choice="{ data }">
      <span v-if="data.selected_inquiry_item_id" class="text-xs text-emerald-700"><i class="pi pi-check text-xs"></i> Đã chốt</span>
      <Select
        v-else-if="resultOptions(data.id).length > 1"
        :options="resultOptions(data.id)"
        optionLabel="label"
        optionValue="id"
        placeholder="Chọn báo giá khác"
        size="small"
        class="w-full min-w-56 !rounded-[6px] !text-xs"
        @change="event => emit('select-result', { itemId: data.id, inquiryItemId: event.value })"
      />
      <span v-else class="text-xs text-slate-400">—</span>
    </template>
  </ItemDataTable>
</template>

<script setup>
import { computed } from 'vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ItemDataTable from '../common/ItemDataTable.vue';
import { INQUIRY_ITEM_FIELDS, formatPrice } from '../../constants/inquiry.constant';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  readonly: Boolean,
  // Kết quả từ các phiếu hỏi NCC đã hoàn thành (request.results)
  results: { type: Array, default: () => [] },
  // Phòng mua hàng: được mở phiếu và chốt báo giá
  selectable: Boolean
});

const emit = defineEmits(['update:modelValue', 'select-result', 'open-inquiry']);
const items = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) });

const columns = computed(() => [
  ...INQUIRY_ITEM_FIELDS.map(field => ({ field: field.key, header: field.label, width: field.width })),
  ...(props.readonly ? [
    { field: 'result_supplier', header: 'Nhà cung cấp', width: '200px' },
    { field: 'result_inquiry', header: 'Phiếu hỏi hàng', width: '160px' },
    { field: 'gross_price', header: 'Gross price', width: '150px' }
  ] : []),
  ...(props.readonly && props.selectable ? [{ field: 'result_choice', header: 'Chốt báo giá', width: '240px' }] : [])
]);

const resultsFor = itemId => props.results.filter(result => result.request_item_id === itemId);

const resultOptions = itemId => resultsFor(itemId).map(result => ({
  id: result.id,
  label: `${result.inquiry_code} · ${result.supplier_name} · ${formatPrice(result.gross_price)}`
}));

// Phiếu hoàn thành đầu tiên chỉ ghi gross_price, không lưu báo giá nào được dùng, nên đối chiếu theo giá
function appliedResult(item) {
  const results = resultsFor(item.id);
  if (item.selected_inquiry_item_id) return results.find(result => result.id === item.selected_inquiry_item_id);
  if (item.gross_price == null) return null;
  return results.find(result => Number(result.gross_price) === Number(item.gross_price)) || results[0];
}
</script>
