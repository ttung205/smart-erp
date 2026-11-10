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
  COMPLETED: { label: 'Đã nhận đủ', severity: 'success' },
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

export const PURCHASE_RETURN_STATUS = {
  DRAFT: { label: 'Bản nháp', severity: 'secondary' },
  SUBMITTED: { label: 'Chờ duyệt', severity: 'warn' },
  APPROVED: { label: 'Đã duyệt', severity: 'info' },
  DISPATCHED: { label: 'Đã xuất trả', severity: 'success' },
  CANCELLED: { label: 'Đã hủy', severity: 'danger' }
};

export const PURCHASE_RETURN_REASONS = [
  { value: 'Defective', label: 'Hàng lỗi kỹ thuật / Không đạt tiêu chuẩn (Defective)' },
  { value: 'WrongItem', label: 'Giao sai mã SKU / Sai quy cách (Wrong Item)' },
  { value: 'ExcessQuantity', label: 'Giao thừa số lượng đặt (Excess Quantity)' },
  { value: 'DamagedInTransit', label: 'Móp méo / Hư hại do vận chuyển (Damaged)' },
  { value: 'ExpiredNearDate', label: 'Cận date / Hết hạn bảo hành (Expired)' },
  { value: 'Other', label: 'Lý do khác (Other)' }
];

export const DEFAULT_SUPPLIERS = [
  { id: 1, code: 'MS-1021', name: 'Công ty TNHH LG Electronics Việt Nam', taxCode: '0100109106', contact: 'Trần Văn Long (0903.111.222)' },
  { id: 2, code: 'MS-2045', name: 'Công ty TNHH Electrolux Việt Nam', taxCode: '0301444890', contact: 'Nguyễn Thị Mai (0918.333.444)' },
  { id: 3, code: 'TS-3091', name: 'Công ty TNHH Sản Phẩm Tiêu Dùng Toshiba VN', taxCode: '3700234567', contact: 'Phạm Hữu Tài (0982.555.666)' },
  { id: 4, code: 'MS-4158', name: 'Công ty CP Daikin Air Conditioning Vietnam', taxCode: '0305882341', contact: 'Lê Minh Quân (0938.777.888)' }
];

// Bốn kho chuẩn, khớp với inventory-service (DbInitializer.BaseWarehouses). Chỉ dùng làm giá trị ban đầu
// trước khi tải được danh sách kho từ backend; dữ liệu thật luôn lấy từ GET /inventory/warehouses.
export const DEFAULT_WAREHOUSES = [
  { id: 1, code: 'WH-MAIN', name: 'Kho Chính', location: 'Kho Tổng Miền Bắc', is_active: true, can_sell: true },
  { id: 2, code: 'WH-TECH', name: 'Kho Kĩ Thuật', location: 'Xưởng Kỹ Thuật & Bảo Hành', is_active: true, can_sell: false },
  { id: 3, code: 'WH-PENDING', name: 'Kho Chờ Xử Lý', location: 'Khu Vực Kiểm Hàng & Chờ Phê Duyệt', is_active: true, can_sell: false },
  { id: 4, code: 'WH-TECH-01', name: 'Kho Kỹ thuật - Hàng chờ kiểm tra', location: 'Khu vực kiểm tra chất lượng hàng bán trả lại', is_active: true, can_sell: false }
];
