import { createRouter, createWebHistory } from 'vue-router';
import { storage } from '../utils/storage';
import { parseJwt } from '../utils/jwt';
import { canView } from '../utils/rbac';

// Page components
import LoginPage from '../pages/auth/LoginPage.vue';
import ForbiddenPage from '../pages/auth/ForbiddenPage.vue';
import DepartmentsPage from '../pages/identity/DepartmentsPage.vue';
import UsersPage from '../pages/identity/UsersPage.vue';
import RolesPage from '../pages/identity/RolesPage.vue';
import PermissionsPage from '../pages/identity/PermissionsPage.vue';
import CatalogPage from '../pages/catalog/index.vue';
import InventoryView from '../views/inventory/InventoryView.vue';
import SalesPage from '../pages/sales/index.vue';
import MasterPage from '../pages/master/index.vue';
import ArchitecturePage from '../pages/architecture/index.vue';
import ServiceStatusPage from '../pages/system/ServiceStatusPage.vue';

export const routes = [
  {
    path: '/login',
    name: 'login',
    component: LoginPage,
    meta: { public: true, title: 'Đăng Nhập' }
  },
  {
    path: '/forbidden',
    name: 'forbidden',
    component: ForbiddenPage,
    meta: { public: true, title: 'Không Có Quyền Truy Cập (403)' }
  },
  {
    path: '/',
    redirect: '/employee'
  },
  {
    path: '/department',
    name: 'department',
    component: DepartmentsPage,
    meta: {
      requiresAuth: true,
      resource: 'department',
      permission: 'department.view',
      title: 'Quản Lý Phòng Ban'
    }
  },
  {
    path: '/employee',
    name: 'employee',
    component: UsersPage,
    meta: {
      requiresAuth: true,
      resource: 'employee',
      permission: 'employee.view',
      title: 'Quản Lý Nhân Viên'
    }
  },
  {
    path: '/role',
    name: 'role',
    component: RolesPage,
    meta: {
      requiresAuth: true,
      resource: 'role',
      permission: 'role.view',
      title: 'Quản Lý Vai Trò'
    }
  },
  {
    path: '/permission',
    name: 'permission',
    component: PermissionsPage,
    meta: {
      requiresAuth: true,
      resource: 'permission',
      permission: 'permission.view',
      title: 'Quản Lý Quyền Hạn'
    }
  },
  {
    path: '/catalog',
    name: 'catalog',
    component: CatalogPage,
    meta: {
      requiresAuth: true,
      resource: 'catalog',
      permission: 'catalog.view',
      title: 'Danh Mục Sản Phẩm'
    }
  },
  {
    path: '/inventory',
    name: 'inventory',
    component: InventoryView,
    meta: {
      requiresAuth: true,
      resource: 'inventory',
      permission: 'inventory.view',
      title: 'Quản Lý Tồn Kho'
    }
  },
  {
    path: '/sales',
    name: 'sales',
    component: SalesPage,
    meta: {
      requiresAuth: true,
      resource: 'sales',
      permission: 'sales.view',
      title: 'Quản Lý Bán Hàng'
    }
  },
  {
    path: '/master',
    name: 'master',
    component: MasterPage,
    meta: {
      requiresAuth: true,
      resource: 'master',
      permission: 'master.view',
      title: 'Đối Tác & Kho Bãi'
    }
  },
  {
    path: '/architecture',
    name: 'architecture',
    component: ArchitecturePage,
    meta: {
      requiresAuth: true,
      resource: 'architecture',
      permission: 'architecture.view',
      title: 'Kiến Trúc & Outbox'
    }
  },
  {
    path: '/service-status',
    name: 'service-status',
    component: ServiceStatusPage,
    meta: {
      requiresAuth: true,
      title: 'Trạng Thái Dịch Vụ'
    }
  },
  {
    path: '/status',
    redirect: '/service-status'
  },
  // Backward compatibility alias redirects
  {
    path: '/org-departments',
    redirect: '/department'
  },
  {
    path: '/org-users',
    redirect: '/employee'
  },
  {
    path: '/org-roles',
    redirect: '/role'
  },
  {
    path: '/org-permissions',
    redirect: '/permission'
  },
  {
    path: '/identity',
    redirect: '/employee'
  },
  // Catch all unmatched routes -> redirect to /
  {
    path: '/:pathMatch(.*)*',
    redirect: '/'
  }
];

const router = createRouter({
  history: createWebHistory(),
  routes
});

// Route Guard kiểm tra xác thực và phân quyền theo Route
router.beforeEach((to, from, next) => {
  const token = storage.getToken();
  const user = token ? parseJwt(token) : null;

  // 1. Nếu route yêu cầu đăng nhập
  if (to.meta.requiresAuth) {
    if (!token || !user) {
      return next({ path: '/login', query: { redirect: to.fullPath } });
    }

    // 2. Kiểm tra quyền truy cập theo Route Resource
    if (to.meta.resource) {
      const allowed = canView(to.meta.resource, user);
      if (!allowed) {
        return next({
          path: '/forbidden',
          query: { permission: to.meta.permission || `${to.meta.resource}.view` }
        });
      }
    }
  }

  // 3. Nếu đã đăng nhập mà truy cập trang /login thì chuyển hướng vào trong
  if (to.path === '/login' && token && user) {
    return next('/');
  }

  next();
});

router.afterEach((to) => {
  if (to.meta.title) {
    document.title = `${to.meta.title} - Smart ERP`;
  }
});

export default router;
