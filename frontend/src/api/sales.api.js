import { apiClient } from './client';
import { storage } from '../utils/storage';

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

  rejectQuotation(id, reason = '') {
    return apiClient(`/api/v1/sales/quotations/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason })
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

  getOrderById(id) {
    return apiClient(`/api/v1/sales/orders/${id}`);
  },

  createOrder(data) {
    return apiClient('/api/v1/sales/orders', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateOrder(id, data) {
    return apiClient(`/api/v1/sales/orders/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  deleteOrder(id) {
    return apiClient(`/api/v1/sales/orders/${id}`, {
      method: 'DELETE'
    });
  },

  submitOrderApproval(id) {
    return apiClient(`/api/v1/sales/orders/${id}/submit-approval`, {
      method: 'POST'
    });
  },

  approveOrder(id, approverId = 6) {
    return apiClient(`/api/v1/sales/orders/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify({ approver_id: approverId })
    });
  },

  rejectOrder(id, reason = '') {
    return apiClient(`/api/v1/sales/orders/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ reason })
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

  approveDelivery(id, approverName = 'admin') {
    return this.updateDeliveryStatus(id, 'APPROVED');
  },

  rejectDelivery(id, reason = '') {
    return this.updateDeliveryStatus(id, 'REJECTED');
  },

  // 4. Invoices
  getInvoices() {
    return apiClient('/api/v1/sales/invoices');
  },

  getInvoiceById(id) {
    return apiClient(`/api/v1/sales/invoices/${id}`);
  },

  createInvoice(data) {
    return apiClient('/api/v1/sales/invoices', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  sendInvoiceToWarehouse(id) {
    return apiClient(`/api/v1/sales/invoices/${id}/send-to-warehouse`, {
      method: 'POST'
    });
  },

  syncReserve(id, warehouseName = null) {
    return apiClient(`/api/v1/sales/invoices/${id}/sync-reserve`, {
      method: 'POST',
      body: JSON.stringify({ warehouse_name: warehouseName })
    });
  },

  assignLogistics(id, data) {
    return apiClient(`/api/v1/sales/invoices/${id}/assign-logistics`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  warehousePost(id, data = {}) {
    return apiClient(`/api/v1/sales/invoices/${id}/warehouse-post`, {
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
  },

  // Sales returns
  getSaleReturnRequests(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/sale-return-requests${qs ? '?' + qs : ''}`);
  },

  getEligibleReturnInvoices(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/sale-return-requests/eligible-invoices${qs ? '?' + qs : ''}`);
  },

  getSaleReturnRequest(id) {
    return apiClient(`/api/v1/sales/sale-return-requests/${id}`);
  },

  createSaleReturnRequest(data) {
    return apiClient('/api/v1/sales/sale-return-requests', { method: 'POST', body: JSON.stringify(data) });
  },

  saleReturnAction(id, action, data = {}, method = 'POST') {
    return apiClient(`/api/v1/sales/sale-return-requests/${id}/${action}`, { method, body: JSON.stringify(data) });
  },

  getSaleReturns(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/sale-returns${qs ? '?' + qs : ''}`);
  },

  getSaleReturn(id) {
    return apiClient(`/api/v1/sales/sale-returns/${id}`);
  },

  // 5. VAT Invoices (Hóa Đơn GTGT)
  getNextVatInvoiceNumber() {
    return apiClient('/api/v1/sales/vat-invoices/next-number');
  },

  getVatInvoices(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/vat-invoices${qs ? '?' + qs : ''}`);
  },

  getVatInvoiceById(id) {
    return apiClient(`/api/v1/sales/vat-invoices/${id}`);
  },

  getUnbilledRevenueItems(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/vat-invoices/unbilled-items${qs ? '?' + qs : ''}`);
  },

  createVatInvoice(data) {
    return apiClient('/api/v1/sales/vat-invoices', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  // 6. Reports
  getSaleReportItems(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/reports/sale-items${qs ? '?' + qs : ''}`);
  },

  getRevenueReportItems(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/reports/revenue-items${qs ? '?' + qs : ''}`);
  },

  // 7. Attachments (Tệp đính kèm)
  uploadAttachments(formData) {
    return apiClient('/api/v1/sales/attachments/upload', {
      method: 'POST',
      body: formData
    });
  },

  getAttachments(params = {}) {
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/sales/attachments${qs ? '?' + qs : ''}`);
  },

  deleteAttachment(id) {
    return apiClient(`/api/v1/sales/attachments/${id}`, {
      method: 'DELETE'
    });
  },

  async downloadAttachment(id) {
    const token = storage.getToken();
    const response = await fetch(`/api/v1/sales/attachments/${id}/download`, {
      headers: token ? { Authorization: `Bearer ${token}` } : {}
    });
    if (!response.ok) {
      throw new Error('Không thể tải tệp đính kèm.');
    }
    return response.blob();
  }
};

