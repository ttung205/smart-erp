export const STATUS_TYPES = {
  ACTIVE: { label: 'Đang hoạt động', severity: 'success' },
  INACTIVE: { label: 'Tạm dừng', severity: 'secondary' },
  PENDING: { label: 'Chờ duyệt', severity: 'warn' },
  COMPLETED: { label: 'Hoàn tất', severity: 'info' },
  CANCELLED: { label: 'Đã hủy', severity: 'danger' }
};

export const PARTNER_TYPES = {
  CUSTOMER: { label: 'Khách hàng', severity: 'info' },
  VENDOR: { label: 'Nhà cung cấp', severity: 'warn' }
};

export const ORDER_STATUSES = {
  DRAFT: { label: 'Bản nháp', severity: 'secondary' },
  CONFIRMED: { label: 'Đã xác nhận', severity: 'info' },
  DELIVERED: { label: 'Đã giao hàng', severity: 'success' },
  CANCELLED: { label: 'Đã hủy', severity: 'danger' }
};
