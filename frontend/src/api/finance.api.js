import { apiClient } from './client';

const queryString = params => new URLSearchParams(
  Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '')
).toString();

export const financeApi = {
  getCustomerReceivables(params = {}) {
    const qs = queryString(params);
    return apiClient(`/api/v1/finance/customer-receivables${qs ? `?${qs}` : ''}`);
  },
  getAccounts(params = {}) {
    const qs = queryString(params);
    return apiClient(`/api/v1/finance/accounts${qs ? `?${qs}` : ''}`);
  },
  getMasterData() {
    return apiClient('/api/v1/finance/master-data');
  },
  getCashBook(params = {}) {
    const qs = queryString(params);
    return apiClient(`/api/v1/finance/cash-book${qs ? `?${qs}` : ''}`);
  },
  getBankBook(params = {}) {
    const qs = queryString(params);
    return apiClient(`/api/v1/finance/bank-book${qs ? `?${qs}` : ''}`);
  },
  getVouchers(params = {}) {
    const qs = queryString(params);
    return apiClient(`/api/v1/finance/vouchers${qs ? `?${qs}` : ''}`);
  },
  createVoucher(data) {
    return apiClient('/api/v1/finance/vouchers', { method: 'POST', body: JSON.stringify(data) });
  },
  postVoucher(id) {
    return apiClient(`/api/v1/finance/vouchers/${id}/post`, { method: 'POST' });
  }
};
