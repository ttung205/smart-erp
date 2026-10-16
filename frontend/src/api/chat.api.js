import { apiClient } from './client';

export const chatApi = {
  /**
   * Lấy danh sách các cuộc hội thoại (phòng ban & 1-1)
   */
  async getConversations() {
    const res = await apiClient('/api/v1/chat/conversations');
    return res.data || [];
  },

  /**
   * Lấy chi tiết cuộc hội thoại
   */
  async getConversationById(id) {
    const res = await apiClient(`/api/v1/chat/conversations/${id}`);
    return res.data;
  },

  /**
   * Lấy lịch sử tin nhắn của một cuộc hội thoại (hỗ trợ phân trang)
   */
  async getMessages(conversationId, params = {}) {
    const query = new URLSearchParams();
    if (params.page) query.append('page', params.page);
    if (params.pageSize) query.append('pageSize', params.pageSize);
    if (params.beforeMessageId) query.append('beforeMessageId', params.beforeMessageId);

    const queryString = query.toString() ? `?${query.toString()}` : '';
    const res = await apiClient(`/api/v1/chat/conversations/${conversationId}/messages${queryString}`);
    return {
      messages: res.data || [],
      meta: res.meta || {}
    };
  },

  /**
   * Gửi tin nhắn qua REST API
   */
  async sendMessage(conversationId, data) {
    const res = await apiClient(`/api/v1/chat/conversations/${conversationId}/messages`, {
      method: 'POST',
      body: JSON.stringify(data)
    });
    return res.data;
  },

  /**
   * Khởi tạo hoặc tìm cuộc trò chuyện 1-1
   */
  async getOrCreateDirectConversation(recipientId, recipientName = '') {
    const res = await apiClient('/api/v1/chat/conversations/direct', {
      method: 'POST',
      body: JSON.stringify({
        recipient_id: recipientId,
        recipient_name: recipientName
      })
    });
    return res.data;
  },

  /**
   * Đánh dấu tin nhắn đã đọc
   */
  async markAsRead(messageId) {
    const res = await apiClient(`/api/v1/chat/messages/${messageId}/read`, {
      method: 'POST'
    });
    return res.data;
  },

  /**
   * Lấy danh sách ID người dùng đang online
   */
  async getOnlineUsers() {
    const res = await apiClient('/api/v1/chat/users/online');
    return res.data || [];
  }
};
