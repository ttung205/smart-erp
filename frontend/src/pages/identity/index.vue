<template>
  <div class="identity-page">
    <!-- Single Page Title -->
    <PageHeader
      title="Quản Lý Tài Khoản & Phân Quyền"
      subtitle="Quản lý định danh người dùng, xác thực tập trung và kiểm thử ma trận phân quyền động (RBAC)"
    />

    <!-- Personas Selection Section (Flat Panel) -->
    <div class="identity-section-panel">
      <PersonaGrid
        :current-email="currentUser ? currentUser.email : ''"
        @select-persona="$emit('quick-login', $event)"
      />

      <div class="manual-login-bar">
        <span class="login-prompt">Hoặc đăng nhập tài khoản khác:</span>
        <div class="login-controls">
          <InputText
            v-model="loginForm.email"
            placeholder="Email (ví dụ: sales@erp.local)"
            class="login-input"
            size="small"
          />
          <InputText
            v-model="loginForm.password"
            type="password"
            placeholder="Mật khẩu"
            class="login-input"
            size="small"
          />
          <Button
            label="Đăng nhập"
            icon="pi pi-sign-in"
            size="small"
            :loading="loadingLogin"
            @click="$emit('manual-login', loginForm)"
          />
        </div>
      </div>
    </div>

    <!-- Inspector 2-Col Grid (Flat Sibling Panels) -->
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
  </div>
</template>

<script setup>
import { reactive } from 'vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import PageHeader from '../../components/common/PageHeader.vue';
import PersonaGrid from './components/PersonaGrid.vue';
import ClaimsInspector from './components/ClaimsInspector.vue';
import PermissionMatrix from './components/PermissionMatrix.vue';

defineProps({
  currentUser: {
    type: Object,
    default: null
  },
  jwtToken: {
    type: String,
    default: ''
  },
  loadingLogin: {
    type: Boolean,
    default: false
  }
});

defineEmits(['quick-login', 'manual-login', 'copy-token']);

const loginForm = reactive({
  email: 'sales@erp.local',
  password: 'password123'
});
</script>

<style scoped>
.identity-page {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.identity-section-panel {
  background: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.manual-login-bar {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--border-subtle);
}

.login-prompt {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-body);
}

.login-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.login-input {
  flex: 1;
}

.inspector-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
</style>
