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
import ProductPage from '../pages/product/index.vue';
import BrandPage from '../pages/brand/index.vue';
import CategoryPage from '../pages/category/index.vue';
import CustomerPage from '../pages/customer/index.vue';
import CustomerFormPage from '../pages/customer/form.vue';
import SupplierPage from '../pages/supplier/index.vue';
import InventoryView from '../pages/inventory/InventoryView.vue';
import MasterPage from '../pages/master/index.vue';
import ArchitecturePage from '../pages/architecture/index.vue';
import ServiceStatusPage from '../pages/system/ServiceStatusPage.vue';

// Sales sub-pages
import QuotationPage from '../pages/quotation/index.vue';
import QuotationFormPage from '../pages/quotation/form.vue';
import OrderPage from '../pages/order/index.vue';
import OrderFormPage from '../pages/order/form.vue';
import DeliveryPage from '../pages/delivery/index.vue';
import InvoicePage from '../pages/invoice/index.vue';
import InvoiceFormPage from '../pages/invoice/form.vue';
import WarehouseRequestSalePage from '../pages/warehouse-request-sale/index.vue';
import AccountingPage from '../pages/accounting/index.vue';

// Purchase sub-pages
import PurchaseRequisitionPage from '../pages/purchase/requisition/index.vue';
import PurchaseRequisitionFormPage from '../pages/purchase/requisition/form.vue';
import PurchaseOrderPage from '../pages/purchase/order/index.vue';
import PurchaseOrderFormPage from '../pages/purchase/order/form.vue';
import PurchaseReceiptPage from '../pages/purchase/receipt/index.vue';
import PurchaseReceiptFormPage from '../pages/purchase/receipt/form.vue';
import PurchaseInvoicePage from '../pages/purchase/invoice/index.vue';
import PurchaseInvoiceFormPage from '../pages/purchase/invoice/form.vue';

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
    path: '/customer',
    name: 'customer',
    component: CustomerPage,
    meta: {
      requiresAuth: true,
      resource: 'catalog',
      permission: 'catalog.view',
      title: 'Quản Lý Khách Hàng'
    }
  },
  {
    path: '/customer/create',
    name: 'customer-create',
    component: CustomerFormPage,
    meta: {
      requiresAuth: true,
      resource: 'catalog',
      permission: 'catalog.view',
      title: 'Thêm Khách Hàng Mới'
    }
  },
  {
    path: '/customer/:id/edit',
    name: 'customer-edit',
    component: CustomerFormPage,
    meta: {
      requiresAuth: true,
      resource: 'catalog',
      permission: 'catalog.view',
      title: 'Chỉnh Sửa Khách Hàng'
    }
  },
  {
    path: '/customer/:id',
    redirect: to => `/customer/${to.params.id}/edit`
  },
  {
    path: '/supplier',
    name: 'supplier',
    component: SupplierPage,
    meta: {
      requiresAuth: true,
      resource: 'catalog',
      permission: 'catalog.view',
      title: 'Quản Lý Nhà Cung Cấp'
    }
  },
  {
    path: '/partner',
    redirect: '/customer'
  },
  {
    path: '/product',
    name: 'product',
    component: ProductPage,
    meta: {
      requiresAuth: true,
      resource: 'catalog',
      permission: 'catalog.view',
      title: 'Quản Lý Sản Phẩm'
    }
  },
  {
    path: '/brand',
    name: 'brand',
    component: BrandPage,
    meta: {
      requiresAuth: true,
      resource: 'catalog',
      permission: 'catalog.view',
      title: 'Quản Lý Thương Hiệu'
    }
  },
  {
    path: '/category',
    name: 'category',
    component: CategoryPage,
    meta: {
      requiresAuth: true,
      resource: 'catalog',
      permission: 'catalog.view',
      title: 'Quản Lý Danh Mục'
    }
  },
  {
    path: '/catalog',
    redirect: '/product'
  },
  {
    path: '/inventory',
    redirect: '/inventory/overview'
  },
  {
    path: '/inventory/overview',
    name: 'inventory-overview',
    component: InventoryView,
    props: { currentSubTab: 'overview' },
    meta: {
      requiresAuth: true,
      resource: 'inventory',
      permission: 'inventory.view',
      title: 'Tổng Quan Kho'
    }
  },
  {
    path: '/inventory/stocks',
    name: 'inventory-stocks',
    component: InventoryView,
    props: { currentSubTab: 'stocks' },
    meta: {
      requiresAuth: true,
      resource: 'inventory',
      permission: 'inventory.view',
      title: 'Bảng Tồn Kho'
    }
  },
  {
    path: '/inventory/reservations',
    name: 'inventory-reservations',
    component: InventoryView,
    props: { currentSubTab: 'reservations' },
    meta: {
      requiresAuth: true,
      resource: 'inventory',
      permission: 'inventory.view',
      title: 'Quản Lý Hàng Giữ Chỗ'
    }
  },
  {
    path: '/inventory/movements',
    name: 'inventory-movements',
    component: InventoryView,
    props: { currentSubTab: 'movements' },
    meta: {
      requiresAuth: true,
      resource: 'inventory',
      permission: 'inventory.view',
      title: 'Sổ Cái Thẻ Kho'
    }
  },
  {
    path: '/purchase',
    redirect: '/purchase/requisitions'
  },
  {
    path: '/purchase/requisitions',
    name: 'purchase-requisitions',
    component: PurchaseRequisitionPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Đề Nghị Mua Sắm (PR)'
    }
  },
  {
    path: '/purchase/requisitions/create',
    name: 'purchase-requisitions-create',
    component: PurchaseRequisitionFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.create',
      title: 'Tạo Đề Nghị Mua Sắm Mới'
    }
  },
  {
    path: '/purchase/requisitions/:id',
    name: 'purchase-requisitions-detail',
    component: PurchaseRequisitionFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Chi Tiết Đề Nghị Mua Sắm'
    }
  },
  {
    path: '/purchase/orders',
    name: 'purchase-orders',
    component: PurchaseOrderPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Đơn Đặt Mua Hàng (PO)'
    }
  },
  {
    path: '/purchase/orders/create',
    name: 'purchase-orders-create',
    component: PurchaseOrderFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.create',
      title: 'Lập Đơn Mua Hàng Mới'
    }
  },
  {
    path: '/purchase/orders/:id',
    name: 'purchase-orders-detail',
    component: PurchaseOrderFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Chi Tiết Đơn Đặt Mua Hàng'
    }
  },
  {
    path: '/purchase/receipts',
    name: 'purchase-receipts',
    component: PurchaseReceiptPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Phiếu Nhập Kho (GRN)'
    }
  },
  {
    path: '/purchase/receipts/create',
    name: 'purchase-receipts-create',
    component: PurchaseReceiptFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.create',
      title: 'Tạo Phiếu Nhập Kho Mới'
    }
  },
  {
    path: '/purchase/receipts/:id',
    name: 'purchase-receipts-detail',
    component: PurchaseReceiptFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Chi Tiết Phiếu Nhập Kho'
    }
  },
  {
    path: '/purchase/invoices',
    name: 'purchase-invoices',
    component: PurchaseInvoicePage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Hóa Đơn & Đối Chiếu 3 Bên'
    }
  },
  {
    path: '/purchase/invoices/create',
    name: 'purchase-invoices-create',
    component: PurchaseInvoiceFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.create',
      title: 'Nhập Hóa Đơn Mua Hàng Mới'
    }
  },
  {
    path: '/purchase/invoices/:id',
    name: 'purchase-invoices-detail',
    component: PurchaseInvoiceFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Chi Tiết Hóa Đơn & Đối Chiếu'
    }
  },
  {
    path: '/sales',
    redirect: '/quotation'
  },
  {
    path: '/quotation',
    name: 'quotation',
    component: QuotationPage,
    meta: {
      requiresAuth: true,
      resource: 'quotation',
      permission: 'quotation.view',
      title: 'Báo Giá Khách Hàng'
    }
  },
  {
    path: '/quotation/create',
    name: 'quotation-create',
    component: QuotationFormPage,
    meta: {
      requiresAuth: true,
      resource: 'quotation',
      permission: 'quotation.create',
      title: 'Tạo Báo Giá Mới'
    }
  },
  {
    path: '/quotation/:id/edit',
    name: 'quotation-edit',
    component: QuotationFormPage,
    meta: {
      requiresAuth: true,
      resource: 'quotation',
      permission: 'quotation.view',
      title: 'Chỉnh Sửa Báo Giá'
    }
  },
  {
    path: '/quotation/:id',
    redirect: to => `/quotation/${to.params.id}/edit`
  },
  {
    path: '/order',
    name: 'order',
    component: OrderPage,
    meta: {
      requiresAuth: true,
      resource: 'order',
      permission: 'order.view',
      title: 'Đơn Bán Hàng'
    }
  },
  {
    path: '/order/create',
    name: 'order-create',
    component: OrderFormPage,
    meta: {
      requiresAuth: true,
      resource: 'order',
      permission: 'order.create',
      title: 'Lập Đơn Bán Hàng Mới'
    }
  },
  {
    path: '/order/:id/edit',
    name: 'order-edit',
    component: OrderFormPage,
    meta: {
      requiresAuth: true,
      resource: 'order',
      permission: 'order.view',
      title: 'Chỉnh Sửa Đơn Bán Hàng'
    }
  },
  {
    path: '/order/:id',
    redirect: to => `/order/${to.params.id}/edit`
  },
  {
    path: '/delivery',
    name: 'delivery',
    component: DeliveryPage,
    meta: {
      requiresAuth: true,
      resource: 'delivery',
      permission: 'delivery.view',
      title: 'Giao Hàng'
    }
  },
  {
    path: '/delivery-request',
    name: 'delivery-request',
    component: DeliveryPage,
    meta: {
      requiresAuth: true,
      resource: 'delivery',
      permission: 'delivery.view',
      title: 'Yêu Cầu Giao Hàng'
    }
  },
  {
    path: '/invoice',
    name: 'invoice',
    component: InvoicePage,
    meta: {
      requiresAuth: true,
      resource: 'invoice',
      permission: 'invoice.view',
      title: 'Chứng Từ Bán Hàng'
    }
  },
  {
    path: '/invoice/create',
    name: 'invoice-create',
    component: InvoiceFormPage,
    meta: {
      requiresAuth: true,
      resource: 'invoice',
      permission: 'invoice.create',
      title: 'Lập Chứng Từ Bán Hàng'
    }
  },
  {
    path: '/invoice/:id/edit',
    name: 'invoice-edit',
    component: InvoiceFormPage,
    meta: {
      requiresAuth: true,
      resource: 'invoice',
      permission: 'invoice.view',
      title: 'Chỉnh Sửa Chứng Từ Bán Hàng'
    }
  },
  {
    path: '/invoice/:id',
    redirect: to => `/invoice/${to.params.id}/edit`
  },
  {
    path: '/warehouse-request-sale',
    name: 'warehouse-request-sale',
    component: WarehouseRequestSalePage,
    meta: {
      requiresAuth: true,
      resource: 'inventory',
      permission: 'inventory.view',
      title: 'Yêu Cầu Xuất Kho Bán Hàng'
    }
  },
  {
    path: '/accounting',
    name: 'accounting',
    component: AccountingPage,
    meta: {
      requiresAuth: true,
      resource: 'finance',
      permission: 'finance.view',
      title: 'Ghi Sổ Kế Toán'
    }
  },
  // Backward compatibility alias redirects
  { path: '/sales/quotation', redirect: '/quotation' },
  { path: '/sales/order', redirect: '/order' },
  { path: '/sales/delivery', redirect: '/delivery' },
  { path: '/sales/invoice', redirect: '/invoice' },
  { path: '/sales/accounting', redirect: '/accounting' },
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
