// Constants and presets for Purchase Hub (SCM / Enterprise Procurement)

export const REQUISITION_STATUS = {
  DRAFT: { label: 'Bản nháp', severity: 'secondary' },
  SUBMITTED: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'info' },
  PARTIALLY_ORDERED: { label: 'Đang lên đơn', severity: 'warn' },
  ORDERED: { label: 'Đã đặt hàng', severity: 'success' },
  REJECTED: { label: 'Từ chối', severity: 'danger' }
};

export const PURCHASE_ORDER_STATUS = {
  DRAFT: { label: 'Bản nháp', severity: 'secondary' },
  SUBMITTED: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'info' },
  PARTIALLY_RECEIVED: { label: 'Nhận 1 phần', severity: 'warn' },
  RECEIVED: { label: 'Đã nhận đủ', severity: 'success' },
  CANCELLED: { label: 'Đã hủy', severity: 'danger' }
};

export const GOODS_RECEIPT_STATUS = {
  DRAFT: { label: 'Bản nháp', severity: 'secondary' },
  CONFIRMED: { label: 'Đã nhập kho', severity: 'success' }
};

export const MATCHING_STATUS = {
  PENDING: { label: 'Chờ đối chiếu', severity: 'secondary' },
  MATCHED: { label: 'Khớp', severity: 'success' },
  DISCREPANCY: { label: 'Lệch', severity: 'danger' },
  POSTED: { label: 'Đã ghi sổ', severity: 'info' }
};

export const DEFAULT_SUPPLIERS = [
  { id: 1, code: 'SUP-LG', name: 'Công ty TNHH LG Electronics Việt Nam', taxCode: '0100109106', contact: 'Trần Văn Long (0903.111.222)' },
  { id: 2, code: 'SUP-ELX', name: 'Công ty TNHH Electrolux Việt Nam', taxCode: '0301444890', contact: 'Nguyễn Thị Mai (0918.333.444)' },
  { id: 3, code: 'SUP-TSB', name: 'Công ty TNHH Sản Phẩm Tiêu Dùng Toshiba VN', taxCode: '3700234567', contact: 'Phạm Hữu Tài (0982.555.666)' },
  { id: 4, code: 'SUP-DKN', name: 'Công ty CP Daikin Air Conditioning Vietnam', taxCode: '0305882341', contact: 'Lê Minh Quân (0938.777.888)' }
];

export const DEFAULT_WAREHOUSES = [
  { id: 1, code: 'WH-HN', name: 'Kho Tổng Hà Nội (Logistics KCN Quang Minh)' },
  { id: 2, code: 'WH-HCM', name: 'Kho Trung Tâm Miền Nam (Khu Công Nghệ Cao Q9)' },
  { id: 3, code: 'WH-DN', name: 'Kho Đà Nẵng (KCN Hòa Cầm, Cẩm Lệ)' }
];
