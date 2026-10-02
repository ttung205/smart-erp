// Constants and presets for Purchase Hub (SCM / Enterprise Procurement)

export const REQUISITION_STATUS = {
  DRAFT: { label: 'Bản nháp', class: 'bg-slate-100 text-slate-700 border border-slate-200' },
  SUBMITTED: { label: 'Chờ duyệt', class: 'bg-amber-50 text-amber-700 border border-amber-200' },
  APPROVED: { label: 'Đã duyệt', class: 'bg-emerald-50 text-emerald-700 border border-emerald-200' },
  PARTIALLY_ORDERED: { label: 'Đang lên đơn PO', class: 'bg-sky-50 text-sky-700 border border-sky-200' },
  ORDERED: { label: 'Đã đặt hàng hết', class: 'bg-slate-100 text-slate-700 border border-slate-300' },
  REJECTED: { label: 'Từ chối duyệt', class: 'bg-rose-50 text-rose-700 border border-rose-200' }
};

export const PURCHASE_ORDER_STATUS = {
  DRAFT: { label: 'Bản nháp', class: 'bg-slate-100 text-slate-700 border border-slate-200' },
  SUBMITTED: { label: 'Chờ duyệt PO', class: 'bg-amber-50 text-amber-700 border border-amber-200' },
  APPROVED: { label: 'Đã duyệt PO', class: 'bg-emerald-50 text-emerald-700 border border-emerald-200' },
  PARTIALLY_RECEIVED: { label: 'Đã nhận một phần', class: 'bg-sky-50 text-sky-700 border border-sky-200' },
  RECEIVED: { label: 'Đã nhận đủ', class: 'bg-slate-100 text-slate-700 border border-slate-300' },
  CANCELLED: { label: 'Đã hủy', class: 'bg-rose-50 text-rose-700 border border-rose-200' }
};

export const GOODS_RECEIPT_STATUS = {
  DRAFT: { label: 'Bản nháp', class: 'bg-slate-100 text-slate-700 border border-slate-200' },
  CONFIRMED: { label: 'Đã nhập kho', class: 'bg-emerald-50 text-emerald-700 border border-emerald-200' }
};

export const MATCHING_STATUS = {
  PENDING: { label: 'Chờ đối chiếu', class: 'bg-slate-100 text-slate-700 border border-slate-200' },
  MATCHED: { label: 'Khớp 3 bên (3-Way OK)', class: 'bg-emerald-50 text-emerald-700 border border-emerald-200' },
  DISCREPANCY: { label: 'Lệch số liệu', class: 'bg-rose-50 text-rose-700 border border-rose-200' },
  POSTED: { label: 'Đã ghi sổ kế toán', class: 'bg-sky-50 text-sky-700 border border-sky-200' }
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
