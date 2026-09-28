<template>
  <div class="flex flex-col gap-4 pb-10">
    <PageHeader
      title="Quản Lý Danh Mục Ngành Hàng (Category Master)"
      subtitle="Phân loại hàng hóa, nhóm sản phẩm và danh mục kinh doanh"
      badge="Catalog Service"
      badge-severity="info"
    >
      <template #actions>
        <Button
          label="Thêm danh mục mới"
          icon="pi pi-plus"
          size="small"
          class="rounded-[6px] !text-xs !py-1.5 !px-3 !bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 font-medium"
          @click="openCreateModal"
        />
      </template>
    </PageHeader>

    <!-- Toolbar -->
    <Toolbar class="bg-white border border-slate-200 rounded-[6px] px-3 py-2 shadow-none">
      <template #start>
        <div class="flex items-center gap-2">
          <IconField>
            <InputIcon class="pi pi-search" />
            <InputText
              v-model="searchQuery"
              placeholder="Tìm theo tên hoặc mã danh mục..."
              class="w-72 rounded-[6px]"
              size="small"
            />
          </IconField>
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
            @click="fetchCategories"
          />
          <Tag
            severity="secondary"
            :value="filteredCategories.length + ' Danh mục'"
            class="text-[11px] px-2 py-1 rounded-[6px]"
          />
        </div>
      </template>
    </Toolbar>

    <!-- Data Table -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden shadow-none">
      <DataTable
        :value="filteredCategories"
        showGridlines
        class="clean-datatable"
        responsiveLayout="scroll"
        :paginator="filteredCategories.length > 10"
        :rows="10"
        :loading="loading"
      >
        <Column header="#" style="width: 50px" class="text-center">
          <template #body="{ index }">
            <span class="text-xs font-mono text-slate-400 font-semibold">{{ index + 1 }}</span>
          </template>
        </Column>

        <Column field="name" header="Tên Danh Mục" :sortable="true" style="width: 220px">
          <template #body="{ data }">
            <span class="font-bold text-slate-900 text-xs">{{ data.name }}</span>
          </template>
        </Column>

        <Column field="code" header="Mã Code" :sortable="true" style="width: 160px">
          <template #body="{ data }">
            <span class="font-mono text-xs text-sky-700 bg-sky-50 px-2 py-0.5 rounded-[4px] border border-sky-200">
              {{ data.code }}
            </span>
          </template>
        </Column>

        <Column field="description" header="Mô Tả Danh Mục" style="min-width: 250px">
          <template #body="{ data }">
            <span class="text-xs text-slate-600">{{ data.description || '-' }}</span>
          </template>
        </Column>

        <Column field="products_count" header="Số Lượng Sản Phẩm" :sortable="true" style="width: 170px" class="text-center">
          <template #body="{ data }">
            <Tag
              :severity="data.products_count > 0 ? 'info' : 'secondary'"
              :value="`${data.products_count || 0} sản phẩm`"
              class="text-[11px] px-2 py-0.5 rounded-[4px]"
            />
          </template>
        </Column>

        <Column field="created_at" header="Ngày Tạo" :sortable="true" style="width: 170px">
          <template #body="{ data }">
            <span class="text-xs text-slate-500 font-mono">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>

        <!-- Thao tác -->
        <Column header="Thao Tác" style="width: 130px" class="text-center">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1">
              <!-- Sửa -->
              <Button
                icon="pi pi-pencil"
                size="small"
                text
                class="!text-xs !p-1.5 rounded-[6px] !text-sky-600 hover:!bg-sky-50"
                v-tooltip.bottom="'Chỉnh sửa danh mục'"
                @click="openEditModal(data)"
              />

              <!-- Xóa -->
              <Button
                icon="pi pi-trash"
                size="small"
                text
                class="!text-xs !p-1.5 rounded-[6px] !text-red-500 hover:!bg-red-50"
                :disabled="data.products_count > 0"
                v-tooltip.bottom="data.products_count > 0 ? 'Không thể xóa danh mục đang có sản phẩm' : 'Xóa danh mục'"
                @click="confirmDeleteCategory(data)"
              />
            </div>
          </template>
        </Column>

        <template #empty>
          <div class="py-10 text-center flex flex-col items-center gap-2">
            <i class="pi pi-th-large text-3xl text-slate-300"></i>
            <p class="text-xs font-semibold text-slate-700 m-0">Chưa có danh mục nào</p>
            <p class="text-[11px] text-slate-400 m-0">Bấm nút "Thêm danh mục mới" ở trên để khởi tạo.</p>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Create / Edit Dialog -->
    <Dialog
      v-model:visible="showModal"
      modal
      :header="editingCat ? 'Chỉnh Sửa Danh Mục' : 'Thêm Mới Danh Mục'"
      :style="{ width: '480px' }"
      class="rounded-[6px]"
    >
      <div class="flex flex-col gap-3 pt-1 text-xs">
        <div class="flex flex-col gap-1">
          <label for="cat-name" class="font-semibold text-slate-700">Tên danh mục <span class="text-red-600">*</span></label>
          <InputText
            id="cat-name"
            v-model="catForm.name"
            placeholder="Ví dụ: Tủ lạnh, Máy giặt, Vòng bi..."
            class="w-full rounded-[6px]"
            size="small"
          />
        </div>

        <div class="flex flex-col gap-1">
          <label for="cat-desc" class="font-semibold text-slate-700">Mô tả chi tiết</label>
          <Textarea
            id="cat-desc"
            v-model="catForm.description"
            rows="3"
            placeholder="Mô tả ngành hàng, đặc tính hoặc ghi chú phân loại..."
            class="w-full rounded-[6px] text-xs"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <Button
            label="Hủy bỏ"
            icon="pi pi-times"
            text
            size="small"
            severity="secondary"
            class="rounded-[6px]"
            @click="showModal = false"
          />
          <Button
            :label="editingCat ? 'Lưu thay đổi' : 'Tạo danh mục'"
            icon="pi pi-check"
            size="small"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 rounded-[6px] !text-xs font-semibold"
            :loading="saving"
            @click="saveCategory"
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
import Textarea from 'primevue/textarea';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Dialog from 'primevue/dialog';
import PageHeader from '../../components/common/PageHeader.vue';
import { catalogApi } from '../../api/catalog.api';

const toast = useToast();

const categories = ref([]);
const loading = ref(false);
const searchQuery = ref('');

const showModal = ref(false);
const editingCat = ref(null);
const saving = ref(false);
const catForm = ref({ name: '', description: '' });

const filteredCategories = computed(() => {
  if (!searchQuery.value) return categories.value;
  const q = searchQuery.value.toLowerCase().trim();
  return categories.value.filter(
    (c) =>
      (c.name && c.name.toLowerCase().includes(q)) ||
      (c.code && c.code.toLowerCase().includes(q)) ||
      (c.description && c.description.toLowerCase().includes(q))
  );
});

const formatDate = (d) => {
  if (!d) return '-';
  try {
    return new Date(d).toLocaleString('vi-VN');
  } catch {
    return d;
  }
};

const fetchCategories = async () => {
  loading.value = true;
  try {
    const res = await catalogApi.getCategories();
    if (res && res.data) {
      categories.value = res.data;
    }
  } catch (err) {
    console.error('Fetch categories error:', err);
    toast.add({
      severity: 'error',
      summary: 'Lỗi nạp danh mục',
      detail: 'Không thể kết nối đến Catalog Service.',
      life: 3000
    });
  } finally {
    loading.value = false;
  }
};

const openCreateModal = () => {
  editingCat.value = null;
  catForm.value = { name: '', description: '' };
  showModal.value = true;
};

const openEditModal = (c) => {
  editingCat.value = c;
  catForm.value = { name: c.name, description: c.description || '' };
  showModal.value = true;
};

const saveCategory = async () => {
  const name = catForm.value.name.trim();
  if (!name) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Tên danh mục không được để trống.', life: 3000 });
    return;
  }

  saving.value = true;
  try {
    if (editingCat.value) {
      await catalogApi.updateCategory(editingCat.value.id, {
        name,
        description: catForm.value.description
      });
      toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã cập nhật danh mục "${name}".`, life: 3000 });
    } else {
      await catalogApi.createCategory({
        name,
        description: catForm.value.description
      });
      toast.add({ severity: 'success', summary: 'Thành công', detail: `Đã tạo mới danh mục "${name}".`, life: 3000 });
    }
    showModal.value = false;
    await fetchCategories();
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: err.message || 'Không thể lưu danh mục.', life: 3000 });
  } finally {
    saving.value = false;
  }
};

const confirmDeleteCategory = async (c) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa danh mục "${c.name}" không?`)) return;

  try {
    await catalogApi.deleteCategory(c.id);
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: `Đã xóa danh mục "${c.name}".`, life: 3000 });
    await fetchCategories();
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi xóa', detail: err.message || 'Không thể xóa danh mục này.', life: 3000 });
  }
};

onMounted(() => {
  fetchCategories();
});
</script>
