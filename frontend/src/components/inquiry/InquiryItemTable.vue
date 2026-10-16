<template>
  <ItemDataTable
    v-model="items"
    :columns="columns"
    :readonly="readonly"
    :show-toolbar="false"
    :show-delete="false"
    :show-add-row="false"
    :show-catalog-search="false"
    :show-column-setting="false"
    :show-export="false"
    :table-style="{ minWidth: '2000px' }"
  >
    <template v-for="field in SUPPLIER_ITEM_FIELDS" :key="field.key" #[`col-${field.key}`]="{ data, notifyChange }">
      <span v-if="readonly" class="text-xs">{{ data[field.key] ?? '—' }}</span>
      <InputText
        v-else
        v-model="data[field.key]"
        :type="field.key === 'quantity' ? 'number' : 'text'"
        :min="field.key === 'quantity' ? 0.001 : undefined"
        size="small"
        class="w-full min-w-24 !text-xs !rounded-[6px]"
        @change="notifyChange()"
      />
    </template>

    <template #col-brand_id="{ data, notifyChange }">
      <span v-if="readonly" class="text-xs">{{ brandName(data.brand_id) }}</span>
      <Select
        v-else
        v-model="data.brand_id"
        :options="brands"
        optionLabel="name"
        optionValue="id"
        placeholder="Chọn Brand"
        filter
        size="small"
        class="w-full min-w-40 !rounded-[6px] !text-xs"
        @change="notifyChange()"
      />
    </template>

    <template v-for="field in SUPPLIER_PRICE_FIELDS" :key="field.key" #[`col-${field.key}`]="{ data, notifyChange }">
      <span v-if="readonly" class="font-mono text-xs">{{ field.key === 'buying_price' ? formatPrice(data[field.key]) : `${data[field.key]}%` }}</span>
      <InputNumber
        v-else
        v-model="data[field.key]"
        :min="0"
        :max="field.key === 'buying_price' ? undefined : 100"
        :minFractionDigits="0"
        :maxFractionDigits="2"
        class="w-full min-w-24"
        inputClass="w-full !text-xs !rounded-[6px]"
        @update:modelValue="value => onPriceFieldChange(data, field.key, value, notifyChange)"
      />
    </template>

    <template #col-gross_price="{ data }">
      <span class="font-mono text-xs font-semibold text-sky-700 whitespace-nowrap">{{ formatPrice(data.gross_price) }}</span>
    </template>
  </ItemDataTable>
</template>

<script setup>
import { computed } from 'vue';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import ItemDataTable from '../common/ItemDataTable.vue';
import { SUPPLIER_ITEM_FIELDS, SUPPLIER_PRICE_FIELDS, formatPrice } from '../../constants/inquiry.constant';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  brands: { type: Array, default: () => [] },
  readonly: Boolean
});

const emit = defineEmits(['update:modelValue']);
const items = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) });

// ItemDataTable chuẩn hoá vat = vat_percent ?? vat mỗi lần dữ liệu đổi, nên phải đồng bộ cả vat_percent để VAT không bị ghi đè về giá trị cũ
const onPriceFieldChange = (row, key, value, notifyChange) => {
  if (key === 'vat') row.vat_percent = value;
  notifyChange();
};

const brandName = id => props.brands.find(b => b.id === id)?.name || id || '—';

const columns = [
  ...SUPPLIER_ITEM_FIELDS.map(field => ({ field: field.key, header: field.label, width: field.width })),
  { field: 'brand_id', header: 'Brand *', width: '170px' },
  ...SUPPLIER_PRICE_FIELDS.map(field => ({ field: field.key, header: field.label, width: '130px' })),
  { field: 'gross_price', header: 'Gross price', width: '150px' }
];
</script>
