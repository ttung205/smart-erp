<template>
  <div class="flex flex-col gap-4 pb-10">
    <!-- Single Page Title -->
    <PageHeader
      title="Danh Mục Sản Phẩm (Catalog Master)"
      subtitle="Quản lý mã hàng SKU, thông số kỹ thuật, giá niêm yết chuẩn và đồng bộ toàn hệ thống"
      badge="Catalog Service"
      badge-severity="info"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <!-- Nút Import Excel -->
          <Button
            label="Import Excel"
            icon="pi pi-file-excel"
            size="small"
            severity="success"
            outlined
            class="rounded-[6px] !text-xs !py-1.5 !px-3 !border-emerald-600 !text-emerald-700 hover:!bg-emerald-50 font-medium"
            @click="showImportDialog = true"
          />

          <!-- Nút Tạo sản phẩm mới -->
          <Button
            label="Tạo sản phẩm mới"
            icon="pi pi-plus"
            size="small"
            class="rounded-[6px] !text-xs !py-1.5 !px-3 !bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 font-medium"
            @click="handleOpenCreateModal"
          />
        </div>
      </template>
    </PageHeader>

    <!-- Operational Toolbar -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo SKU, Designation, Brand, Tên sản phẩm..."
              class="w-80 rounded-[6px]"
              size="small"
            />
          </IconField>
          <Select
            v-model="selectedBrandFilter"
            :options="brandOptions"
            placeholder="Lọc theo hãng..."
            class="w-44 rounded-[6px] text-xs"
            size="small"
            showClear
          />
          <Select
            v-model="selectedCategoryFilter"
            :options="categoryOptions"
            placeholder="Lọc danh mục..."
            class="w-44 rounded-[6px] text-xs"
            size="small"
            showClear
          />
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2">
          <Button
            label="Làm mới"
            icon="pi pi-refresh"
            severity="secondary"
            outlined
            size="small"
            class="rounded-[6px] !text-xs !py-1 !px-2.5"
            @click="fetchProducts"
          />
          <Tag
            severity="secondary"
            :value="filteredProducts.length + ' Sản phẩm'"
            class="text-[11px] px-2 py-1 rounded-[6px]"
          />
        </div>
      </template>
    </Toolbar>

    <!-- Products DataTable với scroll ngang chuẩn sử dụng CommonDataTable -->
    <CommonDataTable
      :value="filteredProducts"
      :columns="tableColumns"
      scrollable
      scrollHeight="600px"
      :tableStyle="{ minWidth: '1600px' }"
      :rows="15"
      :rowsPerPageOptions="[15, 30, 50, 100]"
      :paginator="filteredProducts.length > 15"
      :loading="loadingProducts"
      emptyIcon="pi pi-box"
      emptyTitle="Không tìm thấy sản phẩm nào"
      emptySubtitle="Nhấn nút 'Import Excel' để nạp dữ liệu mẫu hoặc 'Tạo sản phẩm mới'."
    >
      <!-- 1. SKU -->
      <template #col-sku="{ data }">
        <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200">
          {{ data.sku }}
        </span>
      </template>

      <!-- 2. Designation -->
      <template #col-designation="{ data }">
        <span class="font-mono text-xs font-semibold text-rose-700">
          {{ data.designation || '-' }}
        </span>
      </template>

      <!-- 3. Brand -->
      <template #col-brand="{ data }">
        <span class="text-xs font-bold text-slate-800">{{ data.brand || '-' }}</span>
      </template>

      <!-- 4. Category -->
      <template #col-category="{ data }">
        <Tag severity="secondary" :value="data.category || 'Mặc định'" class="text-[11px] px-2 py-0.5 rounded-[4px]" />
      </template>

      <!-- 5. Item Description -->
      <template #col-description="{ data }">
        <span class="text-xs font-semibold text-slate-900 block truncate" :title="data.description || data.name">
          {{ data.description || data.name || '-' }}
        </span>
      </template>

      <!-- 6. COM (Xuất xứ) -->
      <template #col-com="{ data }">
        <span class="text-xs text-slate-600">{{ data.com || 'Việt Nam' }}</span>
      </template>

      <!-- 7. Unit -->
      <template #col-unit="{ data }">
        <span class="text-xs text-slate-600 font-medium">{{ data.unit || 'Cái' }}</span>
      </template>

      <!-- 8. Gross Price -->
      <template #col-gross_price="{ data }">
        <span class="font-bold text-slate-900 font-mono text-xs num-tabular">
          {{ formatCurrency(data.gross_price) }}
        </span>
      </template>

      <!-- 9. Specs (Thông số kỹ thuật) -->
      <template #col-spec="{ data }">
        <span class="text-[11px] text-slate-500 font-mono block truncate" :title="data.spec">
          {{ data.spec || '-' }}
        </span>
      </template>
    </CommonDataTable>

    <!-- Product Create Form Modal -->
    <ProductForm
      v-model:visible="showCreateModal"
      :loading="loadingCreate"
      :initial-data="initialFormData"
      @submit="handleCreateProduct"
    />

    <!-- Import Excel Modal Dialog -->
    <CatalogImportExcelDialog
      v-model:visible="showImportDialog"
      :importing="importing"
      @submit="submitImportExcel"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import ProductForm from './form.vue';
import CatalogImportExcelDialog from '../../components/catalog/CatalogImportExcelDialog.vue';
import { catalogApi } from '../../api/catalog.api';
import { formatCurrency } from '../../utils/format';

const toast = useToast();

const tableColumns = [
  { field: 'sku', header: 'MÃ SKU', sortable: true, width: '170px' },
  { field: 'designation', header: 'DESIGNATION', sortable: true, width: '150px' },
  { field: 'brand', header: 'BRAND', sortable: true, width: '110px' },
  { field: 'category', header: 'DANH MỤC', sortable: true, width: '130px' },
  { field: 'description', header: 'ITEM DESCRIPTION', sortable: false, width: '320px' },
  { field: 'com', header: 'COM', sortable: false, width: '110px' },
  { field: 'unit', header: 'UNIT', sortable: false, width: '75px', align: 'center' },
  { field: 'gross_price', header: 'GROSS PRICE', sortable: true, width: '150px', align: 'right' },
  { field: 'spec', header: 'THÔNG SỐ KỸ THUẬT (SPECS)', sortable: false, width: '380px' }
];

const products = ref([]);
const loadingProducts = ref(false);
const searchQuery = ref('');
const selectedBrandFilter = ref(null);
const selectedCategoryFilter = ref(null);

const showCreateModal = ref(false);
const loadingCreate = ref(false);
const initialFormData = ref({});

const showImportDialog = ref(false);
const importing = ref(false);
const selectedFileName = ref('');
const parsedItems = ref([]);

const brandOptions = computed(() => {
  const brands = new Set(products.value.map((p) => p.brand).filter(Boolean));
  return Array.from(brands).sort();
});

const categoryOptions = computed(() => {
  const cats = new Set(products.value.map((p) => p.category).filter(Boolean));
  return Array.from(cats).sort();
});

const filteredProducts = computed(() => {
  return products.value.filter((p) => {
    const q = searchQuery.value.toLowerCase().trim();
    const matchSearch =
      !q ||
      (p.sku && p.sku.toLowerCase().includes(q)) ||
      (p.designation && p.designation.toLowerCase().includes(q)) ||
      (p.brand && p.brand.toLowerCase().includes(q)) ||
      (p.description && p.description.toLowerCase().includes(q)) ||
      (p.name && p.name.toLowerCase().includes(q));

    const matchBrand = !selectedBrandFilter.value || p.brand === selectedBrandFilter.value;
    const matchCat = !selectedCategoryFilter.value || p.category === selectedCategoryFilter.value;

    return matchSearch && matchBrand && matchCat;
  });
});

const fetchProducts = async () => {
  loadingProducts.value = true;
  try {
    const res = await catalogApi.getProducts();
    if (res && res.data) {
      products.value = res.data;
    }
  } catch (err) {
    console.error('Fetch products error:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi nạp sản phẩm',
      detail: 'Không thể kết nối đến Catalog Service.',
      life: 3000
    });
  } finally {
    loadingProducts.value = false;
  }
};

const handleOpenCreateModal = () => {
  initialFormData.value = {};
  showCreateModal.value = true;
};

const handleCreateProduct = async (formData) => {
  loadingCreate.value = true;
  try {
    const res = await catalogApi.createProduct(formData);
    if (res && res.success) {
      toast.add({
        severity: 'success',
        summary: 'Thêm mới thành công',
        detail: `Đã lưu sản phẩm ${formData.sku} vào Catalog.`,
        life: 3000
      });
      showCreateModal.value = false;
      await fetchProducts();
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu sản phẩm',
      detail: err.message || 'Không thể tạo sản phẩm mới.',
      life: 3000
    });
  } finally {
    loadingCreate.value = false;
  }
};

// Đọc file Excel từ client
const handleFileSelected = (e) => {
  const file = e.target.files[0];
  if (!file) return;

  selectedFileName.value = file.name;
  const reader = new FileReader();

  reader.onload = (evt) => {
    try {
      const data = new Uint8Array(evt.target.result);
      const workbook = XLSX.read(data, { type: 'array' });
      const firstSheetName = workbook.SheetNames[0];
      const worksheet = workbook.Sheets[firstSheetName];
      const json = XLSX.utils.sheet_to_json(worksheet);

      parsedItems.value = json;
    } catch (err) {
      console.error('Parse Excel error:', err);
      toast.add({
        severity: 'error',
        summary: 'Lỗi đọc file Excel',
        detail: 'Định dạng file Excel không hợp lệ.',
        life: 3000
      });
    }
  };

  reader.readAsArrayBuffer(file);
};

// Gửi dữ liệu Excel đã parse lên Catalog Service
const submitImportExcel = async () => {
  if (parsedItems.value.length === 0) return;

  importing.value = true;
  try {
    const res = await catalogApi.importProducts(parsedItems.value);
    if (res && res.success) {
      toast.add({
        severity: 'success',
        summary: 'Import thành công!',
        detail: res.data.message || `Đã import ${res.data.imported_count} sản phẩm vào hệ thống.`,
        life: 4000
      });
      showImportDialog.value = false;
      parsedItems.value = [];
      selectedFileName.value = '';
      await fetchProducts();
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi Import Excel',
      detail: err.message || 'Không thể gửi dữ liệu lên Catalog Service.',
      life: 4000
    });
  } finally {
    importing.value = false;
  }
};

onMounted(() => {
  fetchProducts();
});
</script>
