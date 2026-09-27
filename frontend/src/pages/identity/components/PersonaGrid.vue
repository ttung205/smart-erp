<template>
  <div class="persona-section">
    <div class="section-desc">
      <span class="text-sm text-body font-medium">Chọn tài khoản mẫu để chuyển đổi phiên làm việc và kiểm tra phân quyền RBAC:</span>
    </div>

    <div class="persona-grid">
      <div
        v-for="persona in personas"
        :key="persona.email"
        class="persona-tile"
        :class="{ active: currentEmail === persona.email }"
        @click="$emit('select-persona', persona.email)"
      >
        <div class="persona-icon-box">
          <i :class="[persona.icon, 'text-primary']"></i>
        </div>
        <div class="persona-details">
          <span class="persona-name">{{ persona.name }}</span>
          <span class="persona-role">{{ persona.roleName }}</span>
          <span class="persona-email">{{ persona.email }}</span>
        </div>
        <Tag
          :severity="persona.severity"
          :value="persona.badge"
          class="persona-tag"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import Tag from 'primevue/tag';
import { SEED_PERSONAS } from '../../../constants/permissions.constant';

defineProps({
  currentEmail: {
    type: String,
    default: ''
  }
});

defineEmits(['select-persona']);

const personas = SEED_PERSONAS;
</script>

<style scoped>
.persona-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.section-desc {
  margin-bottom: 4px;
}

.persona-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
}

.persona-tile {
  background: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 12px;
  display: flex;
  align-items: center;
  gap: 12px;
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}

.persona-tile:hover {
  background-color: var(--surface-hover);
  border-color: var(--primary-border);
}

.persona-tile.active {
  background-color: var(--primary-subtle);
  border-color: var(--primary);
}

.persona-icon-box {
  width: 36px;
  height: 36px;
  border-radius: 6px;
  background-color: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  flex-shrink: 0;
}

.persona-tile.active .persona-icon-box {
  background-color: var(--surface-white);
  border-color: var(--primary-border);
}

.persona-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.persona-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--text-main);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.persona-role {
  font-size: 11px;
  color: var(--text-muted);
}

.persona-email {
  font-size: 11px;
  color: var(--text-muted);
  font-family: var(--font-mono);
}

.persona-tag {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 6px;
  flex-shrink: 0;
}
</style>
