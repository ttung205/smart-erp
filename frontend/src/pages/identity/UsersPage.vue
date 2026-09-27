<template>
  <div class="space-y-3">
    <!-- 1. Header Độc Bản -->
    <PageHeader
      title="Danh Sách Nhân Viên"
      subtitle="Quản lý hồ sơ tài khoản nhân sự và phân bổ vai trò nghiệp vụ trong toàn hệ thống"
      badge="Nhân sự & Tài khoản"
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
          @click="loadAllData"
        />
        <Button
          label="Thêm nhân viên"
          icon="pi pi-user-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
          @click="openCreateDialog"
        />
      </template>
    </PageHeader>

    <!-- 2. Toolbar Tìm Kiếm & Lọc -->
    <div class="bg-white border border-slate-200 rounded-[6px] px-3.5 py-2.5 flex flex-wrap items-center justify-between gap-3">
      <div class="flex flex-wrap items-center gap-3">
        <InputText
          v-model="filters.search"
          placeholder="Tìm theo tên hoặc email..."
          size="small"
          class="w-64 !rounded-[6px]"
        />

        <Select
          v-model="filters.departmentId"
          :options="departmentOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả phòng ban"
          size="small"
          class="min-w-[180px] !rounded-[6px]"
        />

        <Select
          v-model="filters.roleId"
          :options="roleOptions"
          optionLabel="label"
          optionValue="value"
          placeholder="Tất cả vai trò"
          size="small"
          class="min-w-[180px] !rounded-[6px]"
        />

        <Button
          v-if="filters.search || filters.departmentId || filters.roleId"
          label="Xóa lọc"
          size="small"
          text
          severity="secondary"
          class="!rounded-[6px]"
          @click="resetFilters"
        />
      </div>

      <div class="text-xs text-slate-500 font-mono">
        Tổng số: <strong>{{ filteredUsers.length }}</strong> nhân viên
      </div>
    </div>

    <!-- 3. Bảng Dữ Liệu Nhân Viên -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="filteredUsers"
        :loading="loading.fetch"
        showGridlines
        paginator
        :rows="10"
        :rowsPerPageOptions="[10, 20, 50]"
        tableStyle="min-width: 50rem"
      >
        <template #empty>
          <div class="p-8 text-center text-slate-500 text-sm">
            Không tìm thấy nhân viên nào phù hợp.
          </div>
        </template>

        <!-- Cột 1: Thông tin nhân sự -->
        <Column header="Nhân Viên" style="min-width: 220px">
          <template #body="{ data }">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center font-bold text-xs shrink-0">
                {{ data.name ? data.name.charAt(0).toUpperCase() : 'U' }}
              </div>
              <div class="flex flex-col">
                <span class="text-sm font-semibold text-slate-800 leading-tight">{{ data.name }}</span>
                <span class="text-xs text-slate-500 font-mono">{{ data.email }}</span>
              </div>
            </div>
          </template>
        </Column>

        <!-- Cột 2: Phòng ban -->
        <Column field="department_name" header="Phòng Ban" style="min-width: 170px">
          <template #body="{ data }">
            <span class="text-xs font-medium text-slate-700">
              {{ data.department_name || 'Chưa gán phòng ban' }}
            </span>
          </template>
        </Column>

        <!-- Cột 3: Vai trò đảm nhiệm -->
        <Column header="Vai Trò" style="min-width: 220px">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1">
              <template v-if="data.roles && data.roles.length > 0">
                <Tag
                  v-for="r in data.roles"
                  :key="r.role_id"
                  :value="r.name"
                  severity="secondary"
                  class="!text-[10px] !px-1.5 !py-0.5 !rounded-[4px]"
                />
              </template>
              <span v-else class="text-xs text-slate-400 italic">Chưa gán vai trò</span>
            </div>
          </template>
        </Column>

        <!-- Cột 4: Trạng thái tài khoản -->
        <Column header="Trạng Thái" style="width: 140px; text-align: center">
          <template #body="{ data }">
            <Tag
              :value="data.is_active ? 'Đang hoạt động' : 'Tạm khóa'"
              :severity="data.is_active ? 'success' : 'danger'"
              class="!text-xs !rounded-[6px]"
            />
          </template>
        </Column>

        <!-- Cột 5: Ngày tạo -->
        <Column header="Ngày Tạo" style="width: 140px">
          <template #body="{ data }">
            <span class="text-xs font-mono text-slate-500">{{ formatDate(data.created_at) }}</span>
          </template>
        </Column>

        <!-- Cột 6: Thao tác -->
        <Column header="Hành Động" style="width: 120px; text-align: center">
          <template #body="{ data }">
            <Button
              icon="pi pi-user-edit"
              size="small"
              text
              severity="secondary"
              class="!w-7 !h-7 !p-0 !rounded-[6px]"
              v-tooltip.top="'Chỉnh sửa thông tin nhân viên'"
              @click="openEditDialog(data)"
            />
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- 4. Dialog Thêm / Chỉnh Sửa Nhân Viên -->
    <Dialog
      v-model:visible="dialogVisible"
      modal
      :header="isEditing ? 'Chỉnh Sửa Thông Tin Nhân Viên' : 'Thêm Nhân Viên Mới'"
      :style="{ width: '500px' }"
    >
      <div class="py-2 flex flex-col gap-4">
        <!-- Họ tên -->
        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Họ và Tên *</label>
          <InputText
            v-model="form.name"
            placeholder="Nhập họ và tên nhân viên..."
            size="small"
            class="!rounded-[6px]"
          />
        </div>

        <!-- Email -->
        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Địa Chỉ Email *</label>
          <InputText
            v-model="form.email"
            placeholder="email@example.com"
            size="small"
            class="!rounded-[6px] font-mono"
            :disabled="isEditing"
          />
        </div>

        <!-- Mật khẩu -->
        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">
            {{ isEditing ? 'Mật Khẩu Mới (Để trống nếu giữ nguyên)' : 'Mật Khẩu Khởi Tạo *' }}
          </label>
          <InputText
            v-model="form.password"
            type="password"
            placeholder="Tối thiểu 6 ký tự..."
            size="small"
            class="!rounded-[6px]"
          />
        </div>

        <!-- Phòng ban -->
        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Phòng Ban</label>
          <Select
            v-model="form.department_id"
            :options="departments"
            optionLabel="name"
            optionValue="id"
            placeholder="Chọn phòng ban trực thuộc..."
            size="small"
            class="w-full !rounded-[6px]"
          />
        </div>

        <!-- Phân vai trò -->
        <div class="flex flex-col gap-1.5">
          <label class="block text-xs font-semibold text-slate-700">Vai Trò Đảm Nhiệm</label>
          <MultiSelect
            v-model="form.role_ids"
            :options="roles"
            optionLabel="name"
            optionValue="id"
            placeholder="Chọn một hoặc nhiều vai trò..."
            size="small"
            display="chip"
            class="w-full !rounded-[6px]"
          />
        </div>

        <!-- Trạng thái hoạt động -->
        <div class="flex items-center gap-2 pt-1">
          <Checkbox
            v-model="form.is_active"
            :binary="true"
            inputId="userActiveInput"
          />
          <label for="userActiveInput" class="text-xs font-medium text-slate-700 cursor-pointer select-none">
            Tài khoản đang hoạt động (Cho phép đăng nhập)
          </label>
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
            :label="isEditing ? 'Lưu Thay Đổi' : 'Tạo Nhân Viên'"
            size="small"
            :loading="loading.save"
            class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
            @click="saveUser"
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
import MultiSelect from 'primevue/multiselect';
import Checkbox from 'primevue/checkbox';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import PageHeader from '../../components/common/PageHeader.vue';
import { identityApi } from '../../api/identity.api';

const toast = useToast();

const users = ref([]);
const departments = ref([]);
const roles = ref([]);

const loading = reactive({
  fetch: false,
  save: false
});

const filters = reactive({
  search: '',
  departmentId: null,
  roleId: null
});

const dialogVisible = ref(false);
const isEditing = ref(false);
const editingId = ref(null);

const form = reactive({
  name: '',
  email: '',
  password: '',
  department_id: null,
  role_ids: [],
  is_active: true
});

const departmentOptions = computed(() => [
  { label: 'Tất cả phòng ban', value: null },
  ...departments.value.map(d => ({ label: d.name, value: d.id }))
]);

const roleOptions = computed(() => [
  { label: 'Tất cả vai trò', value: null },
  ...roles.value.map(r => ({ label: r.name, value: r.id }))
]);

const filteredUsers = computed(() => {
  return users.value.filter(u => {
    if (filters.departmentId && u.department_id !== filters.departmentId) {
      return false;
    }
    if (filters.roleId && (!u.role_ids || !u.role_ids.includes(filters.roleId))) {
      return false;
    }
    if (filters.search && filters.search.trim()) {
      const q = filters.search.toLowerCase().trim();
      const name = (u.name || '').toLowerCase();
      const email = (u.email || '').toLowerCase();
      return name.includes(q) || email.includes(q);
    }
    return true;
  });
});

const loadAllData = async () => {
  loading.fetch = true;
  try {
    const [usersRes, deptsRes, rolesRes] = await Promise.all([
      identityApi.getUsers(),
      identityApi.getDepartments(),
      identityApi.getRoles()
    ]);
    if (usersRes?.success) users.value = usersRes.data;
    if (deptsRes?.success) departments.value = deptsRes.data;
    if (rolesRes?.success) roles.value = rolesRes.data;
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi tải dữ liệu',
      detail: err.message || 'Không thể nạp danh sách nhân sự',
      life: 4000
    });
  } finally {
    loading.fetch = false;
  }
};

const resetFilters = () => {
  filters.search = '';
  filters.departmentId = null;
  filters.roleId = null;
};

const openCreateDialog = () => {
  isEditing.value = false;
  editingId.value = null;
  form.name = '';
  form.email = '';
  form.password = '';
  form.department_id = null;
  form.role_ids = [];
  form.is_active = true;
  dialogVisible.value = true;
};

const openEditDialog = (user) => {
  isEditing.value = true;
  editingId.value = user.id;
  form.name = user.name;
  form.email = user.email;
  form.password = '';
  form.department_id = user.department_id;
  form.role_ids = user.role_ids ? [...user.role_ids] : [];
  form.is_active = Boolean(user.is_active);
  dialogVisible.value = true;
};

const saveUser = async () => {
  if (!form.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Họ tên không được để trống', life: 3000 });
    return;
  }
  if (!isEditing.value && !form.email.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Email không được để trống', life: 3000 });
    return;
  }

  loading.save = true;
  try {
    const payload = {
      name: form.name.trim(),
      email: form.email.trim(),
      department_id: form.department_id,
      role_ids: form.role_ids,
      is_active: form.is_active
    };
    if (form.password) {
      payload.password = form.password;
    }

    if (isEditing.value) {
      await identityApi.updateUser(editingId.value, payload);
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật thông tin nhân viên', life: 3000 });
    } else {
      await identityApi.createUser(payload);
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm nhân viên mới', life: 3000 });
    }

    dialogVisible.value = false;
    await loadAllData();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Thao tác thất bại',
      detail: err.message || 'Lỗi khi lưu dữ liệu nhân viên',
      life: 4000
    });
  } finally {
    loading.save = false;
  }
};

const formatDate = (isoStr) => {
  if (!isoStr) return '---';
  try {
    const d = new Date(isoStr);
    return d.toLocaleDateString('vi-VN');
  } catch {
    return isoStr;
  }
};

onMounted(() => {
  loadAllData();
});
</script>
