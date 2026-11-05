import { apiClient } from './client';

export const authApi = {
  checkHealth() {
    return fetch('/api/v1/auth/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  },

  login(employeeCode, password) {
    return apiClient('/api/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({ employee_code: employeeCode, password })
    });
  },

  requestOtp(employeeCode) {
    return apiClient('/api/v1/auth/login/otp/request', {
      method: 'POST',
      body: JSON.stringify({ employee_code: employeeCode })
    });
  },

  verifyOtp(employeeCode, otp) {
    return apiClient('/api/v1/auth/login/otp/verify', {
      method: 'POST',
      body: JSON.stringify({ employee_code: employeeCode, otp })
    });
  },

  getMe() {
    return apiClient('/api/v1/auth/me');
  }
};
