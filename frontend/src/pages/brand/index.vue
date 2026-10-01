<template>
  <div class="space-y-3 pb-8">
    <!-- Header -->
    <PageHeader
      title="Thương Hiệu (Brands)"
      subtitle="Quản lý danh sách các thương hiệu & hãng sản xuất thiết bị công nghiệp trong catalog"
      badge="Catalog"
      badge-severity="info"
    >
      <template #actions>
        <Button
          label="Thêm Thương Hiệu Mới"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
          @click="openCreateModal"
        />
      </template>
    </PageHeader>

    <!-- Toolbar Tìm kiếm & Thống kê -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo tên thương hiệu..."
              class="w-72 rounded-[6px]"
              size="small"
            />
          </IconField>
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2">
          <Button
            icon="pi pi-refresh"
            text
            size="small"
            severity="secondary"
            class="rounded-[6px]"
            v-tooltip.bottom="'Tải lại dữ liệu'"
            @click="fetchBrands"
          />
          <Tag
            severity="secondary"
            :value="filteredBrands.length + ' Thương hiệu'"
            class="text-[11px] px-2 py-1 rounded-[6px]"
          />
        </div>
      </template>
    </Toolbar>

    <!-- Data Table using CommonDataTable -->
    <CommonDataTable
      :value="filteredBrands"
      :columns="tableColumns"
      :loading="loading"
      :rows="10"
      :paginator="filteredBrands.length > 10"
      emptyIcon="pi pi-bookmark"
      emptyTitle="Chưa có thương hiệu nào"
      emptySubtitle="Bấm nút 'Thêm Thương Hiệu Mới' ở trên để tạo thương hiệu đầu tiên."
    >
      <!-- Prepend column for STT -->
      <template #prepend-columns>
        <Column header="#" style="width: 50px" class="text-center">
          <template #body="{ index }">
            <span class="text-xs font-mono text-slate-400 font-semibold">{{ index + 1 }}</span>
          </template>
        </Column>
      </template>

      <!-- Custom column: name -->
      <template #col-name="{ data }">
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 rounded bg-indigo-50 border border-indigo-200 flex items-center justify-center text-indigo-700 font-bold text-xs">
            {{ data.name.charAt(0).toUpperCase() }}
          </div>
          <span class="font-bold text-slate-900 text-xs">{{ data.name }}</span>
        </div>
      </template>

      <!-- Custom column: products_count -->
      <template #col-products_count="{ data }">
        <div class="text-center">
          <Tag
            :severity="data.products_count > 0 ? 'info' : 'secondary'"
            :value="`${data.products_count || 0} sản phẩm`"
            class="text-[11px] px-2 py-0.5 rounded-[4px]"
          />
        </div>
      </template>

      <!-- Custom column: created_at -->
      <template #col-created_at="{ data }">
        <span class="text-xs text-slate-500 font-mono">{{ formatDate(data.created_at) }}</span>
      </template>

      <!-- Thao tác -->
      <Column header="Thao Tác" style="width: 130px" class="text-center">
        <template #body="{ data }">
          <div class="flex items-center justify-center gap-1">
            <Button
              icon="pi pi-pencil"
              size="small"
              text
              class="!text-xs !p-1.5 rounded-[6px] !text-sky-600 hover:!bg-sky-50"
              v-tooltip.bottom="'Chỉnh sửa tên thương hiệu'"
              @click="openEditModal(data)"
            />

            <Button
              icon="pi pi-trash"
              size="small"
              text
              class="!text-xs !p-1.5 rounded-[6px] !text-red-500 hover:!bg-red-50"
              :disabled="data.products_count > 0"
              v-tooltip.bottom="data.products_count > 0 ? 'Không thể xóa thương hiệu đang có sản phẩm' : 'Xóa thương hiệu'"
              @click="confirmDeleteBrand(data)"
            />
          </div>
        </template>
      </Column>
    </CommonDataTable>

    <!-- Create / Edit Dialog -->
    <Dialog
      v-model:visible="showModal"
      modal
      :header="editingBrand ? 'Chỉnh Sửa Thương Hiệu' : 'Thêm Mới Thương Hiệu'"
      :style="{ width: '420px' }"
      class="rounded-[6px]"
    >
      <div class="flex flex-col gap-3 pt-1 text-xs">
        <div class="flex flex-col gap-1">
          <label for="brand-name" class="font-semibold text-slate-700">Tên thương hiệu <span class="text-red-600">*</span></label>
          <InputText
            id="brand-name"
            v-model="brandForm.name"
            placeholder="Ví dụ: LG, SKF, Toshiba, Samsung..."
            class="w-full rounded-[6px]"
            size="small"
            @keyup.enter="saveBrand"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-2">
          <Button
            label="Hủy bỏ"
            text
            size="small"
            severity="secondary"
            class="rounded-[6px]"
            @click="showModal = false"
          />
          <Button
            :label="editingBrand ? 'Cập Nhật' : 'Lưu Thương Hiệu'"
            icon="pi pi-check"
            size="small"
            :loading="saving"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px]"
            @click="saveBrand"
          />
        </div>
      </template>
    </Dialog>
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
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import { catalogApi } from '../../api/catalog.api';

const toast = useToast();

const brands = ref([]);
const loading = ref(false);
const searchQuery = ref('');

const showModal = ref(false);
const editingBrand = ref(null);
const saving = ref(false);
const brandForm = ref({ name: '' });

const tableColumns = [
  { field: 'name', header: 'Tên Thương Hiệu', sortable: true, width: 'auto' },
  { field: 'products_count', header: 'Số Lượng Sản Phẩm', sortable: true, width: '180px', align: 'center' },
  { field: 'created_at', header: 'Ngày Tạo', sortable: true, width: '180px' }
];

const filteredBrands = computed(() => {
  if (!searchQuery.value) return brands.value;
  const q = searchQuery.value.toLowerCase().trim();
  return brands.value.filter((b) => b.name && b.name.toLowerCase().includes(q));
});

const formatDate = (d) => {
  if (!d) return '-';
  try {
    return new Date(d).toLocaleString('vi-VN');
  } catch {
    return d;
  }
};

const fetchBrands = async () => {
  loading.value = true;
  try {
    const res = await catalogApi.getBrands();
    if (res && res.data) {
      brands.value = res.data;
    }
  } catch (err) {
    console.error('Fetch brands error:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi nạp thương hiệu',
      detail: 'Không thể kết nối đến Catalog Service.',
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingBrand.value = null;
  brandForm.value = { name: '' };
  showModal.value = true;
};

const openEditModal = (brand) => {
  editingBrand.value = brand;
  brandForm.value = { name: brand.name };
  showModal.value = true;
};

const saveBrand = async () => {
  const name = brandForm.value.name.trim();
  if (!name) {
    alert('Vui lòng nhập tên thương hiệu!');
    return;
  }

  saving.value = true;
  try {
    if (editingBrand.value) {
      await catalogApi.updateBrand(editingBrand.value.id, { name });
      toast.add({
        severity: 'success',
        summary: 'Thành công',
        detail: `Đã cập nhật thương hiệu "${name}"`,
        life: 3000
      });
    } else {
      await catalogApi.createBrand({ name });
      toast.add({
        severity: 'success',
        summary: 'Thành công',
        detail: `Đã tạo thương hiệu mới "${name}"`,
        life: 3000
      });
    }
    showModal.value = false;
    await fetchBrands();
  } catch (err) {
    console.error('Save brand error:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi lưu thương hiệu',
      detail: err.message || 'Không thể lưu thương hiệu.',
      life: 3000
    });
  } finally {
    saving.value = false;
  }
};

const confirmDeleteBrand = async (brand) => {
  if (confirm(`Bạn có chắc chắn muốn xóa thương hiệu "${brand.name}"?`)) {
    try {
      await catalogApi.deleteBrand(brand.id);
      toast.add({
        severity: 'success',
        summary: 'Đã xóa',
        detail: `Đã xóa thương hiệu "${brand.name}"`,
        life: 3000
      });
      await fetchBrands();
    } catch (err) {
      toast.add({
        severity: 'error',
        summary: 'Lỗi xóa',
        detail: err.message || 'Không thể xóa thương hiệu.',
        life: 3000
      });
    }
  }
};

onMounted(() => {
  fetchBrands();
});
</script>
