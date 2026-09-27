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
            icon: 'pi pi-building',
            badge: 'Cơ cấu'
          },
          {
            id: 'employee',
            path: '/employee',
            resource: 'employee',
            permission: 'employee.view',
            label: 'Nhân viên',
            icon: 'pi pi-users',
            badge: 'Tài khoản'
          },
          {
            id: 'role',
            path: '/role',
            resource: 'role',
            permission: 'role.view',
            label: 'Vai trò',
            icon: 'pi pi-id-card',
            badge: 'Role'
          },
          {
            id: 'permission',
            path: '/permission',
            resource: 'permission',
            permission: 'permission.view',
            label: 'Quyền hạn',
            icon: 'pi pi-key',
            badge: 'Perms'
          }
        ]
      },
      {
        id: 'catalog',
        path: '/catalog',
        resource: 'catalog',
        permission: 'catalog.view',
        label: 'Danh mục sản phẩm',
        icon: 'pi pi-box',
        badge: 'Master'
      },
      {
        id: 'inventory',
        path: '/inventory',
        resource: 'inventory',
        permission: 'inventory.view',
        label: 'Quản lý tồn kho',
        icon: 'pi pi-server',
        badge: 'Kho hàng'
      },
      {
        id: 'sales',
        label: 'Bán hàng',
        icon: 'pi pi-shopping-cart',
        isFolder: true,
        children: [
          {
            id: 'sales-quotation',
            path: '/sales/quotation',
            resource: 'sales',
            permission: 'sales.view',
            label: 'Báo giá',
            icon: 'pi pi-file-edit',
            badge: 'Báo giá'
          },
          {
            id: 'sales-order',
            path: '/sales/order',
            resource: 'sales',
            permission: 'sales.view',
            label: 'Đơn bán hàng',
            icon: 'pi pi-shopping-bag',
            badge: 'Đơn bán'
          },
          {
            id: 'sales-delivery',
            path: '/sales/delivery',
            resource: 'sales',
            permission: 'sales.view',
            label: 'Yêu cầu giao hàng',
            icon: 'pi pi-truck',
            badge: 'Giao hàng'
          },
          {
            id: 'sales-invoice',
            path: '/sales/invoice',
            resource: 'sales',
            permission: 'sales.view',
            label: 'Chứng từ & Kho',
            icon: 'pi pi-file',
            badge: 'Chứng từ'
          },
          {
            id: 'sales-accounting',
            path: '/sales/accounting',
            resource: 'sales',
            permission: 'sales.view',
            label: 'Ghi sổ kế toán',
            icon: 'pi pi-book',
            badge: 'Kế toán'
          }
        ]
      },
      {
        id: 'master',
        path: '/master',
        resource: 'master',
        permission: 'master.view',
        label: 'Đối tác & Kho bãi',
        icon: 'pi pi-building',
        badge: 'B2B'
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
        icon: 'pi pi-pulse',
        badge: 'Health'
      },
      {
        id: 'architecture',
        path: '/architecture',
        resource: 'architecture',
        permission: 'architecture.view',
        label: 'Kiến trúc Outbox & MQ',
        icon: 'pi pi-share-alt',
        badge: 'Event-Driven'
      }
    ]
  }
];
