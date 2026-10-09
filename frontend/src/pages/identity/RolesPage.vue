<template>
  <div class="space-y-3">
    <!-- 1. Header Độc Bản -->
    <PageHeader
      title="Quản Lý Vai Trò & Thẩm Quyền"
      subtitle="Thiết lập các vai trò chức danh, theo dõi số lượng nhân sự đảm nhiệm và phân bổ ma trận quyền hạn"
      badge="RBAC Matrix"
      badge-severity="warn"
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
        <Tag
          v-if="!canManageRoles"
          value="Chế độ chỉ xem (role.view)"
          severity="secondary"
          icon="pi pi-eye"
          class="!text-[11px] !rounded-[6px] !px-2.5 !py-1"
        />
        <Button
          v-if="canManageRoles"
          label="Thêm vai trò"
          icon="pi pi-plus"
          size="small"
          class="!bg-sky-600 hover:!bg-sky-700 !text-white !border-sky-600 !rounded-[6px]"
          @click="openCreateRoleDialog"
        />
      </template>
    </PageHeader>

    <!-- 2. Bảng Dữ Liệu Vai Trò -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden">
      <DataTable
        :value="roles"
        :loading="loading.fetch"
        showGridlines
        tableStyle="min-width: 50rem"
      >
        <template #empty>
          <div class="p-8 text-center text-slate-500 text-sm">
            Chưa có vai trò nào trong hệ thống.
          </div>
        </template>

        <!-- Cột 1: Mã vai trò -->
        <Column field="code" header="Mã Vai Trò" style="width: 170px">
          <template #body="{ data }">
            <span class="font-mono text-xs font-bold text-sky-700 bg-sky-50 border border-sky-200 px-2 py-0.5 rounded-[4px]">
              {{ data.code }}
            </span>
          </template>
        </Column>

        <!-- Cột 2: Tên vai trò -->
        <Column field="name" header="Tên Vai Trò" style="min-width: 200px">
          <template #body="{ data }">
            <div class="font-semibold text-slate-800 text-sm">{{ data.name }}</div>
            <div class="text-xs text-slate-400 mt-0.5">{{ data.description || 'Chưa có mô tả' }}</div>
          </template>
        </Column>

        <!-- Cột 3: Đếm số lượng người dùng (User Count) -->
        <Column header="Số Nhân Sự Đảm Nhiệm" style="width: 180px; text-align: center">
          <template #body="{ data }">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] border text-xs font-semibold"
              :class="data.users_count > 0 ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-slate-50 border-slate-200 text-slate-500'"
            >
              <i class="pi pi-users text-xs"></i>
              <span>{{ data.users_count }} người dùng</span>
            </div>
          </template>
        </Column>

        <!-- Cột 4: Số lượng quyền hạn (Permissions Count) -->
        <Column header="Quyền Hạn Được Cấp" style="width: 170px; text-align: center">
          <template #body="{ data }">
            <Badge
              :value="`${data.permission_ids ? data.permission_ids.length : 0} quyền`"
              :severity="data.permission_ids && data.permission_ids.length > 0 ? 'info' : 'secondary'"
              class="cursor-pointer"
              v-tooltip.top="'Click để xem và phân chia quyền'"
              @click="openPermissionsModal(data)"
            />
          </template>
        </Column>

        <!-- Cột 5: Thao tác -->
        <Column v-if="canManageRoles" header="Hành Động" style="width: 150px; text-align: center">
          <template #body="{ data }">
            <div class="flex items-center justify-center gap-1.5">
              <Button
                icon="pi pi-key"
                size="small"
                outlined
                severity="warn"
                class="!w-7 !h-7 !p-0 !rounded-[6px]"
                v-tooltip.top="'Phân quyền cho vai trò'"
                @click="openPermissionsModal(data)"
              />
              <Button
                icon="pi pi-pencil"
                size="small"
                outlined
                severity="info"
                class="!w-7 !h-7 !p-0 !rounded-[6px]"
                v-tooltip.top="'Chỉnh sửa tên & mô tả'"
                @click="openEditRoleDialog(data)"
              />
              <Button
                v-if="data.code !== 'SUPER_ADMIN'"
                icon="pi pi-trash"
                size="small"
                outlined
                severity="danger"
                class="!w-7 !h-7 !p-0 !rounded-[6px]"
                v-tooltip.top="'Xóa vai trò'"
                @click="confirmDeleteRole(data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <!-- 3. Modal Thêm / Chỉnh Sửa Vai Trò -->
    <RoleFormDialog
      v-model:visible="roleDialogVisible"
      :is-editing="isEditingRole"
      :form="roleForm"
      :loading="loading.saveRole"
      @save="saveRole"
    />

    <!-- 4. Modal Chia Quyền (Permission Assignment Matrix) -->
    <RolePermissionMatrixDialog
      v-model:visible="permModalVisible"
      v-model:selected-perm-ids="selectedPermIds"
      :role="activeRole"
      :all-permissions="allPermissions"
      :permissions-by-module="permissionsByModule"
      :loading="loading.savePerms"
      @save="saveRolePermissions"
    />
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Badge from 'primevue/badge';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import PageHeader from '../../components/common/PageHeader.vue';
import RoleFormDialog from '../../components/identity/RoleFormDialog.vue';
import RolePermissionMatrixDialog from '../../components/identity/RolePermissionMatrixDialog.vue';
import { identityApi } from '../../api/identity.api';
import { canManage } from '../../utils/rbac';

const props = defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const canManageRoles = computed(() => canManage('role', props.currentUser));

const toast = useToast();

const roles = ref([]);
const allPermissions = ref([]);

const loading = reactive({
  fetch: false,
  saveRole: false,
  savePerms: false
});

// Role create/edit dialog
const roleDialogVisible = ref(false);
const isEditingRole = ref(false);
const editingRoleId = ref(null);
const roleForm = reactive({
  code: '',
  name: '',
  description: ''
});

// Permission assignment modal
const permModalVisible = ref(false);
const activeRole = ref(null);
const selectedPermIds = ref([]);

const permissionsByModule = computed(() => {
  const map = {};
  for (const p of allPermissions.value) {
    const mod = p.module || 'general';
    if (!map[mod]) map[mod] = [];
    map[mod].push(p);
  }
  return map;
});

const loadAllData = async () => {
  loading.fetch = true;
  try {
    const [rolesRes, permsRes] = await Promise.all([
      identityApi.getRoles(),
      identityApi.getPermissions()
    ]);
    if (rolesRes?.success) roles.value = rolesRes.data;
    if (permsRes?.success) allPermissions.value = permsRes.data;
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi nạp dữ liệu',
      detail: err.message || 'Không thể tải danh sách vai trò',
      life: 4000
    });
  } finally {
    loading.fetch = false;
  }
};

const openCreateRoleDialog = () => {
  isEditingRole.value = false;
  editingRoleId.value = null;
  roleForm.code = '';
  roleForm.name = '';
  roleForm.description = '';
  roleDialogVisible.value = true;
};

const openEditRoleDialog = (role) => {
  isEditingRole.value = true;
  editingRoleId.value = role.id;
  roleForm.code = role.code;
  roleForm.name = role.name;
  roleForm.description = role.description || '';
  roleDialogVisible.value = true;
};

const saveRole = async () => {
  if (!roleForm.name.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Tên vai trò không được để trống', life: 3000 });
    return;
  }
  if (!isEditingRole.value && !roleForm.code.trim()) {
    toast.add({ severity: 'warn', summary: 'Cảnh báo', detail: 'Mã vai trò không được để trống', life: 3000 });
    return;
  }

  loading.saveRole = true;
  try {
    if (isEditingRole.value) {
      await identityApi.updateRole(editingRoleId.value, {
        name: roleForm.name.trim(),
        description: roleForm.description.trim()
      });
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã cập nhật vai trò', life: 3000 });
    } else {
      await identityApi.createRole({
        code: roleForm.code.trim().toUpperCase(),
        name: roleForm.name.trim(),
        description: roleForm.description.trim()
      });
      toast.add({ severity: 'success', summary: 'Thành công', detail: 'Đã thêm vai trò mới', life: 3000 });
    }

    roleDialogVisible.value = false;
    await loadAllData();
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi', detail: err.message || 'Không thể lưu vai trò', life: 4000 });
  } finally {
    loading.saveRole = false;
  }
};

const confirmDeleteRole = async (role) => {
  if (!confirm(`Bạn có chắc chắn muốn xóa vai trò "${role.name}" (${role.code}) không?`)) {
    return;
  }
  try {
    await identityApi.deleteRole(role.id);
    toast.add({ severity: 'success', summary: 'Đã xóa', detail: `Đã xóa vai trò ${role.name}`, life: 3000 });
    await loadAllData();
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Lỗi xóa', detail: err.message || 'Không thể xóa vai trò', life: 4000 });
  }
};

// Phân quyền cho role
const openPermissionsModal = (role) => {
  activeRole.value = role;
  selectedPermIds.value = role.permission_ids ? [...role.permission_ids] : [];
  permModalVisible.value = true;
};

const togglePerm = (permId) => {
  const idx = selectedPermIds.value.indexOf(permId);
  if (idx > -1) {
    selectedPermIds.value.splice(idx, 1);
  } else {
    selectedPermIds.value.push(permId);
  }
};

const isAllModuleSelected = (perms) => {
  return perms.every(p => selectedPermIds.value.includes(p.id));
};

const toggleSelectModule = (perms) => {
  if (isAllModuleSelected(perms)) {
    const ids = perms.map(p => p.id);
    selectedPermIds.value = selectedPermIds.value.filter(id => !ids.includes(id));
  } else {
    for (const p of perms) {
      if (!selectedPermIds.value.includes(p.id)) {
        selectedPermIds.value.push(p.id);
      }
    }
  }
};

const saveRolePermissions = async () => {
  if (!activeRole.value) return;
  loading.savePerms = true;
  try {
    await identityApi.updateRolePermissions(activeRole.value.id, selectedPermIds.value);
    toast.add({
      severity: 'success',
      summary: 'Thành công',
      detail: `Đã cập nhật ${selectedPermIds.value.length} quyền cho vai trò ${activeRole.value.name}`,
      life: 3000
    });
    permModalVisible.value = false;
    await loadAllData();
  } catch (err) {
    toast.add({
      severity: 'error',
      summary: 'Lỗi cập nhật quyền',
      detail: err.message || 'Không thể lưu phân quyền',
      life: 4000
    });
  } finally {
    loading.savePerms = false;
  }
};

const formatModuleName = (mod) => {
  const map = {
    sales: 'Bán Hàng (Sales)',
    purchase: 'Mua Hàng (Purchase)',
    inventory: 'Quản Lý Kho (Inventory)',
    catalog: 'Danh Mục & Master Data',
    finance: 'Tài Chính Kế Toán (Finance)',
    technical: 'Kỹ Thuật & Tích Hợp (Technical)',
    analytics: 'Báo Cáo & Thống Kê (Analytics)',
    ai: 'Trí Tuệ Nhân Tạo (AI Copilot)',
    admin: 'Quản Trị Hệ Thống (System Admin)'
  };
  return map[mod] || mod.toUpperCase();
};

onMounted(() => {
  loadAllData();
});
</script>
