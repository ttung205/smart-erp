<template>
  <div class="identity-page">
    <!-- Single Page Title -->
    <PageHeader
      title="Quản Lý Định Danh & Phân Quyền (RBAC)"
      subtitle="Quản lý ma trận 11 vai trò nghiệp vụ, cấp phát token JWT phi trạng thái và kiểm soát truy cập"
    />

    <!-- Active User & Claims Inspector 2-Col Grid -->
    <div class="inspector-grid">
      <ClaimsInspector
        :current-user="currentUser"
        :jwt-token="jwtToken"
        @copy-token="$emit('copy-token')"
      />
      <PermissionMatrix
        :current-user="currentUser"
      />
    </div>

    <!-- 11 Standard System Roles Table (Flat Container, Zero Card Inside Card) -->
    <div class="roles-table-container">
      <div class="roles-table-header">
        <div class="roles-title-box">
          <i class="pi pi-shield text-primary"></i>
          <span class="roles-title">Ma trận 11 Vai trò hệ thống chuẩn (System Roles)</span>
        </div>
        <Tag
          severity="info"
          :value="systemRoles.length + ' Vai trò chuẩn'"
          class="roles-count-tag"
        />
      </div>

      <DataTable
        :value="systemRoles"
        class="clean-datatable"
        responsiveLayout="scroll"
      >
        <Column field="name" header="Tên Vai Trò" :sortable="true">
          <template #body="{ data }">
            <span class="font-semibold text-main">{{ data.name }}</span>
          </template>
        </Column>

        <Column field="code" header="Mã Role">
          <template #body="{ data }">
            <span class="role-code-badge">{{ data.code }}</span>
          </template>
        </Column>

        <Column field="sampleEmail" header="Tài Khoản Đại Diện">
          <template #body="{ data }">
            <span class="font-mono text-xs text-muted">{{ data.sampleEmail }}</span>
          </template>
        </Column>

        <Column field="desc" header="Mô Tả Chức Năng">
          <template #body="{ data }">
            <span class="text-body text-xs">{{ data.desc }}</span>
          </template>
        </Column>

        <Column header="Phân Quyền" class="text-center">
          <template #body="{ data }">
            <Tag
              :severity="data.hasFullPermissions ? 'success' : 'secondary'"
              :value="data.hasFullPermissions ? '24/24 Quyền (Toàn quyền)' : '0 Quyền (Chưa phân quyền)'"
              class="perm-status-tag"
            />
          </template>
        </Column>
      </DataTable>
    </div>
  </div>
</template>

<script setup>
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import PageHeader from '../../components/common/PageHeader.vue';
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
</script>

<style scoped>
.identity-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.inspector-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.roles-table-container {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.roles-table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border-subtle);
  background-color: var(--canvas-bg);
}

.roles-title-box {
  display: flex;
  align-items: center;
  gap: 8px;
}

.roles-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-main);
}

.roles-count-tag {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 6px;
}

.role-code-badge {
  font-family: var(--font-mono);
  font-size: 11px;
  font-weight: 600;
  color: var(--primary);
  background-color: var(--primary-subtle);
  padding: 2px 6px;
  border-radius: 6px;
}

.perm-status-tag {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 6px;
}
</style>
