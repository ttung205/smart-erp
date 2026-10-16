<template>
  <div>
  <ItemDataTable
    v-model="items"
    :columns="columns"
    :catalog-item-mapper="toRequestItem"
    :create-empty-row="createBlankRequestItem"
    :readonly="readonly"
    :show-toolbar="!readonly"
    :show-delete="!readonly"
    add-row-label="Thêm item"
    :show-export="false"
    :table-style="{ minWidth: '1600px' }"
  >
    <template #summary>
      <span class="text-xs font-bold text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
        <i class="pi pi-box text-sky-600 text-xs"></i>
        Item yêu cầu ({{ items.length }})
      </span>
    </template>

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

    <!-- Gross price của SKU trong catalog tại thời điểm lập yêu cầu -->
    <template #col-catalog_gross_price="{ data }">
      <div class="flex items-center justify-end gap-1 whitespace-nowrap">
        <span class="font-mono text-xs" :class="data.catalog_gross_price != null ? 'font-semibold text-slate-700' : 'text-slate-400'">
          {{ data.catalog_gross_price != null ? formatPrice(data.catalog_gross_price) : (data.sku ? 'Lưu để lấy giá' : '—') }}
        </span>
        <button
          v-if="data.sku"
          type="button"
          class="w-6 h-6 rounded flex items-center justify-center text-slate-400 hover:text-sky-600 hover:bg-sky-50 cursor-pointer"
          title="Lịch sử gross price"
          @click="openHistory(data.sku)"
        >
          <i class="pi pi-history text-xs"></i>
        </button>
      </div>
    </template>

  </ItemDataTable>

  <ProductPriceHistoryDialog v-model:visible="showHistory" :sku="historySku" />
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import InputText from 'primevue/inputtext';
import ItemDataTable from '../common/ItemDataTable.vue';
import ProductPriceHistoryDialog from '../common/ProductPriceHistoryDialog.vue';
import { INQUIRY_ITEM_FIELDS, createBlankRequestItem, formatPrice } from '../../constants/inquiry.constant';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  readonly: Boolean
});

const emit = defineEmits(['update:modelValue']);
const items = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) });

// Sản phẩm chọn từ catalog -> item yêu cầu (lead time để trống cho phòng mua hàng hỏi NCC)
const toRequestItem = product => ({
  ...createBlankRequestItem(),
  sku: product.sku || '',
  designation: product.designation || '',
  item_description: product.description || '',
  brand: product.brand || '',
  category: product.category || '',
  com: product.com || '',
  unit: product.unit || '',
  spec: product.spec || '',
  catalog_gross_price: product.gross_price ?? null
});

const showHistory = ref(false);
const historySku = ref('');
const openHistory = sku => { historySku.value = sku; showHistory.value = true; };

const columns = computed(() => [
  ...INQUIRY_ITEM_FIELDS.map(field => ({ field: field.key, header: field.label, width: field.width })),
  { field: 'catalog_gross_price', header: 'Gross price lúc hỏi', width: '170px' }
]);
</script>
