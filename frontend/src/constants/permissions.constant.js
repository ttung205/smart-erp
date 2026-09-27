export const SYSTEM_PERMISSIONS = [
  { code: 'sales.view', desc: 'Xem danh sách đơn bán hàng' },
  { code: 'sales.create', desc: 'Tạo mới đơn bán hàng' },
  { code: 'sales.approve', desc: 'Phê duyệt đơn bán hàng' },
  { code: 'delivery.create', desc: 'Tạo phiếu giao hàng' },
  { code: 'purchase.view', desc: 'Xem danh sách đơn mua hàng' },
  { code: 'purchase.create', desc: 'Tạo đơn mua hàng' },
  { code: 'purchase.approve', desc: 'Phê duyệt đơn mua hàng' },
  { code: 'inventory.view', desc: 'Xem tồn kho thực tế' },
  { code: 'inventory.receipt', desc: 'Nhập kho hàng hóa' },
  { code: 'inventory.issue', desc: 'Xuất kho hàng hóa' },
  { code: 'inventory.reserve', desc: 'Giữ hàng tạm thời' },
  { code: 'catalog.view', desc: 'Xem danh mục sản phẩm' },
  { code: 'catalog.create', desc: 'Tạo mới sản phẩm' },
  { code: 'catalog.edit', desc: 'Chỉnh sửa thông tin sản phẩm' },
  { code: 'partner.view', desc: 'Xem danh sách đối tác B2B' },
  { code: 'partner.create', desc: 'Thêm mới đối tác B2B' },
  { code: 'warehouse.view', desc: 'Xem danh mục kho bãi' },
  { code: 'users.view', desc: 'Xem danh sách người dùng' },
  { code: 'roles.view', desc: 'Xem ma trận phân quyền' },
  { code: 'ai.use', desc: 'Sử dụng trợ lý AI phân tích' }
];

export const SEED_PERSONAS = [
  {
    name: 'Nguyễn Văn Giám Đốc',
    roleName: 'Ban Giám Đốc (BOD)',
    email: 'director@erp.local',
    icon: 'pi pi-crown',
    badge: 'Toàn quyền',
    severity: 'success'
  },
  {
    name: 'Trần Thị Trưởng Phòng Sales',
    roleName: 'Trưởng Phòng Kinh Doanh',
    email: 'sales_mgr@erp.local',
    icon: 'pi pi-briefcase',
    badge: 'Quản lý Catalog & Sales',
    severity: 'info'
  },
  {
    name: 'Lê Văn Nhân Viên Sales',
    roleName: 'Nhân Viên Kinh Doanh',
    email: 'sales@erp.local',
    icon: 'pi pi-shopping-cart',
    badge: 'Chỉ xem',
    severity: 'warn'
  },
  {
    name: 'Hoàng Văn Thủ Kho',
    roleName: 'Bộ Phận Kho Vận',
    email: 'warehouse@erp.local',
    icon: 'pi pi-box',
    badge: 'Thủ kho',
    severity: 'secondary'
  },
  {
    name: 'Phạm Văn Mua Hàng',
    roleName: 'Phòng Mua Hàng & Cung Ứng',
    email: 'purchase_mgr@erp.local',
    icon: 'pi pi-truck',
    badge: 'Quản lý mua hàng',
    severity: 'info'
  },
  {
    name: 'Quản Trị Viên Hệ Thống',
    roleName: 'Hệ Thống & IT',
    email: 'admin@erp.local',
    icon: 'pi pi-cog',
    badge: 'Quản trị viên',
    severity: 'success'
  }
];
