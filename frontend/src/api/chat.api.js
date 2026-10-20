import { apiClient } from './client';
import { storage } from '../utils/storage';

const root = '/api/v1/chat';
const json = (method, data) => ({ method, body: JSON.stringify(data ?? {}) });
const unwrap = (promise) => promise.then((res) => res.data);

export const chatApi = {
  // Hội thoại
  conversations: (params = {}) => unwrap(apiClient(`${root}/conversations?${new URLSearchParams(params)}`)),
  conversation: (id) => unwrap(apiClient(`${root}/conversations/${id}`)),
  unreadCount: () => unwrap(apiClient(`${root}/conversations/unread-count`)),
  openDirect: (userId) => unwrap(apiClient(`${root}/conversations/direct`, json('POST', { user_id: userId }))),
  openWorkThread: (subjectType, subjectId, subjectCode = null, subjectTitle = null) =>
    unwrap(apiClient(`${root}/conversations/work`, json('POST', {
      subject_type: subjectType,
      subject_id: subjectId,
      subject_code: subjectCode,
      subject_title: subjectTitle
    }))),
  createGroup: (title, userIds) => unwrap(apiClient(`${root}/conversations/group`, json('POST', { title, user_ids: userIds }))),
  markRead: (id, messageId) => unwrap(apiClient(`${root}/conversations/${id}/read`, json('POST', { message_id: messageId }))),
  markAllRead: () => unwrap(apiClient(`${root}/conversations/read-all`, json('POST'))),
  setPinned: (id, pinned) => unwrap(apiClient(`${root}/conversations/${id}/pin`, { method: pinned ? 'POST' : 'DELETE' })),
  setMuted: (id, muted) => unwrap(apiClient(`${root}/conversations/${id}/mute`, { method: muted ? 'POST' : 'DELETE' })),

  // Quản lý nhóm
  rename: (id, title) => unwrap(apiClient(`${root}/conversations/${id}`, json('PATCH', { title }))),
  setAvatar: (id, file) => {
    const body = new FormData();
    body.append('avatar', file);
    return unwrap(apiClient(`${root}/conversations/${id}/avatar`, { method: 'POST', body }));
  },
  removeAvatar: (id) => unwrap(apiClient(`${root}/conversations/${id}/avatar`, { method: 'DELETE' })),
  addMembers: (id, userIds) => unwrap(apiClient(`${root}/conversations/${id}/members`, json('POST', { user_ids: userIds }))),
  removeMember: (id, userId) => unwrap(apiClient(`${root}/conversations/${id}/members/${userId}`, { method: 'DELETE' })),
  setRole: (id, userId, role) => unwrap(apiClient(`${root}/conversations/${id}/members/${userId}/role`, json('PUT', { role }))),
  transferOwner: (id, userId) => unwrap(apiClient(`${root}/conversations/${id}/transfer-owner/${userId}`, json('POST'))),
  leave: (id, newOwnerId = null) => unwrap(apiClient(`${root}/conversations/${id}/leave`, json('POST', { new_owner_id: newOwnerId }))),
  dissolve: (id) => unwrap(apiClient(`${root}/conversations/${id}`, { method: 'DELETE' })),

  // Tin nhắn
  messages: (id, params = {}) => unwrap(apiClient(`${root}/conversations/${id}/messages?${new URLSearchParams(params)}`)),
  pins: (id) => unwrap(apiClient(`${root}/conversations/${id}/pins`)),
  search: (id, q) => unwrap(apiClient(`${root}/conversations/${id}/messages/search?${new URLSearchParams({ q })}`)),
  send: (id, { content, replyToId, mentions = [], mentionAll = false, files = [] }) => {
    const body = new FormData();
    if (content) body.append('content', content);
    if (replyToId) body.append('reply_to_id', replyToId);
    mentions.forEach((userId) => body.append('mentions[]', userId));
    if (mentionAll) body.append('mention_all', 'true');
    files.forEach((file) => body.append('attachments[]', file, file.name));
    return unwrap(apiClient(`${root}/conversations/${id}/messages`, { method: 'POST', body }));
  },
  edit: (messageId, content) => unwrap(apiClient(`${root}/messages/${messageId}`, json('PATCH', { content }))),
  recall: (messageId) => unwrap(apiClient(`${root}/messages/${messageId}/recall`, json('POST'))),
  hide: (messageId) => unwrap(apiClient(`${root}/messages/${messageId}/hide`, json('POST'))),
  react: (messageId, emoji) => unwrap(apiClient(`${root}/messages/${messageId}/reactions`, json('POST', { emoji }))),
  setMessagePinned: (messageId, pinned) => unwrap(apiClient(`${root}/messages/${messageId}/pin`, { method: pinned ? 'POST' : 'DELETE' })),
  forward: (messageId, conversationIds) => unwrap(apiClient(`${root}/messages/${messageId}/forward`, json('POST', { conversation_ids: conversationIds }))),

  // Người dùng
  searchUsers: (q = '', limit = 20) => unwrap(apiClient(`${root}/users/search?${new URLSearchParams({ q, limit })}`)),
  user: (userId) => unwrap(apiClient(`${root}/users/${userId}`)),
  contacts: () => unwrap(apiClient(`${root}/contacts`)),
  onlineUsers: () => unwrap(apiClient(`${root}/users/online`)),
  preferences: () => unwrap(apiClient(`${root}/preferences`)),
  setAlias: (userId, alias) => unwrap(apiClient(`${root}/users/${userId}/alias`, json('PUT', { alias }))),

  /** Tải tệp/ảnh có kiểm tra quyền (cần header Authorization nên không dùng thẳng URL). */
  async fetchBlob(url) {
    const response = await fetch(url, { headers: { Authorization: `Bearer ${storage.getToken()}` } });
    if (!response.ok) throw new Error('Không tải được tệp.');
    return response.blob();
  },
  attachmentUrl: (attachmentId) => `${root}/attachments/${attachmentId}`,
  hubUrl: `${root}/hub`
};
