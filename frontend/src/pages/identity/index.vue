<template>
  <div class="flex flex-col gap-4">
    <!-- Single Page Title -->
    <PageHeader
      title="Quản Lý Định Danh & Phân Quyền (RBAC)"
      subtitle="Quản lý ma trận 11 vai trò nghiệp vụ, cấp phát token JWT phi trạng thái và kiểm soát truy cập"
    />

    <!-- Active User & Claims Inspector 2-Col Grid -->
    <div class="grid grid-cols-2 gap-4">
      <ClaimsInspector
        :current-user="currentUser"
        :jwt-token="jwtToken"
        @copy-token="$emit('copy-token')"
      />
      <PermissionMatrix
        :current-user="currentUser"
      />
    </div>

    <!-- 11 Standard System Roles Table (100% Tailwind, Flat, Zero Card Inside Card) -->
    <div class="bg-white border border-slate-200 rounded-[6px] overflow-hidden flex flex-col shadow-none">
      <div class="flex justify-between items-center px-4 py-3 border-b border-slate-200 bg-slate-50">
        <div class="flex items-center gap-2">
          <i class="pi pi-shield text-indigo-600"></i>
          <span class="text-sm font-bold text-slate-900">Ma trận 11 Vai trò hệ thống chuẩn (System Roles)</span>
        </div>
        <Tag
          severity="info"
          :value="systemRoles.length + ' Vai trò chuẩn'"
          class="text-[11px] px-2 py-0.5 rounded-[6px]"
        />
      </div>

      <CommonDataTable
        :value="systemRoles"
        :columns="tableColumns"
        :paginator="false"
      >
        <template #col-name="{ data }">
          <span class="font-semibold text-slate-900">{{ data.name }}</span>
        </template>

        <template #col-code="{ data }">
          <span class="font-mono text-[11px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-[4px] border border-indigo-200">{{ data.code }}</span>
        </template>

        <template #col-sampleEmail="{ data }">
          <span class="font-mono text-xs text-slate-500">{{ data.sampleEmail }}</span>
        </template>

        <template #col-desc="{ data }">
          <span class="text-slate-700 text-xs">{{ data.desc }}</span>
        </template>

        <template #col-permissions="{ data }">
          <div class="text-center">
            <Tag
              :severity="data.hasFullPermissions ? 'success' : 'secondary'"
              :value="data.hasFullPermissions ? '24/24 Quyền (Toàn quyền)' : '0 Quyền (Chưa phân quyền)'"
              class="text-[11px] px-2 py-0.5 rounded-[6px]"
            />
          </div>
        </template>
      </CommonDataTable>
    </div>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
import CommonDataTable from '../../components/common/CommonDataTable.vue';
import ClaimsInspector from './components/ClaimsInspector.vue';
import PermissionMatrix from './components/PermissionMatrix.vue';
import { SYSTEM_ROLES } from '../../constants/permissions.constant';

defineProps({
  currentUser: {
    type: Object,
    default: null
  },
  jwtToken: {
    type: String,
    default: ''
  }
});

defineEmits(['copy-token']);

const systemRoles = SYSTEM_ROLES;

const tableColumns = [
  { field: 'name', header: 'Tên Vai Trò', sortable: true },
  { field: 'code', header: 'Mã Role', sortable: true },
  { field: 'sampleEmail', header: 'Tài Khoản Đại Diện', sortable: false },
  { field: 'desc', header: 'Mô Tả Chức Năng', sortable: false },
  { field: 'permissions', header: 'Phân Quyền', sortable: false, align: 'center' }
];
</script>
