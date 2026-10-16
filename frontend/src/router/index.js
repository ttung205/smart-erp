import { createRouter, createWebHistory } from 'vue-router';
import { storage } from '../utils/storage';
import { parseJwt } from '../utils/jwt';
import { canView, hasPermission, isSuperAdmin } from '../utils/rbac';

// Page components
import LoginPage from '../pages/auth/LoginPage.vue';
import ForbiddenPage from '../pages/auth/ForbiddenPage.vue';
import DashboardPage from '../pages/dashboard/index.vue';
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
import ChatPage from '../pages/chat/index.vue';
import ChatFormPage from '../pages/chat/form.vue';

// Sales sub-pages
import QuotationPage from '../pages/quotation/index.vue';
import QuotationFormPage from '../pages/quotation/form.vue';
import OrderPage from '../pages/order/index.vue';
import OrderFormPage from '../pages/order/form.vue';
import DeliveryPage from '../pages/delivery/index.vue';
import DeliveryRequestPage from '../pages/delivery-request/index.vue';
import InvoicePage from '../pages/invoice/index.vue';
import InvoiceFormPage from '../pages/invoice/form.vue';
import VatInvoicePage from '../pages/vat-invoice/index.vue';
import VatInvoiceAdjustmentsPage from '../pages/vat-invoice/adjustments/index.vue';
import VatInvoiceAdjustmentFormPage from '../pages/vat-invoice/adjustments/form.vue';
import WarehouseRequestSalePage from '../pages/warehouse-request-sale/index.vue';
import CustomerReceivablesPage from '../pages/customer-receivables/index.vue';
import FinanceAccountsPage from '../pages/finance/accounts/index.vue';
import GeneralLedgerPage from '../pages/finance/general-ledger/index.vue';
import CashBookPage from '../pages/finance/cash-book/index.vue';
import FinanceVouchersPage from '../pages/finance/vouchers/index.vue';
import FinanceVoucherFormPage from '../pages/finance/vouchers/form.vue';
import ReceivableAllocationPage from '../pages/finance/receivable-allocation/index.vue';
import SaleReturnRequestPage from '../pages/sale-return-request/index.vue';
import SaleReturnRequestFormPage from '../pages/sale-return-request/form.vue';
import SaleReturnPage from '../pages/sale-return/index.vue';
import SaleReturnFormPage from '../pages/sale-return/form.vue';

// E-Office (văn phòng số nội bộ)
import LeaveRequestPage from '../pages/eoffice/leave/index.vue';
import LeaveRequestFormPage from '../pages/eoffice/leave/form.vue';
import LeaveCalendarPage from '../pages/eoffice/leave-calendar/index.vue';
import LeaveBalancesPage from '../pages/eoffice/leave-balances/index.vue';

// Purchase sub-pages
import PurchaseRequisitionPage from '../pages/purchase/requisition/index.vue';
import PurchaseRequisitionFormPage from '../pages/purchase/requisition/form.vue';
import PurchaseOrderPage from '../pages/purchase/order/index.vue';
import PurchaseOrderFormPage from '../pages/purchase/order/form.vue';
import PurchaseReceiptPage from '../pages/purchase/receipt/index.vue';
import PurchaseReceiptFormPage from '../pages/purchase/receipt/form.vue';
import PurchaseInvoicePage from '../pages/purchase/invoice/index.vue';
import PurchaseInvoiceFormPage from '../pages/purchase/invoice/form.vue';
import InquiryRequestIndexPage from '../pages/inquiry-request/index.vue';
import InquiryRequestFormPage from '../pages/inquiry-request/form.vue';
import InquiryRequestViewPage from '../pages/inquiry-request/view.vue';
import InquiryIndexPage from '../pages/inquiry/index.vue';
import InquiryFormPage from '../pages/inquiry/form.vue';
import PurchaseReturnPage from '../pages/purchase/return/index.vue';
import PurchaseReturnFormPage from '../pages/purchase/return/form.vue';

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
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: DashboardPage,
    meta: {
      requiresAuth: true,
      title: 'Bảng Điều Khiển Tổng Quan'
    }
  },
  {
    path: '/chat',
    name: 'chat',
    component: ChatPage,
    meta: {
      requiresAuth: true,
      title: 'Tin Nhắn Nội Bộ'
    }
  },
  {
    path: '/chat/create',
    name: 'chat-create',
    component: ChatFormPage,
    meta: {
      requiresAuth: true,
      title: 'Bắt Đầu Trò Chuyện Mới'
    }
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
      permission: 'customer.view',
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
      permission: 'customer.view',
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
      permission: 'customer.view',
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
      permission: 'supplier.view',
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
    redirect: '/inventory/stocks'
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
  { path: '/sale/item-inquiries', component: InquiryRequestIndexPage, meta: { requiresAuth: true, permission: 'item-inquiry.view', title: 'Yêu cầu hỏi hàng' } },
  { path: '/sale/item-inquiries/create', component: InquiryRequestFormPage, meta: { requiresAuth: true, permission: 'item-inquiry.create', title: 'Lập yêu cầu hỏi hàng' } },
  { path: '/sale/item-inquiries/:id/edit', component: InquiryRequestFormPage, meta: { requiresAuth: true, permission: 'item-inquiry.create', title: 'Sửa yêu cầu hỏi hàng' } },
  { path: '/sale/item-inquiries/:id', component: InquiryRequestViewPage, meta: { requiresAuth: true, permission: 'item-inquiry.view', title: 'Xem yêu cầu hỏi hàng' } },
  { path: '/purchase/item-inquiries', component: InquiryRequestIndexPage, meta: { requiresAuth: true, permission: 'item-inquiry.process', title: 'Yêu cầu hỏi hàng' } },
  { path: '/purchase/item-inquiries/:id/supplier-inquiries/:inquiryId', redirect: to => to.params.inquiryId === 'create' ? `/purchase/inquiries/create?requestId=${to.params.id}` : `/purchase/inquiries/${to.params.inquiryId}` },
  { path: '/purchase/item-inquiries/:id', component: InquiryRequestViewPage, meta: { requiresAuth: true, permission: 'item-inquiry.process', title: 'Xem yêu cầu hỏi hàng' } },
  { path: '/purchase/inquiries', component: InquiryIndexPage, meta: { requiresAuth: true, permission: 'item-inquiry.process', title: 'Hỏi hàng nhà cung cấp' } },
  { path: '/purchase/inquiries/create', component: InquiryFormPage, meta: { requiresAuth: true, permission: 'item-inquiry.process', title: 'Lập phiếu hỏi hàng' } },
  { path: '/purchase/inquiries/:inquiryId', component: InquiryFormPage, meta: { requiresAuth: true, permission: 'item-inquiry.process', title: 'Phiếu hỏi hàng' } },
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
      resource: ['purchase', 'finance'],
      permission: 'purchase.view',
      title: 'Chứng Từ Mua Hàng & Đối Chiếu 3 Bên'
    }
  },
  {
    path: '/purchase/invoices/create',
    name: 'purchase-invoices-create',
    component: PurchaseInvoiceFormPage,
    meta: {
      requiresAuth: true,
      resource: ['purchase', 'finance'],
      permission: 'purchase.create',
      title: 'Lập Chứng Từ Mua Hàng Mới'
    }
  },
  {
    path: '/purchase/invoices/:id',
    name: 'purchase-invoices-detail',
    component: PurchaseInvoiceFormPage,
    meta: {
      requiresAuth: true,
      resource: ['purchase', 'finance'],
      permission: 'purchase.view',
      title: 'Chi Tiết Chứng Từ Mua Hàng & Đối Chiếu 3 Bên'
    }
  },
  {
    path: '/purchase/returns',
    name: 'purchase-returns',
    component: PurchaseReturnPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Trả Hàng Mua (RTV)'
    }
  },
  {
    path: '/purchase/returns/create',
    name: 'purchase-returns-create',
    component: PurchaseReturnFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.create',
      title: 'Lập Phiếu Trả Hàng Mua'
    }
  },
  {
    path: '/purchase/returns/:id',
    name: 'purchase-returns-detail',
    component: PurchaseReturnFormPage,
    meta: {
      requiresAuth: true,
      resource: 'purchase',
      permission: 'purchase.view',
      title: 'Chi Tiết Phiếu Trả Hàng Mua'
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
    component: DeliveryRequestPage,
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
      permission: 'sale-invoice.create',
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
    path: '/vat-invoice',
    name: 'vat-invoice',
    component: VatInvoicePage,
    meta: {
      requiresAuth: true,
      resource: 'invoice',
      permission: 'invoice.view',
      title: 'Quản Lý Xuất Hóa Đơn GTGT'
    }
  },
  { path: '/vat-invoice/adjustments', name: 'vat-invoice-adjustments', component: VatInvoiceAdjustmentsPage,
    meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Hóa đơn điều chỉnh' } },
  { path: '/vat-invoice/adjustments/new', name: 'vat-invoice-adjustment-new', component: VatInvoiceAdjustmentFormPage,
    meta: { requiresAuth: true, resource: 'finance', permission: 'finance.create', title: 'Lập yêu cầu điều chỉnh hóa đơn' } },
  { path: '/vat-invoice/adjustments/:id', name: 'vat-invoice-adjustment-detail', component: VatInvoiceAdjustmentFormPage,
    meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Chi tiết hóa đơn điều chỉnh' } },
  {
    path: '/sale-return-request', name: 'sale-return-request', component: SaleReturnRequestPage,
    meta: { requiresAuth: true, resource: 'sale_return', permission: 'sale_return.view', title: 'Yêu cầu nhập hàng trả lại' }
  },
  {
    path: '/sale-return-request/create', name: 'sale-return-request-create', component: SaleReturnRequestFormPage,
    meta: { requiresAuth: true, resource: 'sale_return', permission: 'sale_return.create', title: 'Tạo yêu cầu nhập hàng trả lại' }
  },
  {
    path: '/sale-return-request/:id', name: 'sale-return-request-detail', component: SaleReturnRequestFormPage,
    meta: { requiresAuth: true, resource: 'sale_return', permission: 'sale_return.view', title: 'Chi tiết yêu cầu nhập trả' }
  },
  {
    path: '/sale-return', name: 'sale-return', component: SaleReturnPage,
    meta: { requiresAuth: true, resource: 'sale_return', permission: 'sale_return.view', title: 'Phiếu trả hàng bán' }
  },
  {
    path: '/sale-return/:id', name: 'sale-return-detail', component: SaleReturnFormPage,
    meta: { requiresAuth: true, resource: 'sale_return', permission: 'sale_return.view', title: 'Chi tiết phiếu trả hàng bán' }
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
    path: '/customer-receivables',
    name: 'customer-receivables',
    component: CustomerReceivablesPage,
    meta: {
      requiresAuth: true,
      resource: 'finance',
      permission: 'finance.view',
      title: 'Công Nợ Khách Hàng'
    }
  },
  { path: '/finance/accounts', name: 'finance-accounts', component: FinanceAccountsPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Hệ thống tài khoản' } },
  { path: '/finance/general-ledger', name: 'finance-general-ledger', component: GeneralLedgerPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Sổ cái' } },
  { path: '/finance/cash-book', name: 'finance-cash-book', component: CashBookPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Sổ quỹ' } },
  { path: '/finance/bank-book', name: 'finance-bank-book', component: CashBookPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Sổ tiền gửi ngân hàng', bookChannel: 'BANK' } },
  { path: '/finance/cash-receipts', name: 'finance-cash-receipts', component: FinanceVouchersPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Phiếu thu', voucherType: 'CASH_RECEIPT' } },
  { path: '/finance/cash-payments', name: 'finance-cash-payments', component: FinanceVouchersPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Phiếu chi', voucherType: 'CASH_PAYMENT' } },
  { path: '/finance/bank-receipts', name: 'finance-bank-receipts', component: FinanceVouchersPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Thu tiền ngân hàng', voucherType: 'BANK_RECEIPT' } },
  { path: '/finance/bank-payments', name: 'finance-bank-payments', component: FinanceVouchersPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Chi tiền ngân hàng', voucherType: 'BANK_PAYMENT' } },
  { path: '/finance/internal-transfers', name: 'finance-internal-transfers', component: FinanceVouchersPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.view', title: 'Chuyển tiền nội bộ', voucherType: 'INTERNAL_TRANSFER' } },
  { path: '/finance/vouchers/new', name: 'finance-voucher-new', component: FinanceVoucherFormPage,
    meta: { requiresAuth: true, permission: 'finance.create', title: 'Lập chứng từ thu chi' } },
  { path: '/finance/receivable-allocation', name: 'finance-receivable-allocation', component: ReceivableAllocationPage, meta: { requiresAuth: true, resource: 'finance', permission: 'finance.approve', title: 'Đối trừ công nợ' } },
  { path: '/accounting', redirect: '/customer-receivables' },
  // E-Office: mọi nhân viên đăng nhập đều dùng được (quyền chi tiết kiểm tra ở eoffice-service)
  { path: '/eoffice', redirect: '/eoffice/leave' },
  { path: '/eoffice/leave', name: 'eoffice-leave', component: LeaveRequestPage, meta: { requiresAuth: true, title: 'Nghỉ phép' } },
  { path: '/eoffice/leave/new', name: 'eoffice-leave-new', component: LeaveRequestFormPage, meta: { requiresAuth: true, title: 'Tạo đơn xin nghỉ phép' } },
  { path: '/eoffice/leave/:id', name: 'eoffice-leave-detail', component: LeaveRequestFormPage, meta: { requiresAuth: true, title: 'Chi tiết đơn nghỉ phép' } },
  // Trang con của Nghỉ phép (đường dẫn tĩnh được ưu tiên hơn /eoffice/leave/:id)
  { path: '/eoffice/leave/calendar', name: 'eoffice-leave-calendar', component: LeaveCalendarPage, meta: { requiresAuth: true, title: 'Lịch nghỉ' } },
  { path: '/eoffice/leave/balances', name: 'eoffice-leave-balances', component: LeaveBalancesPage, meta: { requiresAuth: true, resource: 'eoffice', permission: 'eoffice.manage', title: 'Quản lý phép năm' } },
  { path: '/eoffice/leave-calendar', redirect: '/eoffice/leave/calendar' },
  { path: '/eoffice/leave-balances', redirect: '/eoffice/leave/balances' },
  { path: '/eoffice/approvals', redirect: { path: '/eoffice/leave', query: { scope: 'to_approve' } } },
  // Backward compatibility alias redirects
  { path: '/sales/quotation', redirect: '/quotation' },
  { path: '/sales/order', redirect: '/order' },
  { path: '/sales/delivery', redirect: '/delivery' },
  { path: '/sales/invoice', redirect: '/invoice' },
  { path: '/sales/accounting', redirect: '/customer-receivables' },
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

const sectionRoles = [
  { matches: path => path.startsWith('/purchase/'), roles: ['PURCHASE', 'PURCHASE_MANAGER'] },
  { matches: path => path.startsWith('/inventory/') || path === '/warehouse-request-sale', roles: ['LOGISTIC', 'LOGISTIC_MANAGER'] },
  { matches: path => path.startsWith('/finance/') || path.startsWith('/invoice') || path.startsWith('/vat-invoice') || path === '/customer-receivables', roles: ['FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER'] },
  { matches: path => path.startsWith('/quotation') || path.startsWith('/order') || path === '/delivery', roles: ['SALE', 'SALE_MANAGER'] },
  { matches: path => path === '/delivery-request', roles: ['SALE', 'SALE_MANAGER', 'FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER'] }
];

// Route Guard kiểm tra xác thực và phân quyền theo Route
router.beforeEach((to, from, next) => {
  const token = storage.getToken();
  const user = token ? parseJwt(token) : null;

  // 1. Nếu route yêu cầu đăng nhập
  if (to.meta.requiresAuth) {
    if (!token || !user) {
      return next({ path: '/login', query: { redirect: to.fullPath } });
    }

    const section = sectionRoles.find(item => item.matches(to.path));
    const userRoles = (user.roles || []).map(role => typeof role === 'string' ? role : role.code);
    if (section && !isSuperAdmin(user) && !section.roles.some(role => userRoles.includes(role))) {
      return next({ path: '/forbidden' });
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
    if (to.meta.permission && !hasPermission(to.meta.permission, user)) {
      return next({ path: '/forbidden', query: { permission: to.meta.permission } });
    }
  }

  // 3. Nếu đã đăng nhập mà truy cập trang /login thì chuyển hướng vào trong
  if (to.path === '/login' && token && user) {
    const redirectUrl = to.query.redirect || '/dashboard';
    return next(redirectUrl);
  }

  next();
});

router.afterEach((to) => {
  if (to.meta.title) {
    document.title = `${to.meta.title} - Smart ERP`;
  }
});

export default router;
