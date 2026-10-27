export const menuCategories = [
  {
    category: 'Tổng Quan',
    items: [
      {
        id: 'intro',
        path: '/intro',
        label: 'Giới thiệu',
        icon: 'pi pi-home'
      },
      {
        id: 'ai-assistant',
        path: '/ai',
        label: 'Trợ lý AI',
        icon: 'pi pi-sparkles',
        permission: 'ai.use'
      },
      {
        id: 'analytics-dashboard',
        path: '/analytics',
        label: 'Phân tích & Dự báo BI',
        icon: 'pi pi-chart-line',
        permission: 'ai.use'
      }
    ]
  },
  {
    category: 'Phân Hệ Nghiệp Vụ',
    items: [
      {
        id: 'eoffice-hub',
        label: 'Văn phòng số',
        icon: 'pi pi-briefcase',
        isFolder: true,
        children: [
          { id: 'eoffice-chat',
            path: '/chat',
            label: 'Trò chuyện',
            icon: 'pi pi-comments' },
          { id: 'eoffice-leave',
            path: '/eoffice/leave',
            label: 'Nghỉ phép',
            icon: 'pi pi-calendar-minus' },
          { id: 'eoffice-attendance',
            path: '/eoffice/attendance',
            label: 'Chấm công của tôi',
            icon: 'pi pi-clock' },
          { id: 'eoffice-attendance-requests',
            path: '/eoffice/attendance-requests',
            label: 'Yêu cầu chấm công',
            icon: 'pi pi-file-edit' },
          { id: 'eoffice-payslip',
            path: '/eoffice/payslip',
            label: 'Phiếu lương của tôi',
            icon: 'pi pi-wallet' },
          { id: 'eoffice-attendance-manage',
            path: '/eoffice/attendance-manage',
            anyPermission: ['attendance.view', 'attendance.manage', 'eoffice.manage'],
            label: 'Quản lý chấm công',
            icon: 'pi pi-users' },
          { id: 'eoffice-payroll',
            path: '/eoffice/payroll',
            anyPermission: ['payroll.view', 'payroll.manage', 'payroll.approve', 'payroll.pay'],
            label: 'Bảng lương',
            icon: 'pi pi-money-bill' },
          { id: 'eoffice-purchase-requests',
            path: '/eoffice/purchase-requests',
            label: 'Đề nghị mua sắm',
            icon: 'pi pi-shopping-cart' },
          { id: 'eoffice-payment-requests',
            path: '/eoffice/payment-requests',
            label: 'Đề nghị thanh toán',
            icon: 'pi pi-wallet' },
        ]
      },
      {
        id: 'partner-hub',
        label: 'Đối tác',
        icon: 'pi pi-users',
        isFolder: true,
        children: [
          {
            id: 'customer',
            path: '/customer',
            resource: 'catalog',
            permission: 'customer.view',
            label: 'Khách hàng',
            icon: 'pi pi-user'
          },
          {
            id: 'supplier',
            path: '/supplier',
            resource: 'catalog',
            permission: 'supplier.view',
            label: 'Nhà cung cấp',
            icon: 'pi pi-truck'
          }
        ]
      },
      {
        id: 'product-hub',
        label: 'Sản phẩm',
        icon: 'pi pi-box',
        isFolder: true,
        children: [
          {
            id: 'product',
            path: '/product',
            resource: 'catalog',
            permission: 'catalog.view',
            label: 'Sản phẩm',
            icon: 'pi pi-tag'
          },
          {
            id: 'brand',
            path: '/brand',
            resource: 'catalog',
            permission: 'catalog.view',
            label: 'Thương hiệu',
            icon: 'pi pi-bookmark'
          },
          {
            id: 'category',
            path: '/category',
            resource: 'catalog',
            permission: 'catalog.view',
            label: 'Danh mục',
            icon: 'pi pi-th-large'
          }
        ]
      },
      {
        id: 'sales',
        label: 'Bán hàng',
        icon: 'pi pi-shopping-bag',
        roles: ['SALE', 'SALE_MANAGER'],
        isFolder: true,
        children: [
          { id: 'sale-item-inquiry', path: '/sale/item-inquiries', resource: 'item-inquiry', permission: 'item-inquiry.view', label: 'Yêu cầu hỏi hàng', icon: 'pi pi-search' },
          {
            id: 'quotation',
            path: '/quotation',
            resource: 'quotation',
            permission: 'quotation.view',
            label: 'Báo giá',
            icon: 'pi pi-file-edit'
          },
          {
            id: 'order',
            path: '/order',
            resource: 'order',
            permission: 'order.view',
            label: 'Đơn bán hàng',
            icon: 'pi pi-shopping-bag'
          },
          {
            id: 'delivery',
            path: '/delivery',
            resource: 'delivery',
            permission: 'delivery.view',
            label: 'Giao hàng',
            icon: 'pi pi-truck'
          },
          {
            id: 'delivery-request',
            path: '/delivery-request',
            resource: 'delivery',
            permission: 'delivery.view',
            label: 'Yêu cầu giao hàng',
            icon: 'pi pi-send'
          },
          {
            id: 'sale-return-request',
            path: '/sale-return-request',
            resource: 'sale_return',
            permission: 'sale_return.view',
            label: 'Yêu cầu nhập trả',
            icon: 'pi pi-replay'
          }
        ]
      },
      {
        id: 'purchase-hub',
        label: 'Mua hàng',
        icon: 'pi pi-shopping-cart',
        roles: ['PURCHASE', 'PURCHASE_MANAGER'],
        isFolder: true,
        children: [
          { id: 'purchase-item-inquiry', path: '/purchase/item-inquiries', permission: 'item-inquiry.process', label: 'Yêu cầu hỏi hàng', icon: 'pi pi-inbox' },
          { id: 'purchase-inquiry', path: '/purchase/inquiries', permission: 'item-inquiry.process', label: 'Hỏi hàng nhà cung cấp', icon: 'pi pi-search' },
          {
            id: 'purchase-requisition',
            path: '/purchase/requisitions',
            resource: 'purchase',
            permission: 'purchase.view',
            label: 'Đề nghị mua (PR)',
            icon: 'pi pi-file-plus'
          },
          {
            id: 'purchase-order',
            path: '/purchase/orders',
            resource: 'purchase',
            permission: 'purchase.view',
            label: 'Đơn đặt mua (PO)',
            icon: 'pi pi-shopping-cart'
          },
          {
            id: 'purchase-receipt',
            path: '/purchase/receipts',
            resource: 'purchase',
            permission: 'purchase.view',
            label: 'Nhập kho (GRN)',
            icon: 'pi pi-inbox'
          },
          {
            id: 'purchase-invoice',
            path: '/purchase/invoices',
            resource: 'purchase',
            permission: 'purchase.view',
            label: 'Chứng từ mua hàng',
            icon: 'pi pi-file-import'
          },
          {
            id: 'purchase-return',
            path: '/purchase/returns',
            resource: 'purchase',
            permission: 'purchase.view',
            label: 'Trả hàng mua (RTV)',
            icon: 'pi pi-replay'
          }
        ]
      },
      {
        id: 'accounting-hub',
        label: 'Kế toán',
        icon: 'pi pi-calculator',
        roles: ['FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER'],
        isFolder: true,
        children: [
          {
            id: 'invoice',
            path: '/invoice',
            resource: 'invoice',
            permission: 'invoice.view',
            label: 'Chứng từ bán hàng',
            icon: 'pi pi-file'
          },
          {
            id: 'accounting-purchase-invoice',
            path: '/purchase/invoices',
            resource: 'purchase',
            permission: 'purchase.view',
            label: 'Chứng từ mua hàng',
            icon: 'pi pi-file-import'
          },
          {
            id: 'accounting-delivery-request',
            path: '/delivery-request',
            resource: 'delivery',
            permission: 'delivery.view',
            label: 'Yêu cầu giao hàng',
            icon: 'pi pi-send'
          },
          {
            id: 'vat-invoice',
            path: '/vat-invoice',
            resource: 'invoice',
            permission: 'invoice.view',
            label: 'Hóa đơn GTGT (VAT)',
            icon: 'pi pi-receipt'
          },
          {
            id: 'vat-invoice-adjustments', path: '/vat-invoice/adjustments',
            resource: 'finance', permission: 'finance.view',
            label: 'Hóa đơn điều chỉnh', icon: 'pi pi-file-edit'
          },
          {
            id: 'customer-receivables',
            path: '/customer-receivables',
            resource: 'finance',
            permission: 'finance.view',
            label: 'Công nợ khách hàng',
            icon: 'pi pi-wallet'
          },
          {
            id: 'finance-general-ledger', path: '/finance/general-ledger',
            resource: 'finance', permission: 'finance.view', label: 'Sổ cái', icon: 'pi pi-book'
          },
          {
            id: 'receivable-allocation',
            path: '/finance/receivable-allocation',
            resource: 'finance',
            permission: 'finance.approve',
            label: 'Đối trừ công nợ',
            icon: 'pi pi-arrows-h'
          },
          {
            id: 'accounting-sale-return-request',
            path: '/sale-return-request',
            resource: 'sale_return',
            permission: 'sale_return.view',
            label: 'Yêu cầu nhập trả',
            icon: 'pi pi-replay'
          },
          {
            id: 'sale-return',
            path: '/sale-return',
            resource: 'sale_return',
            permission: 'sale_return.view',
            label: 'Phiếu trả hàng bán',
            icon: 'pi pi-file-check'
          }
        ]
      },
      {
        id: 'cash-hub',
        label: 'Quỹ',
        icon: 'pi pi-wallet',
        roles: ['FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER'],
        isFolder: true,
        children: [
          { id: 'cash-book', path: '/finance/cash-book', resource: 'finance', permission: 'finance.view', label: 'Sổ quỹ', icon: 'pi pi-book' },
          { id: 'cash-customer-receipt', path: '/finance/cash-receipts', resource: 'finance', permission: 'finance.view', label: 'Thu tiền khách hàng', icon: 'pi pi-users' },
          { id: 'cash-receipt', path: '/finance/cash-receipts', resource: 'finance', permission: 'finance.view', label: 'Phiếu thu', icon: 'pi pi-arrow-down-left' },
          { id: 'cash-payment', path: '/finance/cash-payments', resource: 'finance', permission: 'finance.view', label: 'Phiếu chi', icon: 'pi pi-arrow-up-right' }
        ]
      },
      {
        id: 'bank-hub',
        label: 'Ngân hàng',
        icon: 'pi pi-building-columns',
        roles: ['FINANCE_ACCOUNTING', 'FINANCE_ACCOUNTING_MANAGER'],
        isFolder: true,
        children: [
          { id: 'bank-book', path: '/finance/bank-book', resource: 'finance', permission: 'finance.view', label: 'Sổ tiền gửi', icon: 'pi pi-book' },
          { id: 'bank-customer-receipt', path: '/finance/bank-receipts', resource: 'finance', permission: 'finance.view', label: 'Thu tiền khách hàng', icon: 'pi pi-users' },
          { id: 'bank-receipt', path: '/finance/bank-receipts', resource: 'finance', permission: 'finance.view', label: 'Chứng từ thu', icon: 'pi pi-arrow-down-left' },
          { id: 'bank-payment', path: '/finance/bank-payments', resource: 'finance', permission: 'finance.view', label: 'Chứng từ chi', icon: 'pi pi-arrow-up-right' },
          { id: 'bank-transfer', path: '/finance/internal-transfers', resource: 'finance', permission: 'finance.view', label: 'Chuyển tiền nội bộ', icon: 'pi pi-arrow-right-arrow-left' },
          { id: 'finance-accounts', path: '/finance/accounts', resource: 'finance', permission: 'finance.view', label: 'Hệ thống tài khoản', icon: 'pi pi-sitemap' }
        ]
      },
      {
        id: 'inventory-hub',
        label: 'Quản lý kho',
        icon: 'pi pi-server',
        roles: ['LOGISTIC', 'LOGISTIC_MANAGER'],
        isFolder: true,
        children: [
          {
            id: 'sales-warehouse-dispatch',
            path: '/warehouse-request-sale',
            resource: 'inventory',
            permission: 'inventory.view',
            label: 'Yêu cầu xuất kho',
            icon: 'pi pi-box'
          },
          {
            id: 'inventory-overview',
            path: '/inventory/overview',
            resource: 'inventory',
            permission: 'inventory.view',
            label: 'Tổng quan kho',
            icon: 'pi pi-chart-bar'
          },
          {
            id: 'inventory-stocks',
            path: '/inventory/stocks',
            resource: 'inventory',
            permission: 'inventory.view',
            label: 'Bảng tồn kho',
            icon: 'pi pi-box'
          },
          {
            id: 'inventory-movements',
            path: '/inventory/movements',
            resource: 'inventory',
            permission: 'inventory.view',
            label: 'Lịch sử tồn kho',
            icon: 'pi pi-history'
          }
        ]
      },
      {
        id: 'organization',
        label: 'Tổ chức',
        icon: 'pi pi-sitemap',
        isFolder: true,
        children: [
          { id: 'company', path: '/company', label: 'Công ty', icon: 'pi pi-building-columns' },
          {
            id: 'department',
            path: '/department',
            resource: 'department',
            permission: 'department.view',
            label: 'Phòng ban',
            icon: 'pi pi-building'
          },
          {
            id: 'employee',
            path: '/employee',
            resource: 'employee',
            permission: 'employee.view',
            label: 'Nhân viên',
            icon: 'pi pi-users'
          },
          {
            id: 'role',
            path: '/role',
            resource: 'role',
            permission: 'role.view',
            label: 'Vai trò',
            icon: 'pi pi-id-card'
          },
          {
            id: 'permission',
            path: '/permission',
            resource: 'permission',
            permission: 'permission.view',
            label: 'Quyền hạn',
            icon: 'pi pi-key'
          }
        ]
      }
    ]
  },
  {
    category: 'Hạ Tầng & Tích Hợp',
    items: [
      {
        id: 'service-status',
        path: '/service-status',
        label: 'Trạng thái dịch vụ',
        icon: 'pi pi-info-circle'
      }
    ]
  }
];
