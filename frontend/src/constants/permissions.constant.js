export const SYSTEM_PERMISSIONS = [
  // Phân hệ Tổ chức (Organization)
  { code: 'department.view', desc: 'Xem danh sách và cơ cấu phòng ban (Route: /department)' },
  { code: 'department.manage', desc: 'Thêm, sửa, xóa phòng ban' },
  { code: 'employee.view', desc: 'Xem danh sách nhân viên (Route: /employee)' },
  { code: 'employee.manage', desc: 'Thêm, sửa, khóa nhân viên và gán vai trò' },
  { code: 'role.view', desc: 'Xem danh sách vai trò (Route: /role)' },
  { code: 'role.manage', desc: 'Thêm, sửa, xóa vai trò và chia quyền' },
  { code: 'permission.view', desc: 'Xem danh mục quyền hạn (Route: /permission)' },
  { code: 'permission.manage', desc: 'Thêm, sửa, xóa định nghĩa quyền hạn' },

  // Phân hệ Sản phẩm (Catalog)
  { code: 'catalog.view', desc: 'Xem danh mục sản phẩm (Route: /catalog)' },
  { code: 'catalog.manage', desc: 'Thêm, sửa, xóa sản phẩm và bảng giá' },
  { code: 'customer.view', desc: 'Xem danh sách khách hàng' },
  { code: 'supplier.view', desc: 'Xem danh sách nhà cung cấp' },

  // Phân hệ Kho hàng (Inventory)
  { code: 'inventory.view', desc: 'Xem tồn kho thực tế On-Hand / Reserved (Route: /inventory)' },
  { code: 'inventory.manage', desc: 'Quản trị nghiệp vụ nhập, xuất, điều chuyển kho' },
  { code: 'inventory.receive', desc: 'Nhập kho hàng hóa thực tế' },
  { code: 'inventory.issue', desc: 'Xuất kho hàng hóa thực tế' },
  { code: 'inventory.transfer', desc: 'Điều chuyển kho hàng hóa' },

  // Phân hệ Báo giá (Quotation)
  { code: 'quotation.view', desc: 'Xem danh sách và chi tiết báo giá (Route: /quotation)' },
  { code: 'quotation.create', desc: 'Tạo mới báo giá' },
  { code: 'quotation.update', desc: 'Cập nhật thông tin báo giá' },
  { code: 'quotation.cancel', desc: 'Hủy báo giá' },
  { code: 'quotation.approve', desc: 'Phê duyệt báo giá' },
  { code: 'quotation.manage', desc: 'Quản trị nghiệp vụ báo giá' },

  // Phân hệ Đơn bán hàng (Order)
  { code: 'order.view', desc: 'Xem danh sách và chi tiết đơn bán hàng SO (Route: /order)' },
  { code: 'order.create', desc: 'Tạo mới đơn bán hàng SO' },
  { code: 'order.update', desc: 'Cập nhật thông tin đơn bán hàng SO' },
  { code: 'order.cancel', desc: 'Hủy đơn đặt hàng SO' },
  { code: 'order.approve', desc: 'Phê duyệt đơn bán hàng SO' },
  { code: 'order.manage', desc: 'Quản trị nghiệp vụ đơn bán hàng SO' },

  // Phân hệ Giao hàng & Hóa đơn (Delivery & Invoice)
  { code: 'delivery.view', desc: 'Xem danh sách giao hàng (Route: /delivery)' },
  { code: 'delivery.create', desc: 'Tạo yêu cầu giao hàng Delivery Request' },
  { code: 'delivery-request.approve', desc: 'Phê duyệt yêu cầu giao hàng (Delivery Request)' },
  { code: 'delivery.manage', desc: 'Quản trị giao hàng' },
  { code: 'invoice.view', desc: 'Xem danh sách chứng từ và hóa đơn (Route: /invoice)' },
  { code: 'invoice.create', desc: 'Tạo chứng từ xuất hóa đơn' },
  { code: 'sale-invoice.create', desc: 'Lập chứng từ bán hàng từ yêu cầu giao hàng' },
  { code: 'invoice.manage', desc: 'Quản trị chứng từ và hóa đơn' },

  // Hạ tầng & Outbox
  { code: 'architecture.view', desc: 'Xem kiến trúc phân tán & Outbox (Route: /architecture)' },
  { code: 'architecture.manage', desc: 'Quản trị hạ tầng hệ thống và đồng bộ Outbox' },

  // Phân hệ Mua hàng & Tài chính
  { code: 'purchase.view', desc: 'Xem danh sách và chi tiết đơn mua hàng PO' },
  { code: 'purchase.create', desc: 'Tạo mới đơn mua hàng PO' },
  { code: 'purchase.approve', desc: 'Phê duyệt đơn mua hàng PO' },
  { code: 'item-inquiry.view', desc: 'Xem yêu cầu hỏi hàng của mình' },
  { code: 'item-inquiry.create', desc: 'Tạo và gửi yêu cầu hỏi hàng' },
  { code: 'item-inquiry.process', desc: 'Xử lý yêu cầu và phiếu hỏi hàng nhà cung cấp' },
  { code: 'finance.view', desc: 'Xem báo cáo tài chính và công nợ' },
  { code: 'finance.approve', desc: 'Duyệt thanh toán và hóa đơn' },
  { code: 'ai.use', desc: 'Sử dụng trợ lý AI phân tích dữ liệu' },
  { code: 'users.view', desc: 'Xem danh sách người dùng' },
  { code: 'users.manage', desc: 'Quản trị người dùng và phân quyền' }
];

export const SYSTEM_ROLES = [
  {
    code: 'SUPER_ADMIN',
    name: 'Super Admin',
    desc: 'Toàn quyền hệ thống',
    badge: 'Toàn quyền',
    severity: 'success',
    hasFullPermissions: true,
    sampleEmail: 'admin@erp.local',
    sampleUser: 'Quản Trị Viên Tối Cao'
  },
  {
    code: 'SALE',
    name: 'Sale',
    desc: 'Chuyên viên kinh doanh',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'sale@erp.local',
    sampleUser: 'Chuyên Viên Kinh Doanh'
  },
  {
    code: 'SALE_MANAGER',
    name: 'Sale Manager',
    desc: 'Trưởng phòng kinh doanh',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'sale.manager@erp.local',
    sampleUser: 'Trưởng Phòng Kinh Doanh'
  },
  {
    code: 'PURCHASE',
    name: 'Purchase',
    desc: 'Chuyên viên mua hàng',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'purchase@erp.local',
    sampleUser: 'Chuyên Viên Mua Hàng'
  },
  {
    code: 'PURCHASE_MANAGER',
    name: 'Purchase Manager',
    desc: 'Trưởng phòng mua hàng',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'purchase.manager@erp.local',
    sampleUser: 'Trưởng Phòng Mua Hàng'
  },
  {
    code: 'TECHNICAL',
    name: 'Technical',
    desc: 'Kỹ thuật viên hệ thống',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'technical@erp.local',
    sampleUser: 'Kỹ Thuật Viên Hệ Thống'
  },
  {
    code: 'TECHNICAL_MANAGER',
    name: 'Technical Manager',
    desc: 'Trưởng phòng kỹ thuật',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'technical.manager@erp.local',
    sampleUser: 'Trưởng Phòng Kỹ Thuật'
  },
  {
    code: 'FINANCE_ACCOUNTING',
    name: 'Finance & Accounting',
    desc: 'Chuyên viên tài chính kế toán',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'finance@erp.local',
    sampleUser: 'Chuyên Viên Tài Chính Kế Toán'
  },
  {
    code: 'FINANCE_ACCOUNTING_MANAGER',
    name: 'Finance & Accounting Manager',
    desc: 'Kế toán trưởng / Quản lý tài chính',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'finance.manager@erp.local',
    sampleUser: 'Kế Toán Trưởng'
  },
  {
    code: 'LOGISTIC',
    name: 'Logistic',
    desc: 'Chuyên viên kho vận & điều vận',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'logistic@erp.local',
    sampleUser: 'Chuyên Viên Kho Vận'
  },
  {
    code: 'LOGISTIC_MANAGER',
    name: 'Logistic Manager',
    desc: 'Trưởng phòng kho vận & vận tải',
    badge: 'Chưa phân quyền',
    severity: 'secondary',
    hasFullPermissions: false,
    sampleEmail: 'logistic.manager@erp.local',
    sampleUser: 'Trưởng Phòng Kho Vận'
  }
];
