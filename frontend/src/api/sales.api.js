import { apiClient } from './client';

export const salesApi = {
  checkHealth() {
    return fetch('/api/v1/sales/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  },

  // 1. Quotations
  getQuotations(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/quotations${qs ? '?' + qs : ''}`);
  },

  getQuotationById(id) {
    return apiClient(`/api/v1/sales/quotations/${id}`);
  },

  createQuotation(data) {
    return apiClient('/api/v1/sales/quotations', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateQuotation(id, data) {
    return apiClient(`/api/v1/sales/quotations/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  deleteQuotation(id) {
    return apiClient(`/api/v1/sales/quotations/${id}`, {
      method: 'DELETE'
    });
  },

  submitApproval(id) {
    return apiClient(`/api/v1/sales/quotations/${id}/submit-approval`, {
      method: 'POST'
    });
  },

  approveQuotation(id, approverName = 'admin') {
    return apiClient(`/api/v1/sales/quotations/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify({ approver_name: approverName })
    });
  },

  createSOFromQuotation(id, payload = {}) {
    return apiClient(`/api/v1/sales/quotations/${id}/create-so`, {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  },

  // 2. Orders
  getOrders() {
    return apiClient('/api/v1/sales/orders');
  },

  createOrder(data) {
    return apiClient('/api/v1/sales/orders', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  // 3. Deliveries
  getDeliveries() {
    return apiClient('/api/v1/sales/deliveries');
  },

  createDelivery(data) {
    return apiClient('/api/v1/sales/deliveries', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateDeliveryStatus(id, status) {
    return apiClient(`/api/v1/sales/deliveries/${id}/status`, {
      method: 'PUT',
      body: JSON.stringify({ status })
    });
  },

  // 4. Invoices
  getInvoices() {
    return apiClient('/api/v1/sales/invoices');
  },

  createInvoice(data) {
    return apiClient('/api/v1/sales/invoices', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateInvoiceWarehouseStatus(id, status) {
    return apiClient(`/api/v1/sales/invoices/${id}/warehouse-status`, {
      method: 'PUT',
      body: JSON.stringify({ status })
    });
  },

  postAccounting(id, accountantName = 'kt_truong') {
    return apiClient(`/api/v1/sales/invoices/${id}/post-accounting`, {
      method: 'POST',
      body: JSON.stringify({ posted_by: accountantName })
    });
  }
};
