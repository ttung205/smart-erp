import { apiClient } from './client';

export const inventoryApi = {
  checkHealth() {
    return fetch('/api/v1/inventory/health', { signal: AbortSignal.timeout(3000) })
      .then((res) => res.ok)
      .catch(() => false);
  },

  // 1. Kho bãi (Warehouses)
  getWarehouses() {
    return apiClient('/api/v1/inventory/warehouses');
  },

  createWarehouse(payload) {
    return apiClient('/api/v1/inventory/warehouses', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  },

  updateWarehouse(id, payload) {
    return apiClient(`/api/v1/inventory/warehouses/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload)
    });
  },

  deleteWarehouse(id) {
    return apiClient(`/api/v1/inventory/warehouses/${id}`, {
      method: 'DELETE'
    });
  },

  // 2. Tồn kho (Stocks)
  getStocks(params = {}) {
    const qs = new URLSearchParams({ pageSize: 1000, ...params }).toString();
    return apiClient(`/api/v1/inventory/stocks?${qs}`);
  },

  // Chi tiết đơn hàng giữ chỗ cho 1 sản phẩm
  getReservationOrders(productId, warehouseId = null) {
    const params = { productId };
    if (warehouseId) params.warehouseId = warehouseId;
    const qs = new URLSearchParams(params).toString();
    return apiClient(`/api/v1/inventory/stocks/reservations/orders?${qs}`);
  },

  // 3. Lịch sử giao dịch tồn kho thực tế (Physical Transactions)
  getPhysicalTransactions(params = {}) {
    const qs = new URLSearchParams({ pageSize: 500, ...params }).toString();
    return apiClient(`/api/v1/inventory/stocks/transactions?${qs}`);
  },

  // 4. Lịch sử giao dịch tồn kho khả dụng (Reservation Transactions)
  getReservationTransactions(params = {}) {
    const qs = new URLSearchParams({ pageSize: 500, ...params }).toString();
    return apiClient(`/api/v1/inventory/reservations/transactions?${qs}`);
  },

  // 5. Thao tác biến động trực tiếp (Commands)
  changePhysicalStock(payload) {
    return apiClient('/api/v1/inventory/stocks/change', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  },

  changeReservationStock(payload) {
    return apiClient('/api/v1/inventory/reservations/change', {
      method: 'POST',
      body: JSON.stringify(payload)
    });
  }
};
