// Constants cho phân hệ Yêu cầu hỏi hàng (Sale) và Hỏi hàng nhà cung cấp (Purchase)

export const INQUIRY_REQUEST_STATUS = {
  DRAFT: { label: 'Nháp', severity: 'secondary' },
  WAITING: { label: 'Chờ xử lý', severity: 'warn' },
  IN_PROGRESS: { label: 'Đang hỏi hàng', severity: 'info' },
  RESPONDED: { label: 'Đã phản hồi', severity: 'success' },
  CLOSED: { label: 'Đã đóng', severity: 'contrast' }
};

export const SUPPLIER_INQUIRY_STATUS = {
  DRAFT: { label: 'Nháp', severity: 'secondary' },
  SENT: { label: 'Đã gửi', severity: 'info' },
  COMPLETED: { label: 'Hoàn thành', severity: 'success' }
};

export const getStatusMeta = (statusMap, status) => statusMap[status] || { label: status || '—', severity: 'secondary' };

export const toStatusOptions = (statusMap) => [
  { label: 'Tất cả trạng thái', value: '' },
  ...Object.entries(statusMap).map(([value, meta]) => ({ label: meta.label, value }))
];

// Các trường item dùng chung giữa yêu cầu hỏi hàng và phiếu hỏi hàng nhà cung cấp
export const INQUIRY_ITEM_FIELDS = [
  { key: 'sku', label: 'SKU', width: '130px' },
  { key: 'designation', label: 'Designation *', width: '190px' },
  { key: 'item_description', label: 'Item description', width: '200px' },
  { key: 'brand', label: 'Brand', width: '140px' },
  { key: 'category', label: 'Category', width: '130px' },
  { key: 'com', label: 'COM', width: '110px' },
  { key: 'unit', label: 'Unit', width: '100px' },
  { key: 'quantity', label: 'Quantity *', width: '110px' },
  { key: 'lead_time', label: 'Lead time', width: '130px' },
  { key: 'note', label: 'Note', width: '170px' },
  { key: 'spec', label: 'Spec', width: '190px' }
];

// Phiếu hỏi NCC chọn Brand từ danh mục (brand_id) thay cho text brand của yêu cầu
export const SUPPLIER_ITEM_FIELDS = INQUIRY_ITEM_FIELDS.filter(field => field.key !== 'brand');

export const SUPPLIER_PRICE_FIELDS = [
  { key: 'buying_price', label: 'Buying price' },
  { key: 'vat', label: 'VAT %' },
  { key: 'tax', label: 'Tax %' }
];

export const createBlankRequestItem = () => ({
  sku: '', designation: '', item_description: '', brand: '', category: '',
  com: '', unit: '', quantity: 1, lead_time: '', note: '', spec: ''
});

export const INQUIRY_REQUEST_COLUMNS = [
  { field: 'code', header: 'Mã yêu cầu' },
  { field: 'customer_name', header: 'Khách hàng' },
  { field: 'status', header: 'Trạng thái' },
  { field: 'due_date', header: 'Thời hạn' },
  { field: 'created_at', header: 'Ngày tạo' }
];

export const SUPPLIER_INQUIRY_COLUMNS = [
  { field: 'code', header: 'Mã phiếu hỏi hàng' },
  { field: 'request_code', header: 'Yêu cầu' },
  { field: 'customer_name', header: 'Khách hàng' },
  { field: 'supplier_name', header: 'Nhà cung cấp' },
  { field: 'status', header: 'Trạng thái' },
  { field: 'created_at', header: 'Ngày tạo' }
];

export const formatPrice = (value) => (value == null ? '—' : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value));
