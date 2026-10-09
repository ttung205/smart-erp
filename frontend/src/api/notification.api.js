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

  // Kiểm tra trạng thái Notification Service
  getHealth() {
    return apiClient('/api/v1/notifications/health');
  }
};
