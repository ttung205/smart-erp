import { apiClient } from './client';

export const catalogApi = {
  checkHealth() {
    return fetch('/api/v1/catalog/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  },

  getProducts() {
    return apiClient('/api/v1/catalog/products');
  },

  createProduct(productData) {
    return apiClient('/api/v1/catalog/products', {
      method: 'POST',
      body: JSON.stringify(productData)
    });
  },

  importProducts(items) {
    return apiClient('/api/v1/catalog/products/import', {
      method: 'POST',
      body: JSON.stringify({ items })
    });
  },

  getPartners() {
    return apiClient('/api/v1/catalog/partners');
  },

  getWarehouses() {
    return apiClient('/api/v1/catalog/warehouses');
  }
};
