export const menuCategories = [
  {
    category: 'Phân Hệ Nghiệp Vụ',
    items: [
      {
        id: 'identity',
        label: 'Tài khoản & Phân quyền',
        icon: 'pi pi-shield',
        badge: 'RBAC'
      },
      {
        id: 'catalog',
        label: 'Danh mục sản phẩm',
        icon: 'pi pi-box',
        badge: 'Master'
      },
      {
        id: 'inventory',
        label: 'Quản lý tồn kho',
        icon: 'pi pi-server',
        badge: 'Kho hàng'
      },
      {
        id: 'sales',
        label: 'Quản lý bán hàng',
        icon: 'pi pi-shopping-cart',
        badge: 'Đơn hàng'
      },
      {
        id: 'master',
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
        label: 'Kiến trúc Outbox & MQ',
        icon: 'pi pi-share-alt',
        badge: 'Event-Driven'
      }
    ]
  }
];
