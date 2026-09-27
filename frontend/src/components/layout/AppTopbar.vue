<template>
  <header class="layout-topbar">
    <!-- Left: Breadcrumb / System Scope indicator -->
    <div class="topbar-left">
      <div class="system-indicator">
        <i class="pi pi-server text-muted"></i>
        <span class="system-name">Hệ Thống Phân Tán Smart ERP</span>
      </div>
    </div>

    <!-- Center/Right: Microservices Status -->
    <div class="service-status-cluster">
      <Tag
        :severity="health.identity ? 'success' : 'danger'"
        :value="'Identity :8001 • ' + (health.identity ? 'Sẵn sàng' : 'Mất kết nối')"
        class="status-tag"
      />
      <Tag
        :severity="health.catalog ? 'success' : 'danger'"
        :value="'Catalog :8002 • ' + (health.catalog ? 'Sẵn sàng' : 'Mất kết nối')"
        class="status-tag"
      />
      <Tag
        :severity="health.inventory ? 'success' : 'danger'"
        :value="'Inventory :8004 • ' + (health.inventory ? 'Sẵn sàng' : 'Mất kết nối')"
        class="status-tag"
      />
      <Button
        icon="pi pi-refresh"
        text
        size="small"
        severity="secondary"
        @click="$emit('refresh-health')"
        v-tooltip.bottom="'Kiểm tra lại trạng thái API'"
      />
    </div>

    <!-- Right: Current User Widget -->
    <div class="topbar-right">
      <div v-if="currentUser" class="user-box">
        <Avatar
          :label="currentUser.name ? currentUser.name.charAt(0) : 'U'"
          shape="circle"
          class="user-avatar"
        />
        <div class="user-info">
          <span class="user-name">{{ currentUser.name }}</span>
          <span class="user-dept">{{ currentUser.department }}</span>
        </div>
        <Button
          icon="pi pi-sign-out"
          text
          severity="danger"
          size="small"
          @click="$emit('logout')"
          v-tooltip.bottom="'Đăng xuất'"
        />
      </div>
      <Tag
        v-else
        severity="secondary"
        icon="pi pi-lock"
        value="Chưa đăng nhập"
        class="status-tag"
      />
    </div>
  </header>
</template>

<script setup>
import Tag from 'primevue/tag';
import Avatar from 'primevue/avatar';
import Button from 'primevue/button';

defineProps({
  currentUser: {
    type: Object,
    default: null
  },
  health: {
    type: Object,
    required: true
  }
});

defineEmits(['refresh-health', 'logout']);
</script>

<style scoped>
.layout-topbar {
  height: 56px;
  background-color: var(--surface-white);
  border-bottom: 1px solid var(--border-subtle);
  padding: 0 24px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  position: sticky;
  top: 0;
  z-index: 90;
}

.topbar-left {
  display: flex;
  align-items: center;
}

.system-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: var(--text-body);
}

.service-status-cluster {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-tag {
  font-size: 11px;
  padding: 4px 8px;
  border-radius: 6px;
}

.topbar-right {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-box {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid var(--border-subtle);
  background-color: var(--surface-white);
}

.user-avatar {
  background-color: var(--primary) !important;
  color: #ffffff !important;
  font-weight: 700;
  font-size: 12px;
  width: 28px;
  height: 28px;
}

.user-info {
  display: flex;
  flex-direction: column;
  line-height: 1.2;
}

.user-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-main);
}

.user-dept {
  font-size: 10px;
  color: var(--text-muted);
}
</style>
