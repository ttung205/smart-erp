<template>
  <div class="permission-matrix-panel">
    <div class="panel-header">
      <span class="panel-title">Ma trận quyền hạn (RBAC)</span>
      <Tag
        severity="secondary"
        :value="systemPermissions.length + ' Quyền hệ thống'"
        class="matrix-tag"
      />
    </div>

    <p class="panel-desc">
      Hệ thống kiểm tra quyền hạn phi trạng thái (Stateless Authorization) trực tiếp từ thông tin trong JWT Token:
    </p>

    <div v-if="currentUser" class="tags-cluster">
      <Tag
        v-for="perm in systemPermissions"
        :key="perm.code"
        :severity="currentUser.permissions && currentUser.permissions.includes(perm.code) ? 'success' : 'secondary'"
        class="perm-tag"
      >
        <template #default>
          <div class="perm-tag-content">
            <i :class="currentUser.permissions && currentUser.permissions.includes(perm.code) ? 'pi pi-check' : 'pi pi-times'"></i>
            <span>{{ perm.code }}</span>
          </div>
        </template>
      </Tag>
    </div>

    <div v-else class="empty-matrix">
      <p class="empty-text">Chưa có thông tin phân quyền.</p>
    </div>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag';
import { SYSTEM_PERMISSIONS } from '../../../constants/permissions.constant';

defineProps({
  currentUser: {
    type: Object,
    default: null
  }
});

const systemPermissions = SYSTEM_PERMISSIONS;
</script>

<style scoped>
.permission-matrix-panel {
  background: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border-subtle);
}

.panel-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--text-main);
}

.matrix-tag {
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 6px;
}

.panel-desc {
  font-size: 12px;
  color: var(--text-muted);
  margin: 0;
}

.tags-cluster {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.perm-tag {
  border-radius: 6px;
  padding: 4px 8px;
  font-size: 11px;
}

.perm-tag-content {
  display: flex;
  align-items: center;
  gap: 6px;
}

.empty-matrix {
  padding: 24px;
  text-align: center;
}

.empty-text {
  font-size: 13px;
  color: var(--text-muted);
  margin: 0;
}
</style>
