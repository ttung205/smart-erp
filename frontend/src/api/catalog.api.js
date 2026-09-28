import { apiClient } from './client';

export const catalogApi = {
  checkHealth() {
    return fetch('/api/v1/catalog/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  },

  // 1. Products
  getProducts(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/catalog/products${qs ? '?' + qs : ''}`);
  },

  getProductById(id) {
    return apiClient(`/api/v1/catalog/products/${id}`);
  },

  createProduct(productData) {
    return apiClient('/api/v1/catalog/products', {
      method: 'POST',
      body: JSON.stringify(productData)
    });
  },

  updateProduct(id, productData) {
    return apiClient(`/api/v1/catalog/products/${id}`, {
      method: 'PUT',
      body: JSON.stringify(productData)
    });
  },

  deleteProduct(id) {
    return apiClient(`/api/v1/catalog/products/${id}`, {
      method: 'DELETE'
    });
  },

  importProducts(items) {
    return apiClient('/api/v1/catalog/products/import', {
      method: 'POST',
      body: JSON.stringify({ items })
    });
  },

  // 2. Brands
  getBrands() {
    return apiClient('/api/v1/catalog/brands');
  },

  createBrand(data) {
    return apiClient('/api/v1/catalog/brands', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateBrand(id, data) {
    return apiClient(`/api/v1/catalog/brands/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  deleteBrand(id) {
    return apiClient(`/api/v1/catalog/brands/${id}`, {
      method: 'DELETE'
    });
  },

  // 3. Categories
  getCategories() {
    return apiClient('/api/v1/catalog/categories');
  },

  createCategory(data) {
    return apiClient('/api/v1/catalog/categories', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateCategory(id, data) {
    return apiClient(`/api/v1/catalog/categories/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  deleteCategory(id) {
    return apiClient(`/api/v1/catalog/categories/${id}`, {
      method: 'DELETE'
    });
  },

  // 4. Partners & Warehouses
  getPartners() {
    return apiClient('/api/v1/catalog/partners');
  },

  getWarehouses() {
    return apiClient('/api/v1/catalog/warehouses');
  }
};
