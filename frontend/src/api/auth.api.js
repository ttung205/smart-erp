import { apiClient } from './client';

export const authApi = {
  checkHealth() {
    return fetch('/api/v1/auth/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  },

  login(email, password) {
    return apiClient('/api/v1/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password })
    });
  },

  getMe() {
    return apiClient('/api/v1/auth/me');
  }
};
