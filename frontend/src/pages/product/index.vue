<template>
  <div class="flex flex-col gap-4 pb-10">
    <PageHeader
      title="Danh Mục Sản Phẩm (Product Master)"
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
            @click="openCreateModal"
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
          <Badge
            severity="secondary"
            :value="filteredProducts.length + ' Sản phẩm'"
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
      :tableStyle="{ minWidth: '1720px' }"
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
        <Badge severity="secondary" :value="data.category || 'Mặc định'" />
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

      <!-- 10. Thao tác (Sửa / Xóa) -->
      <Column header="THAO TÁC" style="width: 100px; min-width: 100px" class="text-center" frozen alignFrozen="right">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1.5">
            <!-- Sửa -->
            <Button
              icon="pi pi-pencil"
              outlined
              size="small"
              severity="info"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Chỉnh sửa sản phẩm'"
              @click="openEditModal(data)"
            />

            <!-- Xóa -->
            <Button
              icon="pi pi-trash"
              outlined
              size="small"
              severity="danger"
              class="!w-7 !h-7 !p-0 rounded-[6px]"
              v-tooltip.bottom="'Xóa sản phẩm'"
              @click="confirmDeleteProduct(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Product Create / Edit Form Modal -->
    <ProductForm
      v-model:visible="showFormModal"
      :loading="loadingSave"
      :initial-data="selectedProduct"
      @submit="handleSaveProduct"
    />

    <!-- Import Excel Modal Dialog -->
    <Dialog
      v-model:visible="showImportDialog"
      modal
      header="Import Danh Mục Sản Phẩm Từ Excel (.xlsx)"
      :style="{ width: '800px' }"
      class="rounded-[6px]"
    >
      <div class="flex flex-col gap-3.5 pt-1 text-xs">
        <div class="p-3 bg-sky-50 border border-sky-200 rounded-[6px] text-sky-800">
          <p class="font-semibold m-0 mb-1 flex items-center gap-1.5">
            <i class="pi pi-info-circle text-sky-600"></i>
            Quy định các cột file Excel:
          </p>
          <p class="m-0 text-[11px] text-slate-600">
            Hệ thống hỗ trợ file mẫu <code>san_pham_mau.xlsx</code> với các cột:
            <strong>sku, item description, brand, designation, category, gross price, specs</strong>.
          </p>
        </div>

        <!-- File Upload Drag & Drop Box -->
        <div
          class="border-2 border-dashed border-slate-300 rounded-[6px] p-6 text-center hover:border-sky-500 transition-colors cursor-pointer bg-slate-50/50"
          @click="$refs.fileInput.click()"
        >
          <input
            ref="fileInput"
            type="file"
            accept=".xlsx, .xls"
            class="hidden"
            @change="handleFileSelected"
          />
          <i class="pi pi-file-excel text-4xl text-emerald-600 mb-2"></i>
          <p class="text-xs font-semibold text-slate-800 m-0">
            {{ selectedFileName ? `Đã chọn: ${selectedFileName}` : 'Nhấn để chọn file Excel (.xlsx)' }}
          </p>
          <p class="text-[11px] text-slate-400 m-0 mt-0.5">Hỗ trợ tệp tin định dạng Excel (.xlsx, .xls)</p>
        </div>

        <!-- Preview Table if file parsed -->
        <div v-if="parsedItems.length > 0" class="flex flex-col gap-2">
          <div class="flex justify-between items-center">
            <span class="font-semibold text-slate-800">
              Xem trước 5 dòng đầu (Tổng cộng: <strong class="text-sky-700">{{ parsedItems.length }}</strong> sản phẩm):
            </span>
          </div>

          <div class="border border-slate-200 rounded-[6px] overflow-hidden max-h-48 overflow-y-auto">
            <table class="w-full text-[11px] text-left border-collapse">
              <thead class="bg-slate-100 text-slate-700 font-semibold sticky top-0">
                <tr>
                  <th class="p-1.5 border-b">SKU</th>
                  <th class="p-1.5 border-b">Designation</th>
                  <th class="p-1.5 border-b">Brand</th>
                  <th class="p-1.5 border-b">Category</th>
                  <th class="p-1.5 border-b">Description</th>
                  <th class="p-1.5 border-b text-right">Gross Price</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(it, idx) in parsedItems.slice(0, 5)" :key="idx" class="border-b hover:bg-slate-50">
                  <td class="p-1.5 font-mono font-semibold text-sky-700">{{ it.sku }}</td>
                  <td class="p-1.5 font-mono text-rose-700">{{ it.designation }}</td>
                  <td class="p-1.5">{{ it.brand }}</td>
                  <td class="p-1.5">{{ it.category }}</td>
                  <td class="p-1.5 truncate max-w-[200px]">{{ it['item description'] || it.description }}</td>
                  <td class="p-1.5 text-right font-mono">{{ formatCurrency(it['gross price'] || it.gross_price) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between items-center w-full">
          <Button
            label="Đóng"
            icon="pi pi-times"
            text
            size="small"
            severity="secondary"
            class="rounded-[6px]"
            @click="showImportDialog = false"
          />
          <Button
            label="Xác nhận Import vào Hệ Thống"
            icon="pi pi-upload"
            size="small"
            class="!bg-emerald-600 hover:!bg-emerald-700 !text-white !border-emerald-600 rounded-[6px] !text-xs font-semibold"
            :loading="importing"
            :disabled="parsedItems.length === 0"
            @click="submitImportExcel"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Toolbar from 'primevue/toolbar';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import Badge from 'primevue/badge';
import Dialog from 'primevue/dialog';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import ProductForm from './form.vue';
import { catalogApi } from '../../api/catalog.api';
import { formatCurrency } from '../../utils/format';
import * as XLSX from 'xlsx';

const toast = useToast();

const tableColumns = [
  { field: 'sku', header: 'MÃ SKU', sortable: true, width: '170px' },
  { field: 'designation', header: 'DESIGNATION', sortable: true, width: '150px' },
  { field: 'brand', header: 'BRAND', sortable: true, width: '110px' },
  { field: 'category', header: 'DANH MỤC', sortable: true, width: '130px' },
  { field: 'description', header: 'ITEM DESCRIPTION', sortable: false, width: '300px' },
  { field: 'com', header: 'COM', sortable: false, width: '110px' },
  { field: 'unit', header: 'UNIT', sortable: false, width: '75px', align: 'center' },
  { field: 'gross_price', header: 'GROSS PRICE', sortable: true, width: '150px', align: 'right' },
  { field: 'spec', header: 'THÔNG SỐ KỸ THUẬT (SPECS)', sortable: false, width: '360px' }
];

const products = ref([]);
const loadingProducts = ref(false);
const searchQuery = ref('');
const selectedBrandFilter = ref(null);
const selectedCategoryFilter = ref(null);

const showFormModal = ref(false);
const loadingSave = ref(false);
const selectedProduct = ref({});

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

const openCreateModal = () => {
  selectedProduct.value = {};
  showFormModal.value = true;
};

const openEditModal = (p) => {
  selectedProduct.value = { ...p };
  showFormModal.value = true;
};

const handleSaveProduct = async (formData) => {
  loadingSave.value = true;
  try {
    if (formData.id) {
      // Update
      const res = await catalogApi.updateProduct(formData.id, formData);
      if (res && res.success) {
        toast.add({
          severity: 'success',
          summary: 'Cập nhật thành công',
          detail: `Đã cập nhật thông tin sản phẩm ${formData.sku}.`,
          life: 3000
        });
      }
    } else {
      // Create
      const res = await catalogApi.createProduct(formData);
      if (res && res.success) {
        toast.add({
          severity: 'success',
          summary: 'Thêm mới thành công',
          detail: `Đã lưu sản phẩm ${formData.sku} vào Catalog.`,
          life: 3000
        });
      }
    }
    showFormModal.value = false;
    await fetchProducts();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu sản phẩm',
      detail: err.message || 'Không thể lưu sản phẩm.',
      life: 3000
    });
  } finally {
    loadingSave.value = false;
  }
};

const confirmDeleteProduct = async (p) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa sản phẩm "${p.sku} - ${p.description || p.name}" không?`)) return;

  try {
    await catalogApi.deleteProduct(p.id);
    toast.add({
      severity: 'success',
      summary: 'Đã xóa',
      detail: `Đã xóa sản phẩm ${p.sku}.`,
      life: 3000
    });
    await fetchProducts();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi xóa sản phẩm',
      detail: err.message || 'Không thể xóa sản phẩm.',
      life: 3000
    });
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
