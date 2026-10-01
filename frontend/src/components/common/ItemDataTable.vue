<template>
  <div class="flex flex-col gap-2">
    <!-- 1. Header Toolbar & Statistics Summary (Tổng quát cho mọi bảng mặt hàng) -->
    <div
      v-if="showToolbar"
      class="bg-white border border-slate-200 rounded-[6px] p-2.5 flex flex-wrap items-center justify-between gap-3 shadow-sm"
    >
      <!-- Left: Statistics Summary -->
      <slot name="summary" :summary="summary" :items="items">
        <div class="flex items-center gap-4 flex-wrap">
          <div class="flex flex-col">
            <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
              <i class="pi pi-box text-sky-600 text-xs"></i>
              Mặt hàng ({{ items.length }})
            </span>
          </div>

          <div class="h-6 w-[1px] bg-slate-200 hidden sm:block"></div>

          <div class="flex items-center gap-4 text-xs font-mono">
            <div>
              <span class="text-slate-400 block text-[10px] font-sans">Tiền hàng (Subtotal):</span>
              <span class="font-bold text-slate-800">{{ formatCurrency(summary.subtotal) }}</span>
            </div>

            <div>
              <span class="text-slate-400 block text-[10px] font-sans">Thuế VAT:</span>
              <span class="font-semibold text-slate-700">{{ formatCurrency(summary.vatTotal) }}</span>
            </div>

            <div>
              <span class="text-slate-400 block text-[10px] font-sans">CK Trung Bình:</span>
              <span class="font-bold text-emerald-600">{{ summary.avgDiscount }}%</span>
            </div>

            <div class="bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-100">
              <span class="text-sky-600 block text-[10px] font-sans font-semibold">Tổng cộng (Total):</span>
              <span class="font-bold text-sky-700 text-sm">{{ formatCurrency(summary.grandTotal) }}</span>
            </div>
          </div>
        </div>
      </slot>

      <!-- Right: Action Buttons -->
      <div class="flex items-center gap-1.5 flex-wrap">
        <!-- Nút Thêm sản phẩm nhanh 1 dòng -->
        <Button
          v-if="showAddRow"
          label="Thêm sản phẩm"
          icon="pi pi-plus"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !text-xs !py-1 !px-2.5 rounded-[6px]"
          @click="addNewEmptyRow"
        />

        <!-- Nút Tìm & Thêm từ danh mục Catalog -->
        <Button
          v-if="showCatalogSearch"
          label="Tìm & Thêm"
          icon="pi pi-search"
          size="small"
          severity="info"
          outlined
          class="!text-xs !py-1 !px-2.5 rounded-[6px] !border-sky-500 !text-sky-600 hover:!bg-sky-50"
          @click="openSearchDialog"
        />

        <!-- Slot cho các nút tác vụ nghiệp vụ đặc thù (do component con truyền vào) -->
        <slot name="extra-actions" :items="items" :summary="summary" />

        <!-- Nút Cài đặt cột (Ẩn/Hiện cột) -->
        <Button
          v-if="showColumnSetting"
          label="Cài đặt cột"
          icon="pi pi-cog"
          size="small"
          severity="secondary"
          text
          class="!text-xs !py-1 !px-2 rounded-[6px] !text-slate-600 hover:!bg-slate-100"
          @click="showColumnConfig = true"
        />

        <!-- Nút Xuất Excel -->
        <Button
          v-if="showExport"
          icon="pi pi-file-excel"
          label="Xuất Excel"
          size="small"
          severity="secondary"
          text
          class="!text-xs !py-1 !px-2 rounded-[6px] !text-emerald-700 hover:!bg-emerald-50"
          @click="exportToExcel"
        />
      </div>
    </div>

    <!-- 2. Main Generic Data Table (Tương tự CommonDataTable nhưng cho Items) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden shadow-sm">
      <DataTable
        :value="items"
        showGridlines
        class="clean-datatable item-editor-table"
        responsiveLayout="scroll"
        scrollable
        :scrollHeight="scrollHeight"
        :tableStyle="tableStyleComputed"
      >
        <!-- STT - Frozen (nếu showIndex) -->
        <Column v-if="showIndex" header="#" style="width: 44px; min-width: 44px" class="text-center" frozen>
          <template #body="{ index }">
            <span class="text-xs font-mono text-slate-400 font-semibold">{{ index + 1 }}</span>
          </template>
        </Column>

        <!-- Thao tác xóa dòng - Frozen (nếu showDelete) -->
        <Column v-if="showDelete" style="width: 40px; min-width: 40px" class="text-center" frozen>
          <template #body="{ index }">
            <button
              type="button"
              class="w-6 h-6 rounded flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer mx-auto"
              title="Xóa dòng mặt hàng này"
              @click="removeItemRow(index)"
            >
              <i class="pi pi-trash text-xs"></i>
            </button>
          </template>
        </Column>

        <!-- Slot cho các cột chèn phía trước (ví dụ Checkbox chọn dòng) -->
        <slot name="prepend-columns" :items="items" :notify-change="notifyChange"></slot>

        <!-- Dynamic Columns Loop - Render toàn bộ cột dựa theo columns prop -->
        <template v-if="activeColumns && activeColumns.length > 0">
          <Column
            v-for="col in activeColumns"
            :key="col.field"
            :field="col.field"
            :header="col.header"
            :style="{ width: col.width || 'auto', minWidth: col.minWidth || col.width || 'auto' }"
            :class="[col.align ? `text-${col.align}` : '', col.class || '']"
            :headerClass="col.headerClass || ''"
            :frozen="col.frozen"
          >
            <template #body="{ data, field, index }">
              <slot
                :name="`col-${col.field}`"
                :data="data"
                :field="field"
                :index="index"
                :value="data[col.field]"
                :notify-change="notifyChange"
              >
                <!-- Render mặc định dạng văn bản nếu component con không định nghĩa template -->
                <span class="text-xs text-slate-800">{{ data[col.field] }}</span>
              </slot>
            </template>
          </Column>
        </template>

        <!-- Slot cho các cột chèn phía sau -->
        <slot name="append-columns" :items="items" :notify-change="notifyChange"></slot>

        <!-- Dynamic slot cho bất kỳ component Column nào cắm trực tiếp -->
        <slot :items="items" :notify-change="notifyChange"></slot>

        <!-- Trạng thái bảng rỗng -->
        <template #empty>
          <slot name="empty">
            <div class="py-10 text-center flex flex-col items-center gap-2">
              <i class="pi pi-box text-3xl text-slate-300"></i>
              <p class="text-xs font-semibold text-slate-700 m-0">Chưa có sản phẩm nào trong danh sách</p>
              <p class="text-[11px] text-slate-400 m-0">Bấm nút "Thêm sản phẩm" hoặc "Tìm & Thêm" ở trên để đưa mặt hàng vào bảng.</p>
            </div>
          </slot>
        </template>
      </DataTable>
    </div>

    <!-- 3. Modal Tìm & Thêm từ danh mục Catalog chung -->
    <Dialog
      v-model:visible="showCatalogSearchDialog"
      modal
      header="Tìm & Thêm Mặt Hàng Từ Danh Mục Catalog"
      :style="{ width: '920px' }"
      class="rounded-[6px]"
    >
      <div class="space-y-3 pt-2">
        <div class="flex items-center gap-2">
          <IconField class="flex-1">
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="catalogSearchQuery"
              placeholder="Nhập mã SKU, tên quy cách hoặc mô tả mặt hàng..."
              class="w-full text-xs rounded-[6px]"
              autofocus
            />
          </IconField>
          <span class="text-xs text-slate-500 whitespace-nowrap">
            Tìm thấy: <strong class="text-sky-700 font-mono">{{ filteredCatalogProducts.length }}</strong> sản phẩm
          </span>
        </div>

        <div class="border border-slate-200 rounded-[6px] overflow-hidden max-h-[420px] overflow-y-auto">
          <DataTable
            :value="filteredCatalogProducts"
            v-model:selection="selectedCatalogItems"
            dataKey="id"
            class="clean-datatable"
            responsiveLayout="scroll"
          >
            <Column selectionMode="multiple" headerStyle="width: 3rem" class="text-center"></Column>
            <Column field="sku" header="Mã SKU" style="width: 150px">
              <template #body="{ data }">
                <span class="font-mono text-xs font-bold text-sky-700">{{ data.sku }}</span>
              </template>
            </Column>
            <Column field="designation" header="Designation" style="width: 150px">
              <template #body="{ data }">
                <span class="font-mono text-xs text-rose-700">{{ data.designation }}</span>
              </template>
            </Column>
            <Column field="brand" header="Hãng" style="width: 90px">
              <template #body="{ data }">
                <span class="text-xs font-medium">{{ data.brand }}</span>
              </template>
            </Column>
            <Column field="description" header="Mô tả sản phẩm">
              <template #body="{ data }">
                <span class="text-xs text-slate-800 line-clamp-1" :title="data.description">{{ data.description }}</span>
              </template>
            </Column>
            <Column field="stock" header="Tồn kho" class="text-center" style="width: 100px">
              <template #body="{ data }">
                <span
                  class="font-mono text-xs font-bold px-2 py-0.5 rounded"
                  :class="getAvailableStock(data) > 0 ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' : 'text-slate-400 bg-slate-50 border border-slate-200'"
                >
                  {{ getAvailableStock(data) }}
                </span>
              </template>
            </Column>
            <Column field="gross_price" header="Giá Niêm Yết" class="text-right" style="width: 130px">
              <template #body="{ data }">
                <span class="font-mono text-xs font-bold text-slate-700">{{ formatCurrency(data.gross_price) }}</span>
              </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between items-center w-full">
          <span class="text-xs text-slate-500">
            Đã chọn: <strong class="text-sky-700">{{ selectedCatalogItems.length }}</strong> sản phẩm
          </span>
          <div class="flex gap-2">
            <Button
              label="Hủy bỏ"
              icon="pi pi-times"
              size="small"
              text
              severity="secondary"
              class="rounded-[6px]"
              @click="showCatalogSearchDialog = false"
            />
            <Button
              label="Thêm vào danh sách"
              icon="pi pi-plus"
              size="small"
              class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
              :disabled="selectedCatalogItems.length === 0"
              @click="confirmAddCatalogItems"
            />
          </div>
        </div>
      </template>
    </Dialog>

    <!-- 4. Modal Cài Đặt Cột Động -->
    <Dialog
      v-model:visible="showColumnConfig"
      modal
      header="Cài Đặt Ẩn / Hiện Cột"
      :style="{ width: '450px' }"
      class="rounded-[6px]"
    >
      <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
        <label
          v-for="col in allColumns"
          :key="col.field"
          class="flex items-center gap-2 p-1.5 hover:bg-slate-50 rounded cursor-pointer"
        >
          <input
            type="checkbox"
            :checked="!hiddenColumns[col.field]"
            @change="hiddenColumns[col.field] = !hiddenColumns[col.field]"
            class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 cursor-pointer"
          />
          <span class="text-slate-700 font-medium">{{ col.header || col.field }}</span>
        </label>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Xong"
            icon="pi pi-check"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
            @click="showColumnConfig = false"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, reactive, watch, onMounted } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Dialog from 'primevue/dialog';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { catalogApi } from '../../api/catalog.api';
import { inventoryApi } from '../../api/inventory.api';
import { FALLBACK_CATALOG_PRODUCTS } from '../../constants/catalogProducts.constant';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  columns: {
    type: Array,
    default: () => []
  },
  showToolbar: {
    type: Boolean,
    default: true
  },
  showAddRow: {
    type: Boolean,
    default: true
  },
  showCatalogSearch: {
    type: Boolean,
    default: true
  },
  showColumnSetting: {
    type: Boolean,
    default: true
  },
  showExport: {
    type: Boolean,
    default: true
  },
  showIndex: {
    type: Boolean,
    default: true
  },
  showDelete: {
    type: Boolean,
    default: true
  },
  scrollHeight: {
    type: String,
    default: '580px'
  },
  tableStyle: {
    type: Object,
    default: () => ({ minWidth: '1800px' })
  }
});

const emit = defineEmits(['update:modelValue', 'summary-change', 'add-row']);

const items = ref([]);

// Quản lý ẩn/hiện cột
const showColumnConfig = ref(false);
const hiddenColumns = reactive({});

const allColumns = computed(() => {
  return props.columns || [];
});

const activeColumns = computed(() => {
  return allColumns.value.filter((col) => !hiddenColumns[col.field]);
});

const tableStyleComputed = computed(() => {
  if (props.tableStyle) return props.tableStyle;
  const count = activeColumns.value.length;
  return { minWidth: `${Math.max(1200, count * 130)}px` };
});

// Map tồn kho
const inventoryAvailableBySku = ref(new Map());
const inventoryAvailableByProductId = ref(new Map());

const getAvailableStock = (item) => {
  if (!item) return 0;
  const sku = (item.sku || '').trim().toUpperCase();
  if (sku && inventoryAvailableBySku.value.has(sku)) {
    return inventoryAvailableBySku.value.get(sku);
  }
  const pid = Number(item.product_id || item.productId || item.id || 0);
  if (pid && inventoryAvailableByProductId.value.has(pid)) {
    return inventoryAvailableByProductId.value.get(pid);
  }
  return Number(item.stock) || 0;
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      items.value = newVal.map((it) => ({
        ...it,
        stock: getAvailableStock(it)
      }));
    }
  },
  { immediate: true, deep: true }
);

const notifyChange = () => {
  emit('update:modelValue', items.value);
  emit('summary-change', summary.value);
};

// Thêm dòng mới
const addNewEmptyRow = () => {
  const newRow = {
    id: Date.now() + Math.random(),
    sku: '',
    designation: '',
    brand: '',
    description: '',
    com: 'Việt Nam',
    stock: 0,
    unit: 'Cái',
    quantity: 1,
    gross_price: 0,
    discount_percent: 0,
    selling_price: 0,
    vat_percent: 10,
    lead_time: 'Có sẵn',
    delivery_date: '',
    subtotal: 0
  };
  items.value.push(newRow);
  emit('add-row', newRow);
  notifyChange();
};

// Xóa dòng
const removeItemRow = (idx) => {
  if (items.value.length > 1) {
    items.value.splice(idx, 1);
    notifyChange();
  } else {
    items.value[0] = {
      id: Date.now(),
      sku: '',
      designation: '',
      brand: '',
      description: '',
      com: 'Việt Nam',
      stock: 0,
      unit: 'Cái',
      quantity: 1,
      gross_price: 0,
      discount_percent: 0,
      selling_price: 0,
      vat_percent: 10,
      lead_time: 'Có sẵn',
      delivery_date: '',
      subtotal: 0
    };
    notifyChange();
  }
};

// Catalog search & import
const showCatalogSearchDialog = ref(false);
const catalogSearchQuery = ref('');
const catalogProducts = ref([]);
const selectedCatalogItems = ref([]);

const loadInventoryStocks = async () => {
  try {
    const res = await inventoryApi.getStocks();
    const stocks = res.data || [];
    const bySku = new Map();
    const byPid = new Map();

    stocks.forEach((s) => {
      const avail = Number(s.available_quantity ?? s.quantity ?? 0);
      const sku = (s.product_sku || s.sku || '').trim().toUpperCase();
      const pid = Number(s.product_id || s.id || 0);

      if (pid) byPid.set(pid, (byPid.get(pid) || 0) + avail);
      if (sku) bySku.set(sku, (bySku.get(sku) || 0) + avail);
    });

    inventoryAvailableBySku.value = bySku;
    inventoryAvailableByProductId.value = byPid;
  } catch (err) {
    console.warn('loadInventoryStocks fallback:', err);
  }
};

const loadCatalogProducts = async () => {
  try {
    const res = await catalogApi.getProducts();
    if (res && res.data) {
      catalogProducts.value = res.data.map((p) => ({
        id: p.id,
        sku: p.sku,
        designation: p.designation || '',
        brand: p.brand || '',
        description: p.name || p.description || '',
        gross_price: Number(p.gross_price) || Number(p.price) || 0,
        unit: p.unit || 'Cái',
        com: p.com || 'Chính hãng',
        stock: 0,
        lead_time: 'Có sẵn',
        vat_percent: 10,
        spec: p.spec || ''
      }));
    }
  } catch (err) {
    catalogProducts.value = FALLBACK_CATALOG_PRODUCTS;
  }
};

const filteredCatalogProducts = computed(() => {
  if (!catalogSearchQuery.value) return catalogProducts.value;
  const q = catalogSearchQuery.value.toLowerCase().trim();
  return catalogProducts.value.filter(
    (p) =>
      p.sku.toLowerCase().includes(q) ||
      (p.designation && p.designation.toLowerCase().includes(q)) ||
      (p.brand && p.brand.toLowerCase().includes(q)) ||
      (p.description && p.description.toLowerCase().includes(q))
  );
});

const openSearchDialog = async () => {
  if (catalogProducts.value.length === 0) {
    await loadCatalogProducts();
  }
  await loadInventoryStocks();
  selectedCatalogItems.value = [];
  catalogSearchQuery.value = '';
  showCatalogSearchDialog.value = true;
};

const confirmAddCatalogItems = () => {
  selectedCatalogItems.value.forEach((prod) => {
    const lastItem = items.value[items.value.length - 1];
    if (lastItem && !lastItem.sku && items.value.length === 1) {
      lastItem.sku = prod.sku;
      lastItem.designation = prod.designation;
      lastItem.brand = prod.brand;
      lastItem.description = prod.description;
      lastItem.unit = prod.unit || 'Cái';
      lastItem.com = prod.com || 'Chính hãng';
      lastItem.stock = getAvailableStock(prod);
      lastItem.gross_price = prod.gross_price || 0;
      lastItem.vat_percent = prod.vat_percent || 10;
      lastItem.lead_time = prod.lead_time || 'Có sẵn';
      lastItem.selling_price = Math.round(prod.gross_price * 0.9);
      lastItem.subtotal = (Number(lastItem.quantity) || 1) * lastItem.selling_price;
    } else {
      items.value.push({
        id: Date.now() + Math.random(),
        sku: prod.sku,
        designation: prod.designation,
        brand: prod.brand,
        description: prod.description,
        unit: prod.unit || 'Cái',
        com: prod.com || 'Chính hãng',
        stock: getAvailableStock(prod),
        quantity: 1,
        gross_price: prod.gross_price || 0,
        discount_percent: 10,
        selling_price: Math.round(prod.gross_price * 0.9),
        vat_percent: prod.vat_percent || 10,
        lead_time: prod.lead_time || 'Có sẵn',
        delivery_date: '',
        subtotal: Math.round(prod.gross_price * 0.9)
      });
    }
  });

  notifyChange();
  showCatalogSearchDialog.value = false;
};

// Thống kê tổng hợp banner
const summary = computed(() => {
  let subtotal = 0;
  let vatTotal = 0;
  let totalGross = 0;

  items.value.forEach((it) => {
    const qty = Number(it.quantity) || 1;
    const selling = Number(it.selling_price) || 0;
    const lineSubtotal = Number(it.subtotal) || qty * selling;
    const lineVat = Math.round(lineSubtotal * ((Number(it.vat_percent) || 0) / 100));

    subtotal += lineSubtotal;
    vatTotal += lineVat;
    totalGross += (Number(it.gross_price) || selling) * qty;
  });

  const avgDiscount = totalGross > 0 ? Math.round(((totalGross - subtotal) / totalGross) * 100) : 0;
  const grandTotal = subtotal + vatTotal;

  return {
    subtotal,
    vatTotal,
    avgDiscount,
    grandTotal
  };
});

// Xuất Excel CSV
const exportToExcel = () => {
  const cols = activeColumns.value;
  const headers = ['STT', ...cols.map((c) => c.header || c.field)];
  let csv = headers.join(',') + '\n';
  items.value.forEach((it, idx) => {
    const row = [
      idx + 1,
      ...cols.map((c) => {
        let val = it[c.field];
        if (val === undefined || val === null) val = '';
        if (c.field === 'discount') val = it.discount_percent ?? '';
        return `"${String(val).replace(/"/g, '""')}"`;
      })
    ];
    csv += row.join(',') + '\n';
  });

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.setAttribute('download', `Items_${Date.now()}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
};

onMounted(async () => {
  await Promise.allSettled([loadCatalogProducts(), loadInventoryStocks()]);
  if (items.value.length > 0) {
    items.value.forEach((it) => {
      it.stock = getAvailableStock(it);
    });
  }
});

defineExpose({
  items,
  summary,
  notifyChange,
  addNewEmptyRow,
  getAvailableStock
});
</script>
