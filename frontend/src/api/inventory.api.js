import { apiClient } from './client';

export const inventoryApi = {
  checkHealth() {
    return fetch('/api/v1/inventory/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  },

  getWarehouses() {
    return apiClient('/api/v1/inventory/warehouses');
  },

  getStocks() {
    return apiClient('/api/v1/inventory/stocks');
  },

  reserveStock(payload) {
    return apiClient('/api/v1/inventory/stocks/reserve', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  },

  issueStock(payload) {
    return apiClient('/api/v1/inventory/stocks/issue', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }
};
