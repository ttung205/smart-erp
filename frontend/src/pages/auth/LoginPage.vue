<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 p-4">
    <div class="w-full max-w-[580px] flex flex-col gap-4">
      <!-- 1. Brand Header -->
      <div class="text-center flex flex-col items-center gap-1.5">
        <div class="w-11 h-11 rounded-[6px] bg-indigo-50 border border-indigo-200 text-indigo-600 flex items-center justify-center text-xl mb-1">
          <i class="pi pi-bolt"></i>
        </div>
        <h1 class="text-[22px] font-bold text-slate-900 m-0 tracking-tight">Đăng Nhập Smart ERP</h1>
        <p class="text-[13px] text-slate-500 m-0">Hệ thống quản trị nguồn lực doanh nghiệp phân tán</p>
      </div>

      <!-- 2. Main Login Card (100% TailwindCSS, Flat, Hairline Border, Bo góc 6px) -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-6 shadow-none">
        <!-- Error Alert -->
        <Message
          v-if="errorMessage"
          severity="error"
          :closable="true"
          class="mb-4 rounded-[6px]"
          @close="errorMessage = ''"
        >
          {{ errorMessage }}
        </Message>

        <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
          <div class="flex flex-col gap-1.5">
            <label for="login-email" class="text-xs font-semibold text-slate-700">Email tài khoản</label>
            <IconField>
              <InputIcon class="pi pi-envelope" />
              <InputText
                id="login-email"
                v-model="email"
                type="email"
                placeholder="Ví dụ: admin@erp.local"
                class="w-full rounded-[6px]"
                required
              />
            </IconField>
          </div>

          <div class="flex flex-col gap-1.5">
            <label for="login-password" class="text-xs font-semibold text-slate-700">Mật khẩu</label>
            <IconField>
              <InputIcon class="pi pi-lock" />
              <InputText
                id="login-password"
                v-model="password"
                type="password"
                placeholder="Nhập mật khẩu..."
                class="w-full rounded-[6px]"
                required
              />
            </IconField>
          </div>

          <div class="flex justify-between items-center text-xs">
            <div class="flex items-center gap-2">
              <Checkbox v-model="rememberMe" :binary="true" inputId="remember-me" />
              <label for="remember-me" class="text-xs text-slate-700 cursor-pointer select-none">Ghi nhớ phiên đăng nhập</label>
            </div>
            <span class="text-slate-500 text-[11px]">Mật khẩu mẫu: <code class="bg-slate-50 border border-slate-200 px-1 py-0.5 rounded-[4px] font-mono text-indigo-600">password123</code></span>
          </div>

          <Button
            type="submit"
            label="Đăng Nhập Hệ Thống"
            icon="pi pi-sign-in"
            :loading="loading"
            class="w-full h-10 text-sm rounded-[6px]"
          />
        </form>
      </div>

      <!-- 3. Helper Panel: Chọn nhanh tài khoản theo vai trò -->
      <div class="bg-white border border-slate-200 rounded-[6px] p-4 flex flex-col gap-3 shadow-none">
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-2">
            <i class="pi pi-users text-indigo-600"></i>
            <span class="text-xs font-bold text-slate-900">Chọn nhanh tài khoản theo vai trò (11 Roles chuẩn):</span>
          </div>
          <span class="text-[11px] text-slate-500">Bấm vào vai trò để tự động điền</span>
        </div>

        <div class="grid grid-cols-2 gap-2 max-h-[240px] overflow-y-auto pr-1">
          <button
            v-for="role in systemRoles"
            :key="role.code"
            type="button"
            class="flex justify-between items-center p-2 rounded-[6px] border border-slate-200 bg-slate-50 cursor-pointer text-left transition-colors hover:bg-slate-100 hover:border-indigo-200"
            :class="{ 'bg-indigo-50 border-indigo-600': email === role.sampleEmail }"
            @click="selectRoleAccount(role)"
          >
            <div class="flex flex-col min-w-0 pr-2">
              <span class="text-xs font-semibold text-slate-900 truncate">{{ role.name }}</span>
              <span class="text-[11px] text-slate-500 font-mono">{{ role.sampleEmail }}</span>
            </div>
            <Tag
              :severity="role.severity"
              :value="role.badge"
              class="text-[10px] py-0.5 px-1.5 rounded-[6px] shrink-0"
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

defineProps({
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
