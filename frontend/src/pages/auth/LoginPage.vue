<template>
  <div class="login-wrapper">
    <div class="login-container">
      <!-- 1. Brand Header -->
      <div class="login-header">
        <div class="login-brand-icon">
          <i class="pi pi-bolt"></i>
        </div>
        <h1 class="login-title">Đăng Nhập Smart ERP</h1>
        <p class="login-subtitle">Hệ thống quản trị nguồn lực doanh nghiệp phân tán (Enterprise v2.0)</p>
      </div>

      <!-- 2. Main Login Card (Flat Canvas, Hairline Border, Bo góc 6px) -->
      <div class="login-card">
        <!-- Error Alert -->
        <Message
          v-if="errorMessage"
          severity="error"
          :closable="true"
          class="login-error-msg"
          @close="errorMessage = ''"
        >
          {{ errorMessage }}
        </Message>

        <form @submit.prevent="handleSubmit" class="login-form">
          <div class="form-field">
            <label for="login-email" class="field-label">Email tài khoản</label>
            <IconField>
              <InputIcon class="pi pi-envelope" />
              <InputText
                id="login-email"
                v-model="email"
                type="email"
                placeholder="Ví dụ: admin@erp.local"
                class="w-full"
                required
              />
            </IconField>
          </div>

          <div class="form-field">
            <label for="login-password" class="field-label">Mật khẩu</label>
            <IconField>
              <InputIcon class="pi pi-lock" />
              <InputText
                id="login-password"
                v-model="password"
                type="password"
                placeholder="Nhập mật khẩu..."
                class="w-full"
                required
              />
            </IconField>
          </div>

          <div class="form-options">
            <div class="remember-box">
              <Checkbox v-model="rememberMe" :binary="true" inputId="remember-me" />
              <label for="remember-me" class="remember-label">Ghi nhớ phiên đăng nhập</label>
            </div>
            <span class="password-hint">Mật khẩu mẫu: <code>password123</code></span>
          </div>

          <Button
            type="submit"
            label="Đăng Nhập Hệ Thống"
            icon="pi pi-sign-in"
            :loading="loading"
            class="w-full submit-btn"
          />
        </form>
      </div>

      <!-- 3. Test Account Selector by 11 System Roles -->
      <div class="roles-helper-card">
        <div class="helper-header">
          <div class="helper-title-box">
            <i class="pi pi-users text-primary"></i>
            <span class="helper-title">Chọn nhanh tài khoản theo vai trò (11 Roles chuẩn):</span>
          </div>
          <span class="helper-hint">Bấm vào vai trò để tự động điền</span>
        </div>

        <div class="roles-grid">
          <button
            v-for="role in systemRoles"
            :key="role.code"
            type="button"
            class="role-select-item"
            :class="{ active: email === role.sampleEmail }"
            @click="selectRoleAccount(role)"
          >
            <div class="role-item-info">
              <span class="role-name">{{ role.name }}</span>
              <span class="role-email">{{ role.sampleEmail }}</span>
            </div>
            <Tag
              :severity="role.severity"
              :value="role.badge"
              class="role-badge"
            />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Tag from 'primevue/tag';
import Message from 'primevue/message';
import { SYSTEM_ROLES } from '../../constants/permissions.constant';

const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['login']);

const email = ref('admin@erp.local');
const password = ref('password123');
const rememberMe = ref(true);
const errorMessage = ref('');
const systemRoles = SYSTEM_ROLES;

const selectRoleAccount = (role) => {
  email.value = role.sampleEmail;
  password.value = 'password123';
  errorMessage.value = '';
};

const handleSubmit = () => {
  if (!email.value || !password.value) {
    errorMessage.value = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    return;
  }
  errorMessage.value = '';
  emit('login', { email: email.value.trim(), password: password.value });
};
</script>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background-color: var(--canvas-bg);
  padding: 32px 16px;
}

.login-container {
  width: 100%;
  max-width: 580px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.login-header {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.login-brand-icon {
  width: 44px;
  height: 44px;
  border-radius: 6px;
  background-color: var(--primary-subtle);
  border: 1px solid var(--primary-border);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  margin-bottom: 4px;
}

.login-title {
  font-size: 22px;
  font-weight: 700;
  color: var(--text-main);
  margin: 0;
  letter-spacing: -0.01em;
}

.login-subtitle {
  font-size: 13px;
  color: var(--text-muted);
  margin: 0;
}

.login-card {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 24px;
}

.login-error-msg {
  margin-bottom: 16px;
  border-radius: 6px;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-body);
}

.form-options {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 12px;
}

.remember-box {
  display: flex;
  align-items: center;
  gap: 8px;
}

.remember-label {
  font-size: 12px;
  color: var(--text-body);
  cursor: pointer;
}

.password-hint {
  color: var(--text-muted);
  font-size: 11px;
}

.password-hint code {
  background-color: var(--canvas-bg);
  border: 1px solid var(--border-subtle);
  padding: 1px 4px;
  border-radius: 4px;
  font-family: var(--font-mono);
  color: var(--primary);
}

.submit-btn {
  height: 40px;
  font-size: 14px;
}

/* Helper Roles Panel */
.roles-helper-card {
  background-color: var(--surface-white);
  border: 1px solid var(--border-subtle);
  border-radius: 6px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.helper-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.helper-title-box {
  display: flex;
  align-items: center;
  gap: 8px;
}

.helper-title {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-main);
}

.helper-hint {
  font-size: 11px;
  color: var(--text-muted);
}

.roles-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
  max-height: 240px;
  overflow-y: auto;
  padding-right: 4px;
}

.role-select-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 10px;
  border-radius: 6px;
  border: 1px solid var(--border-subtle);
  background-color: var(--canvas-bg);
  cursor: pointer;
  text-align: left;
  transition: all 0.15s ease;
}

.role-select-item:hover {
  background-color: var(--surface-hover);
  border-color: var(--primary-border);
}

.role-select-item.active {
  background-color: var(--primary-subtle);
  border-color: var(--primary);
}

.role-item-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.role-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--text-main);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.role-email {
  font-size: 11px;
  color: var(--text-muted);
  font-family: var(--font-mono);
}

.role-badge {
  font-size: 10px;
  padding: 2px 6px;
  border-radius: 6px;
  flex-shrink: 0;
}

.w-full {
  width: 100%;
}
</style>
