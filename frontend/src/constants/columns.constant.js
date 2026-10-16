export const PRODUCT_COLUMNS = [
  { field: 'sku', header: 'Mã SKU', sortable: true },
  { field: 'name', header: 'Tên Sản Phẩm', sortable: true },
  { field: 'category_name', header: 'Danh Mục', sortable: false },
  { field: 'unit_code', header: 'Đơn Vị Tính', sortable: false },
  { field: 'gross_price', header: 'Giá Gross', sortable: true, align: 'right' },
  { field: 'cost_price', header: 'Giá Vốn', sortable: true, align: 'right' },
  { field: 'min_stock', header: 'Tồn An Toàn', sortable: true, align: 'center' },
  { field: 'status', header: 'Trạng Thái', sortable: false, align: 'center' }
];

export const PARTNER_COLUMNS = [
  { field: 'code', header: 'Mã Đối Tác', sortable: true },
  { field: 'name', header: 'Tên Đơn Vị / Đối Tác', sortable: true },
  { field: 'type', header: 'Phân Loại', sortable: true, align: 'center' },
  { field: 'credit_limit', header: 'Hạn Mức Nợ', sortable: true, align: 'right' }
];

export const WAREHOUSE_COLUMNS = [
  { field: 'code', header: 'Mã Kho', sortable: true },
  { field: 'name', header: 'Tên Kho Bãi', sortable: true },
  { field: 'address', header: 'Địa Chỉ Vận Hành', sortable: false },
  { field: 'status', header: 'Trạng Thái', sortable: false, align: 'center' }
];

export const SALES_ORDER_COLUMNS = [
  { field: 'order_number', header: 'Mã Đơn Bán', sortable: true },
  { field: 'customer_name', header: 'Khách Hàng', sortable: true },
  { field: 'order_date', header: 'Ngày Tạo', sortable: true },
  { field: 'total_amount', header: 'Tổng Giá Trị', sortable: true, align: 'right' },
  { field: 'status', header: 'Trạng Thái', sortable: false, align: 'center' }
];
