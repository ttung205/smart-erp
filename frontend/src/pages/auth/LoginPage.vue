<template>
  <div class="min-h-screen flex items-center justify-center bg-slate-50 p-4">
    <div class="w-full max-w-[440px] flex flex-col gap-4">
      <div class="text-center flex flex-col items-center gap-1">
        <div class="w-11 h-11 rounded-[6px] bg-indigo-50 border border-indigo-200 text-indigo-600 flex items-center justify-center text-xl mb-1">
          <i class="pi pi-bolt"></i>
        </div>
        <h1 class="text-[22px] font-bold text-slate-900 m-0 tracking-tight">Đăng Nhập Smart ERP</h1>
        <p class="text-xs text-slate-500 m-0">Sử dụng mã nhân viên do công ty cấp</p>
      </div>

      <div class="bg-white border border-slate-200 rounded-[6px] p-6 shadow-none flex flex-col gap-4">
        <Tabs :value="mode" @update:value="switchMode">
          <TabList>
            <Tab value="otp">Đăng nhập bằng OTP</Tab>
            <Tab value="password">Mã nhân viên và mật khẩu</Tab>
          </TabList>
        </Tabs>

        <Message v-if="errorMessage" severity="error" :closable="true" class="rounded-[6px]" @close="errorMessage = ''">
          {{ errorMessage }}
        </Message>
        <Message v-else-if="infoMessage" severity="info" :closable="false" class="rounded-[6px]">
          {{ infoMessage }}
        </Message>

        <!-- Đăng nhập bằng OTP -->
        <form v-if="mode === 'otp'" class="flex flex-col gap-4" @submit.prevent="otpSent ? verifyOtp() : requestOtp()">
          <div class="flex flex-col gap-2">
            <label for="login-code-otp" class="text-xs font-semibold text-slate-700">Mã nhân viên</label>
            <IconField>
              <InputIcon class="pi pi-id-card" />
              <InputText
                id="login-code-otp"
                v-model="employeeCode"
                placeholder="Ví dụ: AC-2005"
                class="w-full rounded-[6px] uppercase"
                autocomplete="username"
                maxlength="10"
                :disabled="otpSent || submitting"
                required
              />
            </IconField>
          </div>

          <template v-if="otpSent">
            <div class="flex flex-col gap-2">
              <label class="text-xs font-semibold text-slate-700">Mã OTP gồm 6 chữ số</label>
              <InputOtp v-model="otp" :length="6" integer-only :disabled="submitting" class="justify-center" @paste.capture="pasteOtp" />
              <span class="text-xs text-slate-500">Mã có hiệu lực 5 phút và chỉ dùng một lần.</span>
            </div>
            <div class="flex justify-between items-center text-xs">
              <Button type="button" label="Đổi mã nhân viên" text size="small" :disabled="submitting" @click="resetOtp" />
              <Button
                type="button"
                :label="resendIn > 0 ? `Gửi lại mã sau ${resendIn}s` : 'Gửi lại mã OTP'"
                text
                size="small"
                :disabled="resendIn > 0 || submitting"
                @click="requestOtp"
              />
            </div>
          </template>

          <Button
            type="submit"
            :label="otpSent ? 'Xác nhận đăng nhập' : 'Gửi mã OTP về email'"
            :icon="otpSent ? 'pi pi-sign-in' : 'pi pi-send'"
            :loading="submitting"
            :disabled="otpSent && otp.length !== 6"
            class="w-full h-10 text-sm rounded-[6px]"
          />
        </form>

        <!-- Đăng nhập bằng mật khẩu -->
        <form v-else class="flex flex-col gap-4" @submit.prevent="loginWithPassword">
          <div class="flex flex-col gap-2">
            <label for="login-code-password" class="text-xs font-semibold text-slate-700">Mã nhân viên</label>
            <IconField>
              <InputIcon class="pi pi-id-card" />
              <InputText
                id="login-code-password"
                v-model="employeeCode"
                placeholder="Ví dụ: AC-2005"
                class="w-full rounded-[6px] uppercase"
                autocomplete="username"
                maxlength="10"
                required
              />
            </IconField>
          </div>
          <div class="flex flex-col gap-2">
            <label for="login-password" class="text-xs font-semibold text-slate-700">Mật khẩu</label>
            <IconField>
              <InputIcon class="pi pi-lock" />
              <InputText
                id="login-password"
                v-model="password"
                type="password"
                placeholder="Nhập mật khẩu"
                class="w-full rounded-[6px]"
                autocomplete="current-password"
                required
              />
            </IconField>
          </div>
          <Button
            type="submit"
            label="Đăng nhập"
            icon="pi pi-sign-in"
            :loading="submitting"
            class="w-full h-10 text-sm rounded-[6px]"
          />
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import InputText from 'primevue/inputtext';
import InputOtp from 'primevue/inputotp';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Message from 'primevue/message';
import Tabs from 'primevue/tabs';
import TabList from 'primevue/tablist';
import Tab from 'primevue/tab';
import { authApi } from '../../api/auth.api';

const emit = defineEmits(['authenticated']);

const mode = ref('otp');
const employeeCode = ref('');
const password = ref('');
const otp = ref('');
const otpSent = ref(false);
const resendIn = ref(0);
const submitting = ref(false);
const errorMessage = ref('');
const infoMessage = ref('');
let timer = null;

const normalizedCode = computed(() => employeeCode.value.trim().toUpperCase());

function switchMode(value) {
  mode.value = value;
  errorMessage.value = '';
  infoMessage.value = '';
  password.value = '';
  resetOtp();
}

// Dán mã từ email: bỏ khoảng trắng, xuống dòng, ký tự lạ và điền đủ 6 số bất kể con trỏ đang ở ô nào
function pasteOtp(event) {
  const digits = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
  event.preventDefault();
  event.stopPropagation();
  if (digits) otp.value = digits;
}

function resetOtp() {
  otpSent.value = false;
  otp.value = '';
  infoMessage.value = '';
  stopCountdown();
}

function startCountdown(seconds) {
  stopCountdown();
  resendIn.value = seconds;
  timer = setInterval(() => {
    resendIn.value -= 1;
    if (resendIn.value <= 0) stopCountdown();
  }, 1000);
}

function stopCountdown() {
  if (timer) clearInterval(timer);
  timer = null;
  resendIn.value = 0;
}

async function requestOtp() {
  if (!normalizedCode.value) {
    errorMessage.value = 'Vui lòng nhập mã nhân viên.';
    return;
  }
  submitting.value = true;
  errorMessage.value = '';
  try {
    const res = await authApi.requestOtp(normalizedCode.value);
    otpSent.value = true;
    otp.value = '';
    infoMessage.value = res.data?.message || 'Mã OTP đã được gửi tới email đăng ký của nhân viên.';
    startCountdown(res.data?.resend_after || 60);
  } catch (error) {
    errorMessage.value = error.message || 'Chưa gửi được mã OTP. Vui lòng thử lại.';
  } finally {
    submitting.value = false;
  }
}

async function verifyOtp() {
  if (otp.value.length !== 6) {
    errorMessage.value = 'Vui lòng nhập đủ 6 chữ số của mã OTP.';
    return;
  }
  submitting.value = true;
  errorMessage.value = '';
  try {
    const res = await authApi.verifyOtp(normalizedCode.value, otp.value);
    emit('authenticated', res);
  } catch (error) {
    errorMessage.value = error.message || 'Mã OTP không đúng hoặc đã hết hạn.';
    otp.value = '';
  } finally {
    submitting.value = false;
  }
}

async function loginWithPassword() {
  if (!normalizedCode.value || !password.value) {
    errorMessage.value = 'Vui lòng nhập đầy đủ mã nhân viên và mật khẩu.';
    return;
  }
  submitting.value = true;
  errorMessage.value = '';
  try {
    const res = await authApi.login(normalizedCode.value, password.value);
    emit('authenticated', res);
  } catch (error) {
    errorMessage.value = error.message || 'Mã nhân viên hoặc mật khẩu không chính xác.';
  } finally {
    submitting.value = false;
  }
}

onBeforeUnmount(stopCountdown);
</script>
