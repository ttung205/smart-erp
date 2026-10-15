import { computed, reactive } from 'vue';
import { io } from 'socket.io-client';
import { notificationApi } from '../api/notification.api';
import { useStockStore } from '../stores/stockStore';

const PAGE_SIZE = 20;

// State dùng chung toàn app (chuông trên topbar, trang danh sách...) — 1 kết nối socket duy nhất
const state = reactive({
  items: [],
  unreadCount: 0,
  loading: false,
  hasMore: true,
  connected: false
});

let socket = null;
const listeners = new Set();

function upsert(notification) {
  const index = state.items.findIndex((item) => item.id === notification.id);
  if (index >= 0) state.items.splice(index, 1, notification);
  else state.items.unshift(notification);
}

async function refresh() {
  state.loading = true;
  try {
    const res = await notificationApi.list({ limit: PAGE_SIZE });
    state.items = res.data || [];
    state.unreadCount = res.meta?.unread_count ?? 0;
    state.hasMore = state.items.length === PAGE_SIZE;
  } finally {
    state.loading = false;
  }
}

async function loadMore() {
  if (state.loading || !state.hasMore || state.items.length === 0) return;
  state.loading = true;
  try {
    const res = await notificationApi.list({ limit: PAGE_SIZE, before_id: state.items[state.items.length - 1].id });
    state.items.push(...(res.data || []));
    state.hasMore = (res.data || []).length === PAGE_SIZE;
  } finally {
    state.loading = false;
  }
}

async function markRead(notification) {
  if (notification.read_at) return;
  const res = await notificationApi.markRead(notification.id);
  upsert(res.data);
  state.unreadCount = res.meta?.unread_count ?? state.unreadCount;
}

async function markAllRead() {
  await notificationApi.markAllRead();
  const now = new Date().toISOString();
  state.items = state.items.map((item) => (item.status === 'UNREAD' ? { ...item, status: 'READ', read_at: now } : item));
  state.unreadCount = 0;
}

/** Mở kết nối realtime; socket chỉ báo hiệu, mỗi lần (re)connect đều đồng bộ lại qua REST. */
function connect(token) {
  if (socket || !token) return;
  socket = io({ path: '/api/v1/notifications/socket.io', auth: { token }, transports: ['websocket', 'polling'] });

  socket.on('connect', () => {
    state.connected = true;
    refresh().catch(() => {});
  });
  socket.on('disconnect', () => {
    state.connected = false;
  });
  socket.on('notification:new', (notification) => {
    upsert(notification);
    listeners.forEach((listener) => listener(notification));
  });
  socket.on('notification:resolved', ({ id }) => {
    const item = state.items.find((n) => n.id === id);
    if (item) upsert({ ...item, status: 'RESOLVED', resolved_at: new Date().toISOString() });
  });
  socket.on('notification:unread_count', ({ unread_count: count }) => {
    state.unreadCount = count;
  });

  // Giai đoạn 3: Real-time Catalog & Stock Sync
  socket.on('catalog:product_created', (product) => {
    try {
      const stockStore = useStockStore();
      stockStore.syncNewProduct(product);
      window.dispatchEvent(new CustomEvent('catalog:product_created', { detail: product }));
    } catch (e) {
      console.error('[Realtime] Lỗi xử lý catalog:product_created:', e);
    }
  });

  socket.on('inventory:stock_updated', (payload) => {
    try {
      const stockStore = useStockStore();
      stockStore.applyStockUpdated(payload);
      window.dispatchEvent(new CustomEvent('inventory:stock_updated', { detail: payload }));
    } catch (e) {
      console.error('[Realtime] Lỗi xử lý inventory:stock_updated:', e);
    }
  });
}

function disconnect() {
  socket?.disconnect();
  socket = null;
  state.items = [];
  state.unreadCount = 0;
  state.connected = false;
  listeners.clear();
}

/** Đăng ký nhận thông báo mới (vd hiện toast); trả về hàm hủy đăng ký. */
function onNew(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function useNotifications() {
  return {
    state,
    hasUnread: computed(() => state.unreadCount > 0),
    connect,
    disconnect,
    refresh,
    loadMore,
    markRead,
    markAllRead,
    onNew
  };
}
