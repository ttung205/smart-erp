import { apiClient } from './client';

export const notificationApi = {
  // Gửi email kèm file đính kèm
  sendEmail(data) {
    return apiClient('/api/v1/notifications/email/send', {
      method: 'POST',
      body: JSON.stringify(data)
    });
  },

  // Xem lịch sử gửi email
  getEmailLogs() {
    return apiClient('/api/v1/notifications/email/logs');
  },

  // Hộp thư thông báo của người đang đăng nhập
  list(params = {}) {
    const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== ''));
    return apiClient(`/api/v1/notifications?${query}`);
  },

  unreadCount() {
    return apiClient('/api/v1/notifications/unread-count');
  },

  markRead(id) {
    return apiClient(`/api/v1/notifications/${id}/read`, { method: 'POST' });
  },

  markAllRead() {
    return apiClient('/api/v1/notifications/read-all', { method: 'POST' });
  },

  // Kiểm tra trạng thái Notification Service
  getHealth() {
    return apiClient('/api/v1/notifications/health');
  }
};
