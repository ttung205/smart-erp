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
        path: '/sales',
        resource: 'sales',
        permission: 'sales.view',
        label: 'Quản lý bán hàng',
        icon: 'pi pi-shopping-cart',
        badge: 'Đơn hàng'
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
