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
        id: 'catalog',
        path: '/catalog',
        resource: 'catalog',
        permission: 'catalog.view',
        label: 'Danh mục sản phẩm',
        icon: 'pi pi-box'
      },
      {
        id: 'inventory',
        path: '/inventory',
        resource: 'inventory',
        permission: 'inventory.view',
        label: 'Quản lý tồn kho',
        icon: 'pi pi-server'
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
            resource: 'sales',
            permission: 'sales.view',
            label: 'Báo giá',
            icon: 'pi pi-file-edit'
          },
          {
            id: 'order',
            path: '/order',
            resource: 'sales',
            permission: 'sales.view',
            label: 'Đơn bán hàng',
            icon: 'pi pi-shopping-bag'
          },
          {
            id: 'delivery',
            path: '/delivery',
            resource: 'sales',
            permission: 'sales.view',
            label: 'Yêu cầu giao hàng',
            icon: 'pi pi-truck'
          },
          {
            id: 'invoice',
            path: '/invoice',
            resource: 'sales',
            permission: 'sales.view',
            label: 'Chứng từ & Kho',
            icon: 'pi pi-file'
          },
          {
            id: 'accounting',
            path: '/accounting',
            resource: 'sales',
            permission: 'sales.view',
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
        label: 'Đối tác & Kho bãi',
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
