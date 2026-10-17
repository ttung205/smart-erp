import { apiClient } from './client';

const root = '/api/v1/ai';
const unwrap = (promise) => promise.then((res) => res.data);

export const aiApi = {
  chat: (message, conversationId = null) =>
    unwrap(apiClient(`${root}/chat`, {
      method: 'POST',
      body: JSON.stringify({ message, conversation_id: conversationId })
    })),
  conversations: () => unwrap(apiClient(`${root}/conversations`)),
  conversation: (id) => unwrap(apiClient(`${root}/conversations/${id}`)),
  deleteConversation: (id) => unwrap(apiClient(`${root}/conversations/${id}`, { method: 'DELETE' }))
};
