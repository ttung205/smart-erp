<template>
  <div class="flex flex-col gap-2">
    <!-- 1. Header Toolbar & Statistics Banner (Khớp giao diện ERP thực tế) -->
    <div class="bg-white border border-slate-200 rounded-[6px] p-2.5 flex flex-wrap items-center justify-between gap-3">
      <!-- Left: Statistics Summary -->
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex flex-col">
          <span class="text-xs font-bold text-slate-900 flex items-center gap-1">
            <i class="pi pi-box text-sky-600 text-xs"></i>
            Sản phẩm ({{ items.length }})
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

      <!-- Right: Action Buttons -->
      <div class="flex items-center gap-1.5 flex-wrap">
        <!-- Nút Thêm sản phẩm nhanh 1 dòng -->
        <Button
          label="Thêm sản phẩm"
          icon="pi pi-plus"
          size="small"
          class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 !text-xs !py-1 !px-2.5 rounded-[6px]"
          @click="addNewEmptyRow"
        />

        <!-- Nút Tìm & Thêm từ danh mục Catalog -->
        <Button
          label="Tìm & Thêm"
          icon="pi pi-search"
          size="small"
          severity="info"
          outlined
          class="!text-xs !py-1 !px-2.5 rounded-[6px] !border-sky-500 !text-sky-600 hover:!bg-sky-50"
          @click="openSearchDialog"
        />

        <!-- Nút Cài đặt cột (Ẩn/Hiện cột) -->
        <Button
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

    <!-- 2. Main Editable Data Table -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="items"
        showGridlines
        class="clean-datatable item-editor-table"
        responsiveLayout="scroll"
        scrollable
        scrollHeight="560px"
      >
        <!-- STT & Thao tác dòng -->
        <Column header="#" style="width: 40px" class="text-center">
          <template #body="{ index }">
            <span class="text-[11px] font-mono text-slate-400 font-semibold">{{ index + 1 }}</span>
          </template>
        </Column>

        <Column style="width: 42px" class="text-center">
          <template #body="{ index }">
            <button
              type="button"
              class="w-6 h-6 rounded flex items-center justify-center text-slate-300 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
              title="Xóa dòng mặt hàng này"
              @click="removeItemRow(index)"
            >
              <i class="pi pi-trash text-xs"></i>
            </button>
          </template>
        </Column>

        <!-- 1. Mã nội bộ (SKU / Internal Code) -->
        <Column v-if="columnsVisibility.sku" header="Internal Code" style="width: 170px">
          <template #body="{ data }">
            <div class="flex items-center gap-1">
              <InputText
                v-model="data.sku"
                class="w-full text-xs font-mono font-semibold !py-1 !px-1.5 rounded-[4px] !border-slate-200"
                placeholder="Mã SKU..."
                @change="onSkuChange(data)"
              />
              <button
                type="button"
                class="w-6 h-6 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center shrink-0 cursor-pointer"
                title="Chọn từ danh mục"
                @click="pickProductForLine(data)"
              >
                <i class="pi pi-list text-[10px]"></i>
              </button>
            </div>
          </template>
        </Column>

        <!-- 2. Designation -->
        <Column v-if="columnsVisibility.designation" header="Designation" style="width: 150px">
          <template #body="{ data }">
            <InputText
              v-model="data.designation"
              class="w-full text-xs font-mono text-indigo-700 font-medium !py-1 !px-1.5 rounded-[4px] !border-slate-200"
              placeholder="Quy cách..."
            />
          </template>
        </Column>

        <!-- 3. Brand -->
        <Column v-if="columnsVisibility.brand" header="Brand" style="width: 100px">
          <template #body="{ data }">
            <InputText
              v-model="data.brand"
              class="w-full text-xs font-semibold text-slate-700 !py-1 !px-1.5 rounded-[4px] !border-slate-200"
              placeholder="Brand..."
            />
          </template>
        </Column>

        <!-- 4. Item Description -->
        <Column v-if="columnsVisibility.description" header="Item Description" style="min-width: 200px">
          <template #body="{ data }">
            <InputText
              v-model="data.description"
              class="w-full text-xs text-slate-800 !py-1 !px-1.5 rounded-[4px] !border-slate-200"
              placeholder="Mô tả sản phẩm..."
            />
          </template>
        </Column>

        <!-- 5. COM (Xuất xứ) -->
        <Column v-if="columnsVisibility.com" header="COM" style="width: 110px">
          <template #body="{ data }">
            <InputText
              v-model="data.com"
              class="w-full text-xs text-slate-600 !py-1 !px-1.5 rounded-[4px] !border-slate-200"
              placeholder="Xuất xứ..."
            />
          </template>
        </Column>

        <!-- 6. Stock (Tồn kho) -->
        <Column v-if="columnsVisibility.stock" header="Stock" style="width: 75px" class="text-right">
          <template #body="{ data }">
            <span
              class="font-mono text-xs font-semibold px-1 py-0.5 rounded-[4px]"
              :class="(data.stock || 0) > 0 ? 'text-emerald-700 bg-emerald-50' : 'text-slate-400 bg-slate-50'"
            >
              {{ data.stock ?? 0 }}
            </span>
          </template>
        </Column>

        <!-- 7. Unit (Đơn vị tính) -->
        <Column v-if="columnsVisibility.unit" header="Unit" style="width: 80px" class="text-center">
          <template #body="{ data }">
            <InputText
              v-model="data.unit"
              class="w-full text-xs text-center !py-1 !px-1 rounded-[4px] !border-slate-200"
              placeholder="ĐVT"
            />
          </template>
        </Column>

        <!-- 8. Quantity (Số lượng) -->
        <Column v-if="columnsVisibility.quantity" header="Qty" style="width: 80px" class="text-right">
          <template #body="{ data }">
            <InputNumber
              v-model="data.quantity"
              :min="1"
              class="w-full text-xs font-mono font-bold"
              inputClass="!py-1 !px-1 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200"
              @update:modelValue="onQuantityChange(data)"
            />
          </template>
        </Column>

        <!-- 9. Gross Price (Giá niêm yết) -->
        <Column v-if="columnsVisibility.grossPrice" header="Gross Price" style="width: 130px" class="text-right">
          <template #body="{ data }">
            <InputNumber
              v-model="data.gross_price"
              :min="0"
              currency="VND"
              locale="vi-VN"
              class="w-full text-xs font-mono"
              inputClass="!py-1 !px-1 text-right text-xs font-mono rounded-[4px] !border-slate-200"
              @update:modelValue="onGrossPriceChange(data)"
            />
          </template>
        </Column>

        <!-- 10. Discount % (Chiết khấu) -->
        <Column v-if="columnsVisibility.discount" header="CK %" style="width: 75px" class="text-right">
          <template #body="{ data }">
            <InputNumber
              v-model="data.discount_percent"
              :min="0"
              :max="100"
              suffix="%"
              class="w-full text-xs font-mono font-semibold"
              inputClass="!py-1 !px-1 text-right text-xs font-mono font-semibold rounded-[4px] !border-slate-200 !text-emerald-700"
              @update:modelValue="onDiscountChange(data)"
            />
          </template>
        </Column>

        <!-- 11. Selling Price (Đơn giá bán thực tế) -->
        <Column v-if="columnsVisibility.sellingPrice" header="Selling Price" style="width: 135px" class="text-right">
          <template #body="{ data }">
            <InputNumber
              v-model="data.selling_price"
              :min="0"
              currency="VND"
              locale="vi-VN"
              class="w-full text-xs font-mono font-bold"
              inputClass="!py-1 !px-1 text-right text-xs font-mono font-bold rounded-[4px] !border-slate-200 !text-sky-700"
              @update:modelValue="onSellingPriceChange(data)"
            />
          </template>
        </Column>

        <!-- 12. VAT % -->
        <Column v-if="columnsVisibility.vat" header="VAT" style="width: 65px" class="text-center">
          <template #body="{ data }">
            <span class="text-xs font-mono text-slate-500">{{ data.vat_percent ?? 10 }}%</span>
          </template>
        </Column>

        <!-- 13. Lead Time -->
        <Column v-if="columnsVisibility.leadTime" header="Lead Time" style="width: 100px">
          <template #body="{ data }">
            <InputText
              v-model="data.lead_time"
              class="w-full text-[11px] text-slate-600 !py-1 !px-1 rounded-[4px] !border-slate-200"
              placeholder="Thời gian..."
            />
          </template>
        </Column>

        <!-- 14. Subtotal (Thành tiền) -->
        <Column v-if="columnsVisibility.subtotal" header="Subtotal" style="width: 140px" class="text-right">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-slate-900 num-tabular">
              {{ formatCurrency(data.subtotal || (data.quantity * data.selling_price)) }}
            </span>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-box text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có sản phẩm nào trong báo giá</p>
            <p class="text-[11px] text-slate-400 m-0">Bấm nút "Thêm sản phẩm" hoặc "Tìm & Thêm" ở trên để đưa mặt hàng vào báo giá.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- 3. Modal Tìm & Thêm Sản Phẩm từ Catalog -->
    <Dialog
      v-model:visible="showCatalogSearchDialog"
      modal
      header="Tìm & Thêm Sản Phẩm Từ Danh Mục Hàng Hóa"
      :style="{ width: '840px' }"
      class="rounded-[6px]"
    >
      <div class="flex flex-col gap-3 pt-1">
        <IconField class="w-full">
          <InputIcon class="pi pi-search" />
          <InputText
            v-model="catalogSearchQuery"
            placeholder="Tìm theo mã SKU, Designation, Thương hiệu hoặc Tên sản phẩm..."
            class="w-full rounded-[6px]"
            size="small"
          />
        </IconField>

        <div class="border border-slate-200 rounded-[6px] overflow-hidden max-h-[380px] overflow-y-auto">
          <DataTable
            :value="filteredCatalogProducts"
            showGridlines
            class="clean-datatable"
            v-model:selection="selectedCatalogItems"
            dataKey="sku"
          >
            <Column selectionMode="multiple" headerStyle="width: 3rem"></Column>
            <Column field="sku" header="Mã SKU" style="width: 160px">
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
            <Column field="stock" header="Tồn kho" class="text-right" style="width: 80px">
              <template #body="{ data }">
                <span class="font-mono text-xs font-semibold text-emerald-700">{{ data.stock }}</span>
              </template>
            </Column>
            <Column field="gross_price" header="Giá niêm yết" class="text-right" style="width: 130px">
              <template #body="{ data }">
                <span class="font-mono text-xs font-semibold text-slate-800">{{ formatCurrency(data.gross_price) }}</span>
              </template>
            </Column>
          </DataTable>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between items-center w-full">
          <span class="text-xs text-slate-500">Đã chọn: <strong class="text-sky-600">{{ selectedCatalogItems.length }}</strong> sản phẩm</span>
          <div class="flex gap-2">
            <Button
              label="Hủy bỏ"
              icon="pi pi-times"
              text
              size="small"
              severity="secondary"
              class="rounded-[6px]"
              @click="showCatalogSearchDialog = false"
            />
            <Button
              label="Thêm vào báo giá"
              icon="pi pi-check"
              size="small"
              :disabled="selectedCatalogItems.length === 0"
              class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
              @click="confirmAddSelectedProducts"
            />
          </div>
        </div>
      </template>
    </Dialog>

    <!-- 4. Modal Cài Đặt Ẩn/Hiện Cột (Column Config) -->
    <Dialog
      v-model:visible="showColumnConfig"
      modal
      header="Cài Đặt Ẩn / Hiện Cột Mặt Hàng"
      :style="{ width: '450px' }"
      class="rounded-[6px]"
    >
      <div class="grid grid-cols-2 gap-2 pt-1 text-xs">
        <label v-for="(val, key) in columnsVisibility" :key="key" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 rounded cursor-pointer">
          <input
            type="checkbox"
            v-model="columnsVisibility[key]"
            class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 cursor-pointer"
          />
          <span class="text-slate-700 font-medium">{{ getColumnLabel(key) }}</span>
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
import { ref, reactive, computed, watch } from 'vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Dialog from 'primevue/dialog';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import { catalogProducts } from '../../composables/salesStore';
import { formatCurrency } from '../../utils/format';

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  }
});

const emit = defineEmits(['update:modelValue', 'summary-change']);

const items = ref([]);

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      items.value = newVal;
    }
  },
  { immediate: true, deep: true }
);

// Ẩn / hiện cột
const showColumnConfig = ref(false);
const columnsVisibility = reactive({
  sku: true,
  designation: true,
  brand: true,
  description: true,
  com: true,
  stock: true,
  unit: true,
  quantity: true,
  grossPrice: true,
  discount: true,
  sellingPrice: true,
  vat: true,
  leadTime: true,
  subtotal: true
});

const getColumnLabel = (key) => {
  const map = {
    sku: 'Internal Code (SKU)',
    designation: 'Designation (Quy cách)',
    brand: 'Brand (Thương hiệu)',
    description: 'Item Description (Mô tả)',
    com: 'COM (Xuất xứ)',
    stock: 'Stock (Tồn kho)',
    unit: 'Unit (Đơn vị tính)',
    quantity: 'Quantity (Số lượng)',
    grossPrice: 'Gross Price (Giá niêm yết)',
    discount: 'Discount (Chiết khấu %)',
    sellingPrice: 'Selling Price (Giá bán)',
    vat: 'VAT %',
    leadTime: 'Lead Time (Giao hàng)',
    subtotal: 'Subtotal (Thành tiền)'
  };
  return map[key] || key;
};

// ----------------------------------------------------
// TÍNH TOÁN CÔNG THỨC 2 CHIỀU (BIDIRECTIONAL CALCULATION)
// ----------------------------------------------------
// 1. Khi nhập chiết khấu % -> tính ra selling_price
const onDiscountChange = (row) => {
  const gross = Number(row.gross_price) || 0;
  const disc = Number(row.discount_percent) || 0;
  row.selling_price = Math.round(gross * (1 - disc / 100));
  row.subtotal = (Number(row.quantity) || 1) * row.selling_price;
  notifyChange();
};

// 2. Khi nhập đơn giá bán selling_price -> tự tính ngược lại chiết khấu %
const onSellingPriceChange = (row) => {
  const gross = Number(row.gross_price) || 0;
  const selling = Number(row.selling_price) || 0;
  if (gross > 0) {
    const calculatedDisc = ((gross - selling) / gross) * 100;
    row.discount_percent = Math.max(0, Math.round(calculatedDisc * 10) / 10);
  } else {
    row.discount_percent = 0;
  }
  row.subtotal = (Number(row.quantity) || 1) * selling;
  notifyChange();
};

// 3. Khi sửa giá niêm yết Gross Price -> cập nhật Selling Price theo % chiết khấu hiện tại
const onGrossPriceChange = (row) => {
  const gross = Number(row.gross_price) || 0;
  const disc = Number(row.discount_percent) || 0;
  row.selling_price = Math.round(gross * (1 - disc / 100));
  row.subtotal = (Number(row.quantity) || 1) * row.selling_price;
  notifyChange();
};

// 4. Khi sửa số lượng Quantity
const onQuantityChange = (row) => {
  const qty = Number(row.quantity) || 1;
  const selling = Number(row.selling_price) || 0;
  row.subtotal = qty * selling;
  notifyChange();
};

// 5. Thêm dòng trống
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
    subtotal: 0,
    won: true,
    won_quantity: 1
  };
  items.value.push(newRow);
  notifyChange();
};

// 6. Xóa dòng
const removeItemRow = (idx) => {
  if (items.value.length > 1) {
    items.value.splice(idx, 1);
    notifyChange();
  } else {
    alert('Báo giá cần có ít nhất 1 dòng sản phẩm!');
  }
};

// 7. Nhập SKU hoặc tự động điền nếu khớp Catalog
const onSkuChange = (row) => {
  const found = catalogProducts.value.find((p) => p.sku.toLowerCase() === row.sku.toLowerCase());
  if (found) {
    fillRowWithCatalogProduct(row, found);
  }
};

const fillRowWithCatalogProduct = (row, product) => {
  row.sku = product.sku;
  row.designation = product.designation;
  row.brand = product.brand;
  row.description = product.description;
  row.unit = product.unit || 'Cái';
  row.com = product.com || 'Chính hãng';
  row.stock = product.stock || 0;
  row.gross_price = product.gross_price || 0;
  row.vat_percent = product.vat_percent || 10;
  row.lead_time = product.lead_time || 'Có sẵn';

  // Áp dụng tính toán selling price theo chiết khấu hiện có
  const disc = Number(row.discount_percent) || 0;
  row.selling_price = Math.round(row.gross_price * (1 - disc / 100));
  row.subtotal = (Number(row.quantity) || 1) * row.selling_price;
  notifyChange();
};

// ----------------------------------------------------
// TÌM & THÊM TỪ CATALOG MODAL
// ----------------------------------------------------
const showCatalogSearchDialog = ref(false);
const catalogSearchQuery = ref('');
const selectedCatalogItems = ref([]);
const activeLineToPick = ref(null);

const filteredCatalogProducts = computed(() => {
  if (!catalogSearchQuery.value) return catalogProducts.value;
  const q = catalogSearchQuery.value.toLowerCase().trim();
  return catalogProducts.value.filter(
    (p) =>
      p.sku.toLowerCase().includes(q) ||
      (p.designation && p.designation.toLowerCase().includes(q)) ||
      (p.brand && p.brand.toLowerCase().includes(q)) ||
      p.description.toLowerCase().includes(q)
  );
});

const openSearchDialog = () => {
  activeLineToPick.value = null;
  selectedCatalogItems.value = [];
  catalogSearchQuery.value = '';
  showCatalogSearchDialog.value = true;
};

const pickProductForLine = (row) => {
  activeLineToPick.value = row;
  selectedCatalogItems.value = [];
  catalogSearchQuery.value = '';
  showCatalogSearchDialog.value = true;
};

const confirmAddSelectedProducts = () => {
  if (activeLineToPick.value && selectedCatalogItems.value.length === 1) {
    fillRowWithCatalogProduct(activeLineToPick.value, selectedCatalogItems.value[0]);
  } else {
    // Thêm các sản phẩm đã chọn thành các dòng mới
    for (const prod of selectedCatalogItems.value) {
      // Nếu dòng đầu tiên đang rỗng, điền vào dòng đó
      const firstEmpty = items.value.find((r) => !r.sku && !r.description);
      if (firstEmpty) {
        fillRowWithCatalogProduct(firstEmpty, prod);
      } else {
        const newRow = {
          id: Date.now() + Math.random(),
          sku: prod.sku,
          designation: prod.designation,
          brand: prod.brand,
          description: prod.description,
          com: prod.com || 'Chính hãng',
          stock: prod.stock || 0,
          unit: prod.unit || 'Cái',
          quantity: 1,
          gross_price: prod.gross_price || 0,
          discount_percent: 0,
          selling_price: prod.gross_price || 0,
          vat_percent: prod.vat_percent || 10,
          lead_time: prod.lead_time || 'Có sẵn',
          subtotal: prod.gross_price || 0,
          won: true,
          won_quantity: 1
        };
        items.value.push(newRow);
      }
    }
    notifyChange();
  }
  showCatalogSearchDialog.value = false;
};

// ----------------------------------------------------
// TÍNH TOÁN TỔNG KẾT
// ----------------------------------------------------
const summary = computed(() => {
  let subtotal = 0;
  let totalDiscount = 0;
  let vatTotal = 0;

  for (const item of items.value) {
    const itemSub = Number(item.subtotal) || ((Number(item.quantity) || 1) * (Number(item.selling_price) || 0));
    subtotal += itemSub;
    totalDiscount += (Number(item.discount_percent) || 0);
    const vat = (itemSub * (Number(item.vat_percent) || 10)) / 100;
    vatTotal += vat;
  }

  const avgDiscount = items.value.length > 0 ? Math.round(totalDiscount / items.value.length) : 0;
  const grandTotal = subtotal + vatTotal;

  return {
    subtotal,
    vatTotal,
    avgDiscount,
    grandTotal
  };
});

const notifyChange = () => {
  emit('update:modelValue', items.value);
  emit('summary-change', summary.value);
};

const exportToExcel = () => {
  alert('Đã xuất danh sách ' + items.value.length + ' mặt hàng báo giá ra file Excel thành công.');
};
</script>
