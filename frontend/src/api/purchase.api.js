import { apiClient } from './client';

export const purchaseApi = {
  checkHealth() {
    return fetch('/api/v1/purchase/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  },

  // 1. Purchase Requisitions (Đề nghị mua sắm - PR)
  getRequisitions(params = {}) {
    const cleanParams = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
    const qs = new URLSearchParams(cleanParams).toString();
    return apiClient(`/api/v1/purchase/requisitions${qs ? '?' + qs : ''}`);
  },

  getRequisitionById(id) {
    return apiClient(`/api/v1/purchase/requisitions/${id}`);
  },

  getAvailablePrItems(warehouseId = null) {
    const qs = warehouseId ? `?warehouseId=${warehouseId}` : '';
    return apiClient(`/api/v1/purchase/requisitions/available-for-order${qs}`);
  },

  createRequisition(data) {
    return apiClient('/api/v1/purchase/requisitions', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  submitRequisition(id) {
    return apiClient(`/api/v1/purchase/requisitions/${id}/submit`, {
      method: 'POST'
    });
  },

  approveRequisition(id, data) {
    return apiClient(`/api/v1/purchase/requisitions/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  rejectRequisition(id, data) {
    return apiClient(`/api/v1/purchase/requisitions/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  // 2. Purchase Orders (Đơn đặt mua - PO)
  getOrders(params = {}) {
    const cleanParams = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
    const qs = new URLSearchParams(cleanParams).toString();
    return apiClient(`/api/v1/purchase/orders${qs ? '?' + qs : ''}`);
  },

  getOrderById(id) {
    return apiClient(`/api/v1/purchase/orders/${id}`);
  },

  createOrder(data) {
    return apiClient('/api/v1/purchase/orders', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  submitOrder(id) {
    return apiClient(`/api/v1/purchase/orders/${id}/submit`, {
      method: 'POST'
    });
  },

  approveOrder(id, data = {}) {
    return apiClient(`/api/v1/purchase/orders/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  cancelOrder(id, data) {
    return apiClient(`/api/v1/purchase/orders/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  closeOrder(id, data = {}) {
    return apiClient(`/api/v1/purchase/orders/${id}/close`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  // 3. Goods Receipts (Phiếu kiểm nhận nhập kho - GRN)
  getReceipts(params = {}) {
    const cleanParams = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
    const qs = new URLSearchParams(cleanParams).toString();
    return apiClient(`/api/v1/purchase/receipts${qs ? '?' + qs : ''}`);
  },

  getReceiptById(id) {
    return apiClient(`/api/v1/purchase/receipts/${id}`);
  },

  createReceipt(data) {
    return apiClient('/api/v1/purchase/receipts', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  confirmReceipt(id) {
    return apiClient(`/api/v1/purchase/receipts/${id}/confirm`, {
      method: 'POST'
    });
  },

  // 4. Purchase Invoices & 3-Way Matching (Hóa đơn mua hàng & Đối chiếu 3 bên)
  getInvoices(params = {}) {
    const cleanParams = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
    const qs = new URLSearchParams(cleanParams).toString();
    return apiClient(`/api/v1/purchase/invoices${qs ? '?' + qs : ''}`);
  },

  getInvoiceById(id) {
    return apiClient(`/api/v1/purchase/invoices/${id}`);
  },

  createInvoice(data) {
    return apiClient('/api/v1/purchase/invoices', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  matchInvoice(id) {
    return apiClient(`/api/v1/purchase/invoices/${id}/match`, {
      method: 'POST'
    });
  },

  postInvoice(id) {
    return apiClient(`/api/v1/purchase/invoices/${id}/post`, {
      method: 'POST'
    });
  },

  // 5. Purchase Returns / Return To Vendor (Trả hàng mua - RTV)
  getReturns(params = {}) {
    const cleanParams = Object.fromEntries(
      Object.entries(params).filter(([_, v]) => v !== null && v !== undefined && v !== '')
    );
    const qs = new URLSearchParams(cleanParams).toString();
    return apiClient(`/api/v1/purchase/returns${qs ? '?' + qs : ''}`);
  },

  getReturnById(id) {
    return apiClient(`/api/v1/purchase/returns/${id}`);
  },

  createReturn(data) {
    return apiClient('/api/v1/purchase/returns', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  updateReturn(id, data) {
    return apiClient(`/api/v1/purchase/returns/${id}`, {
      method: 'PUT',
      body: JSON.stringify(data)
    });
  },

  submitReturn(id, data = null) {
    return apiClient(`/api/v1/purchase/returns/${id}/submit`, {
      method: 'POST',
      body: data ? JSON.stringify(data) : undefined
    });
  },

  approveReturn(id) {
    return apiClient(`/api/v1/purchase/returns/${id}/approve`, {
      method: 'POST'
    });
  },

  dispatchReturn(id) {
    return apiClient(`/api/v1/purchase/returns/${id}/dispatch`, {
      method: 'POST'
    });
  },

  cancelReturn(id, reason) {
    return apiClient(`/api/v1/purchase/returns/${id}/cancel`, {
      method: 'POST',
      body: JSON.stringify({ reason: reason || 'Hủy phiếu trả' })
    });
  }
};
