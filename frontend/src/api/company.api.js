import { apiClient } from './client';

const path = '/api/v1/auth/company';

export const companyApi = {
  get() { return apiClient(path); },
  update(data) { return apiClient(path, { method: 'PUT', body: JSON.stringify(data) }); },
  uploadLogo(file) {
    const body = new FormData();
    body.append('logo', file);
    return apiClient(`${path}/logo`, { method: 'POST', body });
  },
  deleteLogo() { return apiClient(`${path}/logo`, { method: 'DELETE' }); }
};
