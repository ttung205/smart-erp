<template>
  <div class="space-y-3">
    <!-- 1. Header Độc Bản -->
    <PageHeader
      title="Danh Mục Quyền Hạn"
      subtitle="Quản lý định danh các thẩm quyền API và thao tác nghiệp vụ chi tiết theo từng phân hệ ERP"
      badge="Atomic Permissions"
      badge-severity="success"
    >
      <template #actions>
        <Button
          label="Làm mới"
          icon="pi pi-refresh"
          severity="secondary"
          size="small"
          :loading="loading.fetch"
          class="!rounded-[6px]"
          @click="loadPermissions"
        />
        <Tag
          v-if="!canManagePermissions"
          value="Chế độ chỉ xem (permission.view)"
          severity="secondary"
          icon="pi pi-eye"
          class="!text-[11px] !rounded-[6px] !px-2.5 !py-1"
        />
        <Button
          v-if="canManagePermissions"
          label="Thêm quyền hạn"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
          @click="openCreateDialog"
        />
      </template>
    </PageHeader>

    <!-- 2. Toolbar Lọc & Tìm Kiếm -->
    <div class="bg-white border border-slate-200 rounded-[6px] px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <InputText
          v-model="filters.search"
          placeholder="Tìm theo mã hoặc mô tả quyền..."
          size="small"
          class="w-64 !rounded-[6px]"
        />

        <Select
          v-model="filters.module"
          :options="moduleFilterOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả phân hệ"
          size="small"
          class="min-w-[180px] !rounded-[6px]"
        />

        <Button
          v-if="filters.search || filters.module"
          label="Xóa lọc"
          size="small"
          text
          severity="secondary"
          class="!rounded-[6px]"
          @click="resetFilters"
        />
      </div>

      <div class="text-xs text-slate-500 font-mono">
        Tổng số: <strong>{{ filteredPermissions.length }}</strong> quyền hạn
      </div>
    </div>

    <!-- 3. Bảng Dữ Liệu Quyền Hạn -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="filteredPermissions"
        :loading="loading.fetch"
        showGridlines
        paginator
        :rows="12"
        :rowsPerPageOptions="[12, 24, 48]"
        tableStyle="min-width: 50rem"
      >
        <template #empty>
          <div class="p-8 text-center text-slate-500 text-sm">
            Không tìm thấy quyền hạn nào phù hợp.
          </div>
        </template>

        <!-- Cột 1: Mã quyền (code) -->
        <Column field="code" header="Mã Thẩm Quyền (Code)" style="width: 220px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-sky-800 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-[4px]">
              {{ data.code }}
            </span>
          </template>
        </Column>

        <!-- Cột 2: Phân hệ (module) -->
        <Column field="module" header="Phân Hệ / Module" style="width: 170px">
          <template #body="{ data }">
            <Tag
              :value="formatModuleName(data.module)"
              severity="secondary"
              class="!text-xs !rounded-[4px] !font-medium"
            />
          </template>
        </Column>

        <!-- Cột 3: Diễn giải chức năng -->
        <Column field="description" header="Diễn Giải Quyền Hạn & Thẩm Quyền" style="min-width: 300px">
          <template #body="{ data }">
            <span class="text-xs text-slate-700 leading-relaxed">{{ data.description }}</span>
          </template>
        </Column>

        <!-- Cột 4: Thao tác -->
        <Column v-if="canManagePermissions" header="Hành Động" style="width: 120px; text-align: center">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1.5">
              <Button
                icon="pi pi-pencil"
                size="small"
                text
                severity="secondary"
                class="!w-7 !h-7 !p-0 !rounded-[6px]"
                v-tooltip.top="'Chỉnh sửa thông tin quyền'"
                @click="openEditDialog(data)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                text
                severity="danger"
                class="!w-7 !h-7 !p-0 !rounded-[6px]"
                v-tooltip.top="'Xóa quyền hạn'"
                @click="confirmDelete(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- 4. Dialog Thêm / Chỉnh Sửa Quyền Hạn -->
    <Dialog
      v-model:visible="dialogVisible"
      modal
      :header="isEditing ? 'Chỉnh Sửa Thông Tin Quyền Hạn' : 'Định Nghĩa Quyền Hạn Mới'"
      :style="{ width: '480px' }"
    >
      <div class="py-2 flex flex-col gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Mã Quyền (Code) *</label>
          <InputText
            v-model="form.code"
            placeholder="Ví dụ: inventory.transfer, order.approve"
            size="small"
            class="!rounded-[6px] font-mono"
            :disabled="isEditing"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Phân Hệ (Module) *</label>
          <Select
            v-model="form.module"
            :options="availableModules"
            optionLabel="label"
            optionValue="value"
            placeholder="Chọn phân hệ áp dụng..."
            size="small"
            class="w-full !rounded-[6px]"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Diễn Giải Quyền Hạn *</label>
          <InputText
            v-model="form.description"
            placeholder="Mô tả quyền hạn được cấp và phạm vi ảnh hưởng..."
            size="small"
            class="!rounded-[6px]"
          />
        </div>
      </div>

      <template #footer>
        <div class="flex items-center justify-end gap-2 w-full pt-2">
          <Button
            label="Hủy Bỏ"
            text
            size="small"
            severity="secondary"
            class="!rounded-[6px]"
            @click="dialogVisible = false"
          />
          <Button
            :label="isEditing ? 'Lưu Thay Đổi' : 'Thêm Quyền'"
            size="small"
            :loading="loading.save"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
            @click="savePermission"
          />
        </div>
      </template>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import PageHeader from '../../components/common/PageHeader.vue';
import { identityApi } from '../../api/identity.api';
import { canManage } from '../../utils/rbac';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const canManagePermissions = computed(() => canManage('permission', props.currentUser));

const toast = useToast();

const permissions = ref([]);

const loading = reactive({
  fetch: false,
  save: false
});

const filters = reactive({
  search: '',
  module: null
});

const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = reactive({
  code: '',
  module: 'sales',
  description: ''
});

const availableModules = [
  { label: 'Bán Hàng (Sales)', value: 'sales' },
  { label: 'Mua Hàng (Purchase)', value: 'purchase' },
  { label: 'Quản Lý Kho (Inventory)', value: 'inventory' },
  { label: 'Danh Mục & Master Data', value: 'catalog' },
  { label: 'Tài Chính Kế Toán (Finance)', value: 'finance' },
  { label: 'Kỹ Thuật & Tích Hợp (Technical)', value: 'technical' },
  { label: 'Báo Cáo & Thống Kê (Analytics)', value: 'analytics' },
  { label: 'Trí Tuệ Nhân Tạo (AI Copilot)', value: 'ai' },
  { label: 'Quản Trị Hệ Thống (System Admin)', value: 'admin' }
];

const moduleFilterOptions = computed(() => [
  { label: 'Tất cả phân hệ', value: null },
  ...availableModules
]);

const filteredPermissions = computed(() => {
  return permissions.value.filter(p => {
    if (filters.module && p.module !== filters.module) {
      return false;
    }
    if (filters.search && filters.search.trim()) {
      const q = filters.search.toLowerCase().trim();
      const code = (p.code || '').toLowerCase();
      const desc = (p.description || '').toLowerCase();
      return code.includes(q) || desc.includes(q);
    }
    return true;
  });
});

const loadPermissions = async () => {
  loading.fetch = true;
  try {
    const res = await identityApi.getPermissions();
    if (res?.success) {
      permissions.value = res.data;
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi tải quyền hạn',
      detail: err.message || 'Không thể nạp danh sách quyền',
      life: 4000
    });
  } finally {
    loading.fetch = false;
  }
};

const resetFilters = () => {
  filters.search = '';
  filters.module = null;
};

const openCreateDialog = () => {
  isEditing.value = false;
  editingId.value = null;
  form.code = '';
  form.module = 'sales';
  form.description = '';
  dialogVisible.value = true;
};

const openEditDialog = (perm) => {
  isEditing.value = true;
  editingId.value = perm.id;
  form.code = perm.code;
  form.module = perm.module;
  form.description = perm.description;
  dialogVisible.value = true;
};

const savePermission = async () => {
  if (!form.code.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Mã quyền không được để trống', life: 3000 });
    return;
  }
  if (!form.description.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Diễn giải quyền không được để trống', life: 3000 });
    return;
  }

  loading.save = true;
  try {
    const payload = {
      code: form.code.trim().toLowerCase(),
      module: form.module,
      description: form.description.trim()
    };

    if (isEditing.value) {
      await identityApi.updatePermission(editingId.value, payload);
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật quyền hạn', life: 3000 });
    } else {
      await identityApi.createPermission(payload);
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm quyền hạn mới', life: 3000 });
    }

    dialogVisible.value = false;
    await loadPermissions();
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi lưu', detail: err.message || 'Không thể lưu quyền hạn', life: 4000 });
  } finally {
    loading.save = false;
  }
};

const confirmDelete = async (perm) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa quyền "${perm.code}" không?`)) {
    return;
  }
  try {
    await identityApi.deletePermission(perm.id);
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: `Đã xóa quyền ${perm.code}`, life: 3000 });
    await loadPermissions();
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi xóa', detail: err.message || 'Không thể xóa quyền hạn', life: 4000 });
  }
};

const formatModuleName = (mod) => {
  const found = availableModules.find(m => m.value === mod);
  return found ? found.label : mod.toUpperCase();
};

onMounted(() => {
  loadPermissions();
});
</script>
