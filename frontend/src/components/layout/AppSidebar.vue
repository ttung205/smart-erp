<template>
  <aside class="layout-sidebar">
    <!-- Brand Header -->
    <div class="sidebar-header">
      <div class="brand-box">
        <i class="pi pi-bolt brand-icon"></i>
      </div>
      <div class="brand-info">
        <span class="brand-title">{{ appConfig.appName }}</span>
        <span class="brand-tag">{{ appConfig.version }}</span>
      </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-menu">
      <template v-for="(cat, idx) in menuCategories" :key="idx">
        <span class="menu-category-title">{{ cat.category }}</span>
        <div class="menu-group">
          <button
            v-for="item in cat.items"
            :key="item.id"
            class="menu-nav-button"
            :class="{ active: currentTab === item.id }"
            @click="$emit('select-tab', item.id)"
          >
            <i :class="[item.icon, 'menu-nav-icon']"></i>
            <span class="menu-nav-label">{{ item.label }}</span>
            <span v-if="item.badge" class="menu-nav-badge">{{ item.badge }}</span>
          </button>
        </div>
      </template>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
      <div class="arch-info-box">
        <span class="arch-label">Kiến trúc hệ thống</span>
        <span class="arch-value">{{ appConfig.architecture }}</span>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { appConfig } from '../../config/app.config';
import { menuCategories } from '../../config/menu.config';

defineProps({
  currentTab: {
    type: String,
    required: true
  }
});

defineEmits(['select-tab']);
</script>

<style scoped>
.layout-sidebar {
  width: 256px;
  background-color: var(--surface-white);
  border-right: 1px solid var(--border-subtle);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  z-index: 100;
}

.sidebar-header {
  height: 56px;
  padding: 0 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  border-bottom: 1px solid var(--border-subtle);
}

.brand-box {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  background-color: var(--primary-subtle);
  border: 1px solid var(--primary-border);
  display: flex;
  align-items: center;
  justify-content: center;
}

.brand-icon {
  font-size: 16px;
  color: var(--primary);
}

.brand-info {
  display: flex;
  align-items: center;
  gap: 8px;
}

.brand-title {
  font-family: var(--font-heading);
  font-size: 15px;
  font-weight: 700;
  color: var(--text-main);
}

.brand-tag {
  font-size: 11px;
  font-weight: 600;
  background-color: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  color: var(--primary);
  padding: 2px 6px;
  border-radius: 6px;
}

.sidebar-menu {
  flex: 1;
  padding: 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  overflow-y: auto;
}

.menu-category-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-muted);
  letter-spacing: 0.04em;
  padding: 8px 8px 4px 8px;
}

.menu-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.menu-nav-button {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  border-radius: 6px;
  border: 1px solid transparent;
  background: transparent;
  color: var(--text-body);
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
  width: 100%;
}

.menu-nav-button:hover {
  background-color: var(--surface-hover);
  color: var(--text-main);
}

.menu-nav-button.active {
  background-color: var(--primary-subtle);
  border-color: var(--primary-border);
  color: var(--primary);
  font-weight: 600;
}

.menu-nav-icon {
  font-size: 16px;
  color: var(--text-muted);
}

.menu-nav-button.active .menu-nav-icon {
  color: var(--primary);
}

.menu-nav-label {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.menu-nav-badge {
  font-size: 10px;
  font-weight: 600;
  background: var(--surface-white);
  border: 1px solid var(--border-subtle);
  color: var(--text-muted);
  padding: 1px 6px;
  border-radius: 6px;
}

.menu-nav-button.active .menu-nav-badge {
  background: var(--surface-white);
  border-color: var(--primary-border);
  color: var(--primary);
}

.sidebar-footer {
  padding: 12px 16px;
  border-top: 1px solid var(--border-subtle);
}

.arch-info-box {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.arch-label {
  font-size: 11px;
  color: var(--text-muted);
}

.arch-value {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-main);
}
</style>
