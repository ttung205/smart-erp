export const SYSTEM_PERMISSIONS = [
  { code: 'sales.view', desc: 'Xem danh sách và chi tiết đơn bán hàng SO' },
  { code: 'sales.create', desc: 'Tạo mới báo giá và đơn đặt hàng SO' },
  { code: 'sales.update', desc: 'Cập nhật thông tin đơn bán hàng SO' },
  { code: 'sales.cancel', desc: 'Hủy đơn đặt hàng SO' },
  { code: 'sales.approve', desc: 'Phê duyệt chiết khấu hoặc đơn bán hàng' },
  { code: 'delivery.create', desc: 'Tạo yêu cầu giao hàng Delivery Request' },
  { code: 'purchase.view', desc: 'Xem danh sách và chi tiết đơn mua hàng PO' },
  { code: 'purchase.create', desc: 'Tạo mới đơn mua hàng PO' },
  { code: 'purchase.approve', desc: 'Phê duyệt đơn mua hàng PO' },
  { code: 'inventory.view', desc: 'Xem tồn kho thực tế On-Hand / Reserved' },
  { code: 'inventory.receive', desc: 'Nhập kho hàng hóa thực tế' },
  { code: 'inventory.issue', desc: 'Xuất kho hàng hóa thực tế' },
  { code: 'inventory.transfer', desc: 'Điều chuyển kho hàng hóa' },
  { code: 'catalog.view', desc: 'Xem danh mục sản phẩm và đối tác' },
  { code: 'catalog.create', desc: 'Tạo mới sản phẩm hoặc đối tác' },
  { code: 'catalog.edit', desc: 'Chỉnh sửa sản phẩm và bảng giá' },
  { code: 'finance.view', desc: 'Xem báo cáo tài chính và công nợ' },
  { code: 'finance.approve', desc: 'Duyệt thanh toán và hóa đơn' },
  { code: 'technical.manage', desc: 'Cấu hình kỹ thuật và hạ tầng microservices' },
  { code: 'report.view', desc: 'Xem báo cáo quản trị tổng hợp' },
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
