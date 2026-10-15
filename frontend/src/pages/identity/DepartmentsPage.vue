<template>
  <div class="space-y-3">
    <!-- 1. Header Độc Bản -->
    <PageHeader
      title="Quản Lý Cơ Cấu Phòng Ban"
      subtitle="Định nghĩa cơ cấu tổ chức, phòng ban nghiệp vụ và theo dõi quy mô nhân sự trực thuộc"
      badge="Cơ cấu tổ chức"
      badge-severity="info"
    >
      <template #actions>
        <Button
          label="Làm mới"
          icon="pi pi-refresh"
          severity="secondary"
          size="small"
          :loading="loading.fetch"
          class="!rounded-[6px]"
          @click="loadDepartments"
        />
        <Tag
          v-if="!canManageDepartments"
          value="Chế độ chỉ xem (department.view)"
          severity="secondary"
          icon="pi pi-eye"
          class="!text-[11px] !rounded-[6px] !px-2.5 !py-1"
        />
        <Button
          v-if="canManageDepartments"
          label="Thêm phòng ban"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
          @click="openCreateDialog"
        />
      </template>
    </PageHeader>

    <!-- 2. Toolbar Tìm Kiếm -->
    <div class="bg-white border border-slate-200 rounded-[6px] px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <InputText
          v-model="searchQuery"
          placeholder="Tìm theo mã hoặc tên phòng ban..."
          size="small"
          class="w-72 !rounded-[6px]"
        />
        <Button
          v-if="searchQuery"
          label="Xóa lọc"
          size="small"
          text
          severity="secondary"
          class="!rounded-[6px]"
          @click="searchQuery = ''"
        />
      </div>

      <div class="text-xs text-slate-500 font-mono">
        Tổng số: <strong>{{ filteredDepartments.length }}</strong> phòng ban
      </div>
    </div>

    <!-- 3. Bảng Dữ Liệu Phòng Ban -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="filteredDepartments"
        :loading="loading.fetch"
        showGridlines
        tableStyle="min-width: 50rem"
      >
        <template #empty>
          <div class="p-8 text-center text-slate-500 text-sm">
            Không tìm thấy phòng ban nào phù hợp.
          </div>
        </template>

        <!-- Cột 1: Mã phòng ban -->
        <Column field="code" header="Mã Phòng Ban" style="width: 160px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-sky-800 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-[4px]">
              {{ data.code }}
            </span>
          </template>
        </Column>

        <!-- Cột 2: Tên phòng ban -->
        <Column field="name" header="Tên Đơn Vị / Phòng Ban" style="min-width: 220px">
          <template #body="{ data }">
            <div class="font-semibold text-slate-800 text-sm">{{ data.name }}</div>
          </template>
        </Column>

        <!-- Cột 3: Diễn giải nhiệm vụ -->
        <Column field="description" header="Phạm Vi Trách Nhiệm" style="min-width: 300px">
          <template #body="{ data }">
            <span class="text-xs text-slate-600 leading-relaxed">{{ data.description || 'Chưa cập nhật mô tả' }}</span>
          </template>
        </Column>

        <!-- Cột: Trưởng phòng (người duyệt đơn E-Office) -->
        <Column field="manager_name" header="Trưởng Phòng" style="min-width: 200px">
          <template #body="{ data }">
            <span v-if="data.manager_name" class="text-xs text-slate-800">{{ data.manager_name }}</span>
            <span v-else class="text-xs text-amber-600">Chưa chỉ định</span>
          </template>
        </Column>

        <!-- Cột 4: Số lượng nhân viên trực thuộc -->
        <Column header="Quy Mô Nhân Sự" style="width: 170px; text-align: center">
          <template #body="{ data }">
            <div
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] border text-xs font-semibold"
              :class="data.users_count > 0 ? 'bg-sky-50 border-sky-200 text-sky-800' : 'bg-slate-50 border-slate-200 text-slate-500'"
            >
              <i class="pi pi-users text-xs"></i>
              <span>{{ data.users_count }} nhân sự</span>
            </div>
          </template>
        </Column>

        <!-- Cột 5: Thao tác -->
        <Column v-if="canManageDepartments" header="Hành Động" style="width: 120px; text-align: center">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1.5">
              <Button
                icon="pi pi-pencil"
                size="small"
                text
                severity="secondary"
                class="!w-7 !h-7 !p-0 !rounded-[6px]"
                v-tooltip.top="'Chỉnh sửa thông tin phòng ban'"
                @click="openEditDialog(data)"
              />
              <Button
                icon="pi pi-trash"
                size="small"
                text
                severity="danger"
                class="!w-7 !h-7 !p-0 !rounded-[6px]"
                v-tooltip.top="'Xóa phòng ban'"
                @click="confirmDelete(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- 4. Dialog Thêm / Chỉnh Sửa Phòng Ban -->
    <DepartmentFormDialog
      v-model:visible="dialogVisible"
      :is-editing="isEditing"
      :form="form"
      :users="users"
      :loading="loading.save"
      @save="saveDepartment"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import DepartmentFormDialog from '../../components/identity/DepartmentFormDialog.vue';
import { identityApi } from '../../api/identity.api';
import { canManage } from '../../utils/rbac';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const canManageDepartments = computed(() => canManage('department', props.currentUser));

const toast = useToast();

const departments = ref([]);
const users = ref([]);
const searchQuery = ref('');

const loading = reactive({
  fetch: false,
  save: false
});

const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = reactive({
  code: '',
  name: '',
  description: '',
  manager_id: null
});

const filteredDepartments = computed(() => {
  if (!searchQuery.value.trim()) return departments.value;
  const q = searchQuery.value.toLowerCase().trim();
  return departments.value.filter(d => {
    const code = (d.code || '').toLowerCase();
    const name = (d.name || '').toLowerCase();
    const desc = (d.description || '').toLowerCase();
    return code.includes(q) || name.includes(q) || desc.includes(q);
  });
});

const loadDepartments = async () => {
  loading.fetch = true;
  try {
    const res = await identityApi.getDepartments();
    if (res?.success) {
      departments.value = res.data;
    }
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi nạp dữ liệu',
      detail: err.message || 'Không thể tải danh sách phòng ban',
      life: 4000
    });
  } finally {
    loading.fetch = false;
  }
};

const openCreateDialog = () => {
  isEditing.value = false;
  editingId.value = null;
  form.code = '';
  form.name = '';
  form.description = '';
  form.manager_id = null;
  dialogVisible.value = true;
};

const openEditDialog = (dept) => {
  isEditing.value = true;
  editingId.value = dept.id;
  form.code = dept.code;
  form.name = dept.name;
  form.description = dept.description || '';
  form.manager_id = dept.manager_id || null;
  dialogVisible.value = true;
};

const saveDepartment = async () => {
  if (!form.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Tên phòng ban không được để trống', life: 3000 });
    return;
  }
  if (!isEditing.value && !form.code.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Mã phòng ban không được để trống', life: 3000 });
    return;
  }

  loading.save = true;
  try {
    const payload = {
      code: form.code.trim().toUpperCase(),
      name: form.name.trim(),
      description: form.description.trim(),
      manager_id: form.manager_id || null
    };

    if (isEditing.value) {
      await identityApi.updateDepartment(editingId.value, payload);
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật thông tin phòng ban', life: 3000 });
    } else {
      await identityApi.createDepartment(payload);
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm phòng ban mới', life: 3000 });
    }

    dialogVisible.value = false;
    await loadDepartments();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Thao tác thất bại',
      detail: err.message || 'Lỗi khi lưu phòng ban',
      life: 4000
    });
  } finally {
    loading.save = false;
  }
};

const confirmDelete = async (dept) => {
  let msg = `Bạn có chắc chắn muốn xóa phòng ban "${dept.name}" (${dept.code})?`;
  if (dept.users_count > 0) {
    msg += `\nLưu ý: Đang có ${dept.users_count} nhân sự thuộc phòng ban này. Sau khi xóa, các nhân sự này sẽ được chuyển về trạng thái Chưa gán phòng ban.`;
  }
  if (!confirm(msg)) {
    return;
  }

  try {
    await identityApi.deleteDepartment(dept.id);
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: `Đã xóa phòng ban ${dept.name}`, life: 3000 });
    await loadDepartments();
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi xóa', detail: err.message || 'Không thể xóa phòng ban', life: 4000 });
  }
};

const loadUsers = async () => {
  try {
    const res = await identityApi.getUsers();
    users.value = res?.data || [];
  } catch {
    users.value = [];
  }
};

onMounted(() => {
  loadDepartments();
  if (canManageDepartments.value) loadUsers();
});
</script>
