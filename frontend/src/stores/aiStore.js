import { defineStore } from 'pinia';
import { ref } from 'vue';
import { aiApi } from '../api/ai.api';

/**
 * Trạng thái trợ lý AI dùng chung cho trang /ai và widget nổi:
 * đang chat ở widget bấm "Mở toàn màn hình" vẫn giữ nguyên hội thoại.
 */
export const useAiStore = defineStore('ai', () => {
  const conversationId = ref(null);
  const messages = ref([]);
  const history = ref([]);
  const sending = ref(false);
  const loadingHistory = ref(false);
  const asOf = ref(null);

  async function send(text) {
    const message = (text || '').trim();
    if (!message || sending.value) return;
    messages.value.push({ role: 'user', content: message });
    sending.value = true;
    try {
      const res = await aiApi.chat(message, conversationId.value);
      const isNew = conversationId.value !== res.conversation_id;
      conversationId.value = res.conversation_id;
      asOf.value = res.as_of || asOf.value;
      messages.value.push({ role: 'assistant', content: res.answer, tools: res.tools_used });
      if (isNew) loadHistory();
    } catch (e) {
      messages.value.push({ role: 'assistant', content: e.message || 'Có lỗi xảy ra, vui lòng thử lại.', error: true });
    } finally {
      sending.value = false;
    }
  }

  function newConversation() {
    conversationId.value = null;
    messages.value = [];
  }

  async function loadHistory() {
    loadingHistory.value = true;
    try {
      history.value = await aiApi.conversations();
    } finally {
      loadingHistory.value = false;
    }
  }

  async function loadConversation(id) {
    if (!id) return newConversation();
    if (id === conversationId.value && messages.value.length) return;
    const rows = await aiApi.conversation(id);
    conversationId.value = id;
    messages.value = rows.map((r) => ({ role: r.role, content: r.content }));
  }

  async function removeConversation(id) {
    await aiApi.deleteConversation(id);
    history.value = history.value.filter((c) => c.id !== id);
    if (conversationId.value === id) newConversation();
  }

  function reset() {
    newConversation();
    history.value = [];
    asOf.value = null;
  }

  return {
    conversationId, messages, history, sending, loadingHistory, asOf,
    send, newConversation, loadHistory, loadConversation, removeConversation, reset
  };
});
