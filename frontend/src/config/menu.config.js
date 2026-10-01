export const menuCategories = [
  {
    category: 'Phân Hệ Nghiệp Vụ',
    items: [
      {
        id: 'organization',
        label: 'Tổ chức',
        icon: 'pi pi-sitemap',
        isFolder: true,
        children: [
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
            permission: 'catalog.view',
            label: 'Khách hàng',
            icon: 'pi pi-user'
          },
          {
            id: 'supplier',
            path: '/supplier',
            resource: 'catalog',
            permission: 'catalog.view',
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
        id: 'inventory-hub',
        label: 'Quản lý kho',
        icon: 'pi pi-server',
        isFolder: true,
        children: [
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
            id: 'inventory-reservations',
            path: '/inventory/reservations',
            resource: 'inventory',
            permission: 'inventory.view',
            label: 'Hàng giữ chỗ',
            icon: 'pi pi-lock'
          },
          {
            id: 'inventory-movements',
            path: '/inventory/movements',
            resource: 'inventory',
            permission: 'inventory.view',
            label: 'Sổ cái thẻ kho',
            icon: 'pi pi-history'
          }
        ]
      },
      {
        id: 'sales',
        label: 'Bán hàng',
        icon: 'pi pi-shopping-cart',
        isFolder: true,
        children: [
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
            id: 'invoice',
            path: '/invoice',
            resource: 'invoice',
            permission: 'invoice.view',
            label: 'Chứng từ & Kho',
            icon: 'pi pi-file'
          },
          {
            id: 'accounting',
            path: '/accounting',
            resource: 'finance',
            permission: 'finance.view',
            label: 'Ghi sổ kế toán',
            icon: 'pi pi-book'
          }
        ]
      },
      {
        id: 'master',
        path: '/master',
        resource: 'master',
        permission: 'master.view',
        label: 'Kho bãi',
        icon: 'pi pi-building'
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
        icon: 'pi pi-pulse'
      },
      {
        id: 'architecture',
        path: '/architecture',
        resource: 'architecture',
        permission: 'architecture.view',
        label: 'Kiến trúc Outbox & MQ',
        icon: 'pi pi-share-alt'
      }
    ]
  }
];
