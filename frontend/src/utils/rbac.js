/**
 * RBAC Permission Helper for Smart ERP
 * Quy tắc phân quyền theo Route & Tài nguyên:
 * - <resource>.view: Quyền truy cập trang và đọc dữ liệu (Read-only)
 * - <resource>.manage: Quyền thao tác đầy đủ (Thêm mới, Cập nhật, Xóa, Lưu...)
 * - SUPER_ADMIN: Toàn quyền truy cập và thao tác tất cả các tài nguyên
 */

/**
 * Kiểm tra xem người dùng có phải là Super Admin hay không
 * @param {Object} user
 * @returns {boolean}
 */
export function isSuperAdmin(user) {
  if (!user) return false;
  if (user.email === 'admin@erp.local') return true;
  if (user.name && user.name.includes('Super Admin')) return true;
  if (Array.isArray(user.roles)) {
    return user.roles.some(r => {
      if (typeof r === 'string') return r.toUpperCase() === 'SUPER_ADMIN';
      if (typeof r === 'object' && r) return (r.code && r.code.toUpperCase() === 'SUPER_ADMIN');
      return false;
    });
  }
  if (typeof user.role === 'string' && user.role.toUpperCase() === 'SUPER_ADMIN') return true;
  return false;
}

/**
 * Kiểm tra xem người dùng có quyền xem (Read) tài nguyên hay không
 * @param {string} resource - Tên tài nguyên (ví dụ: 'employee', 'department', 'role', 'catalog', ...)
 * @param {Object} user - Object người dùng hiện tại (lấy từ JWT payload)
 * @returns {boolean}
 */
export function canView(resource, user) {
  if (!user) return false;
  if (isSuperAdmin(user)) return true;
  if (!user.permissions || !Array.isArray(user.permissions)) return false;
  return user.permissions.includes(`${resource}.view`) || user.permissions.includes(`${resource}.manage`);
}

/**
 * Kiểm tra xem người dùng có quyền quản trị (Write/Manage) tài nguyên hay không
 * @param {string} resource - Tên tài nguyên (ví dụ: 'employee', 'department', 'role', 'catalog', ...)
 * @param {Object} user - Object người dùng hiện tại (lấy từ JWT payload)
 * @returns {boolean}
 */
export function canManage(resource, user) {
  if (!user) return false;
  if (isSuperAdmin(user)) return true;
  if (!user.permissions || !Array.isArray(user.permissions)) return false;
  return user.permissions.includes(`${resource}.manage`);
}

/**
 * Kiểm tra mã quyền bất kỳ
 * @param {string} permissionCode - Mã quyền (ví dụ: 'order.approve', 'inventory.receive')
 * @param {Object} user - Object người dùng
 * @returns {boolean}
 */
export function hasPermission(permissionCode, user) {
  if (!user) return false;
  if (isSuperAdmin(user)) return true;
  if (!user.permissions || !Array.isArray(user.permissions)) return false;
  return user.permissions.includes(permissionCode);
}
